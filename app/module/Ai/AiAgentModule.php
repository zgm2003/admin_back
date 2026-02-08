<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiAgentValidate;
use RuntimeException;

class AiAgentModule extends BaseModule
{
    protected AiAgentsDep $dep;
    protected AiModelsDep $modelsDep;

    public function __construct()
    {
        $this->dep = new AiAgentsDep();
        $this->modelsDep = new AiModelsDep();
    }

    public function init($request): array
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setAiModeArr()
            ->setCommonStatusArr()
            ->getDict();

        // 获取可用的模型列表（用于下拉选择）
        $models = $this->modelsDep->getAllActive();
        $data['dict']['model_list'] = $models->map(function ($item) {
            return [
                'value' => $item->id,
                'label' => $item->name . ' (' . (AiEnum::$driverArr[$item->driver] ?? $item->driver) . ')',
            ];
        })->toArray();

        return self::success($data);
    }

    public function list($request): array
    {
        $param = $this->validate($request, AiAgentValidate::list());
        $res = $this->dep->list($param);

        // 批量获取关联的模型信息（避免N+1）
        $modelIds = $res->pluck('model_id')->unique()->toArray();
        $modelMap = $this->modelsDep->getMap($modelIds);

        $list = $res->map(function ($item) use ($modelMap) {
            $model = $modelMap->get($item->model_id);
            $modelDeleted = $model && $model->is_del == CommonEnum::YES;
            return [
                'id' => $item->id,
                'name' => $item->name,
                'model_id' => $item->model_id,
                'model_name' => $model?->name ?? '',
                'model_deleted' => $modelDeleted,
                'driver' => $model?->driver ?? '',
                'driver_name' => $model ? (AiEnum::$driverArr[$model->driver] ?? $model->driver) : '',
                'model_code' => $model?->model_code ?? '',
                'modalities' => $model?->modalities ?? null,
                'avatar' => $item->avatar,
                'system_prompt' => $item->system_prompt,
                'mode' => $item->mode,
                'mode_name' => AiEnum::$modeArr[$item->mode] ?? $item->mode,
                'temperature' => $item->temperature,
                'max_tokens' => $item->max_tokens,
                'extra_params' => $item->extra_params,
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

    public function add($request): array
    {
        $param = $this->validate($request, AiAgentValidate::add());

        // 校验 model_id 是否存在且有效
        $model = $this->modelsDep->get((int)$param['model_id']);
        self::throwNotFound($model, '关联的模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');

        // extra_params
        $extraParams = null;
        if (!empty($param['extra_params'])) {
            $extraParams = $param['extra_params'];
        }

        $data = [
            'name' => $param['name'],
            'model_id' => (int)$param['model_id'],
            'avatar' => $param['avatar'] ?? null,
            'system_prompt' => $param['system_prompt'] ?? null,
            'mode' => $param['mode'] ?? 'chat',
            'temperature' => $param['temperature'] ?? 1.00,
            'max_tokens' => $param['max_tokens'] ?? null,
            'extra_params' => $extraParams ? json_encode($extraParams) : null,
            'status' => $param['status'] ?? CommonEnum::YES,
            'is_del' => CommonEnum::NO,
        ];

        $this->dep->add($data);
        return self::success();
    }

    public function edit($request): array
    {
        $param = $this->validate($request, AiAgentValidate::edit());

        $id = (int)$param['id'];
        $row = $this->dep->get($id);
        self::throwNotFound($row, '记录不存在');

        // 校验 model_id
        $model = $this->modelsDep->get((int)$param['model_id']);
        self::throwNotFound($model, '关联的模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');

        // 构建更新数据
        $data = [
            'name' => $param['name'],
            'model_id' => (int)$param['model_id'],
            'avatar' => $param['avatar'] ?? null,
            'system_prompt' => $param['system_prompt'] ?? null,
            'mode' => $param['mode'],
            'temperature' => $param['temperature'],
            'max_tokens' => $param['max_tokens'] ?? null,
            'status' => (int)$param['status'],
        ];

        // extra_params
        if (!empty($param['extra_params'])) {
            $data['extra_params'] = json_encode($param['extra_params']);
        }

        $this->dep->update($id, $data);
        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, AiAgentValidate::del());

        $ids = $param['id'];
        $affected = $this->dep->delete($ids);

        return self::success(['affected' => $affected]);
    }

    public function status($request): array
    {
        $param = $this->validate($request, AiAgentValidate::status());

        $ids = $param['id'];
        $status = (int)$param['status'];
        $affected = $this->dep->setStatus($ids, $status);

        return self::success(['affected' => $affected]);
    }
}
