<?php

namespace tests\Pay;

use app\enum\PayEnum;
use app\service\Pay\PayDomainService;
use PHPUnit\Framework\TestCase;

class PayDomainServiceTest extends TestCase
{
    public function testResolveFulfillmentActionType(): void
    {
        $service = new PayDomainService();

        self::assertSame(PayEnum::FULFILL_ACTION_RECHARGE, $service->resolveFulfillmentActionType(PayEnum::TYPE_RECHARGE));
        self::assertSame(PayEnum::FULFILL_ACTION_CONSUME, $service->resolveFulfillmentActionType(PayEnum::TYPE_CONSUME));
        self::assertSame(PayEnum::FULFILL_ACTION_GOODS, $service->resolveFulfillmentActionType(PayEnum::TYPE_GOODS));
    }
}
