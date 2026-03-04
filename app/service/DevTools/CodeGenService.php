<?php

namespace app\service\DevTools;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\lib\Ai\CodeGenParser;
use app\lib\Ai\CodeGenTools;
use app\lib\Ai\NeuronAgentFactory;
use app\process\Monitor;
use app\service\Ai\AiChatService;
use support\Log;

/**
 * AI 代码生成服务 — 多 Agent 编排
 * Phase 1: 研究员 Agent（tool calling）收集上下文
 * Phase 2: 程序员 Agent（最强代码模型）生成代码
 * Phase 3: 后端解析标记并执行（建表/写文件）
 * Phase 4: 审查员 Agent（可选）自动 Code Review
 * Phase 5: 测试员 Agent（可选）自动生成测试用例
 */
class CodeGenService
{
    private AiAgentsDep $agentsDep;
    private AiModelsDep $modelsDep;
    private AiConversationsDep $conversationsDep;
    private AiMessagesDep $messagesDep;
    private AiRunsDep $runsDep;
    private AiRunStepsDep $runStepsDep;

    public function __construct()
    {
        $this->agentsDep        = new AiAgentsDep();
        $this->modelsDep        = new AiModelsDep();
        $this->conversationsDep = new AiConversationsDep();
        $this->messagesDep      = new AiMessagesDep();
        $this->runsDep          = new AiRunsDep();
        $this->runStepsDep      = new AiRunStepsDep();
    }

    /**
     * 执行完整的多 Agent 代码生成流程
     */
    public function run(string $userMessage, int $userId, ?int $conversationId, callable $onChunk, bool $allowOverwrite = false, bool $enableReview = false, bool $enableTest = false): void
    {
        $startTime = hrtime(true);
        $stepNo    = 0;
        $runId     = 0;

        // 获取研究员 agent 以便创建会话时绑定
        $researcherAgent = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_RESEARCH);

        // 创建或复用会话
        $isNewConversation = false;
        if (!$conversationId) {
            $isNewConversation = true;
            $conversationId = $this->conversationsDep->add([
                'user_id'         => $userId,
                'agent_id'        => $researcherAgent ? $researcherAgent->id : 0,
                'title'           => mb_substr($userMessage, 0, 50),
                'last_message_at' => date('Y-m-d H:i:s'),
                'status'          => CommonEnum::YES,
                'is_del'          => CommonEnum::NO,
            ]);
            $onChunk('conversation', ['conversation_id' => $conversationId]);
        }

        // 加载历史消息（必须在插入用户消息之前，否则首轮判断会失效）
        $history = $isNewConversation ? [] : $this->loadHistory($conversationId);

        // 保存用户消息
        $userMessageId = $this->messagesDep->add([
            'conversation_id' => $conversationId,
            'role'            => AiEnum::ROLE_USER,
            'content'         => $userMessage,
            'is_del'          => CommonEnum::NO,
        ]);

