<?php

namespace app\module\Ai;

use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\lib\Ai\Crypto\KeyVault;
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
        try {
            $param = $this->validate($request, AiModelValidate::list());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $param['page_size'] = $param['page_size'] ?? 20;
        $param['current_page'] = $param['current_page'] ?? 1;

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
                'status' => $item->status,
                'status_name' => CommonEnum::$statusArr[$item->status] ?? '',
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
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

    /**
     * 创建
     */
    public function add($request): array
    {
        try {
            $param = $this->validate($request, AiModelValidate::add());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        // 检查唯一性
        if ($this->dep->existsByDriverAndName($param['driver'], $param['name'])) {
            return self::error('该驱动下已存在同名模型');
        }

        // 处理 default_params
        $defaultParams = null;
        if (isset($param['default_params'])) {
            $defaultParams = is_string($param['default_params'])
                ? json_decode($param['default_params'], true)
                : $param['default_params'];
        }

        // 构建数据
        $data = [
            'name' => $param['name'],
            'driver' => $param['driver'],
            'model_code' => $param['model_code'],
            'endpoint' => $param['endpoint'] ?? null,
            'default_params' => $defaultParams ? json_encode($defaultParams) : null,
            'status' => $param['status'] ?? CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];

        // 处理 API Key 加密
        if (!empty($param['api_key'])) {
            try {
                $data['api_key_enc'] = KeyVault::encrypt($param['api_key']);
                $data['api_key_hint'] = KeyVault::hint($param['api_key']);
            } catch (RuntimeException $e) {
                return self::error($e->getMessage());
            }
        }

        $id = $this->dep->create($data);

        return self::success();
    }

    /**
     * 更新
     */
    public function edit($request): array
    {
        try {
            $param = $this->validate($request, AiModelValidate::edit());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $id = (int)$param['id'];
        $row = $this->dep->getById($id);
        if (!$row) {
            return self::error('记录不存在');
        }

        // 如果修改了 driver 或 name，检查唯一性
        $newDriver = $param['driver'] ?? $row->driver;
        $newName = $param['name'] ?? $row->name;
        if (($newDriver !== $row->driver || $newName !== $row->name) &&
            $this->dep->existsByDriverAndName($newDriver, $newName, $id)) {
            return self::error('该驱动下已存在同名模型');
        }

        // 构建更新数据
        $data = [];

        if (isset($param['name'])) {
            $data['name'] = $param['name'];
        }
        if (isset($param['driver'])) {
            $data['driver'] = $param['driver'];
        }
        if (isset($param['model_code'])) {
            $data['model_code'] = $param['model_code'];
        }
        if (array_key_exists('endpoint', $param)) {
            $data['endpoint'] = $param['endpoint'];
        }
        if (isset($param['status'])) {
            $data['status'] = (int)$param['status'];
        }

        // 处理 default_params
        if (isset($param['default_params'])) {
            $defaultParams = is_string($param['default_params'])
                ? json_decode($param['default_params'], true)
                : $param['default_params'];
            $data['default_params'] = $defaultParams ? json_encode($defaultParams) : null;
        }

        // 处理 API Key（只有传了才更新）
        if (!empty($param['api_key'])) {
            try {
                $data['api_key_enc'] = KeyVault::encrypt($param['api_key']);
                $data['api_key_hint'] = KeyVault::hint($param['api_key']);
            } catch (RuntimeException $e) {
                return self::error($e->getMessage());
            }
        }

        if (empty($data)) {
            return self::success();
        }

        $ok = $this->dep->updateById($id, $data);
        if (!$ok) {
            return self::error('更新失败');
        }

        return self::success();
    }

    /**
     * 删除（软删）
     */
    public function del($request): array
    {
        try {
            $param = $this->validate($request, AiModelValidate::del());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $ids = $param['id'];
        $affected = $this->dep->softDelete($ids);

        return self::success(['affected' => $affected]);
    }

    /**
     * 设置状态
     */
    public function status($request): array
    {
        try {
            $param = $this->validate($request, AiModelValidate::setStatus());
        } catch (RuntimeException $e) {
            return self::error($e->getMessage());
        }

        $ids = $param['id'];
        $status = (int)$param['status'];
        $affected = $this->dep->setStatus($ids, $status);

        return self::success(['affected' => $affected]);
    }
}
