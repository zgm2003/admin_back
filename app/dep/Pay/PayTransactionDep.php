<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\model\Pay\PayTransactionModel;
use support\Model;

class PayTransactionDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new PayTransactionModel();
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'transaction_no', 'order_id', 'order_no', 'attempt_no',
                'channel_id', 'channel', 'pay_method', 'amount',
                'trade_no', 'trade_status', 'status', 'paid_at', 'closed_at', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['order_no']), fn($q) => $q->where('order_no', $param['order_no']))
            ->when(!empty($param['transaction_no']), fn($q) => $q->where('transaction_no', $param['transaction_no']))
            ->when(isset($param['channel']) && $param['channel'] !== '', fn($q) => $q->where('channel', (int) $param['channel']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int) $param['status']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    public function findByTransactionNo(string $transactionNo): ?Model
    {
        return $this->query()->where('transaction_no', $transactionNo)->first();
    }

    public function findOrFailByTransactionNo(string $transactionNo): Model
    {
        $row = $this->findByTransactionNo($transactionNo);
        if (!$row) {
            throw new \RuntimeException('支付流水不存在: ' . $transactionNo);
        }
        return $row;
    }

    public function findByTradeNo(string $tradeNo): ?Model
    {
        return $this->query()->where('trade_no', $tradeNo)->first();
    }

    /** 查找某订单的最新一笔未关闭交易 */
    public function findLastActive(int $orderId): ?Model
    {
        return $this->model
            ->where('order_id', $orderId)
            ->whereIn('status', [1, 2])
            ->where('is_del', CommonEnum::NO)
            ->orderBy('attempt_no', 'desc')
            ->first();
    }

    /** 条件更新交易状态（乐观锁） */
    public function updateStatus(int $id, int $currentStatus, int $targetStatus, array $extra = []): int
    {
        $data = array_merge(['status' => $targetStatus], $extra);
        return $this->query()
            ->where('id', $id)
            ->where('status', $currentStatus)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }

    /** 查找待查单的交易（已创建/等待支付 且超过 N 分钟） */
    public function getPendingCheck(string $since, int $limit = 100): array
    {
        return $this->model
            ->leftJoin('orders', 'orders.id', '=', 'pay_transactions.order_id')
            ->whereIn('pay_transactions.status', [PayEnum::TXN_CREATED, PayEnum::TXN_WAITING])
            ->whereIn('orders.pay_status', [PayEnum::PAY_STATUS_PENDING, PayEnum::PAY_STATUS_PAYING])
            ->where('pay_transactions.created_at', '<=', $since)
            ->where('pay_transactions.is_del', CommonEnum::NO)
            ->where('orders.is_del', CommonEnum::NO)
            ->select('pay_transactions.*')
            ->orderBy('pay_transactions.id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /** 统计某状态的交易 */
    public function countByStatus(int $status, ?string $startDate = null, ?string $endDate = null): int
    {
        $query = $this->model
            ->where('status', $status)
            ->where('is_del', CommonEnum::NO);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        return $query->count();
    }
}
