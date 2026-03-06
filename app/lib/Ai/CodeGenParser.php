<?php

namespace app\lib\Ai;

use app\dep\Ai\GenDep;
use support\Db;
use support\Log;

/**
 * 代码生成输出解析器
 * 实时解析程序员 Agent 输出中的结构化标记并安全执行
 * 内含确认状态机：draft 模式只发 preview 事件，confirmed 模式才执行写操作
 */
class CodeGenParser
{
    private $onChunk;
    private string $buffer = '';
    private bool $allowOverwrite;
    private string $backendBasePath;
    private string $frontendBasePath;
    private array $stats = [
        'routes_patch_added' => 0,
        'routes_patch_skipped' => 0,
        'routes_patch_failed' => 0,
        'dict_patch_added' => 0,
        'dict_patch_skipped' => 0,
        'dict_patch_failed' => 0,
    ];

    /** 允许写入的路径前缀（白名单） */
    private const ALLOWED_PATHS = [
        'app/controller/', 'app/module/', 'app/dep/', 'app/model/',
        'app/validate/', 'app/service/', 'app/enum/',
        'src/api/', 'src/views/', 'src/components/', 'src/hooks/',
    ];

    /** 允许的文件后缀 */
    private const ALLOWED_EXTENSIONS = ['php', 'ts', 'vue', 'js'];

    /** 仅允许 PATCH_FILE 修改的文件（禁止 WRITE_FILE 覆盖） */
    private const PATCH_ONLY_FILES = [
        'app/service/DictService.php',
        'routes/admin.php',
    ];

    public function __construct(callable $onChunk, bool $allowOverwrite = false, ?string $backendBasePath = null, ?string $frontendBasePath = null)
    {
        $this->onChunk = $onChunk;
        $this->allowOverwrite = $allowOverwrite;
        $this->backendBasePath = rtrim($backendBasePath ?: base_path(), '/\\');
        $this->frontendBasePath = rtrim($frontendBasePath ?: (dirname($this->backendBasePath) . '/admin_front_ts'), '/\\');
    }

    public function feed(string $delta): void
    {
        $this->buffer .= $delta;
    }

    /**
     * 流结束后执行所有解析到的操作
     * DDL 和文件写入独立执行，互不阻断
     */
    public function flush(): void
    {
        $this->parseCreateTable();
        $this->parseAlterTable();
        $this->parseWriteFile();
        $this->parsePatchRoutes();
        $this->parsePatchFile();
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 解析并执行 CREATE_TABLE 标记
     * @return bool 全部成功返回 true，任一失败返回 false
     */
    private function parseCreateTable(): bool
    {
        preg_match_all('/```sql:CREATE_TABLE:(\w+)\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $markerTableName = $match[1];
            $ddl = trim($match[2]);

            try {
                $executed = $this->executeCreateTable($markerTableName, $ddl);
                ($this->onChunk)('table_created', [
                    'table_name' => $markerTableName,
                    'success'    => true,
                    'skipped'    => !$executed,
                ]);
            } catch (\Throwable $e) {
                ($this->onChunk)('table_created', [
                    'table_name' => $markerTableName,
                    'success'    => false,
                    'error'      => $e->getMessage(),
                ]);
                ($this->onChunk)('error', [
                    'msg' => "建表失败({$markerTableName}): {$e->getMessage()}",
                ]);
            }
        }
        return true;
    }

    /**
     * 安全执行 CREATE TABLE
     * @return bool true=已执行, false=表已存在跳过
     */
    private function executeCreateTable(string $markerTableName, string $ddl): bool
    {
        if (!preg_match('/^\s*CREATE\s+TABLE/i', $ddl)) {
            throw new \RuntimeException('仅允许 CREATE TABLE 语句');
        }

        // 拒绝危险操作（含分号后追加语句）
        // 先剔除 DDL 中合法的 ON UPDATE 子句（如 ON UPDATE CURRENT_TIMESTAMP），避免误判
        $ddlForCheck = preg_replace('/\bON\s+UPDATE\b/i', '_ON_UPDATE_', $ddl);
        if (preg_match('/\b(DROP|ALTER|TRUNCATE|INSERT|UPDATE|DELETE|GRANT|REVOKE)\b/i', $ddlForCheck)) {
            throw new \RuntimeException('检测到危险操作关键字');
        }

        // 从 DDL 中解析实际表名，与标记表名交叉校验
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $ddl, $nameMatch)) {
            $actualTableName = $nameMatch[1];
            if ($actualTableName !== $markerTableName) {
                throw new \RuntimeException(
                    "标记表名({$markerTableName})与 DDL 实际表名({$actualTableName})不一致"
                );
            }
        }

