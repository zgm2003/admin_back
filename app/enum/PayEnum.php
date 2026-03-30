<?php

namespace app\enum;

class PayEnum
{
    // ==================== 支付渠道 ====================
    const CHANNEL_WECHAT = 1;
    const CHANNEL_ALIPAY = 2;

    public static $channelArr = [
        self::CHANNEL_WECHAT => '微信支付',
        self::CHANNEL_ALIPAY => '支付宝',
    ];

    // ==================== 支付方式 ====================
    const METHOD_WEB  = 'web';
    const METHOD_H5   = 'h5';
    const METHOD_APP  = 'app';
    const METHOD_MINI = 'mini';
    const METHOD_SCAN = 'scan';
    const METHOD_MP   = 'mp';

    public static $methodArr = [
        self::METHOD_WEB  => 'PC网页支付',
        self::METHOD_H5   => 'H5支付',
        self::METHOD_APP  => 'APP支付',
        self::METHOD_MINI => '小程序支付',
        self::METHOD_SCAN => '扫码支付',
        self::METHOD_MP   => '公众号支付',
    ];

    public static array $channelMethodArr = [
        self::CHANNEL_WECHAT => [
            self::METHOD_SCAN,
            self::METHOD_H5,
            self::METHOD_APP,
            self::METHOD_MINI,
            self::METHOD_MP,
        ],
        self::CHANNEL_ALIPAY => [
            self::METHOD_WEB,
            self::METHOD_H5,
            self::METHOD_APP,
            self::METHOD_SCAN,
            self::METHOD_MINI,
        ],
    ];

    public static function getDefaultSupportedMethods(int $channel): array
    {
        return self::$channelMethodArr[$channel] ?? array_keys(self::$methodArr);
    }

    public static function normalizeSupportedMethods(int $channel, array $methods): array
    {
        $allowedMethods = self::getDefaultSupportedMethods($channel);
        $allowedMap = array_fill_keys($allowedMethods, true);

        $normalized = [];
        foreach ($methods as $method) {
            $method = (string) $method;
            if ($method === '' || !isset($allowedMap[$method])) {
                continue;
            }

            $normalized[$method] = $method;
        }

        return array_values($normalized);
    }

    // ==================== 订单类型 ====================
    const TYPE_RECHARGE = 1;
    const TYPE_CONSUME  = 2;
    const TYPE_GOODS    = 3;

    public static $orderTypeArr = [
        self::TYPE_RECHARGE => '充值',
        self::TYPE_CONSUME  => '消费',
        self::TYPE_GOODS    => '商品购买',
    ];

    // ==================== 订单支付状态 pay_status ====================
    const PAY_STATUS_PENDING    = 1;
    const PAY_STATUS_PAYING     = 2;
    const PAY_STATUS_PAID       = 3;
    const PAY_STATUS_CLOSED     = 4;
    const PAY_STATUS_EXCEPTION  = 5;

    public static $payStatusArr = [
        self::PAY_STATUS_PENDING   => '待支付',
        self::PAY_STATUS_PAYING   => '支付中',
        self::PAY_STATUS_PAID     => '已支付',
        self::PAY_STATUS_CLOSED   => '已关闭',
        self::PAY_STATUS_EXCEPTION => '支付异常',
    ];

    private static array $payStatusTransitions = [
        self::PAY_STATUS_PENDING => [self::PAY_STATUS_PAYING, self::PAY_STATUS_PAID, self::PAY_STATUS_CLOSED],
        self::PAY_STATUS_PAYING  => [self::PAY_STATUS_PAID, self::PAY_STATUS_CLOSED, self::PAY_STATUS_EXCEPTION],
        self::PAY_STATUS_CLOSED  => [self::PAY_STATUS_PAID, self::PAY_STATUS_EXCEPTION],
    ];

    public static function canTransitPayStatus(int $from, int $to): bool
    {
        return in_array($to, self::$payStatusTransitions[$from] ?? [], true);
    }

    // ==================== 订单业务状态 biz_status ====================
    const BIZ_STATUS_INIT      = 1;
    const BIZ_STATUS_PENDING   = 2;
    const BIZ_STATUS_EXECUTING = 3;
    const BIZ_STATUS_SUCCESS   = 4;
    const BIZ_STATUS_FAILED    = 5;
    const BIZ_STATUS_MANUAL    = 6;

    public static $bizStatusArr = [
        self::BIZ_STATUS_INIT      => '初始化',
        self::BIZ_STATUS_PENDING    => '待履约',
        self::BIZ_STATUS_EXECUTING  => '履约中',
        self::BIZ_STATUS_SUCCESS    => '履约成功',
        self::BIZ_STATUS_FAILED     => '履约失败',
        self::BIZ_STATUS_MANUAL    => '人工处理',
    ];

