<?php

namespace app\module\DevTools;

use app\dep\DevTools\TauriVersionDep;
use app\module\BaseModule;
use app\validate\DevTools\TauriVersionValidate;
use app\service\DictService;
use app\enum\CommonEnum;

/**
 * Tauri 版本管理模块
 */
class TauriVersionModule extends BaseModule
{
    private TauriVersionDep $dep;

    public function __construct()
    {
        $this->dep = new TauriVersionDep();
    }

    public function init($request): array
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setTauriPlatformArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 获取版本列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::list());
        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

        $res = $this->dep->list($param);

        $data['list'] = $res->map(fn($item) => [
            'id' => $item->id,
            'version' => $item->version,
            'notes' => $item->notes,
            'file_url' => $item->file_url,
            'signature' => $item->signature,
            'platform' => $item->platform,
            'file_size' => $item->file_size,
            'file_size_text' => $this->formatFileSize($item->file_size),
            'is_latest' => $item->is_latest,
            'force_update' => $item->force_update,
            'created_at' => $item->created_at->toDateTimeString(),
            'updated_at' => $item->updated_at->toDateTimeString(),
        ]);
        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];
        return self::paginate($data['list'], $data['page']);
    }

    /**
     * 添加版本
     */
    public function add($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::add());
        $param['force_update'] = $param['force_update'] ?? CommonEnum::NO;
        $param['is_latest'] = CommonEnum::NO;
        $id = $this->dep->add($param);
        return self::success(['id' => $id]);
    }

    /**
     * 编辑版本
     */
    public function edit($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::edit());
        $version = $this->dep->find($param['id']);
        self::throwIf(!$version, '版本不存在');
        
        $updateData = array_filter([
            'version' => $param['version'] ?? null,
            'notes' => $param['notes'] ?? null,
            'file_url' => $param['file_url'] ?? null,
            'signature' => $param['signature'] ?? null,
            'file_size' => $param['file_size'] ?? null,
            'force_update' => $param['force_update'] ?? null,
        ], fn($v) => $v !== null);
        
        $this->dep->update($param['id'], $updateData);
        return self::success();
    }

    /**
     * 设为最新版本（自动生成 update.json）
     */
    public function setLatest($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::setLatest());
        $version = $this->dep->find($param['id']);
        self::throwIf(!$version, '版本不存在');

        $this->withTransaction(function () use ($param, $version) {
            $this->dep->setLatest($param['id'], $version->platform);
            // 生成并上传 update.json 到 COS
            $this->uploadUpdateJson($version);
        });

        return self::success();
    }

    /**
     * 删除版本
     */
    public function del($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::del());
        $version = $this->dep->find($param['id']);
        self::throwIf(!$version, '版本不存在');
        self::throwIf($version->is_latest === CommonEnum::YES, '不能删除当前最新版本');

        $this->dep->hardDelete($param['id']);
        return self::success();
    }

    /**
     * 切换强制更新状态
     */
    public function forceUpdate($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::forceUpdate());
        $version = $this->dep->find($param['id']);
        self::throwIf(!$version, '版本不存在');
        
        $this->dep->update($param['id'], ['force_update' => $param['force_update']]);
        return self::success();
    }

    /**
     * 客户端初始化（公开接口）
     * 返回当前版本是否需要强制更新
     */
    public function clientInit($request): array
    {
        $param = $this->validate($request, TauriVersionValidate::clientInit());
        $version = $param['version'];
        $platform = $param['platform'] ?? 'windows-x86_64';
        
        $record = $this->dep->getByCondition([
            'version' => $version,
            'platform' => $platform
        ]);
        
        if (!$record) {
            return self::success(['force_update' => false]);
        }
        
        return self::success([
            'force_update' => $record->force_update === CommonEnum::YES,
        ]);
    }

    /**
     * 获取 update.json 内容（公开接口）
     */
    public function updateJson($request): array
    {
        $platform = $request->input('platform', 'windows-x86_64');
        $latest = $this->dep->getLatest($platform);
        
        if (!$latest) {
            return self::success([]);
        }

        $json = [
            'version' => $latest->version,
            'notes' => $latest->notes,
            'pub_date' => date('c', strtotime($latest->created_at)),
            'platforms' => [
                $latest->platform => [
                    'url' => $latest->file_url,
                    'signature' => $latest->signature,
                ]
            ]
        ];
        return self::success($json);
    }

    /**
     * 上传 update.json 到 COS
     * TODO: 待实现 COS 上传，目前通过 updateJson 接口手动获取
     */
    private function uploadUpdateJson($version): void
    {
        // 暂不自动上传，可通过 updateJson 接口获取 JSON 内容后手动上传
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(?int $size): string
    {
        if (!$size) return '-';
        if ($size < 1024) return $size . ' B';
        if ($size < 1024 * 1024) return round($size / 1024, 2) . ' KB';
        return round($size / (1024 * 1024), 2) . ' MB';
    }
}
