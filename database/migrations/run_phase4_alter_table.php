<?php
/**
 * Phase 4.2 迁移：新增 codegen_alter_table 工具 + 绑定研究员 + 更新提示词
 * 用法: php database/migrations/run_phase4_alter_table.php
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
$pass = getenv('DB_PASSWORD') ?: '';

echo "Connecting to {$host}:{$port}/{$db}...\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected.\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ============================================================
// 1. 插入 codegen_alter_table 工具
// ============================================================
echo "=== Step 1: Insert codegen_alter_table tool ===\n";

$toolCode = 'codegen_alter_table';
$check = $pdo->prepare("SELECT id FROM ai_tools WHERE code = ? AND is_del = 2 LIMIT 1");
$check->execute([$toolCode]);
$existingToolId = $check->fetchColumn();

if ($existingToolId) {
    echo "  [SKIP] Tool '{$toolCode}' already exists (id={$existingToolId}).\n";
    $toolId = $existingToolId;
} else {
    $stmt = $pdo->prepare("INSERT INTO ai_tools (name, code, description, schema_json, executor_type, status, is_del) VALUES (?, ?, ?, ?, 1, 1, 2)");
    $stmt->execute([
        '查看表结构DDL',
        $toolCode,
        '获取指定表的完整 CREATE TABLE 语句（含索引、约束），用于修改已有表时了解完整结构',
        '{"properties":{"table_name":{"type":"string","description":"数据库表名"}},"required":["table_name"]}',
    ]);
    $toolId = $pdo->lastInsertId();
    echo "  [OK] Inserted tool '{$toolCode}' (id={$toolId})\n";
}

// ============================================================
// 2. 绑定工具到研究员 Agent
// ============================================================
echo "\n=== Step 2: Bind tool to researcher agent ===\n";

$researcherId = $pdo->query("SELECT id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1")->fetchColumn();
if (!$researcherId) {
    die("  [ERROR] Researcher agent not found!\n");
}

$checkBinding = $pdo->prepare("SELECT id FROM ai_assistant_tools WHERE assistant_id = ? AND tool_id = ? AND is_del = 2 LIMIT 1");
$checkBinding->execute([$researcherId, $toolId]);
if ($checkBinding->fetch()) {
    echo "  [SKIP] Binding already exists.\n";
} else {
    $insertBinding = $pdo->prepare("INSERT INTO ai_assistant_tools (assistant_id, tool_id, status, is_del) VALUES (?, ?, 1, 2)");
    $insertBinding->execute([$researcherId, $toolId]);
    echo "  [OK] Bound '{$toolCode}' (tool_id={$toolId}) to researcher (agent_id={$researcherId})\n";
}

// ============================================================
// 3. 更新研究员 Agent 提示词（新增 codegen_alter_table 工具说明）
// ============================================================
echo "\n=== Step 3: Update researcher agent prompt ===\n";

$newResearcherPrompt = <<<'PROMPT'
你是 admin 管理平台的代码生成研究员。你的任务是为代码生成收集必要的上下文信息。

## 你的工具
- codegen_list_tables: 列出数据库所有表
- codegen_get_columns: 获取指定表的字段结构
- codegen_alter_table: 获取指定表的完整 CREATE TABLE DDL（含索引、约束）
- codegen_read_convention: 读取项目编码规范（php/vue/db/structure）
- codegen_read_example: 读取现有代码作为参考
- codegen_list_files: 列出目录结构

## 工作流程
1. 调用 codegen_list_tables 了解现有数据库表
2. 如果用户需求涉及现有表（如关联查询），调用 codegen_get_columns 获取结构
3. 如果用户需要修改已有表，调用 codegen_alter_table 获取完整建表语句
4. 调用 codegen_read_convention('php') 和 codegen_read_convention('db') 获取核心规范
5. 调用 codegen_read_example 获取一个类似的代码参考（根据用户需求选择合适的层级和域）
6. 如果需要了解目录结构，调用 codegen_list_files

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

$updateStmt = $pdo->prepare("UPDATE ai_agents SET system_prompt = ? WHERE id = ? AND is_del = 2");
$updateStmt->execute([$newResearcherPrompt, $researcherId]);
echo "  [OK] Updated researcher prompt (added codegen_alter_table tool description)\n";

// ============================================================
// 4. 更新程序员 Agent 提示词（新增 ALTER_TABLE 标记说明）
// ============================================================
echo "\n=== Step 4: Update programmer agent prompt ===\n";

$programmerId = $pdo->query("SELECT id FROM ai_agents WHERE name = '代码生成-程序员' AND is_del = 2 LIMIT 1")->fetchColumn();
if (!$programmerId) {
    die("  [ERROR] Programmer agent not found!\n");
}

$newCoderPrompt = <<<'PROMPT'
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

当你需要修改已有数据库表时，输出：
```sql:ALTER_TABLE:表名
ALTER TABLE DDL（如 ADD COLUMN, ADD INDEX, MODIFY COLUMN 等）
```

当你需要写入代码文件时，输出：
```语言标记:WRITE_FILE:相对路径
完整的文件内容
```

语言标记对照：php / typescript / vue

## 工作流程
1. 收到研究员的上下文 + 用户需求后，先设计表结构并展示
2. 等用户确认后，再输出 CREATE_TABLE 或 ALTER_TABLE 标记
3. DDL 成功后，逐个生成并输出代码文件（WRITE_FILE 标记）
4. 路由配置用普通代码块展示（不用 WRITE_FILE），提示用户手动添加
5. 支持用户后续修改要求

## 约束
- **在用户明确确认前，不要输出 CREATE_TABLE、ALTER_TABLE 和 WRITE_FILE 标记**
- 先展示方案（普通 Markdown），等到用户说"确认"/"好的"/"开始" 后再输出操作标记
- 每个文件必须是完整的、可直接运行的代码
- 严格遵循上下文中的编码规范和示例代码风格
- ALTER TABLE 操作禁止删除标准字段（id, created_at, updated_at, is_del）
PROMPT;

$updateStmt->execute([$newCoderPrompt, $programmerId]);
echo "  [OK] Updated programmer prompt (added ALTER_TABLE marker format)\n";

// ============================================================
// 验证
// ============================================================
echo "\n=== Verification ===\n";
$count = $pdo->query("SELECT COUNT(*) FROM ai_tools WHERE code LIKE 'codegen_%' AND is_del = 2")->fetchColumn();
echo "Codegen tools: {$count}\n";

$bindings = $pdo->query("SELECT COUNT(*) FROM ai_assistant_tools WHERE assistant_id = {$researcherId} AND is_del = 2")->fetchColumn();
echo "Researcher tool bindings: {$bindings}\n";

echo "\nDone!\n";
