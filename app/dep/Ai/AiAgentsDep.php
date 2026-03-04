<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiAgentsModel;
use app\enum\CommonEnum;
use support\Model;

class AiAgentsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAgentsModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $columns = [
            'id', 'name', 'model_id', 'avatar', 'system_prompt',
            'mode', 'scene', 'status', 'created_at', 'updated_at',
        ];

        return $this->model
            ->select($columns)
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['model_id']), fn($q) => $q->where('model_id', (int)$param['model_id']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['mode']), fn($q) => $q->where('mode', $param['mode']))
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', $param['name'] . '%'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取所有启用的智能体（字典用）
     */
    public function allActive()
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['id', 'name'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 根据场景获取启用的智能体
     */
    public function getByScene(string $scene)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->where('scene', $scene)
            ->first();
    }

    /**
     * 根据场景获取所有启用的智能体（字典用）
     */
    public function getActiveByScene(string $scene)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->where('scene', $scene)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 根据场景 + 模式精确获取启用的智能体
     */
    public function getBySceneAndMode(string $scene, string $mode)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->where('scene', $scene)
            ->where('mode', $mode)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * 根据多个场景获取所有启用的智能体
     */
    public function getActiveByScenes(array $scenes)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->whereIn('scene', $scenes)
            ->orderBy('id', 'desc')
            ->get();
    }

}
