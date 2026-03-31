<?php

namespace app\process\Pay;

use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayReconcileTaskDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\process\BaseCronTask;
use support\Log;

/**
 * 日对账汇总定时任务
 * 每天凌晨1点汇总前一日交易数据，生成对账任务
 */
class PayReconcileDailyTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_reconcile_daily';
    }

    protected function handle(): ?string
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $startDate = $yesterday . ' 00:00:00';
        $endDate = $yesterday . ' 23:59:59';

        $created = 0;
        $channels = (new PayChannelDep())->getAllActive();
        foreach ($channels as $channel) {
            $created += $this->createReconcileTask(
                $yesterday,
                (int) $channel['channel'],
                (int) $channel['id'],
                1,
                $startDate,
                $endDate
            );
        }

        return $created > 0 ? "生成了 {$created} 条对账任务" : null;
    }

    private function createReconcileTask(string $date, int $channel, int $channelId, int $billType, string $startDate, string $endDate): int
    {
        $exist = (new PayReconcileTaskDep())->findByDateChannelBillType($date, $channel, $channelId, $billType);
        if ($exist) {
            return 0;
        }

        $stat = (new PayTransactionDep())->statSuccessfulByChannelId($channelId, $startDate, $endDate);

        (new PayReconcileTaskDep())->add([
            'reconcile_date' => $date,
            'channel' => $channel,
            'channel_id' => $channelId,
            'bill_type' => $billType,
            'status' => PayEnum::RECONCILE_PENDING,
            'local_count' => $stat['count'],
            'local_amount' => $stat['amount'],
            'platform_count' => 0,
            'platform_amount' => 0,
        ]);

        Log::info('[PayReconcileDaily] 生成对账任务', [
            'date' => $date,
            'channel' => $channel,
            'channel_id' => $channelId,
            'bill_type' => $billType,
            'local_count' => $stat['count'],
            'local_amount' => $stat['amount'],
        ]);

        return 1;
    }
}
