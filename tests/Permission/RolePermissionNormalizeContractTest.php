<?php

namespace tests\Permission;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RolePermissionDep;
use app\enum\PermissionEnum;
use PHPUnit\Framework\TestCase;

class RolePermissionNormalizeContractTest extends TestCase
{
    public function testButtonsImplyParentPagesButDirectoriesAreNotAssignable(): void
    {
        $dep = new TestableRolePermissionDep(new FakePermissionDepForRoleNormalize([
            ['id' => 1, 'parent_id' => 0, 'type' => PermissionEnum::TYPE_DIR],
            ['id' => 2, 'parent_id' => 1, 'type' => PermissionEnum::TYPE_PAGE],
            ['id' => 3, 'parent_id' => 2, 'type' => PermissionEnum::TYPE_BUTTON],
            ['id' => 4, 'parent_id' => 0, 'type' => PermissionEnum::TYPE_BUTTON],
            ['id' => 5, 'parent_id' => 1, 'type' => PermissionEnum::TYPE_BUTTON],
            ['id' => 6, 'parent_id' => 0, 'type' => PermissionEnum::TYPE_PAGE],
        ]));

        self::assertSame(
            [2, 3, 4, 5, 6],
            $dep->normalizeAssignablePermissionIdsWithPageParents([1, 3, 4, 5, 6, 999])
        );
    }
}

class FakePermissionDepForRoleNormalize extends PermissionDep
{
    public function __construct(private readonly array $permissions)
    {
        parent::__construct();
    }

    public function getAllPermissions(): array
    {
        return $this->permissions;
    }
}

class TestableRolePermissionDep extends RolePermissionDep
{
    public function __construct(private readonly PermissionDep $testPermissionDep)
    {
        parent::__construct();
    }

    protected function permissionDep(): PermissionDep
    {
        return $this->testPermissionDep;
    }
}
