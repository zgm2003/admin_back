<?php

namespace app\queue\redis\fast\ai_task_prompt;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoDep;
use app\dep\AiWorkLine\AiImageVideo\AiImageVideoTaskDep;
use app\enum\AiImageVideoEnum;
use app\lib\AliCloud\AigcSdk;
use Webman\RedisQueue\Consumer;

class AiTaskPrompt implements Consumer
{
    // 要消费的队列名
    public $queue = 'ai-task-prompt';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $dep = new AiImageVideoTaskDep();
        $aiImageVideoDep = new AiImageVideoDep();
        $item = $dep->first($this->contentId);
        if (!$item) {
            $this->log('No content found with ID: ' . $this->contentId);
            return false;
        }
        $sdk = new AigcSdk();

        $prompt = $item->prompt;
        $chat = str_replace('{name}', $item->name, $prompt);
        $chat = str_replace('{platform}', AiImageVideoEnum::$platformArr[$item->platform], $chat);
        $resChat = $sdk->chat("你现在是一名专业的运营", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];
        $this->log("AI response: {$origin}");
        $content = trim(str_replace(['```json', '```'], '', $origin));
        $contentArr = json_decode($content, true);
        // 校验 JSON 解析结果
        if (!is_array($contentArr)) {
            throw new \Exception("JSON 解析错误，返回内容: " . $content);
        }
        $data = [
            'status' => AiImageVideoEnum::TASK_PROMPT_SUCCESS,
            'status_msg' => '生成成功',
        ];
        $dep->edit($this->contentId,$data);

        foreach ($contentArr as $content){
            $data = [
                'task_id' => $item->id,
                'status' => AiImageVideoEnum::DRAFT,
                'title' => $content['标题'],
                'text' => $content['文案'],
                'image_prompt' => $content['生图提示词'],
                'video_prompt' => $content['生视频提示词'],
            ];
            $aiImageVideoDep->add($data);
        }
        $this->log("Generated content: " . json_encode($contentArr));
        return true;

    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log("Error: {$e->getMessage()}");
        $dep = new AiImageVideoTaskDep();
        $data = [
            'status' => AiImageVideoEnum::TASK_PROMPT_ERROR,
            'status_msg' => $e->getMessage(),
        ];
        $dep->edit($this->contentId,$data);

    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
            
}
