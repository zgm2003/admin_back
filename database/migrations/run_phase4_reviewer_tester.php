<?php
/**
 * Phase 4.5 + 4.6 迁移：新增审查员 Agent + 测试员 Agent
 * - 审查员: scene=code_gen, mode=rag（对生成代码自动 Code Review）
 * - 测试员: scene=code_gen, mode=workflow（自动生成测试用例）
 * 用法: php database/migrations/run_phase4_reviewer_tester.php
 */
require_once __DIR__ . '/../../vendor/autoload.php';

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line !== '' && !str_starts_with($line, '#') && str_contains($line, '=')) putenv($line);
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_DATABASE') ?: 'admin';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

echo "Connecting to {$host}:{$port}/{$db}...\n";
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
echo "Connected.\n\n";

// ============================================================
// 1. 插入审查员 Agent (mode=rag, scene=code_gen)
// ============================================================
echo "=== Step 1: Insert reviewer agent ===\n";

$reviewerPrompt = <<<'PROMPT'
你是 admin 管理平台的代码审查专家。你将收到 AI 程序员生成的代码，你的任务是进行全面的 Code Review。

## 审查维度

### 1. 架构规范遵循
- Controller 是否只做路由转发（每个方法一行）
- Module 是否正确使用 dep() / validate() / svc() / throwIf()
- Dep 是否继承 BaseDep 并实现 createModel()
- Model 是否只定义 $table 不含业务逻辑
- Validate 是否用 Respect\Validation 静态方法

### 2. 数据库规范
- 表名/字段名是否 snake_case
- 是否包含标准字段: id, created_at, updated_at, is_del
- ENGINE/CHARSET/ROW_FORMAT 是否正确
- 索引命名是否规范 (idx_/uk_)
- 软删除是否用 is_del (2=正常, 1=删除)

### 3. 前端规范
- 是否使用 script setup + TypeScript
- API 是否全部 POST
- 是否使用 i18n
- 是否使用 Element Plus 组件

### 4. 安全问题
- SQL 注入风险
- XSS 风险
- 路径穿越风险
- 敏感数据暴露

### 5. 潜在 Bug
- 空值/边界检查
- 类型不匹配
- 逻辑错误

## 输出格式
请用以下 Markdown 格式输出审查结果：

### ✅ 通过项
- 列出代码做得好的地方

### ⚠️ 建议改进
- 列出可以优化但不影响功能的点

### ❌ 必须修改
- 列出必须修改的问题（安全隐患、规范违反、明显 Bug）

### 📊 总结
- 总体评分（A/B/C/D）和一句话总结

## 约束
- 只审查代码质量，不要重写代码
- 发现问题时指出具体文件和行号/位置
- 评估要客观公正，好的地方也要肯定
PROMPT;

$check = $pdo->prepare("SELECT id FROM ai_agents WHERE name = '代码生成-审查员' AND is_del = 2 LIMIT 1");
$check->execute();
if ($existing = $check->fetchColumn()) {
    echo "  [SKIP] Reviewer agent already exists (id={$existing}).\n";
} else {
    // 使用与研究员相同的模型（便宜模型即可做审查）
    $researcherModelId = $pdo->query("SELECT model_id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1")->fetchColumn() ?: 1;
    $stmt = $pdo->prepare("INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES (?, ?, ?, 'rag', 'code_gen', 1, 2)");
    $stmt->execute(['代码生成-审查员', $researcherModelId, $reviewerPrompt]);
    echo "  [OK] Inserted reviewer agent (id={$pdo->lastInsertId()}, mode=rag)\n";
}

// ============================================================
// 2. 插入测试员 Agent (mode=workflow, scene=code_gen)
// ============================================================
echo "\n=== Step 2: Insert tester agent ===\n";

$testerPrompt = <<<'PROMPT'
你是 admin 管理平台的测试专家。你将收到 AI 程序员生成的代码，你的任务是为其生成完整的测试用例。

## 测试范围

### 后端测试（PHP）
为生成的 Module / Dep / Service 编写测试：
- **单元测试**: 测试各方法的输入输出、边界条件、异常处理
- **参数校验测试**: 验证 Validate 规则是否完整（必填、类型、长度）
- **业务逻辑测试**: 覆盖核心业务流程的正常和异常路径

测试代码格式：
```php
// 测试文件路径: tests/Feature/XxxModuleTest.php
class XxxModuleTest
{
    public function test_add_success(): void { /* ... */ }
    public function test_add_missing_required_field(): void { /* ... */ }
    public function test_list_with_filters(): void { /* ... */ }
    public function test_delete_soft_delete(): void { /* ... */ }
}
```

### 前端测试（可选）
如果生成了前端代码，提供 API 接口测试要点：
- 接口请求参数覆盖
- 响应数据结构验证
- 错误场景处理

### 数据库测试
- DDL 执行后表结构验证
- 索引是否生效
- 字段默认值是否正确

## 输出格式
用 Markdown 输出，包含：

### 📋 测试清单
列出所有需要测试的场景（编号格式）

### 🧪 测试代码
完整可运行的测试代码（用代码块）

### 📊 覆盖率分析
- 哪些场景被覆盖了
- 哪些边界场景需要注意

## 约束
- 测试用例要具体且可执行
- 包含正常路径和异常路径
- Mock 外部依赖（数据库、HTTP 等）
- 测试方法命名清晰表达意图
PROMPT;

$check = $pdo->prepare("SELECT id FROM ai_agents WHERE name = '代码生成-测试员' AND is_del = 2 LIMIT 1");
$check->execute();
if ($existing = $check->fetchColumn()) {
    echo "  [SKIP] Tester agent already exists (id={$existing}).\n";
} else {
    $researcherModelId = $pdo->query("SELECT model_id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1")->fetchColumn() ?: 1;
    $stmt = $pdo->prepare("INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES (?, ?, ?, 'workflow', 'code_gen', 1, 2)");
    $stmt->execute(['代码生成-测试员', $researcherModelId, $testerPrompt]);
    echo "  [OK] Inserted tester agent (id={$pdo->lastInsertId()}, mode=workflow)\n";
}

// ============================================================
// 验证
// ============================================================
echo "\n=== Verification ===\n";
$agents = $pdo->query("SELECT id, name, mode, scene FROM ai_agents WHERE scene = 'code_gen' AND is_del = 2 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($agents as $a) {
    echo "  Agent: id={$a['id']} name={$a['name']} mode={$a['mode']} scene={$a['scene']}\n";
}

echo "\nDone!\n";
