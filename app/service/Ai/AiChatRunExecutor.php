<?php

namespace app\service\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiAgentKnowledgeBasesDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use RuntimeException;
use Webman\Event\Event;

class AiChatRunExecutor
{
    private AiRunEventPublisher $events;

    public function __construct(?AiRunEventPublisher $events = null)
    {
        $this->events = $events ?? new AiRunEventPublisher();
    }

    public function execute(int $runId): void
    {
        $start = microtime(true);
        $run = (new AiRunsDep())->find($runId);
        if (!$run || (int)$run->is_del !== CommonEnum::NO) {
            return;
        }
        if ((int)$run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
            return;
        }

        try {
            $this->events->publish($runId, 'run_started', ['run_id' => $runId]);
            $ctx = $this->buildContext($run);
            $this->executeModelOrImage($runId, $ctx, $start);
        } catch (\Throwable $e) {
            $this->events->publishError($runId, 'AI 调用失败: ' . $e->getMessage());
            (new AiRunsDep())->markFailed($runId, $e->getMessage());
            Event::emit('ai.run.failed', [
                'run_id' => $runId,
                'user_id' => (int)$run->user_id,
                'conversation_id' => (int)$run->conversation_id,
                'error_msg' => $e->getMessage(),
                'latency_ms' => (int)((microtime(true) - $start) * 1000),
            ]);
        }
    }

    private function buildContext(object $run): array
    {
        $conversation = (new AiConversationsDep())->find((int)$run->conversation_id);
        if (!$conversation || (int)$conversation->is_del !== CommonEnum::NO) {
            throw new RuntimeException('会话不存在');
        }

        $agent = (new AiAgentsDep())->get((int)$run->agent_id);
        if (!$agent || (int)$agent->status !== CommonEnum::YES) {
            throw new RuntimeException('智能体不存在或已禁用');
        }

        $model = (new AiModelsDep())->get((int)$agent->model_id);
        if (!$model || (int)$model->status !== CommonEnum::YES) {
            throw new RuntimeException('模型不存在或已禁用');
        }

        $userMessage = (new AiMessagesDep())->find((int)$run->user_message_id);
        if (!$userMessage || (int)$userMessage->is_del !== CommonEnum::NO) {
            throw new RuntimeException('用户消息不存在');
        }

        $runMeta = $this->decodeJson($run->meta_json ?? []);
        $messageMeta = $this->decodeJson($userMessage->meta_json ?? []);
        $runtime = is_array($runMeta['runtime_overrides'] ?? null) ? $runMeta['runtime_overrides'] : [];
        $attachments = is_array($messageMeta['attachments'] ?? null) ? $messageMeta['attachments'] : [];
        $maxHistory = (int)($runtime['max_history'] ?? 20);

        return [
            'run' => $run,
            'conversation' => $conversation,
            'agent' => $agent,
            'model' => $model,
            'user_message' => $userMessage,
            'content' => (string)$userMessage->content,
            'attachments' => $attachments,
            'runtime' => $runtime,
            'max_history' => max(1, min(100, $maxHistory)),
        ];
    }

    private function executeModelOrImage(int $runId, array $ctx, float $start): void
    {
        $imageIntent = (new AiImageIntentDetector())->detect($ctx['content']);
        if ($imageIntent !== null) {
            $this->executeImage($runId, $ctx, $imageIntent, $start);
            return;
        }

        $this->executeText($runId, $ctx, $start);
    }

    private function executeText(int $runId, array $ctx, float $start): void
    {
        $stepNo = 0;
        $agent = $ctx['agent'];
        $model = $ctx['model'];
        $conversationId = (int)$ctx['conversation']->id;
        $userMessageId = (int)$ctx['user_message']->id;

        $ragChunks = $this->retrieveRagChunks($agent, $ctx['content']);
        if (!empty($ragChunks)) {
            $agent->system_prompt = AiRagService::buildAugmentedSystemPrompt((string)($agent->system_prompt ?? ''), $ragChunks);
        }

        $history = AiChatService::buildMessages($agent, $conversationId, $ctx['max_history'], $userMessageId);
        $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_PROMPT, [
            'messages_count' => count($history),
            'model' => (string)$model->model_code,
            'max_history' => $ctx['max_history'],
        ], (int)$agent->id, (string)$model->model_code);

