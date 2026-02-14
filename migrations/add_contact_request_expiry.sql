-- 添加好友请求过期功能
-- 执行时间：2025-02-14

-- 1. 给 cron_task 表插入新的定时任务配置
INSERT INTO `cron_task` (`name`, `title`, `description`, `cron`, `cron_readable`, `handler`, `status`, `is_del`)
VALUES (
    'clean_expired_contact_request',
    '清理过期好友请求',
    '将超过7天未处理的好友请求标记为过期',
    '0 0 2 * * *',
    '每天凌晨2点执行',
    'app\\process\\CleanExpiredContactRequestTask',
    1,
    2
);

-- 说明：
-- 1. 好友请求创建后，如果7天内未被确认或拒绝，将被自动清理（软删除）
-- 2. 定时任务每天凌晨2点执行一次
-- 3. 可以通过后台管理界面修改 cron 表达式来调整执行频率
-- 4. 可以通过修改 CleanExpiredContactRequestTask 类中的 $expireDays 属性来调整过期天数
