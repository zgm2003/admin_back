-- Unify users.id and all direct user reference columns to INT UNSIGNED
-- This batch intentionally normalizes the user primary key chain before launch.

ALTER TABLE `users`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_profiles`
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL;

ALTER TABLE `users_quick_entry`
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL;

ALTER TABLE `user_sessions`
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL;

ALTER TABLE `users_login_log`
  MODIFY COLUMN `user_id` INT UNSIGNED NULL;

ALTER TABLE `operation_logs`
  MODIFY COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT '0';
