<?php

namespace app\module\System;

use app\dep\System\UploadRuleDep;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class UploadRuleModule extends BaseModule
{
    public $dep;

    public function __construct()
    {
        $this->dep = new UploadRuleDep();
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
        try {
            $param = v::input($request->all(), [
                'title'       => v::stringType()->length(1, 100)->setName('title'),
                'max_size_mb' => v::intVal()->min(1)->setName('max_size_mb'),
                'image_exts'  => v::arrayType()->setName('image_exts'),
                'file_exts'   => v::arrayType()->setName('file_exts'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $dep = $this->dep;
        $resDep = $dep->firstByTitle($param['title']);
        if ($resDep){
            return self::error('规则标题已存在');
        }
        $data = [
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => json_encode($param['image_exts']),
            'file_exts'   => json_encode($param['file_exts']),
        ];
        $dep->add($data);
        return self::success();
    }

    public function edit($request)
    {
        try {
            $param = v::input($request->all(), [
                'id'          => v::intVal()->setName('id'),
                'title'       => v::stringType()->length(1, 100)->setName('title'),
                'max_size_mb' => v::intVal()->min(1)->setName('max_size_mb'),
                'image_exts'  => v::arrayType()->setName('image_exts'),
                'file_exts'   => v::arrayType()->setName('file_exts'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $dep = $this->dep;
        $resDep = $dep->firstByTitle($param['title']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::error('规则标题已存在');
        }
        $data = [
            'title'       => $param['title'],
            'max_size_mb' => $param['max_size_mb'],
            'image_exts'  => json_encode($param['image_exts']),
            'file_exts'   => json_encode($param['file_exts']),
        ];
        $dep->edit($param['id'], $data);
        return self::success();
    }

    public function del($request)
    {
        try {
            $param = v::input($request->all(), [
                'id' => v::intVal()->setName('id'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $this->dep->del($param['id'], ['is_del' => CommonEnum::YES]);
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 50;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->dep->list($param);
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
