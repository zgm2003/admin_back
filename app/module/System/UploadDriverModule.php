<?php

namespace app\module\System;

use app\dep\System\UploadDriverDep;
use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\DictService;
use app\enum\CommonEnum;
use app\validate\System\UploadDriverValidate;

class UploadDriverModule extends BaseModule
{
    protected UploadDriverDep $uploadDriverDep;
    protected DictService $dictService;

    public function __construct()
    {
        $this->uploadDriverDep = $this->dep(UploadDriverDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request)
    {
        $data['dict'] = $this->dictService
            ->setUploadDriverArr()
            ->getDict();
        return self::success($data);
    }

    public function add($request)
    {
        $param = $this->validate($request, UploadDriverValidate::add());
        $exists = $this->uploadDriverDep->findByDriverBucket($param['driver'], $param['bucket']);
        self::throwIf($exists, '同一驱动下该桶已存在');

        $data = [
            'driver' => $param['driver'],
            'secret_id_enc' => KeyVault::encrypt($param['secret_id']),
            'secret_id_hint' => KeyVault::hint($param['secret_id']),
            'secret_key_enc' => KeyVault::encrypt($param['secret_key']),
            'secret_key_hint' => KeyVault::hint($param['secret_key']),
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
        $param = $this->validate($request, UploadDriverValidate::edit());
        $exists = $this->uploadDriverDep->findByDriverBucket($param['driver'], $param['bucket']);
        self::throwIf($exists && $exists['id'] != $param['id'], '同一驱动下该桶已存在');

        $data = [
            'driver' => $param['driver'],
            'bucket' => $param['bucket'],
            'region' => $param['region'],
            'role_arn' => $param['role_arn'] ?? null,
            'appid' => $param['appid'] ?? null,
            'endpoint' => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];

        // 密钥留空不改，非空则重新加密
        if (!empty($param['secret_id'])) {
            $data['secret_id_enc'] = KeyVault::encrypt($param['secret_id']);
            $data['secret_id_hint'] = KeyVault::hint($param['secret_id']);
        }
        if (!empty($param['secret_key'])) {
            $data['secret_key_enc'] = KeyVault::encrypt($param['secret_key']);
            $data['secret_key_hint'] = KeyVault::hint($param['secret_key']);
        }

        $this->uploadDriverDep->update($param['id'], $data);
        return self::success();
    }

    public function del($request)
    {
        $param = $this->validate($request, UploadDriverValidate::del());
        $this->uploadDriverDep->delete($param['id']);
        return self::success();
    }

    public function list($request)
    {
        $param = $this->validate($request, UploadDriverValidate::list());
        $res = $this->uploadDriverDep->list($param);
        $list = $res->map(function ($item) {
            return [
                'id' => $item['id'],
                'driver' => $item['driver'],
                'driver_show' => UploadConfigEnum::$driverArr[$item->driver],
                'secret_id_hint' => $item['secret_id_hint'] ?? '',
                'secret_key_hint' => $item['secret_key_hint'] ?? '',
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
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($list, $page);
    }
}
