<?php

namespace app\module\System;

use app\dep\System\UploadDriverDep;
use app\enum\UploadConfigEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

class UploadDriverModule extends BaseModule
{
    public $dep;

    public function __construct()
    {
        $this->dep = new UploadDriverDep();
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
        try {
            $param = v::input($request->all(), [
                'driver'       => v::stringType()->length(1, 20)->setName('driver'),
                'secret_id'    => v::stringType()->length(1, 255)->setName('secret_id'),
                'secret_key'   => v::stringType()->length(1, 255)->setName('secret_key'),
                'bucket'       => v::stringType()->length(1, 255)->setName('bucket'),
                'region'       => v::stringType()->length(1, 100)->setName('region'),
                'appid'        => v::optional(v::stringType())->setName('appid'),
                'endpoint'     => v::optional(v::stringType())->setName('endpoint'),
                'bucket_domain'=> v::optional(v::stringType())->setName('bucket_domain'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $data = [
            'driver' => $param['driver'],
            'secret_id' => $param['secret_id'],
            'secret_key' => $param['secret_key'],
            'bucket' => $param['bucket'],
            'region' => $param['region'],
            'appid' => $param['appid'] ?? null,
            'endpoint' => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];
        $id = $this->dep->add($data);
        return self::success(['id' => $id]);
    }

    public function edit($request)
    {
        try {
            $param = v::input($request->all(), [
                'id'           => v::intVal()->setName('id'),
                'driver'       => v::stringType()->length(1, 20)->setName('driver'),
                'secret_id'    => v::stringType()->length(1, 255)->setName('secret_id'),
                'secret_key'   => v::stringType()->length(1, 255)->setName('secret_key'),
                'bucket'       => v::stringType()->length(1, 255)->setName('bucket'),
                'region'       => v::stringType()->length(1, 100)->setName('region'),
                'appid'        => v::optional(v::stringType())->setName('appid'),
                'endpoint'     => v::optional(v::stringType())->setName('endpoint'),
                'bucket_domain'=> v::optional(v::stringType())->setName('bucket_domain'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $data = [
            'driver' => $param['driver'],
            'secret_id' => $param['secret_id'],
            'secret_key' => $param['secret_key'],
            'bucket' => $param['bucket'],
            'region' => $param['region'],
            'appid' => $param['appid'] ?? null,
            'endpoint' => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];
        $this->dep->edit($param['id'], $data);
        return self::success();
    }

    public function del($request)
    {
        try {
            $param = v::input($request->all(), [
                'id' => v::intVal()->setName('id'),
            ]);
        } catch (ValidationException $e) {
            return self::error($e->getMessage());
        }
        $this->dep->del($param['id'], ['is_del' => CommonEnum::YES]);
        return self::success();
    }

    public function list($request)
    {
        $param = $request->all();
        $param['page_size'] = $param['page_size'] ?? 50;
        $param['current_page'] = $param['current_page'] ?? 1;
        $res = $this->dep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'driver' => $item['driver'],
                'driver_show' => UploadConfigEnum::$driverArr[$item->driver],
                'secret_id' => $item['secret_id'],
                'secret_key' => $item['secret_key'],
                'bucket' => $item['bucket'],
                'region' => $item['region'],
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
