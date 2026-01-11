<?php

namespace app\dep\System;

use app\dep\BaseDep;
use app\model\System\UploadSettingModel;
use app\enum\CommonEnum;
use support\Model;

class UploadSettingDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UploadSettingModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据 driver_id + rule_id 查询
     */
    public function findByDriverRule(int $driverId, int $ruleId)
    {
        return $this->model
            ->where('driver_id', $driverId)
            ->where('rule_id', $ruleId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 获取当前启用的配置（关联 driver 和 rule）
     */
    public function getActive()
    {
        return $this->model
            ->from('upload_setting as us')
            ->leftJoin('upload_driver as ud', 'us.driver_id', '=', 'ud.id')
            ->leftJoin('upload_rule as ur', 'us.rule_id', '=', 'ur.id')
            ->select(
                'us.*',
                'ud.driver', 'ud.secret_id', 'ud.secret_key', 'ud.bucket', 'ud.region', 'ud.appid', 'ud.role_arn', 'ud.endpoint', 'ud.bucket_domain',
                'ur.title as rule_title', 'ur.max_size_mb', 'ur.image_exts', 'ur.file_exts'
            )
            ->where('us.status', CommonEnum::YES)
            ->where('us.is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * 检查指定 ID 中是否包含启用的配置
     */
    public function hasEnabledIn(array $ids): bool
    {
        return $this->model
            ->whereIn('id', $ids)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->exists();
    }

    // ==================== 列表查询 ====================

    /**
     * 列表查询（分页 + 过滤，关联 driver 和 rule）
     */
    public function list(array $param)
    {
        return $this->model
            ->from('upload_setting as us')
            ->leftJoin('upload_driver as ud', 'us.driver_id', '=', 'ud.id')
            ->leftJoin('upload_rule as ur', 'us.rule_id', '=', 'ur.id')
            ->select('us.*', 'ud.driver', 'ud.bucket', 'ur.title as rule_title')
            ->when(!empty($param['remark']), fn($q) => $q->where('us.remark', 'like', '%' . $param['remark'] . '%'))
            ->when(!empty($param['status']), fn($q) => $q->where('us.status', $param['status']))
            ->when(!empty($param['driver_id']), fn($q) => $q->where('us.driver_id', $param['driver_id']))
            ->when(!empty($param['rule_id']), fn($q) => $q->where('us.rule_id', $param['rule_id']))
            ->where('us.is_del', CommonEnum::NO)
            ->orderBy('us.id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    // ==================== 写入方法 ====================

    /**
     * 清除所有启用状态
     */
    public function clearStatus(): int
    {
        return $this->model
            ->where('status', CommonEnum::YES)
            ->update(['status' => CommonEnum::NO]);
    }
}
