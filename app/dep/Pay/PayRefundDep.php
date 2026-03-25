<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\model\Pay\PayRefundModel;
use support\Model;

class PayRefundDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new PayRefundModel();
    }

    public function findByRefundNo(string $refundNo): ?Model
    {
        return $this->query()->where('refund_no', $refundNo)->first();
    }

    public function findOrFailByRefundNo(string $refundNo): Model
    {
        $row = $this->findByRefundNo($refundNo);
        if (!$row) {
            throw new \RuntimeException('退款记录不存在: ' . $refundNo);
        }
        return $row;
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'refund_no', 'order_id', 'order_no', 'channel',
                'refund_amount', 'status', 'reason', 'operator_id',
                'refunded_at', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int) $param['status']))
            ->when(!empty($param['refund_no']), fn($q) => $q->where('refund_no', $param['refund_no']))
            ->when(!empty($param['order_no']), fn($q) => $q->where('order_no', $param['order_no']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    /** 查找退款中的记录（超时未完成） */
    public function getPendingRefund(string $since, int $limit = 100): array
    {
        return $this->model
            ->where('status', PayEnum::REFUND_ING)
            ->where('created_at', '<=', $since)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
