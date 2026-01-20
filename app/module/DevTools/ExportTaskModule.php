<?php

namespace app\module\DevTools;

use app\dep\DevTools\ExportTaskDep;
use app\enum\ExportTaskEnum;
use app\module\BaseModule;
use app\validate\DevTools\ExportTaskValidate;

/**
 * 导出任务管理模块
 */
class ExportTaskModule extends BaseModule
{
    private ExportTaskDep $exportTaskDep;

    public function __construct()
    {
        $this->exportTaskDep = new ExportTaskDep();
    }

    /**
     * 状态统计
     */
    public function statusCount($request): array
    {
        $param = $request->all();
        $param['user_id'] = $request->userId;
        $counts = $this->exportTaskDep->countByStatus($param);
        $list = [];
        foreach (ExportTaskEnum::$statusArr as $val => $label) {
            $list[] = ['label' => $label, 'value' => $val, 'num' => $counts[$val] ?? 0];
        }
        return self::success($list);
    }

    /**
     * 获取导出任务列表
     */
    public function list($request): array
    {
        $param = $request->all();
        $param['user_id'] = $request->userId;
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $res = $this->exportTaskDep->list($param);

        $data['list'] = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'file_name' => $item->file_name,
                'file_url' => $item->file_url,
                'file_size_text' => $this->formatFileSize($item->file_size),
                'row_count' => $item->row_count,
                'status' => $item->status,
                'status_text' => ExportTaskEnum::$statusArr[$item->status] ?? '未知',
                'error_msg' => $item->error_msg,
                'expire_at' => $item->expire_at,
                'created_at' => $item->created_at,
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 删除导出任务
     */
    public function del($request): array
    {
        $param = $this->validate($request, ExportTaskValidate::del());
        $task = $this->exportTaskDep->get($param['id']);
        self::throwIf(!$task || $task->user_id !== $request->userId, '任务不存在');
        $this->exportTaskDep->delete($param['id']);
        return self::success();
    }

    /**
     * 批量删除
     */
    public function batchDel($request): array
    {
        $param = $this->validate($request, ExportTaskValidate::batchDel());
        $count = $this->exportTaskDep->batchDeleteByUser($param['ids'], $request->userId);
        return self::success(['deleted' => $count]);
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(?int $size): string
    {
        if (!$size) return '-';
        if ($size < 1024) return $size . ' B';
        if ($size < 1024 * 1024) return round($size / 1024, 2) . ' KB';
        return round($size / (1024 * 1024), 2) . ' MB';
    }
}
