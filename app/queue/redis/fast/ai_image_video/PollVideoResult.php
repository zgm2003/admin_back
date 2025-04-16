<?php

namespace app\queue\redis\fast\ai_image_video;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoDep;
use app\enum\AiImageVideoEnum;
use app\lib\AliCloud\ImageToVideo;
use Webman\RedisQueue\Consumer;
use Webman\RedisQueue\Redis;
class PollVideoResult implements Consumer
{
    // 要消费的队列名
    public $queue = 'poll-video-result';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;
    public $taskId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data['id'];
        $this->taskId = $data['task_id'];

        $sdk = new ImageToVideo();
        $dep = new AiImageVideoDep();
        $result = $sdk->getTaskStatus($this->taskId);

        if ($result['code'] == 200 && $result['data']['task_status'] == "SUCCEEDED") {
            $this->log("PollVideoResultJob 成功✅", $result);
            $data = [
                'video' => $result['data']['video_url'],
                'status' => AiImageVideoEnum::VIDEO_SUCCESS,
            ];
            $dep->edit($this->contentId, $data);
        } elseif ($result['code'] == 200 && $result['data']['task_status'] !== "SUCCEEDED") {
            $this->log("PollVideoResultJob 轮询中...", $result);
            $this->reschedule($this->contentId,$this->taskId);
        }else{
            $this->log("PollVideoResultJob 失败❌", $result);
            $data = [
                'status' => AiImageVideoEnum::VIDEO_ERROR,
                'status_msg' => $result['data']['message'],
            ];
            $dep->edit($this->contentId, $data);
        }
    }
    private static function reschedule($id,$taskId)
    {
        $queue = "poll-video-result";
        $data = [
            'id' => $id,
            'task_id' => $taskId,
        ];
        Redis::send($queue, $data,30);

    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log("PollVideoResultJob 失败❌". $e->getMessage());
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
