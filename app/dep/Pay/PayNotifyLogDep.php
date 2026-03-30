<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\PayNotifyLogModel;
use app\enum\CommonEnum;
use support\Model;

class PayNotifyLogDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new PayNotifyLogModel();
    }

    public function add(array $data): int
    {
        $data['is_del'] = CommonEnum::NO;
        return parent::add($data);
    }

    public function list(array $param)
    {
        return $this->model
            ->select(['id', 'channel', 'notify_type', 'transaction_no', 'trade_no', 'process_status', 'process_msg', 'ip', 'created_at'])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['channel']) && $param['channel'] !== '', fn($q) => $q->where('channel', (int) $param['channel']))
            ->when(isset($param['notify_type']) && $param['notify_type'] !== '', fn($q) => $q->where('notify_type', (int) $param['notify_type']))
            ->when(!empty($param['transaction_no']), fn($q) => $q->where('transaction_no', $param['transaction_no']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }
}
