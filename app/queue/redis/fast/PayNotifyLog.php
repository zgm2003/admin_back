<?php

namespace app\queue\redis\fast;

use app\dep\Pay\PayNotifyLogDep;
use Webman\RedisQueue\Consumer;

class PayNotifyLog implements Consumer
{
    public $queue = 'pay_notify_log';
    public $connection = 'default';

    public function consume($data): void
    {
        (new PayNotifyLogDep())->add([
            'channel'        => $data['channel'] ?? 0,
            'notify_type'    => $data['notify_type'] ?? 1,
            'transaction_no' => $data['transaction_no'] ?? '',
            'trade_no'       => $data['trade_no'] ?? '',
            'headers'        => json_encode($data['headers'] ?? [], JSON_UNESCAPED_UNICODE),
            'raw_data'       => json_encode($data['raw_data'] ?? [], JSON_UNESCAPED_UNICODE),
            'process_status' => $data['process_status'] ?? 1,
            'process_msg'    => $data['process_msg'] ?? '',
            'ip'             => $data['ip'] ?? '',
        ]);
    }

    public function onConsumeFailure(\Throwable $e, $package): void
    {
        $this->log('pay_notify_log queue consume failed', [
            'error'   => $e->getMessage(),
            'package' => $package,
        ]);
    }

    private function log($msg, $context = [])
    {
        log_daily('queue_pay_notify_log')->info($msg, $context);
    }
}
