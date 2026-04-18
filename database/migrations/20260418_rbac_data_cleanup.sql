-- 1) 历史通知旧路由统一切到当前导出任务页
UPDATE notifications
SET
  link = '/system/exportTask',
  updated_at = NOW()
WHERE is_del = 2
  AND link = '/devTools/exportTask';

UPDATE notifications
SET
  link = REPLACE(link, '/devTools/exportTask', '/system/exportTask'),
  updated_at = NOW()
WHERE is_del = 2
  AND link LIKE '/devTools/exportTask?%';

-- 2) 快捷入口只允许页面权限，清理历史非页面/失效引用
UPDATE users_quick_entry uq
LEFT JOIN permission p
  ON p.id = uq.permission_id
 AND p.is_del = 2
SET
  uq.is_del = 1,
  uq.updated_at = NOW()
WHERE uq.is_del = 2
  AND (p.id IS NULL OR p.type <> 2);

-- 3) 角色权限不再保留已禁用/已失效权限引用
UPDATE role_permissions rp
LEFT JOIN permission p
  ON p.id = rp.permission_id
 AND p.is_del = 2
SET
  rp.is_del = 1,
  rp.updated_at = NOW()
WHERE rp.is_del = 2
  AND (p.id IS NULL OR p.status <> 1);
