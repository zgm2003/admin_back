<?php

namespace app\module\Blog;

use app\dep\Article\CategoryDep;
use app\dep\Blog\BlogDep;
use app\dep\Article\TagDep;
use app\dep\Blog\StarDep;
use app\dep\SystemDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use app\enum\BlogEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use support\Redis;


class BlogModule extends BaseModule
{
    public $blogDep;
    public $tagDep;
    public $categoryDep;
    public $blogEnum;
    public $systemDep;
    public $article_prompt;

    public $usersDep;
    public $starDep;
    public $tokenDep;
    public $dictService;

    public function __construct()
    {
        $this->blogDep = new BlogDep();
        $this->tagDep = new TagDep();
        $this->categoryDep = new CategoryDep();
        $this->blogEnum = new BlogEnum();
        $this->systemDep = new SystemDep();
        $this->article_prompt = $this->systemDep->first()->article_prompt;
        $this->usersDep = new UsersDep();
        $this->starDep = new StarDep();
        $this->tokenDep=  new UsersTokenDep();
        $this->dictService = new DictService();
    }

    public function init($request)
    {

        // —— 1. 字典缓存读取（Pipeline 三键） ——
        $redis = Redis::connection('cache');
        $keys  = ['dict:tag_arr', 'dict:category_arr', 'dict:carousel_arr'];
        // 一次性获取多 key
        $cached = $redis->mGet($keys);

        $dict = [];
        $miss = false;
        foreach ($keys as $i => $key) {
            if (isset($cached[$i]) && $cached[$i] !== null) {
                $part = str_replace('dict:', '', $key);
                $dict[$part] = json_decode($cached[$i], true);
            } else {
                $miss = true;
                break;
            }
        }
        // 2. 缓存未命中，重建并写回
        if ($miss) {
            $dict = (new DictService())
                ->setBlogTagArr()
                ->setBlogCategoryArr()
                ->setCarouselArticlesArr()
                ->getDict();
            // Redis 事务批量写回
            $redis->multi();
            $redis->setEx('dict:tag_arr', 300, json_encode($dict['tag_arr'], JSON_UNESCAPED_UNICODE));
            $redis->setEx('dict:category_arr', 300, json_encode($dict['category_arr'], JSON_UNESCAPED_UNICODE));
            $redis->setEx('dict:carousel_arr', 300, json_encode($dict['carousel_arr'], JSON_UNESCAPED_UNICODE));
            $redis->exec();
        }
        return self::response($dict);
    }


    public function del($request)
    {

        $param = $request->all();

        $dep = $this->blogDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function detail($request)
    {
        $param = $request->all();

        $res = $this->blogDep->first($param['id']);

        // 标签
        $tagIds = json_decode($res['tag_id'], true);
        $tagObjects = [];
        foreach ($tagIds as $tagId) {
            $tag = $this->tagDep->first($tagId);
            $tagObjects[] = [
                'id' => $tag['id'],
                'name' => $tag['name'],
            ];
        }

        // 分类
        $resCategory = $this->categoryDep->first($res['category_id']);

        // 创建人
        $resUser = $this->usersDep->first($res['created_user_id']);

        $data['detail'] = [
            'id' => $res['id'],
            'title' => $res['title'],
            'desc' => $res['desc'],
            'cover' => $res['cover'],
            'content' => $res['content'],
            'tag_id' => $tagIds,
            'tag_name' => $tagObjects, // 修改为包含对象的列表
            'category_id' => $res['category_id'],
            'category_name' => $resCategory->name,
            'type' => $res['type'],
            'type_name' => blogEnum::$typesArr[$res['type']],
            'status' => $res['status'],
            'status_msg' => $res['status_msg'],
            'created_user_name' => $resUser['username'],
            'created_user_id' => $res['created_user_id'],
            'created_user_avatar' => $resUser['avatar'],
            'updated_at' => $res['updated_at']->toDateTimeString(),
        ];

        return self::response($data);
    }


    public function list($request)
    {

        $dep = $this->blogDep;
        $categoryDep = $this->categoryDep;
        $usersDep = $this->usersDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 10;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use ($categoryDep, $usersDep) {
            //标签
            $tagIds = json_decode($item['tag_id'], true);
            $tagObjects = [];
            foreach ($tagIds as $tagId) {
                $tag = $this->tagDep->first($tagId);
                $tagObjects[] = [
                    'id' => $tag['id'],
                    'name' => $tag['name'],
                ];
            }
            //分类
            $resCategory = $categoryDep->first($item['category_id']);

            //创建人
            $resUser = $usersDep->first($item['created_user_id']);

            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'desc' => $item['desc'],
                'cover' => $item['cover'],
                'tag_id' => $tagIds,
                'tag_name' => $tagObjects,
                'category_id' => $item['category_id'],
                'category_name' => $resCategory->name,
                'is_top' => $item['is_top'],
                'status' => $item['status'],
                'status_msg' => $item['status_msg'],
                'prompt' => $item['prompt'],
                'type' => $item['type'],
                'type_name' => blogEnum::$typesArr[$item['type']],
                'is_carousel' => $item['is_carousel'],
                'content' => $item['content'],
                'created_user_name' => $resUser['username'],
                'created_user_id' => $item['created_user_id'],
                'created_user_avatar' => $resUser['avatar'],
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

