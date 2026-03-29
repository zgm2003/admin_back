<?php

namespace app\lib\Pay;

use app\dep\Pay\PayChannelDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\lib\Crypto\KeyVault;
use app\model\Pay\PayChannelModel;
use RuntimeException;
use support\Log;
use Yansongda\Pay\Pay;

/**
 * 支付 SDK 统一入口
 * 基于 yansongda/pay，根据渠道动态加载配置
 *
 * yansongda/pay 已内置：
 * - 签名/验签（RSA、AES）
 * - 回调数据解密（AES-256-GCM）
 * - 响应解包（XML/JSON）
 * - 平台证书自动获取
 *
 * 所有方法直接委托给 Pay::alipay() / Pay::wechat()，利用 SDK 内置插件机制
 */
class PaySdk
{
    // ==================== 微信支付 ====================

    /** JSAPI 支付（公众号） */
    public function wechatMp(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->mp($order);
    }

    /** 微信小程序支付 */
    public function wechatMini(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->mini($order);
    }

    /** 微信 APP 支付 */
    public function wechatApp(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->app($order);
    }

    /** 微信 H5 支付 */
    public function wechatH5(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->h5($order);
    }

    /** 微信扫码支付 */
    public function wechatScan(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->scan($order);
    }

    /** 微信 JSAPI 调起支付（网页内） */
    public function wechatJsapiInvoke(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->jsapiInvoke($order);
    }

    /** 微信订单查询 */
    public function wechatQuery(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->query($order);
    }

    /** 微信订单关闭 */
    public function wechatClose(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->close($order);
    }

    /** 微信退款 */
    public function wechatRefund(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->refund($order);
    }

