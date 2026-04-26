-- 移除旧的“APP按钮权限”后台菜单入口。
-- 统一改由“后台菜单管理”的平台切换维护 PC后台 / H5/APP 的目录、页面和按钮权限。
-- 已登录用户的按钮权限缓存通过 PermissionService::BUTTON_CACHE_KEY_VERSION 版本化失效。

DROP TEMPORARY TABLE IF EXISTS tmp_remove_app_button_permissions;

CREATE TEMPORARY TABLE tmp_remove_app_button_permissions (
  id INT UNSIGNED PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_remove_app_button_permissions (id)
SELECT id
FROM permission
WHERE is_del = 2
  AND platform = 'admin'
  AND (
    path = '/permission/appButton'
    OR component = 'permission/appButton'
    OR i18n_key = 'menu.permission.appButton'
    OR code LIKE 'permission\_appButton\_%'
    OR parent_id IN (
      SELECT id FROM (
        SELECT id
        FROM permission
        WHERE is_del = 2
          AND platform = 'admin'
          AND (
            path = '/permission/appButton'
            OR component = 'permission/appButton'
            OR i18n_key = 'menu.permission.appButton'
          )
      ) AS parent_ids
    )
  );

UPDATE users_quick_entry uq
JOIN tmp_remove_app_button_permissions p ON p.id = uq.permission_id
SET
  uq.is_del = 1,
  uq.updated_at = NOW()
WHERE uq.is_del = 2;

UPDATE role_permissions rp
JOIN tmp_remove_app_button_permissions p ON p.id = rp.permission_id
SET
  rp.is_del = 1,
  rp.updated_at = NOW()
WHERE rp.is_del = 2;

UPDATE permission p
JOIN tmp_remove_app_button_permissions removed ON removed.id = p.id
SET
  p.is_del = 1,
  p.updated_at = NOW()
WHERE p.is_del = 2;

DROP TEMPORARY TABLE tmp_remove_app_button_permissions;
