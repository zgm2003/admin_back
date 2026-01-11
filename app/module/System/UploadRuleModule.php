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

    public function __construct()
    {
        $this->uploadRuleDep = new UploadRuleDep();
    }

    public function init($request){
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setUploadImageExtArr()
            ->setUploadFileExtArr()
            ->getDict();
        return self::success($data);
    }

    public function add($request)
    {
        try { $param = $this->validate($request, UploadRuleValidate::add()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $resDep = $this->uploadRuleDep->findByTitle($param['title']);
        if ($resDep){
            return self::error('规则标题已存在');
        }
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
        try { $param = $this->validate($request, UploadRuleValidate::edit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $resDep = $this->uploadRuleDep->findByTitle($param['title']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::error('规则标题已存在');
        }
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
        try { $param = $this->validate($request, UploadRuleValidate::del()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $this->uploadRuleDep->delete($param['id']);
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->uploadRuleDep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'max_size_mb' => $item['max_size_mb'],
                'image_exts' => json_decode($item['image_exts']),
                'file_exts' => json_decode($item['file_exts']),
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
