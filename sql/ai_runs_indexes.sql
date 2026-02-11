-- AI 运行监控表索引优化
-- 执行前建议先 EXPLAIN 验证当前查询计划
-- 2026-02-11

-- 1. 统计查询核心索引：覆盖 getStats / getStatsByDate / getStatsByAgent / getStatsByUser
--    WHERE is_del=2 AND created_at BETWEEN ... [AND agent_id=X] [AND user_id=X]
--    GROUP BY DATE(created_at) / agent_id / user_id
ALTER TABLE `ai_runs` ADD INDEX `idx_del_created` (`is_del`, `created_at`);
ALTER TABLE `ai_runs` ADD INDEX `idx_del_agent_created` (`is_del`, `agent_id`, `created_at`);

-- 2. 列表查询索引：WHERE is_del=2 AND run_status=X ORDER BY id DESC
--    替代原来的单列 idx_run_status（区分度低）
ALTER TABLE `ai_runs` ADD INDEX `idx_del_status_id` (`is_del`, `run_status`, `id`);

-- 3. 超时任务查询：WHERE run_status=1 AND is_del=2 AND created_at < threshold
--    用于 batchMarkFailed / getTimeoutRuns
ALTER TABLE `ai_runs` ADD INDEX `idx_status_del_created` (`run_status`, `is_del`, `created_at`);

-- 4. 可选：删除低效的单列索引（执行前确认无其他查询依赖）
-- ALTER TABLE `ai_runs` DROP INDEX `idx_is_del`;
-- ALTER TABLE `ai_runs` DROP INDEX `idx_run_status`;
