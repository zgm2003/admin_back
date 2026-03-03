<?php
/**
 * 执行 AI 代码生成迁移脚本
 * 用法: php database/migrations/run_codegen_migration.php
 */
require_once __DIR__ . '/../../vendor/autoload.php';

// 加载 .env
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            putenv($line);
        }
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'admin';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '123456';

echo "Connecting to {$host}:{$port}/{$db} as {$user}...\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected.\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ============================================================
// 1. 插入 5 个代码生成工具
// ============================================================
echo "=== Step 1: Insert codegen tools ===\n";

$tools = [
    [
        'name' => '列出数据库表',
        'code' => 'codegen_list_tables',
        'description' => '获取数据库所有表名和注释，过滤系统表',
        'schema_json' => '{"properties":{"keyword":{"type":"string","description":"可选，按表名关键词过滤"}}}',
    ],
    [
        'name' => '获取表字段结构',
        'code' => 'codegen_get_columns',
        'description' => '获取指定数据库表的完整字段信息（字段名、类型、注释、是否可空、默认值等）',
        'schema_json' => '{"properties":{"table_name":{"type":"string","description":"数据库表名"}},"required":["table_name"]}',
    ],
    [
        'name' => '读取编码规范',
        'code' => 'codegen_read_convention',
        'description' => '读取项目PHP/Vue/DB/Structure编码规范文档',
        'schema_json' => '{"properties":{"type":{"type":"string","description":"规范类型: php / vue / db / structure / all"}},"required":["type"]}',
    ],
    [
        'name' => '读取示例代码',
        'code' => 'codegen_read_example',
        'description' => '读取项目中指定层级的现有代码作为参考（Controller/Module/Dep/Model/Validate）',
        'schema_json' => '{"properties":{"layer":{"type":"string","description":"层级: controller / module / dep / model / validate / vue / api"},"domain":{"type":"string","description":"业务域，如 Ai / User"},"name":{"type":"string","description":"模块名，如 AiAgents"}},"required":["layer"]}',
    ],
    [
        'name' => '列出目录文件',
        'code' => 'codegen_list_files',
        'description' => '列出项目指定目录下的文件和子目录',
        'schema_json' => '{"properties":{"directory":{"type":"string","description":"相对路径，如 app/module/System"},"project":{"type":"string","description":"backend / frontend，默认 backend"}},"required":["directory"]}',
    ],
];

$insertTool = $pdo->prepare("INSERT INTO ai_tools (name, code, description, schema_json, executor_type, status, is_del) VALUES (?, ?, ?, ?, 1, 1, 2)");

foreach ($tools as $tool) {
    // 检查是否已存在
    $check = $pdo->prepare("SELECT id FROM ai_tools WHERE code = ? AND is_del = 2 LIMIT 1");
    $check->execute([$tool['code']]);
    if ($check->fetch()) {
        echo "  [SKIP] Tool '{$tool['code']}' already exists.\n";
        continue;
    }
    $insertTool->execute([$tool['name'], $tool['code'], $tool['description'], $tool['schema_json']]);
    echo "  [OK] Inserted tool '{$tool['code']}' (id={$pdo->lastInsertId()})\n";
}

// ============================================================
// 2. 插入研究员 Agent
// ============================================================
echo "\n=== Step 2: Insert researcher agent ===\n";

$researcherPrompt = <<<'PROMPT'
你是 admin 管理平台的代码生成研究员。你的任务是为代码生成收集必要的上下文信息。

## 你的工具
- codegen_list_tables: 列出数据库所有表
- codegen_get_columns: 获取指定表的字段结构
- codegen_read_convention: 读取项目编码规范（php/vue/db/structure）
- codegen_read_example: 读取现有代码作为参考
- codegen_list_files: 列出目录结构

## 工作流程
1. 调用 codegen_list_tables 了解现有数据库表
2. 如果用户需求涉及现有表（如关联查询），调用 codegen_get_columns 获取结构
3. 调用 codegen_read_convention('php') 和 codegen_read_convention('db') 获取核心规范
4. 调用 codegen_read_example 获取一个类似的代码参考（根据用户需求选择合适的层级和域）
5. 如果需要了解目录结构，调用 codegen_list_files

