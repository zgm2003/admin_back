<?php

namespace app\module\Ai;

use app\dep\Ai\CategoryDep;
use app\dep\Ai\AiDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class CategoryModule extends BaseModule
{
    public $categoryDep;

    public $usersDpe;
    private $aiDep;

    public function __construct()
    {
        $this->categoryDep = new CategoryDep();
        $this->usersDpe = new UsersDep();
        $this->aiDep = new AiDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->getDict();

        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        $user = $request->user();
        if (empty($param['name'])
        ) {
            return self::response([], '分类名不能为空', 100);
        }
        $resDep = $this->categoryDep->firstByName($param['name']);
        if ($resDep){
            return self::response([], '分类名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'icon' => $param['icon']??'',
            'created_user_id' => $user->id,
        ];
        $this->categoryDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->categoryDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->categoryDep;
        $user = $request->user();
        if (empty($param['name'])

        ) {
            return self::response([], '分类名不能为空', 100);
        }
        $resDep = $dep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '分类名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'icon' => $param['icon']??'',
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {

        $dep = $this->categoryDep;
        $aiDep = $this->aiDep;
        $usersDpe = $this->usersDpe;
        $param = $request->all();
        $resAi = $aiDep->allOK();
        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use($usersDpe,$resAi){
            $resUser = $usersDpe->first($item['created_user_id']);
            $item['ai_num'] = $resAi->where('category_id',$item['id'])->count();
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'icon' => $item['icon'],
                'created_user_id' => $item['created_user_id'],
                'created_user_name' => $resUser['username'],
                'ai_num' => $item['ai_num'],
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

}

