-- Export Task migration: DevTools -> System
-- Frontend keeps legacy notification-link normalization for old records.
-- To avoid sort collision in environments where old system/test still exists,
-- this script places export task at sort = 8.

UPDATE `permission`
SET
  `parent_id` = 3,
  `path` = '/system/exportTask',
  `component` = 'system/exportTask',
  `sort` = 8,
  `i18n_key` = 'menu.system_exportTask'
WHERE `id` = 58 AND `is_del` = 2;

-- Rollback reference
-- UPDATE `permission`
-- SET
--   `parent_id` = 6,
--   `path` = '/devTools/exportTask',
--   `component` = 'devTools/exportTask',
--   `sort` = 4,
--   `i18n_key` = 'menu.devTools_exportTask'
-- WHERE `id` = 58 AND `is_del` = 2;
