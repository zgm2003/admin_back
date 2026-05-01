<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiAgentScenesModel;
use support\Model;

class AiAgentScenesDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAgentScenesModel();
    }

    public function syncScenes(int $agentId, array $sceneCodes, array $sceneConfigs = []): void
    {
        $sceneCodes = array_values(array_unique(array_filter(
            array_map('strval', $sceneCodes),
            static fn(string $sceneCode) => $sceneCode !== ''
        )));

        $current = $this->model
            ->where('agent_id', $agentId)
            ->where('is_del', CommonEnum::NO)
            ->pluck('scene_code')
            ->toArray();

        $toAdd = array_diff($sceneCodes, $current);
        $toRemove = array_diff($current, $sceneCodes);

        foreach ($toAdd as $sceneCode) {
            $this->bindOrRestore($agentId, $sceneCode, $sceneConfigs[$sceneCode] ?? []);
        }

        foreach (array_intersect($sceneCodes, $current) as $sceneCode) {
            if (array_key_exists($sceneCode, $sceneConfigs)) {
                $this->model
                    ->where('agent_id', $agentId)
                    ->where('scene_code', $sceneCode)
                    ->where('is_del', CommonEnum::NO)
                    ->update([
                        'prompt_overlay' => $sceneConfigs[$sceneCode]['prompt_overlay'] ?? null,
                        'config_json'    => $sceneConfigs[$sceneCode]['config_json'] ?? null,
                        'status'         => (int)($sceneConfigs[$sceneCode]['status'] ?? CommonEnum::YES),
                    ]);
            }
        }

        if (!empty($toRemove)) {
            $this->model
                ->where('agent_id', $agentId)
                ->whereIn('scene_code', $toRemove)
                ->where('is_del', CommonEnum::NO)
                ->update(['is_del' => CommonEnum::YES]);
        }
    }

    public function bindOrRestore(int $agentId, string $sceneCode, array $config = []): int
    {
        $deleted = $this->model
            ->where('agent_id', $agentId)
            ->where('scene_code', $sceneCode)
            ->where('is_del', CommonEnum::YES)
            ->first();

        $data = [
            'prompt_overlay' => $config['prompt_overlay'] ?? null,
            'config_json'    => $config['config_json'] ?? null,
            'status'         => (int)($config['status'] ?? CommonEnum::YES),
            'is_del'         => CommonEnum::NO,
        ];

        if ($deleted) {
            return $this->model->where('id', $deleted->id)->update($data);
        }

        $this->add($data + [
            'agent_id'   => $agentId,
            'scene_code' => $sceneCode,
        ]);

        return 1;
    }

    public function getSceneCodesByAgentIds(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if (empty($agentIds)) {
            return [];
        }

        $rows = $this->model
            ->whereIn('agent_id', $agentIds)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['agent_id', 'scene_code'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->agent_id][] = $row->scene_code;
        }

        return $map;
    }

    public function getAgentIdsBySceneCode(string $sceneCode): array
    {
        return $this->model
            ->where('scene_code', $sceneCode)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->pluck('agent_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }
}
