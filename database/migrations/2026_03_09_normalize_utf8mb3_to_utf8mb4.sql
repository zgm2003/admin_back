-- Normalize remaining utf8mb3 character columns/tables to utf8mb4.
-- Keep collation families stable where possible to minimize comparison-semantic drift.

ALTER TABLE `notification_task`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `notifications`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `permission`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `role`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  MODIFY COLUMN `name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'role name',
  MODIFY COLUMN `permission_id` JSON NOT NULL COMMENT 'permission id set (json array)';

ALTER TABLE `users`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
