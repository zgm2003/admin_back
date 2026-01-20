<?php

namespace app\module\DevTools;

use app\module\BaseModule;
use support\Redis;

/**
 * 队列监控模块
 */
class QueueMonitorModule extends BaseModule
{
    /**
     * 队列配置
     * group: fast/slow
     * name: 队列名称
     * label: 显示名称
     */
    private array $queues = [
        ['group' => 'fast', 'name' => 'operation_log', 'label' => '操作日志'],
        ['group' => 'fast', 'name' => 'user_login_log', 'label' => '登录日志'],
        ['group' => 'fast', 'name' => 'test_test', 'label' => '测试队列'],
        ['group' => 'slow', 'name' => 'email_send', 'label' => '邮件发送'],
        ['group' => 'slow', 'name' => 'export_task', 'label' => '导出任务'],
        ['group' => 'slow', 'name' => 'generate_conversation_title', 'label' => 'AI标题生成'],
    ];

    /**
     * 获取所有队列状态
     */
    public function list($request): array
    {
        $list = [];
        
        foreach ($this->queues as $queue) {
            $name = $queue['name'];
            
            $list[] = [
                'name' => $name,
                'label' => $queue['label'],
                'group' => $queue['group'],
                'waiting' => (int) Redis::lLen("{$name}_waiting"),
                'delayed' => (int) Redis::zCard("{$name}_delayed"),
                'failed' => (int) Redis::lLen("{$name}_failed"),
            ];
        }
        
        return self::success($list);
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
        $start = ($currentPage - 1) * $pageSize;
        $end = $start + $pageSize - 1;
        
        $failedKey = "{$queueName}_failed";
        $total = (int) Redis::lLen($failedKey);
        $items = Redis::lRange($failedKey, $start, $end);
        
        $list = [];
        foreach ($items as $index => $item) {
            $data = json_decode($item, true);
            $list[] = [
                'index' => $start + $index,
                'data' => $data['data'] ?? null,
                'max_attempts' => $data['max_attempts'] ?? null,
                'attempts' => $data['attempts'] ?? null,
                'error' => $data['error'] ?? null,
                'raw' => $item,
            ];
        }
        
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
        
        $failedKey = "{$queueName}_failed";
        $waitingKey = "{$queueName}_waiting";
        
        // 获取指定位置的任务
        $item = Redis::lIndex($failedKey, (int)$index);
        self::throwIf(!$item, '任务不存在');
        
        // 解析任务数据
        $data = json_decode($item, true);
        self::throwIf(!$data || !isset($data['data']), '任务数据无效');
        
        // 重置重试次数，重新入队
        $data['attempts'] = 0;
        unset($data['error']);
        
        // 使用事务确保原子性
        Redis::multi();
        Redis::lRem($failedKey, 1, $item);
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
        
        $waitingKey = "{$queueName}_waiting";
        $count = (int) Redis::lLen($waitingKey);
        Redis::del($waitingKey);
        
        return self::success(['cleared' => $count], "已清空 {$count} 条待处理任务");
    }

    /**
     * 清空失败队列
     */
    public function clearFailed($request): array
    {
        $param = $request->all();
        $queueName = $param['queue'] ?? '';
        
        self::throwIf(!$queueName, '请选择队列');
        self::throwIf(!$this->isValidQueue($queueName), '无效的队列名');
        
        $failedKey = "{$queueName}_failed";
        $count = (int) Redis::lLen($failedKey);
        Redis::del($failedKey);
        
        return self::success(['cleared' => $count], "已清空 {$count} 条失败任务");
    }

    /**
     * 验证队列名是否有效
     */
    private function isValidQueue(string $name): bool
    {
        foreach ($this->queues as $queue) {
            if ($queue['name'] === $name) {
                return true;
            }
        }
        return false;
    }
}
