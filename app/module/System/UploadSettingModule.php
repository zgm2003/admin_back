<?php

namespace app\module\System;

use app\dep\System\UploadSettingDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\System\UploadSettingValidate;

/**
 * 上传配置模块
 * 负责：驱动 + 规则的组合配置，同一时间只能有一个启用的配置（互斥启用）
 */
class UploadSettingModule extends BaseModule
{
    /**
     * 初始化（返回状态、驱动列表、规则列表字典）
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setCommonStatusArr()
            ->setUploadDriverList()
            ->setUploadRuleList()
            ->getDict();
        return self::success($data);
    }

    /**
     * 新增配置（驱动+规则组合不可重复；启用时事务内先清除其他启用项）
     */
    public function add($request)
    {
        $param = $this->validate($request, UploadSettingValidate::add());

        $dep = $this->dep(UploadSettingDep::class);
        self::throwIf($dep->existsByDriverRule($param['driver_id'], $param['rule_id']), '该驱动与规则组合已存在');

        $data = [
            'driver_id' => $param['driver_id'],
            'rule_id'   => $param['rule_id'],
            'status'    => $param['status'],
            'remark'    => $param['remark'] ?? '',
        ];

        // 启用状态：事务内先清除所有已启用项，保证互斥
        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($dep, $data) {
                $dep->clearStatus();
                $dep->add($data);
            });
        } else {
            $dep->add($data);
        }
        return self::success();
    }

    /**
     * 编辑配置（排除自身的组合唯一校验；启用时互斥处理）
     */
    public function edit($request)
    {
        $param = $this->validate($request, UploadSettingValidate::edit());

        $dep = $this->dep(UploadSettingDep::class);
        self::throwIf($dep->existsByDriverRule($param['driver_id'], $param['rule_id'], $param['id']), '该驱动与规则组合已存在');

        $data = [
            'driver_id' => $param['driver_id'],
            'rule_id'   => $param['rule_id'],
            'status'    => $param['status'],
            'remark'    => $param['remark'] ?? '',
        ];

        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($dep, $param, $data) {
                $dep->clearStatus();
                $dep->update($param['id'], $data);
            });
        } else {
            $dep->update($param['id'], $data);
        }
        return self::success();
    }

    /**
     * 删除配置（启用中的不允许删除，支持批量）
     */
    public function del($request)
    {
        $param = $this->validate($request, UploadSettingValidate::del());
        $ids = is_array($param['id']) ? array_map('intval', $param['id']) : [(int)$param['id']];
        self::throwIf($this->dep(UploadSettingDep::class)->hasEnabledIn($ids), '包含启用的上传设置，无法删除');
        $this->dep(UploadSettingDep::class)->delete($ids);
        return self::success();
    }

    /**
     * 切换状态（启用时互斥：事务内先清除再设置）
     */
    public function status($request)
    {
        $param = $this->validate($request, UploadSettingValidate::status());

        $dep = $this->dep(UploadSettingDep::class);
        if ((int)$param['status'] === CommonEnum::YES) {
            $this->withTransaction(function () use ($dep, $param) {
                $dep->clearStatus();
                $dep->update($param['id'], ['status' => $param['status']]);
            });
        } else {
            $dep->update($param['id'], ['status' => $param['status']]);
        }
        return self::success();
    }

    /**
     * 配置列表（分页，关联驱动名+规则名展示）
     */
    public function list($request)
    {
        $param = $this->validate($request, UploadSettingValidate::list());
        $res = $this->dep(UploadSettingDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'          => $item['id'],
            'driver_id'   => $item['driver_id'],
            'rule_id'     => $item['rule_id'],
            'driver_name' => $item['driver'] . ' - ' . $item['bucket'],
            'rule_name'   => $item['rule_title'],
            'status'      => $item['status'],
            'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
            'remark'      => $item['remark'],
            'created_at'  => $item['created_at'],
            'updated_at'  => $item['updated_at'],
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }
}