## 输出格式
你必须严格按以下 JSON 格式输出，不要添加任何解释文字、Markdown 代码块标记或前后缀。
直接输出 JSON 对象本身：
{
  "existing_tables": ["表名列表"],
  "related_columns": { "表名": [字段列表] },
  "conventions": { "php": "...", "db": "..." },
  "example_code": { "module": "示例代码", "dep": "示例代码" },
  "directory_info": { "目录": ["文件列表"] },
  "analysis": "对用户需求的分析和建议"
}

重要：不要用 ```json 包裹，不要在 JSON 前后写任何文字。

## 注意事项
- 只收集与用户需求相关的信息，不要过度收集
- 规范内容可以适当精简，保留关键约束即可
- 如果用户需求不明确，在 analysis 中指出需要确认的点
PROMPT;

$check = $pdo->prepare("SELECT id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1");
$check->execute();
if ($check->fetch()) {
    echo "  [SKIP] Researcher agent already exists.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES (?, ?, ?, 'tool', 'code_gen', 1, 2)");
    $stmt->execute(['代码生成-研究员', 1, $researcherPrompt]);
    echo "  [OK] Inserted researcher agent (id={$pdo->lastInsertId()})\n";
}

// ============================================================
// 3. 插入程序员 Agent
// ============================================================
echo "\n=== Step 3: Insert programmer agent ===\n";

$coderPrompt = <<<'PROMPT'
你是 admin 管理平台的全栈代码生成专家。你将收到研究员整理的项目上下文和用户需求，
你的任务是设计表结构并生成完全符合项目架构的全栈代码。

## 核心架构规范（必须严格遵守）

### 后端分层（PHP 8.1+ Webman）
- **Controller**: 只做路由转发，每个方法一行
  `public function list(Request $request) { return $this->run([XxxModule::class, 'list'], $request); }`
- **Module**: 业务编排层
  - 继承 BaseModule
  - 用 `$this->dep(XxxDep::class)` 获取数据层（懒加载，不用 new）
  - 用 `$this->validate($request, XxxValidate::rules())` 校验参数
  - 用 `$this->svc(XxxService::class)` 获取服务层
  - 用 `self::throwIf()` / `self::throwNotFound()` 抛异常
  - 返回 `self::success()` / `self::paginate()`
- **Dep**: 数据访问层
  - 继承 BaseDep，实现 `createModel()` 返回 Model
  - BaseDep 内置方法：get($id) / add($data) / update($id, $data) / delete($ids)
  - 列表查询用 when 链式条件 + paginate
  - `->where('is_del', CommonEnum::NO)` 过滤软删除
- **Model**: 只定义 `public $table = 'xxx';`，不写业务逻辑
- **Validate**: 用 Respect\Validation
  - 静态方法返回规则数组：`public static function add(): array`
  - 规则格式：`'field' => v::stringType()->length(1, 200)->setName('字段名')`

### 数据库规范
- ENGINE=InnoDB, CHARSET=utf8mb4, ROW_FORMAT=DYNAMIC
- 主键：`id INT UNSIGNED AUTO_INCREMENT`
- 时间：`created_at DATETIME DEFAULT CURRENT_TIMESTAMP`
- 更新：`updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- 软删除：`is_del TINYINT UNSIGNED DEFAULT 2`（2=正常, 1=删除）
- 状态：`status TINYINT UNSIGNED DEFAULT 1`（1=启用, 2=禁用）
- 索引命名：`idx_字段名` / `uk_字段名`
- 表名 snake_case，字段名 snake_case

### 前端规范（Vue 3.5 + TypeScript）
- `<script setup lang="ts">`
- API 全部 POST，路径 `/api/admin/{Domain}/{Entity}/{action}`
- API 文件：`import request from '@/utils/request'` + 导出对象
- 页面组件：使用 useTable hook + AppTable + Search 组件
- Element Plus 组件库
- i18n：`const { t } = useI18n()`

