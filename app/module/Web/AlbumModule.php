<?php

namespace app\module\Web;

use app\dep\Web\AlbumDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;


class AlbumModule extends BaseModule
{
    public $albumDep;

    public function __construct()
    {
        $this->albumDep = new AlbumDep();
    }

    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAlbumArr()
            ->getDict();

        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        foreach (['title','cover','images_list'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        $resDep = $this->albumDep->firstByTitle($param['title']);
        if ($resDep){
            return self::response([], '相册已存在', 100);
        }
        $data = [
            'title' => $param['title'],
            'cover' => $param['cover'],
            'desc' => $param['desc'] ?? '',
            'password' => $param['password'] ?? '',
            'is_lock' => $param['is_lock'] ?? CommonEnum::NO,
            'images_list' => json_encode($param['images_list']),
        ];
        $this->albumDep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->albumDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {

        $param = $request->all();

        $dep = $this->albumDep;
        foreach (['title','cover','images_list'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        $resDep = $this->albumDep->firstByTitle($param['title']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '相册已存在', 100);
        }
        $data = [
            'title' => $param['title'],
            'cover' => $param['cover'],
            'desc' => $param['desc'] ?? '',
            'password' => $param['password'] ?? '',
            'is_lock' => $param['is_lock'] ?? CommonEnum::NO,
            'images_list' => json_encode($param['images_list']),
        ];
        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function batchEdit($request)
    {

        $param = $request->all();
        $ids = is_array($param['ids']) ? $param['ids'] : [$param['ids']];
        $dep = $this->albumDep;

        if ($param['field'] == 'desc') {
            $data = [
                'desc' => $param['desc'],
            ];
            $dep->batchEdit($ids, $data);
        }


        return self::response();

    }

    public function list($request)
    {

        $dep = $this->albumDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 20;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);
        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'desc' => $item['desc'] ?? CommonEnum::DEFAULT_NULL,
                'cover' => $item['cover'],
                'is_lock' => $item['is_lock'],
                'password' => $item['password'],
                'images_list' => json_decode($item['images_list']),
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

    public function detail($request){
        $param = $request->all();
        $resDetail = $this->albumDep->first($param['id']);

        // 解析 images_list
        $imagesList = json_decode($resDetail['images_list']);

        // 生成 src_list 数组
        $srcList = array_map(function($item) {
            return $item->url;  // 提取每个元素的 url
        }, $imagesList);

        // 返回数据
        $data['detail'] = [
            'id' => $param['id'],
            'title' => $resDetail['title'],
            'desc' => $resDetail['desc'] ?? CommonEnum::DEFAULT_NULL,
            'cover' => $resDetail['cover'],
            'is_lock' => $resDetail['is_lock'],
            'images_list' => $imagesList, // 原始数据
            'src_list' => $srcList, // 新增的图片 URL 数组
            'created_at' => $resDetail['created_at']->format('Y-m-d'),
            'num' => count($imagesList),
        ];

        return self::response($data);
    }

    public function check($request){
        $param = $request->all();
        if (empty($param['password'])||empty($param['id'])){
            return self::response([], '参数错误', 100);
        }
        $resDetail = $this->albumDep->first($param['id']);
        if ($resDetail['password'] == $param['password']){
            return self::response();
        }else{
            return self::response([], '密码错误', 100);
        }



    }
}

