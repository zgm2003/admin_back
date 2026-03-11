<?php

namespace app\module\System;

use app\dep\System\UploadDriverDep;
use app\enum\UploadConfigEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\System\UploadDriverValidate;

/**
 * 上传驱动模块
 * 负责：云存储驱动配置的 CRUD（COS/OSS/S3 等），密钥加密存储
 */
class UploadDriverModule extends BaseModule
{
    /**
     * 初始化（返回驱动类型字典）
     */
    public function init($request)
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setUploadDriverArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 新增驱动（同一驱动下桶名不可重复，密钥加密存储）
     */
    public function add($request)
    {
        $param = $this->validate($request, UploadDriverValidate::add());
        self::throwIf(
            $this->dep(UploadDriverDep::class)->existsByDriverBucket($param['driver'], $param['bucket']),
            '同一驱动下该桶已存在'
        );

        $id = $this->dep(UploadDriverDep::class)->add([
            'driver'          => $param['driver'],
            'secret_id_enc'   => KeyVault::encrypt($param['secret_id']),
            'secret_id_hint'  => KeyVault::hint($param['secret_id']),
            'secret_key_enc'  => KeyVault::encrypt($param['secret_key']),
            'secret_key_hint' => KeyVault::hint($param['secret_key']),
            'bucket'          => $param['bucket'],
            'region'          => $param['region'],
            'role_arn'        => $param['role_arn'] ?? null,
            'appid'           => $param['appid'] ?? null,
            'endpoint'        => $param['endpoint'] ?? null,
            'bucket_domain'   => $param['bucket_domain'] ?? null,
        ]);
        return self::success(['id' => $id]);
    }

    /**
     * 编辑驱动（密钥留空则不更新，非空则重新加密）
     */
    public function edit($request)
    {
        $param = $this->validate($request, UploadDriverValidate::edit());
        self::throwIf(
            $this->dep(UploadDriverDep::class)->existsByDriverBucket($param['driver'], $param['bucket'], $param['id']),
            '同一驱动下该桶已存在'
        );

        $data = [
            'driver'        => $param['driver'],
            'bucket'        => $param['bucket'],
            'region'        => $param['region'],
            'role_arn'      => $param['role_arn'] ?? null,
            'appid'         => $param['appid'] ?? null,
            'endpoint'      => $param['endpoint'] ?? null,
            'bucket_domain' => $param['bucket_domain'] ?? null,
        ];

        // 密钥留空不改，非空则重新加密写入
        if (!empty($param['secret_id'])) {
            $data['secret_id_enc']  = KeyVault::encrypt($param['secret_id']);
            $data['secret_id_hint'] = KeyVault::hint($param['secret_id']);
        }
        if (!empty($param['secret_key'])) {
            $data['secret_key_enc']  = KeyVault::encrypt($param['secret_key']);
            $data['secret_key_hint'] = KeyVault::hint($param['secret_key']);
        }

        $this->dep(UploadDriverDep::class)->update($param['id'], $data);
        return self::success();
    }

    /**
     * 删除驱动（支持批量）
     */
    public function del($request)
    {
        $param = $this->validate($request, UploadDriverValidate::del());
        $this->dep(UploadDriverDep::class)->delete($param['id']);
        return self::success();
    }

    /**
     * 驱动列表（分页，密钥只返回脱敏提示，不返回明文）
     */
    public function list($request)
    {
        $param = $this->validate($request, UploadDriverValidate::list());
        $res = $this->dep(UploadDriverDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'              => $item['id'],
            'driver'          => $item['driver'],
            'driver_show'     => UploadConfigEnum::$driverArr[$item->driver],
            'secret_id_hint'  => $item['secret_id_hint'] ?? '',
            'secret_key_hint' => $item['secret_key_hint'] ?? '',
            'bucket'          => $item['bucket'],
            'region'          => $item['region'],
            'role_arn'        => $item['role_arn'],
            'appid'           => $item['appid'],
            'endpoint'        => $item['endpoint'],
            'bucket_domain'   => $item['bucket_domain'],
            'created_at'      => $item['created_at'],
            'updated_at'      => $item['updated_at'],
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }
}
