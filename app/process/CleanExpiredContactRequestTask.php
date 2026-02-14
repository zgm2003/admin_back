<?php

namespace app\process;

use support\Db;

/**
 * 清理过期的好友请求
 * - 将超过 7 天未处理的好友请求标记为过期（软删除）
 */
class CleanExpiredContactRequestTask extends BaseCronTask
{
    protected int $expireDays = 7; // 过期天数

    protected function getTaskName(): string
    {
        return 'clean_expired_contact_request';
    }

    protected function handle(): ?string
    {
        // 计算过期时间点
        $expiredAt = date('Y-m-d H:i:s', time() - $this->expireDays * 86400);
        
        // 软删除过期的待确认好友请求
        $count = Db::table('chat_contacts')
            ->where('status', 1) // 待确认状态
            ->where('is_del', 2) // 未删除
            ->where('created_at', '<', $expiredAt)
            ->update(['is_del' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        
        return $count > 0 ? "清理了 {$count} 条过期好友请求" : null;
    }
}
