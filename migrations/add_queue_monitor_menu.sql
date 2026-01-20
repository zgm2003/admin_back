-- ============================================================
-- 迁移脚本：队列监控功能 + 操作日志菜单调整
-- 
-- 执行前需要确认：
-- 1. 开发工具的菜单 ID（假设开发工具的 parent_id 需要查询）
-- 2. 操作日志的菜单 ID
-- ============================================================

-- 查询开发工具菜单的 ID（执行此查询获取 parent_id）
-- SELECT id FROM permission WHERE name = '开发工具' AND type = 1 AND is_del = 2;

-- 假设开发工具菜单 ID 为 @devToolsId，请根据实际情况替换

-- 1. 添加队列监控菜单
INSERT INTO `permission` (`name`, `path`, `icon`, `parent_id`, `component`, `status`, `type`, `sort`, `code`, `i18n_key`, `keep_alive`, `show_menu`, `is_del`)
SELECT '队列监控', '/devTools/queueMonitor', 'Monitor', id, 'devTools/queueMonitor', 1, 2, 2, 'devTools.queueMonitor.list', 'menu.devTools_queueMonitor', 0, 1, 2
FROM permission WHERE i18n_key = 'menu.devTools' AND is_del = 2 LIMIT 1;

-- 2. 将操作日志从系统管理移动到开发工具下
UPDATE `permission` 
SET `parent_id` = (SELECT id FROM (SELECT id FROM permission WHERE i18n_key = 'menu.devTools' AND is_del = 2 LIMIT 1) AS t),
    `path` = '/devTools/operationLog',
    `component` = 'devTools/operationLog',
    `i18n_key` = 'menu.devTools_operationLog',
    `sort` = 3
WHERE `i18n_key` = 'menu.system_operationLog' AND `is_del` = 2;

-- 3. 更新操作日志删除按钮的权限code
UPDATE `permission`
SET `code` = 'devTools_operationLog_del'
WHERE `code` = 'system_operationLog_del' AND `is_del` = 2;

-- 4. 为超级管理员角色添加新菜单权限（如果需要）
-- 首先获取新添加的队列监控菜单 ID
-- INSERT INTO role_permission ... (根据你的权限系统实现)

-- ============================================================
-- 注意：执行后需要重新登录或刷新页面以更新菜单
-- ============================================================
