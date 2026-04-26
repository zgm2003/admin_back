-- Rename RBAC entity tables to plural names.
-- Run in a deployment window together with the matching PHP model changes.

RENAME TABLE `permission` TO `permissions`;
RENAME TABLE `role` TO `roles`;

ALTER TABLE `permissions` RENAME INDEX `uniq_platform_code` TO `uk_permissions_platform_code`;
ALTER TABLE `permissions` RENAME INDEX `idx_platform` TO `idx_permissions_platform`;
ALTER TABLE `permissions` RENAME INDEX `idx_parent_sort` TO `idx_permissions_parent_sort`;
ALTER TABLE `permissions` RENAME INDEX `idx_status_del` TO `idx_permissions_status_del_platform_type`;

ALTER TABLE `roles` RENAME INDEX `uniq_role_tenant_name` TO `uk_roles_name`;
ALTER TABLE `roles` RENAME INDEX `idx_role_default_del` TO `idx_roles_default_del`;
