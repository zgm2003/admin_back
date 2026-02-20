<?php

namespace app\queue\redis\slow;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\AiRunsDep;
use app\dep\Ai\AiRunStepsDep;
use app\dep\Ai\GoodsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use app\lib\OcrSdk;
use app\service\Ai\AiChatService;
use Webman\RedisQueue\Consumer;

/**
 * 商品异步处理队列（OCR / AI生成 / TTS）
 */
class GoodsProcess implements Consumer
{
    public $queue = 'goods_process';
    public $connection = 'default';

    public function consume($data)
    {
        $id   = $data['id'];
        $step = $data['step']; // ocr | generate | tts
        $dep  = new GoodsDep();

        $this->log("开始处理 [{$step}]", ['id' => $id]);

        $goods = $dep->getOrFail($id);

        try {
            match ($step) {
                'ocr'      => $this->handleOcr($dep, $goods, $data),
                'generate' => $this->handleGenerate($dep, $goods, $data),
                'tts'      => $this->handleTts($dep, $goods, $data),
            };
            $this->log("{$step} 完成", ['id' => $id]);
        } catch (\Throwable $e) {
            $dep->markFailed($id, "{$step}失败: " . $e->getMessage());
            $this->log("{$step} 失败", ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * OCR识别
     */
    private function handleOcr(GoodsDep $dep, $goods, array $data): void
    {
        $images = $data['image_list_success'] ?? [];
        if (empty($images)) {
            throw new \RuntimeException('没有可识别的图片');
        }

        $ocrSdk  = new OcrSdk();
        $result  = $ocrSdk->ocrList($images);
        $ocrText = $this->extractOcrText($result);

        $dep->transitStatus($goods->id, GoodsEnum::STATUS_OCR, GoodsEnum::STATUS_RECOGNIZED, [
            'ocr' => $ocrText,
        ]);
    }

    /**
     * AI生成口播词 — 使用指定智能体 + 记录运行日志
     */
    private function handleGenerate(GoodsDep $dep, $goods, array $data): void
    {
        $tips    = $data['tips'] ?? $goods->tips ?? '';
        $ocrText = $goods->ocr ?? '';
        $title   = $goods->title ?? '';

        // 使用前端指定的智能体
        $agentId   = (int)($data['agent_id'] ?? 0);
        $agentsDep = new AiAgentsDep();
        $agent     = $agentId ? $agentsDep->get($agentId) : $agentsDep->getByScene('goods_script');
        if (!$agent) {
            throw new \RuntimeException('智能体不存在或未启用，请检查配置');
        }

        // 获取关联模型
        $modelsDep = new AiModelsDep();
        $model     = $modelsDep->getOrFail($agent->model_id);

        // 构建用户消息
        $userMessage = $this->buildPrompt($title, $ocrText, $tips);

        // 通过 AiChatService 调用
        $chatService = new AiChatService();
        [$client, $config, $error] = $chatService->createClient($model);
        if ($error) {
            throw new \RuntimeException("AI客户端创建失败: {$error}");
        }

        $messages = [];
        if (!empty($agent->system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $agent->system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = $chatService->buildPayload($agent, $model, $messages);

        // 创建运行记录
        $runsDep   = new AiRunsDep();
        $stepsDep  = new AiRunStepsDep();
        $requestId = $chatService->generateRequestId();
        $startTime = microtime(true);
        $stepNo    = 0;

        $runId = $runsDep->add([
            'request_id'      => $requestId,
            'user_id'         => 0,
            'agent_id'        => $agent->id,
            'conversation_id' => 0,
            'user_message_id' => 0,
            'run_status'      => AiEnum::RUN_STATUS_RUNNING,
            'model_snapshot'  => $model->model_code,
            'is_del'          => CommonEnum::NO,
        ]);

        // Step 1: 提示词构建
        $stepsDep->add([
            'run_id'       => $runId,
            'step_no'      => ++$stepNo,
            'step_type'    => AiEnum::STEP_TYPE_PROMPT,
            'status'       => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode(['messages_count' => count($messages), 'model' => $model->model_code], JSON_UNESCAPED_UNICODE),
            'is_del'       => CommonEnum::NO,
        ]);

        // Step 2: LLM 调用
        $llmStart  = microtime(true);
        $llmStepId = $stepsDep->add([
            'run_id'       => $runId,
            'step_no'      => ++$stepNo,
            'step_type'    => AiEnum::STEP_TYPE_LLM,
            'status'       => AiEnum::STEP_STATUS_SUCCESS,
            'payload_json' => json_encode(['model' => $model->model_code, 'stream' => false], JSON_UNESCAPED_UNICODE),
            'is_del'       => CommonEnum::NO,
        ]);

        try {
            $result  = $chatService->chat($client, $payload, $config);
            $content = $result['content'] ?? '';
            $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
            $totalLatency = (int)((microtime(true) - $startTime) * 1000);

            // 更新 LLM 步骤
            $stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_SUCCESS, null, $llmLatency);

            // Step 3: 最终化
            $stepsDep->add([
                'run_id'       => $runId,
                'step_no'      => ++$stepNo,
                'step_type'    => AiEnum::STEP_TYPE_FINALIZE,
                'status'       => AiEnum::STEP_STATUS_SUCCESS,
                'payload_json' => json_encode([
                    'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? null,
                    'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                    'total_tokens'      => $result['usage']['total_tokens'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'is_del'       => CommonEnum::NO,
            ]);

            // 标记运行成功
            $runsDep->markSuccess($runId, [
                'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $result['usage']['total_tokens'] ?? 0,
                'latency_ms'        => $totalLatency,
            ]);
        } catch (\Throwable $e) {
            $llmLatency = (int)((microtime(true) - $llmStart) * 1000);
            $stepsDep->updateStepStatus($llmStepId, AiEnum::STEP_STATUS_FAIL, $e->getMessage(), $llmLatency);
            $runsDep->markFailed($runId, $e->getMessage());
            throw $e;
        }

        // 解析AI返回的卖点和口播词
        $parsed = $this->parseAiResponse($content);

        $dep->transitStatus($goods->id, GoodsEnum::STATUS_GENERATING, GoodsEnum::STATUS_GENERATED, [
            'point'        => $parsed['point'],
            'script_text'  => $parsed['script_text'],
            'model_origin' => $content,
        ]);
    }

    /**
     * TTS语音合成
     * TODO: 对接实际的TTS服务（阿里云 / SiliconFlow）
     */
    private function handleTts(GoodsDep $dep, $goods, array $data): void
    {
        $scriptText = $data['script_text'] ?? $goods->script_text ?? '';
        if (empty($scriptText)) {
            throw new \RuntimeException('没有口播词内容');
        }

        // TODO: 替换为实际TTS调用
        throw new \RuntimeException('TTS服务尚未对接，请实现 GoodsProcess::handleTts()');
    }

    // ==================== 工具方法 ====================

    /**
     * 构建AI提示词（用户消息部分）
     */
    private function buildPrompt(string $title, string $ocrText, string $tips): string
    {
        $parts = ["请根据以下商品信息，生成直播口播词。\n"];
        $parts[] = "【商品标题】{$title}";
        if ($ocrText) $parts[] = "【商品详情(OCR识别)】\n{$ocrText}";
        if ($tips)    $parts[] = "【额外要求】{$tips}";
        $parts[] = "\n请严格按以下格式输出：";
        $parts[] = "【卖点提炼】\n（3-5个核心卖点，每个一行）";
        $parts[] = "【口播词】\n（200-400字的直播口播文案，语气自然亲切）";

        return implode("\n", $parts);
    }

    /**
     * 解析AI返回内容，提取卖点和口播词
     */
    private function parseAiResponse(string $content): array
    {
        $point = '';
        $scriptText = '';

        // 尝试按【卖点提炼】和【口播词】分割
        if (preg_match('/【卖点提炼】\s*(.*?)(?=【口播词】)/s', $content, $m)) {
            $point = trim($m[1]);
        }
        if (preg_match('/【口播词】\s*(.*)/s', $content, $m)) {
            $scriptText = trim($m[1]);
        }

        // 如果解析失败，整段作为口播词
        if (empty($point) && empty($scriptText)) {
            $scriptText = $content;
        }

        return ['point' => $point, 'script_text' => $scriptText];
    }

    private function extractOcrText(array $result): string
    {
        $lines = [];
        foreach ($result['results'] ?? [] as $item) {
            if (!empty($item['text'])) {
                $lines[] = $item['text'];
            }
        }
        return implode("\n", $lines);
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $id   = $data['id'] ?? null;
        $step = $data['step'] ?? 'unknown';

        if ($id) {
            (new GoodsDep())->markFailed($id, "{$step}最终失败: " . $e->getMessage());
        }
        $this->log('队列消费最终失败', ['id' => $id, 'step' => $step, 'error' => $e->getMessage()]);
    }

    private function log($msg, $context = [])
    {
        log_daily("queue_{$this->queue}")->info($msg, $context);
    }
}
