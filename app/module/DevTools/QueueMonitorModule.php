<?php

namespace app\module\DevTools;

use app\module\BaseModule;
use app\service\System\SettingService;
use app\validate\DevTools\QueueMonitorValidate;
use support\Redis;

/**
 * 队列监控模块
 * 负责：Redis 队列（webman/redis-queue）的状态监控、失败任务管理、重试/清空操作
 *
 * webman/redis-queue 的 key 格式：
 * - waiting: {redis-queue}-waiting + 队列名
 * - delayed: {redis-queue}-delayed（统一，所有队列共用）
 * - failed:  {redis-queue}-failed（统一，通过 queue 字段区分）
 */
class QueueMonitorModule extends BaseModule
{
    private const WAITING_PREFIX = '{redis-queue}-waiting';
    private const DELAYED_KEY    = '{redis-queue}-delayed';
    private const FAILED_KEY     = '{redis-queue}-failed';
    private const SETTING_KEY_QUEUES = 'devtools_queue_monitor_queues';

    /**
     * 队列配置默认值（仅作兜底，优先读系统设置 JSON：devtools_queue_monitor_queues）
     * 运行时先读系统设置（启用且合法的 JSON），失败/缺失时才回退到此默认列表
     * 运维可直接在线修改系统设置，无需发版
     */
    private const DEFAULT_QUEUES = [
        ['group' => 'fast', 'name' => 'operation_log',                  'label' => '操作日志'],
        ['group' => 'fast', 'name' => 'user_login_log',                 'label' => '登录日志'],
        ['group' => 'slow', 'name' => 'email_send',                     'label' => '邮件发送'],
        ['group' => 'slow', 'name' => 'export_task',                    'label' => '导出任务'],
        ['group' => 'slow', 'name' => 'generate_conversation_title',    'label' => 'AI标题生成'],
        ['group' => 'slow', 'name' => 'generate_conversation_content',  'label' => 'AI内容生成'],
    ];

    /** 运行时缓存：队列名列表（用于 isValidQueue 快速校验） */
    private ?array $queueNames = null;
    /** 运行时缓存：完整队列配置 */
    private ?array $queues = null;


    /**
     * 获取所有队列状态（waiting/delayed/failed 数量）
     * 使用 pipeline 批量获取 waiting 长度，减少 Redis 往返
     */
    public function list($request): array
    {
        $this->validate($request, QueueMonitorValidate::list());
        $queues = $this->getQueues();

        // pipeline 批量获取 waiting 队列长度
        $pipe = Redis::pipeline();
        foreach ($queues as $queue) {
            $pipe->lLen(self::WAITING_PREFIX . $queue['name']);
        }
        $waitingResults = $pipe->exec();

        $failedCounts  = $this->countFailedByQueue();
        $delayedCounts = $this->countDelayedByQueue();

        $list = [];
        foreach ($queues as $i => $queue) {
            $list[] = [
                'name'    => $queue['name'],
                'label'   => $queue['label'],
                'group'   => $queue['group'],
                'waiting' => (int)($waitingResults[$i] ?? 0),
                'delayed' => $delayedCounts[$queue['name']] ?? 0,
                'failed'  => $failedCounts[$queue['name']] ?? 0,
            ];
        }

        return self::success($list);
    }

    /**
     * 失败任务列表（从统一 failed 队列中按队列名过滤，内存分页）
     */
    public function failedList($request): array
    {
        $param = $this->validate($request, QueueMonitorValidate::failedList());
        $this->getQueues();

        $queueName   = $param['queue'];
        $pageSize    = $param['page_size'];
        $currentPage = $param['current_page'];

        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');

        // 从统一 failed 队列过滤指定队列的任务
        $allItems = Redis::lRange(self::FAILED_KEY, 0, -1);
        $filtered = [];
        foreach ($allItems as $index => $item) {
            $data = json_decode($item, true);
            if (($data['queue'] ?? '') === $queueName) {
                $filtered[] = [
                    'index'        => $index,
                    'data'         => $data['data'] ?? null,
                    'max_attempts' => $data['max_attempts'] ?? null,
                    'attempts'     => $data['attempts'] ?? null,
                    'error'        => $data['error'] ?? null,
                    'raw'          => $item,
                ];
            }
        }

        $total = \count($filtered);
        $list  = \array_slice($filtered, ($currentPage - 1) * $pageSize, $pageSize);

        return self::paginate($list, [
            'page_size'    => $pageSize,
            'current_page' => $currentPage,
            'total_page'   => (int)ceil($total / $pageSize),
            'total'        => $total,
        ]);
    }

