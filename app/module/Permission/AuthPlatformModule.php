<?php

namespace app\module\Permission;

use app\dep\Permission\AuthPlatformDep;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Permission\AuthPlatformValidate;

/**
 * 认证平台模块
 * 负责：平台列表、新增、编辑、删除、状态切换
 */
class AuthPlatformModule extends BaseModule
{
    // ==================== 公开接口 ====================

    /**
     * 初始化（获取字典数据）
     */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setCommonStatusArr()
            ->setAuthPlatformLoginTypeArr()
            ->getDict();

        return self::success(['dict' => $dict]);
    }

    /**
     * 平台列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::list());
        $paginator = $this->dep(AuthPlatformDep::class)->list($param);

        $list = $paginator->map(fn($it) => [
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
        ]);

        $page = [
            'page_size'    => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'total_page'   => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 新增平台
     */
    public function add($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::add());

        $dep = $this->dep(AuthPlatformDep::class);

        self::throwIf(
            $dep->existsByCode($param['code']),
            "平台标识 [{$param['code']}] 已存在"
        );

        $dep->addPlatform([
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

    /**
     * 编辑平台
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::edit());

        $dep = $this->dep(AuthPlatformDep::class);
        $row = $dep->get((int)$param['id']);
        self::throwNotFound($row);

        $affected = $dep->updateById((int)$param['id'], [
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

        self::throwIf($affected === 0, '更新失败');
        return self::success();
    }

    /**
     * 删除平台
     */
    public function del($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::del());
        $this->dep(AuthPlatformDep::class)->deleteByIds($param['id']);
        return self::success();
    }

    /**
     * 切换平台状态
     */
    public function status($request): array
    {
        $param = $this->validate($request, AuthPlatformValidate::status());
        $affected = $this->dep(AuthPlatformDep::class)->setStatusById((int)$param['id'], (int)$param['status']);
        self::throwIf($affected === 0, '平台不存在');
        return self::success();
    }
}
