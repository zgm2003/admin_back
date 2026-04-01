<?php

namespace app\service\Pay;

use app\dep\Pay\PayChannelDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Yansongda\Artful\Plugin\AddPayloadBodyPlugin;
use Yansongda\Artful\Plugin\ParserPlugin;
use Yansongda\Artful\Plugin\StartPlugin;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Plugin\Alipay\V2\AddPayloadSignaturePlugin as AlipayAddPayloadSignaturePlugin;
use Yansongda\Pay\Plugin\Alipay\V2\AddRadarPlugin as AlipayAddRadarPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\FormatPayloadBizContentPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\Pay\Web\QueryBillUrlPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\ResponsePlugin as AlipayResponsePlugin;
use Yansongda\Pay\Plugin\Alipay\V2\StartPlugin as AlipayStartPlugin;
use Yansongda\Pay\Plugin\Alipay\V2\VerifySignaturePlugin as AlipayVerifySignaturePlugin;
use Yansongda\Pay\Plugin\Wechat\AddRadarPlugin as WechatAddRadarPlugin;
use Yansongda\Pay\Plugin\Wechat\ResponsePlugin as WechatResponsePlugin;
use Yansongda\Pay\Plugin\Wechat\V3\AddPayloadSignaturePlugin as WechatAddPayloadSignaturePlugin;
use Yansongda\Pay\Plugin\Wechat\V3\Pay\Bill\DownloadPlugin;
use Yansongda\Pay\Plugin\Wechat\V3\Pay\Bill\GetTradePlugin;
use Yansongda\Pay\Plugin\Wechat\V3\VerifySignaturePlugin as WechatVerifySignaturePlugin;
use Yansongda\Supports\Collection;

class PayChannelService
{
    private PayChannelDep $payChannelDep;

    public function __construct()
    {
        $this->payChannelDep = new PayChannelDep();
    }

    public function resolveRechargeChannel(int $channelId, int $channelType = 0): ?object
    {
        $channelById = $channelId > 0 ? $this->payChannelDep->findActive($channelId) : null;
        $legacyChannelEnum = $channelId > 0 && isset(PayEnum::$channelArr[$channelId]);
        $channelByLegacyType = $legacyChannelEnum ? $this->payChannelDep->getPreferredActiveByChannel($channelId) : null;

        if ($channelType > 0) {
            if ($channelById && (int) $channelById->channel === $channelType) {
                return $channelById;
            }

            $channelByType = $this->payChannelDep->getPreferredActiveByChannel($channelType);
            if ($channelByType) {
                return $channelByType;
            }

            return $channelById;
        }

        if ($legacyChannelEnum) {
            if ($channelById && (int) $channelById->channel === $channelId) {
                return $channelById;
            }

            if ($channelByLegacyType) {
                return $channelByLegacyType;
            }
        }

        return $channelById;
    }

    public function getSupportedMethods(object $channel): array
    {
        $extraConfig = is_array($channel->extra_config ?? null)
            ? $channel->extra_config
            : (empty($channel->extra_config) ? [] : (json_decode((string) $channel->extra_config, true) ?: []));

        $methods = is_array($extraConfig['supported_methods'] ?? null)
            ? $extraConfig['supported_methods']
            : [];

        $normalized = PayEnum::normalizeSupportedMethods((int) $channel->channel, $methods);

        return $normalized !== [] ? $normalized : PayEnum::getDefaultSupportedMethods((int) $channel->channel);
    }

    public function isPayMethodSupportedByChannel(object $channel, string $payMethod): bool
    {
        return in_array($payMethod, $this->getSupportedMethods($channel), true);
    }

