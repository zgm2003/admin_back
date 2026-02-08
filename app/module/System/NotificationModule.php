<?php

namespace app\module\System;

use app\dep\System\NotificationDep;
use app\enum\NotificationEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\NotificationValidate;

class NotificationModule extends BaseModule
{
    protected NotificationDep $notificationDep;
    private DictService $dictService;

    public function __construct()
    {
        $this->notificationDep = $this->dep(NotificationDep::class);
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
            ->setNotificationReadStatusArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 通知列表（普通分页，独立页面用）
     */
    public function list($request): array
    {
        $param = $this->validate($request, NotificationValidate::pageList());
        $res = $this->notificationDep->pageListByUser($request->userId, $request->platform, $param);

        $data['list'] = $res->map(fn($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'content' => $item->content,
            'type' => $item->type,
            'type_text' => NotificationEnum::$typeArr[$item->type] ?? '未知',
            'level' => $item->level,
            'level_text' => NotificationEnum::$levelArr[$item->level] ?? '未知',
            'link' => $item->link,
            'is_read' => $item->is_read,
            'created_at' => $item->created_at,
        ]);
        $data['page'] = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 标记已读（支持单个/批量/全部）
     */
    public function read($request): array
    {
        $param = $this->validate($request, NotificationValidate::read());
        $this->notificationDep->markRead($request->userId, $request->platform, $param['id'] ?? null);
        return self::success();
    }

    /**
     * 获取通知列表（游标分页，Popover用）
     */
    public function listCursor($request): array
    {
        $param = $this->validate($request, NotificationValidate::list());
        $res = $this->notificationDep->listByUser($request->userId, $request->platform, $param);
        
        $list = $res['list']->map(fn($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'content' => $item->content,
            'type' => $item->type,
            'level' => $item->level,
            'link' => $item->link,
            'is_read' => $item->is_read,
            'created_at' => $item->created_at,
        ])->toArray();
        
        return self::success([
            'list' => $list,
            'next_cursor' => $res['next_cursor'],
            'has_more' => $res['has_more'],
        ]);
    }

    /**
     * 获取未读数量
     */
    public function unreadCount($request): array
    {
        return self::success(['count' => $this->notificationDep->unreadCount($request->userId, $request->platform)]);
    }

    /**
     * 删除通知（支持单个/批量）
     */
    public function del($request): array
    {
        $param = $this->validate($request, NotificationValidate::del());
        $this->notificationDep->deleteByUser($param['id'], $request->userId);
        return self::success();
    }
}
