-- ============================================================
-- 数据库 Schema 统一迁移脚本
-- 创建时间: 2026-03-10
-- 影响范围: admin 数据库 36 张表
-- ============================================================
--
-- 【执行前必读】
-- 1. 务必先备份数据库: mysqldump -u root -p admin > admin_backup_$(date +%Y%m%d).sql
-- 2. 建议在业务低峰期执行（Phase 1 会锁表重建）
-- 3. 执行前运行数据安全检查（见 design.md 第 7.2 节）
-- 4. 可分三个 Phase 逐段执行，每段执行后验证
--
-- 【执行顺序】
-- Phase 1: Collation 统一 (14 张表)
-- Phase 2: 索引重构 (6 ADD + 13 DROP)
-- Phase 3: 字段类型归一 (11 MODIFY)
--
-- ============================================================


-- ============================================================
-- Phase 1: Collation 统一 → utf8mb4_0900_ai_ci
-- 影响：14 张表（10 general_ci + 4 unicode_ci）
-- 注意：CONVERT TO 会重建表，低峰期执行
-- ============================================================

-- general_ci → 0900_ai_ci (10 张)
ALTER TABLE address           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE cron_task          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE cron_task_log      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE operation_logs     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE permission         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE role               CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE role_permissions   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE upload_driver      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE upload_rule        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE users              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- unicode_ci → 0900_ai_ci (4 张)
ALTER TABLE goods              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE notification_task  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE notifications      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE users_login_log    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- ============================================================
-- Phase 2: 索引重构
-- 原则：先 ADD 新索引，再 DROP 旧索引，避免中间态查询退化
-- ============================================================

-- ---- 第一步：ADD 新索引 (6 个) ----

-- notifications: 支持按用户+平台+删除状态查询
ALTER TABLE notifications
  ADD INDEX idx_user_platform_del_id (user_id, is_del, id);

-- cron_task_log: 支持按任务 ID 和任务名称查询
ALTER TABLE cron_task_log
  ADD INDEX idx_task_del_id (task_id, is_del),
  ADD INDEX idx_name_del_id (task_name, is_del);

-- operation_logs: 支持按删除状态+创建时间游标分页
ALTER TABLE operation_logs
  ADD INDEX idx_del_created_id (is_del, created_at, id);

-- goods: 支持按删除状态+商品状态查询
ALTER TABLE goods
  ADD INDEX idx_del_status_id (is_del, status, id);

-- chat_messages: 支持按会话+删除状态游标分页
ALTER TABLE chat_messages
  ADD INDEX idx_conv_del_id (conversation_id, is_del, id);

-- ---- 第二步：DROP 冗余索引 (13 个) ----

-- ai_assistant_tools: 被 idx_assistant_del_status 覆盖
ALTER TABLE ai_assistant_tools DROP INDEX idx_is_del;

-- ai_tools: 被 idx_del_status_id 覆盖
ALTER TABLE ai_tools DROP INDEX idx_status;

-- chat_messages: 删除低选择性和被覆盖的索引
ALTER TABLE chat_messages
  DROP INDEX idx_is_del,
  DROP INDEX idx_sender_id,
  DROP INDEX idx_conversation_id;

-- cron_task_log: 删除低选择性和被新索引覆盖的索引
ALTER TABLE cron_task_log
  DROP INDEX idx_status,
  DROP INDEX idx_start_time,
  DROP INDEX idx_task_id,
  DROP INDEX idx_task_name;

-- notifications: 被新 idx_user_platform_del_id 覆盖
ALTER TABLE notifications DROP INDEX idx_user_read;

-- ai_runs: 删除被其他索引左前缀覆盖的索引
ALTER TABLE ai_runs
  DROP INDEX idx_conv_created,
  DROP INDEX idx_del_created,
  DROP INDEX idx_status_del_created;


-- ============================================================
-- Phase 3: 字段类型归一
-- ============================================================

-- ---- signed int → unsigned (7 列) ----

ALTER TABLE ai_prompts         MODIFY COLUMN sort         int unsigned NOT NULL DEFAULT 0 COMMENT '排序，越大越前';
ALTER TABLE permission         MODIFY COLUMN sort         int unsigned NOT NULL DEFAULT 0 COMMENT '排序';
ALTER TABLE users_quick_entry  MODIFY COLUMN sort         int unsigned NOT NULL DEFAULT 0 COMMENT '排序';
ALTER TABLE cron_task_log      MODIFY COLUMN duration_ms  int unsigned DEFAULT NULL COMMENT '执行耗时(毫秒)';
ALTER TABLE upload_rule        MODIFY COLUMN max_size_mb  int unsigned NOT NULL DEFAULT 5 COMMENT '最大 MB';

ALTER TABLE notification_task  MODIFY COLUMN total_count  int unsigned NOT NULL DEFAULT 0 COMMENT '目标用户数';
ALTER TABLE notification_task  MODIFY COLUMN sent_count   int unsigned NOT NULL DEFAULT 0 COMMENT '已发送数';

-- ---- text/varchar → json (4 列) ----

ALTER TABLE goods        MODIFY COLUMN image_list          json DEFAULT NULL COMMENT '轮播图片列表(JSON)';
ALTER TABLE goods        MODIFY COLUMN image_list_success  json DEFAULT NULL COMMENT '选中图片列表(JSON)';
ALTER TABLE upload_rule  MODIFY COLUMN image_exts          json NOT NULL COMMENT '允许的图片扩展名';
ALTER TABLE upload_rule  MODIFY COLUMN file_exts           json NOT NULL COMMENT '允许的通用文件扩展名';


-- ============================================================
-- 执行完成后验证
-- ============================================================
--
-- 验证 Phase 1 (Collation):
-- SELECT TABLE_COLLATION, COUNT(*) AS cnt
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = 'admin' AND TABLE_TYPE = 'BASE TABLE'
-- GROUP BY TABLE_COLLATION;
-- 预期：只有 utf8mb4_0900_ai_ci 一行，cnt=36
--
-- 验证 Phase 2 (索引):
-- SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA='admin' AND TABLE_NAME='ai_runs' AND INDEX_NAME != 'PRIMARY'
-- GROUP BY INDEX_NAME;
-- 预期：5 个非主键索引
--
-- SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA='admin' AND TABLE_NAME='chat_messages' AND INDEX_NAME != 'PRIMARY'
-- GROUP BY INDEX_NAME;
-- 预期：1 个非主键索引 (idx_conv_del_id)
--
-- ============================================================
