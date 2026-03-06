<?php

namespace app\module;

use app\dep\System\SystemSettingDep;
use app\service\Permission\AuthPlatformService;
use app\service\System\SettingService;

/**
 * 测试模块
 * 用于验证 BaseModule 异常快捷方法、事务、系统设置 CRUD、三级缓存性能
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
     * 测试 403 无权限
     */
    public function testForbidden($request): array
    {
        self::throw('您没有权限执行此操作', self::CODE_FORBIDDEN);
        
        return self::success();
    }

    /**
     * 测试系统设置 CRUD
     */
    public function testSystemSetting($request): array
    {
        $dep = $this->dep(SystemSettingDep::class);
        $testKey = 'test.crud.' . time();
        
        // 1. 新增（通过 SettingService 带类型转换）
        SettingService::set($testKey, 'hello world', 1, '测试配置');
        
        // 2. 读取（通过 SettingService 带类型转换）
        $value = SettingService::get($testKey);
        self::throwIf($value !== 'hello world', '读取失败: ' . $value);
        
        // 3. 更新（通过 Dep 纯数据操作）
        $row = $dep->findByKey($testKey);
        $dep->updateById($row->id, ['setting_value' => 'updated value']);
        $value2 = SettingService::get($testKey);
        self::throwIf($value2 !== 'updated value', '更新失败: ' . $value2);
        
        // 4. 删除（通过 Dep 纯数据操作）
        $dep->deleteByKey($testKey);
        $value3 = SettingService::get($testKey);
        self::throwIf($value3 !== null, '删除失败，值仍存在');
        
        return self::success(['message' => '系统设置 CRUD 测试通过！', 'test_key' => $testKey]);
    }

    /**
     * 测试 SettingService 读取数据库配置
     */
    public function testSettingService($request): array
    {
        $accessTtl = \app\service\System\SettingService::getAccessTtl();
        $refreshTtl = \app\service\System\SettingService::getRefreshTtl();
        $adminPolicy = \app\service\System\SettingService::getAuthPolicy('admin');
        $defaultAvatar = \app\service\System\SettingService::getDefaultAvatar();
        
        return self::success([
            'access_ttl' => $accessTtl,
            'refresh_ttl' => $refreshTtl,
            'admin_policy' => $adminPolicy,
            'default_avatar' => $defaultAvatar,
        ]);
    }

    /**
     * 测试 AuthPlatformService 三级缓存性能
     * 对比：L1 内存缓存 vs L2 Redis 缓存 vs L3 MySQL
     */
    public function testMemCache($request): array
    {
        $iterations = (int)($request->post('iterations', 1000));
        $platform = $request->post('platform', 'admin');
        $results = [];

        // ==================== 测试1：预热后的内存缓存（L1） ====================
        // 先调一次预热
        AuthPlatformService::getPlatform($platform);

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            AuthPlatformService::getPlatform($platform);
        }
        $l1Time = (hrtime(true) - $start) / 1e6; // 转毫秒

        $results['L1_memory'] = [
            'iterations'   => $iterations,
            'total_ms'     => round($l1Time, 4),
            'avg_us'       => round($l1Time / $iterations * 1000, 4), // 微秒
            'ops_per_sec'  => $iterations > 0 ? round($iterations / ($l1Time / 1000)) : 0,
        ];

        // ==================== 测试2：强制走 Redis（清内存缓存后） ====================
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            AuthPlatformService::flushMemCache(); // 每次清内存，强制走 Redis
            AuthPlatformService::getPlatform($platform);
        }
        $l2Time = (hrtime(true) - $start) / 1e6;

        $results['L2_redis'] = [
            'iterations'   => $iterations,
            'total_ms'     => round($l2Time, 4),
            'avg_us'       => round($l2Time / $iterations * 1000, 4),
            'ops_per_sec'  => $iterations > 0 ? round($iterations / ($l2Time / 1000)) : 0,
        ];

        // ==================== 测试3：强制走 MySQL（清 Redis + 内存缓存） ====================
        $mysqlIterations = \min($iterations, 100); // MySQL 测试限制 100 次，避免太慢
        $cacheKey = 'auth_platform_' . $platform;

        $start = hrtime(true);
        for ($i = 0; $i < $mysqlIterations; $i++) {
            AuthPlatformService::flushMemCache();
            \support\Cache::delete($cacheKey); // 清 Redis
            AuthPlatformService::getPlatform($platform);
        }
        $l3Time = (hrtime(true) - $start) / 1e6;

        $results['L3_mysql'] = [
            'iterations'   => $mysqlIterations,
            'total_ms'     => round($l3Time, 4),
            'avg_us'       => round($l3Time / $mysqlIterations * 1000, 4),
            'ops_per_sec'  => $mysqlIterations > 0 ? round($mysqlIterations / ($l3Time / 1000)) : 0,
        ];

        // ==================== 测试4：便捷方法（基于内存缓存） ====================
        // 预热
        AuthPlatformService::getPlatform($platform);

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            AuthPlatformService::getAuthPolicy($platform);
        }
        $policyTime = (hrtime(true) - $start) / 1e6;

        $results['getAuthPolicy_cached'] = [
            'iterations'   => $iterations,
            'total_ms'     => round($policyTime, 4),
            'avg_us'       => round($policyTime / $iterations * 1000, 4),
            'ops_per_sec'  => $iterations > 0 ? round($iterations / ($policyTime / 1000)) : 0,
        ];

        // ==================== 测试5：getAllowedPlatforms 内存缓存 ====================
        AuthPlatformService::getAllowedPlatforms(); // 预热

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            AuthPlatformService::getAllowedPlatforms();
        }
        $codesTime = (hrtime(true) - $start) / 1e6;

        $results['getAllowedPlatforms_cached'] = [
            'iterations'   => $iterations,
            'total_ms'     => round($codesTime, 4),
            'avg_us'       => round($codesTime / $iterations * 1000, 4),
            'ops_per_sec'  => $iterations > 0 ? round($iterations / ($codesTime / 1000)) : 0,
        ];

        // ==================== 汇总 ====================
        $l1Avg = $results['L1_memory']['avg_us'];
        $l2Avg = $results['L2_redis']['avg_us'];
        $l3Avg = $results['L3_mysql']['avg_us'];

        $results['summary'] = [
            'L1_vs_L2_speedup' => $l1Avg > 0 ? round($l2Avg / $l1Avg, 1) . 'x' : 'N/A',
            'L1_vs_L3_speedup' => $l1Avg > 0 ? round($l3Avg / $l1Avg, 1) . 'x' : 'N/A',
            'L2_vs_L3_speedup' => $l2Avg > 0 ? round($l3Avg / $l2Avg, 1) . 'x' : 'N/A',
            'conclusion'       => $l1Avg < $l2Avg && $l2Avg < $l3Avg
                ? '✅ 三级缓存有效：L1(内存) < L2(Redis) < L3(MySQL)'
                : '⚠️ 结果异常，请检查',
        ];

        // 恢复正常缓存状态
        AuthPlatformService::flushMemCache();
        AuthPlatformService::getPlatform($platform);

        return self::success($results);
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
            'forbidden' => $this->testForbidden($request),
            'transactionSuccess' => $this->testTransactionSuccess($request),
            'transactionRollback' => $this->testTransactionRollback($request),
            'systemSetting' => $this->testSystemSetting($request),
            'settingService' => $this->testSettingService($request),
            'memCache' => $this->testMemCache($request),
            default => $this->testSuccess($request),
        };
    }
}
