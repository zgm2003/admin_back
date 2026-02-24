<?php

namespace app\module\System;

use app\dep\System\NotificationDep;
use app\enum\NotificationEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\NotificationValidate;

/**
 * 用户通知模块
 * 负责：通知列表（普通分页+游标分页）、标记已读、未读计数、删除
 */
class NotificationModule extends BaseModule
{
    /**
     * 初始化（返回通知类型、级别、已读状态字典）
     */
    public function init(): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setNotificationTypeArr()
            ->setNotificationLevelArr()
            ->setNotificationReadStatusArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 通知列表（普通分页，独立通知页面用）
     * 按当前用户 + 平台过滤
     */
    public function list($request): array
    {
        $param = $this->validate($request, NotificationValidate::pageList());
        $res = $this->dep(NotificationDep::class)->pageListByUser($request->userId, $request->platform, $param);

        $data['list'] = $res->map(fn($item) => [
            'id'         => $item->id,
            'title'      => $item->title,
            'content'    => $item->content,
            'type'       => $item->type,
            'type_text'  => NotificationEnum::$typeArr[$item->type] ?? '未知',
            'level'      => $item->level,
            'level_text' => NotificationEnum::$levelArr[$item->level] ?? '未知',
            'link'       => $item->link,
            'is_read'    => $item->is_read,
            'created_at' => $item->created_at,
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
     * 标记已读（id 为空则全部已读，支持单个/批量）
     */
    public function read($request): array
    {
        $param = $this->validate($request, NotificationValidate::read());
        $this->dep(NotificationDep::class)->markRead($request->userId, $request->platform, $param['id'] ?? null);
        return self::success();
    }

    /**
     * 通知列表（游标分页，Header Popover 弹窗用）
     * 返回 next_cursor + has_more 供前端无限滚动
     */
    public function listCursor($request): array
    {
        $param = $this->validate($request, NotificationValidate::list());
        $res = $this->dep(NotificationDep::class)->listByUser($request->userId, $request->platform, $param);

        $list = $res['list']->map(fn($item) => [
            'id'         => $item->id,
            'title'      => $item->title,
            'content'    => $item->content,
            'type'       => $item->type,
            'level'      => $item->level,
            'link'       => $item->link,
            'is_read'    => $item->is_read,
            'created_at' => $item->created_at,
        ])->toArray();

        return self::success([
            'list'        => $list,
            'next_cursor' => $res['next_cursor'],
            'has_more'    => $res['has_more'],
        ]);
    }

    /**
     * 获取当前用户未读通知数量
     */
    public function unreadCount($request): array
    {
        return self::success([
            'count' => $this->dep(NotificationDep::class)->unreadCount($request->userId, $request->platform),
        ]);
    }

    /**
     * 删除通知（支持单个/批量，只能删自己的）
     */
    public function del($request): array
    {
        $param = $this->validate($request, NotificationValidate::del());
        $this->dep(NotificationDep::class)->deleteByUser($param['id'], $request->userId);
        return self::success();
    }
}
