<?php

namespace app\listener\Ai;

use support\Log;

/**
 * AI Run 日志监听器
 */
class AiRunLogListener
{
    /**
     * 监听 Run 开始
     */
    public function onStarted(array $data): void
    {
        Log::info('AI Run started', $data);
    }

    /**
     * 监听 Run 完成
     */
    public function onCompleted(array $data): void
    {
        Log::info('AI Run completed', $data);
    }

    /**
     * 监听 Run 失败
     */
    public function onFailed(array $data): void
    {
        Log::warning('AI Run failed', $data);
    }
}
