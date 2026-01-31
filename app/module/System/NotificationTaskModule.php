<?php

namespace app\module\System;

use app\dep\System\NotificationTaskDep;
use app\dep\User\UsersDep;
use app\enum\NotificationEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\NotificationTaskValidate;

/**
 * 通知任务管理模块
 */
class NotificationTaskModule extends BaseModule
{
    private NotificationTaskDep $notificationTaskDep;
    private UsersDep $usersDep;

    public function __construct()
    {
        $this->notificationTaskDep = $this->dep(NotificationTaskDep::class);
        $this->usersDep = $this->dep(UsersDep::class);
    }

    /**
     * 初始化（返回字典）
     */
    public function init(): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setNotificationTypeArr()
            ->setNotificationLevelArr()
            ->setNotificationTargetTypeArr()
            ->getDict();
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
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

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
                'target_type' => $item->target_type,
                'target_type_text' => NotificationEnum::$targetTypeArr[$item->target_type] ?? '未知',
                'status' => $item->status,
                'status_text' => NotificationEnum::$statusArr[$item->status] ?? '未知',
                'total_count' => $item->total_count,
                'sent_count' => $item->sent_count,
                'send_at' => $item->send_at,
                'error_msg' => $item->error_msg,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
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

        $taskId = $this->notificationTaskDep->submit([
            'title' => $param['title'],
            'content' => $param['content'] ?? '',
            'type' => $param['type'] ?? NotificationEnum::TYPE_INFO,
            'level' => $param['level'] ?? NotificationEnum::LEVEL_NORMAL,
            'link' => $param['link'] ?? '',
            'target_type' => $param['target_type'],
            'target_ids' => json_encode($param['target_ids'] ?? []),
            'status' => NotificationEnum::STATUS_PENDING,
            'total_count' => $totalCount,
            'send_at' => $param['send_at'] ?: null,
            'created_by' => $request->userId,
        ]);

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
