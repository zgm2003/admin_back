<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\WalletTransactionModel;
use app\enum\CommonEnum;
use support\Model;

class WalletTransactionDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new WalletTransactionModel();
    }

    public function existsByBizActionNo(string $bizActionNo): bool
    {
        return $this->query()
            ->where('biz_action_no', $bizActionNo)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'biz_action_no', 'type', 'available_delta', 'frozen_delta',
                'balance_before', 'balance_after', 'order_no', 'title', 'remark', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int) $param['user_id']))
            ->when(isset($param['type']) && $param['type'] !== '', fn($q) => $q->where('type', (int) $param['type']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    public function listByUserId(int $userId, int $page, int $pageSize)
    {
        return $this->model
            ->select([
                'id', 'biz_action_no', 'type', 'available_delta', 'frozen_delta',
                'balance_before', 'balance_after', 'order_no', 'title', 'remark', 'created_at',
            ])
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }
}
