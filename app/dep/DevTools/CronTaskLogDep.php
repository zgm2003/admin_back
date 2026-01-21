<?php

namespace app\dep\DevTools;

use app\dep\BaseDep;
use app\model\DevTools\CronTaskLogModel;
use support\Model;

class CronTaskLogDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new CronTaskLogModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据任务ID获取最近执行日志
     */
    public function getRecentByTaskId(int $taskId, int $limit = 10)
    {
        return $this->model
            ->select(['id', 'task_id', 'task_name', 'start_time', 'end_time', 'duration_ms', 'status', 'error_msg', 'created_at'])
            ->where('task_id', $taskId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 根据任务名称获取最近执行日志
     */
    public function getRecentByTaskName(string $taskName, int $limit = 10)
    {
        return $this->model
            ->select(['id', 'task_id', 'task_name', 'start_time', 'end_time', 'duration_ms', 'status', 'error_msg', 'created_at'])
            ->where('task_name', $taskName)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页）
     */
    public function list(array $param)
    {
        $columns = ['id', 'task_id', 'task_name', 'start_time', 'end_time', 'duration_ms', 'status', 'result', 'error_msg', 'created_at'];
        return $this->model
            ->select($columns)
            ->when(!empty($param['task_id']), fn($q) => $q->where('task_id', $param['task_id']))
            ->when(!empty($param['task_name']), fn($q) => $q->where('task_name', $param['task_name']))
            ->when(!empty($param['status']), fn($q) => $q->where('status', $param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, $columns, 'page', $param['current_page'] ?? 1);
    }

    // ==================== 写入方法 ====================

    /**
     * 记录任务开始
     */
    public function logStart(int $taskId, string $taskName): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->model->insertGetId([
            'task_id' => $taskId,
            'task_name' => $taskName,
            'start_time' => $now,
            'status' => 3, // 运行中
            'created_at' => $now
        ]);
    }

    /**
     * 记录任务完成
     */
    public function logEnd(int $logId, bool $success, ?string $result = null, ?string $errorMsg = null): int
    {
        $endTime = date('Y-m-d H:i:s');
        $log = $this->model->select(['id', 'start_time'])->find($logId);
        $startTime = strtotime($log->start_time);
        $duration = (int)((time() - $startTime) * 1000);

        return $this->model
            ->where('id', $logId)
            ->update([
                'end_time' => $endTime,
                'duration_ms' => $duration,
                'status' => $success ? 1 : 2,
                'result' => $result,
                'error_msg' => $errorMsg
            ]);
    }

    /**
     * 清理过期日志（保留最近N天）
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->model
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
