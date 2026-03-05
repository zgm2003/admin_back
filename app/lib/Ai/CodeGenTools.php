<?php

namespace app\lib\Ai;

use app\dep\DevTools\GenDep;

/**
 * 浠ｇ爜鐢熸垚涓撶敤宸ュ叿闆? * 娉ㄥ唽鍦?ToolExecutor::$internalTools 涓紝渚涚爺绌跺憳 Agent 璋冪敤
 */
class CodeGenTools
{
    /** 蹇界暐鐨勭郴缁熻〃 */
    private const IGNORE_TABLES = [
        'migrations', 'failed_jobs', 'password_resets', 'personal_access_tokens',
    ];

    /**
     * 鍒楀嚭鏁版嵁搴撴墍鏈夎〃
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
     * 鑾峰彇鎸囧畾琛ㄧ殑瀛楁缁撴瀯
     */
    public static function getColumns(array $inputs): string
    {
        $tableName = $inputs['table_name'] ?? '';
        if (empty($tableName)) {
            return '鍙傛暟缂哄け: table_name';
        }

        $dep = new GenDep();
        if (!$dep->tableExists($tableName)) {
            return "琛ㄤ笉瀛樺湪: {$tableName}";
        }

        $columns = $dep->getColumns($tableName);
        return json_encode($columns, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 璇诲彇椤圭洰缂栫爜瑙勮寖
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
            return "鏈煡鐨勮鑼冪被鍨? {$type}锛屽彲閫? php / vue / db / structure / all";
        }

        $path = $steeringDir . '/' . $file;
        if (!file_exists($path)) {
            return "瑙勮寖鏂囦欢涓嶅瓨鍦? {$file}";
        }

        $content = file_get_contents($path);
        // 鎴柇鑷?8000 瀛楃浠ユ帶鍒?Token
        if (mb_strlen($content) > 8000) {
            $content = mb_substr($content, 0, 8000) . "\n\n...[鎴柇锛屽畬鏁村唴瀹硅鍙傝€冮」鐩枃浠禲";
        }

        return $content;
    }

    /**
     * 璇诲彇绀轰緥浠ｇ爜
     */
    public static function readExample(array $inputs): string
    {
        $layer  = $inputs['layer'] ?? '';
        $domain = $inputs['domain'] ?? '';
        $name   = $inputs['name'] ?? '';

        // 安全校验：domain/name 必须是大驼峰，防止路径穿越

        if (!empty($domain) && !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $domain)) {
            return "domain 鏍煎紡闈炴硶锛堝繀椤诲ぇ椹煎嘲锛? {$domain}";
        }
        if (!empty($name) && !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            return "name 鏍煎紡闈炴硶锛堝繀椤诲ぇ椹煎嘲锛? {$name}";
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
            return "鏈煡鐨勫眰绾? {$layer}锛屽彲閫? controller / module / dep / model / validate / vue / api";
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
            return "绀轰緥鏂囦欢涓嶅瓨鍦? {$relativePath}";
        }

        $content = file_get_contents($path);
        if (mb_strlen($content) > 5000) {
            $content = mb_substr($content, 0, 5000) . "\n\n...[鎴柇]";
        }

        return $content;
    }

    /**
     * 鑾峰彇琛ㄧ殑瀹屾暣 CREATE TABLE DDL锛堝惈绱㈠紩銆佺害鏉熺瓑锛?     * 鐢ㄤ簬淇敼宸叉湁琛ㄦ椂浜嗚В瀹屾暣缁撴瀯
     */
    public static function showCreateTable(array $inputs): string
    {
        $tableName = $inputs['table_name'] ?? '';
        if (empty($tableName)) {
            return '鍙傛暟缂哄け: table_name';
        }

        // 瀹夊叏鏍￠獙锛氳〃鍚嶅彧鍏佽 snake_case
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $tableName)) {
            return "琛ㄥ悕鏍煎紡闈炴硶: {$tableName}";
        }

        $dep = new GenDep();
        if (!$dep->tableExists($tableName)) {
            return "琛ㄤ笉瀛樺湪: {$tableName}";
        }

        try {
            $result = \support\Db::select("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($result)) {
                $row = (array)$result[0];
                return $row['Create Table'] ?? '鏃犳硶鑾峰彇寤鸿〃璇彞';
            }
            return '鏃犳硶鑾峰彇寤鸿〃璇彞';
        } catch (\Throwable $e) {
            return "鏌ヨ澶辫触: {$e->getMessage()}";
        }
    }

    /**
     * 鍒楀嚭鐩綍涓嬬殑鏂囦欢
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



