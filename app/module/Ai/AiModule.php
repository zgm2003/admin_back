<?php

namespace app\module\Ai;

use app\dep\Ai\CategoryDep;
use app\dep\Ai\AiDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class AiModule extends BaseModule
{
    public $categoryDep;
    public $aiDep;
    public $usersDpe;

    public function __construct()
    {
        $this->categoryDep = new CategoryDep();
        $this->aiDep = new AiDep();
        $this->usersDpe = new UsersDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAicategoryArr()
            ->getDict();

        return self::response($data);
    }
    public function init1($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAicategoryArr()
            ->getDict();

        return self::response($data);
    }
    public function add($request)
    {
        $param = $request->all();
        $user = $request->user();

        if (empty($param['title']) || empty($param['url']) || empty($param['link']) || empty($param['category_id'])) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->aiDep->firstByTitleAndCategoryId($param['title'], $param['category_id']);
        if ($resDep) {
            return self::response([], '当前分类AI已存在', 100);
        }
        $data = [
            'category_id' => $param['category_id'],
            'url' => $param['url'],
            'title' => $param['title'],
            'desc' => $param['desc'] ?? '',
            'link' => $param['link'],
            'created_user_id' => $user->id,
        ];
        $this->aiDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->aiDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();
        $user = $request->user();
        if (empty($param['title']) || empty($param['url']) || empty($param['link']) || empty($param['category_id'])) {
            return self::response([], '必填项不能为空', 100);
        }

        $resDep = $this->aiDep->firstByTitleAndCategoryId($param['title'], $param['category_id']);
        if ($resDep && $resDep->id != $param['id']) {
            return self::response([], '当前分类AI已存在', 100);
        }
        $data = [
            'category_id' => $param['category_id'],
            'url' => $param['url'],
            'title' => $param['title'],
            'desc' => $param['desc'] ?? '',
            'link' => $param['link'],
            'created_user_id' => $user->id,
        ];
        $this->aiDep->edit($param['id'], $data);
        return self::response();
    }

    public function batchEdit($request)
    {

        $param = $request->all();
        $dep = $this->aiDep;
        $id = $param['ids'];
        if ($param['field'] == 'category_id') {
            if (empty($param['category_id'])) {
                return self::response([], '分类不能为空', 100);
            }
            $data = [
                'category_id' => $param['category_id'],
            ];
            $dep->edit($id, $data);
        }
        return self::response();
    }

    public function list($request)
    {

        $dep = $this->aiDep;
        $categoryDep = $this->categoryDep;
        $usersDpe = $this->usersDpe;
        $param = $request->all();
        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use ($usersDpe, $categoryDep) {
            $resUser = $usersDpe->first($item['created_user_id']);
            $resCategory = $categoryDep->first($item['category_id']);
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'desc' => $item['desc'] ?? CommonEnum::DEFAULT_NULL,
                'url' => $item['url'],
                'link' => $item['link'],
                'created_user_id' => $item['created_user_id']??'',
                'created_user_name' => $resUser['username']??'',
                'category_id' => $item['category_id']??-1,
                'category_name' => $resCategory['name'] ?? CommonEnum::DEFAULT_NULL,
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::response($data);
    }


    public function list1($request)
    {
        // 获取依赖数据
        $dep = $this->aiDep;
        $categoryDep = $this->categoryDep;
        $param = $request->all();

        // 获取数据，按 category_id 分组
        $resList = $dep->list1($param);

        // 处理并构造返回数据
        $data['list'] = $resList->groupBy('category_id')->map(function ($items, $categoryId) use ($categoryDep) {
            // 获取分类名称
            $category = $categoryDep->first($categoryId);
            // 获取每个分类下的项
            $content = $items->map(function ($item){
                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'link' => $item['link'],
                    'desc' => $item['desc'] ?? CommonEnum::DEFAULT_NULL,
                    'created_at' => $item['created_at']->toDateTimeString(),
//                    'updated_at' => $item['updated_at']->toDateTimeString(),
                ];
            });

            // 返回每个分类的数据，包含分类名称和内容项
            return [
                'category_name' => $category['name'],
                'category_icon' => $category['icon'],
                'category_id' => $categoryId,
                'content' => $content
            ];
        });

        // 返回响应数据
        return self::response($data);
    }

}

