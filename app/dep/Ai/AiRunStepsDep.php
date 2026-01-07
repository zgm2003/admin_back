<?php

namespace app\dep\Ai;

use app\model\Ai\AiRunStepModel;
use app\enum\CommonEnum;

class AiRunStepsDep
{
    protected AiRunStepModel $model;

    public function __construct()
    {
        $this->model = new AiRunStepModel();
    }

    /**
     * 添加步骤记录
     */
    public function add(array $data): int
    {
        return $this->model->insertGetId($data);
    }

    /**
     * 获取某次 run 的所有步骤（按 step_no 排序）
     */
    public function getByRunId(int $runId): \Illuminate\Support\Collection
    {
        return $this->model
            ->where('run_id', $runId)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('step_no', 'asc')
            ->get();
    }

    /**
     * 获取当前 run 的最大步骤序号
     */
    public function getMaxStepNo(int $runId): int
    {
        return (int)$this->model
            ->where('run_id', $runId)
            ->max('step_no');
    }

    /**
     * 更新步骤状态
     */
    public function updateStatus(int $id, int $status, ?string $errorMsg = null, ?int $latencyMs = null): int
    {
        $data = ['status' => $status];
        if ($errorMsg !== null) {
            $data['error_msg'] = $errorMsg;
        }
        if ($latencyMs !== null) {
            $data['latency_ms'] = $latencyMs;
        }
        return $this->model->where('id', $id)->update($data);
    }
}
