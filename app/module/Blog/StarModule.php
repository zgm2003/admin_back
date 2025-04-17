<?php
namespace app\module\Blog;

use app\dep\Blog\StarDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class StarModule extends BaseModule
{

    public $StarDep;

    public function __construct()
    {

        $this->StarDep = new StarDep();
    }

    public function starCount($request)
    {
        $param = $request->all();
        $res = $this->StarDep->allByArticle($param['article_id']);
        $data = [
            'count' => count($res),
        ];
        return self::response($data);
    }
    public function isStar($request)
    {
        $user = $request->user();
        $param = $request->all();
        $res = $this->StarDep->firstByUserAndArticle($user->id,$param['article_id']);
        if($res){
            $data = [
                'is_star' => true,
            ];
            return self::response($data);
        }else{
            $data = [
                'is_star' => false,
            ];
            return self::response($data);
        }
    }
    public function add($request)
    {
        $param = $request->all();
        $user = $request->user();
        $res = $this->StarDep->firstByUserAndArticle($user->id,$param['article_id']);
        if($res){
            return self::response([],'点赞过了',100);
        }
        $data = [
            'user_id' => $user->id,
            'article_id' => $param['article_id'],
        ];
        $this->StarDep->add($data);

        $res1 = $this->StarDep->allByArticle($param['article_id']);
        $data1 = [
            'count' => count($res1)
        ];
        return self::response($data1);
    }


    public function del($request)
    {

        $param = $request->all();
        $dep = $this->StarDep;
        $user = $request->user();
        $res = $this->StarDep->firstByUserAndArticle($user->id,$param['article_id']);
        $dep->del($res->id);
        $res1 = $this->StarDep->allByArticle($param['article_id']);
        $data = [
            'count' => count($res1)
        ];

        return self::response($data);
    }

}

