<?php

namespace app\module\Ai;

use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiConversationsDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\enum\AiEnum;
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
        $param = $this->validate($request, AiMessageValidate::list());

        // 校验会话存在且属于当前用户
        $conversation = $this->conversationsDep->getByUser((int)$param['conversation_id'], $request->userId);
        self::throwNotFound($conversation, '会话不存在');

        $res = $this->dep->list($param);

        $list = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'conversation_id' => $item->conversation_id,
                'role' => $item->role,
                'content' => $item->content,
                'meta_json' => $item->meta_json,
                'created_at' => $item->created_at,
            ];
        });

        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 删除消息
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiMessageValidate::del());

        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];

        // 校验消息属于当前用户的会话
        foreach ($ids as $id) {
            $message = $this->dep->get((int)$id);
            self::throwNotFound($message, '消息不存在');
            $conversation = $this->conversationsDep->getByUser($message->conversation_id, $request->userId);
            self::throwIf(!$conversation, '无权操作');
        }

        $this->dep->delete($ids);
        return self::success();
    }

    /**
     * 编辑消息内容并删除后续消息（用于编辑后重新生成）
     */
    public function editContent($request): array
    {
        $param = $this->validate($request, AiMessageValidate::editContent());

        $message = $this->dep->get((int)$param['id']);
        self::throwNotFound($message, '消息不存在');

        // 校验消息属于当前用户的会话
        $conversation = $this->conversationsDep->getByUser($message->conversation_id, $request->userId);
        self::throwIf(!$conversation, '无权操作');

        // 只允许编辑用户消息
        self::throwIf($message->role !== AiEnum::ROLE_USER, '只能编辑用户消息');

        // 更新消息内容
        $this->dep->updateContent((int)$param['id'], $param['content']);

        // 软删除该消息之后的所有消息
        $deletedCount = $this->dep->softDeleteAfter($message->conversation_id, (int)$param['id']);

        return self::success(['deleted_count' => $deletedCount]);
    }

    /**
     * 消息反馈（点赞/点踩）
     */
    public function feedback($request): array
    {
        $param = $this->validate($request, AiMessageValidate::feedback());

        // 校验消息存在
        $message = $this->dep->get((int)$param['id']);
        self::throwNotFound($message, '消息不存在');

        // 校验消息属于当前用户的会话
        $conversation = $this->conversationsDep->getByUser($message->conversation_id, $request->userId);
        self::throwIf(!$conversation, '无权操作');

        // feedback: 1=点赞 2=点踩 null=取消
        $feedback = isset($param['feedback']) ? (int)$param['feedback'] : null;
        $this->dep->updateFeedback((int)$param['id'], $feedback);

        return self::success();
    }
}
