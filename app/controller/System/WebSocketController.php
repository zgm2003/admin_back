<?php

namespace app\controller\System;

use app\controller\Controller;
use app\module\System\WebSocketModule;
use support\Request;

class WebSocketController extends Controller
{
    public function bind(Request $request) { return $this->run([WebSocketModule::class, 'bind'], $request); }
    public function joinGroup(Request $request) { return $this->run([WebSocketModule::class, 'joinGroup'], $request); }
    public function onlineCount(Request $request) { return $this->run([WebSocketModule::class, 'onlineCount'], $request); }
    public function pushToUser(Request $request) { return $this->run([WebSocketModule::class, 'pushToUser'], $request); }
    public function broadcast(Request $request) { return $this->run([WebSocketModule::class, 'broadcast'], $request); }
}
