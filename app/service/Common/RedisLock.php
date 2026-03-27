<?php

namespace app\service\Common;

use support\Redis;

class RedisLock
{
    /**
     * 加锁
     * @param string $key 锁键
     * @param int $ttl 锁过期时间（秒）
     * @return string|false 返回锁值（解锁时需要），失败返回 false
     */
    public static function lock(string $key, int $ttl = 10)
    {
        $value = uniqid('', true); // 唯一标识（解锁凭证）
        $isLocked = Redis::set($key, $value, 'EX', $ttl, 'NX');
        return $isLocked ? $value : false;
    }

    /**
     * 解锁（通过 Lua 脚本确保原子性）
     * @param string $key
     * @param string $value 加锁时的值
     * @return bool
     */
    public static function unlock(string $key, string $value): bool
    {
        $script = <<<LUA
if redis.call("GET", KEYS[1]) == ARGV[1] then
    return redis.call("DEL", KEYS[1])
else
    return 0
end
LUA;
        $result = Redis::eval($script, 1, $key, $value);
        return $result === 1;
    }
}
