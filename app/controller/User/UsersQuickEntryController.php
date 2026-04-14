<?php

namespace app\controller\User;

use app\controller\Controller;
use app\module\User\UsersQuickEntryModule;
use support\Request;

class UsersQuickEntryController extends Controller
{
    public function save(Request $request) { return $this->run([UsersQuickEntryModule::class, 'save'], $request); }
}
