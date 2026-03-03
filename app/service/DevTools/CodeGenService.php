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
use app\service\Ai\AiChatService;

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
        $researcherAgent = $this->agentsDep->getBySceneAndMode(
            AiEnum::SCENE_CODE_GEN, AiEnum::MODE_TOOL
        );

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

        // 创建运行记录
        $requestId  = 'codegen_' . uniqid('', true);
        $modelCode  = '';
        $coderAgent = $this->agentsDep->getBySceneAndMode(AiEnum::SCENE_CODE_GEN, AiEnum::MODE_CHAT);
        if ($coderAgent) {
            $coderModel = $this->modelsDep->get((int)$coderAgent->model_id);
            $modelCode  = $coderModel->model_code ?? '';
        }
        $runId = $this->runsDep->add([
            'request_id'       => $requestId,
            'user_id'          => $userId,
            'agent_id'         => $researcherAgent ? $researcherAgent->id : ($coderAgent ? $coderAgent->id : 0),
            'conversation_id'  => $conversationId,
            'user_message_id'  => $userMessageId,
            'run_status'       => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot'   => $modelCode,
            'is_del'           => CommonEnum::NO,
        ]);

        try {
            // Phase 1: 研究员收集上下文（首轮对话才执行，迭代轮次复用历史上下文）
            $context = [];
            if (empty($history)) {
                $phaseStart = hrtime(true);
                $onChunk('phase', ['phase' => 'researching', 'msg' => '正在分析需求，收集项目信息...']);
                $context = $this->gatherContext($userMessage, $onChunk);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_TOOL_CALL, [
                    'phase' => 'researching', 'context_keys' => array_keys($context),
                ], $this->elapsedMs($phaseStart));
            }

            // Phase 2: 程序员生成代码
            $phaseStart = hrtime(true);
            $onChunk('phase', ['phase' => 'generating', 'msg' => '正在生成代码...']);
            $aiContent = $this->generateCode($context, $userMessage, $history, $onChunk, $allowOverwrite);
            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                'phase' => 'generating', 'content_length' => mb_strlen($aiContent),
            ], $this->elapsedMs($phaseStart));

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
            ], 0);

            // Phase 4: 审查员自动 Code Review（可选）
            if ($enableReview && !empty($aiContent)) {
                $phaseStart = hrtime(true);
                $onChunk('phase', ['phase' => 'reviewing', 'msg' => '正在审查代码质量...']);
                $reviewContent = $this->reviewCode($aiContent, $onChunk);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                    'phase' => 'reviewing', 'content_length' => mb_strlen($reviewContent),
                ], $this->elapsedMs($phaseStart));
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
                $testContent = $this->generateTests($aiContent, $onChunk);
                $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_LLM, [
                    'phase' => 'testing', 'content_length' => mb_strlen($testContent),
                ], $this->elapsedMs($phaseStart));
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

            // 标记运行成功
            $this->runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'latency_ms'           => $this->elapsedMs($startTime),
            ]);

            $onChunk('done', ['conversation_id' => $conversationId, 'msg' => '生成完成']);
        } catch (\Throwable $e) {
            // 标记运行失败
            if ($runId) {
                $this->runsDep->markFailed($runId, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * 添加运行步骤记录
     */
    private function addStep(int $runId, int $stepNo, int $stepType, array $payload, int $latencyMs = 0): void
    {
        $this->runStepsDep->add([
            'run_id'       => $runId,
            'step_no'      => $stepNo,
            'step_type'    => $stepType,
            'status'       => AiEnum::STEP_STATUS_SUCCESS,
            'latency_ms'   => $latencyMs,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'is_del'       => CommonEnum::NO,
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
        $researcherAgent = $this->agentsDep->getBySceneAndMode(
            AiEnum::SCENE_CODE_GEN, AiEnum::MODE_TOOL
        );
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

        return self::extractJson($result['content'] ?? '');
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
            'existing_tables' => CodeGenTools::listTables([]),
            'conventions'     => [
                'php'       => CodeGenTools::readConvention(['type' => 'php']),
                'db'        => CodeGenTools::readConvention(['type' => 'db']),
                'vue'       => CodeGenTools::readConvention(['type' => 'vue']),
                'structure' => CodeGenTools::readConvention(['type' => 'structure']),
            ],
        ];
    }

    /**
     * Phase 2: 程序员 Agent 生成代码（流式）
     */
    private function generateCode(array $context, string $userMessage, array $history, callable $onChunk, bool $allowOverwrite): string
    {
        $coderAgent = $this->agentsDep->getBySceneAndMode(
            AiEnum::SCENE_CODE_GEN, AiEnum::MODE_CHAT
        );
        if (!$coderAgent) {
            $onChunk('error', ['msg' => '未配置代码生成程序员智能体']);
            return '';
        }

        $model = $this->modelsDep->get((int)$coderAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            $onChunk('error', ['msg' => '程序员智能体关联的模型不可用']);
            return '';
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $coderAgent);
        if ($error) {
            $onChunk('error', ['msg' => "创建程序员 Agent 失败: {$error}"]);
            return '';
        }

        // 构建包含上下文的完整提示
        $fullPrompt = $this->buildCoderPrompt($context, $userMessage);

        // 带解析的流式输出
        $parser = new CodeGenParser($onChunk, $allowOverwrite);
        $fullContent = '';

        AiChatService::chatStream(
            $neuronAgent, $fullPrompt, $history,
            function ($delta) use ($parser, $onChunk, &$fullContent) {
                $fullContent .= $delta;
                $onChunk('content', ['delta' => $delta]);
                $parser->feed($delta);
            }
        );

        // 流结束后，解析缓存的操作
        $parser->flush();

        return $fullContent;
    }

    /**
     * Phase 4: 审查员 Agent 自动 Code Review（流式）
     */
    private function reviewCode(string $generatedCode, callable $onChunk): string
    {
        $reviewerAgent = $this->agentsDep->getBySceneAndMode(
            AiEnum::SCENE_CODE_GEN, AiEnum::MODE_RAG
        );
        if (!$reviewerAgent) {
            return '';
        }

        $model = $this->modelsDep->get((int)$reviewerAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            return '';
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $reviewerAgent);
        if ($error) {
            return '';
        }

        $prompt = "请审查以下 AI 生成的代码：\n\n" . $generatedCode;
        $fullContent = '';

        AiChatService::chatStream(
            $neuronAgent, $prompt, [],
            function ($delta) use ($onChunk, &$fullContent) {
                $fullContent .= $delta;
                $onChunk('review', ['delta' => $delta]);
            }
        );

        return $fullContent;
    }

    /**
     * Phase 5: 测试员 Agent 自动生成测试用例（流式）
     */
    private function generateTests(string $generatedCode, callable $onChunk): string
    {
        $testerAgent = $this->agentsDep->getBySceneAndMode(
            AiEnum::SCENE_CODE_GEN, AiEnum::MODE_WORKFLOW
        );
        if (!$testerAgent) {
            return '';
        }

        $model = $this->modelsDep->get((int)$testerAgent->model_id);
        if (!$model || $model->status !== CommonEnum::YES) {
            return '';
        }

        [$neuronAgent, $error] = NeuronAgentFactory::createAgent($model, $testerAgent);
        if ($error) {
            return '';
        }

        $prompt = "请为以下 AI 生成的代码编写测试用例：\n\n" . $generatedCode;
        $fullContent = '';

        AiChatService::chatStream(
            $neuronAgent, $prompt, [],
            function ($delta) use ($onChunk, &$fullContent) {
                $fullContent .= $delta;
                $onChunk('test', ['delta' => $delta]);
            }
        );

        return $fullContent;
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
        $parts[] = "\n请根据以上上下文和用户需求，设计表结构并生成代码。先展示方案，等我确认后再执行。";

        return implode("\n\n", $parts);
    }
}
