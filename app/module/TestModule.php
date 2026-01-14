<?php

namespace app\module;

/**
 * 测试模块 - 用于测试 BaseModule 的异常快捷方法
 */
class TestModule extends BaseModule
{
    /**
     * 测试 self::throw()
     */
    public function testThrow($request): array
    {
        self::throw('这是一个直接抛出的业务异常');
        
        return self::success(['msg' => '不会执行到这里']);
    }

    /**
     * 测试 self::throwIf()
     */
    public function testThrowIf($request): array
    {
        $value = $request->post('value', 'bad');
        
        self::throwIf($value === 'bad', '参数 value 不能是 bad');
        
        return self::success(['value' => $value]);
    }

    /**
     * 测试 self::throwUnless()
     */
    public function testThrowUnless($request): array
    {
        $user = $request->post('user');
        
        self::throwUnless($user, '用户不存在');
        
        return self::success(['user' => $user]);
    }

    /**
     * 测试 self::throwNotFound()
     */
    public function testThrowNotFound($request): array
    {
        $id = $request->post('id');
        $resource = $id ? ['id' => $id, 'name' => 'test'] : null;
        
        self::throwNotFound($resource, '资源不存在');
        
        return self::success($resource);
    }

    /**
     * 测试事务 - 成功场景
     */
    public function testTransactionSuccess($request): array
    {
        $this->withTransaction(function () {
            // 模拟两个数据库操作
            \support\Db::table('system_settings')->where('id', 1)->update(['updated_at' => date('Y-m-d H:i:s')]);
            \support\Db::table('system_settings')->where('id', 2)->update(['updated_at' => date('Y-m-d H:i:s')]);
        });
        
        return self::success(['message' => '事务提交成功']);
    }

    /**
     * 测试事务 - 回滚场景
     */
    public function testTransactionRollback($request): array
    {
        $this->withTransaction(function () {
            // 第一个操作成功
            \support\Db::table('system_settings')->where('id', 1)->update(['updated_at' => date('Y-m-d H:i:s')]);
            
            // 第二个操作前抛异常，触发回滚
            self::throw('模拟事务中的业务异常，触发回滚');
            
            // 这行不会执行
            \support\Db::table('system_settings')->where('id', 2)->update(['updated_at' => date('Y-m-d H:i:s')]);
        });
        
        return self::success(['message' => '不会执行到这里']);
    }

    /**
     * 测试正常流程
     */
    public function testSuccess($request): array
    {
        return self::success(['message' => 'BaseModule 异常快捷方法测试通过！']);
    }

    /**
     * 综合测试
     */
    public function test($request): array
    {
        $action = $request->post('action', 'success');
        
        return match ($action) {
            'throw' => $this->testThrow($request),
            'throwIf' => $this->testThrowIf($request),
            'throwUnless' => $this->testThrowUnless($request),
            'throwNotFound' => $this->testThrowNotFound($request),
            'transactionSuccess' => $this->testTransactionSuccess($request),
            'transactionRollback' => $this->testTransactionRollback($request),
            default => $this->testSuccess($request),
        };
    }
}
