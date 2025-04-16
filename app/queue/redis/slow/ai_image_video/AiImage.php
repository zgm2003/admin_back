<?php

namespace app\queue\redis\slow\ai_image_video;

use app\dep\AiWorkLine\AiImageVideo\AiImageVideoDep;
use app\enum\AiImageVideoEnum;
use app\lib\SiliconCloud\TextToImage;
use app\service\CosUploadService;
use Webman\RedisQueue\Consumer;

class AiImage implements Consumer
{
    // 要消费的队列名
    public $queue = 'ai-image';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';
    public $contentId;

    // 消费
    public function consume($data)
    {
        $this->contentId = $data;
        $dep = new AiImageVideoDep();
        $sdk = new TextToImage();
        $item = $dep->first($this->contentId);
        if (!$item) {
            $this->log('No content found with ID: ' . $this->contentId);
            return false;
        }
        // 创建一个随机 seed
        $seed = rand(1, 100000);
        // 注意：这里 imageSize 要确保 item->imageSize 是枚举类型对应的整数
        $resSdk = $sdk->generateImage(
            'Kwai-Kolors/Kolors',
            $item->image_prompt,
            AiImageVideoEnum::$imageSizeArr[$item->imageSize],
            $item->batchSize,
            $seed,
            $item->numInferenceSteps,
            $item->guidanceScale,
            $item->referenceImage
        );
        $this->log("图片生成请求发送成功，response: ",$resSdk);
        if ($resSdk['code'] == 200) {
            // 从返回数据中提取图片 URL 列表
            $imageUrls = [];
            if (isset($resSdk['data']['images']) && is_array($resSdk['data']['images'])) {
                foreach ($resSdk['data']['images'] as $img) {
                    if (isset($img['url'])) {
                        $imageUrls[] = $img['url'];
                    }
                }
            }
            if (isset($resSdk['data']['data']) && is_array($resSdk['data']['data'])) {
                foreach ($resSdk['data']['data'] as $img) {
                    if (isset($img['url'])) {
                        $imageUrls[] = $img['url'];
                    }
                }
            }
            // 去重
            $imageUrls = array_values(array_unique($imageUrls));

            // 使用 COS 上传服务类将远程图片 URL 上传至 COS，
            // 注意：上传过程是在内存中获取内容，不会生成临时文件
            $cosUploader = new CosUploadService();
            $cosUrls = [];
            foreach ($imageUrls as $url) {
                $uploadedUrl = $cosUploader->uploadFromUrl($url, 'ai_image_video');
                if ($uploadedUrl) {
                    $cosUrls[] = $uploadedUrl;
                } else {
                    // 上传失败时保留原地址（或根据业务做其它处理）
                    $cosUrls[] = $url;
                }
            }

            // 存储到数据库的字段要求为 JSON 数组字符串
            $data = [
                'image_list'         => json_encode($cosUrls, JSON_UNESCAPED_SLASHES),
                'status'             => AiImageVideoEnum::IMAGE_SUCCESS,
            ];
            $dep->edit($this->contentId, $data);

            $this->log("图片上传并存储成功，contentId={$this->contentId}", $data);
        } else {
            $data = [
                'status'     => AiImageVideoEnum::IMAGE_ERROR,
                'status_msg' => $resSdk['message'],
            ];
            $dep->edit($this->contentId, $data);
            $this->log("图片生成失败，response: " . json_encode($resSdk));
        }
    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log("图片生成失败，异常信息: " . $e->getMessage(), $package);
        $dep = new AiImageVideoDep();
        $data = [
            'status'     => AiImageVideoEnum::IMAGE_ERROR,
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
