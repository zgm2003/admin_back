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
 */
class NotificationTaskModule extends BaseModule
{
    private NotificationTaskDep $notificationTaskDep;
    private UsersDep $usersDep;
    private DictService $dictService;

    public function __construct()
    {
        $this->notificationTaskDep = $this->dep(NotificationTaskDep::class);
        $this->usersDep = $this->dep(UsersDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    /**
     * 初始化（返回字典）
     */
    public function init(): array
    {
        $data['dict'] = $this->dictService
            ->setNotificationTypeArr()
            ->setNotificationLevelArr()
            ->setNotificationTargetTypeArr()
            ->setPlatformArr()
            ->getDict();
        
        // 通知任务的平台选项需要在前面加一个“全平台”
        array_unshift($data['dict']['platformArr'], ['label' => '全平台', 'value' => 'all']);
        
        return self::success($data);
    }

    /**
     * 状态统计
     */
    public function statusCount($request): array
    {
        $param = $request->all();
        $counts = $this->notificationTaskDep->countByStatus($param);
        $list = [];
        foreach (NotificationEnum::$statusArr as $val => $label) {
            $list[] = ['label' => $label, 'value' => $val, 'num' => $counts[$val] ?? 0];
        }
        return self::success($list);
    }

    /**
     * 任务列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::list());

        $res = $this->notificationTaskDep->list($param);

        $data['list'] = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'content' => $item->content,
                'type' => $item->type,
                'type_text' => NotificationEnum::$typeArr[$item->type] ?? '未知',
                'level' => $item->level,
                'level_text' => NotificationEnum::$levelArr[$item->level] ?? '未知',
                'platform' => $item->platform ?? 'all',
                'platform_text' => ['all' => '全平台', 'admin' => 'PC后台', 'app' => 'H5/APP'][$item->platform] ?? '未知',
                'target_type' => $item->target_type,
                'target_type_text' => NotificationEnum::$targetTypeArr[$item->target_type] ?? '未知',
                'status' => $item->status,
                'status_text' => NotificationEnum::$statusArr[$item->status] ?? '未知',
                'total_count' => $item->total_count,
                'sent_count' => $item->sent_count,
                'send_at' => $item->send_at,
                'error_msg' => $item->error_msg,
                'created_at' => $item->created_at,
            ];
        });
        $data['page'] = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 创建通知任务
     */
    public function add($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::add());

        // 计算目标用户数
        $totalCount = $this->calculateTotalCount($param['target_type'], $param['target_ids'] ?? []);

        // Module 层编排：写表 + 判断是否入队
        $taskData = [
            'title' => $param['title'],
            'content' => $param['content'] ?? '',
            'type' => $param['type'] ?? NotificationEnum::TYPE_INFO,
            'level' => $param['level'] ?? NotificationEnum::LEVEL_NORMAL,
            'link' => $param['link'] ?? '',
            'platform' => $param['platform'] ?? 'all', // 推送平台
            'target_type' => $param['target_type'],
            'target_ids' => json_encode($param['target_ids'] ?? []),
            'status' => NotificationEnum::STATUS_PENDING,
            'total_count' => $totalCount,
            'send_at' => $param['send_at'] ?: null,
            'created_by' => $request->userId,
        ];
        $taskId = $this->notificationTaskDep->create($taskData);

        // 立即发送（无定时）则入队
        if (empty($param['send_at'])) {
            RedisQueue::send('notification_task', ['task_id' => $taskId]);
        }

        return self::success(['id' => $taskId]);
    }

    /**
     * 删除任务
     */
    public function del($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::del());
        $task = $this->notificationTaskDep->get($param['id']);
        self::throwIf(!$task, '任务不存在');
        $this->notificationTaskDep->delete($param['id']);
        return self::success();
    }

    /**
     * 取消任务（仅待发送状态可取消）
     */
    public function cancel($request): array
    {
        $param = $this->validate($request, NotificationTaskValidate::del());
        $task = $this->notificationTaskDep->get($param['id']);
        self::throwIf(!$task, '任务不存在');
        self::throwIf($task->status !== NotificationEnum::STATUS_PENDING, '只能取消待发送的任务');
        $this->notificationTaskDep->cancel($param['id']);
        return self::success();
    }

    /**
     * 计算目标用户数
     */
    private function calculateTotalCount(int $targetType, array $targetIds): int
    {
        return match ($targetType) {
            NotificationEnum::TARGET_ALL => $this->usersDep->countAll(),
            NotificationEnum::TARGET_USERS => count($targetIds),
            NotificationEnum::TARGET_ROLES => $this->usersDep->getIdsByRoleIds($targetIds)->count(),
            default => 0,
        };
    }
}
