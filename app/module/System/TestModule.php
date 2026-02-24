<?php

namespace app\module\System;

use app\dep\System\TestDep;
use app\module\BaseModule;
use app\validate\System\TestValidate;

/**
 * 测试模块（开发调试用）
 * 负责：CRUD 全流程演示，包含多种字段类型
 */
class TestModule extends BaseModule
{
    /**
     * 初始化（返回状态、类型、性别等字典）
     */
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

    /**
     * 列表查询（分页）
     */
    public function list($request): array
    {
        $param = $this->validate($request, TestValidate::list());
        $res = $this->dep(TestDep::class)->list($param);

        $list = $res->map(fn($item) => [
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
        ]);

        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 新增
     */
    public function add($request): array
    {
        $param = $this->validate($request, TestValidate::add());
        $this->dep(TestDep::class)->add($param);
        return self::success();
    }

    /**
     * 编辑（先校验记录是否存在）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, TestValidate::edit());
        $row = $this->dep(TestDep::class)->get((int)$param['id']);
        self::throwNotFound($row);
        $this->dep(TestDep::class)->update((int)$param['id'], $param);
        return self::success();
    }

    /**
     * 删除（支持批量）
     */
    public function del($request): array
    {
        $param = $this->validate($request, TestValidate::del());
        $this->dep(TestDep::class)->delete($param['id']);
        return self::success();
    }
}