    /**
     * 重试失败任务（从 failed 移除，重置重试次数后重新入 waiting 队列）
     * 使用 Redis MULTI 保证原子性
     */
    public function retry($request): array
    {
        $param = $this->validate($request, QueueMonitorValidate::retry());
        $this->getQueues();

        $queueName = $param['queue'];
        $index     = $param['index'];

        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');

        // 获取指定位置的任务
        $item = Redis::lIndex(self::FAILED_KEY, (int)$index);
        self::throwIf(!$item, '任务不存在');

        // 解析并验证队列归属
        $data = json_decode($item, true);
        self::throwIf(!$data || ($data['queue'] ?? '') !== $queueName, '任务数据无效');

        // 重置重试次数，清除错误信息，重新入队
        $data['attempts'] = 0;
        unset($data['error']);

        Redis::multi();
        Redis::lRem(self::FAILED_KEY, 1, $item);
        Redis::rPush(self::WAITING_PREFIX . $queueName, json_encode($data, JSON_UNESCAPED_UNICODE));
        Redis::exec();

        return self::success([], '已重新入队');
    }

    /**
     * 清空指定队列的 waiting 任务
     */
    public function clear($request): array
    {
        $param = $this->validate($request, QueueMonitorValidate::clear());
        $this->getQueues();

        $queueName  = $param['queue'];
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');

        $waitingKey = self::WAITING_PREFIX . $queueName;
        $count      = (int)Redis::lLen($waitingKey);
        Redis::del($waitingKey);

        return self::success(['cleared' => $count], "已清空 {$count} 条待处理任务");
    }

    /**
     * 清空指定队列的 failed 任务（遍历统一 failed 队列逐条匹配删除）
     */
    public function clearFailed($request): array
    {
        $param = $this->validate($request, QueueMonitorValidate::clearFailed());
        $this->getQueues();

        $queueName = $param['queue'];
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');

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


    // ==================== 私有方法 ====================

    /**
     * 获取队列配置（优先系统设置 JSON，失败则 fallback 默认值）
     * 系统设置 key: devtools_queue_monitor_queues，类型 JSON (value_type=4)
     * 结构: [ { "group": "fast|slow", "name": "queue_name", "label": "展示名" }, ... ]
     */
    private function getQueues(): array
    {
        if ($this->queues !== null) {
            return $this->queues;
        }

        $cfg = SettingService::get(self::SETTING_KEY_QUEUES, null);
        $queues = \is_array($cfg) ? $this->normalizeQueues($cfg) : [];

        $this->queues = $queues ?: self::DEFAULT_QUEUES;

        return $this->queues;
    }

    /**
     * 规范化并校验队列配置
     * - name 必填且去重
     * - group 仅允许 fast/slow，非法值降级为 slow
     * - label 为空则使用 name
     */
    private function normalizeQueues(array $raw): array
    {
        $out  = [];
        $seen = [];

        foreach ($raw as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $name = isset($item['name']) ? trim((string)$item['name']) : '';
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $group = isset($item['group']) ? trim((string)$item['group']) : 'slow';
            $label = isset($item['label']) ? trim((string)$item['label']) : $name;

            // group 只允许 fast/slow，其它值降级到 slow
            if (!\in_array($group, ['fast', 'slow'], true)) {
                $group = 'slow';
            }

            $seen[$name] = true;
            $out[] = ['group' => $group, 'name' => $name, 'label' => $label];
        }

        return $out;
    }

    /**
     * 统计每个队列的失败任务数（遍历统一 failed 队列按 queue 字段分组计数）
     */
    private function countFailedByQueue(): array
    {
        $counts = [];
        foreach (Redis::lRange(self::FAILED_KEY, 0, -1) as $item) {
            $data  = json_decode($item, true);
            $queue = $data['queue'] ?? 'unknown';
            $counts[$queue] = ($counts[$queue] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * 统计每个队列的延迟任务数（遍历统一 delayed 有序集合按 queue 字段分组计数）
     */
    private function countDelayedByQueue(): array
    {
        $counts = [];
        foreach (Redis::zRange(self::DELAYED_KEY, 0, -1) as $item) {
            $data  = json_decode($item, true);
            $queue = $data['queue'] ?? 'unknown';
            $counts[$queue] = ($counts[$queue] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * 校验队列名是否在配置列表中（运行时缓存队列名数组）
     */
    private function isValidQueue(string $name): bool
    {
        if ($this->queueNames === null) {
            $this->queueNames = array_column($this->getQueues(), 'name');
        }

        return \in_array($name, $this->queueNames, true);
    }
}