<?php

namespace tests\Unit;

use app\dep\BaseDep;
use app\dep\Permission\PermissionDep;
use app\dep\Permission\RoleDep;
use app\dep\Permission\RolePermissionDep;
use app\dep\User\UserProfileDep;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use support\Model;

class SchemaRefactorGuardsTest extends TestCase
{
    private static bool $dbInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$dbInitialized) {
            $this->initDatabase();
            self::$dbInitialized = true;
        }
    }

    private function initDatabase(): void
    {
        // Load .env file
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }

        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'    => getenv('DB_CONNECTION') ?: 'mysql',
            'host'      => getenv('DB_HOST') ?: '127.0.0.1',
            'port'      => (int)(getenv('DB_PORT') ?: 3306),
            'database'  => getenv('DB_DATABASE') ?: 'admin',
            'username'  => getenv('DB_USERNAME') ?: 'root',
            'password'  => getenv('DB_PASSWORD') ?: '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

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

    public function testRolePermissionDepFiltersToActiveLeafIdsOnly(): void
    {
        $permissionDep = new class([
            ['id' => 1, 'parent_id' => 0],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 2],
            ['id' => 4, 'parent_id' => 0],
            ['id' => 5, 'parent_id' => 4],
            ['id' => 6, 'parent_id' => 0],
        ]) extends PermissionDep {
            public function __construct(private readonly array $items)
            {
            }

            protected function createModel(): Model
            {
                return new FakeSchemaGuardModel('id');
            }

            public function allActive()
            {
                return collect(array_map(static fn(array $item) => (object)$item, $this->items));
            }
        };

        $dep = new class($permissionDep) extends RolePermissionDep {
            public function __construct(private readonly PermissionDep $mockPermissionDep)
            {
                parent::__construct();
            }

            protected function createModel(): Model
            {
                return new FakeSchemaGuardModel('id');
            }

            protected function permissionDep(): PermissionDep
            {
                return $this->mockPermissionDep;
            }

            public function exposeFilterActiveLeafPermissionIds(array $permissionIds): array
            {
                return $this->filterActiveLeafPermissionIds($permissionIds);
            }
        };

        $this->assertSame([3, 5, 6], $dep->exposeFilterActiveLeafPermissionIds([
            1, '2', 3, 4, 5, 6, 0, -1, 999, 3,
        ]));
    }

    public function test_all_tables_use_0900_ai_ci(): void
    {
        $result = Capsule::select("
            SELECT TABLE_NAME, TABLE_COLLATION
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = 'admin'
              AND TABLE_TYPE = 'BASE TABLE'
              AND TABLE_COLLATION != 'utf8mb4_0900_ai_ci'
        ");
        $this->assertEmpty($result, 'Tables with non-0900_ai_ci collation: ' . json_encode($result));
    }

    public function test_ai_runs_index_count(): void
    {
        $count = Capsule::selectOne("
            SELECT COUNT(DISTINCT INDEX_NAME) AS cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA='admin' AND TABLE_NAME='ai_runs' AND INDEX_NAME != 'PRIMARY'
        ")->cnt;
        $this->assertEquals(5, $count, 'ai_runs should have exactly 5 non-primary indexes');
    }

    public function test_no_signed_int_except_goods_platform_and_test_age(): void
    {
        $result = Capsule::select("
            SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='admin'
              AND DATA_TYPE = 'int'
              AND COLUMN_TYPE NOT LIKE '%unsigned%'
              AND NOT (TABLE_NAME='goods' AND COLUMN_NAME='platform')
              AND NOT (TABLE_NAME='test' AND COLUMN_NAME='age')
        ");
        $this->assertEmpty($result, 'Signed int columns remaining: ' . json_encode($result));
    }

    public function test_json_columns_use_native_json_type(): void
    {
        $columns = [
            ['goods', 'image_list'],
            ['goods', 'image_list_success'],
            ['upload_rule', 'image_exts'],
            ['upload_rule', 'file_exts'],
        ];
        foreach ($columns as [$table, $col]) {
            $type = Capsule::selectOne("
                SELECT DATA_TYPE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='admin' AND TABLE_NAME=? AND COLUMN_NAME=?
            ", [$table, $col])->DATA_TYPE;
            $this->assertEquals('json', $type, "$table.$col should be json, got $type");
        }
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