    private static array $bizStatusTransitions = [
        self::BIZ_STATUS_INIT      => [self::BIZ_STATUS_PENDING],
        self::BIZ_STATUS_PENDING   => [self::BIZ_STATUS_EXECUTING],
        self::BIZ_STATUS_EXECUTING => [self::BIZ_STATUS_SUCCESS, self::BIZ_STATUS_FAILED],
        self::BIZ_STATUS_FAILED    => [self::BIZ_STATUS_EXECUTING, self::BIZ_STATUS_MANUAL],
    ];

    public static function canTransitBizStatus(int $from, int $to): bool
    {
        return in_array($to, self::$bizStatusTransitions[$from] ?? [], true);
    }

    // ==================== 支付流水状态 ====================
    const TXN_CREATED = 1;
    const TXN_WAITING  = 2;
    const TXN_SUCCESS  = 3;
    const TXN_FAILED   = 4;
    const TXN_CLOSED   = 5;

    public static $txnStatusArr = [
        self::TXN_CREATED => '已创建',
        self::TXN_WAITING => '等待支付',
        self::TXN_SUCCESS => '支付成功',
        self::TXN_FAILED  => '支付失败',
        self::TXN_CLOSED  => '已关闭',
    ];

    // ==================== 履约状态 ====================
    const FULFILL_PENDING  = 1;
    const FULFILL_RUNNING  = 2;
    const FULFILL_SUCCESS  = 3;
    const FULFILL_FAILED   = 4;
    const FULFILL_MANUAL   = 5;

    public static $fulfillStatusArr = [
        self::FULFILL_PENDING  => '待执行',
        self::FULFILL_RUNNING  => '执行中',
        self::FULFILL_SUCCESS  => '执行成功',
        self::FULFILL_FAILED   => '执行失败',
        self::FULFILL_MANUAL   => '人工处理',
    ];

    // ==================== 履约动作类型 ====================
    const FULFILL_ACTION_RECHARGE = 1;
    const FULFILL_ACTION_CONSUME  = 2;
    const FULFILL_ACTION_GOODS    = 3;

    public static $fulfillActionArr = [
        self::FULFILL_ACTION_RECHARGE => '充值入账',
        self::FULFILL_ACTION_CONSUME  => '消费履约',
        self::FULFILL_ACTION_GOODS    => '商品回调',
    ];

    // ==================== 钱包流水类型 ====================
    const WALLET_RECHARGE = 1;
    const WALLET_CONSUME  = 2;
    const WALLET_ADJUST   = 3;

    public static $walletTypeArr = [
        self::WALLET_RECHARGE => '充值入账',
        self::WALLET_CONSUME  => '消费扣款',
        self::WALLET_ADJUST   => '系统调账',
    ];

    // ==================== 钱包流水来源类型 ====================
    const WALLET_SOURCE_NONE    = 0;
    const WALLET_SOURCE_FULFILL = 1;
    const WALLET_SOURCE_MANUAL  = 2;

    public static $walletSourceArr = [
        self::WALLET_SOURCE_NONE    => '未关联',
        self::WALLET_SOURCE_FULFILL => '履约',
        self::WALLET_SOURCE_MANUAL  => '人工',
    ];

    // ==================== 回调通知类型 ====================
    const NOTIFY_PAY = 1;

    public static $notifyTypeArr = [
        self::NOTIFY_PAY => '支付回调',
    ];

    // ==================== 对账任务状态 ====================
    const RECONCILE_PENDING   = 1;
    const RECONCILE_DOWNLOAD  = 2;
    const RECONCILE_COMPARING = 3;
    const RECONCILE_SUCCESS   = 4;
    const RECONCILE_DIFF      = 5;
    const RECONCILE_FAILED    = 6;

    public static $reconcileStatusArr = [
        self::RECONCILE_PENDING   => '待执行',
        self::RECONCILE_DOWNLOAD  => '下载中',
        self::RECONCILE_COMPARING => '对比中',
        self::RECONCILE_SUCCESS   => '成功',
        self::RECONCILE_DIFF      => '有差异',
        self::RECONCILE_FAILED    => '失败',
    ];

    // ==================== 业务常量 ====================
    const ORDER_EXPIRE_SECONDS = 1800;
    const FULFILL_MAX_RETRY   = 10;
    const FULFILL_RETRY_BASE   = 30;

    /** 充值金额档位（分） */
    public static array $rechargePresetArr = [
        1000  => '10元',
        3000  => '30元',
        5000  => '50元',
        10000 => '100元',
        30000 => '300元',
        50000 => '500元',
    ];
}
