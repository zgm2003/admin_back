<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiAssistantToolsModel;
use support\Db;
use support\Model;

/**
 * AI 智能体工具绑定数据访问层
 */
class AiAssistantToolsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAssistantToolsModel();
    }

    /**
     * 获取智能体已绑定的工具记录（未删除+启用）
     */
    public function getBindingsByAgentId(int $agentId): \Illuminate\Support\Collection
    {
        return $this->model
            ->where('assistant_id', $agentId)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->get();
    }

    /**
     * 获取智能体绑定的完整工具信息（JOIN ai_tools，用于 NeuronAgentFactory）
     * 只返回绑定启用 + 工具启用 + 均未删除的记录
     */
    public function getActiveToolsByAgentId(int $agentId): \Illuminate\Support\Collection
    {
        return Db::table('ai_assistant_tools as at')
            ->join('ai_tools as t', 't.id', '=', 'at.tool_id')
            ->where('at.assistant_id', $agentId)
            ->where('at.is_del', CommonEnum::NO)
            ->where('at.status', CommonEnum::YES)
            ->where('t.is_del', CommonEnum::NO)
            ->where('t.status', CommonEnum::YES)
            ->select(['t.*'])
            ->get();
    }

    /**
     * 绑定或恢复（先查已删记录恢复，否则新建）
     */
    public function bindOrRestore(int $agentId, int $toolId): int
    {
        $deleted = $this->model
            ->where('assistant_id', $agentId)
            ->where('tool_id', $toolId)
            ->where('is_del', CommonEnum::YES)
            ->first();

        if ($deleted) {
            return $this->model->where('id', $deleted->id)->update([
                'is_del' => CommonEnum::NO,
                'status' => CommonEnum::YES,
            ]);
        }

        $this->add([
            'assistant_id' => $agentId,
            'tool_id'      => $toolId,
            'status'       => CommonEnum::YES,
            'is_del'       => CommonEnum::NO,
        ]);
        return 1;
    }

    /**
     * 批量同步绑定（事务内 diff：新增缺少的，软删除多余的）
     */
    public function syncBindings(int $agentId, array $toolIds): void
    {
        $current = $this->model
            ->where('assistant_id', $agentId)
            ->where('is_del', CommonEnum::NO)
            ->pluck('tool_id')
            ->toArray();

        $toAdd    = array_diff($toolIds, $current);
        $toRemove = array_diff($current, $toolIds);

        foreach ($toAdd as $toolId) {
            $this->bindOrRestore($agentId, (int)$toolId);
        }

        if (!empty($toRemove)) {
            $this->model
                ->where('assistant_id', $agentId)
                ->whereIn('tool_id', $toRemove)
                ->where('is_del', CommonEnum::NO)
                ->update(['is_del' => CommonEnum::YES]);
        }
    }
}
