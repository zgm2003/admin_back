<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiAgentKnowledgeBasesModel;
use support\Db;
use support\Model;

class AiAgentKnowledgeBasesDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAgentKnowledgeBasesModel();
    }

    public function syncBindings(int $agentId, array $knowledgeBaseIds): void
    {
        $knowledgeBaseIds = array_values(array_unique(array_filter(array_map('intval', $knowledgeBaseIds))));

        $current = $this->model
            ->where('agent_id', $agentId)
            ->where('is_del', CommonEnum::NO)
            ->pluck('knowledge_base_id')
            ->toArray();

        $toAdd = array_diff($knowledgeBaseIds, $current);
        $toRemove = array_diff($current, $knowledgeBaseIds);

        foreach ($toAdd as $knowledgeBaseId) {
            $this->bindOrRestore($agentId, (int)$knowledgeBaseId);
        }

        if (!empty($toRemove)) {
            $this->model
                ->where('agent_id', $agentId)
                ->whereIn('knowledge_base_id', $toRemove)
                ->where('is_del', CommonEnum::NO)
                ->update(['is_del' => CommonEnum::YES]);
        }
    }

    public function bindOrRestore(int $agentId, int $knowledgeBaseId): int
    {
        $deleted = $this->model
            ->where('agent_id', $agentId)
            ->where('knowledge_base_id', $knowledgeBaseId)
            ->where('is_del', CommonEnum::YES)
            ->first();

        if ($deleted) {
            return $this->model->where('id', $deleted->id)->update([
                'status' => CommonEnum::YES,
                'is_del' => CommonEnum::NO,
            ]);
        }

        $this->add([
            'agent_id' => $agentId,
            'knowledge_base_id' => $knowledgeBaseId,
            'config_json' => null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ]);

        return 1;
    }

    public function getKnowledgeBaseIdsByAgentIds(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if (empty($agentIds)) {
            return [];
        }

        $rows = $this->model
            ->whereIn('agent_id', $agentIds)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['agent_id', 'knowledge_base_id'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->agent_id][] = (int)$row->knowledge_base_id;
        }

        return $map;
    }

    public function getActiveKnowledgeBasesByAgentId(int $agentId): \Illuminate\Support\Collection
    {
        return Db::table('ai_agent_knowledge_bases as akb')
            ->join('ai_knowledge_bases as kb', 'kb.id', '=', 'akb.knowledge_base_id')
            ->where('akb.agent_id', $agentId)
            ->where('akb.is_del', CommonEnum::NO)
            ->where('akb.status', CommonEnum::YES)
            ->where('kb.is_del', CommonEnum::NO)
            ->where('kb.status', CommonEnum::YES)
            ->select(['kb.id', 'kb.name', 'kb.top_k', 'kb.score_threshold'])
            ->orderBy('kb.id', 'desc')
            ->get();
    }

    public function deleteByKnowledgeBaseIds(array $knowledgeBaseIds): int
    {
        $knowledgeBaseIds = $this->normalizeIds($knowledgeBaseIds);
        if (empty($knowledgeBaseIds)) {
            return 0;
        }

        return $this->model
            ->whereIn('knowledge_base_id', $knowledgeBaseIds)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }
}
