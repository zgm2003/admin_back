<?php

namespace app\dep\DevTools;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\enum\ExportTaskEnum;
use app\model\DevTools\ExportTaskModel;
use support\Model;
use Webman\RedisQueue\Client as RedisQueue;

class ExportTaskDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new ExportTaskModel();
    }

    /**
     * 列表查询
     */
    public function list(array $param)
    {
        return $this->model
            ->where('user_id', $param['user_id'])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 提交导出任务（创建记录 + 入队）
     */
    public function submit(int $userId, string $title, array $headers, array $data, string $prefix = 'export'): int
    {
        $taskId = $this->add([
            'user_id' => $userId,
            'title' => $title,
            'status' => ExportTaskEnum::STATUS_PENDING,
            'expire_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        RedisQueue::send('export_task', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'headers' => $headers,
            'data' => $data,
            'title' => $title,
            'prefix' => $prefix,
        ]);

        return $taskId;
    }

    /**
     * 批量删除（指定用户）
     */
    public function batchDeleteByUser(array $ids, int $userId): int
    {
        return $this->query()
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 更新任务为成功
     */
    public function updateSuccess(int $id, array $data): int
    {
        return $this->update($id, [
            'status' => ExportTaskEnum::STATUS_SUCCESS,
            'file_name' => $data['file_name'],
            'file_url' => $data['url'],
            'file_size' => $data['file_size'],
            'row_count' => $data['row_count'],
        ]);
    }

    /**
     * 更新任务为失败
     */
    public function updateFailed(int $id, string $errorMsg): int
    {
        return $this->update($id, [
            'status' => ExportTaskEnum::STATUS_FAILED,
            'error_msg' => mb_substr($errorMsg, 0, 500),
        ]);
    }

    /**
     * 清理过期任务
     */
    public function cleanExpired(): int
    {
        return $this->query()
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }
}