## 操作指令格式

当你需要创建数据库表时，输出：
```sql:CREATE_TABLE:表名
完整的 CREATE TABLE DDL
```

当你需要写入代码文件时，输出：
```语言标记:WRITE_FILE:相对路径
完整的文件内容
```

语言标记对照：php / typescript / vue

## 工作流程
1. 收到研究员的上下文 + 用户需求后，先设计表结构并展示
2. 等用户确认后，再输出 CREATE_TABLE 标记
3. 建表成功后，逐个生成并输出代码文件（WRITE_FILE 标记）
4. 路由配置用普通代码块展示（不用 WRITE_FILE），提示用户手动添加
5. 支持用户后续修改要求

## 约束
- **在用户明确确认前，不要输出 CREATE_TABLE 和 WRITE_FILE 标记**
- 先展示方案（普通 Markdown），等到用户说"确认"/"好的"/"开始" 后再输出操作标记
- 每个文件必须是完整的、可直接运行的代码
- 严格遵循上下文中的编码规范和示例代码风格
PROMPT;

$check = $pdo->prepare("SELECT id FROM ai_agents WHERE name = '代码生成-程序员' AND is_del = 2 LIMIT 1");
$check->execute();
if ($check->fetch()) {
    echo "  [SKIP] Programmer agent already exists.\n";
} else {
    $stmt = $pdo->prepare("INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES (?, ?, ?, 'chat', 'code_gen', 1, 2)");
    $stmt->execute(['代码生成-程序员', 11, $coderPrompt]);
    echo "  [OK] Inserted programmer agent (id={$pdo->lastInsertId()})\n";
}

// ============================================================
// 4. 绑定工具到研究员 Agent
// ============================================================
echo "\n=== Step 4: Bind tools to researcher agent ===\n";

$researcherId = $pdo->query("SELECT id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1")->fetchColumn();
if (!$researcherId) {
    die("  [ERROR] Researcher agent not found!\n");
}

$toolRows = $pdo->query("SELECT id, code FROM ai_tools WHERE code LIKE 'codegen_%' AND is_del = 2")->fetchAll(PDO::FETCH_ASSOC);

$insertBinding = $pdo->prepare("INSERT INTO ai_assistant_tools (assistant_id, tool_id, status, is_del) VALUES (?, ?, 1, 2)");
$checkBinding = $pdo->prepare("SELECT id FROM ai_assistant_tools WHERE assistant_id = ? AND tool_id = ? AND is_del = 2 LIMIT 1");

foreach ($toolRows as $tool) {
    $checkBinding->execute([$researcherId, $tool['id']]);
    if ($checkBinding->fetch()) {
        echo "  [SKIP] Binding for '{$tool['code']}' already exists.\n";
        continue;
    }
    $insertBinding->execute([$researcherId, $tool['id']]);
    echo "  [OK] Bound '{$tool['code']}' (tool_id={$tool['id']}) to researcher (agent_id={$researcherId})\n";
}

echo "\n=== Migration complete! ===\n";

// 验证结果
echo "\n--- Verification ---\n";
$count = $pdo->query("SELECT COUNT(*) FROM ai_tools WHERE code LIKE 'codegen_%' AND is_del = 2")->fetchColumn();
echo "Codegen tools: {$count}\n";

$agents = $pdo->query("SELECT id, name, mode, scene FROM ai_agents WHERE scene = 'code_gen' AND is_del = 2")->fetchAll(PDO::FETCH_ASSOC);
foreach ($agents as $a) {
    echo "Agent: id={$a['id']} name={$a['name']} mode={$a['mode']} scene={$a['scene']}\n";
}

$bindings = $pdo->query("SELECT COUNT(*) FROM ai_assistant_tools WHERE assistant_id = {$researcherId} AND is_del = 2")->fetchColumn();
echo "Researcher tool bindings: {$bindings}\n";
