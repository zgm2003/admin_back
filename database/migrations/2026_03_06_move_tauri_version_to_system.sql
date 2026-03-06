-- Tauri Version migration: DevTools -> System
-- Keeps existing permission codes `devTools_tauriVersion_*` unchanged for compatibility.
-- Public client init endpoint path remains `/api/TauriVersion/clientInit`.
-- To avoid sort collision in environments where old system/test still exists,
-- this script places tauri version at sort = 10.

UPDATE `permission`
SET
  `parent_id` = 3,
  `path` = '/system/tauriVersion',
  `component` = 'system/tauriVersion',
  `sort` = 10,
  `i18n_key` = 'menu.system_tauriVersion'
WHERE `id` = 60 AND `is_del` = 2;

-- Rollback reference
-- UPDATE `permission`
-- SET
--   `parent_id` = 6,
--   `path` = '/devTools/tauriVersion',
--   `component` = 'devTools/tauriVersion',
--   `sort` = 6,
--   `i18n_key` = 'menu.devTools_tauriVersion'
-- WHERE `id` = 60 AND `is_del` = 2;
