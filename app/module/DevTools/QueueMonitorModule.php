<?php

namespace app\module\DevTools;

use app\module\BaseModule;
use support\Redis;

/**
 * 队列监控模块
 * 
 * webman/redis-queue 的 key 格式：
 * - waiting: {redis-queue}-waiting + 队列名
 * - delayed: {redis-queue}-delayed（统一，所有队列共用）
 * - failed: {redis-queue}-failed（统一，通过 queue 字段区分）
 */
class QueueMonitorModule extends BaseModule
{
    const WAITING_PREFIX = '{redis-queue}-waiting';
    const DELAYED_KEY = '{redis-queue}-delayed';
    const FAILED_KEY = '{redis-queue}-failed';

    /**
     * 队列配置
     */
    private array $queues = [
        ['group' => 'fast', 'name' => 'operation_log', 'label' => '操作日志'],
        ['group' => 'fast', 'name' => 'user_login_log', 'label' => '登录日志'],
        ['group' => 'slow', 'name' => 'email_send', 'label' => '邮件发送'],
        ['group' => 'slow', 'name' => 'export_task', 'label' => '导出任务'],
        ['group' => 'slow', 'name' => 'generate_conversation_title', 'label' => 'AI标题生成'],
    ];

    private ?array $queueNames = null;

    /**
     * 获取所有队列状态
     */
    public function list($request): array
    {
        // 批量获取 waiting 队列长度
        $pipe = Redis::pipeline();
        foreach ($this->queues as $queue) {
            $pipe->lLen(self::WAITING_PREFIX . $queue['name']);
        }
        $waitingResults = $pipe->exec();

        // 统计每个队列的失败数（failed 是统一的，需要遍历统计）
        $failedCounts = $this->countFailedByQueue();

        // 按 queue 字段统计延迟任务
        $delayedCounts = $this->countDelayedByQueue();

        $list = [];
        foreach ($this->queues as $i => $queue) {
            $list[] = [
                'name' => $queue['name'],
                'label' => $queue['label'],
                'group' => $queue['group'],
                'waiting' => (int)($waitingResults[$i] ?? 0),
                'delayed' => $delayedCounts[$queue['name']] ?? 0,
                'failed' => $failedCounts[$queue['name']] ?? 0,
            ];
        }

        return self::success($list);
    }

    /**
     * 统计每个队列的失败任务数
     */
    private function countFailedByQueue(): array
    {
        $counts = [];
        $items = Redis::lRange(self::FAILED_KEY, 0, -1);
        foreach ($items as $item) {
            $data = json_decode($item, true);
            $queue = $data['queue'] ?? 'unknown';
            $counts[$queue] = ($counts[$queue] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * 统计每个队列的延迟任务数
     */
    private function countDelayedByQueue(): array
    {
        $counts = [];
        $items = Redis::zRange(self::DELAYED_KEY, 0, -1);
        foreach ($items as $item) {
            $data = json_decode($item, true);
            $queue = $data['queue'] ?? 'unknown';
            $counts[$queue] = ($counts[$queue] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * 获取失败任务列表
     */
    public function failedList($request): array
    {
        $param = $request->all();
        $queueName = $param['queue'] ?? '';
        
        self::throwIf(!$queueName, '请选择队列');
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');
        
        $pageSize = (int)($param['page_size'] ?? 20);
        $currentPage = (int)($param['current_page'] ?? 1);
        
        // 从统一的 failed 队列过滤指定队列的任务
        $allItems = Redis::lRange(self::FAILED_KEY, 0, -1);
        $filtered = [];
        foreach ($allItems as $index => $item) {
            $data = json_decode($item, true);
            if (($data['queue'] ?? '') === $queueName) {
                $filtered[] = [
                    'index' => $index,  // 实际在 Redis list 中的位置
                    'data' => $data['data'] ?? null,
                    'max_attempts' => $data['max_attempts'] ?? null,
                    'attempts' => $data['attempts'] ?? null,
                    'error' => $data['error'] ?? null,
                    'raw' => $item,
                ];
            }
        }
        
        $total = count($filtered);
        $start = ($currentPage - 1) * $pageSize;
        $list = array_slice($filtered, $start, $pageSize);
        
        return self::paginate($list, [
            'page_size' => $pageSize,
            'current_page' => $currentPage,
            'total_page' => ceil($total / $pageSize),
            'total' => $total,
        ]);
    }

    /**
     * 重试失败任务
     */
    public function retry($request): array
    {
        $param = $request->all();
        $queueName = $param['queue'] ?? '';
        $index = $param['index'] ?? null;
        
        self::throwIf(!$queueName, '请选择队列');
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');
        self::throwIf($index === null, '请指定任务索引');
        
        // 获取指定位置的任务
        $item = Redis::lIndex(self::FAILED_KEY, (int)$index);
        self::throwIf(!$item, '任务不存在');
        
        // 解析并验证队列
        $data = json_decode($item, true);
        self::throwIf(!$data || ($data['queue'] ?? '') !== $queueName, '任务数据无效');
        
        // 重置重试次数，重新入队
        $data['attempts'] = 0;
        unset($data['error']);
        
        $waitingKey = self::WAITING_PREFIX . $queueName;
        
        Redis::multi();
        Redis::lRem(self::FAILED_KEY, 1, $item);
        Redis::rPush($waitingKey, json_encode($data, JSON_UNESCAPED_UNICODE));
        Redis::exec();
        
        return self::success([], '已重新入队');
    }

    /**
     * 清空等待队列
     */
    public function clear($request): array
    {
        $param = $request->all();
        $queueName = $param['queue'] ?? '';
        
        self::throwIf(!$queueName, '请选择队列');
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');
        
        $waitingKey = self::WAITING_PREFIX . $queueName;
        $count = (int) Redis::lLen($waitingKey);
        Redis::del($waitingKey);
        
        return self::success(['cleared' => $count], "已清空 {$count} 条待处理任务");
    }

    /**
     * 清空指定队列的失败任务
     */
    public function clearFailed($request): array
    {
        $param = $request->all();
        $queueName = $param['queue'] ?? '';
        
        self::throwIf(!$queueName, '请选择队列');
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');
        
        // 遍历删除指定队列的失败任务
        $allItems = Redis::lRange(self::FAILED_KEY, 0, -1);
        $count = 0;
        foreach ($allItems as $item) {
            $data = json_decode($item, true);
            if (($data['queue'] ?? '') === $queueName) {
                Redis::lRem(self::FAILED_KEY, 1, $item);
                $count++;
            }
        }
        
        return self::success(['cleared' => $count], "已清空 {$count} 条失败任务");
    }

    /**
     * 验证队列名是否有效
     */
    private function isValidQueue(string $name): bool
    {
        if ($this->queueNames === null) {
            $this->queueNames = array_column($this->queues, 'name');
        }
        return in_array($name, $this->queueNames, true);
    }
}
