<?php

namespace app\module\Blog;

use app\dep\Article\CategoryDep;
use app\dep\Blog\BlogDep;
use app\dep\Article\TagDep;
use app\dep\Blog\StarDep;
use app\dep\SystemDep;
use app\dep\User\UsersDep;
use app\dep\User\UsersTokenDep;
use app\dep\Web\VisitorDep;
use app\enum\BlogEnum;
use app\enum\CommonEnum;
use app\Module\BaseModule;
use app\Service\DictService;
use Carbon\Carbon;
use GuzzleHttp\Client;


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
    public $visitorDep;
    public $tokenDep;

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
        $this->visitorDep = new VisitorDep();
        $this->tokenDep=  new UsersTokenDep();
    }

    public function init($request)
    {

        $dictService = new DictService();
        $data1['dict'] = $dictService
            ->setArticleTypeArr()
            ->setBlogTagArr()
            ->setBlogCategoryArr()
            ->setCarouselArticlksArr()
            ->getDict();

        $user = null;
        if (!empty($param['token'])) {
            $tokenDep = $this->tokenDep->firstByToken($param['token']);
            $user = $this->usersDep->first($tokenDep['user_id']);
        }
        $ip = $request->getRealIp();
        $client = new Client();
        $response = $client->get("http://ip-api.com/json/{$ip}?lang=zh-CN");
        $ipData = json_decode($response->getBody()->getContents(), true);
        $city = $ipData['city'] ?? '未知';

        if ($user) {
            // 优先通过用户ID查询访问记录
            $visitor = $this->visitorDep->firstByUserId($user['id']);
            if (!$visitor) {
                // 如果未通过用户ID查询到，再尝试通过IP查询
                $visitor = $this->visitorDep->firstByIp($ip);
                if ($visitor && $visitor['user_id'] == -1) {
                    // 如果查询到的是游客记录，则更新该记录的用户ID和时间
                    $data = [
                        'user_id' => $user['id'],
                        'created_at' => Carbon::now(),
                    ];
                    $this->visitorDep->edit($visitor['id'], $data);
                } else {
                    // 没有记录，则新增一条记录
                    $data = [
                        'user_id' => $user['id'],
                        'ip' => $ip,
                        'city' => $city,
                    ];
                    $this->visitorDep->add($data);
                }
            } else {
                // 如果已经存在记录，则更新访问时间
                $data = [
                    'created_at' => Carbon::now(),
                ];
                $this->visitorDep->edit($visitor['id'], $data);
            }
        } else {
            // 游客状态，只以IP查询记录
            $visitor = $this->visitorDep->firstByIp($ip);
            if ($visitor) {
                $data = [
                    'created_at' => Carbon::now(),
                ];
                $this->visitorDep->edit($visitor['id'], $data);
            } else {
                $data = [
                    'user_id' => -1,
                    'ip' => $ip,
                    'city' => $city,
                ];
                $this->visitorDep->add($data);
            }
        }


        return self::response($data1);
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

