<?php

namespace tests\Unit;

use app\lib\Ai\CodeGenParser;
use PHPUnit\Framework\TestCase;

class CodeGenParserTest extends TestCase
{
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->deleteDirectory($dir);
        }
        $this->tempDirs = [];
    }

    public function testPatchRoutesInsertSuccess(): void
    {
        $baseRoutes = $this->baseRoutesContent();
        [$parser, $events, $routesPath] = $this->createParser($baseRoutes);

        $parser->feed(<<<'TXT'
```php:PATCH_ROUTES:routes/admin.php
// 反馈管理
Route::post('/Feedback/list', [\app\controller\User\FeedbackController::class, 'list']);
Route::post('/Feedback/add', [\app\controller\User\FeedbackController::class, 'add']);
```
TXT);
        $parser->flush();

        $content = file_get_contents($routesPath);
        $this->assertStringContainsString("Route::post('/Feedback/list'", $content);
        $this->assertStringContainsString("Route::post('/Feedback/add'", $content);
        $this->assertTrue(strpos($content, "Route::post('/Feedback/list'") < strpos($content, '})->middleware(['));

        $stats = $parser->getStats();
        $this->assertSame(2, $stats['routes_patch_added']);
        $this->assertSame(0, $stats['routes_patch_skipped']);
        $this->assertSame(0, $stats['routes_patch_failed']);

        $event = $this->findLastEvent($events->getArrayCopy(), 'routes_patched');
        $this->assertNotNull($event);
        $this->assertTrue($event['payload']['success']);
        $this->assertSame(2, $event['payload']['added']);
    }

    public function testPatchRoutesDuplicateWillSkip(): void
    {
        $baseRoutes = str_replace(
            "})->middleware([",
            "    Route::post('/Feedback/list', [\\app\\controller\\User\\FeedbackController::class, 'list']);\n})->middleware([",
            $this->baseRoutesContent()
        );
        [$parser, $events, $routesPath] = $this->createParser($baseRoutes);
        $before = file_get_contents($routesPath);

        $parser->feed(<<<'TXT'
```php:PATCH_ROUTES:routes/admin.php
Route::post('/Feedback/list', [\app\controller\User\FeedbackController::class, 'list']);
```
TXT);
        $parser->flush();

        $after = file_get_contents($routesPath);
        $this->assertSame($before, $after);

        $stats = $parser->getStats();
        $this->assertSame(0, $stats['routes_patch_added']);
        $this->assertSame(1, $stats['routes_patch_skipped']);
        $this->assertSame(0, $stats['routes_patch_failed']);

        $event = $this->findLastEvent($events->getArrayCopy(), 'routes_patched');
        $this->assertNotNull($event);
        $this->assertTrue($event['payload']['success']);
        $this->assertSame(1, $event['payload']['skipped']);
    }

    public function testPatchRoutesRejectInvalidStatement(): void
    {
        [$parser, $events, $routesPath] = $this->createParser($this->baseRoutesContent());
        $before = file_get_contents($routesPath);

        $parser->feed(<<<'TXT'
```php:PATCH_ROUTES:routes/admin.php
Route::get('/Feedback/list', [\app\controller\User\FeedbackController::class, 'list']);
```
TXT);
        $parser->flush();

        $after = file_get_contents($routesPath);
        $this->assertSame($before, $after);

        $stats = $parser->getStats();
        $this->assertSame(0, $stats['routes_patch_added']);
        $this->assertSame(0, $stats['routes_patch_skipped']);
        $this->assertSame(1, $stats['routes_patch_failed']);

        $event = $this->findLastEvent($events->getArrayCopy(), 'routes_patched');
        $this->assertNotNull($event);
        $this->assertFalse($event['payload']['success']);
        $this->assertStringContainsString('Route::post', $event['payload']['error']);
    }

    public function testPatchRoutesFailsWhenAnchorMissing(): void
    {
        $routes = <<<'PHP'
<?php
use Webman\Route;
Route::group('/api/admin', function () {
    Route::post('/test', [\app\controller\TestController::class, 'test']);
});
PHP;
        [$parser, $events, $routesPath] = $this->createParser($routes);
        $before = file_get_contents($routesPath);

        $parser->feed(<<<'TXT'
```php:PATCH_ROUTES:routes/admin.php
Route::post('/Feedback/list', [\app\controller\User\FeedbackController::class, 'list']);
```
TXT);
        $parser->flush();

        $after = file_get_contents($routesPath);
        $this->assertSame($before, $after);

        $stats = $parser->getStats();
        $this->assertSame(1, $stats['routes_patch_failed']);

        $event = $this->findLastEvent($events->getArrayCopy(), 'routes_patched');
        $this->assertNotNull($event);
        $this->assertFalse($event['payload']['success']);
        $this->assertStringContainsString('插入锚点', $event['payload']['error']);
    }

    public function testPatchRoutesLintFailureWillRollback(): void
    {
        [$parser, $events, $routesPath] = $this->createParser($this->baseRoutesContent());
        $before = file_get_contents($routesPath);

        $parser->feed(<<<'TXT'
```php:PATCH_ROUTES:routes/admin.php
Route::post('/Bad, [\app\controller\User\FeedbackController::class, 'list']);
```
TXT);
        $parser->flush();

        $after = file_get_contents($routesPath);
        $this->assertSame($before, $after);

        $stats = $parser->getStats();
        $this->assertSame(1, $stats['routes_patch_failed']);

        $event = $this->findLastEvent($events->getArrayCopy(), 'routes_patched');
        $this->assertNotNull($event);
        $this->assertFalse($event['payload']['success']);
        $this->assertStringContainsString('语法检查失败', $event['payload']['error']);
    }

    private function baseRoutesContent(): string
    {
        return <<<'PHP'
<?php
use Webman\Route;
Route::group('/api/admin', function () {
    Route::post('/test', [\app\controller\TestController::class, 'test']);
})->middleware([
    \app\middleware\AdminAuth::class,
]);
PHP;
    }

    /**
     * @return array{0:CodeGenParser,1:\ArrayObject,2:string}
     */
    private function createParser(string $routesContent): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'codegen_parser_' . uniqid('', true);
        $backend = $root . DIRECTORY_SEPARATOR . 'backend';
        $frontend = $root . DIRECTORY_SEPARATOR . 'frontend';
        $routesDir = $backend . DIRECTORY_SEPARATOR . 'routes';
        mkdir($routesDir, 0777, true);
        mkdir($frontend, 0777, true);

        $routesPath = $routesDir . DIRECTORY_SEPARATOR . 'admin.php';
        file_put_contents($routesPath, $routesContent);

        $this->tempDirs[] = $root;

        $events = new \ArrayObject();
        $parser = new CodeGenParser(
            function (string $type, array $payload) use ($events) {
                $events->append(['type' => $type, 'payload' => $payload]);
            },
            true,
            $backend,
            $frontend
        );

        return [$parser, $events, $routesPath];
    }

    private function findLastEvent(array $events, string $type): ?array
    {
        for ($i = count($events) - 1; $i >= 0; $i--) {
            if (($events[$i]['type'] ?? '') === $type) {
                return $events[$i];
            }
        }
        return null;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }
            @unlink($path);
        }
        @rmdir($dir);
    }
}