        if (!empty($ragChunks)) {
            $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_RAG, [
                'chunks_count' => count($ragChunks),
                'documents' => array_values(array_unique(array_map(
                    static fn(array $chunk) => $chunk['document_title'] ?? '',
                    $ragChunks
                ))),
            ], (int)$agent->id, (string)$model->model_code);
        }

        $userContent = AiChatService::buildMultimodalContent($ctx['content'], $ctx['attachments']);
        [$neuronAgent, $error] = AiChatService::createAgent($model, $agent, $ctx['runtime'] ?: null);
        if ($error) {
            throw new RuntimeException($error);
        }

        $llmStart = microtime(true);
        $llmStepId = (new AiRunStepsDep())->add([
            'run_id' => $runId,
            'step_no' => ++$stepNo,
            'step_type' => AiEnum::STEP_TYPE_LLM,
            'agent_id' => (int)$agent->id,
            'model_snapshot' => (string)$model->model_code,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode(['model' => (string)$model->model_code, 'stream' => true], JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);

        $result = AiChatService::chatStream(
            $neuronAgent,
            $userContent,
            $history,
            fn(string $delta) => $this->events->publish($runId, 'content', ['delta' => $delta]),
            fn($callId, $toolName, $toolInputs) => $this->events->publish($runId, 'tool_call', [
                'call_id' => $callId,
                'tool_name' => $toolName,
                'tool_inputs' => $toolInputs,
            ]),
            fn($callId, $toolName, $toolResult) => $this->events->publish($runId, 'tool_result', [
                'call_id' => $callId,
                'tool_name' => $toolName,
                'tool_result' => mb_substr((string)$toolResult, 0, 2000),
            ]),
            fn() => (int)((new AiRunsDep())->find($runId)?->run_status ?? 0) === AiEnum::RUN_STATUS_CANCELED
        );

        if (!empty($result['canceled'])) {
            (new AiRunStepsDep())->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, '用户取消', (int)((microtime(true) - $llmStart) * 1000));
            $assistantMessageId = null;
            if (!empty($result['content'])) {
                $assistantMessageId = $this->saveAssistantMessage($conversationId, (string)$result['content'], [
                    'blocks' => [['type' => 'text', 'text' => (string)$result['content']]],
                    'run_request_id' => (string)$ctx['run']->request_id,
                    'finish_reason' => 'canceled',
                ]);
            }
            $this->events->publish($runId, 'canceled', [
                'conversation_id' => $conversationId,
                'run_id' => $runId,
                'user_message_id' => $userMessageId,
                'assistant_message_id' => $assistantMessageId,
            ]);
            return;
        }

        (new AiRunStepsDep())->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_SUCCESS, null, (int)((microtime(true) - $llmStart) * 1000));

        $content = (string)($result['content'] ?? '');
        $assistantMessageId = $this->saveAssistantMessage($conversationId, $content, [
            'blocks' => [['type' => 'text', 'text' => $content]],
            'run_request_id' => (string)$ctx['run']->request_id,
            'provider_request_id' => $result['request_id'] ?? null,
        ]);

        $this->addStep($runId, ++$stepNo, AiEnum::STEP_TYPE_FINALIZE, [
            'assistant_message_id' => $assistantMessageId,
            'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
            'total_tokens' => $result['usage']['total_tokens'] ?? null,
        ], (int)$agent->id, (string)$model->model_code);

        $latencyMs = (int)((microtime(true) - $start) * 1000);
        (new AiRunsDep())->markSuccess($runId, [
            'assistant_message_id' => $assistantMessageId,
            'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
            'total_tokens' => $result['usage']['total_tokens'] ?? null,
            'latency_ms' => $latencyMs,
        ]);

        $this->events->publish($runId, 'done', [
            'conversation_id' => $conversationId,
            'run_id' => $runId,
            'user_message_id' => $userMessageId,
            'assistant_message_id' => $assistantMessageId,
        ]);

        Event::emit('ai.run.completed', [
            'run_id' => $runId,
            'user_id' => (int)$ctx['run']->user_id,
            'conversation_id' => $conversationId,
            'assistant_message_id' => $assistantMessageId,
            'total_tokens' => $result['usage']['total_tokens'] ?? null,
            'latency_ms' => $latencyMs,
        ]);
    }

    private function executeImage(int $runId, array $ctx, array $imageIntent, float $start): void
    {
        $conversationId = (int)$ctx['conversation']->id;
        $userMessageId = (int)$ctx['user_message']->id;
        $model = $this->resolveImageModel($ctx);
        $prompt = (string)$imageIntent['prompt'];

        $this->events->publish($runId, 'image_generating', ['prompt' => $prompt]);
        $stepStart = microtime(true);
        $stepId = $this->addStep($runId, 1, AiEnum::STEP_TYPE_IMAGE, [
            'prompt' => $prompt,
            'model' => (string)$model->model_code,
        ], (int)$ctx['agent']->id, (string)$model->model_code);

        try {
            $imageOptions = is_array($ctx['runtime']['image'] ?? null) ? $ctx['runtime']['image'] : [];
            $imageBlock = (new AiImageGenerationService())->generate($model, $prompt, $imageOptions);
        } catch (\Throwable $e) {
            (new AiRunStepsDep())->updateStepStatus($stepId, AiEnum::STEP_STATUS_FAIL, $e->getMessage(), (int)((microtime(true) - $stepStart) * 1000));
            throw $e;
        }

        (new AiRunStepsDep())->updateStepStatus($stepId, AiEnum::STEP_STATUS_SUCCESS, null, (int)((microtime(true) - $stepStart) * 1000));

        $text = '图片已生成';
        $assistantMessageId = $this->saveAssistantMessage($conversationId, $text, [
            'blocks' => [
                ['type' => 'text', 'text' => $text],
                $imageBlock,
            ],
            'run_request_id' => (string)$ctx['run']->request_id,
        ]);

        $this->addStep($runId, 2, AiEnum::STEP_TYPE_FINALIZE, [
            'assistant_message_id' => $assistantMessageId,
        ], (int)$ctx['agent']->id, (string)$model->model_code);

        $latencyMs = (int)((microtime(true) - $start) * 1000);
        (new AiRunsDep())->markSuccess($runId, [
            'assistant_message_id' => $assistantMessageId,
            'latency_ms' => $latencyMs,
            'model_snapshot' => (string)$model->model_code,
        ]);

        $this->events->publish($runId, 'image_done', [
            'url' => $imageBlock['url'] ?? '',
            'block' => $imageBlock,
        ]);
        $this->events->publish($runId, 'done', [
            'conversation_id' => $conversationId,
            'run_id' => $runId,
            'user_message_id' => $userMessageId,
            'assistant_message_id' => $assistantMessageId,
        ]);

        Event::emit('ai.run.completed', [
            'run_id' => $runId,
            'user_id' => (int)$ctx['run']->user_id,
            'conversation_id' => $conversationId,
            'assistant_message_id' => $assistantMessageId,
            'total_tokens' => null,
            'latency_ms' => $latencyMs,
        ]);
    }

    private function resolveImageModel(array $ctx): object
    {
        $runtime = $ctx['runtime'];
        $imageModelId = (int)($runtime['image_model_id'] ?? 0);
        if ($imageModelId > 0) {
            $model = (new AiModelsDep())->get($imageModelId);
            if (!$model || (int)$model->status !== CommonEnum::YES) {
                throw new RuntimeException('图片模型不存在或已禁用');
            }
            return $model;
        }

        $imageModel = (new AiModelsDep())->getLatestActiveByModelCode('gpt-image-2');
        return $imageModel ?: $ctx['model'];
    }

    private function retrieveRagChunks(object $agent, string $content): array
    {
        if (!$this->agentHasCapability($agent, AiEnum::CAPABILITY_RAG)) {
            return [];
        }

        $knowledgeBases = (new AiAgentKnowledgeBasesDep())->getActiveKnowledgeBasesByAgentId((int)$agent->id);
        if ($knowledgeBases->isEmpty()) {
            return [];
        }

        $knowledgeBaseIds = $knowledgeBases->pluck('id')->map(fn($id) => (int)$id)->toArray();
        $topK = (int)max($knowledgeBases->pluck('top_k')->map(fn($value) => (int)$value)->toArray() ?: [5]);
        $threshold = (float)min($knowledgeBases->pluck('score_threshold')->map(fn($value) => (float)$value)->toArray() ?: [0]);

        return AiRagService::retrieveFromKnowledgeBases($knowledgeBaseIds, $content, $topK, $threshold);
    }

    private function agentHasCapability(object $agent, string $capability): bool
    {
        $capabilities = $agent->capabilities_json ?? [];
        if (is_string($capabilities)) {
            $decoded = json_decode($capabilities, true);
            $capabilities = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($capabilities)) {
            $capabilities = [];
        }

        if (array_key_exists($capability, $capabilities)) {
            return (bool)$capabilities[$capability];
        }

        return match ($capability) {
            AiEnum::CAPABILITY_RAG => ($agent->mode ?? '') === AiEnum::MODE_RAG,
            AiEnum::CAPABILITY_TOOLS => ($agent->mode ?? '') === AiEnum::MODE_TOOL,
            AiEnum::CAPABILITY_WORKFLOW => ($agent->mode ?? '') === AiEnum::MODE_WORKFLOW,
            default => false,
        };
    }

    private function addStep(
        int $runId,
        int $stepNo,
        int $stepType,
        array $payload,
        ?int $agentId = null,
        ?string $modelSnapshot = null
    ): int
    {
        $data = [
            'run_id' => $runId,
            'step_no' => $stepNo,
            'step_type' => $stepType,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ];
        if ($agentId !== null) {
            $data['agent_id'] = $agentId;
        }
        if ($modelSnapshot !== null) {
            $data['model_snapshot'] = $modelSnapshot;
        }

        return (new AiRunStepsDep())->add($data);
    }

    private function saveAssistantMessage(int $conversationId, string $content, array $meta): int
    {
        $messageId = (new AiMessagesDep())->add([
            'conversation_id' => $conversationId,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $content,
            'meta_json' => json_encode($this->filterNullRecursive($meta), JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);

        (new AiConversationsDep())->updateLastMessageAt($conversationId);

        return $messageId;
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function filterNullRecursive(array $data): array
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = $this->filterNullRecursive($value);
                continue;
            }
            if ($value !== null) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
