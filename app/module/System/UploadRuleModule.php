<?php

namespace app\module\System;

use app\dep\System\UploadRuleDep;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use app\validate\System\UploadRuleValidate;

class UploadRuleModule extends BaseModule
{
    protected UploadRuleDep $uploadRuleDep;
    protected DictService $dictService;

    public function __construct()
    {
        $this->uploadRuleDep = $this->dep(UploadRuleDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request)
    {
        $data['dict'] = $this->dictService
            ->setUploadImageExtArr()
            ->setUploadFileExtArr()
            ->getDict();
        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, UploadRuleValidate::add());
        self::throwIf($this->uploadRuleDep->existsByTitle($param['title']), '规则标题已存在');
        $data = [
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => json_encode($param['image_exts']),
            'file_exts'   => json_encode($param['file_exts']),
        ];
        $this->uploadRuleDep->add($data);
        return self::success();
    }

    public function edit($request)
    {
        $param = $this->validate($request, UploadRuleValidate::edit());
        self::throwIf($this->uploadRuleDep->existsByTitle($param['title'], $param['id']), '规则标题已存在');
        $data = [
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => json_encode($param['image_exts']),
            'file_exts'   => json_encode($param['file_exts']),
        ];
        $this->uploadRuleDep->update($param['id'], $data);
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, UploadRuleValidate::del());
        $this->uploadRuleDep->delete($param['id']);
        return self::success();
    }

    public function list($request)
    {
        $param = $this->validate($request, UploadRuleValidate::list());
        $res = $this->uploadRuleDep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'max_size_mb' => $item['max_size_mb'],
                'image_exts' => json_decode($item['image_exts']),
                'file_exts' => json_decode($item['file_exts']),
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
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
}
