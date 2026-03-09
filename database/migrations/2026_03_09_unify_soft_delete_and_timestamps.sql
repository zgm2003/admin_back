-- Unify soft delete and audit timestamp fields for remaining legacy tables.

ALTER TABLE `address`
  CHANGE COLUMN `create_time` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
  CHANGE COLUMN `update_time` `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
  ADD COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT 'soft delete flag: 1 deleted, 2 active' AFTER `name`;

ALTER TABLE `user_profiles`
  ADD COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT 'soft delete flag: 1 deleted, 2 active' AFTER `detail_address`;

ALTER TABLE `users_login_log`
  ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at' AFTER `created_at`,
  ADD COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT 'soft delete flag: 1 deleted, 2 active' AFTER `reason`;

ALTER TABLE `tauri_version`
  ADD COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT 'soft delete flag: 1 deleted, 2 active' AFTER `force_update`;

ALTER TABLE `tauri_version`
  DROP INDEX `uk_version_platform`,
  ADD UNIQUE INDEX `uk_version_platform_del` (`version`, `platform`, `is_del`) USING BTREE;

ALTER TABLE `cron_task_log`
  ADD COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT 'soft delete flag: 1 deleted, 2 active' AFTER `error_msg`;
