<?php

namespace app\process\Pay;

use app\dep\Pay\PayTransactionDep;
use app\dep\Pay\PayReconcileTaskDep;
use app\enum\PayEnum;
use app\process\BaseCronTask;
use support\Db;
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

        $created += $this->createReconcileTask($yesterday, PayEnum::CHANNEL_WECHAT, 1, $startDate, $endDate);
        $created += $this->createReconcileTask($yesterday, PayEnum::CHANNEL_ALIPAY, 1, $startDate, $endDate);

        return $created > 0 ? "生成了 {$created} 条对账任务" : null;
    }

    private function createReconcileTask(string $date, int $channel, int $billType, string $startDate, string $endDate): int
    {
        // 检查是否已存在
        $exist = (new PayReconcileTaskDep())->findByDateChannelBillType($date, $channel, 0, $billType);
        if ($exist) {
            return 0;
        }

        $stat = Db::table('pay_transactions')
            ->where('status', PayEnum::TXN_SUCCESS)
            ->where('channel', $channel)
            ->where('is_del', 2)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->first();

        $localCount = (int) ($stat->cnt ?? 0);
        $localAmount = (int) ($stat->total ?? 0);

        (new PayReconcileTaskDep())->add([
            'reconcile_date' => $date,
            'channel'       => $channel,
            'channel_id'    => 0,
            'bill_type'    => $billType,
            'status'       => PayEnum::RECONCILE_PENDING,
            'local_count'   => $localCount,
            'local_amount'  => $localAmount,
            'platform_count' => 0,
            'platform_amount' => 0,
        ]);

        Log::info('[PayReconcileDaily] 生成对账任务', [
            'date'         => $date,
            'channel'     => $channel,
            'bill_type'   => $billType,
            'local_count'  => $localCount,
            'local_amount' => $localAmount,
        ]);

        return 1;
    }
}
