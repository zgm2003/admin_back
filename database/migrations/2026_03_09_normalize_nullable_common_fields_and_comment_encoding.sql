-- Normalize nullable Common fields and repair corrupted schema comments.
-- Keep comments ASCII-only here to avoid Windows codepage corruption during manual execution.

ALTER TABLE `notification_task`
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal';

ALTER TABLE `chat_messages`
  MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at';

ALTER TABLE `tauri_version`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal',
  MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `address`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal';

ALTER TABLE `cron_task_log`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal';

ALTER TABLE `user_profiles`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal';

ALTER TABLE `users_login_log`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted 2 normal',
  MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at';
