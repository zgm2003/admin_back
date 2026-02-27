<?php

namespace app\module\Ai;

use app\dep\Ai\AiPromptsDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\validate\Ai\AiPromptsValidate;

/**
 * AI 提示词管理模块
 * 负责：用户个人提示词 CRUD、收藏切换、使用计数
 * 所有操作均校验 user_id 归属权限
 */
class AiPromptsModule extends BaseModule
{
    /**
     * 提示词列表（分页，仅当前用户）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::list());
        $param['user_id'] = $request->userId;

        $res = $this->dep(AiPromptsDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'          => $item->id,
            'title'       => $item->title,
            'content'     => $item->content,
            'category'    => $item->category,
            'tags'        => $item->tags ? \json_decode($item->tags, true) : [],
            'variables'   => $item->variables ? \json_decode($item->variables, true) : [],
            'is_favorite' => $item->is_favorite,
            'use_count'   => $item->use_count,
            'sort'        => $item->sort,
            'created_at'  => $item->created_at,
        ]);

        return self::paginate($list, [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ]);
    }

    /**
     * 提示词详情（校验归属权限）
     */
    public function detail($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::detail());

        $row = $this->dep(AiPromptsDep::class)->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权访问');

        return self::success([
            'id'          => $row->id,
            'title'       => $row->title,
            'content'     => $row->content,
            'category'    => $row->category,
            'tags'        => $row->tags ? \json_decode($row->tags, true) : [],
            'variables'   => $row->variables ? \json_decode($row->variables, true) : [],
            'is_favorite' => $row->is_favorite,
            'use_count'   => $row->use_count,
            'sort'        => $row->sort,
        ]);
    }

    /**
     * 新增提示词（自动绑定当前用户，JSON 字段序列化）
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::add());
        $param['user_id']     = $request->userId;
        $param['is_favorite'] = CommonEnum::NO;
        $param['use_count']   = 0;
        $param['sort']        = 0;

        if (isset($param['tags']) && \is_array($param['tags'])) {
            $param['tags'] = \json_encode($param['tags']);
        }
        if (isset($param['variables']) && \is_array($param['variables'])) {
            $param['variables'] = \json_encode($param['variables']);
        }

        $this->dep(AiPromptsDep::class)->add($param);

        return self::success();
    }

    /**
     * 编辑提示词（校验归属权限，JSON 字段序列化）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::edit());
        $dep = $this->dep(AiPromptsDep::class);

        $row = $dep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        if (isset($param['tags']) && \is_array($param['tags'])) {
            $param['tags'] = \json_encode($param['tags']);
        }
        if (isset($param['variables']) && \is_array($param['variables'])) {
            $param['variables'] = \json_encode($param['variables']);
        }

        $dep->update((int)$param['id'], $param);

        return self::success();
    }

    /**
     * 删除提示词（支持批量，仅删除当前用户拥有的记录）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::del());
        $ids = \is_array($param['id']) ? $param['id'] : [$param['id']];
        $dep = $this->dep(AiPromptsDep::class);

        foreach ($ids as $id) {
            $row = $dep->get((int)$id);
            if ($row && (int)$row->user_id === $request->userId) {
                $dep->delete((int)$id);
            }
        }

        return self::success();
    }

    /**
     * 切换收藏状态（收藏 ↔ 取消收藏）
     */
    public function toggleFavorite($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::detail());
        $dep = $this->dep(AiPromptsDep::class);

        $row = $dep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        $newVal = $row->is_favorite === CommonEnum::YES ? CommonEnum::NO : CommonEnum::YES;
        $dep->update((int)$param['id'], ['is_favorite' => $newVal]);

        return self::success(['is_favorite' => $newVal]);
    }

    /**
     * 使用提示词（递增使用计数，返回内容）
     */
    public function use($request): array
    {
        $param = $this->validate($request, AiPromptsValidate::detail());
        $dep = $this->dep(AiPromptsDep::class);

        $row = $dep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        $dep->incrementUseCount((int)$param['id']);

        return self::success(['content' => $row->content]);
    }
}