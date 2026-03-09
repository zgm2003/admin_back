<?php

namespace tests\Unit;

use app\dep\Permission\AuthPlatformDep;
use app\enum\CommonEnum;
use app\enum\PermissionEnum;
use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\Permission\RoleDep as RoleDepClass;
use app\dep\AddressDep;
use app\dep\Chat\ChatParticipantDep;
use app\dep\System\ExportTaskDep;
use app\dep\System\NotificationTaskDep;
use app\dep\System\SystemSettingDep;
use app\enum\NotificationEnum;
use app\dep\User\UserProfileDep;
use app\dep\User\UsersDep;
use app\exception\BusinessException;
use app\module\Permission\AuthPlatformModule;
use app\module\Permission\PermissionModule;
use app\module\Permission\RoleModule;
use app\module\System\ExportTaskModule;
use app\module\System\NotificationTaskModule;
use app\module\System\SystemSettingModule;
use app\module\User\UsersListModule;
use app\module\User\UsersQuickEntryModule;
use app\validate\Permission\PermissionValidate;
use app\validate\System\ExportTaskValidate;
use app\validate\User\UsersListValidate;
use app\validate\User\UsersQuickEntryValidate;
use app\service\AddressService;
use app\service\Permission\AuthPlatformService;
use app\service\System\SettingService;
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

        AuthPlatformService::flushMemCache();

        $addressServiceRef = new ReflectionClass(AddressService::class);
        $addressDepProp = $addressServiceRef->getProperty('dep');
        $addressDepProp->setAccessible(true);
        $addressDepProp->setValue(null, null);

        $settingServiceRef = new ReflectionClass(SettingService::class);
        $depProp = $settingServiceRef->getProperty('dep');
        $depProp->setAccessible(true);
        $depProp->setValue(null, null);
    }

    private function stubAllowedPlatforms(array $platforms = ['admin', 'app']): void
    {
        $serviceRef = new ReflectionClass(AuthPlatformService::class);

        foreach ([
            'memCodes' => $platforms,
            'memCodesAt' => time(),
        ] as $property => $value) {
            $prop = $serviceRef->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
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


    public function testPermissionContextTreatsZeroAsRootParentId(): void
    {
        $roleDep = $this->getMockBuilder(RoleDep::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
        $roleDep->method('find')->willReturn((object) ['permission_id' => [2]]);

        $permissionDep = $this->getMockBuilder(PermissionDep::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllPermissions'])
            ->getMock();
        $permissionDep->method('getAllPermissions')->willReturn([
            [
                'id' => 1,
                'name' => 'Root',
                'path' => '',
                'icon' => '',
                'component' => '',
                'platform' => 'admin',
                'type' => PermissionEnum::TYPE_DIR,
                'sort' => 1,
                'code' => '',
                'i18n_key' => 'root',
                'show_menu' => CommonEnum::YES,
                'parent_id' => 0,
            ],
            [
                'id' => 2,
                'name' => 'Child',
                'path' => '/child',
                'icon' => '',
                'component' => 'Main/child/index',
                'platform' => 'admin',
                'type' => PermissionEnum::TYPE_PAGE,
                'sort' => 1,
                'code' => '',
                'i18n_key' => 'child',
                'show_menu' => CommonEnum::YES,
                'parent_id' => 1,
            ],
        ]);

        $serviceRef = new ReflectionClass(PermissionService::class);
        $roleDepProp = $serviceRef->getProperty('roleDep');
        $roleDepProp->setAccessible(true);
        $roleDepProp->setValue(null, $roleDep);
        $permissionDepProp = $serviceRef->getProperty('permissionDep');
        $permissionDepProp->setAccessible(true);
        $permissionDepProp->setValue(null, $permissionDep);

        $this->stubAllowedPlatforms(['admin']);

        $result = PermissionService::buildPermissionContextByUser((object)['role_id' => 9], 'admin');

        $this->assertCount(1, $result['permissions']);
        $this->assertSame('1', $result['permissions'][0]['index']);
        $this->assertCount(1, $result['permissions'][0]['children']);
        $this->assertSame('2', $result['permissions'][0]['children'][0]['index']);
        $this->assertSame(['/child'], array_column($result['router'], 'path'));
    }

    public function testAppButtonAddStoresZeroAsRootParentId(): void
    {
        $this->stubAllowedPlatforms();

        $permissionDep = new class {
            public array $added = [];

            public function existsByPlatformCode(string $platform, string $code, ?int $excludeId = null): bool
            {
                return false;
            }

            public function add(array $data): int
            {
                $this->added[] = $data;
                return 1;
            }
        };

        $module = new class([
            'platform' => 'app',
            'type' => PermissionEnum::TYPE_BUTTON,
            'name' => 'App Button',
            'code' => 'app:test',
            'sort' => 5,
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

            protected function clearPermissionCache(): void
            {
            }
        };

        $result = $module->appButtonAdd(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([[
            'name' => 'App Button',
            'parent_id' => 0,
            'code' => 'app:test',
            'type' => PermissionEnum::TYPE_BUTTON,
            'platform' => 'app',
            'sort' => 5,
        ]], $permissionDep->added);
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

    public function testPermissionValidateRequiresDirectoryFields(): void
    {
        $this->stubAllowedPlatforms();
        $rules = PermissionValidate::add(PermissionEnum::TYPE_DIR);

        $this->assertTrue($rules['i18n_key']->validate('system.menu'));
        $this->assertFalse($rules['i18n_key']->validate(''));
        $this->assertTrue($rules['show_menu']->validate(CommonEnum::NO));
    }

    public function testPermissionValidateRequiresPageFields(): void
    {
        $this->stubAllowedPlatforms();
        $rules = PermissionValidate::edit(PermissionEnum::TYPE_PAGE);

        $this->assertTrue($rules['path']->validate('/system/role'));
        $this->assertFalse($rules['path']->validate(''));
        $this->assertTrue($rules['component']->validate('Main/system/role/index'));
        $this->assertFalse($rules['component']->validate(''));
    }

    public function testPermissionValidateRequiresButtonParentOnlyInAdminFlow(): void
    {
        $this->stubAllowedPlatforms();
        $adminRules = PermissionValidate::add(PermissionEnum::TYPE_BUTTON, true);
        $appRules = PermissionValidate::appButtonAdd();
        $baseRules = PermissionValidate::addBase();

        $this->assertFalse($adminRules['parent_id']->validate(null));
        $this->assertFalse($adminRules['parent_id']->validate(0));
        $this->assertTrue($adminRules['parent_id']->validate(8));
        $this->assertTrue($appRules['parent_id']->validate(null));
        $this->assertTrue($baseRules['parent_id']->validate(0));
        $this->assertFalse($baseRules['parent_id']->validate(-1));
        $this->assertTrue($appRules['code']->validate('user:create'));
        $this->assertFalse($appRules['code']->validate(''));
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

    public function testRoleAddAcceptsExpandedPermissionPayloadWhenStorageIsJson(): void
    {
        $roleDep = new class {
            public bool $addCalled = false;
            public array $received = [];

            public function existsByName(string $name, ?int $excludeId = null): bool
            {
                return false;
            }

            public function add(array $data): int
            {
                $this->addCalled = true;
                $this->received = $data;
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

        $result = $module->add(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertTrue($roleDep->addCalled);
        $this->assertSame([
            'name' => 'oversized-role',
            'permission_id' => range(1, 88),
        ], $roleDep->received);
    }

    public function testRoleEditAcceptsExpandedPermissionPayloadWhenStorageIsJson(): void
    {
        $roleDep = new class {
            public bool $updateCalled = false;
            public mixed $receivedId = null;
            public array $received = [];

            public function existsByName(string $name, ?int $excludeId = null): bool
            {
                return false;
            }

            public function update($id, array $data): int
            {
                $this->updateCalled = true;
                $this->receivedId = $id;
                $this->received = $data;
                return 1;
            }
        };

        $usersDep = new class {
            public function getIdsByRoleIds(array $roleIds)
            {
                return collect([]);
            }
        };

        $module = new class([
            'id' => 9,
            'name' => 'oversized-role',
            'permission_id' => range(1, 88),
        ], [RoleDepClass::class => $roleDep, UsersDep::class => $usersDep]) extends RoleModule {
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

        $this->stubAllowedPlatforms();
        $result = $module->edit(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertTrue($roleDep->updateCalled);
        $this->assertSame(9, $roleDep->receivedId);
        $this->assertSame([
            'name' => 'oversized-role',
            'permission_id' => range(1, 88),
        ], $roleDep->received);
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



    public function testAddressServiceBuildPathUsesZeroRootSentinel(): void
    {
        $dep = new class extends AddressDep {
            public function __construct()
            {
            }

            public function getAllMap(): array
            {
                return [
                    1 => ['id' => 1, 'name' => '???', 'parent_id' => 0],
                    2 => ['id' => 2, 'name' => '???', 'parent_id' => 1],
                    3 => ['id' => 3, 'name' => '???', 'parent_id' => 2],
                ];
            }
        };

        $serviceRef = new ReflectionClass(AddressService::class);
        $depProp = $serviceRef->getProperty('dep');
        $depProp->setAccessible(true);
        $depProp->setValue(null, $dep);

        $this->assertSame('???-???-???', AddressService::buildAddressPath(3));
    }

    public function testUsersQuickEntryAddValidationRejectsZeroPermissionId(): void
    {
        $rule = UsersQuickEntryValidate::add()['permission_id'];

        $this->assertTrue($rule->validate(8));
        $this->assertFalse($rule->validate(0));
    }

    public function testUsersQuickEntryAddRejectsMissingPermission(): void
    {
        $quickEntryDep = new class {
            public bool $addCalled = false;

            public function existsByUserAndPermission(int $userId, int $permissionId): bool
            {
                return false;
            }

            public function getMaxSort(int $userId): int
            {
                return 2;
            }

            public function add(array $data): int
            {
                $this->addCalled = true;
                return 3;
            }
        };

        $permissionDep = new class {
            public function get(int $id)
            {
                return null;
            }
        };

        $module = new class(['permission_id' => 99], [
            \app\dep\User\UsersQuickEntryDep::class => $quickEntryDep,
            PermissionDep::class => $permissionDep,
        ]) extends UsersQuickEntryModule {
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
            public int $userId = 1;
        };

        try {
            $module->add($request);
            $this->fail('Expected BusinessException was not thrown.');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('?????', $e->getMessage());
        }

        $this->assertFalse($quickEntryDep->addCalled);
    }

    public function testUsersListBatchEditValidationRequiresFieldSpecificPayload(): void
    {
        $sexRules = UsersListValidate::batchEdit('sex');
        $addressRules = UsersListValidate::batchEdit('address');
        $detailRules = UsersListValidate::batchEdit('detail_address');

        $this->assertTrue($sexRules['sex']->validate(0));
        $this->assertFalse($sexRules['sex']->validate(''));

        $this->assertTrue($addressRules['address']->validate(330106));
        $this->assertFalse($addressRules['address']->validate(0));

        $this->assertTrue($detailRules['detail_address']->validate('detail ok'));
        $this->assertFalse($detailRules['detail_address']->validate(''));
    }

    public function testSystemSettingEditUsesAffectedRowsContract(): void
    {
        $dep = new class {
            public array $calls = [];

            public function updateById(int $id, array $data): int
            {
                $this->calls[] = ['id' => $id, 'data' => $data];
                return 1;
            }
        };

        $module = new class([
            'id' => 9,
            'type' => 1,
            'value' => 'on',
            'remark' => 'demo',
        ], [SystemSettingDep::class => $dep]) extends SystemSettingModule {
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

        $result = $module->edit(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([[
            'id' => 9,
            'data' => [
                'setting_value' => 'on',
                'value_type' => 1,
                'remark' => 'demo',
            ],
        ]], $dep->calls);
    }

    public function testSystemSettingStatusThrowsWhenAffectedRowsIsZero(): void
    {
        $dep = new class {
            public function setStatusById(int $id, int $status): int
            {
                return 0;
            }
        };

        $module = new class([
            'id' => 9,
            'status' => 1,
        ], [SystemSettingDep::class => $dep]) extends SystemSettingModule {
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

        $this->expectException(BusinessException::class);
        $module->status(new \stdClass());
    }

    public function testSettingServiceSetUsesAffectedRowsContract(): void
    {
        $dep = new class extends SystemSettingDep {
            public array $calls = [];

            public function __construct()
            {
            }

            public function setRaw(string $key, string $value, int $type = 1, string $remark = ''): int
            {
                $this->calls[] = compact('key', 'value', 'type', 'remark');
                return 1;
            }
        };

        $serviceRef = new ReflectionClass(SettingService::class);
        $depProp = $serviceRef->getProperty('dep');
        $depProp->setAccessible(true);
        $depProp->setValue(null, $dep);

        $result = SettingService::set('demo.json', ['enabled' => true], 4, 'demo');

        $this->assertSame(1, $result);
        $this->assertSame([[
            'key' => 'demo.json',
            'value' => '{"enabled":true}',
            'type' => 4,
            'remark' => 'demo',
        ]], $dep->calls);
    }

    public function testNotificationTaskCancelThrowsWhenAffectedRowsIsZero(): void
    {
        $dep = new class {
            public function get(int $id)
            {
                return (object) ['id' => $id, 'status' => NotificationEnum::STATUS_PENDING];
            }

            public function cancel(int $id): int
            {
                return 0;
            }
        };

        $module = new class(['id' => 11], [NotificationTaskDep::class => $dep]) extends NotificationTaskModule {
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

        $this->expectException(BusinessException::class);
        $module->cancel(new \stdClass());
    }

    public function testChatParticipantAddBatchUsesProcessedCountContract(): void
    {
        $query = new class {
            public array $rows = [];

            public function insert(array $rows): bool
            {
                $this->rows = $rows;
                return true;
            }
        };

        $dep = new class($query) extends ChatParticipantDep {
            public function __construct(private readonly object $mockQuery)
            {
            }

            protected function createModel(): \support\Model
            {
                throw new \LogicException('not used');
            }

            protected function query()
            {
                return $this->mockQuery;
            }
        };

        $emptyResult = $dep->addBatch([]);
        $result = $dep->addBatch([
            ['conversation_id' => 1, 'user_id' => 10],
            ['conversation_id' => 1, 'user_id' => 11],
        ]);

        $this->assertSame(0, $emptyResult);
        $this->assertSame(2, $result);
        $this->assertCount(2, $query->rows);
    }

    public function testAuthPlatformEditUsesAffectedRowsContract(): void
    {
        $dep = new class {
            public array $calls = [];

            public function get(int $id)
            {
                return (object) ['code' => 'admin'];
            }

            public function updateById(int $id, array $data, ?string $oldCode = null): int
            {
                $this->calls[] = ['id' => $id, 'data' => $data, 'oldCode' => $oldCode];
                return 1;
            }
        };

        $module = new class([
            'id' => 3,
            'name' => '????',
            'login_types' => ['password', 'sms'],
            'access_ttl' => 3600,
            'refresh_ttl' => 86400,
            'bind_platform' => 1,
            'bind_device' => 0,
            'bind_ip' => 0,
            'single_session' => 1,
            'max_sessions' => 2,
            'allow_register' => 0,
        ], [AuthPlatformDep::class => $dep]) extends AuthPlatformModule {
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

        $result = $module->edit(new \stdClass());

        $this->assertSame([[], 0, 'success'], $result);
        $this->assertSame([[
            'id' => 3,
            'data' => [
                'name' => '????',
                'login_types' => json_encode(['password', 'sms']),
                'access_ttl' => 3600,
                'refresh_ttl' => 86400,
                'bind_platform' => 1,
                'bind_device' => 0,
                'bind_ip' => 0,
                'single_session' => 1,
                'max_sessions' => 2,
                'allow_register' => 0,
            ],
            'oldCode' => 'admin',
        ]], $dep->calls);
    }

    public function testAuthPlatformStatusThrowsWhenAffectedRowsIsZero(): void
    {
        $dep = new class {
            public function setStatusById(int $id, int $status): int
            {
                return 0;
            }
        };

        $module = new class([
            'id' => 3,
            'status' => 2,
        ], [AuthPlatformDep::class => $dep]) extends AuthPlatformModule {
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

        $this->expectException(BusinessException::class);
        $module->status(new \stdClass());
    }
}
