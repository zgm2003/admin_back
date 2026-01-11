<?php

namespace app\module\System;

use app\dep\System\UploadDriverDep;
use app\enum\UploadConfigEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use app\validate\System\UploadDriverValidate;

class UploadDriverModule extends BaseModule
{
    protected UploadDriverDep $uploadDriverDep;

    public function __construct()
    {
        $this->uploadDriverDep = new UploadDriverDep();
    }

    public function init($request){
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setUploadDriverArr()
            ->getDict();
        return self::success($data);
    }

    public function add($request)
    {
        try { $param = $this->validate($request, UploadDriverValidate::add()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $exists = $this->uploadDriverDep->findByDriverBucket($param['driver'], $param['bucket']);
        if ($exists) {
            return self::error('同一驱动下该桶已存在');
        }
        $data = [
            'driver' => $param['driver'],
            'secret_id' => $param['secret_id'],
            'secret_key' => $param['secret_key'],
            'bucket' => $param['bucket'],
            'region' => $param['region'],
            'role_arn' => $param['role_arn'] ?? null,
            'appid' => $param['appid'] ?? null,
            'endpoint' => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];
        $id = $this->uploadDriverDep->add($data);
        return self::success(['id' => $id]);
    }

    public function edit($request)
    {
        try { $param = $this->validate($request, UploadDriverValidate::edit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $exists = $this->uploadDriverDep->findByDriverBucket($param['driver'], $param['bucket']);
        if ($exists && $exists['id'] != $param['id']) {
            return self::error('同一驱动下该桶已存在');
        }
        $data = [
            'driver' => $param['driver'],
            'secret_id' => $param['secret_id'],
            'secret_key' => $param['secret_key'],
            'bucket' => $param['bucket'],
            'region' => $param['region'],
            'role_arn' => $param['role_arn'] ?? null,
            'appid' => $param['appid'] ?? null,
            'endpoint' => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];
        $this->uploadDriverDep->update($param['id'], $data);
        return self::success();
    }

    public function del($request)
    {
        try { $param = $this->validate($request, UploadDriverValidate::del()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $this->uploadDriverDep->delete($param['id']);
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->uploadDriverDep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'driver' => $item['driver'],
                'driver_show' => UploadConfigEnum::$driverArr[$item->driver],
                'secret_id' => $item['secret_id'],
                'secret_key' => $item['secret_key'],
                'bucket' => $item['bucket'],
                'region' => $item['region'],
                'role_arn' => $item['role_arn'],
                'appid' => $item['appid'],
                'endpoint' => $item['endpoint'],
                'bucket_domain' => $item['bucket_domain'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString(),
            ];
        });
        $page = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($list, $page);
    }
}
