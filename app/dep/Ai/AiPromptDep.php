<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\model\Ai\AiPromptModel;
use app\enum\CommonEnum;
use support\Model;

class AiPromptDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiPromptModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        $columns = ['id', 'user_id', 'title', 'category', 'content', 'is_favorite', 'sort', 'use_count', 'created_at', 'updated_at'];

        return $this->model
            ->select($columns)
            ->where('user_id', $param['user_id'])
            ->when(!empty(trim($param['title'] ?? '')), fn($q) => $q->where('title', 'like', '%' . trim($param['title']) . '%'))
            ->when(!empty(trim($param['category'] ?? '')), fn($q) => $q->where('category', trim($param['category'])))
            ->when(isset($param['is_favorite']) && $param['is_favorite'], fn($q) => $q->where('is_favorite', CommonEnum::YES))
            ->where('is_del', CommonEnum::NO)
            ->orderByDesc('is_favorite')
            ->orderByDesc('sort')
            ->orderByDesc('use_count')
            ->orderByDesc('id')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 获取分类列表
     */
    public function getCategories(int $userId): array
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_del', CommonEnum::NO)
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->toArray();
    }

    /**
     * +1 use_count
     */
    public function incrementUseCount(int $id): void
    {
        $this->model->where('id', $id)->increment('use_count');
    }
}