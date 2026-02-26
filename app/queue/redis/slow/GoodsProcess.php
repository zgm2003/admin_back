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

        // 构建用户消息（含爬虫采集的结构化元数据）
        $meta = $goods->meta ?? null;
        $userMessage = $this->buildPrompt($title, $ocrText, $tips, $meta);

        // 通过 Neuron AI 创建 Agent
        [$neuronAgent, $error] = AiChatService::createAgent($model, $agent);
        if ($error) {
            throw new \RuntimeException("AI Agent 创建失败: {$error}");
        }

        $userMessage_forAi = $userMessage;

        // 创建运行记录
        $runsDep   = new AiRunsDep();
        $stepsDep  = new AiRunStepsDep();
        $requestId = AiChatService::generateRequestId();
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
            'payload_json' => json_encode(['messages_count' => 1, 'model' => $model->model_code], JSON_UNESCAPED_UNICODE),
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
            $result  = AiChatService::chat($neuronAgent, $userMessage_forAi);
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
     * TTS语音合成 — 调用 edge-tts 命令行
     */
    private function handleTts(GoodsDep $dep, $goods, array $data): void
    {
        $scriptText = $data['script_text'] ?? $goods->script_text ?? '';
        if (empty($scriptText)) {
            throw new \RuntimeException('没有口播词内容');
        }

        $voice = $data['voice'] ?? GoodsEnum::VOICE_XIAOXIAO;
        $emotion = $data['emotion'] ?? GoodsEnum::EMOTION_DEFAULT;
        $emotionParams = GoodsEnum::$emotionParamsMap[$emotion] ?? GoodsEnum::$emotionParamsMap[GoodsEnum::EMOTION_DEFAULT];

        // 生成文件路径：public/audio/tts/{date}/{id}_{timestamp}.mp3
        $dateDir  = date('Ymd');
        $audioDir = public_path() . '/audio/tts/' . $dateDir;
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }
        $filename = $goods->id . '_' . time() . '.mp3';
        $filePath = $audioDir . '/' . $filename;

        // 写入临时文本文件（避免命令行转义问题）
        $tmpFile = runtime_path() . '/tts_' . $goods->id . '.txt';
        file_put_contents($tmpFile, $scriptText);

        // VTT 字幕输出路径（edge-tts --write-subtitles 生成）
        $vttFilename = $goods->id . '_' . time() . '.vtt';
        $vttPath     = $audioDir . '/' . $vttFilename;

        // 调用 edge-tts（含情绪预设的 rate/pitch/volume + 字幕输出）
        // 注意：rate/pitch/volume 来自枚举常量，不用 escapeshellarg（Windows 下 % 会被 cmd 吞掉）
        $cmd = sprintf(
            'edge-tts --voice %s --rate=%s --pitch=%s --volume=%s --file %s --write-media %s --write-subtitles %s 2>&1',
            escapeshellarg($voice),
            $emotionParams['rate'],
            $emotionParams['pitch'],
            $emotionParams['volume'],
            escapeshellarg($tmpFile),
            escapeshellarg($filePath),
            escapeshellarg($vttPath)
        );

        $this->log('执行TTS命令', ['cmd' => $cmd]);
        exec($cmd, $output, $exitCode);

        // 清理临时文件
        @unlink($tmpFile);

        if ($exitCode !== 0 || !file_exists($filePath)) {
            throw new \RuntimeException('edge-tts执行失败: ' . implode("\n", $output));
        }

        // VTT → SRT 转换
        $srtUrl = null;
        if (file_exists($vttPath)) {
            $srtFilename = str_replace('.vtt', '.srt', $vttFilename);
            $srtPath     = $audioDir . '/' . $srtFilename;
            $srtContent  = $this->vttToSrt(file_get_contents($vttPath));
            file_put_contents($srtPath, $srtContent);
            @unlink($vttPath); // VTT 已转换，删除

            $appUrl = rtrim(getenv('APP_URL') ?: '', '/');
            $srtUrl = $appUrl . '/audio/tts/' . $dateDir . '/' . $srtFilename;
        }

        // 生成完整访问URL（与导出任务一致，使用 APP_URL 拼接）
        $appUrl   = rtrim(getenv('APP_URL') ?: '', '/');
        $audioUrl = $appUrl . '/audio/tts/' . $dateDir . '/' . $filename;

        $extra = ['audio_url' => $audioUrl];
        if ($srtUrl) {
            $extra['srt_url'] = $srtUrl;
        }

        $dep->transitStatus($goods->id, GoodsEnum::STATUS_TTS, GoodsEnum::STATUS_COMPLETED, $extra);
    }

    // ==================== 工具方法 ====================

    /**
     * 构建AI提示词（用户消息部分）
     */
    private function buildPrompt(string $title, string $ocrText, string $tips, ?array $meta = null): string
    {
        $parts = ["请根据以下商品信息，生成直播口播词。\n"];
        $parts[] = "【商品标题】{$title}";

        // 结构化元数据（爬虫采集）
        if (!empty($meta)) {
            $metaLabels = [
                'price' => '价格', 'originalPrice' => '原价', 'sales' => '销量',
                'brand' => '品牌', 'shop' => '店铺', 'specs' => '规格',
                'description' => '商品描述', 'reviews' => '用户评价',
            ];
            $metaParts = [];
            foreach ($metaLabels as $key => $label) {
                $val = $meta[$key] ?? '';
                if (is_array($val)) $val = implode('、', $val);
                if (!empty($val)) $metaParts[] = "{$label}: {$val}";
            }
            if ($metaParts) {
                $parts[] = "【商品信息】\n" . implode("\n", $metaParts);
            }
        }

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

    /**
     * WebVTT → SRT 格式转换
     */
    private function vttToSrt(string $vtt): string
    {
        $vtt = preg_replace('/^WEBVTT\s*\n+/i', '', $vtt);
        $blocks = preg_split('/\n{2,}/', trim($vtt));

        $srt = [];
        $index = 1;
        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            // 时间戳 . → ,（SRT格式要求）
            $block = preg_replace_callback(
                '/(\d{2}:\d{2}:\d{2})\.(\d{3})/',
                fn($m) => $m[1] . ',' . $m[2],
                $block
            );

            if (!preg_match('/^\d+\s*\n/', $block)) {
                $block = $index . "\n" . $block;
            }

            $srt[] = $block;
            $index++;
        }

        return implode("\n\n", $srt) . "\n";
    }

    private function log($msg, $context = [])
    {
        log_daily("queue_{$this->queue}")->info($msg, $context);
    }
}
