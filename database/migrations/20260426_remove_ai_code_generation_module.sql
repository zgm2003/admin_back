-- 移除 AI 代码生成模块的菜单、权限、智能体和内置工具配置。
-- 只做软删除，保留历史会话 / 消息 / 运行记录用于审计。

DROP TEMPORARY TABLE IF EXISTS tmp_removed_ai_codegen_permissions;
DROP TEMPORARY TABLE IF EXISTS tmp_removed_ai_codegen_agents;
DROP TEMPORARY TABLE IF EXISTS tmp_removed_ai_codegen_tools;

CREATE TEMPORARY TABLE tmp_removed_ai_codegen_permissions (
  id INT UNSIGNED PRIMARY KEY
);

INSERT IGNORE INTO tmp_removed_ai_codegen_permissions (id)
SELECT id
FROM permission
WHERE platform = 'admin'
  AND (
    path = '/ai/genAi'
    OR component = 'ai/genAi'
    OR i18n_key IN ('menu.ai_genAi', 'menu.devTools_genAi')
    OR name = 'AI代码生成'
  );

UPDATE users_quick_entry uq
JOIN tmp_removed_ai_codegen_permissions p ON p.id = uq.permission_id
SET
  uq.is_del = 1,
  uq.updated_at = NOW()
WHERE uq.is_del = 2;

UPDATE role_permissions rp
JOIN tmp_removed_ai_codegen_permissions p ON p.id = rp.permission_id
SET
  rp.is_del = 1,
  rp.updated_at = NOW()
WHERE rp.is_del = 2;

UPDATE permission p
JOIN tmp_removed_ai_codegen_permissions removed ON removed.id = p.id
SET
  p.is_del = 1,
  p.updated_at = NOW()
WHERE p.is_del = 2;

DROP TEMPORARY TABLE tmp_removed_ai_codegen_permissions;

CREATE TEMPORARY TABLE tmp_removed_ai_codegen_agents (
  id INT UNSIGNED PRIMARY KEY
);

INSERT IGNORE INTO tmp_removed_ai_codegen_agents (id)
SELECT id
FROM ai_agents
WHERE scene LIKE 'code_gen%';

CREATE TEMPORARY TABLE tmp_removed_ai_codegen_tools (
  id INT UNSIGNED PRIMARY KEY
);

INSERT IGNORE INTO tmp_removed_ai_codegen_tools (id)
SELECT id
FROM ai_tools
WHERE code LIKE 'codegen\_%';

UPDATE ai_assistant_tools aat
LEFT JOIN tmp_removed_ai_codegen_agents a ON a.id = aat.assistant_id
LEFT JOIN tmp_removed_ai_codegen_tools t ON t.id = aat.tool_id
SET
  aat.is_del = 1,
  aat.updated_at = NOW()
WHERE aat.is_del = 2
  AND (a.id IS NOT NULL OR t.id IS NOT NULL);

UPDATE ai_agents a
JOIN tmp_removed_ai_codegen_agents removed ON removed.id = a.id
SET
  a.is_del = 1,
  a.updated_at = NOW()
WHERE a.is_del = 2;

UPDATE ai_tools t
JOIN tmp_removed_ai_codegen_tools removed ON removed.id = t.id
SET
  t.is_del = 1,
  t.updated_at = NOW()
WHERE t.is_del = 2;

DROP TEMPORARY TABLE tmp_removed_ai_codegen_agents;
DROP TEMPORARY TABLE tmp_removed_ai_codegen_tools;
