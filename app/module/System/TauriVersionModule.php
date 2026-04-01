<?php

namespace app\module\System;

use app\dep\System\TauriVersionDep;
use app\enum\CommonEnum;
use app\enum\UploadConfigEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\System\TauriUpdaterService;
use app\validate\System\TauriVersionValidate;

/**
 * Tauri 桌面客户端版本管理模块
 * 负责：版本 CRUD、设为最新版、强制更新切换、客户端初始化检查、update.json 生成
 * 版本按 platform 隔离（windows-x86_64 等），同平台同版本号唯一
 */
class TauriVersionModule extends BaseModule
{
    /**
     * 初始化（返回 Tauri 平台字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setTauriPlatformArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 版本列表（分页，附带文件大小可读文本）
     */
    public function list($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::list());
        $res = $this->dep(TauriVersionDep::class)->list($param);

        $data['list'] = $res->map(fn($item) => [
            'id'             => $item->id,
            'version'        => $item->version,
            'notes'          => $item->notes,
            'file_url'       => $item->file_url,
            'signature'      => $item->signature,
            'platform'       => $item->platform,
            'file_size'      => $item->file_size,
            'file_size_text' => self::formatFileSize($item->file_size),
            'is_latest'      => $item->is_latest,
            'force_update'   => $item->force_update,
            'created_at'     => $item->created_at,
            'updated_at'     => $item->updated_at,
        ]);

        $data['page'] = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($data['list'], $data['page']);
    }


    /**
     * 添加版本（同平台同版本号不可重复）
     */
    public function add($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::add());
        $dep = $this->dep(TauriVersionDep::class);

        self::throwIf($dep->existsByVersionPlatform($param['version'], $param['platform']), '该版本已存在');

        $param['force_update'] = $param['force_update'] ?? CommonEnum::NO;
        $param['is_latest'] = CommonEnum::NO;

        $id = $dep->add($param);

        return self::success(['id' => $id]);
    }

    /**
     * 编辑版本（修改版本号时校验唯一性，仅允许更新白名单字段）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::edit());
        $dep = $this->dep(TauriVersionDep::class);

        $version = $dep->getOrFail($param['id']);

        // 如果修改了版本号，检查是否与其他记录冲突
        $newVersion = $param['version'] ?? $version->version;
        if ($newVersion !== $version->version) {
            self::throwIf($dep->existsByVersionPlatform($newVersion, $version->platform, $param['id']), '该版本已存在');
        }

        // 只提取允许更新的字段
        $allowFields = ['version', 'notes', 'file_url', 'signature', 'file_size', 'force_update'];
        $updateData = array_intersect_key($param, array_flip($allowFields));
        if ($updateData === []) {
            return self::success();
        }

        if ((int) $version->is_latest === CommonEnum::YES) {
            $this->withTransaction(function () use ($dep, $param, $updateData, $version) {
                $dep->update($param['id'], $updateData);
                $this->uploadUpdateJson((string) $version->platform);
            });

            return self::success();
        }

        $dep->update($param['id'], $updateData);

        return self::success();
    }

    /**
     * 设为最新版本（事务内先清除同平台旧 latest 再设置新的，并发布平台清单）
     */
    public function setLatest($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::setLatest());
        $dep = $this->dep(TauriVersionDep::class);

        $version = $dep->getOrFail($param['id']);

        $this->withTransaction(function () use ($dep, $param, $version) {
            $dep->setLatest($param['id'], $version->platform);
            $this->uploadUpdateJson((string) $version->platform);
        });

        return self::success();
    }

    /**
     * 删除版本（不允许删除当前最新版本，硬删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::del());
        $dep = $this->dep(TauriVersionDep::class);

        $version = $dep->getOrFail($param['id']);
        self::throwIf($version->is_latest === CommonEnum::YES, '不能删除当前最新版本');

        $dep->delete($param['id']);

        return self::success();
    }

    /**
     * 切换强制更新状态（通过影响行数判断版本是否存在）
     */
    public function forceUpdate($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::forceUpdate());
        $dep = $this->dep(TauriVersionDep::class);
        $dep->getOrFail($param['id']);
        $dep->update($param['id'], ['force_update' => $param['force_update']]);

        return self::success();
    }

    /**
     * 客户端初始化（公开接口，返回当前版本是否需要强制更新）
     */
    public function clientInit($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::clientInit());
        $platform = $param['platform'] ?? UploadConfigEnum::PLATFORM_WINDOWS;

        $record = $this->dep(TauriVersionDep::class)->getByCondition([
            'version'  => $param['version'],
            'platform' => $platform,
        ]);

        if (!$record) {
            return self::success(['force_update' => false]);
        }

        return self::success([
            'force_update' => $record->force_update === CommonEnum::YES,
        ]);
    }

    /**
     * 获取 update.json 内容（公开接口，供 Tauri updater 调用）
     * 返回符合 Tauri updater 规范的 JSON 结构
     */
    public function updateJson($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::updateJson());
        $platform = $param['platform'] ?? UploadConfigEnum::PLATFORM_WINDOWS;

        return self::success($this->svc(TauriUpdaterService::class)->buildManifestPayload($platform));
    }

    // ==================== 私有方法 ====================

    /**
     * 上传指定平台的 update.json 到对象存储静态地址
     */
    private function uploadUpdateJson(string $platform): void
    {
        $this->svc(TauriUpdaterService::class)->uploadManifest($platform);
    }

    /**
     * 文件大小格式化（字节 → B/KB/MB 可读文本）
     */
    private static function formatFileSize(?int $size): string
    {
        if (!$size) {
            return '-';
        }
        if ($size < 1024) {
            return "{$size} B";
        }
        if ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        }

        return round($size / 1048576, 2) . ' MB';
    }
}
