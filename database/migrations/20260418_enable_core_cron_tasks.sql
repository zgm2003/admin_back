UPDATE cron_task
SET
  status = 1,
  updated_at = NOW()
WHERE is_del = 2
  AND name IN ('ai_run_timeout', 'notification_task_scheduler')
  AND status <> 1;
