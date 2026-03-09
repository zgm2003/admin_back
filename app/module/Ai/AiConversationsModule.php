<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiModelsDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\validate\Ai\AiConversationsValidate;

/**
 * AI 会话管理模块
 * 负责：会话 CRUD、归档/取消归档、会话详情（含智能体+模型信息）
 * 会话归属用户，所有操作均校验 user_id 权限
 */
class AiConversationsModule extends BaseModule
{
    /**
     * 会话列表（分页，默认查正常状态，可传 status=2 查归档）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::list());
        $param['user_id'] = $request->userId;
        // 默认查询正常状态（status=1），前端可传 status=2 查归档
        if (!isset($param['status'])) {
            $param['status'] = CommonEnum::YES;
        }

        $res = $this->dep(AiConversationsDep::class)->list($param);

        // 批量获取关联智能体信息（避免 N+1，只取需要的字段）
        $agentIds = $res->pluck('agent_id')->unique()->toArray();
        $agentMap = $this->dep(AiAgentsDep::class)->getMap($agentIds, ['id', 'name']);

        $list = $res->map(fn($item) => [
            'id'              => $item->id,
            'user_id'         => $item->user_id,
            'agent_id'        => $item->agent_id,
            'agent_name'      => $agentMap->get($item->agent_id)?->name ?? '',
            'title'           => $item->title,
            'last_message_at' => $item->last_message_at?->toDateTimeString(),
            'status'          => $item->status,
            'status_name'     => CommonEnum::$statusArr[$item->status] ?? '',
            'created_at'      => $item->created_at,
            'updated_at'      => $item->updated_at,
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
     * 新增会话（校验智能体存在且启用）
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::add());

        // 校验智能体存在且未禁用
        $agent = $this->dep(AiAgentsDep::class)->get((int)$param['agent_id']);
        self::throwNotFound($agent, '智能体不存在');
        self::throwIf($agent->status !== CommonEnum::YES, '智能体已禁用');

        $id = $this->dep(AiConversationsDep::class)->add([
            'user_id'  => $request->userId,
            'agent_id' => (int)$param['agent_id'],
            'title'    => $param['title'] ?? '新会话',
            'status'   => CommonEnum::YES,
            'is_del'   => CommonEnum::NO,
        ]);

        return self::success(['id' => $id]);
    }

    /**
     * 编辑会话标题（支持批量，仅限当前用户的会话）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::edit());

        if (!isset($param['title'])) {
            return self::success(['affected' => 0]);
        }

        $this->dep(AiConversationsDep::class)->updateTitle($param['id'], $param['title'], $request->userId);

        return self::success();
    }

    /**
     * 删除会话（支持批量，仅限当前用户的会话，软删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::del());

        $this->dep(AiConversationsDep::class)->deleteByUser($param['id'], $request->userId);

        return self::success();
    }

    /**
     * 更新会话状态（归档/取消归档：status=1 正常，status=2 归档）
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::status());
        $this->dep(AiConversationsDep::class)->updateStatus($param['id'], (int)$param['status'], $request->userId);

        return self::success();
    }

    /**
     * 获取单个会话详情（含智能体名称、模型能力信息）
     */
    public function detail($request): array
    {
        $param = $this->validate($request, AiConversationsValidate::detail());

        $item = $this->dep(AiConversationsDep::class)->getByUser((int)$param['id'], $request->userId);
        self::throwNotFound($item, '会话不存在');

        // 关联智能体 → 关联模型（获取 modalities 等能力信息）
        $agent = $this->dep(AiAgentsDep::class)->get((int)$item->agent_id);
        $model = $agent ? $this->dep(AiModelsDep::class)->get((int)$agent->model_id) : null;

        return self::success([
            'id'              => $item->id,
            'user_id'         => $item->user_id,
            'agent_id'        => $item->agent_id,
            'agent_name'      => $agent?->name ?? '',
            'modalities'      => $model?->modalities ?? null,
            'title'           => $item->title,
            'last_message_at' => $item->last_message_at?->toDateTimeString(),
            'status'          => $item->status,
            'created_at'      => $item->created_at,
        ]);
    }
}