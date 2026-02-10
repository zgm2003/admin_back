<?php

namespace app\module\System;

use app\dep\System\AuthPlatformDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\System\AuthPlatformValidate;

class AuthPlatformModule extends BaseModule
{
    protected AuthPlatformDep $authPlatformDep;
    protected DictService $dictService;

    public function __construct()
    {
        $this->authPlatformDep = $this->dep(AuthPlatformDep::class);
        $this->dictService = $this->svc(DictService::class);
    }

    public function init($request): array
    {
        $data['dict'] = $this->dictService
            ->setCommonStatusArr()
            ->setAuthPlatformLoginTypeArr()
            ->getDict();
        return self::success($data);
    }

    public function list($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::list());
        $res = $this->authPlatformDep->list($param);

        $list = $res->map(function ($it) {
            return [
                'id'             => $it['id'],
                'code'           => $it['code'],
                'name'           => $it['name'],
                'login_types'    => $it['login_types'],
                'access_ttl'     => $it['access_ttl'],
                'refresh_ttl'    => $it['refresh_ttl'],
                'bind_platform'  => $it['bind_platform'],
                'bind_device'    => $it['bind_device'],
                'bind_ip'        => $it['bind_ip'],
                'single_session' => $it['single_session'],
                'max_sessions'   => $it['max_sessions'],
                'allow_register' => $it['allow_register'],
                'status'         => $it['status'],
                'status_name'    => CommonEnum::$statusArr[$it['status']] ?? '',
                'created_at'     => $it['created_at'],
                'updated_at'     => $it['updated_at'],
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    public function add($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::add());

        self::throwIf(
            $this->authPlatformDep->existsByCode($param['code']),
            "平台标识 [{$param['code']}] 已存在"
        );

        $this->authPlatformDep->addPlatform([
            'code'           => $param['code'],
            'name'           => $param['name'],
            'login_types'    => \json_encode($param['login_types']),
            'access_ttl'     => (int)$param['access_ttl'],
            'refresh_ttl'    => (int)$param['refresh_ttl'],
            'bind_platform'  => (int)$param['bind_platform'],
            'bind_device'    => (int)$param['bind_device'],
            'bind_ip'        => (int)$param['bind_ip'],
            'single_session' => (int)$param['single_session'],
            'max_sessions'   => (int)$param['max_sessions'],
            'allow_register' => (int)$param['allow_register'],
            'status'         => CommonEnum::YES,
            'is_del'         => CommonEnum::NO,
        ]);

        return self::success();
    }

    public function edit($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::edit());

        $row = $this->authPlatformDep->get((int)$param['id']);
        self::throwNotFound($row);

        $ok = $this->authPlatformDep->updateById((int)$param['id'], [
            'name'           => $param['name'],
            'login_types'    => \json_encode($param['login_types']),
            'access_ttl'     => (int)$param['access_ttl'],
            'refresh_ttl'    => (int)$param['refresh_ttl'],
            'bind_platform'  => (int)$param['bind_platform'],
            'bind_device'    => (int)$param['bind_device'],
            'bind_ip'        => (int)$param['bind_ip'],
            'single_session' => (int)$param['single_session'],
            'max_sessions'   => (int)$param['max_sessions'],
            'allow_register' => (int)$param['allow_register'],
        ], $row->code);

        self::throwIf(!$ok, '更新失败');
        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::del());
        $this->authPlatformDep->deleteByIds($param['id']);
        return self::success();
    }

    public function status($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::status());
        $ok = $this->authPlatformDep->setStatusById((int)$param['id'], (int)$param['status']);
        self::throwIf(!$ok, '平台不存在');
        return self::success();
    }
}
