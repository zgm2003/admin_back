-- Cron Task migration: DevTools -> System
-- Keeps existing permission codes `devTools_cronTask_*` unchanged for compatibility.
-- To avoid sort collision in environments where old system/test still exists,
-- this script places cron task at sort = 9.

UPDATE `permission`
SET
  `parent_id` = 3,
  `path` = '/system/cronTask',
  `component` = 'system/cronTask',
  `sort` = 9,
  `i18n_key` = 'menu.system_cronTask'
WHERE `id` = 59 AND `is_del` = 2;

-- Rollback reference
-- UPDATE `permission`
-- SET
--   `parent_id` = 6,
--   `path` = '/devTools/cronTask',
--   `component` = 'devTools/cronTask',
--   `sort` = 5,
--   `i18n_key` = 'menu.devTools_cronTask'
-- WHERE `id` = 59 AND `is_del` = 2;
