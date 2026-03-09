-- Normalize address identity and timestamps.
-- Historical step before root-parent contract was later normalized to 0.

ALTER TABLE `address`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Region id',
  MODIFY COLUMN `parent_id` INT DEFAULT NULL COMMENT 'Parent region id; later normalized to 0-root',
  MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created time',
  MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated time';
