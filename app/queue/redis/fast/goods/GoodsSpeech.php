<?php

namespace app\queue\redis\fast\goods;

use app\dep\AiWorkLine\E_commerce\GoodsDep;
use app\dep\VoicesDep;
use app\enum\GoodsEnum;
use app\enum\VoicesEnum;
use app\lib\AliCloud\TTS;
use Webman\RedisQueue\Consumer;

class GoodsSpeech implements Consumer
{
    // 要消费的队列名
    public $queue = 'goods-speech';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';

    public $contentId;
    public $voice_id;
    public $volume;
    public $speech_rate;
    public $pitch;
    // 消费
    public function consume($data)
    {
        $this->contentId = $data['id'];
        $this->voice_id = $data['voices_id'];
        $this->volume = $data['volume'];
        $this->speech_rate = $data['speech_rate'];
        $this->pitch = $data['pitch'];

        $dep = new GoodsDep();
        $voicesDep = new VoicesDep();
        $item = $dep->first($this->contentId);
        $resVoices = $voicesDep->first($this->voice_id);
        if (!$item) {
            $this->log('No content found with ID: ' . $this->contentId);
            return false;
        }
        $sdk = new TTS();
        $this->log('Starting TTSAsync for content ID: ' . $this->contentId);
        $res = $sdk->TTSAsync(
            $item->point,
            VoicesEnum::$hzArr[$resVoices->sampling_rates],
            $resVoices->code,
            $this->volume,
            $this->speech_rate,
            $this->pitch
        );
        if ($res['code'] == 200){
            $data = [
                'music_url' => $res['data'],
                'status' => GoodsEnum::SPEECH_SUCCESS,
            ];
            $dep->edit($this->contentId, $data);
            $this->log('TTSAsync success: ' . $res['data']);
        }else{
            $this->log('TTSAsync error: ' . $res['msg']);
            $data = [
                'status' => GoodsEnum::SPEECH_ERROR,
                'status_msg' => $res['msg'],
            ];
            $dep->edit($this->contentId, $data);
        }
        return false;

    }
    public function onConsumeFailure(\Throwable $e, $package)
    {
        $this->log('TTSAsync error: ' . $e->getMessage());
        $dep = new GoodsDep();
        $data = [
            'status' => GoodsEnum::SPEECH_ERROR,
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
