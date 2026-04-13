<?php

namespace tests\System;

use app\service\User\PermissionService;
use PHPUnit\Framework\TestCase;

class PermissionRouteContractTest extends TestCase
{
    public function testBuildRouteViewKeyNormalizesLeadingSlash(): void
    {
        self::assertSame('pay/channel', PermissionService::buildRouteViewKey('pay/channel'));
        self::assertSame('pay/channel', PermissionService::buildRouteViewKey('/pay/channel'));
        self::assertSame('pay/channel', PermissionService::buildRouteViewKey('///pay/channel'));
    }

    public function testBuildRouteRecordExposesViewKeyInsteadOfComponent(): void
    {
        $route = PermissionService::buildRouteRecord([
            'id' => 9,
            'path' => '/pay/channel',
            'component' => '/pay/channel',
        ]);

        self::assertSame('menu_9', $route['name']);
        self::assertSame('/pay/channel', $route['path']);
        self::assertSame('pay/channel', $route['view_key']);
        self::assertSame(['menuId' => '9'], $route['meta']);
        self::assertArrayNotHasKey('component', $route);
    }
}
