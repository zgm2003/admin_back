<?php

namespace app\dep\System;

use app\model\System\UploadSettingModel;
use app\enum\CommonEnum;

class UploadSettingDep
{
    public $model;

    public function __construct()
    {
        $this->model = new UploadSettingModel();
    }

    public function first($id)
    {
        return $this->model->where('id', $id)->first();
    }
    
    public function firstByDriverRule($driverId, $ruleId)
    {
        return $this->model
            ->where('driver_id', $driverId)
            ->where('rule_id', $ruleId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    public function add($data)
    {
        return $this->model->insertGetId($data);
    }

    public function edit($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    public function del($id, $data)
    {
        if (!is_array($id)) $id = [$id];
        return $this->model->whereIn('id', $id)->update($data);
    }

    public function list($param)
    {
        $pageSize = $param['page_size'];
        $currentPage = $param['current_page'];
        return $this->model
            ->from('upload_setting as us')
            ->leftJoin('upload_driver as ud', 'us.driver_id', '=', 'ud.id')
            ->leftJoin('upload_rule as ur', 'us.rule_id', '=', 'ur.id')
            ->select('us.*', 'ud.driver', 'ud.bucket', 'ur.title as rule_title')
            ->when(!empty($param['remark']), function ($query) use ($param) {
                $query->where('us.remark', 'like', '%' . $param['remark'] . '%');
            })
            ->when(!empty($param['status']), function ($query) use ($param) {
                $query->where('us.status', $param['status']);
            })
            ->when(!empty($param['driver_id']), function ($query) use ($param) {
                $query->where('us.driver_id', $param['driver_id']);
            })
            ->when(!empty($param['rule_id']), function ($query) use ($param) {
                $query->where('us.rule_id', $param['rule_id']);
            })
            ->where('us.is_del', CommonEnum::NO)
            ->orderBy('us.id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $currentPage);
    }

    public function clearStatus()
    {
        return $this->model->where('status', 1)->update(['status' => 2]);
    }

    public function getActive()
    {
        return $this->model
            ->from('upload_setting as us')
            ->leftJoin('upload_driver as ud', 'us.driver_id', '=', 'ud.id')
            ->leftJoin('upload_rule as ur', 'us.rule_id', '=', 'ur.id')
            ->select('us.*', 
                'ud.driver', 'ud.secret_id', 'ud.secret_key', 'ud.bucket', 'ud.region', 'ud.appid', 'ud.role_arn', 'ud.endpoint', 'ud.bucket_domain',
                'ur.title as rule_title', 'ur.max_size_mb', 'ur.image_exts', 'ur.file_exts'
            )
            ->where('us.status', 1)
            ->where('us.is_del', CommonEnum::NO)
            ->first();
    }
}
