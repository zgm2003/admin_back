<?php

namespace app\module\Web;

use app\dep\Web\MusicDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Carbon\Carbon;


class MusicModule extends BaseModule
{
    public $musicDep;

    public function __construct()
    {
        $this->musicDep = new MusicDep();
    }
    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setMusicArr()
            ->getDict();
        return self::response($data);
    }

    public function add($request)
    {
        $param = $request->all();
        if (
            empty($param['name']) || empty($param['artist']) || empty($param['cover']) || empty($param['url'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->musicDep->firstByNameAndArtist($param['name'], $param['artist']);
        if ($resDep){
            return self::response([], '音乐已入库', 100);
        }
        $data = [
            'name' => $param['name'],
            'artist' => $param['artist'],
            'cover' => $param['cover'],
            'url' => $param['url'],
        ];
        $this->musicDep->add($data);
        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all();
        if (
            empty($param['name']) || empty($param['artist']) || empty($param['cover']) || empty($param['url'])
        ) {
            return self::response([], '必填项不能为空', 100);
        }
        $resDep = $this->musicDep->firstByNameAndArtist($param['name'], $param['artist']);
        if ($resDep && $resDep['id'] != $param['id']){
            return self::response([], '音乐已入库', 100);
        }
        $data = [
            'name' => $param['name'],
            'artist' => $param['artist'],
            'cover' => $param['cover'],
            'url' => $param['url'],
        ];
        $this->musicDep->edit($param['id'],$data);
        return self::response();
    }
    public function del($request)
    {

        $param = $request->all();

        $dep = $this->musicDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {
        $dep = $this->musicDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 10;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'artist' => $item['artist'],
                'cover' => $item['cover'],
                'url' => $item['url'],
                'is_del' => $item['is_del'],
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

