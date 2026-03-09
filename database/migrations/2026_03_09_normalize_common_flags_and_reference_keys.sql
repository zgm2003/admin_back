-- Normalize common soft-delete/status flags and selected reference key chains.
-- This batch intentionally leaves documented special-case columns unchanged.

-- 1) Clean invalid quick-entry data before tightening validation/index assumptions.
UPDATE `users_quick_entry`
SET `is_del` = 1,
    `updated_at` = NOW()
WHERE `permission_id` <= 0
  AND `is_del` = 2;

-- 2) Normalize signedness for core reference key chains.
ALTER TABLE `role`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `is_default` TINYINT UNSIGNED NOT NULL DEFAULT '2',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `users`
  MODIFY COLUMN `role_id` INT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `permission`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `users_quick_entry`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `permission_id` INT UNSIGNED NOT NULL COMMENT 'Permission menu ID (permission.id)',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT '1 deleted 2 normal';

ALTER TABLE `notification_task`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `created_by` INT UNSIGNED NOT NULL COMMENT 'Creator user id',
  MODIFY COLUMN `status` TINYINT UNSIGNED DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED DEFAULT '2';

ALTER TABLE `goods`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `user_sessions`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2' COMMENT '2 normal 1 deleted';

-- 3) Normalize remaining common status/is_del flags.
ALTER TABLE `ai_prompts`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `cron_task`
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `cron_task_log`
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1';

ALTER TABLE `notifications`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `system_settings`
  MODIFY COLUMN `value_type` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `test`
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '1',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `upload_driver`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `upload_rule`
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

ALTER TABLE `upload_setting`
  MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT '2',
  MODIFY COLUMN `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2';

-- 4) Add indexes that match actual code paths.
ALTER TABLE `users_quick_entry`
  ADD KEY `idx_user_permission_del` (`user_id`, `permission_id`, `is_del`);

ALTER TABLE `notification_task`
  DROP INDEX `idx_status_send`,
  ADD KEY `idx_status_del_send` (`status`, `is_del`, `send_at`);
