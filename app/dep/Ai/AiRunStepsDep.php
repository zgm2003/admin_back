<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiRunStepsModel;
use app\enum\CommonEnum;
use support\Model;

class AiRunStepsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiRunStepsModel();
    }

    /**
     * 获取某次 run 的所有步骤（按 step_no 排序）
     */
    public function getByRunId(int $runId): \Illuminate\Support\Collection
    {
        return $this->model
            ->select(['id', 'step_no', 'step_type', 'agent_id', 'model_snapshot', 'status', 'error_msg', 'latency_ms', 'payload_json', 'created_at'])
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
    public function updateStepStatus(int $id, int $status, ?string $errorMsg = null, ?int $latencyMs = null): int
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