    /** 微信退款查询 */
    public function wechatRefundQuery(int $channelId, array $order): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->refundQuery($order);
    }

    /** 微信支付回调验签（自动解密） */
    public function wechatCallback(int $channelId, array|object|null $contents = null): mixed
    {
        $this->initWechat($channelId);
        return Pay::wechat()->callback($contents);
    }

    /** 微信返回成功响应 */
    public function wechatSuccess(): object
    {
        return Pay::wechat()->success();
    }

    // ==================== 支付宝 ====================

    /** 支付宝电脑网站支付 */
    public function alipayWeb(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->web($order);
    }

    /** 支付宝手机网站支付 */
    public function alipayWap(int $channelId, array $order): mixed
    {
        return $this->alipayH5($channelId, $order);
    }

    /** 支付宝 H5 支付 */
    public function alipayH5(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->h5($order);
    }

    /** 支付宝 APP 支付 */
    public function alipayApp(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->app($order);
    }

    /** 支付宝扫码支付 */
    public function alipayScan(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->scan($order);
    }

    /** 支付宝小程序支付 */
    public function alipayMini(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->mini($order);
    }

    /** 支付宝订单查询 */
    public function alipayQuery(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->query($order);
    }

    /** 支付宝订单关闭 */
    public function alipayClose(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->close($order);
    }

    /** 支付宝退款 */
    public function alipayRefund(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->refund($order);
    }

    /** 支付宝退款查询 */
    public function alipayRefundQuery(int $channelId, array $order): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->refundQuery($order);
    }

    /** 支付宝支付回调验签（自动解密） */
    public function alipayCallback(int $channelId, array|object|null $contents = null): mixed
    {
        $this->initAlipay($channelId);
        return Pay::alipay()->callback($contents);
    }

    /** 支付宝返回成功响应 */
    public function alipaySuccess(): object
    {
        return Pay::alipay()->success();
    }

    // ==================== 初始化（静态方法，供外部直接调用）====================

    /** 初始化微信支付 */
    public static function initWechat(int $channelId): void
    {
        $config = (new self())->buildWechatConfig($channelId);
        Pay::config($config);
    }

    /** 初始化支付宝 */
    public static function initAlipay(int $channelId): void
    {
        $config = (new self())->buildAlipayConfig($channelId);
        Pay::config($config);
    }

    // ==================== 配置构建 ====================

    /** 根据渠道 ID 构建微信配置 */
    public function buildWechatConfig(int $channelId): array
    {
        $channel = (new PayChannelDep())->findActive($channelId);
        if (!$channel || $channel->channel !== PayEnum::CHANNEL_WECHAT) {
            throw new RuntimeException('微信支付渠道不存在或已禁用');
        }

        $extra = is_array($channel->extra_config) ? $channel->extra_config : ($channel->extra_config ? json_decode($channel->extra_config, true) : []);
        $config = [
            'wechat' => [
                'default' => [
                    'mch_id'              => $channel->mch_id,
                    'mch_secret_cert'     => $this->decrypt($channel->app_private_key_enc),
                    'mch_public_cert_path' => $channel->public_cert_path ?: '',
                    'notify_url'          => $channel->notify_url ?: '',
                    'mode'               => $channel->is_sandbox === CommonEnum::YES ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
                ],
            ],
            'logger' => [
                'enable'  => true,
                'file'    => runtime_path() . '/logs/wechat.log',
                'level'   => env('APP_DEBUG', false) ? 'debug' : 'info',
                'type'    => 'daily',
                'max_file' => 30,
            ],
            'http' => [
                'timeout'         => 30,
                'connect_timeout' => 30,
            ],
        ];

        // 各端 app_id
        foreach (['mp_app_id', 'mini_app_id', 'app_id', 'h5_app_id'] as $key) {
            if (!empty($extra[$key])) {
                $config['wechat']['default'][$key] = $extra[$key];
            }
        }

        // 微信平台证书（Swoole 模式下 SDK 会自动获取，php-fpm 建议配置）
        // key 为任意唯一标识，用路径 MD5 保证确定性
        if (!empty($channel->platform_cert_path)) {
            $config['wechat']['default']['wechat_public_cert_path'] = [
                md5($channel->platform_cert_path) => $channel->platform_cert_path,
            ];
        }

        return $config;
    }

    /** 根据渠道 ID 构建支付宝配置 */
    public function buildAlipayConfig(int $channelId): array
    {
        $channel = (new PayChannelDep())->findActive($channelId);
        if (!$channel || $channel->channel !== PayEnum::CHANNEL_ALIPAY) {
            throw new RuntimeException('支付宝渠道不存在或已禁用');
        }

        $extra = is_array($channel->extra_config) ? $channel->extra_config : ($channel->extra_config ? json_decode($channel->extra_config, true) : []);
        $appId = trim((string) ($channel->app_id ?? ''));
        if ($appId === '') {
            throw new RuntimeException('配置异常: 缺少支付宝 app_id');
        }

        $appCertPath = $this->resolveStoredCertPath((string) ($channel->public_cert_path ?? ''), '应用公钥证书');
        $platformCertPath = $this->resolveStoredCertPath((string) ($channel->platform_cert_path ?? ''), '支付宝公钥证书');
        $rootCertPath = $this->resolveStoredCertPath((string) ($channel->root_cert_path ?? ''), '支付宝根证书');

        $config = [
            'alipay' => [
                'default' => [
                    'app_id'               => $appId,
                    'app_secret_cert'      => $this->decrypt($channel->app_private_key_enc),
                    'app_public_cert_path' => $appCertPath,
                    'alipay_public_cert_path' => $platformCertPath,
                    'alipay_root_cert_path'   => $rootCertPath,
                    'notify_url'           => $channel->notify_url ?: '',
                    'mode'                => $channel->is_sandbox === CommonEnum::YES ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
                ],
            ],
            'logger' => [
                'enable'  => true,
                'file'    => runtime_path() . '/logs/alipay.log',
                'level'   => env('APP_DEBUG', false) ? 'debug' : 'info',
                'type'    => 'daily',
                'max_file' => 30,
            ],
            'http' => [
                'timeout'         => 30,
                'connect_timeout' => 30,
            ],
        ];

        return $config;
    }

    private function resolveStoredCertPath(string $path, string $label): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path));
        if ($normalizedPath === '') {
            throw new RuntimeException("配置异常: 缺少{$label}路径");
        }

        if (!preg_match('#^[A-Za-z]:/#', $normalizedPath) && !str_starts_with($normalizedPath, '/')) {
            $normalizedPath = str_replace('\\', '/', base_path()) . '/' . ltrim($normalizedPath, '/');
        }

        if (!is_file($normalizedPath)) {
            throw new RuntimeException("配置异常: {$label}不存在或不可读 ({$normalizedPath})");
        }

        return $normalizedPath;
    }

    /** 解密密钥 */
    private function decrypt(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }
        try {
            return KeyVault::decrypt($encrypted);
        } catch (\Throwable $e) {
            Log::error('[PaySdk] 解密密钥失败', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
