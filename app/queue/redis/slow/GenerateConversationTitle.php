<?php

namespace app\queue\redis\slow;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiModelsDep;
use app\service\Ai\AiChatService;
use Webman\RedisQueue\Consumer;

/**
 * 异步生成会话标题
 */
class GenerateConversationTitle implements Consumer
{
    public $queue = 'generate_conversation_title';
    public $connection = 'default';

    public function consume($data): void
    {
        $conversationId = $data['conversation_id'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $userMessage = $data['user_message'] ?? '';
        $userId = $data['user_id'] ?? null;

        if (!$conversationId || !$agentId || !$userMessage) {
            $this->log('Missing required data', $data);
            return;
        }

        $conversationsDep = new AiConversationsDep();
        $agentsDep = new AiAgentsDep();
        $modelsDep = new AiModelsDep();
        $chatService = new AiChatService();

        // 检查会话是否存在且标题为空
        $conversation = $conversationsDep->getByUser($conversationId, $userId);
        if (!$conversation) {
            $this->log('Conversation not found', ['conversation_id' => $conversationId]);
            return;
        }

        // 如果已有标题，跳过
        if (!empty($conversation->title)) {
            return;
        }

        // 获取智能体和模型
        $agent = $agentsDep->get($agentId);
        if (!$agent) {
            $this->log('Agent not found', ['agent_id' => $agentId]);
            return;
        }

        $model = $modelsDep->get((int)$agent->model_id);
        if (!$model) {
            $this->log('Model not found', ['model_id' => $agent->model_id]);
            return;
        }

        // 创建客户端
        [$client, $config, $error] = $chatService->createClient($model);
        if ($error) {
            $this->log('Failed to create client', ['error' => $error]);
            return;
        }

        // 生成标题
        try {
            $title = $chatService->generateTitle($client, $config, $model->model_code, $userMessage);
            if ($title) {
                $conversationsDep->updateTitle($conversationId, $title, $userId);
                $this->log('Title generated', [
                    'conversation_id' => $conversationId,
                    'title' => $title
                ]);
            }
        } catch (\Throwable $e) {
            $this->log('Failed to generate title', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function onConsumeFailure(\Throwable $e, $package): void
    {
        $this->log('Queue consume failed', [
            'error' => $e->getMessage(),
            'package' => $package,
        ]);
    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue);
        $logger->info($msg, $context);
    }
}
