<?php

namespace app\module\System;

use app\dep\System\TestDep;
use app\module\BaseModule;
use app\validate\System\TestValidate;

class TestModule extends BaseModule
{
    protected TestDep $testDep;

    public function __construct()
    {
        $this->testDep = $this->dep(TestDep::class);
    }

    public function init($request): array
    {
        return self::success([
            'dict' => [
                'status_arr' => [
                    ['value' => 1, 'label' => '启用'],
                    ['value' => 2, 'label' => '禁用'],
                ],
                'type_arr' => [
                    ['value' => 1, 'label' => '类型A'],
                    ['value' => 2, 'label' => '类型B'],
                    ['value' => 3, 'label' => '类型C'],
                ],
                'sex_arr' => [
                    ['value' => 0, 'label' => '未知'],
                    ['value' => 1, 'label' => '男'],
                    ['value' => 2, 'label' => '女'],
                ],
                'is_vip_arr' => [
                    ['value' => 1, 'label' => '是'],
                    ['value' => 2, 'label' => '否'],
                ],
                'is_hot_arr' => [
                    ['value' => 1, 'label' => '是'],
                    ['value' => 2, 'label' => '否'],
                ],
            ]
        ]);
    }

    public function list($request): array
    {
        $param = $this->validate($request, TestValidate::list());
        $res = $this->testDep->list($param);
        
        $list = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'username' => $item->username,
                'nickname' => $item->nickname,
                'email' => $item->email,
                'phone' => $item->phone,
                'avatar' => $item->avatar,
                'cover_image' => $item->cover_image,
                'status' => $item->status,
                'type' => $item->type,
                'sex' => $item->sex,
                'age' => $item->age,
                'score' => $item->score,
                'url' => $item->url,
                'published_at' => $item->published_at,
                'birthday' => $item->birthday,
                'is_vip' => $item->is_vip,
                'is_hot' => $item->is_hot,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
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

    public function add($request): array
    {
        $param = $this->validate($request, TestValidate::add());
        
        $this->testDep->add($param);
        
        return self::success();
    }

    public function edit($request): array
    {
        $param = $this->validate($request, TestValidate::edit());
        
        $row = $this->testDep->get((int)$param['id']);
        self::throwNotFound($row);
        
        $this->testDep->update((int)$param['id'], $param);
        
        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, TestValidate::del());
        
        $this->testDep->delete($param['id']);
        
        return self::success();
    }
}