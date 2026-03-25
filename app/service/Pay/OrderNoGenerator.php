<?php

namespace app\service\Pay;

use support\Redis;

/**
 * 订单号生成器
 * 格式: {前缀}{yyMMddHHmmss}{6位自增/随机}
 * 示例: R260323143025000001（R=充值）
 */
class OrderNoGenerator
{
    private const PREFIX_RECHARGE = 'R';
    private const PREFIX_CONSUME  = 'C';
    private const PREFIX_GOODS    = 'G';
    private const PREFIX_REFUND    = 'F';
    private const PREFIX_FULFILL  = 'D';
    private const PREFIX_TXN      = 'T';

    private const REDIS_KEY_COUNTER = 'pay_order_no_counter';

    /**
     * 生成充值订单号
     */
    public static function recharge(): string
    {
        return self::generate(self::PREFIX_RECHARGE);
    }

    /**
     * 生成消费订单号
     */
    public static function consume(): string
    {
        return self::generate(self::PREFIX_CONSUME);
    }

    /**
     * 生成商品订单号
     */
    public static function goods(): string
    {
        return self::generate(self::PREFIX_GOODS);
    }

    /**
     * 生成退款单号
     */
    public static function refund(): string
    {
        return self::generate(self::PREFIX_REFUND);
    }

    /**
     * 生成履约单号
     */
    public static function fulfill(): string
    {
        return self::generate(self::PREFIX_FULFILL);
    }

    /**
     * 生成支付流水号
     */
    public static function transaction(): string
    {
        return self::generate(self::PREFIX_TXN);
    }

    /**
     * 核心生成逻辑
     * Redis INCR 保证全局唯一且递增
     */
    private static function generate(string $prefix): string
    {
        $date = date('ymdHis');
        $seq = Redis::connection('default')->incr(self::REDIS_KEY_COUNTER);
        $seq = str_pad((string) ($seq % 1000000), 6, '0', STR_PAD_LEFT);

        return $prefix . $date . $seq;
    }
}
