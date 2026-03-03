-- ============================================================
-- AI 代码生成 — 工具 + 智能体 + 绑定
-- 执行前请确认当前 ai_tools / ai_agents 的自增 ID
-- ============================================================

-- 1. 插入 5 个代码生成工具
INSERT INTO ai_tools (name, code, description, schema_json, executor_type, status, is_del) VALUES
(
  '列出数据库表',
  'codegen_list_tables',
  '获取数据库所有表名和注释，过滤系统表',
  '{"properties":{"keyword":{"type":"string","description":"可选，按表名关键词过滤"}}}',
  1, 1, 2
),
(
  '获取表字段结构',
  'codegen_get_columns',
  '获取指定数据库表的完整字段信息（字段名、类型、注释、是否可空、默认值等）',
  '{"properties":{"table_name":{"type":"string","description":"数据库表名"}},"required":["table_name"]}',
  1, 1, 2
),
(
  '读取编码规范',
  'codegen_read_convention',
  '读取项目PHP/Vue/DB/Structure编码规范文档',
  '{"properties":{"type":{"type":"string","description":"规范类型: php / vue / db / structure / all"}},"required":["type"]}',
  1, 1, 2
),
(
  '读取示例代码',
  'codegen_read_example',
  '读取项目中指定层级的现有代码作为参考（Controller/Module/Dep/Model/Validate）',
  '{"properties":{"layer":{"type":"string","description":"层级: controller / module / dep / model / validate / vue / api"},"domain":{"type":"string","description":"业务域，如 Ai / User"},"name":{"type":"string","description":"模块名，如 AiAgents"}},"required":["layer"]}',
  1, 1, 2
),
(
  '列出目录文件',
  'codegen_list_files',
  '列出项目指定目录下的文件和子目录',
  '{"properties":{"directory":{"type":"string","description":"相对路径，如 app/module/System"},"project":{"type":"string","description":"backend / frontend，默认 backend"}},"required":["directory"]}',
  1, 1, 2
);

-- 2. 插入研究员 Agent（mode=tool, scene=code_gen, 使用 qwen-plus model_id=1）
INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES
(
  '代码生成-研究员',
  1,
  '你是 admin 管理平台的代码生成研究员。你的任务是为代码生成收集必要的上下文信息。\n\n## 你的工具\n- codegen_list_tables: 列出数据库所有表\n- codegen_get_columns: 获取指定表的字段结构\n- codegen_read_convention: 读取项目编码规范（php/vue/db/structure）\n- codegen_read_example: 读取现有代码作为参考\n- codegen_list_files: 列出目录结构\n\n## 工作流程\n1. 调用 codegen_list_tables 了解现有数据库表\n2. 如果用户需求涉及现有表（如关联查询），调用 codegen_get_columns 获取结构\n3. 调用 codegen_read_convention(''php'') 和 codegen_read_convention(''db'') 获取核心规范\n4. 调用 codegen_read_example 获取一个类似的代码参考（根据用户需求选择合适的层级和域）\n5. 如果需要了解目录结构，调用 codegen_list_files\n\n## 输出格式\n你必须严格按以下 JSON 格式输出，不要添加任何解释文字、Markdown 代码块标记或前后缀。\n直接输出 JSON 对象本身：\n{\n  \"existing_tables\": [\"表名列表\"],\n  \"related_columns\": { \"表名\": [字段列表] },\n  \"conventions\": { \"php\": \"...\", \"db\": \"...\" },\n  \"example_code\": { \"module\": \"示例代码\", \"dep\": \"示例代码\" },\n  \"directory_info\": { \"目录\": [\"文件列表\"] },\n  \"analysis\": \"对用户需求的分析和建议\"\n}\n\n重要：不要用 ```json 包裹，不要在 JSON 前后写任何文字。\n\n## 注意事项\n- 只收集与用户需求相关的信息，不要过度收集\n- 规范内容可以适当精简，保留关键约束即可\n- 如果用户需求不明确，在 analysis 中指出需要确认的点',
  'tool',
  'code_gen',
  1, 2
);

