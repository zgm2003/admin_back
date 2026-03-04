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

    /** @OperationLog("{Entity}新增") @Permission("{domain}_{entity}_add") */
    public function add(Request $request) { return $this->run([{Entity}Module::class, 'add'], $request); }

    /** @OperationLog("{Entity}编辑") @Permission("{domain}_{entity}_edit") */
    public function edit(Request $request) { return $this->run([{Entity}Module::class, 'edit'], $request); }

    /** @OperationLog("{Entity}删除") @Permission("{domain}_{entity}_del") */
    public function del(Request $request) { return $this->run([{Entity}Module::class, 'del'], $request); }
}
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
- 如果需要新的字典项，**必须**在已有的 `app/service/DictService.php` 中追加 `setXxxArr()` 方法（链式返回 `static`），使用 `self::enumToDict(XxxEnum::$xxxArr)` 转换。**DictService 是全局唯一的**，路径固定为 `app/service/DictService.php`，**禁止**创建新的 DictService 文件。使用 `PATCH_FILE` 增量追加方法（在 `getDict` 方法之前插入），格式：
```php:PATCH_FILE:app/service/DictService.php:BEFORE_METHOD:getDict
    public function setXxxArr(): static
    {
        $this->dict['xxx_arr'] = self::enumToDict(XxxEnum::$xxxArr);
        return $this;
    }
```
- **禁止**在 init 中直接返回 Enum 数组，必须走 DictService

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

    public function list{Entity}(array $where)
    {
        return $this->model              // 注意：是属性，不是 $this->model()
            ->where('is_del', CommonEnum::NO)
            ->when(...)
            ->orderByDesc('id')
            ->paginate(...);
    }
}
```

**Model**：继承 `app\model\BaseModel`，只定义 `$table`、`$casts`，不写查询逻辑
**Validate**：静态方法返回验证规则数组，使用 `Respect\Validation\Validator as v`
**Service**：业务逻辑层，无状态，如无复杂逻辑可留空

**Enum**（必须严格遵循）：
- 每个业务域**只生成一个** Enum 文件，命名为 `{Entity}Enum.php`
- 所有相关常量（类型、状态等）合并在同一个 Enum 类中
- 格式：`const TYPE_XXX = 1;` + `public static array $typeArr = [self::TYPE_XXX => '名称'];`
- **禁止**拆分成多个 Enum 文件（如 XxxTypeEnum + XxxStatusEnum），全部合并为一个 `{Entity}Enum`

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
- 使用 i18n 的 `t()` 函数，**禁止**硬编码中文字符串
- 前端页面模板：
```
<script setup lang="ts">
import {ref, computed, onMounted} from 'vue'
import {useI18n} from 'vue-i18n'
import {useUserStore} from '@/store/user'
import {{Entity}Api} from '@/api/{domain}/{entity}'
import {AppTable} from '@/components/Table'
import {Search} from '@/components/Search'
import type {SearchField} from '@/components/Search/types'
import {useTable} from '@/hooks/useTable'

const {t} = useI18n()
const userStore = useUserStore()

const searchForm = ref({...})
const {loading, data, page, onSearch, onPageChange, refresh, getList, onSelectionChange, confirmDel, batchDel} = useTable({
  api: {Entity}Api,
  searchForm
})

const xxxArr = ref<any[]>([])
const init = () => {
  {Entity}Api.init().then((data: any) => {
    xxxArr.value = data.dict.xxx_arr
  })
}

const columns = [...]
const searchFields = computed<SearchField[]>(() => [...])

onMounted(() => {
  init()
  getList()
})
</script>
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
INSTRUCTION;

        return implode("\n\n", $parts);
    }
}
