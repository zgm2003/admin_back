<?php
namespace app\module\Web;

use app\dep\Article\ArticleDep;
use app\dep\Web\CommentDep;
use app\dep\User\UsersDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class CommentModule extends BaseModule
{
    public $commentDep;
    public $articleDep;
    public $usersDep;


    public function __construct()
    {
        $this->commentDep = new CommentDep();
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
        if (empty($param['content'])) {
            return self::response([], '评论内容不能为空', 100);
        }
        $data = [
            'article_id' => $param['article_id'],
            'user_id' => $user['id'],
            'content' => $param['content'],
            'parent_id' => $param['parent_id']??-1,
        ];
        $this->commentDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->commentDep;

        $dep->del($param['id']);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->articleDep;
        $user = $request->user();
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

    public function list($request)
    {
        $dep = $this->commentDep;
        $usersDep = $this->usersDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        // 获取所有评论和回复，按创建时间排序
        $resList = $dep->list($param);

        // 先处理评论数据
        $comments = $resList->map(function ($item) use ($usersDep) {
            $resUser = $usersDep->first($item['user_id']);
            return [
                'id' => $item['id'],
                'content' => $item['content'],
                'parentId' => $item['parent_id'],
                'createTime' => $item['created_at']->toDateTimeString(),
                'user' => [
                    'username' => $resUser['username'],
                    'avatar' => $resUser['avatar'],
                ],
            ];
        });

        // 递归组织评论和回复
        $groupedComments = $this->groupComments($comments);

        // 为每个评论添加回复信息
        $data['list'] = $groupedComments;

        // 分页信息
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::response($data);
    }

    public function listList($request)
    {

        $dep = $this->commentDep;
        $userDep = $this->usersDep;
        $articleDep = $this->articleDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) use($userDep,$articleDep,$dep) {
            $resUser = $userDep->first($item['user_id']);
            $resRely = $dep->first($item['parent_id']) ?? -1;
            $resRelyUser = $userDep->first($resRely->user_id ?? -1);
            $resArticle = $articleDep->first($item['article_id']);
            return [
                'id' => $item['id'],
                'username' => $resUser['username']??'',
                'avatar' => $resUser['avatar']??'',
                'relyUsername' => $resRelyUser['username'] ?? CommonEnum::DEFAULT_NULL,
                'content' => $item['content'],
                'articleTitle' => $resArticle['title'],
                'created_at' => $item['created_at']->toDateTimeString(),
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

// 递归组织评论和回复的函数
    private function groupComments($comments)
    {
        $grouped = [];

        foreach ($comments as $comment) {
            if ($comment['parentId'] == -1) {
                // 如果是评论，则初始化
                $grouped[$comment['id']] = [
                    'id' => $comment['id'],
                    'content' => $comment['content'],
                    'parentId' => $comment['parentId'],
                    'createTime' => $comment['createTime'],
                    'user' => $comment['user'],
                    'reply' => [
                        'total' => 0,
                        'list' => [],
                    ],
                ];
            } else {
                // 如果是回复，则添加到对应的评论下
                if (isset($grouped[$comment['parentId']])) {
                    $grouped[$comment['parentId']]['reply']['total']++;
                    $grouped[$comment['parentId']]['reply']['list'][] = $comment;
                }
            }
        }

        // 将数组转换为索引列表
        return array_values($grouped);
    }

}

