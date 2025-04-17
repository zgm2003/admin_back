<?php

namespace app\module\Chat;

use App\Dep\Chat\ChatDep;
use App\Dep\User\UsersDep;
use App\Enum\ChatEnum;
use App\Enum\CommonEnum;
use App\Module\BaseModule;
use App\Service\DictService;
use GatewayWorker\Lib\Gateway;


class ChatModule extends BaseModule
{
    public $chatDep;
    public $userDep;

    public function __construct()
    {
        $this->chatDep = new ChatDep();
        $this->userDep = new UsersDep();
    }

    public function init($request)
    {
        $param = $request->all();
        $user = $request->user();
        if (empty($param['client_id'])
        ) {
            return self::response([], '缺少参数', 100);
        }
        Gateway::bindUid($param['client_id'], $user->id);
        Gateway::setSession($param['client_id'], [
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'client_id' => $param['client_id'],

        ]);

        // 发送在线人数
        $this->online($request);

        // 发送欢迎消息
        $this->welcome($request);
        return self::response();
    }

    //欢迎用户
    public function welcome($request)
    {
        $data = [
            'type' => 'welcome',
            'data' => [
                'user_id' => $request->user()->id,
                'username' => $request->user()->username,
                'avatar' => $request->user()->avatar,
                'created_at' => date('Y-m-d H:i:s', time()),
            ]
        ];
        Gateway::sendToAll(json_encode($data));
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
                'content' => $param['content'],
                'username' => $user->username,
                'avatar' => $user->avatar,
                'type' => $param['type'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ]
        ];

        // 发送给所有 WebSocket 连接的用户
        Gateway::sendToAll(json_encode($data));

        // 存入数据库
        $data1 = [
            'user_id' => $user->id,
            'type' => $param['type'],
            'content' => $param['content'],
        ];
        $this->chatDep->add($data1);

        return self::response();
    }


    public function online($request)
    {
        $data = [
            'type' => 'online',
            'data' => Gateway::getAllClientSessions()
        ];
        Gateway::sendToAll(json_encode($data));
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


}

