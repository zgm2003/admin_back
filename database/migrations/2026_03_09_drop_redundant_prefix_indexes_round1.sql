-- Drop redundant prefix indexes verified against current code paths and EXPLAIN plans.
-- Safe round 1:
-- 1) ai_assistant_tools.idx_assistant_id is covered by uniq_assistant_tool(assistant_id, tool_id)
--    and idx_assistant_del_status(assistant_id, is_del, status).
-- 2) ai_tools.idx_is_del is covered by idx_del_status_id(is_del, status, id).
-- 3) upload_setting.idx_driver is covered by uniq_driver_rule(driver_id, rule_id).
--
-- Intentionally NOT dropped in this round:
-- - permission.idx_platform: logically prefix-covered by uniq_platform_code(platform, code),
--   but the composite index is materially wider, so keep the narrow platform index until
--   larger-volume evidence shows no standalone platform-read benefit.

ALTER TABLE `ai_assistant_tools`
  DROP INDEX `idx_assistant_id`;

ALTER TABLE `ai_tools`
  DROP INDEX `idx_is_del`;

ALTER TABLE `upload_setting`
  DROP INDEX `idx_driver`;
