<?php
namespace app\module\Article;

use app\dep\Article\CategoryDep;
use app\dep\Article\ArticleDep;
use app\dep\Article\TagDep;
use app\dep\SystemDep;
use app\dep\User\UsersDep;
use app\enum\ArticleEnum;
use app\enum\CommonEnum;
//use app\Jobs\article\ArticleModelJob;
use app\Lib\AliCloud\AigcSdk;
use app\Module\BaseModule;
use app\Service\DictService;
use Webman\RedisQueue\Redis;


class ArticleModule extends BaseModule
{
    public $articleDep;
    public $tagDep;
    public $categoryDep;
    public $articleEnum;
    public $systemDep;
    public $article_prompt;

    public $usersDep;

    public function __construct()
    {
        $this->articleDep = new ArticleDep();
        $this->tagDep = new TagDep();
        $this->categoryDep = new CategoryDep();
        $this->articleEnum = new ArticleEnum();
        $this->systemDep = new SystemDep();
        $this->article_prompt = $this->systemDep->first()->article_prompt;
        $this->usersDep = new UsersDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setIsArr()
            ->setArticleStatusArr()
            ->setArticleTypeArr()
            ->setArticleTagArr()
            ->setArticleCategoryArr()
            ->getDict();
        $data['article_prompt'] = $this->article_prompt;
        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        $user = $request->user();
        if (
            empty($param['title']) || empty($param['desc']) || empty($param['cover']) || empty($param['tag_id'])
                || empty($param['category_id']) || empty($param['isTop']) || empty($param['status'])
                || empty($param['type']) || empty($param['isCarousel']) || empty($param['prompt'])

        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->articleDep->firstByTitle($param['title']);
        if ($resDep){
            return self::response([], '文章已存在', 100);
        }
        $data = [
            'title' => $param['title'],
            'desc' => $param['desc'],
            'cover' => $param['cover'],
            'tag_id' => json_encode($param['tag_id']),
            'category_id' => $param['category_id'],
            'is_top' => $param['isTop'],
            'status' => $param['status'],
            'type' => $param['type'],
            'is_carousel' => $param['isCarousel'],
            'content' => $param['content']??'',
            'prompt' => $param['prompt'],
            'created_user_id' => $user->id,
        ];
        $this->articleDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->articleDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->articleDep;
        if (
            empty($param['title']) || empty($param['desc']) || empty($param['cover']) || empty($param['tag_id'])
                || empty($param['category_id']) || empty($param['isTop']) || empty($param['status'])
                || empty($param['type']) || empty($param['isCarousel']) || empty($param['prompt'])

        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->articleDep->firstByTitle($param['title']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '文章已存在', 100);
        }
        $data = [
            'title' => $param['title'],
            'desc' => $param['desc'],
            'cover' => $param['cover'],
            'tag_id' => json_encode($param['tag_id']),
            'category_id' => $param['category_id'],
            'is_top' => $param['isTop'],
            'status' => $param['status'],
            'type' => $param['type'],
            'is_carousel' => $param['isCarousel'],
            'content' => $param['content']??'',
            'prompt' => $param['prompt'],
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function batchEdit($request)
    {

        $param = $request->all();
        $dep = $this->articleDep;
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
        if ($param['field'] == 'tag_id') {
            if (empty($param['tag_id'])) {
                return self::response([], '标签不能为空', 100);
            }
            $data = [
                'tag_id' => json_encode($param['tag_id']),
            ];
            $dep->edit($id, $data);
        }
        if ($param['field'] == 'status') {
            if (empty($param['status'])) {
                return self::response([], '状态不能为空', 100);
            }
            $data = [
                'status' => $param['status'],
            ];
            $dep->edit($id, $data);
        }
        if ($param['field'] == 'is_carousel') {
            if (empty($param['is_carousel'])) {
                return self::response([], '请选择是否轮播', 100);
            }
            $data = [
                'is_carousel' => $param['is_carousel'],
            ];
            $dep->edit($id, $data);
        }
        if ($param['field'] == 'is_top') {
            if (empty($param['is_top'])) {
                return self::response([], '请选择是否置顶', 100);
            }
            $data = [
                'is_top' => $param['is_top'],
            ];
            $dep->edit($id, $data);
        }
        return self::response();
    }

    public function list($request)
    {

        $dep = $this->articleDep;
        $tagDep = $this->tagDep;
        $categoryDep = $this->categoryDep;
        $usersDep = $this->usersDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);
        $resCensus = $this->articleDep->census()->keyBy('status');

        $data['census'] = $resCensus->toArray();
        $data['list'] = $resList->map(function ($item) use ($tagDep, $categoryDep, $usersDep) {
            //标签
            $tagIds = json_decode($item['tag_id'],true);
            $tagNames = [];
            foreach ($tagIds as $tagId) {
                $tag = $tagDep->first($tagId);
                $tagNames[] = $tag['name'];
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
                'tag_name' => $tagNames,
                'category_id' => $item['category_id'],
                'category_name' => $resCategory->name,
                'is_top' => $item['is_top'],
                'status' => $item['status'],
                'status_msg' => $item['status_msg'],
                'prompt' => $item['prompt'],
                'type' => $item['type'],
                'type_name' => ArticleEnum::$typesArr[$item['type']],
                'is_carousel' => $item['is_carousel'],
                'content' => $item['content'],
                'created_user_name' => $resUser['username'],
                'created_user_id' => $item['created_user_id'],
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

    public function testPrompt($request)
    {
        $param = $request->all();
        if (
            empty($param['title']) || empty($param['desc']) || empty($param['tag_id'])
                || empty($param['category_id']) || empty($param['prompt'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }

        $tagNames = [];
        foreach ($param['tag_id'] as $tagId) {
            $tag = $this->tagDep->first($tagId);
            $tagNames[] = $tag['name'];
        }
        $resCategory = $this->categoryDep->first($param['category_id']);
        $prompt = $param['prompt'];
        $chat = str_replace('{title}', $param['title'], $prompt);
        $chat = str_replace('{desc}', $param['desc'], $chat);
        $chat = str_replace('{tag}', json_encode($tagNames), $chat);
        $chat = str_replace('{category}', $resCategory->name, $chat);
        $sdk = new AigcSdk();
        $resChat = $sdk->chat("你是一名CSDN上的博客博主", $chat);
        $origin = $resChat['output']['choices'][0]['message']['content'];

        return self::response(['origin_result' => $origin]);
    }

    public function confirmPrompt($request)
    {
        $param = $request->all();

        if (empty($param['prompt'])) {
            return self::response([], '请输入提示词', 100);
        }

        $dep = $this->systemDep;
        $resSystem = $dep->first();

        $dep->edit(1, ['article_prompt' => $param['prompt']]);
        return self::response();
    }
    public function toModel($request)
    {
        $param = $request->all();

        $dep = $this->articleDep;
        $data = [
            'status' => $this->articleEnum::MODEL
        ];

        $dep->edit($param['id'], $data);

        if (!is_array($param['id'])) {
            $param['id'] = [$param['id']];
        }
        // 队列名
        $queue = 'article-model';
        foreach ($param['id'] as $id) {

            Redis::send($queue, $id);
//            ArticleModelJob::dispatch($id);
        }
        return self::response();
    }
    public function toReview($request)
    {
        $param = $request->all();

        $dep = $this->articleDep;
        $data = [
            'status' => $this->articleEnum::REVIEW
        ];

        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function toRelease($request)
    {
        $param = $request->all();

        $dep = $this->articleDep;
        $data = [
            'status' => $this->articleEnum::RELEASE
        ];

        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function toRemove($request)
    {
        $param = $request->all();

        $dep = $this->articleDep;
        $data = [
            'status' => $this->articleEnum::REMOVE
        ];

        $dep->edit($param['id'], $data);

        return self::response();
    }
}

