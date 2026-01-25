<?php

namespace app\module\Ai;

use app\dep\Ai\AiPromptDep;
use app\module\BaseModule;
use app\validate\Ai\AiPromptValidate;
use app\enum\CommonEnum;

class AiPromptModule extends BaseModule
{
    protected AiPromptDep $aiPromptDep;

    public function __construct()
    {
        $this->aiPromptDep = new AiPromptDep();
    }

    public function list($request): array
    {
        $param = $this->validate($request, AiPromptValidate::list());
        $param['user_id'] = $request->userId;
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $res = $this->aiPromptDep->list($param);

        $list = $res->map(fn($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'content' => $item->content,
            'category' => $item->category,
            'tags' => $item->tags ? json_decode($item->tags, true) : [],
            'variables' => $item->variables ? json_decode($item->variables, true) : [],
            'is_favorite' => $item->is_favorite,
            'use_count' => $item->use_count,
            'sort' => $item->sort,
            'created_at' => $item->created_at,
        ]);

        return self::paginate($list, [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ]);
    }

    public function detail($request): array
    {
        $param = $this->validate($request, AiPromptValidate::detail());

        $row = $this->aiPromptDep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权访问');

        return self::success([
            'id' => $row->id,
            'title' => $row->title,
            'content' => $row->content,
            'category' => $row->category,
            'tags' => $row->tags ? json_decode($row->tags, true) : [],
            'variables' => $row->variables ? json_decode($row->variables, true) : [],
            'is_favorite' => $row->is_favorite,
            'use_count' => $row->use_count,
            'sort' => $row->sort,
        ]);
    }

    public function add($request): array
    {
        $param = $this->validate($request, AiPromptValidate::add());
        $param['user_id'] = $request->userId;
        $param['is_favorite'] = CommonEnum::NO;
        $param['use_count'] = 0;
        $param['sort'] = 0;
        if (isset($param['tags']) && is_array($param['tags'])) {
            $param['tags'] = json_encode($param['tags']);
        }
        if (isset($param['variables']) && is_array($param['variables'])) {
            $param['variables'] = json_encode($param['variables']);
        }

        $this->aiPromptDep->add($param);

        return self::success();
    }

    public function edit($request): array
    {
        $param = $this->validate($request, AiPromptValidate::edit());

        $row = $this->aiPromptDep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        if (isset($param['tags']) && is_array($param['tags'])) {
            $param['tags'] = json_encode($param['tags']);
        }
        if (isset($param['variables']) && is_array($param['variables'])) {
            $param['variables'] = json_encode($param['variables']);
        }

        $this->aiPromptDep->update((int)$param['id'], $param);

        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, AiPromptValidate::del());
        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];

        foreach ($ids as $id) {
            $row = $this->aiPromptDep->get((int)$id);
            if ($row && (int)$row->user_id === $request->userId) {
                $this->aiPromptDep->delete((int)$id);
            }
        }

        return self::success();
    }

    public function toggleFavorite($request): array
    {
        $param = $this->validate($request, AiPromptValidate::detail());

        $row = $this->aiPromptDep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        $newVal = $row->is_favorite === CommonEnum::YES ? CommonEnum::NO : CommonEnum::YES;
        $this->aiPromptDep->update((int)$param['id'], ['is_favorite' => $newVal]);

        return self::success(['is_favorite' => $newVal]);
    }

    public function use($request): array
    {
        $param = $this->validate($request, AiPromptValidate::detail());

        $row = $this->aiPromptDep->get((int)$param['id']);
        self::throwNotFound($row);
        self::throwIf((int)$row->user_id !== $request->userId, '无权操作');

        $this->aiPromptDep->incrementUseCount((int)$param['id']);

        return self::success(['content' => $row->content]);
    }
}