    public function dispatchPayRequest(int $channel, int $channelId, string $payMethod, array $payload): mixed
    {
        $sdk = new PaySdk();

        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return match ($payMethod) {
                PayEnum::METHOD_APP => $sdk->wechatApp($channelId, $payload),
                PayEnum::METHOD_H5 => $sdk->wechatH5($channelId, $payload),
                PayEnum::METHOD_MINI => $sdk->wechatMini($channelId, $payload),
                PayEnum::METHOD_MP => $sdk->wechatMp($channelId, $payload),
                PayEnum::METHOD_SCAN, PayEnum::METHOD_WEB => $sdk->wechatScan($channelId, $payload),
                default => throw new RuntimeException('微信支付暂不支持当前支付方式'),
            };
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return match ($payMethod) {
                PayEnum::METHOD_APP => $sdk->alipayApp($channelId, $payload),
                PayEnum::METHOD_H5 => $sdk->alipayWap($channelId, $payload),
                PayEnum::METHOD_WEB => $sdk->alipayWeb($channelId, $payload),
                PayEnum::METHOD_SCAN => $sdk->alipayScan($channelId, $payload),
                PayEnum::METHOD_MINI => $sdk->alipayMini($channelId, $payload),
                default => throw new RuntimeException('支付宝暂不支持当前支付方式'),
            };
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    public function normalizePayResponse(mixed $response): array
    {
        return $this->preparePayResponse($response)['client'];
    }

    public function preparePayResponse(mixed $response): array
    {
        $raw = $this->normalizeRawResponse($response);

        return [
            'raw' => $raw,
            'client' => $this->normalizeClientPayload($raw),
        ];
    }

    public function downloadTradeBill(object $channel, string $date): array
    {
        return match ((int) $channel->channel) {
            PayEnum::CHANNEL_WECHAT => $this->downloadWechatTradeBill((int) $channel->id, $date),
            PayEnum::CHANNEL_ALIPAY => $this->downloadAlipayTradeBill((int) $channel->id, $date),
            default => throw new RuntimeException('当前渠道不支持下载账单'),
        };
    }

    private function downloadWechatTradeBill(int $channelId, string $date): array
    {
        PaySdk::initWechat($channelId);

        $provider = Pay::wechat();
        $query = $provider->pay([
            StartPlugin::class,
            GetTradePlugin::class,
            AddPayloadBodyPlugin::class,
            WechatAddPayloadSignaturePlugin::class,
            WechatAddRadarPlugin::class,
            WechatVerifySignaturePlugin::class,
            WechatResponsePlugin::class,
            ParserPlugin::class,
        ], [
            'bill_date' => $date,
            'bill_type' => 'ALL',
        ]);

        $queryData = $query instanceof Collection ? $query->toArray() : (array) $query;
        $downloadUrl = (string) ($queryData['download_url'] ?? '');
        if ($downloadUrl === '') {
            throw new RuntimeException('微信账单下载地址为空');
        }

        $downloadResponse = $provider->pay([
            StartPlugin::class,
            DownloadPlugin::class,
            AddPayloadBodyPlugin::class,
            WechatAddPayloadSignaturePlugin::class,
            WechatAddRadarPlugin::class,
            WechatVerifySignaturePlugin::class,
            WechatResponsePlugin::class,
        ], ['download_url' => $downloadUrl]);

        $content = $downloadResponse instanceof ResponseInterface
            ? (string) $downloadResponse->getBody()
            : (string) $downloadResponse;

        if ($content === '') {
            throw new RuntimeException('微信账单内容为空');
        }

        return [
            'filename' => "wechat_trade_bill_{$channelId}_{$date}.csv",
            'content' => $content,
            'download_url' => $downloadUrl,
        ];
    }

    private function downloadAlipayTradeBill(int $channelId, string $date): array
    {
        PaySdk::initAlipay($channelId);

        $provider = Pay::alipay();
        $response = $provider->pay([
            AlipayStartPlugin::class,
            QueryBillUrlPlugin::class,
            FormatPayloadBizContentPlugin::class,
            AlipayAddPayloadSignaturePlugin::class,
            AlipayAddRadarPlugin::class,
            AlipayVerifySignaturePlugin::class,
            AlipayResponsePlugin::class,
            ParserPlugin::class,
        ], [
            'bill_type' => 'trade',
            'bill_date' => $date,
        ]);

        $responseData = $response instanceof Collection ? $response->toArray() : (array) $response;
        $downloadUrl = (string) ($responseData['bill_download_url'] ?? '');
        if ($downloadUrl === '') {
            throw new RuntimeException('支付宝账单下载地址为空');
        }

        $content = $this->downloadRemoteFile($downloadUrl);
        if ($content === '') {
            throw new RuntimeException('支付宝账单内容为空');
        }

        return [
            'filename' => "alipay_trade_bill_{$channelId}_{$date}.csv",
            'content' => $content,
            'download_url' => $downloadUrl,
        ];
    }

    private function downloadRemoteFile(string $url): string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 30],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            throw new RuntimeException('下载远程账单失败');
        }

        return $content;
    }

    private function normalizeRawResponse(mixed $response): array
    {
        if ($response instanceof Collection) {
            return $response->toArray();
        }

        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof ResponseInterface) {
            return [
                'content' => $this->normalizeStreamContent($response->getBody()),
                'status_code' => $response->getStatusCode(),
                'reason_phrase' => $response->getReasonPhrase(),
                'headers' => $response->getHeaders(),
            ];
        }

        if ($response instanceof StreamInterface) {
            return ['content' => $this->normalizeStreamContent($response)];
        }

        if (is_object($response)) {
            if (method_exists($response, 'toArray')) {
                return $response->toArray();
            }

            if (method_exists($response, 'getContent')) {
                $content = $response->getContent();

                return is_array($content) ? $content : ['content' => (string) $content];
            }

            $json = json_encode($response, JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && $decoded !== []) {
                    return $decoded;
                }
            }

            if (method_exists($response, '__toString')) {
                return ['content' => (string) $response];
            }

            return ['content' => get_debug_type($response)];
        }

        return ['content' => (string) $response];
    }

    private function normalizeClientPayload(array $raw): array
    {
        $qrContent = $this->pickString($raw, [
            'qr_code',
            'qrCode',
            'code_url',
            'codeUrl',
            'qr_code_url',
            'qrCodeUrl',
            'pay_url',
            'payUrl',
        ]);

        if ($qrContent !== '') {
            return [
                'mode' => 'qrcode',
                'content' => $qrContent,
                'meta' => ['raw' => $raw],
            ];
        }

        $content = $this->pickString($raw, ['content', 'body', 'pay_body']);
        if ($content !== '') {
            if ($this->looksLikeHtml($content)) {
                return [
                    'mode' => 'external',
                    'content' => $content,
                    'meta' => [
                        'external_type' => 'html',
                        'raw' => $raw,
                    ],
                ];
            }

            if ($this->looksLikeUrl($content)) {
                return [
                    'mode' => 'external',
                    'content' => $content,
                    'meta' => [
                        'external_type' => 'link',
                        'raw' => $raw,
                    ],
                ];
            }

            return [
                'mode' => 'text',
                'content' => $content,
                'meta' => ['raw' => $raw],
            ];
        }

        $link = $this->pickString($raw, ['url', 'pay_link', 'link', 'h5_url', 'h5Url', 'mweb_url', 'mwebUrl']);
        if ($link !== '') {
            return [
                'mode' => 'external',
                'content' => $link,
                'meta' => [
                    'external_type' => 'link',
                    'raw' => $raw,
                ],
            ];
        }

        return [
            'mode' => 'text',
            'content' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '',
            'meta' => ['raw' => $raw],
        ];
    }

    private function normalizeStreamContent(StreamInterface $stream): string
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return (string) $stream;
    }

    private function pickString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function looksLikeHtml(string $value): bool
    {
        return preg_match('/<(form|html|body|script)\b|<!doctype/i', $value) === 1;
    }

    private function looksLikeUrl(string $value): bool
    {
        return preg_match('/^(https?:\/\/|alipays?:\/\/|weixin:\/\/|wxp:\/\/)/i', $value) === 1;
    }
}
