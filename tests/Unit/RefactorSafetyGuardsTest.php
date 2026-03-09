<?php

namespace tests\Unit;

use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\Permission\RoleDep as RoleDepClass;
use app\dep\System\ExportTaskDep;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\exception\BusinessException;
use app\module\Permission\PermissionModule;
use app\module\Permission\RoleModule;
use app\module\System\ExportTaskModule;
use app\module\User\UsersListModule;
use app\validate\System\ExportTaskValidate;
use app\service\User\PermissionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RefactorSafetyGuardsTest extends TestCase
{
    protected function tearDown(): void
    {
        $serviceRef = new ReflectionClass(PermissionService::class);

        foreach (['roleDep', 'permissionDep'] as $property) {
            $prop = $serviceRef->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    public function testRoleDeleteRejectsRolesStillBoundToUsers(): void
    {
        $roleDep = new class {
            public bool $deleteCalled = false;

            public function getMapActive(array $ids)
            {
                return collect([(object)['id' => $ids[0]]]);
            }

            public function hasDefaultIn(array $ids): bool
            {
                return false;
            }

            public function delete($ids): int
            {
                $this->deleteCalled = true;
                return 1;
            }
        };

        $usersDep = new class {
            public function getIdsByRoleIds(array $roleIds)
            {
                return collect([1001]);
            }
        };

        $module = new class(['id' => 7], [RoleDepClass::class => $roleDep, UsersDep::class => $usersDep]) extends RoleModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class] ?? array_values($this->deps)[0] ?? null;
            }
        };

        try {
            $module->del(new \stdClass());
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('用户', $e->getMessage());
        }

        $this->assertFalse($roleDep->deleteCalled);
    }

    public function testPermissionContextReturnsEmptyWhenRoleMissing(): void
    {
        $roleDep = $this->getMockBuilder(RoleDep::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
        $roleDep->method('find')->willReturn(null);

        $serviceRef = new ReflectionClass(PermissionService::class);
        $roleDepProp = $serviceRef->getProperty('roleDep');
        $roleDepProp->setAccessible(true);
        $roleDepProp->setValue(null, $roleDep);

        $result = PermissionService::buildPermissionContextByUser((object)['role_id' => 999], 'admin');

        $this->assertSame([
            'permissions' => [],
            'router' => [],
            'buttonCodes' => [],
        ], $result);
    }

    public function testUsersBatchEditUsesBatchProfileUpdate(): void
    {
        $profileDep = new class {
            public array $called = [];

            public function updateByUserId(int $userId, array $data): int
            {
                throw new \LogicException('single-user update should not be used in batchEdit');
            }

            public function updateByUserIds(array $userIds, array $data): int
            {
                $this->called = ['ids' => $userIds, 'data' => $data];
                return count($userIds);
            }
        };

        $module = new class([
            'ids' => [1, 2],
            'field' => 'address',
            'address' => 330106,
        ], [UserProfileDep::class => $profileDep]) extends UsersListModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class] ?? array_values($this->deps)[0] ?? null;
            }
        };

        $result = $module->batchEdit(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([
            'ids' => [1, 2],
            'data' => ['address_id' => 330106],
        ], $profileDep->called);
    }

    public function testPermissionDeleteRejectsWhenSelectedIdsMissChildren(): void
    {
        $permissionDep = new class {
            public bool $deleteCalled = false;

            public function hasChildrenIn(array $ids): bool
            {
                return true;
            }

            public function hasChildrenOutsideIds(array $ids): bool
            {
                return true;
            }

            public function delete($ids): int
            {
                $this->deleteCalled = true;
                return 1;
            }
        };

        $module = new class(['id' => [5]], [PermissionDep::class => $permissionDep]) extends PermissionModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class] ?? array_values($this->deps)[0] ?? null;
            }

            protected function clearPermissionCache(): void
            {
            }

            protected function permissionDep()
            {
                return array_values($this->deps)[0];
            }
        };

        try {
            $module->del(new \stdClass());
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertSame('存在子节点未被勾选，不能删除', $e->getMessage());
        }

        $this->assertFalse($permissionDep->deleteCalled);
    }

    public function testPermissionDeleteAllowsParentNodesWhenChildrenAlsoSelected(): void
    {
        $permissionDep = new class {
            public bool $deleteCalled = false;
            public array $deletedIds = [];

            public function hasChildrenIn(array $ids): bool
            {
                return true;
            }

            public function hasChildrenOutsideIds(array $ids): bool
            {
                return false;
            }

            public function delete($ids): int
            {
                $this->deleteCalled = true;
                $this->deletedIds = $ids;
                return count($ids);
            }
        };

        $module = new class(['id' => [5, 6, 7]], [PermissionDep::class => $permissionDep]) extends PermissionModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class] ?? array_values($this->deps)[0] ?? null;
            }

            protected function clearPermissionCache(): void
            {
            }

            protected function permissionDep()
            {
                return array_values($this->deps)[0];
            }
        };

        $result = $module->del(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertTrue($permissionDep->deleteCalled);
        $this->assertSame([5, 6, 7], $permissionDep->deletedIds);
    }

    public function testPermissionBatchEditRejectsDescriptionFieldWithoutSchemaSupport(): void
    {
        $permissionDep = new class {
            public bool $updateCalled = false;

            public function update($ids, array $data): int
            {
                $this->updateCalled = true;
                return 1;
            }
        };

        $module = new class([
            'ids' => [5],
            'field' => 'description',
            'description' => 'legacy field',
        ], [PermissionDep::class => $permissionDep]) extends PermissionModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class];
            }
        };

        try {
            $module->batchEdit(new \stdClass());
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('不支持', $e->getMessage());
        }

        $this->assertFalse($permissionDep->updateCalled);
    }

    public function testRoleAddRejectsOversizedPermissionPayload(): void
    {
        $roleDep = new class {
            public bool $addCalled = false;

            public function existsByName(string $name, ?int $excludeId = null): bool
            {
                return false;
            }

            public function add(array $data): int
            {
                $this->addCalled = true;
                return 1;
            }
        };

        $module = new class([
            'name' => 'oversized-role',
            'permission_id' => range(1, 88),
        ], [RoleDepClass::class => $roleDep]) extends RoleModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class];
            }
        };

        try {
            $module->add(new \stdClass());
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('permission_id', $e->getMessage());
        }

        $this->assertFalse($roleDep->addCalled);
    }

    public function testRoleEditRejectsOversizedPermissionPayload(): void
    {
        $roleDep = new class {
            public bool $updateCalled = false;

            public function existsByName(string $name, ?int $excludeId = null): bool
            {
                return false;
            }

            public function update($id, array $data): int
            {
                $this->updateCalled = true;
                return 1;
            }
        };

        $module = new class([
            'id' => 9,
            'name' => 'oversized-role',
            'permission_id' => range(1, 88),
        ], [RoleDepClass::class => $roleDep]) extends RoleModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class];
            }
        };

        try {
            $module->edit(new \stdClass());
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('permission_id', $e->getMessage());
        }

        $this->assertFalse($roleDep->updateCalled);
    }

    public function testExportTaskDeleteUsesBatchDeleteForSingleId(): void
    {
        $exportTaskDep = new class {
            public array $calls = [];

            public function get($id)
            {
                throw new \LogicException('single-record lookup should not be used in export task delete');
            }

            public function delete($id): int
            {
                throw new \LogicException('single delete should not be used in export task delete');
            }

            public function batchDeleteByUser(array $ids, int $userId): int
            {
                $this->calls[] = ['ids' => $ids, 'userId' => $userId];
                return count($ids);
            }
        };

        $module = new class(['id' => 7], [ExportTaskDep::class => $exportTaskDep]) extends ExportTaskModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class];
            }
        };

        $request = new class {
            public int $userId = 23;
        };

        $result = $module->del($request);

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([
            ['ids' => [7], 'userId' => 23],
        ], $exportTaskDep->calls);
    }

    public function testExportTaskDeleteUsesBatchDeleteForMultipleIds(): void
    {
        $exportTaskDep = new class {
            public array $calls = [];

            public function batchDeleteByUser(array $ids, int $userId): int
            {
                $this->calls[] = ['ids' => $ids, 'userId' => $userId];
                return count($ids);
            }
        };

        $module = new class(['id' => [7, 8, 9]], [ExportTaskDep::class => $exportTaskDep]) extends ExportTaskModule {
            public function __construct(private array $validated, private array $deps)
            {
            }

            protected function validate($request, array $rules, ?array $input = null): array
            {
                return $this->validated;
            }

            protected function dep(string $class)
            {
                return $this->deps[$class];
            }
        };

        $request = new class {
            public int $userId = 24;
        };

        $result = $module->del($request);

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([
            ['ids' => [7, 8, 9], 'userId' => 24],
        ], $exportTaskDep->calls);
    }

    public function testExportTaskDeleteValidationAcceptsScalarAndArrayIds(): void
    {
        $rule = ExportTaskValidate::del()['id'];

        $this->assertTrue($rule->validate(7));
        $this->assertTrue($rule->validate([7, 8]));
        $this->assertFalse($rule->validate([]));
    }

}
