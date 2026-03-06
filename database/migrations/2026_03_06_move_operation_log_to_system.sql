-- Operation Log migration: DevTools -> System
-- Note: in current MCP database, system test menu (id = 25) still exists.
-- To avoid sort collision, this script places operation log at sort = 7.

UPDATE `permission`
SET
  `parent_id` = 3,
  `path` = '/system/operationLog',
  `component` = 'system/operationLog',
  `sort` = 7,
  `i18n_key` = 'menu.system_operationLog'
WHERE `id` = 57 AND `is_del` = 2;

-- Rollback reference
-- UPDATE `permission`
-- SET
--   `parent_id` = 6,
--   `path` = '/devTools/operationLog',
--   `component` = 'devTools/operationLog',
--   `sort` = 3,
--   `i18n_key` = 'menu.devTools_operationLog'
-- WHERE `id` = 57 AND `is_del` = 2;
