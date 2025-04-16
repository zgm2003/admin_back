<?php

namespace app\module\Article;

use app\dep\Article\ArticleDep;
use app\dep\Article\TagDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class TagModule extends BaseModule
{
    public $tagDep;
    public $articleDep;
    public $usersDep;

    public function __construct()
    {
        $this->tagDep = new TagDep();
        $this->articleDep = new ArticleDep();
        $this->usersDep = new UsersDep();
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
        $resDep = $this->tagDep->firstByName($param['name']);
        if ($resDep) {
            return self::response([], '分类名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
            'created_user_id' => $user->id,
        ];
        $this->tagDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->tagDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->tagDep;
        $user = $request->user();
        if (empty($param['name'])

        ) {
            return self::response([], '分类名不能为空', 100);
        }
        $resDep = $dep->firstByName($param['name']);
        if ($resDep && $resDep['id'] != $param['id']) {
            return self::response([], '分类名已存在', 100);
        }
        $data = [
            'name' => $param['name'],
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }

    public function list($request)
    {
        $dep = $this->tagDep;
        $articleDep = $this->articleDep;
        $usersDep = $this->usersDep;
        $param = $request->all();
//        $param['user_id'] = $request->user->id;
        $resArticle = $articleDep->allOK();


        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use($resArticle,$usersDep){
            $resUser = $usersDep->first($item['created_user_id']);
            // 统计文章数量
            $articleNum = $resArticle->filter(function ($article) use ($item) {
                return in_array($item['id'],json_decode($article['tag_id'],true));
            })->count();

            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'created_user_id' => $item['created_user_id'],
                'created_user_name' => $resUser['username'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString(),
                'article_num' => $articleNum,  // 添加文章数量
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

