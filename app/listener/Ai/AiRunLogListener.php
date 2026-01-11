<?php

namespace app\listener\Ai;

/**
 * AI Run 日志监听器
 * 事件数据已写入 ai_runs 表，这里仅做扩展预留
 */
class AiRunLogListener
{
    /**
     * 监听 Run 开始
     */
    public function onStarted(array $data): void
    {
        // 数据已写入 ai_runs 表，无需重复记录日志
    }

    /**
     * 监听 Run 完成
     */
    public function onCompleted(array $data): void
    {
        // 数据已写入 ai_runs 表，无需重复记录日志
    }

    /**
     * 监听 Run 失败
     */
    public function onFailed(array $data): void
    {
        // 数据已写入 ai_runs 表，无需重复记录日志
        // 如需告警可在此接入钉钉/企微通知
    }
}
