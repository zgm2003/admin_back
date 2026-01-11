<?php

namespace app\module\Ai;

use app\dep\Ai\AiConversationsDep;
use app\dep\Ai\AiAgentsDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\validate\Ai\AiConversationValidate;
use RuntimeException;

class AiConversationModule extends BaseModule
{
    protected AiConversationsDep $dep;
    protected AiAgentsDep $agentsDep;

    public function __construct()
    {
        $this->dep = new AiConversationsDep();
        $this->agentsDep = new AiAgentsDep();
    }

    public function list($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::list());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $param['user_id'] = $request->userId;
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        // 默认查询正常状态（status=1），前端可传 status=2 查归档
        if (!isset($param['status'])) {
            $param['status'] = CommonEnum::YES;
        }

        $res = $this->dep->list($param);

        // 批量获取关联的智能体信息（避免N+1）
        $agentIds = $res->pluck('agent_id')->unique()->toArray();
        $agentMap = $this->agentsDep->getMap($agentIds);

        $list = $res->map(function ($item) use ($agentMap) {
            $agent = $agentMap->get($item->agent_id);
            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'agent_id' => $item->agent_id,
                'agent_name' => $agent?->name ?? '',
                'title' => $item->title,
                'last_message_at' => $item->last_message_at?->toDateTimeString(),
                'status' => $item->status,
                'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
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

    public function add($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::add());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        // 校验 agent_id 存在且 is_del=2
        $agent = $this->agentsDep->get((int)$param['agent_id']);
        if (!$agent) {
            return self::error('智能体不存在');
        }
        if ($agent->status !== CommonEnum::YES) {
            return self::error('智能体已禁用');
        }

        $data = [
            'user_id' => $request->userId,
            'agent_id' => (int)$param['agent_id'],
            'title' => $param['title'] ?? '新会话',
            'status' => CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];

        $id = $this->dep->add($data);
        return self::success(['id' => $id]);
    }

    public function edit($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::edit());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $userId = $request->userId;

        if (!isset($param['title'])) {
            return self::success(['affected' => 0]);
        }

        $this->dep->updateTitle($ids, $param['title'], $userId);
        return self::success();
    }

    public function del($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::del());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $ids = is_array($param['id']) ? $param['id'] : [$param['id']];
        $userId = $request->userId;

        $this->dep->deleteByUser($ids, $userId);
        return self::success();
    }

    /**
     * 更新会话状态（归档/取消归档）
     * status=1 正常，status=2 归档
     */
    public function status($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::status());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $this->dep->updateStatus($param['id'], (int)$param['status'], $request->userId);
        return self::success();
    }

    /**
     * 获取单个会话详情
     */
    public function detail($request): array
    {
        try {
            $param = $this->validate($request, AiConversationValidate::detail());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $item = $this->dep->getByUser((int)$param['id'], $request->userId);
        if (!$item) {
            return self::error('会话不存在');
        }

        $agent = $this->agentsDep->get((int)$item->agent_id);

        return self::success([
            'id' => $item->id,
            'user_id' => $item->user_id,
            'agent_id' => $item->agent_id,
            'agent_name' => $agent?->name ?? '',
            'title' => $item->title,
            'last_message_at' => $item->last_message_at?->toDateTimeString(),
            'status' => $item->status,
            'created_at' => $item->created_at?->toDateTimeString(),
        ]);
    }
}
