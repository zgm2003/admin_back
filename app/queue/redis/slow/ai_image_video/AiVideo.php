<?php

namespace app\queue\redis\slow\ai_image_video;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoDep;
use app\enum\AiImageVideoEnum;
use app\lib\AliCloud\ImageToVideo;
use Webman\RedisQueue\Consumer;
use Webman\RedisQueue\Redis;

class AiVideo implements Consumer
{
    // 要消费的队列名
    public $queue = 'ai-video';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $dep = new AiImageVideoDep();
        $sdk = new ImageToVideo();
        $item = $dep->first($this->contentId);
        if (!$item) {
            $this->log('No content found with ID: ' . $this->contentId);
            return false;
        }
        // 创建一个随机 seed
        $seed = rand(1, 100000);

        // 注意：这里 imageSize 要确保 item->imageSize 是枚举类型对应的整数
        $imageUrls = json_decode($item->image_list_success, true);
        $resSdk = $sdk->createTask(
            'wanx2.1-i2v-turbo',
            $item->video_prompt,
            $imageUrls[0],
            [
                "resolution" => "720P",
                "duration" => 5,
                "prompt_extend" => true,
                "seed" => $seed
            ]
        );
        if ($resSdk['code'] == 200) {
            $taskId = $resSdk['data'];
            $queue = "poll-video-result";
            $data = [
                'id' => $this->contentId,
                'task_id' => $taskId,
            ];
            Redis::send($queue, $data);
        } else {
            $data = [
                'status' => AiImageVideoEnum::VIDEO_ERROR,
                'status_msg' => $resSdk['msg'],
            ];
            $dep->edit($this->contentId, $data);
            $this->log("视频生成失败，response: " . json_encode($resSdk));
        }
        return true;

    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log("视频生成失败，response: " . $e->getMessage());
        $dep = new AiImageVideoDep();
        $data = [
            'status' => AiImageVideoEnum::VIDEO_ERROR,
            'status_msg' => $e->getMessage(),
        ];
        $dep->edit($this->contentId, $data);

    }

    private function log($msg, $context = [])
    {
        $logger = log_daily("queue_" . $this->queue); // 获取 Logger 实例
        $logger->info($msg, $context);
    }
            
}
