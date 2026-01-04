<?php

namespace app\module\Ai;

use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiConversationsDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\validate\Ai\AiMessageValidate;
use RuntimeException;

class AiMessageModule extends BaseModule
{
    protected AiMessagesDep $dep;
    protected AiConversationsDep $conversationsDep;

    public function __construct()
    {
        $this->dep = new AiMessagesDep();
        $this->conversationsDep = new AiConversationsDep();
    }

    /**
     * 消息列表
     */
    public function list($request): array
    {
        try {
            $param = $this->validate($request, AiMessageValidate::list());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        // 校验会话存在且属于当前用户
        $conversation = $this->conversationsDep->getById((int)$param['conversation_id'], $request->userId);
        if (!$conversation) {
            return self::error('会话不存在');
        }

        $param['page_size'] = $param['page_size'] ?? 100;
        $param['current_page'] = $param['current_page'] ?? 1;

        $res = $this->dep->list($param);

        $list = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'conversation_id' => $item->conversation_id,
                'role' => $item->role,
                'content' => $item->content,
                'prompt_tokens' => $item->prompt_tokens,
                'completion_tokens' => $item->completion_tokens,
                'total_tokens' => $item->total_tokens,
                'cost' => $item->cost,
                'model_snapshot' => $item->model_snapshot,
                'meta_json' => $item->meta_json,
                'status' => $item->status,
                'created_at' => $item->created_at?->toDateTimeString(),
            ];
        });

        $page = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 新增消息
     */
    public function add($request): array
    {
        try {
            $param = $this->validate($request, AiMessageValidate::add());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        // 校验会话存在且属于当前用户
        $conversation = $this->conversationsDep->getById((int)$param['conversation_id'], $request->userId);
        if (!$conversation) {
            return self::error('会话不存在');
        }
        if ($conversation->status !== CommonEnum::YES) {
            return self::error('会话已禁用');
        }

        // 处理 meta_json
        $metaJson = null;
        if (isset($param['meta_json'])) {
            $metaJson = is_string($param['meta_json'])
                ? json_decode($param['meta_json'], true)
                : $param['meta_json'];
        }

        $data = [
            'conversation_id' => (int)$param['conversation_id'],
            'role' => (int)$param['role'],
            'content' => $param['content'],
            'meta_json' => $metaJson ? json_encode($metaJson) : null,
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];

        $this->dep->add($data);
        return self::success();
    }

    /**
     * 删除消息
     */
    public function del($request): array
    {
        try {
            $param = $this->validate($request, AiMessageValidate::del());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];

        // 这里简化处理，直接软删（实际可校验消息是否属于用户的会话）
        $this->dep->del($ids, ['is_del' => CommonEnum::YES]);
        return self::success();
    }
}
