-- Upgrade legacy role.permission_id payload storage from VARCHAR(255) JSON string to native JSON.
-- This removes the 255-char ceiling while keeping the existing denormalized contract stable.

UPDATE `role`
SET `permission_id` = '[]'
WHERE `permission_id` IS NULL
   OR `permission_id` = ''
   OR JSON_VALID(`permission_id`) = 0;

ALTER TABLE `role`
  MODIFY COLUMN `permission_id` JSON NOT NULL COMMENT '??ID??(JSON??)';
