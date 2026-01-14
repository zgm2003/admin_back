<?php

namespace app\module\System;

use app\dep\System\UploadDriverDep;
use app\dep\System\UploadRuleDep;
use app\dep\System\UploadSettingDep;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use app\validate\System\UploadSettingValidate;

class UploadSettingModule extends BaseModule
{
    protected UploadSettingDep $uploadSettingDep;
    protected UploadDriverDep $uploadDriverDep;
    protected UploadRuleDep $uploadRuleDep;

    public function __construct()
    {
        $this->uploadSettingDep = new UploadSettingDep();
        $this->uploadDriverDep = new UploadDriverDep();
        $this->uploadRuleDep = new UploadRuleDep();
    }

    public function init($request){
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setCommonStatusArr()
            ->setUploadDriverList()
            ->setUploadRuleList()
            ->getDict();
        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, UploadSettingValidate::add());
        
        $exists = $this->uploadSettingDep->findByDriverRule($param['driver_id'], $param['rule_id']);
        self::throwIf($exists, '该驱动与规则组合已存在');

        $data = [
            'driver_id' => $param['driver_id'],
            'rule_id'   => $param['rule_id'],
            'status'    => $param['status'],
            'remark'    => $param['remark'] ?? '',
        ];

        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($data) {
                $this->uploadSettingDep->clearStatus();
                $this->uploadSettingDep->add($data);
            });
        } else {
            $this->uploadSettingDep->add($data);
        }
        return self::success();
    }

    public function edit($request)
    {
        $param = $this->validate($request, UploadSettingValidate::edit());
        
        $exists = $this->uploadSettingDep->findByDriverRule($param['driver_id'], $param['rule_id']);
        self::throwIf($exists && $exists['id'] != $param['id'], '该驱动与规则组合已存在');

        $data = [
            'driver_id' => $param['driver_id'],
            'rule_id'   => $param['rule_id'],
            'status'    => $param['status'],
            'remark'    => $param['remark'] ?? '',
        ];

        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($param, $data) {
                $this->uploadSettingDep->clearStatus();
                $this->uploadSettingDep->update($param['id'], $data);
            });
        } else {
            $this->uploadSettingDep->update($param['id'], $data);
        }
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, UploadSettingValidate::del());
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [ (int)$param['id'] ];
        self::throwIf($this->uploadSettingDep->hasEnabledIn($ids), '包含启用的上传设置，无法删除');
        $this->uploadSettingDep->delete($ids);
        return self::success();
    }
    
    public function status($request)
    {
        $param = $this->validate($request, UploadSettingValidate::status());
        
        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($param) {
                $this->uploadSettingDep->clearStatus();
                $this->uploadSettingDep->update($param['id'], ['status' => $param['status']]);
            });
        } else {
            $this->uploadSettingDep->update($param['id'], ['status' => $param['status']]);
        }
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->uploadSettingDep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'driver_id' => $item['driver_id'],
                'rule_id' => $item['rule_id'],
                'driver_name' => $item['driver'] . ' - ' . $item['bucket'],
                'rule_name' => $item['rule_title'],
                'status' => $item['status'],
                'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
                'remark' => $item['remark'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString(),
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
}
