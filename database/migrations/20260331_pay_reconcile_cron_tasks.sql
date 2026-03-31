UPDATE cron_task
SET
  title = '日对账汇总',
  description = 'T+1 凌晨汇总前一日交易',
  cron = '0 0 1 * * *',
  cron_readable = '每天凌晨1点',
  handler = 'app\\process\\Pay\\PayReconcileDailyTask',
  status = 1,
  is_del = 2
WHERE name = 'pay_reconcile_daily';

INSERT INTO cron_task (
  name,
  title,
  description,
  cron,
  cron_readable,
  handler,
  status,
  is_del,
  created_at,
  updated_at
)
SELECT
  'pay_reconcile_execute',
  '执行对账任务',
  '下载平台账单并生成本地对账结果',
  '0 */10 * * * *',
  '每10分钟',
  'app\\process\\Pay\\PayReconcileExecuteTask',
  1,
  2,
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM cron_task
  WHERE name = 'pay_reconcile_execute'
);

UPDATE cron_task
SET
  title = '执行对账任务',
  description = '下载平台账单并生成本地对账结果',
  cron = '0 */10 * * * *',
  cron_readable = '每10分钟',
  handler = 'app\\process\\Pay\\PayReconcileExecuteTask',
  status = 1,
  is_del = 2
WHERE name = 'pay_reconcile_execute';
