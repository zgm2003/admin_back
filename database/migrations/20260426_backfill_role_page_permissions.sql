-- Normalize existing RBAC grants after PAGE becomes an explicit assignable permission.
-- BUTTON grants imply their parent PAGE when that parent exists; DIR grants remain display-only and are not inserted.

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `is_del`, `created_at`, `updated_at`)
SELECT DISTINCT
  rp.`role_id`,
  page.`id` AS `permission_id`,
  2 AS `is_del`,
  NOW() AS `created_at`,
  NOW() AS `updated_at`
FROM `role_permissions` rp
JOIN `permissions` btn
  ON btn.`id` = rp.`permission_id`
JOIN `permissions` page
  ON page.`id` = btn.`parent_id`
WHERE rp.`is_del` = 2
  AND btn.`is_del` = 2
  AND btn.`type` = 3
  AND page.`is_del` = 2
  AND page.`type` = 2
ON DUPLICATE KEY UPDATE
  `is_del` = 2,
  `updated_at` = NOW();
