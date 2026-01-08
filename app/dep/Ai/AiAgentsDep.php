<?php

namespace app\dep\Ai;

use app\model\Ai\AiAgentModel;
use app\enum\CommonEnum;

class AiAgentsDep
{
    protected AiAgentModel $model;

    public function __construct()
    {
        $this->model = new AiAgentModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $pageSize = $param['page_size'] ?? 20;
        $currentPage = $param['current_page'] ?? 1;

        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['model_id']), function ($q) use ($param) {
                $q->where('model_id', (int)$param['model_id']);
            })
            ->when(isset($param['status']) && $param['status'] !== '', function ($q) use ($param) {
                $q->where('status', (int)$param['status']);
            })
            ->when(!empty($param['mode']), function ($q) use ($param) {
                $q->where('mode', $param['mode']);
            })
            ->when(!empty($param['name']), function ($q) use ($param) {
                $q->where('name', 'like', '%' . $param['name'] . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    /**
     * 根据 ID 获取单条记录
     */
    public function getById(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 根据 ID 获取单条记录（不检查 is_del）
     */
    public function first(int $id)
    {
        return $this->model->where('id', $id)->first();
    }

    public function add($data)
    {
        return $this->model->insertGetId($data);
    }

    public function edit($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    public function del($id, $data)
    {
        return $this->edit($id, $data);
    }

    /**
     * 批量设置状态
     */
    public function setStatus($ids, int $status): int
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => $status]);
    }

    /**
     * 批量查询，返回 id => model 的 Collection（不检查 is_del）
     */
    public function getMapByIds(array $ids)
    {
        if (empty($ids)) return collect();
        return $this->model
            ->whereIn('id', array_unique($ids))
            ->get()
            ->keyBy('id');
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
}
