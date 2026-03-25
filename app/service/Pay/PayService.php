<?php

namespace app\service\Pay;

/**
 * 支付服务统一入口（已迁移至 yansongda/pay）
 *
 * 请直接使用 \app\lib\Pay\PaySdk，以下方法仅为兼容旧调用
 * @deprecated 请使用 PaySdk 实例方法
 */
class PayService
{
    private \app\lib\Pay\PaySdk $sdk;

    public function __construct()
    {
        $this->sdk = new \app\lib\Pay\PaySdk();
    }

    public function wechatMp(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatMp($channelId, $order);
    }

    public function wechatMini(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatMini($channelId, $order);
    }

    public function wechatApp(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatApp($channelId, $order);
    }

    public function wechatH5(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatH5($channelId, $order);
    }

    public function wechatScan(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatScan($channelId, $order);
    }

    public function wechatQuery(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatQuery($channelId, $order);
    }

    public function wechatClose(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatClose($channelId, $order);
    }

    public function wechatRefund(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatRefund($channelId, $order);
    }

    public function wechatRefundQuery(int $channelId, array $order): mixed
    {
        return $this->sdk->wechatRefundQuery($channelId, $order);
    }

    public function wechatCallback(int $channelId, array|object|null $contents = null): mixed
    {
        return $this->sdk->wechatCallback($channelId, $contents);
    }

    public function wechatSuccess(): object
    {
        return $this->sdk->wechatSuccess();
    }

    public function alipayWeb(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayWeb($channelId, $order);
    }

    public function alipayWap(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayWap($channelId, $order);
    }

    public function alipayApp(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayApp($channelId, $order);
    }

    public function alipayScan(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayScan($channelId, $order);
    }

    public function alipayMini(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayMini($channelId, $order);
    }

    public function alipayQuery(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayQuery($channelId, $order);
    }

    public function alipayClose(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayClose($channelId, $order);
    }

    public function alipayRefund(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayRefund($channelId, $order);
    }

    public function alipayRefundQuery(int $channelId, array $order): mixed
    {
        return $this->sdk->alipayRefundQuery($channelId, $order);
    }

    public function alipayCallback(int $channelId, array|object|null $contents = null): mixed
    {
        return $this->sdk->alipayCallback($channelId, $contents);
    }

    public function alipaySuccess(): object
    {
        return $this->sdk->alipaySuccess();
    }
}
