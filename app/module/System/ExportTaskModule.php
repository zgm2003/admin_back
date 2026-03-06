<?php

namespace app\module\System;

use app\dep\System\ExportTaskDep;
use app\enum\ExportTaskEnum;
use app\module\BaseModule;
use app\validate\System\ExportTaskValidate;

/**
 * 导出任务管理模块
 * 负责：异步导出任务的列表查询、状态统计、单条/批量删除
 * 任务按用户隔离，仅能操作自己的导出记录
 */
class ExportTaskModule extends BaseModule
{
    /**
     * 状态统计（按当前用户统计各状态的任务数量）
     */
    public function statusCount($request): array
    {
        $param = $request->all();
        $param['user_id'] = $request->userId;

        $counts = $this->dep(ExportTaskDep::class)->countByStatus($param);

        // 按枚举顺序组装，确保前端 tab 顺序一致
        $list = [];
        foreach (ExportTaskEnum::$statusArr as $val => $label) {
            $list[] = ['label' => $label, 'value' => $val, 'num' => $counts[$val] ?? 0];
        }

        return self::success($list);
    }

    /**
     * 导出任务列表（分页，按当前用户过滤，附带文件大小可读文本）
     */
    public function list($request): array
    {
        $param = $this->validate($request, ExportTaskValidate::list());
        $param['user_id'] = $request->userId;

        $res = $this->dep(ExportTaskDep::class)->list($param);

        $data['list'] = $res->map(fn($item) => [
            'id'             => $item->id,
            'title'          => $item->title,
            'file_name'      => $item->file_name,
            'file_url'       => $item->file_url,
            'file_size_text' => self::formatFileSize($item->file_size),
            'row_count'      => $item->row_count,
            'status'         => $item->status,
            'status_text'    => ExportTaskEnum::$statusArr[$item->status] ?? '未知',
            'error_msg'      => $item->error_msg,
            'expire_at'      => $item->expire_at,
            'created_at'     => $item->created_at,
        ]);

        $data['page'] = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }


    /**
     * 删除导出任务（仅允许删除自己的任务）
     */
    public function del($request): array
    {
        $param = $this->validate($request, ExportTaskValidate::del());
        $dep = $this->dep(ExportTaskDep::class);

        $task = $dep->get($param['id']);
        self::throwIf(!$task || $task->user_id !== $request->userId, '任务不存在');

        $dep->delete($param['id']);

        return self::success();
    }

    /**
     * 批量删除导出任务（仅删除属于当前用户的记录，返回实际删除数量）
     */
    public function batchDel($request): array
    {
        $param = $this->validate($request, ExportTaskValidate::batchDel());
        $count = $this->dep(ExportTaskDep::class)->batchDeleteByUser($param['ids'], $request->userId);

        return self::success(['deleted' => $count]);
    }

    // ==================== 私有方法 ====================

    /**
     * 文件大小格式化（字节 → B/KB/MB 可读文本）
     */
    private static function formatFileSize(?int $size): string
    {
        if (!$size) {
            return '-';
        }
        if ($size < 1024) {
            return "{$size} B";
        }
        if ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        }

        return round($size / 1048576, 2) . ' MB';
    }
}
