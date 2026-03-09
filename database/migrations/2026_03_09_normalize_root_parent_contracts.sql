-- Normalize address and permission root parent_id contract to 0.
-- This removes the legacy -1 sentinel and allows unsigned parent references.

UPDATE `address`
SET `parent_id` = 0
WHERE `parent_id` IS NULL OR `parent_id` < 0;

ALTER TABLE `address`
  MODIFY COLUMN `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'parent region id; 0 means root';

UPDATE `permission`
SET `parent_id` = 0
WHERE `parent_id` < 0;

ALTER TABLE `permission`
  MODIFY COLUMN `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'parent permission id; 0 means root';
