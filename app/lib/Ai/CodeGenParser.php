<?php

namespace app\lib\Ai;

use app\dep\DevTools\GenDep;
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

    /** 允许写入的路径前缀（白名单） */
    private const ALLOWED_PATHS = [
        'app/controller/', 'app/module/', 'app/dep/', 'app/model/',
        'app/validate/', 'app/service/', 'app/enum/',
        'src/api/', 'src/views/', 'src/components/', 'src/hooks/',
    ];

    /** 允许的文件后缀 */
    private const ALLOWED_EXTENSIONS = ['php', 'ts', 'vue', 'js'];

    public function __construct(callable $onChunk, bool $allowOverwrite = false)
    {
        $this->onChunk = $onChunk;
        $this->allowOverwrite = $allowOverwrite;
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
        $this->parsePatchFile();
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
        preg_match_all('/```(?:php|typescript|vue|ts):WRITE_FILE:([\w\/\.\-]+)\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

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
        $isFrontend = str_starts_with($relativePath, 'src/');
        $basePath = $isFrontend
            ? dirname(base_path()) . '/admin_front_ts'
            : base_path();

        $fullPath = $basePath . '/' . $relativePath;

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

        // 判断目标项目
        $isFrontend = str_starts_with($relativePath, 'src/');
        $basePath = $isFrontend
            ? dirname(base_path()) . '/admin_front_ts'
            : base_path();

        $fullPath = $basePath . '/' . $relativePath;

        // 覆盖策略：默认不覆盖，迭代场景需 allowOverwrite
        if (file_exists($fullPath)) {
            if (!$this->allowOverwrite) {
                throw new \RuntimeException("文件已存在: {$relativePath}（迭代修改需传入 allow_overwrite=true）");
            }
            // 覆盖前先备份
            copy($fullPath, $fullPath . '.bak.' . date('YmdHis'));
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
        Log::info("[CodeGenParser] 写入文件: {$relativePath}");
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
        preg_match_all('/```(?:php|typescript|vue|ts):PATCH_FILE:([\w\/\.\-]+):BEFORE_METHOD:(\w+)\n(.*?)```/s', $this->buffer, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $filePath = $match[1];
            $methodName = $match[2];
            $newCode = $match[3];

            try {
                $this->executePatchFile($filePath, $methodName, $newCode);
                ($this->onChunk)('file_patched', [
                    'path'        => $filePath,
                    'method'      => $methodName,
                    'success'     => true,
                    'code_length' => strlen($newCode),
                ]);
            } catch (\Throwable $e) {
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
    private function executePatchFile(string $relativePath, string $beforeMethod, string $newCode): void
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

        // 判断目标项目
        $isFrontend = str_starts_with($relativePath, 'src/');
        $basePath = $isFrontend
            ? dirname(base_path()) . '/admin_front_ts'
            : base_path();

        $fullPath = $basePath . '/' . $relativePath;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("文件不存在: {$relativePath}");
        }

        $content = file_get_contents($fullPath);

        // 查找目标方法位置（支持 public/private/protected function 和纯 function）
        $pattern = '/(\n\s*)((?:public|private|protected)\s+)?function\s+' . preg_quote($beforeMethod, '/') . '\s*\(/';
        if (!preg_match($pattern, $content, $methodMatch, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException("未找到方法: {$beforeMethod}");
        }

        $insertPos = $methodMatch[0][1];

        // 如果方法前有 docblock 注释或单行注释，插入点应在注释之前
        $before = substr($content, 0, $insertPos);
        if (preg_match('/(\n\s*\/\*\*.*?\*\/\s*)$/s', $before, $docMatch)) {
            // 有 docblock（/** ... */）
            $insertPos -= strlen($docMatch[1]);
        } elseif (preg_match('/(\n\s*\/\/[^\n]*\s*)$/', $before, $lineCommentMatch)) {
            // 有单行注释（// ...）
            $insertPos -= strlen($lineCommentMatch[1]);
        }

        // 确保新代码末尾有换行
        $newCode = rtrim($newCode) . "\n";

        // 在方法（含注释）之前插入新代码
        $newContent = substr($content, 0, $insertPos) . "\n" . $newCode . substr($content, $insertPos);

        // 备份原文件
        copy($fullPath, $fullPath . '.bak.' . date('YmdHis'));

        file_put_contents($fullPath, $newContent);
        Log::info("[CodeGenParser] 增量修改文件: {$relativePath}，在 {$beforeMethod} 方法前插入代码");
    }
}
