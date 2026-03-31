SET @pay_parent_id := (
  SELECT id
  FROM permission
  WHERE platform = 'admin'
    AND code = 'pay_payManager'
    AND is_del = 2
  LIMIT 1
);

INSERT INTO permission (
  parent_id,
  name,
  path,
  icon,
  component,
  platform,
  type,
  sort,
  code,
  i18n_key,
  show_menu,
  status,
  is_del,
  created_at,
  updated_at
)
SELECT
  @pay_parent_id,
  '回调审计',
  '/pay/notify',
  '',
  'pay/notify',
  'admin',
  2,
  8,
  'pay_notify_list',
  'menu.pay_notify',
  1,
  1,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE @pay_parent_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM permission
    WHERE platform = 'admin'
      AND code = 'pay_notify_list'
      AND is_del = 2
  );

UPDATE permission
SET sort = 9
WHERE platform = 'admin'
  AND code = 'pay_reconcile_list'
  AND is_del = 2
  AND sort = 8;

SET @pay_notify_id := (
  SELECT id
  FROM permission
  WHERE platform = 'admin'
    AND code = 'pay_notify_list'
    AND is_del = 2
  LIMIT 1
);

INSERT INTO role_permissions (
  role_id,
  permission_id,
  is_del,
  created_at,
  updated_at
)
SELECT
  2,
  @pay_notify_id,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE @pay_notify_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions
    WHERE role_id = 2
      AND permission_id = @pay_notify_id
      AND is_del = 2
  );

INSERT INTO permission (
  parent_id,
  name,
  path,
  icon,
  component,
  platform,
  type,
  sort,
  code,
  i18n_key,
  show_menu,
  status,
  is_del,
  created_at,
  updated_at
)
SELECT
  @pay_notify_id,
  '查看回调日志',
  '',
  '',
  NULL,
  'admin',
  3,
  10,
  'pay_notify_view',
  '',
  1,
  1,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE @pay_notify_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM permission
    WHERE platform = 'admin'
      AND code = 'pay_notify_view'
      AND is_del = 2
  );

INSERT INTO permission (
  parent_id,
  name,
  path,
  icon,
  component,
  platform,
  type,
  sort,
  code,
  i18n_key,
  show_menu,
  status,
  is_del,
  created_at,
  updated_at
)
SELECT
  104,
  '下载对账文件',
  '',
  '',
  NULL,
  'admin',
  3,
  20,
  'pay_reconcile_download',
  '',
  1,
  1,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM permission
  WHERE platform = 'admin'
    AND code = 'pay_reconcile_download'
    AND is_del = 2
);

SET @pay_notify_view_id := (
  SELECT id
  FROM permission
  WHERE platform = 'admin'
    AND code = 'pay_notify_view'
    AND is_del = 2
  LIMIT 1
);

SET @pay_reconcile_download_id := (
  SELECT id
  FROM permission
  WHERE platform = 'admin'
    AND code = 'pay_reconcile_download'
    AND is_del = 2
  LIMIT 1
);

INSERT INTO role_permissions (
  role_id,
  permission_id,
  is_del,
  created_at,
  updated_at
)
SELECT
  2,
  @pay_notify_view_id,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE @pay_notify_view_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions
    WHERE role_id = 2
      AND permission_id = @pay_notify_view_id
      AND is_del = 2
  );

INSERT INTO role_permissions (
  role_id,
  permission_id,
  is_del,
  created_at,
  updated_at
)
SELECT
  2,
  @pay_reconcile_download_id,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE @pay_reconcile_download_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM role_permissions
    WHERE role_id = 2
      AND permission_id = @pay_reconcile_download_id
      AND is_del = 2
  );
