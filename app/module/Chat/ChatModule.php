<?php

namespace app\module\Chat;

use app\dep\Chat\ChatDep;
use app\dep\Chat\UsersRoomDep;
use app\dep\User\UsersDep;
use app\module\BaseModule;
use GatewayWorker\Lib\Gateway;


class ChatModule extends BaseModule
{
    public $chatDep;
    public $userDep;
    public $userRoomDep;

    public function __construct()
    {
        $this->chatDep = new ChatDep();
        $this->userDep = new UsersDep();
        $this->userRoomDep = new UsersRoomDep();
    }

    public function init($request)
    {
        $param = $request->all();
        $user = $request->user();
        $roomIds = $this->userRoomDep->getByUserId($user->id)->pluck('room_id');

        if (
            empty($param['client_id'])
        ) {
            return self::response([], '缺少参数', 100);
        }
        Gateway::bindUid($param['client_id'], $user->id);

        foreach ($roomIds as $rid) {
            Gateway::joinGroup($param['client_id'], $rid);
        }

        Gateway::setSession($param['client_id'], [
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'client_id' => $param['client_id'],
        ]);

        // 广播给所有在线用户
        foreach ($roomIds as $roomId){
            $data = [
                'type' => 'online',
                'data' => Gateway::getClientSessionsByGroup($roomId)
            ];
            Gateway::sendToGroup($roomId,json_encode($data));
        }


        return self::response();
    }

    public function send($request)
    {
        $param = $request->all();
        $user = $request->user();

        if (empty($param['content'])) {
            return self::response([], '未接收任何内容', 100);
        }

        $data = [
            'type' => 'message',
            'data' => [
                'user_id' => $user->id,  // 发送者的 ID
                'room_id' => $param['room_id'],
                'content' => $param['content'],
                'username' => $user->username,
                'avatar' => $user->avatar,
                'type' => $param['type'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ]
        ];

        // 发送给所有 WebSocket 连接的用户
        Gateway::sendToGroup($param['room_id'],json_encode($data));

        // 存入数据库
        $data1 = [
            'user_id' => $user->id,
            'type' => $param['type'],
            'content' => $param['content'],
            'room_id' => $param['room_id'],
        ];
        $this->chatDep->add($data1);

        return self::response();
    }


    public function online($request)
    {
        $param  = $request->all();
        $roomId = $param['room_id'] ?? null;
        if (!$roomId) {
            return self::response([], '缺少房间号', 100);
        }
        // 从 Gateway 拿到这个房间所有会话
        $sessions = array_values(
            Gateway::getClientSessionsByGroup($roomId)
        );
        // 给整个房间组广播在线
        $payload = json_encode([
            'type'    => 'online',
            'data'    => $sessions,
        ]);
        Gateway::sendToGroup($roomId, $payload);
        return self::response();
    }



    public function list($request)
    {

        $dep = $this->chatDep;
        $userDep = $this->userDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);
        $data['list'] = $resList->map(function ($item) use ($userDep) {
            //发言人
            $resUser = $userDep->first($item['user_id']);

            return [
                'id' => $item['id'],
                'user_id' => $item['user_id'],
                'username' => $resUser->username,
                'avatar' => $resUser->avatar,
                'room_id' => $item['room_id'],
                'type' => $item['type'],
                'content' => $item['content'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::response($data);
    }

    /**
     * 退出房间（不再需要前端传 client_id）
     */
    public function exit($request)
    {
        $user   = $request->user();
        $roomId = $request->input('room_id');

        if (!$roomId) {
            return self::response([], '缺少 room_id', 100);
        }

        // 1. 拿到当前用户所有的 client_id 列表
        $clientIds = Gateway::getClientIdByUid($user->id);
        if (empty($clientIds)) {
            // 已经全部断开或没连接过
            return self::response([], '没有找到活动连接或已退出', 0);
        }

        // 2. 对每个连接都执行 leaveGroup
        foreach ($clientIds as $cid) {
            Gateway::leaveGroup($cid, $roomId);
        }

        // 3. （可选）清理 Session 中的房间列表
        foreach ($clientIds as $cid) {
            $sess = Gateway::getSession($cid);
            if (isset($sess['current_rooms'])) {
                $rooms = array_filter($sess['current_rooms'], fn($r)=> $r != $roomId);
                Gateway::setSession($cid, ['current_rooms' => $rooms]);
            }
        }

        // 4. 广播给房间内剩余连接：更新在线列表
        $sessions = array_values(Gateway::getClientSessionsByGroup($roomId));
        $payload  = json_encode([
            'type' => 'online',
            'data' => $sessions,
        ]);
        Gateway::sendToGroup($roomId, $payload);

        $this->userRoomDep->deleteByUserIdAndRoomId($user->id,  $roomId);

        return self::response([], '退出房间成功');
    }



}

