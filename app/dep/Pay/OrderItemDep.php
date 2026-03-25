<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\OrderItemModel;
use app\enum\CommonEnum;
use support\Model;

class OrderItemDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new OrderItemModel();
    }

    public function getByOrderId(int $orderId)
    {
        return $this->model
            ->where('order_id', $orderId)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function addBatch(int $orderId, array $items): int
    {
        foreach ($items as &$item) {
            $item['order_id'] = $orderId;
            $item['is_del'] = CommonEnum::NO;
        }
        return $this->model->insert($items);
    }
}