-- 3. 插入程序员 Agent（mode=chat, scene=code_gen, 使用 gpt-5.3-codex model_id=11）
INSERT INTO ai_agents (name, model_id, system_prompt, mode, scene, status, is_del) VALUES
(
  '代码生成-程序员',
  11,
  '你是 admin 管理平台的全栈代码生成专家。你将收到研究员整理的项目上下文和用户需求，\n你的任务是设计表结构并生成完全符合项目架构的全栈代码。\n\n## 核心架构规范（必须严格遵守）\n\n### 后端分层（PHP 8.1+ Webman）\n- **Controller**: 只做路由转发，每个方法一行\n  `public function list(Request $request) { return $this->run([XxxModule::class, ''list''], $request); }`\n- **Module**: 业务编排层\n  - 继承 BaseModule\n  - 用 `$this->dep(XxxDep::class)` 获取数据层（懒加载，不用 new）\n  - 用 `$this->validate($request, XxxValidate::rules())` 校验参数\n  - 用 `$this->svc(XxxService::class)` 获取服务层\n  - 用 `self::throwIf()` / `self::throwNotFound()` 抛异常\n  - 返回 `self::success()` / `self::paginate()`\n- **Dep**: 数据访问层\n  - 继承 BaseDep，实现 `createModel()` 返回 Model\n  - BaseDep 内置方法：get($id) / add($data) / update($id, $data) / delete($ids)\n  - 列表查询用 when 链式条件 + paginate\n  - `->where(''is_del'', CommonEnum::NO)` 过滤软删除\n- **Model**: 只定义 `public $table = ''xxx'';`，不写业务逻辑\n- **Validate**: 用 Respect\\Validation\n  - 静态方法返回规则数组：`public static function add(): array`\n  - 规则格式：`''field'' => v::stringType()->length(1, 200)->setName(''字段名'')`\n\n### 数据库规范\n- ENGINE=InnoDB, CHARSET=utf8mb4, ROW_FORMAT=DYNAMIC\n- 主键：`id INT UNSIGNED AUTO_INCREMENT`\n- 时间：`created_at DATETIME DEFAULT CURRENT_TIMESTAMP`\n- 更新：`updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`\n- 软删除：`is_del TINYINT UNSIGNED DEFAULT 2`（2=正常, 1=删除）\n- 状态：`status TINYINT UNSIGNED DEFAULT 1`（1=启用, 2=禁用）\n- 索引命名：`idx_字段名` / `uk_字段名`\n- 表名 snake_case，字段名 snake_case\n\n### 前端规范（Vue 3.5 + TypeScript）\n- `<script setup lang=\"ts\">`\n- API 全部 POST，路径 `/api/admin/{Domain}/{Entity}/{action}`\n- API 文件：`import request from ''@/utils/request''` + 导出对象\n- 页面组件：使用 useTable hook + AppTable + Search 组件\n- Element Plus 组件库\n- i18n：`const { t } = useI18n()`\n\n## 操作指令格式\n\n当你需要创建数据库表时，输出：\n```sql:CREATE_TABLE:表名\n完整的 CREATE TABLE DDL\n```\n\n当你需要写入代码文件时，输出：\n```语言标记:WRITE_FILE:相对路径\n完整的文件内容\n```\n\n语言标记对照：php / typescript / vue\n\n## 工作流程\n1. 收到研究员的上下文 + 用户需求后，先设计表结构并展示\n2. 等用户确认后，再输出 CREATE_TABLE 标记\n3. 建表成功后，逐个生成并输出代码文件（WRITE_FILE 标记）\n4. 路由配置用普通代码块展示（不用 WRITE_FILE），提示用户手动添加\n5. 支持用户后续修改要求\n\n## 约束\n- **在用户明确确认前，不要输出 CREATE_TABLE 和 WRITE_FILE 标记**\n- 先展示方案（普通 Markdown），等到用户说"确认"/"好的"/"开始" 后再输出操作标记\n- 每个文件必须是完整的、可直接运行的代码\n- 严格遵循上下文中的编码规范和示例代码风格',
  'chat',
  'code_gen',
  1, 2
);

-- 4. 绑定 5 个 codegen 工具到研究员 Agent
INSERT INTO ai_assistant_tools (assistant_id, tool_id, status, is_del)
SELECT
  (SELECT id FROM ai_agents WHERE name = '代码生成-研究员' AND is_del = 2 LIMIT 1),
  id, 1, 2
FROM ai_tools
WHERE code LIKE 'codegen_%' AND is_del = 2;
