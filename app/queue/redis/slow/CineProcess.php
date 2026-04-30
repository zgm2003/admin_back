<?php

namespace app\queue\redis\slow;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\dep\Ai\CineAssetDep;
use app\dep\Ai\CineProjectDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\service\Ai\AiChatService;
use app\service\Ai\CineGenerationService;
use NeuronAI\Agent\Agent;
use Webman\RedisQueue\Consumer;

class CineProcess implements Consumer
{
    public $queue = 'cine_process';
    public $connection = 'default';

    public function consume($data)
    {
        $id = (int)($data['id'] ?? 0);
        $projectDep = new CineProjectDep();
        $project = $projectDep->getOrFail($id);

        $this->log('开始生成短剧草稿', ['id' => $id]);

        try {
            $this->handleGenerate($projectDep, $project, $data);
            $this->log('短剧草稿生成完成', ['id' => $id]);
        } catch (\Throwable $e) {
            $projectDep->markFailed($id, '草稿生成失败: ' . $e->getMessage());
            $this->log('短剧草稿生成失败', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function handleGenerate(CineProjectDep $projectDep, $project, array $data): void
    {
        $userId = (int)($data['user_id'] ?? $project->user_id ?? 0);
        $agentId = (int)($data['agent_id'] ?? $project->agent_id ?? 0);

        $agentsDep = new AiAgentsDep();
        $agent = $agentId > 0 ? $agentsDep->get($agentId) : $agentsDep->getByScene(AiEnum::SCENE_CINE_PROJECT);
        if (!$agent || (int)$agent->status !== CommonEnum::YES) {
            throw new \RuntimeException('智能体不存在或未启用，请检查 AI短剧工厂 agent 配置');
        }

        $model = (new AiModelsDep())->getOrFail((int)$agent->model_id);
        $service = new CineGenerationService();
        $prompt = $service->buildGenerationPrompt($project->toArray());

        [$neuronAgent, $error] = AiChatService::createAgent($model, $agent, [
            'disable_tools' => true,
            'http_timeout' => 180,
            'connect_timeout' => 10,
            'reasoning_effort' => 'low',
            'max_output_tokens' => 6000,
        ]);
        if ($error) {
            throw new \RuntimeException("AI Agent 创建失败: {$error}");
        }

        $runsDep = new AiRunsDep();
        $messagesDep = new AiMessagesDep();
        $stepsDep = new AiRunStepsDep();
        $requestId = AiChatService::generateRequestId();
        $startTime = microtime(true);
        $stepNo = 0;

        $runId = $this->createRunAudit(
            $runsDep,
            $messagesDep,
            $userId,
            (int)$agent->id,
            $requestId,
            (string)$model->model_code,
            $prompt,
            (int)$project->id,
            (string)$project->title
        );

        $stepsDep->add([
            'run_id' => $runId,
            'step_no' => ++$stepNo,
            'step_type' => AiEnum::STEP_TYPE_PROMPT,
            'agent_id' => (int)$agent->id,
            'model_snapshot' => (string)$model->model_code,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode([
                'scene' => AiEnum::SCENE_CINE_PROJECT,
                'mode' => $project->mode,
                'duration_seconds' => $project->duration_seconds,
                'aspect_ratio' => $project->aspect_ratio,
            ], JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);

        $llmStart = microtime(true);
        $llmStepId = $stepsDep->add([
            'run_id' => $runId,
            'step_no' => ++$stepNo,
            'step_type' => AiEnum::STEP_TYPE_LLM,
            'agent_id' => (int)$agent->id,
            'model_snapshot' => (string)$model->model_code,
            'status' => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode(['model' => $model->model_code, 'stream' => true], JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);

        try {
            $result = $this->generateDraftByStream($neuronAgent, $prompt);
            $content = $result['content'] ?? '';
            $parsed = $service->parseModelContent($content, $project->toArray());
            if (empty($parsed['shotlist'])) {
                throw new \RuntimeException('草稿解析失败：模型未返回可用分镜，请重试或缩短素材');
            }

            $assets = $service->buildKeyframeAssets((int)$project->id, $parsed['shotlist']);

            $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
            $totalLatency = (int)((microtime(true) - $startTime) * 1000);
            $stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_SUCCESS, null, $llmLatency);

            $stepsDep->add([
                'run_id' => $runId,
                'step_no' => ++$stepNo,
                'step_type' => AiEnum::STEP_TYPE_FINALIZE,
                'agent_id' => (int)$agent->id,
                'model_snapshot' => (string)$model->model_code,
                'status' => AiEnum::STEP_STATUS_SUCCESS,
                'payload_json' => json_encode([
                    'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                    'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                    'total_tokens' => $result['usage']['total_tokens'] ?? null,
                    'keyframe_assets' => count($assets),
                    'stream_meta' => $result['stream_meta'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'is_del' => CommonEnum::NO,
            ]);

            $assistantMessageId = $this->storeAssistantMessage(
                $messagesDep,
                $content,
                $requestId,
                (int)$project->id,
                (string)$project->title
            );

            $projectDep->saveGenerationResult((int)$project->id, $parsed);
            (new CineAssetDep())->replaceProjectAssets((int)$project->id, $assets);

            $runsDep->markSuccess($runId, [
                'assistant_message_id' => $assistantMessageId,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0,
                'latency_ms' => $totalLatency,
            ]);
        } catch (\Throwable $e) {
            $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
            $stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, $e->getMessage(), $llmLatency);
            $runsDep->markFailed($runId, $e->getMessage());
            throw $e;
        }
    }

    private function generateDraftByStream(Agent $neuronAgent, string $prompt): array
    {
        $deltaCount = 0;
        $firstDeltaMs = null;
        $start = microtime(true);

        $result = AiChatService::chatStream(
            $neuronAgent,
            $prompt,
            [],
            static function () use (&$deltaCount, &$firstDeltaMs, $start): void {
                ++$deltaCount;
                if ($firstDeltaMs === null) {
                    $firstDeltaMs = (int)((microtime(true) - $start) * 1000);
                }
            }
        );

        $result['stream_meta'] = [
            'delta_count' => $deltaCount,
            'first_delta_ms' => $firstDeltaMs,
        ];

        return $result;
    }

    private function createRunAudit(
        AiRunsDep $runsDep,
        AiMessagesDep $messagesDep,
        int $userId,
        int $agentId,
        string $requestId,
        string $modelCode,
        string $prompt,
        int $projectId,
        string $projectTitle
    ): int {
        $messageMetaJson = json_encode([
            'scene' => AiEnum::SCENE_CINE_PROJECT,
            'cine_project_id' => $projectId,
            'cine_project_title' => $projectTitle,
        ], JSON_UNESCAPED_UNICODE);

        $userMessageId = $messagesDep->add([
            'conversation_id' => 0,
            'role' => AiEnum::ROLE_USER,
            'content' => $prompt,
            'meta_json' => $messageMetaJson,
            'is_del' => CommonEnum::NO,
        ]);

        return $runsDep->add([
            'request_id' => $requestId,
            'user_id' => $userId > 0 ? $userId : 0,
            'agent_id' => $agentId,
            'conversation_id' => 0,
            'user_message_id' => $userMessageId,
            'run_status' => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot' => $modelCode,
            'meta_json' => $messageMetaJson,
            'is_del' => CommonEnum::NO,
        ]);
    }

    private function storeAssistantMessage(
        AiMessagesDep $messagesDep,
        string $content,
        string $requestId,
        int $projectId,
        string $projectTitle
    ): int {
        return $messagesDep->add([
            'conversation_id' => 0,
            'role' => AiEnum::ROLE_ASSISTANT,
            'content' => $content,
            'meta_json' => json_encode([
                'scene' => AiEnum::SCENE_CINE_PROJECT,
                'cine_project_id' => $projectId,
                'cine_project_title' => $projectTitle,
                'run_request_id' => $requestId,
            ], JSON_UNESCAPED_UNICODE),
            'is_del' => CommonEnum::NO,
        ]);
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            (new CineProjectDep())->markFailed($id, '草稿最终失败: ' . $e->getMessage());
        }
        $this->log('短剧队列消费最终失败', ['id' => $id, 'error' => $e->getMessage()]);
    }

    private function log(string $msg, array $context = []): void
    {
        if (!function_exists('log_daily')) {
            return;
        }

        log_daily("queue_{$this->queue}")->info($msg, $context);
    }
}
