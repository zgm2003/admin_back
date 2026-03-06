<?php

namespace app\lib\Ai;

use app\dep\Ai\GenDep;

/**
 * 代码生成专用工具集
 * 注册在 ToolExecutor::$internalTools 中，供研究员 Agent 调用
 */
class CodeGenTools
{
    /** 忽略的系统表 */
    private const IGNORE_TABLES = [
        'migrations', 'failed_jobs', 'password_resets', 'personal_access_tokens',
    ];

    /**
     * 列出数据库所有表
     */
    public static function listTables(array $inputs): string
    {
        $keyword = $inputs['keyword'] ?? '';
        $tables = (new GenDep())->getTables();

        // 过滤系统表

        $tables = array_filter($tables, fn($t) => !in_array($t['table_name'], self::IGNORE_TABLES));
        // 关键词过滤
        if (!empty($keyword)) {
            $tables = array_filter($tables, fn($t) => str_contains($t['table_name'], $keyword));
        }

        return json_encode(array_values($tables), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取指定表的字段结构
     */
    public static function getColumns(array $inputs): string
    {
        $tableName = $inputs['table_name'] ?? '';
        if (empty($tableName)) {
            return '参数缺失: table_name';
        }

        $dep = new GenDep();
        if (!$dep->tableExists($tableName)) {
            return "表不存在: {$tableName}";
        }

        $columns = $dep->getColumns($tableName);
        return json_encode($columns, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取项目编码规范
     */
    public static function readConvention(array $inputs): string
    {
        $type = $inputs['type'] ?? 'all';
        $steeringDir = base_path() . '/../.kiro/steering';

        $fileMap = [
            'php'       => 'php-conventions.md',
            'vue'       => 'vue-conventions.md',
            'db'        => 'db-conventions.md',
            'structure' => 'structure.md',
        ];

        if ($type === 'all') {
            $result = [];
            foreach ($fileMap as $key => $file) {
                $path = $steeringDir . '/' . $file;
                if (file_exists($path)) {
                    $content = file_get_contents($path);
                    $result[$key] = mb_substr($content, 0, 2000);
                }
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE);
        }

        $file = $fileMap[$type] ?? null;
        if (!$file) {
            return "未知的规范类型: {$type}，可选: php / vue / db / structure / all";
        }

        $path = $steeringDir . '/' . $file;
        if (!file_exists($path)) {
            return "规范文件不存在: {$file}";
        }

        $content = file_get_contents($path);
        // 截断至 8000 字符以控制 Token
        if (mb_strlen($content) > 8000) {
            $content = mb_substr($content, 0, 8000) . "\n\n...[截断，完整内容请参考项目文件]";
        }

        return $content;
    }

    /**
     * 读取示例代码
     */
    public static function readExample(array $inputs): string
    {
        $layer  = $inputs['layer'] ?? '';
        $domain = $inputs['domain'] ?? '';
        $name   = $inputs['name'] ?? '';

        // 安全校验：domain/name 必须是大驼峰，防止路径穿越

        if (!empty($domain) && !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $domain)) {
            return "domain 格式非法（必须大驼峰）: {$domain}";
        }
        if (!empty($name) && !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            return "name 格式非法（必须大驼峰）: {$name}";
        }

        $backendBase  = base_path();
        $frontendBase = dirname($backendBase) . '/admin_front_ts';

        // 默认示例映射（含前端）

        $defaultExamples = [
            'controller' => [$backendBase, 'app/controller/Ai/AiAgentsController.php'],
            'module'     => [$backendBase, 'app/module/Ai/AiAgentsModule.php'],
            'dep'        => [$backendBase, 'app/dep/Ai/AiAgentsDep.php'],
            'model'      => [$backendBase, 'app/model/Ai/AiAgentsModel.php'],
            'validate'   => [$backendBase, 'app/validate/Ai/AiAgentsValidate.php'],
            'vue'        => [$frontendBase, 'src/views/Main/ai/agents/index.vue'],
            'api'        => [$frontendBase, 'src/api/ai/aiAgents.ts'],
        ];

        if (!isset($defaultExamples[$layer])) {
            return "未知的层级: {$layer}，可选: controller / module / dep / model / validate / vue / api";
        }

        // 如果指定了 domain+name，尝试定位具体文件

        if (!empty($domain) && !empty($name)) {
            $pathMap = [
                'controller' => [$backendBase, "app/controller/{$domain}/{$name}Controller.php"],
                'module'     => [$backendBase, "app/module/{$domain}/{$name}Module.php"],
                'dep'        => [$backendBase, "app/dep/{$domain}/{$name}Dep.php"],
                'model'      => [$backendBase, "app/model/{$domain}/{$name}Model.php"],
                'validate'   => [$backendBase, "app/validate/{$domain}/{$name}Validate.php"],
                'vue'        => [$frontendBase, "src/views/Main/" . lcfirst($domain) . "/" . lcfirst($name) . "/index.vue"],
                'api'        => [$frontendBase, "src/api/" . lcfirst($domain) . "/" . lcfirst($name) . ".ts"],
            ];
            [$base, $relativePath] = $pathMap[$layer] ?? ['', ''];
            $path = $base . '/' . $relativePath;
        } else {
            [$base, $relativePath] = $defaultExamples[$layer];
            $path = $base . '/' . $relativePath;
        }

        if (empty($path) || !file_exists($path)) {
            // 回退到默认示例
            [$base, $relativePath] = $defaultExamples[$layer];
            $path = $base . '/' . $relativePath;
        }

        if (!file_exists($path)) {
            return "示例文件不存在: {$relativePath}";
        }

        $content = file_get_contents($path);
        if (mb_strlen($content) > 5000) {
            $content = mb_substr($content, 0, 5000) . "\n\n...[截断]";
        }

        return $content;
    }

    /**
     * 获取表的完整 CREATE TABLE DDL（含索引、约束等）
     * 用于修改已有表时了解完整结构
     */
    public static function showCreateTable(array $inputs): string
    {
        $tableName = $inputs['table_name'] ?? '';
        if (empty($tableName)) {
            return '参数缺失: table_name';
        }

        // 安全校验：表名只允许 snake_case
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $tableName)) {
            return "表名格式非法: {$tableName}";
        }

        $dep = new GenDep();
        if (!$dep->tableExists($tableName)) {
            return "表不存在: {$tableName}";
        }

        try {
            $result = \support\Db::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($result)) {
                $row = (array)$result[0];
                return $row['Create Table'] ?? '无法获取建表语句';
            }
            return '无法获取建表语句';
        } catch (\Throwable $e) {
            return "查询失败: {$e->getMessage()}";
        }
    }

