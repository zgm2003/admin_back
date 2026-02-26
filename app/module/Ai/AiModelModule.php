<?php

namespace app\module\Ai;

use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiModelValidate;

/**
 * AI 模型管理模块
 * 负责：模型 CRUD、状态切换、API Key 加密存储
 * 模型唯一性约束：同一驱动下不允许同名模型
 */
class AiModelModule extends BaseModule
{
    /**
     * 初始化（返回驱动、状态字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setAiDriverArr()
            ->setCommonStatusArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 模型列表（分页，含驱动名称、API Key 脱敏提示）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiModelValidate::list());
        $res = $this->dep(AiModelsDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'             => $item->id,
            'name'           => $item->name,
            'driver'         => $item->driver,
            'driver_name'    => AiEnum::$driverArr[$item->driver] ?? $item->driver,
            'model_code'     => $item->model_code,
            'endpoint'       => $item->endpoint,
            'api_key_hint'   => $item->api_key_hint,
            'modalities'     => $item->modalities,
            'status'         => $item->status,
            'status_name'    => CommonEnum::$statusArr[$item->status] ?? '',
            'created_at'     => $item->created_at,
            'updated_at'     => $item->updated_at,
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /**
     * 新增模型（校验同驱动下唯一性，API Key 加密存储）
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiModelValidate::add());
        $dep = $this->dep(AiModelsDep::class);

        // 同驱动下不允许同名模型
        self::throwIf($dep->existsByDriverAndName($param['driver'], $param['name']), '该驱动下已存在同名模型');

        $data = [
            'name'           => $param['name'],
            'driver'         => $param['driver'],
            'model_code'     => $param['model_code'],
            'endpoint'       => $param['endpoint'] ?? null,
            'modalities'     => !empty($param['modalities']) ? \json_encode($param['modalities']) : null,
            'status'         => $param['status'] ?? CommonEnum::YES,
            'is_del'         => CommonEnum::NO,
        ];

        // API Key 加密存储 + 脱敏提示
        if (!empty($param['api_key'])) {
            $data['api_key_enc']  = KeyVault::encrypt($param['api_key']);
            $data['api_key_hint'] = KeyVault::hint($param['api_key']);
        }

        $dep->add($data);

        return self::success();
    }

    /**
     * 编辑模型（校验记录存在 + 唯一性，API Key 留空不改）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiModelValidate::edit());
        $id = (int)$param['id'];
        $dep = $this->dep(AiModelsDep::class);

        $dep->getOrFail($id);

        // 唯一性校验（排除自身）
        self::throwIf($dep->existsByDriverAndName($param['driver'], $param['name'], $id), '该驱动下已存在同名模型');

        $data = [
            'name'       => $param['name'],
            'driver'     => $param['driver'],
            'model_code' => $param['model_code'],
            'endpoint'   => $param['endpoint'] ?? null,
            'status'     => (int)$param['status'],
        ];

        if (isset($param['modalities'])) {
            $data['modalities'] = \json_encode($param['modalities']);
        }

        // API Key 留空不改
        if (!empty($param['api_key'])) {
            $data['api_key_enc']  = KeyVault::encrypt($param['api_key']);
            $data['api_key_hint'] = KeyVault::hint($param['api_key']);
        }

        $dep->update($id, $data);

        return self::success();
    }

    /**
     * 删除模型（支持批量，软删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiModelValidate::del());
        $affected = $this->dep(AiModelsDep::class)->delete($param['id']);

        return self::success(['affected' => $affected]);
    }

    /**
     * 切换模型状态（支持批量）
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiModelValidate::setStatus());
        $affected = $this->dep(AiModelsDep::class)->setStatus($param['id'], (int)$param['status']);

        return self::success(['affected' => $affected]);
    }
}
