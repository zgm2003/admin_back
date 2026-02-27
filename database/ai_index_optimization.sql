-- AI module index optimization
-- Apply in database: admin

USE `admin`;

-- 1) Optimize query: get running run by conversation
-- Query pattern:
--   WHERE conversation_id = ? AND run_status = ? AND is_del = ? ORDER BY id DESC LIMIT 1
ALTER TABLE `ai_runs`
  ADD INDEX `idx_conv_status_del_id` (`conversation_id`, `run_status`, `is_del`, `id`);

-- 2) Optional: optimize active tool bindings lookup
-- Query pattern:
--   WHERE assistant_id = ? AND is_del = ? AND status = ?
ALTER TABLE `ai_assistant_tools`
  ADD INDEX `idx_assistant_del_status` (`assistant_id`, `is_del`, `status`);

-- 3) Optional: optimize active tools list
-- Query pattern:
--   WHERE is_del = ? AND status = ? ORDER BY id DESC
ALTER TABLE `ai_tools`
  ADD INDEX `idx_del_status_id` (`is_del`, `status`, `id`);
