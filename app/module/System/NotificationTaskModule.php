<?php

namespace app\module\System;

use app\dep\System\NotificationTaskDep;
use app\dep\User\UsersDep;
use app\enum\NotificationEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\NotificationTaskValidate;
use Webman\RedisQueue\Client as RedisQueue;

/**
 * 通知任务管理模块
 * 负责：通知任务的发布、列表、状态统计、取消、删除
 * 支持定时发送（send_at）和立即发送（入 Redis 队列异步处理）
 */
class NotificationTaskModule extends BaseModule
{
    /**
     * 初始化（返回通知类型、级别、目标类型、平台字典）
     * 平台选项额外插入"全平台"选项
     */
    public function init(): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setNotificationTypeArr()
            ->setNotificationLevelArr()
            ->setNotificationTargetTypeArr()
            ->setPlatformArr()
            ->getDict();

        // 通知任务的平台选项需要在前面加一个"全平台"
        array_unshift($data['dict']['platformArr'], ['label' => '全平台', 'value' => 'all']);

        return self::success($data);
    }

    /**
     * 各状态任务数量统计（用于前端 Tab 角标）
     */
    public function statusCount($request): array
    {
        $param = $request->all();
        $counts = $this->dep(NotificationTaskDep::class)->countByStatus($param);

        $list = [];
        foreach (NotificationEnum::$statusArr as $val => $label) {
            $list[] = ['label' => $label, 'value' => $val, 'num' => $counts[$val] ?? 0];
        }
        return self::success($list);
    }

    /**
     * 任务列表（分页，含类型/级别/平台/目标类型/状态的文本映射）
     */
    public function list($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::list());
        $res = $this->dep(NotificationTaskDep::class)->list($param);

        $data['list'] = $res->map(fn($item) => [
            'id'               => $item->id,
            'title'            => $item->title,
            'content'          => $item->content,
            'type'             => $item->type,
            'type_text'        => NotificationEnum::$typeArr[$item->type] ?? '未知',
            'level'            => $item->level,
            'level_text'       => NotificationEnum::$levelArr[$item->level] ?? '未知',
            'platform'         => $item->platform ?? 'all',
            'platform_text'    => ['all' => '全平台', 'admin' => 'PC后台', 'app' => 'H5/APP'][$item->platform] ?? '未知',
            'target_type'      => $item->target_type,
            'target_type_text' => NotificationEnum::$targetTypeArr[$item->target_type] ?? '未知',
            'status'           => $item->status,
            'status_text'      => NotificationEnum::$statusArr[$item->status] ?? '未知',
            'total_count'      => $item->total_count,
            'sent_count'       => $item->sent_count,
            'send_at'          => $item->send_at,
            'error_msg'        => $item->error_msg,
            'created_at'       => $item->created_at,
        ]);
        $data['page'] = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];
        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 创建通知任务
     * 流程：校验 → 计算目标用户数 → 写入任务表 → 无定时则立即入队
     */
    public function add($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::add());

        // 根据目标类型计算受众人数
        $totalCount = $this->calculateTotalCount($param['target_type'], $param['target_ids'] ?? []);

        $taskData = [
            'title'       => $param['title'],
            'content'     => $param['content'] ?? '',
            'type'        => $param['type'] ?? NotificationEnum::TYPE_INFO,
            'level'       => $param['level'] ?? NotificationEnum::LEVEL_NORMAL,
            'link'        => $param['link'] ?? '',
            'platform'    => $param['platform'] ?? 'all',
            'target_type' => $param['target_type'],
            'target_ids'  => json_encode($param['target_ids'] ?? []),
            'status'      => NotificationEnum::STATUS_PENDING,
            'total_count' => $totalCount,
            'send_at'     => $param['send_at'] ?: null,
            'created_by'  => $request->userId,
        ];
        $taskId = $this->dep(NotificationTaskDep::class)->create($taskData);

        // 未设定时发送时间 → 立即入 Redis 队列异步处理
        if (empty($param['send_at'])) {
            RedisQueue::send('notification_task', ['task_id' => $taskId]);
        }

        return self::success(['id' => $taskId]);
    }

    /**
     * 删除任务（先校验存在性）
     */
    public function del($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::del());
        $task = $this->dep(NotificationTaskDep::class)->get($param['id']);
        self::throwIf(!$task, '任务不存在');
        $this->dep(NotificationTaskDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * 取消任务（仅待发送状态可取消）
     */
    public function cancel($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::del());
        $task = $this->dep(NotificationTaskDep::class)->get($param['id']);
        self::throwIf(!$task, '任务不存在');
        self::throwIf($task->status !== NotificationEnum::STATUS_PENDING, '只能取消待发送的任务');
        $affected = $this->dep(NotificationTaskDep::class)->cancel($param['id']);
        self::throwIf($affected === 0, '任务状态已变更，请刷新后重试');
        return self::success();
    }

    // ==================== 私有方法 ====================

    /**
     * 根据目标类型计算受众用户数
     * TARGET_ALL=全部用户 / TARGET_USERS=指定用户 / TARGET_ROLES=指定角色下的用户
     */
    private function calculateTotalCount(int $targetType, array $targetIds): int
    {
        return match ($targetType) {
            NotificationEnum::TARGET_ALL   => $this->dep(UsersDep::class)->countAll(),
            NotificationEnum::TARGET_USERS => count($targetIds),
            NotificationEnum::TARGET_ROLES => $this->dep(UsersDep::class)->getIdsByRoleIds($targetIds)->count(),
            default => 0,
        };
    }
}
