<?php

namespace app\module\DevTools;

use app\dep\DevTools\CronTaskDep;
use app\dep\DevTools\CronTaskLogDep;
use app\module\BaseModule;
use app\enum\CommonEnum;
use app\service\DictService;
use app\validate\DevTools\CronTaskValidate;

/**
 * 定时任务管理模块
 */
class CronTaskModule extends BaseModule
{
    protected CronTaskDep $cronTaskDep;
    protected CronTaskLogDep $cronTaskLogDep;

    public function __construct()
    {
        $this->cronTaskDep = new CronTaskDep();
        $this->cronTaskLogDep = new CronTaskLogDep();
    }

    /**
     * 初始化数据
     */
    public function init(): array
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setCronPresetArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 获取定时任务列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, CronTaskValidate::list());
        $res = $this->cronTaskDep->list($param);
        
        $list = $res->map(function ($task) {
            return [
                'id' => $task['id'],
                'name' => $task['name'],
                'title' => $task['title'],
                'description' => $task['description'],
                'cron' => $task['cron'],
                'cron_readable' => $task['cron_readable'],
                'handler' => $task['handler'],
                'status' => $task['status'],
                'status_name' => CommonEnum::$statusArr[$task['status']] ?? '',
                'next_run_time' => $this->getNextRunTime($task['cron']),
                'created_at' => $task['created_at'],
                'updated_at' => $task['updated_at'],
            ];
        });
        
        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        
        return self::paginate($list, $page);
    }

    /**
     * 新增定时任务
     */
    public function add($request): array
    {
        $param = $this->validate($request, CronTaskValidate::add());
        
        self::throwIf($this->cronTaskDep->nameExists($param['name']), '任务标识已存在');
        
        $data = [
            'name'          => $param['name'],
            'title'         => $param['title'],
            'description'   => $param['description'] ?? '',
            'cron'          => $param['cron'],
            'cron_readable' => $param['cron_readable'] ?? '',
            'handler'       => $param['handler'],
            'status'        => $param['status'],
        ];
        
        $this->cronTaskDep->add($data);
        return self::success();
    }

    /**
     * 编辑定时任务
     */
    public function edit($request): array
    {
        $param = $this->validate($request, CronTaskValidate::edit());
        
        $task = $this->cronTaskDep->getOrFail($param['id']);
        
        $data = [
            'title'         => $param['title'],
            'description'   => $param['description'] ?? '',
            'cron'          => $param['cron'],
            'cron_readable' => $param['cron_readable'] ?? '',
            'handler'       => $param['handler'],
            'status'        => $param['status'],
        ];
        
        $this->cronTaskDep->update($param['id'], $data);
        return self::success();
    }

    /**
     * 删除定时任务
     */
    public function del($request): array
    {
        $param = $this->validate($request, CronTaskValidate::del());
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];
        $this->cronTaskDep->delete($ids);
        return self::success();
    }

    /**
     * 切换任务状态
     */
    public function status($request): array
    {
        $param = $this->validate($request, CronTaskValidate::status());
        
        $task = $this->cronTaskDep->getOrFail($param['id']);
        
        $this->cronTaskDep->toggleStatus($param['id'], $param['status']);
        
        return self::success();
    }

    /**
     * 获取任务执行日志
     */
    public function logs($request): array
    {
        $param = $this->validate($request, CronTaskValidate::logs());
        
        $res = $this->cronTaskLogDep->list($param);
        
        $list = $res->map(function ($log) {
            return [
                'id' => $log['id'],
                'task_id' => $log['task_id'],
                'task_name' => $log['task_name'],
                'start_time' => $log['start_time'],
                'end_time' => $log['end_time'],
                'duration_ms' => $log['duration_ms'],
                'status' => $log['status'],
                'status_name' => $this->getLogStatusName($log['status']),
                'result' => $log['result'],
                'error_msg' => $log['error_msg'],
                'created_at' => $log['created_at'],
            ];
        });
        
        $page = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        
        return self::paginate($list, $page);
    }

    /**
     * 获取日志状态名称
     */
    protected function getLogStatusName(int $status): string
    {
        return match($status) {
            1 => '成功',
            2 => '失败',
            3 => '运行中',
            default => '未知'
        };
    }

    /**
     * 计算下次执行时间
     */
    protected function getNextRunTime(string $cron): string
    {
        try {
            $parts = preg_split('/\s+/', trim($cron));
            if (count($parts) !== 6) {
                return '-';
            }
            
            // 简单解析：秒 分 时 日 月 周
            [$second, $minute, $hour, $day, $month, $week] = $parts;
            
            $now = time();
            
            // 每 N 秒执行
            if (preg_match('/^\*\/([0-9]+)$/', $second, $m)) {
                $interval = (int)$m[1];
                $currentSecond = (int)date('s');
                $nextSecond = ceil($currentSecond / $interval) * $interval;
                if ($nextSecond >= 60) {
                    $next = strtotime(date('Y-m-d H:i:00', $now + 60));
                } else {
                    $next = strtotime(date("Y-m-d H:i:{$nextSecond}"));
                }
                return date('Y-m-d H:i:s', $next);
            }
            
            // 每分钟执行
            if ($second === '0' && $minute === '*') {
                $next = strtotime(date('Y-m-d H:i:00', $now + 60));
                return date('Y-m-d H:i:s', $next);
            }
            
            // 每天固定时间执行
            if ($day === '*' && $month === '*') {
                $h = (int)$hour;
                $m = $minute === '*' ? 0 : (int)$minute;
                $s = $second === '*' ? 0 : (int)$second;
                
                $todayRun = strtotime(date("Y-m-d {$h}:{$m}:{$s}"));
                if ($todayRun > $now) {
                    return date('Y-m-d H:i:s', $todayRun);
                }
                // 明天
                return date('Y-m-d H:i:s', $todayRun + 86400);
            }
            
            return '-';
        } catch (\Throwable $e) {
            return '-';
        }
    }
}
