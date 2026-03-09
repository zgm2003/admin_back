<?php

namespace tests\Unit;

use app\dep\BaseDep;
use app\dep\Permission\RoleDep;
use app\dep\User\UserProfileDep;
use PHPUnit\Framework\TestCase;
use support\Model;

class SchemaRefactorGuardsTest extends TestCase
{
    public function testBaseDepAddUsesProvidedPrimaryKeyForCustomKeyModels(): void
    {
        $model = new FakeSchemaGuardModel('user_id');
        $dep = new FakeSchemaGuardDep($model);

        $result = $dep->add(['user_id' => 123, 'avatar' => 'demo.png']);

        $this->assertSame(123, $result);
        $this->assertSame(['user_id' => 123, 'avatar' => 'demo.png'], $model->insertPayload);
        $this->assertNull($model->insertGetIdPayload);
    }

    public function testBaseDepFindUsesModelPrimaryKey(): void
    {
        $model = new FakeSchemaGuardModel('user_id');
        $expected = (object) ['user_id' => 88];
        $model->firstResult = $expected;
        $dep = new FakeSchemaGuardDep($model);

        $result = $dep->find(88);

        $this->assertSame($expected, $result);
        $this->assertSame([
            ['column' => 'user_id', 'operator' => '=', 'value' => 88],
        ], $model->whereCalls);
    }

    public function testBaseDepIncrementUsesModelPrimaryKey(): void
    {
        $model = new FakeSchemaGuardModel('user_id');
        $model->incrementResult = 1;
        $dep = new FakeSchemaGuardDep($model);

        $result = $dep->increment(12, 'stock', 2);

        $this->assertSame(1, $result);
        $this->assertSame([
            ['column' => 'user_id', 'operator' => '=', 'value' => 12],
        ], $model->whereCalls);
        $this->assertSame(['column' => 'stock', 'amount' => 2], $model->incrementCall);
    }

    public function testBaseDepDecrementUsesModelPrimaryKey(): void
    {
        $model = new FakeSchemaGuardModel('user_id');
        $model->decrementResult = 1;
        $dep = new FakeSchemaGuardDep($model);

        $result = $dep->decrement(12, 'stock', 2);

        $this->assertSame(1, $result);
        $this->assertSame([
            ['column' => 'user_id', 'operator' => '=', 'value' => 12],
            ['column' => 'stock', 'operator' => '>=', 'value' => 2],
        ], $model->whereCalls);
        $this->assertSame(['column' => 'stock', 'amount' => 2], $model->decrementCall);
    }

    public function testUserProfileDeleteUsesUserIdPrimaryKey(): void
    {
        $model = new FakeSchemaGuardModel('user_id');
        $model->updateResult = 2;

        $dep = new class($model) extends UserProfileDep {
            public function __construct(private readonly FakeSchemaGuardModel $mockModel)
            {
                $this->model = $mockModel;
            }

            protected function createModel(): Model
            {
                return $this->mockModel;
            }
        };

        $result = $dep->delete([7, 9]);

        $this->assertSame(2, $result);
        $this->assertSame(['column' => 'user_id', 'values' => [7, 9]], $model->whereInCall);
        $this->assertSame([
            ['column' => 'is_del', 'operator' => '=', 'value' => 2],
        ], $model->whereCalls);
        $this->assertSame(['is_del' => 1], $model->updatePayload);
    }


    public function testRoleDepAddDropsPermissionIdsAfterPivotRefactor(): void
    {
        $model = new FakeSchemaGuardModel('id');

        $dep = new class($model) extends RoleDep {
            public function __construct(private readonly FakeSchemaGuardModel $mockModel)
            {
                $this->model = $mockModel;
            }

            protected function createModel(): Model
            {
                return $this->mockModel;
            }
        };

        $result = $dep->add([
            'name' => 'role-A',
            'permission_id' => [3, '2', 0, -1, 3],
        ]);

        $this->assertSame(456, $result);
        $this->assertSame([
            'name' => 'role-A',
        ], $model->insertGetIdPayload);
    }

    public function testRoleDepUpdateDropsPermissionIdsAfterPivotRefactor(): void
    {
        $model = new FakeSchemaGuardModel('id');
        $model->updateResult = 1;

        $dep = new class($model) extends RoleDep {
            public function __construct(private readonly FakeSchemaGuardModel $mockModel)
            {
                $this->model = $mockModel;
            }

            protected function createModel(): Model
            {
                return $this->mockModel;
            }
        };

        $result = $dep->update(8, [
            'name' => 'role-B',
            'permission_id' => [5, '4', 0, 5],
        ]);

        $this->assertSame(1, $result);
        $this->assertSame(['column' => 'id', 'values' => [8]], $model->whereInCall);
        $this->assertSame([
            'name' => 'role-B',
        ], $model->updatePayload);
    }
}

class FakeSchemaGuardDep extends BaseDep
{
    public function __construct(private readonly FakeSchemaGuardModel $mockModel)
    {
        $this->model = $mockModel;
    }

    protected function createModel(): Model
    {
        return $this->mockModel;
    }
}

class FakeSchemaGuardModel extends Model
{
    public ?array $insertPayload = null;
    public ?array $insertGetIdPayload = null;
    public array $whereCalls = [];
    public ?array $whereInCall = null;
    public mixed $firstResult = null;
    public int $deleteResult = 0;
    public int $updateResult = 0;
    public int $incrementResult = 0;
    public int $decrementResult = 0;
    public ?array $incrementCall = null;
    public ?array $decrementCall = null;
    public ?array $updatePayload = null;

    public function __construct(private readonly string $fakeKeyName)
    {
    }

    public function getKeyName()
    {
        return $this->fakeKeyName;
    }

    public function insert(array $values)
    {
        $this->insertPayload = $values;
        return true;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->insertGetIdPayload = $values;
        return 456;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->whereCalls[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $this->whereInCall = [
            'column' => $column,
            'values' => array_values($values),
        ];

        return $this;
    }

    public function first($columns = ['*'])
    {
        return $this->firstResult;
    }

    public function delete()
    {
        return $this->deleteResult;
    }

    public function update(array $values = [], array $options = [])
    {
        $this->updatePayload = $values;
        return $this->updateResult;
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->incrementCall = [
            'column' => $column,
            'amount' => $amount,
        ];

        return $this->incrementResult;
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->decrementCall = [
            'column' => $column,
            'amount' => $amount,
        ];

        return $this->decrementResult;
    }
}
