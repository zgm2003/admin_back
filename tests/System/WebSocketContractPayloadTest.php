<?php

namespace tests\System;

use app\module\System\WebSocketModule;
use PHPUnit\Framework\TestCase;
use plugin\webman\gateway\Events;

class WebSocketContractPayloadTest extends TestCase
{
    public function testInitPayloadWrapsClientIdInsideData(): void
    {
        $payload = Events::buildInitPayload('client-1');

        self::assertSame('init', $payload['type']);
        self::assertSame(['client_id' => 'client-1'], $payload['data']);
        self::assertArrayNotHasKey('client_id', $payload);
    }

    public function testBindSuccessPayloadUsesStandardEnvelope(): void
    {
        $payload = WebSocketModule::buildEventPayload('bind_success', [
            'uid' => 7,
            'platform' => 'admin',
        ]);

        self::assertSame('bind_success', $payload['type']);
        self::assertSame([
            'uid' => 7,
            'platform' => 'admin',
        ], $payload['data']);
    }
}
