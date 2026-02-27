<?php

namespace app\module\Ai;

use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiMessagesDep;
use app\enum\AiEnum;
use app\module\BaseModule;
use app\validate\Ai\AiMessagesValidate;

/**
 * AI 消息管理模块
 * 负责：消息列表、删除、编辑内容（重新生成）、反馈（点赞/点踩）
 * 所有操作均校验消息所属会话归属当前用户
 */
class AiMessagesModule extends BaseModule
{
    /**
     * 消息列表（分页，校验会话归属当前用户）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiMessagesValidate::list());

        // 校验会话存在且属于当前用户
        $conversation = $this->dep(AiConversationsDep::class)->getByUser((int)$param['conversation_id'], $request->userId);
        self::throwNotFound($conversation, '会话不存在');

        $res = $this->dep(AiMessagesDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'              => $item->id,
            'conversation_id' => $item->conversation_id,
            'role'            => $item->role,
            'content'         => $item->content,
            'meta_json'       => $item->meta_json,
            'created_at'      => $item->created_at,
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 删除消息（支持批量，批量校验消息归属当前用户的会话，软删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiMessagesValidate::del());
        $ids = \is_array($param['id']) ? $param['id'] : [$param['id']];
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $msgDep = $this->dep(AiMessagesDep::class);
        $convDep = $this->dep(AiConversationsDep::class);

        // 批量查询消息，避免 N+1
        $messages = $msgDep->getMapActive($ids, ['id', 'conversation_id']);
        self::throwIf($messages->count() !== \count($ids), '部分消息不存在');

        // 批量校验会话归属
        $convIds = $messages->pluck('conversation_id')->unique()->toArray();
        $conversations = $convDep->getMapActive($convIds, ['id', 'user_id']);
        foreach ($conversations as $conv) {
            self::throwIf($conv->user_id !== $request->userId, '无权操作');
        }

        $msgDep->delete($ids);

        return self::success();
    }

    /**
     * 编辑消息内容并删除后续消息（用于编辑后重新生成）
     * 仅允许编辑用户角色的消息，编辑后软删除该消息之后的所有消息
     */
    public function editContent($request): array
    {
        $param = $this->validate($request, AiMessagesValidate::editContent());
        $msgDep = $this->dep(AiMessagesDep::class);

        $message = $msgDep->get((int)$param['id']);
        self::throwNotFound($message, '消息不存在');

        // 校验消息属于当前用户的会话
        $conversation = $this->dep(AiConversationsDep::class)->getByUser($message->conversation_id, $request->userId);
        self::throwIf(!$conversation, '无权操作');

        // 只允许编辑用户消息
        self::throwIf($message->role !== AiEnum::ROLE_USER, '只能编辑用户消息');

        // 更新消息内容 + 软删除后续消息
        $msgDep->updateContent((int)$param['id'], $param['content']);
        $deletedCount = $msgDep->softDeleteAfter($message->conversation_id, (int)$param['id']);

        return self::success(['deleted_count' => $deletedCount]);
    }

    /**
     * 消息反馈（1=点赞 2=点踩 null=取消反馈）
     */
    public function feedback($request): array
    {
        $param = $this->validate($request, AiMessagesValidate::feedback());

        $message = $this->dep(AiMessagesDep::class)->get((int)$param['id']);
        self::throwNotFound($message, '消息不存在');

        // 校验消息属于当前用户的会话
        $conversation = $this->dep(AiConversationsDep::class)->getByUser($message->conversation_id, $request->userId);
        self::throwIf(!$conversation, '无权操作');

        $feedback = isset($param['feedback']) ? (int)$param['feedback'] : null;
        $this->dep(AiMessagesDep::class)->updateFeedback((int)$param['id'], $feedback);

        return self::success();
    }
}
