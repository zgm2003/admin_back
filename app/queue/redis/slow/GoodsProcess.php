<?php

namespace app\queue\redis\slow;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiModelsDep;
use app\dep\Ai\GoodsDep;
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
     * AI生成口播词 — 复用已有的智能体 + 模型体系
     */
    private function handleGenerate(GoodsDep $dep, $goods, array $data): void
    {
        $tips    = $data['tips'] ?? $goods->tips ?? '';
        $ocrText = $goods->ocr ?? '';
        $title   = $goods->title ?? '';

        // 查找商品口播专用智能体
        $agentsDep = new AiAgentsDep();
        $agent     = $agentsDep->getByScene('goods_script');
        if (!$agent) {
            throw new \RuntimeException('未配置商品口播智能体，请在AI智能体管理中创建 scene=goods_script 的智能体');
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
        $result  = $chatService->chat($client, $payload, $config);

        $content = $result['content'] ?? '';

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
