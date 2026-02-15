<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\UploadRuleModel;
use app\enum\CommonEnum;
use support\Model;

class UploadRuleDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UploadRuleModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据标题查询
     */
    public function findByTitle(string $title)
    {
        return $this->model
            ->where('title', $title)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 检查标题是否已存在（排除指定 ID）
     */
    public function existsByTitle(string $title, ?int $excludeId = null): bool
    {
        return $this->model
            ->where('title', $title)
            ->where('is_del', CommonEnum::NO)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 获取字典列表
     */
    public function getDict()
    {
        return $this->model
            ->select(['id', 'title'])
            ->where('is_del', CommonEnum::NO)
            ->get();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', $param['title'] . '%'))
            ->where('is_del', CommonEnum::NO)
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }
}