    /**
     * 列出目录下的文件
     */
    public static function listFiles(array $inputs): string
    {
        $directory = $inputs['directory'] ?? '';
        $project   = $inputs['project'] ?? 'backend';

        $directory = str_replace('\\', '/', trim((string)$directory));
        $directory = trim($directory, '/');

        if (empty($directory)) {
            return '参数缺失: directory';
        }

        // 路径安全校验
        if (str_contains($directory, '..')) {
            return '不允许路径穿越';
        }

        // 只允许特定前缀
        $allowedPrefixes = $project === 'frontend'
            ? ['src/']
            : ['app/', 'config/', 'routes/'];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($directory, $prefix)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return "目录不在允许范围内: {$directory}";
        }

        $basePath = $project === 'frontend'
            ? dirname(base_path()) . '/admin_front_ts'
            : base_path();

        $fullPath = $basePath . '/' . $directory;
        if (!is_dir($fullPath)) {
            // 目标目录尚未创建时，返回父目录内容，便于 AI 继续规划新模块
            $parentDir = str_replace('\\', '/', dirname($directory));
            $parentDir = $parentDir === '.' ? '' : trim($parentDir, '/');
            $parentFullPath = $parentDir === '' ? $basePath : ($basePath . '/' . $parentDir);

            if (is_dir($parentFullPath)) {
                $parentItems = scandir($parentFullPath);
                $parentList = [];
                foreach ($parentItems as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $itemPath = $parentFullPath . '/' . $item;
                    $parentList[] = [
                        'name' => $item,
                        'type' => is_dir($itemPath) ? 'directory' : 'file',
                    ];
                }

                return json_encode([
                    'exists'       => false,
                    'directory'    => $directory,
                    'message'      => "目录不存在。可在父目录下创建 {$directory}",
                    'parent'       => $parentDir === '' ? '/' : $parentDir,
                    'parent_items' => $parentList,
                ], JSON_UNESCAPED_UNICODE);
            }

            return json_encode([
                'exists'    => false,
                'directory' => $directory,
                'message'   => "目录不存在: {$directory}",
            ], JSON_UNESCAPED_UNICODE);
        }

        $items = scandir($fullPath);
        $result = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $fullPath . '/' . $item;
            $result[] = [
                'name' => $item,
                'type' => is_dir($itemPath) ? 'directory' : 'file',
            ];
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
