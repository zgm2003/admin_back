-- Chat 模块 Schema 优化
-- 执行时间：2026-02-14

-- ============================================================
-- 1. chat_messages: 优化游标分页索引
--    原索引 (conversation_id ASC, id ASC) 用于 ORDER BY id DESC 需要反向扫描
--    改为 (conversation_id ASC, id DESC) 直接顺序扫描，提升分页查询性能
-- ============================================================
ALTER TABLE `chat_messages`
    DROP INDEX `idx_conversation_id`,
    ADD INDEX `idx_conversation_id` (`conversation_id` ASC, `id` DESC) USING BTREE;

-- ============================================================
-- 2. chat_conversations: last_message_at 默认值改为 CURRENT_TIMESTAMP
--    原默认值 '2000-01-01 00:00:00' 是魔法值，语义不清晰
--    改为 CURRENT_TIMESTAMP，新建会话的排序更合理
-- ============================================================
ALTER TABLE `chat_conversations`
    ALTER COLUMN `last_message_at` SET DEFAULT (CURRENT_TIMESTAMP);

-- ============================================================
-- 3. chat_messages: 添加 updated_at 字段
--    为未来消息编辑/撤回功能预留
-- ============================================================
ALTER TABLE `chat_messages`
    ADD COLUMN `updated_at` datetime NULL DEFAULT NULL COMMENT '更新时间（编辑/撤回）' AFTER `created_at`;

-- ============================================================
-- 4. chat_conversations: 添加 type 索引
--    findPrivateConversation 查询需要按 type 过滤
--    配合 is_del 组成覆盖条件
-- ============================================================
ALTER TABLE `chat_conversations`
    ADD INDEX `idx_type_del` (`type` ASC, `is_del` ASC) USING BTREE;
