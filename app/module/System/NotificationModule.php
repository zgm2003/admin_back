<?php

namespace app\module\System;

use app\dep\System\NotificationDep;
use app\module\BaseModule;
use app\validate\System\NotificationValidate;

class NotificationModule extends BaseModule
{
    protected NotificationDep $notificationDep;

    public function __construct()
    {
        $this->notificationDep = new NotificationDep();
    }

    /**
     * 获取通知列表（游标分页）
     */
    public function list($request): array
    {
        $param = $this->validate($request, NotificationValidate::list());
        $param['page_size'] = $param['page_size'] ?? 20;
        
        $res = $this->notificationDep->listByUser($request->userId, $param);
        
        $list = $res['list']->map(fn($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'content' => $item->content,
            'type' => $item->type,
            'level' => $item->level,
            'link' => $item->link,
            'is_read' => $item->is_read,
            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
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
        $count = $this->notificationDep->unreadCount($request->userId);
        return self::success(['count' => $count]);
    }

    /**
     * 标记已读（单条或全部）
     */
    public function read($request): array
    {
        $param = $this->validate($request, NotificationValidate::read());
        $userId = $request->userId;
        $dep = $this->notificationDep;
        
        if (!empty($param['id'])) {
            $dep->markRead((int)$param['id'], $userId);
        } else {
            $dep->markAllRead($userId);
        }
        
        return self::success();
    }

    /**
     * 删除通知
     */
    public function del($request): array
    {
        $param = $this->validate($request, NotificationValidate::del());
        $affected = $this->notificationDep->deleteByUser((int)$param['id'], $request->userId);
        self::throwIf($affected === 0, '通知不存在');
        return self::success();
    }
}
