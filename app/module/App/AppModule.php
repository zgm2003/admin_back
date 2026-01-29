<?php

namespace app\module\App;

use app\module\BaseModule;

class AppModule extends BaseModule
{
    /**
     * 测试接口
     */
    public function test($request)
    {
        return self::success(['message' => '权限验证通过！']);
    }
}
