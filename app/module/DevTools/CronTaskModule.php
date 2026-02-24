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
 * 负责：定时任务的 CRUD、状态切换、执行日志查询
 * 支持 cron 表达式（秒 分 时 日 月 周 六段式）解析下次执行时间
 */
class CronTaskModule extends BaseModule
{
    /**
     * 初始化（返回 cron 预设字典，供前端选择常用表达式）
     */
    public function init(): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setCronPresetArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 定时任务列表（分页，附带状态名称和下次执行时间）
     */
    public function list($request): array
    {
        $param = $this->validate($request, CronTaskValidate::list());
        $res = $this->dep(CronTaskDep::class)->list($param);

        $list = $res->map(fn($task) => [
            'id'            => $task['id'],
            'name'          => $task['name'],
            'title'         => $task['title'],
            'description'   => $task['description'],
            'cron'          => $task['cron'],
            'cron_readable' => $task['cron_readable'],
            'handler'       => $task['handler'],
            'status'        => $task['status'],
            'status_name'   => CommonEnum::$statusArr[$task['status']] ?? '',
            'next_run_time' => $this->getNextRunTime($task['cron']),
            'created_at'    => $task['created_at'],
            'updated_at'    => $task['updated_at'],
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }


    /**
     * 新增定时任务（任务标识 name 不可重复）
     */
    public function add($request): array
    {
        $param = $this->validate($request, CronTaskValidate::add());
        $dep = $this->dep(CronTaskDep::class);

        self::throwIf($dep->nameExists($param['name']), '任务标识已存在');

        $dep->add([
            'name'          => $param['name'],
            'title'         => $param['title'],
            'description'   => $param['description'] ?? '',
            'cron'          => $param['cron'],
            'cron_readable' => $param['cron_readable'] ?? '',
            'handler'       => $param['handler'],
            'status'        => $param['status'],
        ]);

        return self::success();
    }

    /**
     * 编辑定时任务（按 ID 更新，name 不可修改）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, CronTaskValidate::edit());
        $dep = $this->dep(CronTaskDep::class);

        // 确认任务存在
        $dep->getOrFail($param['id']);

        $dep->update($param['id'], [
            'title'         => $param['title'],
            'description'   => $param['description'] ?? '',
            'cron'          => $param['cron'],
            'cron_readable' => $param['cron_readable'] ?? '',
            'handler'       => $param['handler'],
            'status'        => $param['status'],
        ]);

        return self::success();
    }

    /**
     * 删除定时任务（支持批量删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, CronTaskValidate::del());
        $ids = \is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];
        $this->dep(CronTaskDep::class)->delete($ids);

        return self::success();
    }

    /**
     * 切换任务状态（启用/禁用）
     */
    public function status($request): array
    {
        $param = $this->validate($request, CronTaskValidate::status());
        $dep = $this->dep(CronTaskDep::class);

        // 确认任务存在
        $dep->getOrFail($param['id']);

        $dep->toggleStatus($param['id'], $param['status']);

        return self::success();
    }

    /**
     * 任务执行日志列表（分页，按 task_id 过滤）
     */
    public function logs($request): array
    {
        $param = $this->validate($request, CronTaskValidate::logs());
        $res = $this->dep(CronTaskLogDep::class)->list($param);

        $list = $res->map(fn($log) => [
            'id'          => $log['id'],
            'task_id'     => $log['task_id'],
            'task_name'   => $log['task_name'],
            'start_time'  => $log['start_time'],
            'end_time'    => $log['end_time'],
            'duration_ms' => $log['duration_ms'],
            'status'      => $log['status'],
            'status_name' => self::getLogStatusName($log['status']),
            'result'      => $log['result'],
            'error_msg'   => $log['error_msg'],
            'created_at'  => $log['created_at'],
        ]);

        $page = [
            'page_size'    => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    // ==================== 私有方法 ====================

    /**
     * 日志状态码 → 中文名称映射
     */
    protected static function getLogStatusName(int $status): string
    {
        return match ($status) {
            1       => '成功',
            2       => '失败',
            3       => '运行中',
            default => '未知',
        };
    }

    /**
     * 根据六段式 cron 表达式计算下次执行时间
     * 格式：秒 分 时 日 月 周
     * 仅做简单解析，复杂表达式返回 '-'
     */
    protected function getNextRunTime(string $cron): string
    {
        try {
            $parts = preg_split('/\s+/', trim($cron));
            if (\count($parts) !== 6) {
                return '-';
            }

            [$second, $minute, $hour, $day, $month, $week] = $parts;
            $now = time();

            // 每 N 秒执行（如 */5）
            if (preg_match('/^\*\/([0-9]+)$/', $second, $m)) {
                $interval = (int)$m[1];
                $currentSecond = (int)date('s');
                $nextSecond = ceil($currentSecond / $interval) * $interval;
                $next = $nextSecond >= 60
                    ? strtotime(date('Y-m-d H:i:00', $now + 60))
                    : strtotime(date("Y-m-d H:i:{$nextSecond}"));

                return date('Y-m-d H:i:s', $next);
            }

            // 每分钟执行（秒=0，分=*）
            if ($second === '0' && $minute === '*') {
                return date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:00', $now + 60)));
            }

            // 每天固定时间执行
            if ($day === '*' && $month === '*') {
                $h = (int)$hour;
                $m = $minute === '*' ? 0 : (int)$minute;
                $s = $second === '*' ? 0 : (int)$second;

                $todayRun = strtotime(date("Y-m-d {$h}:{$m}:{$s}"));

                return date('Y-m-d H:i:s', $todayRun > $now ? $todayRun : $todayRun + 86400);
            }

            return '-';
        } catch (\Throwable) {
            return '-';
        }
    }
}