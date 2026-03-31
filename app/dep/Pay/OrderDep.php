<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\OrderModel;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use support\Model;

class OrderDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new OrderModel();
    }

    public function list(array $param)
    {
        $query = $this->model
            ->select([
                'id', 'order_no', 'user_id', 'order_type', 'title',
                'total_amount', 'discount_amount', 'pay_amount',
                'pay_status', 'biz_status',
                'channel_id', 'pay_method', 'expire_time', 'admin_remark',
                'pay_time', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['order_type']) && $param['order_type'] !== '', fn($q) => $q->where('order_type', (int) $param['order_type']))
            ->when(isset($param['pay_status']) && $param['pay_status'] !== '', fn($q) => $q->where('pay_status', (int) $param['pay_status']))
            ->when(!empty($param['order_no']), fn($q) => $q->where('order_no', $param['order_no']))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int) $param['user_id']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'));

        if (!empty($param['order_by'])) {
            $direction = $param['direction'] ?? 'desc';
            $query->orderBy($param['order_by'], $direction);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    public function findByOrderNo(string $orderNo): ?Model
    {
        return $this->query()->where('order_no', $orderNo)->first();
    }

    public function findLatestOngoingRechargeByUser(int $userId): ?Model
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('order_type', PayEnum::TYPE_RECHARGE)
            ->whereIn('pay_status', [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING])
            ->orderBy('id', 'desc')
            ->first();
    }

    public function findOrFailByOrderNo(string $orderNo): Model
    {
        $row = $this->findByOrderNo($orderNo);
        if (!$row) {
            throw new \RuntimeException('订单不存在: ' . $orderNo);
        }
        return $row;
    }

    /** 条件更新支付状态（乐观锁） */
    public function updatePayStatus(int $id, int $currentStatus, int $targetStatus, array $extra = []): int
    {
        $data = array_merge(['pay_status' => $targetStatus], $extra);
        return $this->query()
            ->where('id', $id)
            ->where('pay_status', $currentStatus)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }

    /** 条件更新业务状态（乐观锁） */
    public function updateBizStatus(int $id, int $currentStatus, int $targetStatus, array $extra = []): int
    {
        $data = array_merge(['biz_status' => $targetStatus], $extra);
        return $this->query()
            ->where('id', $id)
            ->where('biz_status', $currentStatus)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }
    /** 关闭订单 */
    public function closeOrder(int $id, int $currentStatus, string $reason): int
    {
        return $this->query()
            ->where('id', $id)
            ->where('pay_status', $currentStatus)
            ->where('is_del', CommonEnum::NO)
            ->update([
                'pay_status'  => PayEnum::PAY_STATUS_CLOSED,
                'close_time'  => date('Y-m-d H:i:s'),
                'close_reason' => $reason,
            ]);
    }

    /** 统计订单状态数量 */
    public function countByStatus(): array
    {
        $rows = $this->query()
            ->select('pay_status')
            ->selectRaw('COUNT(*) as count')
            ->where('is_del', CommonEnum::NO)
            ->groupBy('pay_status')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['pay_status']] = $row['count'];
        }
        return $map;
    }

    /** 查找过期待支付订单 */
    public function getExpiredPending(string $expireTime, int $limit = 50): array
    {
        return $this->query()
            ->whereIn('pay_status', [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING])
            ->where('expire_time', '<=', $expireTime)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