        // 校验必要标准字段
        foreach (['id', 'created_at', 'updated_at', 'is_del'] as $requiredField) {
            if (!preg_match('/\b' . $requiredField . '\b/i', $ddl)) {
                throw new \RuntimeException("DDL 缺少标准字段: {$requiredField}");
            }
        }

        if ((new GenDep())->tableExists($markerTableName)) {
            Log::info("[CodeGenParser] 表已存在，跳过建表: {$markerTableName}");
            return false;
        }

        Db::statement($ddl);
        Log::info("[CodeGenParser] 建表成功: {$markerTableName}");
        return true;
    }

    /**
     * 解析并执行 ALTER_TABLE 标记
     * @return bool 全部成功返回 true，任一失败返回 false
     */
    private function parseAlterTable(): bool
    {
        preg_match_all('/```sql:ALTER_TABLE:(\w+)\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $markerTableName = $match[1];
            $ddl = trim($match[2]);

            try {
                $this->executeAlterTable($markerTableName, $ddl);
                ($this->onChunk)('table_altered', [
                    'table_name' => $markerTableName,
                    'success'    => true,
                ]);
            } catch (\Throwable $e) {
                ($this->onChunk)('table_altered', [
                    'table_name' => $markerTableName,
                    'success'    => false,
                    'error'      => $e->getMessage(),
                ]);
                ($this->onChunk)('error', [
                    'msg' => "修改表失败({$markerTableName}): {$e->getMessage()}",
                ]);
            }
        }
        return true;
    }

    /**
     * 安全执行 ALTER TABLE
     */
    private function executeAlterTable(string $markerTableName, string $ddl): void
    {
        if (!preg_match('/^\s*ALTER\s+TABLE/i', $ddl)) {
            throw new \RuntimeException('仅允许 ALTER TABLE 语句');
        }

        // 拒绝危险操作
        if (preg_match('/\b(DROP\s+TABLE|TRUNCATE|CREATE|INSERT|DELETE|GRANT|REVOKE|RENAME\s+TABLE)\b/i', $ddl)) {
            throw new \RuntimeException('检测到危险操作关键字');
        }

        // 从 DDL 中解析实际表名，与标记交叉校验
        if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?/i', $ddl, $nameMatch)) {
            $actualTableName = $nameMatch[1];
            if ($actualTableName !== $markerTableName) {
                throw new \RuntimeException(
                    "标记表名({$markerTableName})与 DDL 实际表名({$actualTableName})不一致"
                );
            }
        }

        // 表必须存在
        if (!(new GenDep())->tableExists($markerTableName)) {
            throw new \RuntimeException("表不存在: {$markerTableName}");
        }

        // 禁止删除标准字段
        $standardFields = ['id', 'created_at', 'updated_at', 'is_del'];
        foreach ($standardFields as $field) {
            if (preg_match('/DROP\s+(COLUMN\s+)?`?' . $field . '`?/i', $ddl)) {
                throw new \RuntimeException("禁止删除标准字段: {$field}");
            }
        }

        Db::statement($ddl);
        Log::info("[CodeGenParser] 修改表成功: {$markerTableName}");
    }

    /**
     * 解析 WRITE_FILE 标记并执行
     */
    private function parseWriteFile(): void
    {
        preg_match_all('/```(?:php|typescript|vue|ts):WRITE_FILE:([\w\/\.\-]+)\r?\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $filePath = $match[1];
            $newContent = $match[2];

            try {
                // 读取原文件内容用于 diff 对比
                $originalContent = $this->readOriginalFile($filePath);
                $isNew = $originalContent === null;

                $this->executeWriteFile($filePath, $newContent);
                ($this->onChunk)('file_written', [
                    'path'     => $filePath,
                    'success'  => true,
                    'is_new'   => $isNew,
                    'original' => $isNew ? null : $originalContent,
                    'content'  => $newContent,
                ]);
            } catch (\Throwable $e) {
                ($this->onChunk)('file_written', [
                    'path'    => $filePath,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 读取原文件内容（用于 diff 对比）
     * @return string|null 文件内容，不存在返回 null
     */
    private function readOriginalFile(string $relativePath): ?string
    {
        $fullPath = $this->getFullPath($relativePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        // 限制大小，超过 50KB 不返回原内容（避免 SSE 消息过大）
        if (strlen($content) > 51200) {
            return '[文件过大，不展示 diff]';
        }

        return $content;
    }

    /**
     * 安全写入文件
     */
    private function executeWriteFile(string $relativePath, string $content): void
    {
        // 路径穿越检测（优先检查）
        if (str_contains($relativePath, '..')) {
            throw new \RuntimeException('检测到路径穿越');
        }

        // 路径白名单校验
        $allowed = false;
        foreach (self::ALLOWED_PATHS as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new \RuntimeException("路径不在白名单内: {$relativePath}");
        }

        // 后缀校验
        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            throw new \RuntimeException("不允许的文件类型: .{$ext}");
        }

        // PATCH_ONLY 文件禁止 WRITE_FILE 覆盖（必须使用 PATCH_FILE 增量修改）
        if (in_array($relativePath, self::PATCH_ONLY_FILES, true)) {
            throw new \RuntimeException("禁止 WRITE_FILE 覆盖 {$relativePath}，请使用 PATCH_FILE 增量修改");
        }

        $fullPath = $this->getFullPath($relativePath);
        $isPhpFile = strtolower((string)pathinfo($relativePath, PATHINFO_EXTENSION)) === 'php';
        $backupPath = null;

        // 覆盖策略：默认不覆盖，迭代场景需 allowOverwrite
        if (file_exists($fullPath)) {
            if (!$this->allowOverwrite) {
                throw new \RuntimeException("文件已存在: {$relativePath}（迭代修改需传入 allow_overwrite=true）");
            }
            // 覆盖前先备份
            $backupPath = $fullPath . '.bak.' . date('YmdHis');
            copy($fullPath, $backupPath);
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
        try {
            if ($isPhpFile) {
                $this->assertPhpSyntax($fullPath);
            }
        } catch (\Throwable $e) {
            if ($backupPath && file_exists($backupPath)) {
                copy($backupPath, $fullPath);
            } elseif (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            throw $e;
        }
        Log::info("[CodeGenParser] 写入文件: {$relativePath}");
    }

    /**
     * 解析 PATCH_ROUTES 标记并执行路由增量补丁
     * 格式：
     * ```php:PATCH_ROUTES:routes/admin.php
     * Route::post(...);
     * ```
     */
    private function parsePatchRoutes(): void
    {
        preg_match_all('/```php:PATCH_ROUTES:([^\r\n]+)\r?\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $filePath = trim($match[1]);
            $routesBlock = $match[2];

            try {
                $result = $this->executePatchRoutes($filePath, $routesBlock);
                $this->stats['routes_patch_added'] += $result['added'];
                $this->stats['routes_patch_skipped'] += $result['skipped'];

                ($this->onChunk)('routes_patched', [
                    'path'    => $filePath,
                    'success' => true,
                    'added'   => $result['added'],
                    'skipped' => $result['skipped'],
                ]);
            } catch (\Throwable $e) {
                $this->stats['routes_patch_failed']++;
                ($this->onChunk)('routes_patched', [
                    'path'    => $filePath,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 执行 routes/admin.php 路由增量补丁
     * 仅允许注释行和 Route::post(...) 语句，插入到 })->middleware([ 之前
     * @return array{added:int,skipped:int}
     */
    private function executePatchRoutes(string $relativePath, string $routesBlock): array
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath !== 'routes/admin.php') {
            throw new \RuntimeException('PATCH_ROUTES 仅允许目标文件 routes/admin.php');
        }
        if (str_contains($relativePath, '..')) {
            throw new \RuntimeException('检测到路径穿越');
        }

        $fullPath = $this->getFullPath($relativePath);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("文件不存在: {$relativePath}");
        }

        $rawLines = preg_split('/\R/', trim($routesBlock)) ?: [];
        $entries = [];
        $candidateRouteCount = 0;

        foreach ($rawLines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            if ($this->isAllowedRouteCommentLine($line)) {
                $entries[] = ['type' => 'comment', 'line' => $line];
                continue;
            }
            if ($this->isAllowedRouteStatement($line)) {
                $entries[] = ['type' => 'route', 'line' => $line];
                $candidateRouteCount++;
                continue;
            }
            throw new \RuntimeException('PATCH_ROUTES 仅允许注释行和 Route::post(...) 语句');
        }

        if ($candidateRouteCount === 0) {
            throw new \RuntimeException('PATCH_ROUTES 至少需要包含一条 Route::post(...)');
        }

        $content = file_get_contents($fullPath);
        $anchor = '})->middleware([';
        $anchorPos = strpos($content, $anchor);
        if ($anchorPos === false) {
            throw new \RuntimeException("未找到插入锚点: {$anchor}");
        }

        $existingLines = preg_split('/\R/', $content) ?: [];
        $existingTrimmed = [];
        $existingRoutes = [];
        foreach ($existingLines as $existingLine) {
            $trimmed = trim($existingLine);
            if ($trimmed === '') {
                continue;
            }
            $existingTrimmed[$trimmed] = true;
            if ($this->isAllowedRouteStatement($trimmed)) {
                $existingRoutes[$this->normalizeRouteLine($trimmed)] = true;
            }
        }

        $skipped = 0;
        $added = 0;
        $newRouteSet = [];
        $insertLines = [];

        foreach ($entries as $entry) {
            $line = $entry['line'];
            if ($entry['type'] === 'comment') {
                if (!isset($existingTrimmed[$line])) {
                    $insertLines[] = $line;
                }
                continue;
            }

            $normalized = $this->normalizeRouteLine($line);
            if (isset($existingRoutes[$normalized]) || isset($newRouteSet[$normalized])) {
                $skipped++;
                continue;
            }

            $newRouteSet[$normalized] = true;
            $insertLines[] = $line;
            $added++;
        }

        if ($added === 0) {
            return ['added' => 0, 'skipped' => $skipped];
        }

        $formattedLines = array_map(static fn(string $line) => '    ' . ltrim($line), $insertLines);
        $insertBlock = implode("\n", $formattedLines) . "\n";

        $prefix = substr($content, 0, $anchorPos);
        $suffix = substr($content, $anchorPos);
        if ($prefix !== '' && !str_ends_with($prefix, "\n")) {
            $prefix .= "\n";
        }
        $newContent = $prefix . $insertBlock . $suffix;

        $backupPath = $fullPath . '.bak.' . date('YmdHis');
        if (!copy($fullPath, $backupPath)) {
            throw new \RuntimeException("备份路由文件失败: {$relativePath}");
        }

        file_put_contents($fullPath, $newContent);
        try {
            $this->assertPhpSyntax($fullPath);
        } catch (\Throwable $e) {
            copy($backupPath, $fullPath);
            throw $e;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * 解析 PATCH_FILE 标记并执行增量修改
     * 格式：```php:PATCH_FILE:路径:BEFORE_MARKER:标记内容
     * 在指定标记之前插入代码
     */
    private function parsePatchFile(): void
    {
        // 格式：```php:PATCH_FILE:app/service/DictService.php:BEFORE_METHOD:getDict
        // 表示在 getDict 方法之前插入代码
        preg_match_all('/```(?:php|typescript|vue|ts):PATCH_FILE:([\w\/\.\-]+):BEFORE_METHOD:(\w+)\s*\r?\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $filePath = $match[1];
            $methodName = $match[2];
            $newCode = $match[3];

            try {
                $result = $this->executePatchFile($filePath, $methodName, $newCode);
                if ($filePath === 'app/service/DictService.php') {
                    $this->stats['dict_patch_added'] += (int)($result['added'] ?? 0);
                    $this->stats['dict_patch_skipped'] += (int)($result['skipped'] ?? 0);
                }
                ($this->onChunk)('file_patched', [
                    'path'        => $filePath,
                    'method'      => $methodName,
                    'success'     => true,
                    'skipped'     => (bool)($result['skipped'] ?? false),
                    'code_length' => strlen($newCode),
                ]);
            } catch (\Throwable $e) {
                if ($filePath === 'app/service/DictService.php') {
                    $this->stats['dict_patch_failed']++;
                }
                Log::warning("[CodeGenParser] PATCH_FILE 执行失败: {$filePath} @ {$methodName}, err={$e->getMessage()}");
                ($this->onChunk)('file_patched', [
                    'path'    => $filePath,
                    'method'  => $methodName,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 执行增量修改：在指定方法之前插入代码
     */
    private function executePatchFile(string $relativePath, string $beforeMethod, string $newCode): array
    {
        // 路径穿越检测
        if (str_contains($relativePath, '..')) {
            throw new \RuntimeException('检测到路径穿越');
        }

        // 路径白名单校验
        $allowed = false;
        foreach (self::ALLOWED_PATHS as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new \RuntimeException("路径不在白名单内: {$relativePath}");
        }

        $fullPath = $this->getFullPath($relativePath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("文件不存在: {$relativePath}");
        }

        $content = file_get_contents($fullPath);

        // 若补丁中的方法已全部存在，直接跳过，避免重复插入导致语法冲突
        $newMethodNames = $this->extractMethodNames($newCode);
        if (!empty($newMethodNames)) {
            $allExists = true;
            foreach ($newMethodNames as $methodName) {
                $existsPattern = '/(?:public|private|protected)\s+(?:static\s+)?function\s+' . preg_quote($methodName, '/') . '\s*\(/m';
                if (!preg_match($existsPattern, $content)) {
                    $allExists = false;
                    break;
                }
            }
            if ($allExists) {
                return ['added' => 0, 'skipped' => 1];
            }
        }

        // 查找目标方法位置（可包含紧邻的 docblock）
        $pattern = '/^[ \t]*(?:\/\*\*[\s\S]*?\*\/\s*\R)?[ \t]*(?:public|private|protected)\s+(?:static\s+)?function\s+'
            . preg_quote($beforeMethod, '/') . '\s*\(/m';
        if (!preg_match($pattern, $content, $methodMatch, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException("未找到方法: {$beforeMethod}");
        }

        $insertPos = $methodMatch[0][1];

        // 确保新代码末尾有换行
        $newCode = rtrim($newCode) . "\n";

        // 在方法（含注释）之前插入新代码
        $newContent = substr($content, 0, $insertPos) . "\n" . $newCode . substr($content, $insertPos);

        // 备份原文件
        $backupPath = $fullPath . '.bak.' . date('YmdHis');
        copy($fullPath, $backupPath);

        file_put_contents($fullPath, $newContent);
        try {
            if (strtolower((string)pathinfo($fullPath, PATHINFO_EXTENSION)) === 'php') {
                $this->assertPhpSyntax($fullPath);
            }
        } catch (\Throwable $e) {
            copy($backupPath, $fullPath);
            throw $e;
        }
        Log::info("[CodeGenParser] 增量修改文件: {$relativePath}，在 {$beforeMethod} 方法前插入代码");
        return ['added' => 1, 'skipped' => 0];
    }

    private function getFullPath(string $relativePath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        $basePath = str_starts_with($normalized, 'src/')
            ? $this->frontendBasePath
            : $this->backendBasePath;

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    private function assertPhpSyntax(string $filePath): void
    {
        $phpBinary = \defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($filePath) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $error = trim(implode("\n", $output));
            throw new \RuntimeException("PHP 语法检查失败: {$error}");
        }
    }

    private function isAllowedRouteCommentLine(string $line): bool
    {
        return str_starts_with($line, '//')
            || str_starts_with($line, '/*')
            || str_starts_with($line, '*')
            || str_starts_with($line, '*/');
    }

    private function isAllowedRouteStatement(string $line): bool
    {
        return (bool)preg_match('/^Route::post\s*\(.+\);\s*$/', $line);
    }

    private function normalizeRouteLine(string $line): string
    {
        return (string)preg_replace('/\s+/', '', $line);
    }

    /**
     * 提取代码片段内声明的方法名（用于补丁去重）
     * @return string[]
     */
    private function extractMethodNames(string $code): array
    {
        preg_match_all('/(?:public|private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(/', $code, $matches);
        if (empty($matches[1])) {
            return [];
        }
        return array_values(array_unique($matches[1]));
    }
}
