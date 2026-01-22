<?php

namespace app\dep\DevTools;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\DevTools\OperationLogModel;
use Carbon\Carbon;
use support\Model;

/**
 * 操作日志 Dep
 */
class OperationLogDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new OperationLogModel();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['action']), fn($q) => $q->where('action', 'like', $param['action'] . '%'))
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', $param['user_id']))
            ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, function ($q) use ($param) {
                $start = Carbon::parse($param['date'][0])->startOfDay()->toDateTimeString();
                $end = Carbon::parse($param['date'][1])->endOfDay()->toDateTimeString();
                $q->whereBetween('created_at', [$start, $end]);
            })
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 游标分页查询（深分页优化）
     */
    public function listByCursor(array $param): array
    {
        $columns = ['id', 'user_id', 'action', 'request_data', 'response_data', 'is_success', 'created_at'];
        
        return $this->listCursor($param, function ($q) use ($param) {
            $q->when(!empty($param['action']), fn($q) => $q->where('action', 'like', $param['action'] . '%'))
              ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', $param['user_id']))
              ->when(!empty($param['date']) && is_array($param['date']) && count($param['date']) === 2, function ($q) use ($param) {
                  $start = Carbon::parse($param['date'][0])->startOfDay()->toDateTimeString();
                  $end = Carbon::parse($param['date'][1])->endOfDay()->toDateTimeString();
                  $q->whereBetween('created_at', [$start, $end]);
              });
        }, $columns);
    }
}
