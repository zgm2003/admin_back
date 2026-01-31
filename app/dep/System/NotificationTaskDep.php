<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\NotificationEnum;
use app\model\System\NotificationTaskModel;
use support\Model;

class NotificationTaskDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new NotificationTaskModel();
    }

    /**
     * 列表查询
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', $param['title'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 统计各状态数量
     */
    public function countByStatus(array $param): array
    {
        return $this->query()
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', $param['title'] . '%'))
            ->selectRaw('status, COUNT(*) as num')
            ->groupBy('status')
            ->pluck('num', 'status')
            ->toArray();
    }

    /**
     * 创建任务记录（纯数据写入）
     */
    public function create(array $data): int
    {
        return $this->add($data);
    }

    /**
     * 获取待发送的定时任务
     */
    public function getPendingTasks(): array
    {
        return $this->query()
            ->where('status', NotificationEnum::STATUS_PENDING)
            ->where('is_del', CommonEnum::NO)
            ->whereNotNull('send_at')
            ->where('send_at', '<=', date('Y-m-d H:i:s'))
            ->get()
            ->toArray();
    }

    /**
     * 更新任务状态
     */
    public function updateStatus(int $id, int $status, ?int $totalCount = null, ?string $errorMsg = null): int
    {
        $data = ['status' => $status];
        if ($totalCount !== null) {
            $data['total_count'] = $totalCount;
        }
        if ($errorMsg !== null) {
            $data['error_msg'] = mb_substr($errorMsg, 0, 500);
        }
        return $this->update($id, $data);
    }

    /**
     * 增加已发送数
     */
    public function incrementSentCount(int $id, int $count): int
    {
        return $this->query()
            ->where('id', $id)
            ->increment('sent_count', $count);
    }

    /**
     * 取消任务（仅待发送状态可取消）
     */
    public function cancel(int $id): bool
    {
        $affected = $this->query()
            ->where('id', $id)
            ->where('status', NotificationEnum::STATUS_PENDING)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
        return $affected > 0;
    }
}
