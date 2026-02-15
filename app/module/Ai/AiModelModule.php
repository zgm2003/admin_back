<?php

namespace app\module\Ai;

use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiModelValidate;
use RuntimeException;

class AiModelModule extends BaseModule
{
    protected AiModelsDep $dep;

    public function __construct()
    {
        $this->dep = new AiModelsDep();
    }

    public function init($request): array
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAiDriverArr()
            ->setCommonStatusArr()
            ->getDict();
        return self::success($data);
    }

    /**
     * 列表
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiModelValidate::list());
        $res = $this->dep->list($param);

        $list = $res->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'driver' => $item->driver,
                'driver_name' => AiEnum::$driverArr[$item->driver] ?? $item->driver,
                'model_code' => $item->model_code,
                'endpoint' => $item->endpoint,
                'api_key_hint' => $item->api_key_hint,
                'default_params' => $item->default_params,
                'modalities' => $item->modalities,
                'status' => $item->status,
                'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
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

    /**
     * 创建
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiModelValidate::add());

        // 检查唯一性
        self::throwIf($this->dep->existsByDriverAndName($param['driver'], $param['name']), '该驱动下已存在同名模型');

        // 处理 default_params
        $defaultParams = null;
        if (!empty($param['default_params'])) {
            $defaultParams = $param['default_params'];
        }

        // 处理 modalities
        $modalities = null;
        if (!empty($param['modalities'])) {
            $modalities = $param['modalities'];
        }

        // 构建数据
        $data = [
            'name' => $param['name'],
            'driver' => $param['driver'],
            'model_code' => $param['model_code'],
            'endpoint' => $param['endpoint'] ?? null,
            'default_params' => $defaultParams ? json_encode($defaultParams) : null,
            'modalities' => $modalities ? json_encode($modalities) : null,
            'status' => $param['status'] ?? CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];

        // 处理 API Key 加密
        if (!empty($param['api_key'])) {
            $data['api_key_enc'] = KeyVault::encrypt($param['api_key']);
            $data['api_key_hint'] = KeyVault::hint($param['api_key']);
        }

        $this->dep->add($data);

        return self::success();
    }

    /**
     * 更新
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiModelValidate::edit());

        $id = (int)$param['id'];
        $row = $this->dep->getOrFail($id);

        // 唯一性校验
        self::throwIf($this->dep->existsByDriverAndName($param['driver'], $param['name'], $id), '该驱动下已存在同名模型');

        // 构建更新数据
        $data = [
            'name' => $param['name'],
            'driver' => $param['driver'],
            'model_code' => $param['model_code'],
            'endpoint' => $param['endpoint'] ?? null,
            'status' => (int)$param['status'],
        ];

        // default_params
        if (!empty($param['default_params'])) {
            $data['default_params'] = json_encode($param['default_params']);
        }

        // modalities
        if (isset($param['modalities'])) {
            $data['modalities'] = json_encode($param['modalities']);
        }

        // API Key（留空不改）
        if (!empty($param['api_key'])) {
            $data['api_key_enc'] = KeyVault::encrypt($param['api_key']);
            $data['api_key_hint'] = KeyVault::hint($param['api_key']);
        }

        $this->dep->update($id, $data);
        return self::success();
    }

    /**
     * 删除（软删）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiModelValidate::del());

        $ids = $param['id'];
        $affected = $this->dep->delete($ids);

        return self::success(['affected' => $affected]);
    }

    /**
     * 设置状态
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiModelValidate::setStatus());

        $ids = $param['id'];
        $status = (int)$param['status'];
        $affected = $this->dep->setStatus($ids, $status);

        return self::success(['affected' => $affected]);
    }
}
