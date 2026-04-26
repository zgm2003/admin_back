<?php

namespace tests\System;

use PHPUnit\Framework\TestCase;

class PermissionMutationGuardContractTest extends TestCase
{
    public function testPermissionModuleGuardsParentTypeAndCircularReference(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Permission/PermissionModule.php');

        self::assertNotFalse($content);
        self::assertStringContainsString('assertValidParentAssignment', $content);
        self::assertStringContainsString('节点不能选择自己作为父级', $content);
        self::assertStringContainsString('节点不能挂到自己的后代下面', $content);
        self::assertStringContainsString('页面类型的父节点只能是目录或根节点', $content);
        self::assertStringContainsString('按钮类型的父节点只能是页面', $content);
        self::assertStringContainsString('目录类型的父节点只能是目录或根节点', $content);
        self::assertStringContainsString('$requiresButtonParent = (int)$param[\'type\'] === PermissionEnum::TYPE_BUTTON && $param[\'platform\'] === \'admin\';', $content);
        self::assertStringContainsString('self::throwIf($type === PermissionEnum::TYPE_BUTTON && $platform === \'admin\', \'按钮类型的父节点只能是页面\');', $content);
    }
}