        // 预加载所有参与的 Agent 及其模型信息（多 Agent 协同）
        $requestId  = 'codegen_' . uniqid('', true);
        $coderAgent   = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_CODER);
        $reviewAgent  = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_REVIEW);
        $testAgent    = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_TEST);

        // 获取各 Agent 的模型代码
        $getModelCode = function (?object $agent): string {
            if (!$agent) return '';
            $model = $this->modelsDep->get((int)$agent->model_id);
            return $model->model_code ?? '';
        };
        $researcherModelCode = $getModelCode($researcherAgent);
        $coderModelCode      = $getModelCode($coderAgent);
        $reviewModelCode     = $getModelCode($reviewAgent);
        $testModelCode       = $getModelCode($testAgent);

        // 运行记录用程序员 Agent（主要工作者），各步骤单独记录执行 Agent
        $runId = $this->runsDep->add([
            'request_id'       => $requestId,
            'user_id'          => $userId,
            'agent_id'         => $coderAgent ? $coderAgent->id : ($researcherAgent ? $researcherAgent->id : 0),
            'conversation_id'  => $conversationId,
            'user_message_id'  => $userMessageId,
            'run_status'       => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot'   => $coderModelCode,
            'is_del'           => CommonEnum::NO,
        ]);

        try {
            $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

            // Phase 1: 研究员收集上下文（首轮对话才执行，迭代轮次复用历史上下文）
            $context = [];
            if (empty($history)) {
                $phaseStart = hrtime(true);
                $onChunk('phase', ['phase' => 'researching', 'msg' => '正在分析需求，收集项目信息...']);
                $researchResult = $this->gatherContext($userMessage, $onChunk);
                $context = $researchResult['context'];
                $phaseUsage = $researchResult['usage'];
                self::accumulateUsage($totalUsage, $phaseUsage);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_TOOL_CALL, [
                    'phase' => 'researching', 'context_keys' => array_keys($context),
                    'prompt_tokens' => $phaseUsage['prompt_tokens'], 'completion_tokens' => $phaseUsage['completion_tokens'], 'total_tokens' => $phaseUsage['total_tokens'],
                ], $this->elapsedMs($phaseStart), $researcherAgent ? (int)$researcherAgent->id : 0, $researcherModelCode);
            }

            // 暂停文件监控，防止写入 PHP 文件后 Monitor 重启 Worker 导致 SSE 中断
            Monitor::pause();

            // Phase 2: 程序员生成代码
            $phaseStart = hrtime(true);
            $onChunk('phase', ['phase' => 'generating', 'msg' => '正在生成代码...']);
            $codeResult = $this->generateCode($context, $userMessage, $history, $onChunk, $allowOverwrite);
            $aiContent = $codeResult['content'];
            $phaseUsage = $codeResult['usage'];
            self::accumulateUsage($totalUsage, $phaseUsage);
            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                'phase' => 'generating', 'content_length' => mb_strlen($aiContent),
                'prompt_tokens' => $phaseUsage['prompt_tokens'], 'completion_tokens' => $phaseUsage['completion_tokens'], 'total_tokens' => $phaseUsage['total_tokens'],
            ], $this->elapsedMs($phaseStart), $coderAgent ? (int)$coderAgent->id : 0, $coderModelCode);

            // 代码生成失败时中断（agent/model 不可用等场景）
            if (empty($aiContent)) {
                throw new \RuntimeException('代码生成失败：程序员 Agent 未返回有效内容');
            }

            // 保存 AI 回复
            $metaJson = !empty($context) ? json_encode(['context' => $context], JSON_UNESCAPED_UNICODE) : null;
            $assistantMessageId = $this->messagesDep->add([
                'conversation_id' => $conversationId,
                'role'            => AiEnum::ROLE_ASSISTANT,
                'content'         => $aiContent,
                'meta_json'       => $metaJson,
                'is_del'          => CommonEnum::NO,
            ]);

            // Phase 3: 解析并执行（建表/写文件）— 已在 generateCode 内的 parser 中完成
            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_FINALIZE, [
                'phase' => 'parsing', 'allow_overwrite' => $allowOverwrite,
            ], 0, 0, '');

            // Phase 4: 审查员自动 Code Review（可选）
            if ($enableReview && !empty($aiContent)) {
                $phaseStart = hrtime(true);
                $onChunk('phase', ['phase' => 'reviewing', 'msg' => '正在审查代码质量...']);
                $reviewResult = $this->reviewCode($aiContent, $onChunk);
                $reviewContent = $reviewResult['content'];
                $phaseUsage = $reviewResult['usage'];
                self::accumulateUsage($totalUsage, $phaseUsage);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                    'phase' => 'reviewing', 'content_length' => mb_strlen($reviewContent),
                    'prompt_tokens' => $phaseUsage['prompt_tokens'], 'completion_tokens' => $phaseUsage['completion_tokens'], 'total_tokens' => $phaseUsage['total_tokens'],
                ], $this->elapsedMs($phaseStart), $reviewAgent ? (int)$reviewAgent->id : 0, $reviewModelCode);
                if (!empty($reviewContent)) {
                    $this->messagesDep->add([
                        'conversation_id' => $conversationId,
                        'role'            => AiEnum::ROLE_ASSISTANT,
                        'content'         => $reviewContent,
                        'meta_json'       => json_encode(['type' => 'review'], JSON_UNESCAPED_UNICODE),
                        'is_del'          => CommonEnum::NO,
                    ]);
                }
            }

            // Phase 5: 测试员自动生成测试用例（可选）
            if ($enableTest && !empty($aiContent)) {
                $phaseStart = hrtime(true);
                $onChunk('phase', ['phase' => 'testing', 'msg' => '正在生成测试用例...']);
                $testResult = $this->generateTests($aiContent, $onChunk);
                $testContent = $testResult['content'];
                $phaseUsage = $testResult['usage'];
                self::accumulateUsage($totalUsage, $phaseUsage);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                    'phase' => 'testing', 'content_length' => mb_strlen($testContent),
                    'prompt_tokens' => $phaseUsage['prompt_tokens'], 'completion_tokens' => $phaseUsage['completion_tokens'], 'total_tokens' => $phaseUsage['total_tokens'],
                ], $this->elapsedMs($phaseStart), $testAgent ? (int)$testAgent->id : 0, $testModelCode);
                if (!empty($testContent)) {
                    $this->messagesDep->add([
                        'conversation_id' => $conversationId,
                        'role'            => AiEnum::ROLE_ASSISTANT,
                        'content'         => $testContent,
                        'meta_json'       => json_encode(['type' => 'test'], JSON_UNESCAPED_UNICODE),
                        'is_del'          => CommonEnum::NO,
                    ]);
                }
            }

            $this->conversationsDep->updateLastMessageAt($conversationId);

            // 标记运行成功（含汇总 token 数据）
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'latency_ms'           => $this->elapsedMs($startTime),
                'prompt_tokens'        => $totalUsage['prompt_tokens'] ?: null,
                'completion_tokens'    => $totalUsage['completion_tokens'] ?: null,
                'total_tokens'         => $totalUsage['total_tokens'] ?: null,
            ]);
            $runMarked = true;

            $onChunk('done', ['conversation_id' => $conversationId, 'msg' => '生成完成']);
        } catch (\Throwable $e) {
            // 仅在尚未标记成功时才标记失败（防止 onChunk('done') 连接异常覆盖已成功的状态）
            if ($runId && empty($runMarked)) {
                try {
                    $this->runsDep->markFailed($runId, $e->getMessage());
                } catch (\Throwable $markErr) {
                    Log::error("[CodeGen] markFailed 也失败 run={$runId}: {$markErr->getMessage()}");
                }
            }
            throw $e;
        } finally {
            // 恢复文件监控（无论成功或失败都必须恢复）
            try { Monitor::resume(); } catch (\Throwable) {}
        }
    }

    /**
     * 添加运行步骤记录
     */
    private function addStep(int $runId, int $stepNo, int $stepType, array $payload, int $latencyMs = 0, int $agentId = 0, string $modelSnapshot = ''): void
    {
        $this->runStepsDep->add([
            'run_id'         => $runId,
            'step_no'        => $stepNo,
            'step_type'      => $stepType,
            'agent_id'       => $agentId,
            'model_snapshot' => $modelSnapshot,
            'status'         => AiEnum::STEP_STATUS_SUCCESS,
            'latency_ms'     => $latencyMs,
            'payload_json'   => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'is_del'         => CommonEnum::NO,
        ]);
    }

    /**
     * 计算从起点到当前的毫秒数
     */
    private function elapsedMs(int $startNs): int
    {
        return (int)((hrtime(true) - $startNs) / 1_000_000);
    }

    /**
     * 空 token 用量（默认值）
     */
    private static function emptyUsage(): array
    {
        return ['prompt_tokens' => null, 'completion_tokens' => null, 'total_tokens' => null];
    }

    /**
     * 累加 token 用量
     */
    private static function accumulateUsage(array &$total, array $usage): void
    {
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                $total[$key] = ($total[$key] ?? 0) + (int)$usage[$key];
            }
        }
    }

    /**
     * 加载会话历史消息（用于迭代场景）
     */
    private function loadHistory(int $conversationId): array
    {
        $messages = $this->messagesDep->getRecentByConversationId($conversationId, 10);
        if ($messages->isEmpty()) {
            return [];
        }

        $history = [];
        // getRecentByConversationId 按 id desc，需要反转
        foreach ($messages->reverse() as $msg) {
            $role = AiEnum::$roleArr[$msg->role] ?? 'user';
            $history[] = [
                'role'    => $role,
                'content' => $msg->content,
            ];
        }
        return $history;
    }

    /**
     * Phase 1: 研究员 Agent 收集上下文
     */
    private function gatherContext(string $userMessage, callable $onChunk): array
    {
        $researcherAgent = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_RESEARCH);
        if (!$researcherAgent) {
            return $this->fallbackGatherContext($userMessage);
        }

        $model = $this->modelsDep->get((int)$researcherAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            return $this->fallbackGatherContext($userMessage);
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $researcherAgent);
        if ($error) {
            return $this->fallbackGatherContext($userMessage);
        }

        $prompt = "用户需要进行代码生成。请使用工具收集相关信息。\n\n用户需求：{$userMessage}";

        $result = AiChatService::chatStream(
            $neuronAgent, $prompt, [],
            fn($delta) => null,
            fn($callId, $name, $inputs) => $onChunk('tool_call', [
                'call_id'     => $callId,
                'tool_name'   => $name,
                'tool_inputs' => $inputs,
            ]),
            fn($callId, $name, $result) => $onChunk('tool_result', [
                'call_id'     => $callId,
                'tool_name'   => $name,
                'tool_result' => $result,
            ]),
        );

        return [
            'context' => self::extractJson($result['content'] ?? ''),
            'usage'   => $result['usage'] ?? self::emptyUsage(),
        ];
    }

    /**
     * 从 AI 输出中提取 JSON（容错处理）
     * 处理常见情况：纯 JSON、```json 包裹、前后有解释文字
     */
    private static function extractJson(string $content): array
    {
        // 1. 先尝试直接解析
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 2. 尝试提取 ```json ... ``` 代码块
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $match)) {
            $decoded = json_decode($match[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. 尝试提取第一个 { ... } 块
        if (preg_match('/\{[\s\S]*\}/s', $content, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 4. 全部失败，返回空
        return [];
    }

    /**
     * 降级方案：后端直接收集上下文（当研究员 Agent 不可用时）
     */
    private function fallbackGatherContext(string $userMessage): array
    {
        return [
            'context' => [
                'existing_tables' => CodeGenTools::listTables([]),
                'conventions'     => [
                    'php'       => CodeGenTools::readConvention(['type' => 'php']),
                    'db'        => CodeGenTools::readConvention(['type' => 'db']),
                    'vue'       => CodeGenTools::readConvention(['type' => 'vue']),
                    'structure' => CodeGenTools::readConvention(['type' => 'structure']),
                ],
            ],
            'usage' => self::emptyUsage(),
        ];
    }

    /**
     * Phase 2: 程序员 Agent 生成代码（流式）
     */
    private function generateCode(array $context, string $userMessage, array $history, callable $onChunk, bool $allowOverwrite): array
    {
        $coderAgent = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_CODER);
        if (!$coderAgent) {
            $onChunk('error', ['msg' => '未配置代码生成程序员智能体']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        $model = $this->modelsDep->get((int)$coderAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            $onChunk('error', ['msg' => '程序员智能体关联的模型不可用']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $coderAgent);
        if ($error) {
            $onChunk('error', ['msg' => "创建程序员 Agent 失败: {$error}"]);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        // 构建包含上下文的完整提示
        $fullPrompt = $this->buildCoderPrompt($context, $userMessage);

        // 带解析的流式输出
        $parser = new CodeGenParser($onChunk, $allowOverwrite);
        $fullContent = '';

        $result = AiChatService::chatStream(
            $neuronAgent, $fullPrompt, $history,
            function ($delta) use ($parser, $onChunk, &$fullContent) {
                $fullContent .= $delta;
                $onChunk('content', ['delta' => $delta]);
                $parser->feed($delta);
            }
        );

        // 流结束后，解析缓存的操作
        $parser->flush();

        return [
            'content' => $fullContent,
            'usage'   => $result['usage'] ?? self::emptyUsage(),
        ];
    }

    /**
     * Phase 4: 审查员 Agent 自动 Code Review（流式）
     */
    private function reviewCode(string $generatedCode, callable $onChunk): array
    {
        $reviewerAgent = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_REVIEW);
        if (!$reviewerAgent) {
            $onChunk('error', ['msg' => '审查失败：未配置审查员智能体（scene=code_gen_review）']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        $model = $this->modelsDep->get((int)$reviewerAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            $onChunk('error', ['msg' => '审查失败：审查员关联的模型不可用']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $reviewerAgent);
        if ($error) {
            $onChunk('error', ['msg' => "审查失败：{$error}"]);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        $prompt = <<<'REVIEW'
请审查以下 AI 生成的代码，重点检查是否违反项目规范：

## 审查要点（必查）
1. **Dep 层**：是否用 `$this->model`（属性）而非 `$this->model()`（方法）？`createModel()` 返回类型是否为 `\support\Model`？是否重复定义了 BaseDep 已有的 `get/find/exists/add/update/delete` 方法？
2. **Enum**：是否每个业务域只有一个 Enum 文件？是否有语法错误（如空常量名 `const = 3`）？
3. **Module init**：是否使用 `DictService` 链式调用？是否直接返回了 Enum 数组（应该走 DictService）？
4. **Controller**：是否继承 `Controller`？写操作是否有 `@OperationLog` 和 `@Permission` 注解？
5. **前端 useTable**：是否用 `useTable({ api: XxxApi, searchForm })` 模式？是否使用 `AppTable` + `Search` 组件？是否用了 i18n `t()` 而非硬编码中文？
6. **命名一致性**：同一模块所有层级 Domain 文件夹和 Entity 命名是否统一？

## 待审查代码

REVIEW
        . $generatedCode;
        $fullContent = '';
        $usage = self::emptyUsage();

        try {
            $result = AiChatService::chatStream(
                $neuronAgent, $prompt, [],
                function ($delta) use ($onChunk, &$fullContent) {
                    $fullContent .= $delta;
                    $onChunk('review', ['delta' => $delta]);
                }
            );
            $usage = $result['usage'] ?? self::emptyUsage();
        } catch (\Throwable $e) {
            try { $onChunk('error', ['msg' => "审查异常：{$e->getMessage()}"]); } catch (\Throwable) {}
        }

        return ['content' => $fullContent, 'usage' => $usage];
    }

    /**
     * Phase 5: 测试员 Agent 自动生成测试用例（流式）
     */
    private function generateTests(string $generatedCode, callable $onChunk): array
    {
        $testerAgent = $this->agentsDep->getByScene(AiEnum::SCENE_CODE_GEN_TEST);
        if (!$testerAgent) {
            $onChunk('error', ['msg' => '测试失败：未配置测试员智能体（scene=code_gen_test）']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        $model = $this->modelsDep->get((int)$testerAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            $onChunk('error', ['msg' => '测试失败：测试员关联的模型不可用']);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $testerAgent);
        if ($error) {
            $onChunk('error', ['msg' => "测试失败：{$error}"]);
            return ['content' => '', 'usage' => self::emptyUsage()];
        }

        $prompt = "请为以下 AI 生成的代码编写测试用例：\n\n" . $generatedCode;
        $fullContent = '';
        $usage = self::emptyUsage();

        try {
            $result = AiChatService::chatStream(
                $neuronAgent, $prompt, [],
                function ($delta) use ($onChunk, &$fullContent) {
                    $fullContent .= $delta;
                    $onChunk('test', ['delta' => $delta]);
                }
            );
            $usage = $result['usage'] ?? self::emptyUsage();
        } catch (\Throwable $e) {
            try { $onChunk('error', ['msg' => "测试异常：{$e->getMessage()}"]); } catch (\Throwable) {}
        }

        return ['content' => $fullContent, 'usage' => $usage];
    }

    private function buildCoderPrompt(array $context, string $userMessage): string
    {
        $parts = ["## 研究员收集的项目上下文\n"];

        if (!empty($context['existing_tables'])) {
            $tables = is_string($context['existing_tables'])
                ? $context['existing_tables']
                : json_encode($context['existing_tables'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $parts[] = "### 现有数据库表\n{$tables}";
        }
        if (!empty($context['related_columns'])) {
            $parts[] = "### 相关表字段结构\n" . json_encode($context['related_columns'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        if (!empty($context['conventions'])) {
            foreach ($context['conventions'] as $type => $content) {
                $parts[] = "### {$type} 编码规范\n{$content}";
            }
        }
        if (!empty($context['example_code'])) {
            foreach ($context['example_code'] as $layer => $code) {
                $parts[] = "### {$layer} 示例代码\n```php\n{$code}\n```";
            }
        }
        if (!empty($context['analysis'])) {
            $parts[] = "### 研究员分析\n{$context['analysis']}";
        }

        $parts[] = "\n---\n\n## 用户需求\n{$userMessage}";
        $parts[] = <<<'INSTRUCTION'

## 输出要求
请根据以上上下文和用户需求生成代码。**首次回复必须先展示设计方案（表结构、文件清单、路由），等用户确认后再输出操作标记。**

当用户回复"确认"、"好的"、"开始"、"生成"等确认词时，再使用以下标记格式输出代码（后端会自动解析并执行）：

### 建表（如需要）
```sql:CREATE_TABLE:表名
CREATE TABLE 表名 (...);
```

### 改表（如需要）
```sql:ALTER_TABLE:表名
ALTER TABLE 表名 ADD COLUMN ...;
```

### 写文件（后端 PHP）
```php:WRITE_FILE:app/controller/Domain/XxxController.php
<?php
// 完整文件内容
```

### 写文件（前端 Vue/TS）
```vue:WRITE_FILE:src/views/Main/domain/xxx/index.vue
<script setup lang="ts">
// 完整文件内容
```

```typescript:WRITE_FILE:src/api/domain/xxx.ts
// 完整文件内容
```

重要规则：
- **首轮对话**：先用普通 Markdown 展示方案（表结构设计、文件清单、路由配置），不要输出 CREATE_TABLE / ALTER_TABLE / WRITE_FILE 标记
- **用户确认后**：输出完整的操作标记，每个文件必须是完整内容，不能省略或用注释占位
- 建表必须包含 id, created_at, updated_at, is_del 标准字段
- 可以在代码前后添加简要说明

### 架构一致性（强制）
Domain 由数据库表名前缀决定，同一模块所有层级的 Domain 文件夹和 Entity 命名必须完全统一：

**规则：表名前缀 → Domain 文件夹**
- 表名 `user_feedback` → 前缀 `user` → Domain 为 `User`
- 表名 `order_item` → 前缀 `order` → Domain 为 `Order`
- 表名 `cron_task` → 前缀不明确时，按业务归属确定 Domain（如 `DevTools`）

**后端路径格式：`app/{layer}/{Domain}/{Entity}{Layer}.php`**
Entity 命名可以是 `UserFeedback` 或 `Feedback`，但所有层级必须用同一个名字：
- ✅ 正确（全部用 `Feedback`）：
  `app/controller/User/FeedbackController.php`、`app/module/User/FeedbackModule.php`、`app/dep/User/FeedbackDep.php`、`app/model/User/FeedbackModel.php`、`app/validate/User/FeedbackValidate.php`
- ✅ 正确（全部用 `UserFeedback`）：
  `app/controller/User/UserFeedbackController.php`、`app/module/User/UserFeedbackModule.php`、`app/dep/User/UserFeedbackDep.php`、`app/model/User/UserFeedbackModel.php`
- ❌ 错误（混用命名）：Controller 叫 `UserFeedbackController`，Module 叫 `FeedbackModule`
- ❌ 错误（混用文件夹）：部分有 `User/` 文件夹，部分没有

**前端路径格式：**
- API：`src/api/{domain}/{entity}.ts`（domain 小驼峰）
- 页面：`src/views/Main/{domain}/{entity}/index.vue`
- 例如：`src/api/user/feedback.ts`、`src/views/Main/user/feedback/index.vue`

### 后端代码规范（强制）

**Controller**（必须严格遵循）：
- 继承 `app\controller\Controller`（不是 BaseController）
- 删除操作方法名用 `del`（不是 `delete`）
- 无注解的方法（`list`、`init`、`detail`）写成单行
- 写操作（`add`、`edit`、`del`、`status`）必须加 `@OperationLog` 和 `@Permission` 注解
- **@OperationLog 必须使用中文业务名称**，从用户需求中提取，2-4 个汉字 + 操作动词。
  ✅ `@OperationLog("用户反馈新增")`、`@OperationLog("系统设置编辑")`
  ❌ `@OperationLog("Feedback新增")`（禁止使用英文类名）
- 权限码格式：`{domain}_{entity}_{action}`（小驼峰，如 `user_feedback_add`）
- 完整模板：
```
<?php
namespace app\controller\{Domain};

use app\controller\Controller;
use app\module\{Domain}\{Entity}Module;
use support\Request;

class {Entity}Controller extends Controller
{
    public function list(Request $request) { return $this->run([{Entity}Module::class, 'list'], $request); }

    /** @OperationLog("中文业务名新增") @Permission("{domain}_{entity}_add") */
    public function add(Request $request) { return $this->run([{Entity}Module::class, 'add'], $request); }

    /** @OperationLog("中文业务名编辑") @Permission("{domain}_{entity}_edit") */
    public function edit(Request $request) { return $this->run([{Entity}Module::class, 'edit'], $request); }

    /** @OperationLog("中文业务名删除") @Permission("{domain}_{entity}_del") */
    public function del(Request $request) { return $this->run([{Entity}Module::class, 'del'], $request); }
}
```

**路由注册（重要）**：
路由文件 `routes/admin.php` 无法自动修改。生成代码后，**必须**在回复末尾用独立段落列出完整路由配置，格式如下：

**📋 需要手动添加到 routes/admin.php 的路由：**
```php
// 中文业务名管理
Route::post('/{Entity}/init', [controller\{Domain}\{Entity}Controller::class, 'init']);
Route::post('/{Entity}/list', [controller\{Domain}\{Entity}Controller::class, 'list']);
Route::post('/{Entity}/detail', [controller\{Domain}\{Entity}Controller::class, 'detail']);
Route::post('/{Entity}/add', [controller\{Domain}\{Entity}Controller::class, 'add']);
Route::post('/{Entity}/edit', [controller\{Domain}\{Entity}Controller::class, 'edit']);
Route::post('/{Entity}/del', [controller\{Domain}\{Entity}Controller::class, 'del']);
Route::post('/{Entity}/status', [controller\{Domain}\{Entity}Controller::class, 'status']);
```

**Module**（必须严格遵循）：
- 继承 `app\module\BaseModule`
- 依赖用 `$this->dep(XxxDep::class)` / `$this->svc(XxxService::class)` 懒加载，**不要** new
- `init` 方法**必须**使用 DictService 链式调用返回字典数据，格式：
```
public function init($request): array
{
    $dict = $this->svc(DictService::class)
        ->setCommonStatusArr()
        ->setXxxTypeArr()     // 按需链式调用
        ->getDict();
    return self::success(['dict' => $dict]);
}
```
- **禁止**在 init 中直接返回 Enum 数组，必须走 DictService
- `list` 方法**必须**格式化枚举字段（添加 `_name` 后缀），并构建标准分页结构：
```
public function list($request): array
{
    $param = $this->validate($request, {Entity}Validate::list());
    $res = $this->dep({Entity}Dep::class)->list{Entity}($param);

    $list = $res->map(fn($item) => [
        'id'             => $item['id'],
        'xxx_type'       => $item['xxx_type'],
        'xxx_type_name'  => {Entity}Enum::$xxxTypeArr[$item['xxx_type']] ?? '',
        'status'         => $item['status'],
        'status_name'    => CommonEnum::$statusArr[$item['status']] ?? '',
        'created_at'     => $item['created_at'],
    ]);

    $page = [
        'page_size'    => $res->perPage(),
        'current_page' => $res->currentPage(),
        'total_page'   => $res->lastPage(),
        'total'        => $res->total(),
    ];

    return self::paginate($list, $page);
}
```
- **禁止**直接 `return self::paginate($data)` —— `paginate()` 签名是 `paginate($list, array $page)`，必须先 map 格式化列表再构建 $page 数组
- 状态常量：`CommonEnum::YES = 1`（启用/是/已删除）、`CommonEnum::NO = 2`（禁用/否/未删除）。**不存在** `CommonEnum::ENABLE` / `CommonEnum::DISABLE`
- **JSON 字段处理**：BaseDep 的 `add()`/`update()` 使用 Query Builder（`insertGetId`/`whereIn->update`），**不会**触发 Model 的 `$casts`。如果字段类型是 JSON/数组，Module 层构建 `$data` 时必须手动 `json_encode`：
  ✅ `'screenshots' => json_encode($param['screenshots'] ?? [], JSON_UNESCAPED_UNICODE)`
  ❌ `'screenshots' => $param['screenshots'] ?? []`（array 无法直接写入数据库）

**Dep**（必须严格遵循）：
- 继承 `app\dep\BaseDep`
- `createModel()` 返回类型必须是 `\support\Model`（不是具体 Model 类）
- 查询使用 `$this->model`（**属性**，不是方法调用 `$this->model()`）
- BaseDep 已提供 `get($id)`、`find($id)`、`exists($id)`、`add()`、`update()`、`delete()`、`setStatus()` 等通用方法，**禁止**在子类中重复定义这些方法
- 子类只写 BaseDep 没有的业务查询方法（如 `listXxx` 分页、`getByXxx` 条件查询等）
- 方法命名：`get*`（过滤 is_del）/ `find*`（不过滤）/ `list*`（分页）/ `exists*`（bool）
- Dep 模板：
```
class {Entity}Dep extends BaseDep
{
    protected function createModel(): Model  // 返回 \support\Model
    {
        return new {Entity}Model();
    }

    public function list{Entity}(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['keyword']), fn($q) => $q->where('xxx', 'like', '%' . $param['keyword'] . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderByDesc('id')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}
```

**Model**：继承 `app\model\BaseModel`，只定义 `$table`、`$casts`，不写查询逻辑

**Validate**（必须遵循）：
- 静态方法返回验证规则数组，使用 `Respect\Validation\Validator as v`
- 枚举字段校验必须使用 `v::in([1,2,3,4])` 列举所有合法值，**禁止**用 `v::min(1)->max(4)`（枚举值可能不连续）

**Service**：仅在有跨模块复用逻辑时才生成。标准 CRUD 模块**不需要** Service 文件，**禁止**创建空的 Service 类

**Enum**（必须严格遵循）：
- 每个业务域**只生成一个** Enum 文件，命名为 `{Entity}Enum.php`
- 所有相关常量（类型、状态等）合并在同一个 Enum 类中
- 格式：`const TYPE_XXX = 1;` + `public static array $typeArr = [self::TYPE_XXX => '名称'];`
- **禁止**拆分成多个 Enum 文件（如 XxxTypeEnum + XxxStatusEnum），全部合并为一个 `{Entity}Enum`
- 同一组常量必须保持 `PREFIX_SUFFIX` 格式统一：
  ✅ `HANDLE_PENDING` / `HANDLE_PROCESSING` / `HANDLE_DONE` / `HANDLE_CLOSED`
  ❌ `HANDLE_PENDING` / `HANDLE_PROCESSING` / `HANDLE` / `HANDLE_CLOSED`（`HANDLE` 缺少后缀）

### DictService 修改规则（强制 — 违反将导致执行失败）
DictService（`app/service/DictService.php`）是全局共享文件，**严禁使用 WRITE_FILE 覆盖**（系统会直接拒绝执行）。
必须使用 `PATCH_FILE` 增量追加，系统会自动插入到指定方法之前：

```php:PATCH_FILE:app/service/DictService.php:BEFORE_METHOD:getDict
    public function setXxxArr(): static
    {
        $this->dict['xxx_arr'] = self::enumToDict(\app\enum\Domain\XxxEnum::$xxxArr);
        return $this;
    }
```

注意事项：
- 只输出新增的方法代码，**不要**输出整个 DictService 文件
- use 语句用完整命名空间（`\app\enum\...`），不需要在文件顶部追加 use
- 每个方法返回 `static` 支持链式调用

### 前端代码规范（强制）

**API 文件**（`src/api/{domain}/{entity}.ts`）：
- 导出名用 PascalCase：`export const {Entity}Api = { ... }`
- 所有接口用 POST，路径 `/api/admin/{Domain}/{Entity}/{action}`
- 必须包含 `init`、`list`、`del` 等标准方法

**页面文件**（`src/views/Main/{domain}/{entity}/index.vue`）：
- `<script setup lang="ts">` 在 `<template>` 之前
- 必须使用 `useTable` hook：`useTable({ api: {Entity}Api, searchForm, initPage: { page_size: 20 } })`
- 必须使用 `AppTable` 组件和 `Search` 组件（从 `@/components/Table` 和 `@/components/Search` 导入）
- init 方法命名为 `init`（不是 `loadInit`），在 `onMounted` 中调用
- 字典数据从 init 接口获取，格式为 `data.dict.xxx_arr`，类型是 `[{label, value}]` 数组，**直接**作为 Search 组件的 options
- 搜索字段使用 `SearchField` 类型，type 可选：`input`、`select-v2`、`date-range`
- 表格列定义用 `columns` 数组，格式：`{key: 'xxx', label: t('xxx')}`
- 操作按钮权限用 `userStore.can('permission_code')`
- 页面**必须**包含完整的新增/编辑对话框（`el-dialog` + `el-form`），不能只有列表骨架
- 枚举字段在表格中显示 `_name`（后端已格式化），在表单中用 `el-select-v2` 绑定 `dict.xxx_arr`
- 前端完整页面模板（script + template + style）：
```
<script setup lang="ts">
import {ref, computed, onMounted, nextTick} from 'vue'
import {useI18n} from 'vue-i18n'
import {useUserStore} from '@/store/user'
import {useIsMobile} from '@/hooks/useResponsive'
import {{Entity}Api} from '@/api/{domain}/{entity}'
import {AppTable} from '@/components/Table'
import {Search} from '@/components/Search'
import type {SearchField} from '@/components/Search/types'
import type {FormInstance} from 'element-plus'
import {ElNotification} from 'element-plus'
import {useTable} from '@/hooks/useTable'

const {t} = useI18n()
const isMobile = useIsMobile()
const userStore = useUserStore()
const dict = ref({} as any)

const searchForm = ref({})
const {loading: listLoading, data: listData, page, onSearch, onPageChange, refresh, getList, onSelectionChange, confirmDel, batchDel} = useTable({
  api: {Entity}Api,
  searchForm
})

const init = () => {
  {Entity}Api.init().then((data: any) => {
    dict.value = data.dict || {}
  })
}

const searchFields = computed<SearchField[]>(() => [
  {key: 'keyword', type: 'input', label: '关键词'},
  {key: 'status', type: 'select-v2', label: '状态', options: dict.value.status_arr || []},
])

const columns = computed(() => [
  {key: 'id', label: 'ID'},
  // ... 业务字段用 xxx_name 显示枚举文本
  {key: 'status', label: '状态'},
  {key: 'created_at', label: '创建时间'},
  {key: 'actions', label: t('common.actions.action'), width: 200}
])

// ========== 新增/编辑对话框 ==========
const dialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')
const formRef = ref<FormInstance | null>(null)
const form = ref({/* 所有可编辑字段的默认值 */} as any)

const add = () => {
  dialogMode.value = 'add'
  form.value = {/* 重置为默认值 */}
  dialogVisible.value = true
  nextTick(() => formRef.value?.clearValidate())
}

const edit = (row: any) => {
  dialogMode.value = 'edit'
  form.value = {...row}
  dialogVisible.value = true
  nextTick(() => formRef.value?.clearValidate())
}

const confirmSubmit = async () => {
  try { await formRef.value?.validate() } catch { return }
  const api = dialogMode.value === 'add' ? {Entity}Api.add : {Entity}Api.edit
  api(form.value).then(() => {
    ElNotification.success({message: t('common.success.operation')})
    dialogVisible.value = false
    getList()
  })
}

onMounted(() => {
  init()
  getList()
})
</script>

<template>
  <div class="box">
    <Search v-model="searchForm" :fields="searchFields" @query="onSearch" @reset="onSearch"/>
    <div class="table">
      <AppTable :columns="columns" :data="listData" :loading="listLoading" row-key="id"
        :pagination="page" selectable @refresh="refresh" @update:pagination="onPageChange" @selection-change="onSelectionChange">
        <template #toolbar-left>
          <el-button type="success" @click="add" v-if="userStore.can('{domain}_{entity}_add')">{{ t('common.actions.add') }}</el-button>
        </template>
        <template #cell-status="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status_name }}</el-tag>
        </template>
        <template #cell-actions="{ row }">
          <el-button type="primary" text @click="edit(row)" v-if="userStore.can('{domain}_{entity}_edit')">{{ t('common.actions.edit') }}</el-button>
          <el-button type="danger" text @click="confirmDel(row)" v-if="userStore.can('{domain}_{entity}_del')">{{ t('common.actions.del') }}</el-button>
        </template>
      </AppTable>
    </div>
  </div>

  <el-dialog v-model="dialogVisible" :width="isMobile ? '94vw' : '700px'">
    <template #header>{{ dialogMode === 'add' ? '新增' : '编辑' }}</template>
    <el-form :model="form" ref="formRef" label-width="auto" :validate-on-rule-change="false">
      <el-row :gutter="12">
        <el-col :md="12" :span="24">
          <el-form-item label="字段名" prop="xxx" required>
            <el-input v-model="form.xxx" clearable/>
          </el-form-item>
        </el-col>
        <el-col :md="12" :span="24">
          <el-form-item label="枚举字段" prop="xxx_type">
            <el-select-v2 v-model="form.xxx_type" :options="dict.xxx_type_arr || []" style="width:100%"/>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
    <template #footer>
      <el-button @click="dialogVisible=false">{{ t('common.actions.cancel') }}</el-button>
      <el-button type="primary" @click="confirmSubmit">{{ t('common.actions.confirm') }}</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.box { display: flex; flex-direction: column; height: 100% }
.table { flex: 1 1 auto; min-height: 0; overflow: auto }
</style>
```

所有类必须包含完整的 namespace 和 use 语句，不能省略！

### 代码块语言标注（强制）
每个代码块必须标注正确的语言：
- PHP 文件（新建/全量覆盖）：\`\`\`php:WRITE_FILE:...
- PHP 文件（增量追加方法）：\`\`\`php:PATCH_FILE:路径:BEFORE_METHOD:方法名
- Vue 文件：\`\`\`vue:WRITE_FILE:...
- TypeScript 文件：\`\`\`typescript:WRITE_FILE:...
- SQL 建表：\`\`\`sql:CREATE_TABLE:...
- SQL 改表：\`\`\`sql:ALTER_TABLE:...

**PATCH_FILE vs WRITE_FILE**：
- `WRITE_FILE`：用于新建文件或需要全量覆盖的场景
- `PATCH_FILE`：用于在已有文件中追加方法（如 DictService），只输出新增的方法代码，系统会自动插入到指定方法之前
- ⚠️ `app/service/DictService.php` 和 `routes/admin.php` 等共享文件**只能用 PATCH_FILE**，使用 WRITE_FILE 会被系统拒绝
INSTRUCTION;

        return implode("\n\n", $parts);
    }
}
