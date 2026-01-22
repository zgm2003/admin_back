<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersQuickEntryModule;
use support\Request;

class UsersQuickEntryController extends Controller
{
    public function add(Request $request) { return $this->run([UsersQuickEntryModule::class, 'add'], $request); }
    public function del(Request $request) { return $this->run([UsersQuickEntryModule::class, 'del'], $request); }
    public function sort(Request $request) { return $this->run([UsersQuickEntryModule::class, 'sort'], $request); }
}
