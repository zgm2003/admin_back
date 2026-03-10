<?php

namespace app\module\System;

use app\dep\System\UploadRuleDep;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\UploadRuleValidate;

/**
 * 上传规则模块
 * 负责：上传规则的 CRUD（限制文件大小、允许的图片/文件扩展名）
 */
class UploadRuleModule extends BaseModule
{
    /**
     * 初始化（返回可选的图片扩展名、文件扩展名字典）
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setUploadImageExtArr()
            ->setUploadFileExtArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 新增规则（标题不可重复）
     */
    public function add($request)
    {
        $param = $this->validate($request, UploadRuleValidate::add());
        self::throwIf($this->dep(UploadRuleDep::class)->existsByTitle($param['title']), '规则标题已存在');

        $this->dep(UploadRuleDep::class)->add([
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => $param['image_exts'],
            'file_exts'   => $param['file_exts'],
        ]);
        return self::success();
    }

    /**
     * 编辑规则（排除自身的标题唯一校验）
     */
    public function edit($request)
    {
        $param = $this->validate($request, UploadRuleValidate::edit());
        self::throwIf($this->dep(UploadRuleDep::class)->existsByTitle($param['title'], $param['id']), '规则标题已存在');

        $this->dep(UploadRuleDep::class)->update($param['id'], [
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => $param['image_exts'],
            'file_exts'   => $param['file_exts'],
        ]);
        return self::success();
    }

    /**
     * 删除规则（支持批量）
     */
    public function del($request)
    {
        $param = $this->validate($request, UploadRuleValidate::del());
        $this->dep(UploadRuleDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * 规则列表（分页，扩展名 JSON 解码后返回数组）
     */
    public function list($request)
    {
        $param = $this->validate($request, UploadRuleValidate::list());
        $res = $this->dep(UploadRuleDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'         => $item['id'],
            'title'      => $item['title'],
            'max_size_mb' => $item['max_size_mb'],
            'image_exts' => $item['image_exts'],
            'file_exts'  => $item['file_exts'],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
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
