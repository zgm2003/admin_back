-- Queue Monitor migration: DevTools -> System
-- Safe because code compatibility is kept on both old and new backend/frontend paths.

UPDATE `permission`
SET
  `parent_id` = 3,
  `path` = '/system/queueMonitor',
  `component` = 'system/queueMonitor',
  `sort` = 6,
  `i18n_key` = 'menu.system_queueMonitor'
WHERE `id` = 56 AND `is_del` = 2;

-- Rollback reference
-- UPDATE `permission`
-- SET
--   `parent_id` = 6,
--   `path` = '/devTools/queueMonitor',
--   `component` = 'devTools/queueMonitor',
--   `sort` = 2,
--   `i18n_key` = 'menu.devTools_queueMonitor'
-- WHERE `id` = 56 AND `is_del` = 2;
