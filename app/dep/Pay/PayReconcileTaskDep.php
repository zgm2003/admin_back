<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\PayReconcileTaskModel;
use app\enum\CommonEnum;
use support\Model;

class PayReconcileTaskDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new PayReconcileTaskModel();
    }

    public function findByDateChannelBillType(string $date, int $channel, int $channelId, int $billType): ?Model
    {
        return $this->query()
            ->where('reconcile_date', $date)
            ->where('channel', $channel)
            ->where('channel_id', $channelId)
            ->where('bill_type', $billType)
            ->first();
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'reconcile_date', 'channel', 'channel_id', 'bill_type',
                'status', 'platform_count', 'platform_amount',
                'local_count', 'local_amount', 'diff_count', 'diff_amount',
                'started_at', 'finished_at', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['channel']) && $param['channel'] !== '', fn($q) => $q->where('channel', (int) $param['channel']))
            ->when(isset($param['bill_type']) && $param['bill_type'] !== '', fn($q) => $q->where('bill_type', (int) $param['bill_type']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int) $param['status']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('reconcile_date', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('reconcile_date', '<=', $param['end_date']))
            ->orderBy('reconcile_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    public function getExecutableTasks(int $limit = 20): array
    {
        return $this->model
            ->where('status', \app\enum\PayEnum::RECONCILE_PENDING)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('reconcile_date', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
