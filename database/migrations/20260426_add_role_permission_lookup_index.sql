-- Add reverse lookup index used when invalidating role/user permission caches by permission id.

ALTER TABLE `role_permissions`
  ADD INDEX `idx_role_permissions_permission_del_role` (`permission_id`, `is_del`, `role_id`);
