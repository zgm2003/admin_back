<?php

namespace app\controller\App;

use app\controller\Controller;
use app\module\App\AppModule;
use support\Request;

class AppController extends Controller
{
    /** @OperationLog("APP测试") @Permission("test_test") */
    public function test(Request $request) { return $this->run([AppModule::class, 'test'], $request); }

    /** 版本检查（暂时 mock，后续接数据库） */
    public function versionCheck(Request $request)
    {
        return json([
            'code' => 0,
            'data' => [
                'has_update'   => false,
                'version'      => $request->post('version', '1.0.0'),
                'description'  => '',
                'download_url' => '',
                'force_update' => false,
            ],
            'msg' => 'ok',
        ]);
    }
}
