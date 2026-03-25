<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\model\Pay\OrderFulfillmentModel;
use support\Model;

class OrderFulfillmentDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new OrderFulfillmentModel();
    }

    public function findByFulfillNo(string $fulfillNo): ?Model
    {
        return $this->query()->where('fulfill_no', $fulfillNo)->first();
    }

    public function findByIdempotencyKey(string $key): ?Model
    {
        return $this->query()->where('idempotency_key', $key)->first();
    }

    /** 查找待重试的履约任务 */
    public function getRetryTasks(string $now, int $limit = 50): array
    {
        return $this->model
            ->whereIn('status', [PayEnum::FULFILL_PENDING, PayEnum::FULFILL_FAILED])
            ->where('next_retry_at', '<=', $now)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
