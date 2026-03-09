-- Split role.permission_id JSON payload into a normalized role_permissions pivot table.
-- Run this before enabling the refactored role permission code path.

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL COMMENT 'role.id',
  `permission_id` INT UNSIGNED NOT NULL COMMENT 'permission.id',
  `is_del` TINYINT UNSIGNED NOT NULL DEFAULT '2',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uniq_role_permission` (`role_id`, `permission_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='role permission pivot';

SET @has_role_permission_json := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'role'
    AND COLUMN_NAME = 'permission_id'
);

SET @backfill_role_permissions_sql := IF(
  @has_role_permission_json > 0,
  'INSERT INTO `role_permissions` (`role_id`, `permission_id`, `is_del`, `created_at`, `updated_at`)
   SELECT DISTINCT
     r.`id`,
     jt.`permission_id`,
     r.`is_del`,
     r.`created_at`,
     r.`updated_at`
   FROM `role` r
   JOIN JSON_TABLE(r.`permission_id`, ''$[*]'' COLUMNS (`permission_id` INT PATH ''$'')) jt
   WHERE jt.`permission_id` > 0
   ON DUPLICATE KEY UPDATE
     `is_del` = VALUES(`is_del`),
     `updated_at` = VALUES(`updated_at`)',
  'SELECT 1'
);

PREPARE stmt FROM @backfill_role_permissions_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `role`
  DROP COLUMN `permission_id`;
