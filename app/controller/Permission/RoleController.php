<?php
namespace app\controller\Permission;

use app\controller\Controller;
use app\module\Permission\RoleModule;
use support\Request;

class RoleController extends Controller
{
    public function init(Request $request)
    {
        $this->run([RoleModule::class, 'init'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("角色新增")
     * @Permission("role.add")
     */
    public function add(Request $request)
    {
        $this->run([RoleModule::class, 'add'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("角色删除")
     * @Permission("role.del")
     */
    public function del(Request $request)
    {
        $this->run([RoleModule::class, 'del'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("角色修改")
     * @Permission("role.edit")
     */
    public function edit(Request $request)
    {
        $this->run([RoleModule::class, 'edit'], $request);
        return $this->response();
    }

    public function list(Request $request)
    {
        $this->run([RoleModule::class, 'list'], $request);
        return $this->response();
    }

    /**
     * @OperationLog("设置默认角色")
     * @Permission("role.setDefault")
     */
    public function default(Request $request)
    {
        $this->run([RoleModule::class, 'setDefault'], $request);
        return $this->response();
    }
}
