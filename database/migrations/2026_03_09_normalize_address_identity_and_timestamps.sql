-- Normalize address identity and timestamps.
-- Keep parent_id signed because -1 is the root sentinel in application code.

ALTER TABLE `address`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Region id',
  MODIFY COLUMN `parent_id` INT DEFAULT NULL COMMENT 'Parent region id; -1 means root',
  MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created time',
  MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated time';
