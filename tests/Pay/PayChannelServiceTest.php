<?php

namespace tests\Pay;

use app\service\Pay\PayChannelService;
use PHPUnit\Framework\TestCase;

class PayChannelServiceTest extends TestCase
{
    public function testNormalizePayResponseBuildsQrcodePayload(): void
    {
        $service = new PayChannelService();
        $result = $service->normalizePayResponse([
            'code_url' => 'weixin://wxpay/mock-qrcode',
        ]);

        self::assertSame('qrcode', $result['mode']);
        self::assertSame('weixin://wxpay/mock-qrcode', $result['content']);
        self::assertIsArray($result['meta']);
    }

    public function testNormalizePayResponseBuildsExternalHtmlPayload(): void
    {
        $service = new PayChannelService();
        $result = $service->normalizePayResponse([
            'content' => '<form action="https://example.com/pay"></form>',
        ]);

        self::assertSame('external', $result['mode']);
        self::assertSame('<form action="https://example.com/pay"></form>', $result['content']);
        self::assertSame('html', $result['meta']['external_type']);
    }
}
