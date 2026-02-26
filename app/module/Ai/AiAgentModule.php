<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiAgentValidate;

/**
 * AI 智能体管理模块
 * 负责：智能体 CRUD、状态切换
 * 智能体绑定模型，新增/编辑时校验模型存在且启用
 */
class AiAgentModule extends BaseModule
{
    /**
     * 初始化（返回模式、场景、状态字典 + 可用模型列表）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setAiModeArr()
            ->setAiSceneArr()
            ->setCommonStatusArr()
            ->getDict();

        // 可用模型列表（供下拉选择，label 带驱动名称）
        $models = $this->dep(AiModelsDep::class)->getAllActive();
        $data['dict']['model_list'] = $models->map(fn($item) => [
            'value' => $item->id,
            'label' => "{$item->name} (" . (AiEnum::$driverArr[$item->driver] ?? $item->driver) . ')',
        ])->toArray();

        return self::success($data);
    }

    /**
     * 智能体列表（分页，批量预加载模型信息避免 N+1）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiAgentValidate::list());
        $res = $this->dep(AiAgentsDep::class)->list($param);

        // 批量预加载关联模型
        $modelIds = $res->pluck('model_id')->unique()->toArray();
        $modelMap = $this->dep(AiModelsDep::class)->getMap($modelIds);

        $list = $res->map(function ($item) use ($modelMap) {
            $model = $modelMap->get($item->model_id);
            $modelDeleted = $model && $model->is_del == CommonEnum::YES;

            return [
                'id'            => $item->id,
                'name'          => $item->name,
                'model_id'      => $item->model_id,
                'model_name'    => $model?->name ?? '',
                'model_deleted' => $modelDeleted,
                'driver'        => $model?->driver ?? '',
                'driver_name'   => $model ? (AiEnum::$driverArr[$model->driver] ?? $model->driver) : '',
                'model_code'    => $model?->model_code ?? '',
                'modalities'    => $model?->modalities ?? null,
                'avatar'        => $item->avatar,
                'system_prompt' => $item->system_prompt,
                'mode'          => $item->mode,
                'mode_name'     => AiEnum::$modeArr[$item->mode] ?? $item->mode,
                'scene'         => $item->scene ?? 'chat',
                'scene_name'    => AiEnum::$sceneArr[$item->scene ?? 'chat'] ?? $item->scene,
                'status'        => $item->status,
                'status_name'   => CommonEnum::$statusArr[$item->status] ?? '',
                'created_at'    => $item->created_at,
                'updated_at'    => $item->updated_at,
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

    /**
     * 新增智能体（校验关联模型存在且启用）
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiAgentValidate::add());

        // 校验关联模型
        $model = $this->dep(AiModelsDep::class)->get((int)$param['model_id']);
        self::throwNotFound($model, '关联的模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');

        $this->dep(AiAgentsDep::class)->add([
            'name'          => $param['name'],
            'model_id'      => (int)$param['model_id'],
            'avatar'        => $param['avatar'] ?? null,
            'system_prompt' => $param['system_prompt'] ?? null,
            'mode'          => $param['mode'] ?? 'chat',
            'scene'         => $param['scene'] ?? 'chat',
            'status'        => $param['status'] ?? CommonEnum::YES,
            'is_del'        => CommonEnum::NO,
        ]);

        return self::success();
    }

    /**
     * 编辑智能体（校验记录存在 + 关联模型存在且启用）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiAgentValidate::edit());
        $id = (int)$param['id'];
        $dep = $this->dep(AiAgentsDep::class);

        $row = $dep->get($id);
        self::throwNotFound($row, '记录不存在');

        // 校验关联模型
        $model = $this->dep(AiModelsDep::class)->get((int)$param['model_id']);
        self::throwNotFound($model, '关联的模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');

        $data = [
            'name'          => $param['name'],
            'model_id'      => (int)$param['model_id'],
            'avatar'        => $param['avatar'] ?? null,
            'system_prompt' => $param['system_prompt'] ?? null,
            'mode'          => $param['mode'],
            'scene'         => $param['scene'] ?? $row->scene ?? 'chat',
            'status'        => (int)$param['status'],
        ];

        $dep->update($id, $data);

        return self::success();
    }

    /**
     * 删除智能体（支持批量，软删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiAgentValidate::del());
        $affected = $this->dep(AiAgentsDep::class)->delete($param['id']);

        return self::success(['affected' => $affected]);
    }

    /**
     * 切换智能体状态（支持批量）
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiAgentValidate::status());
        $affected = $this->dep(AiAgentsDep::class)->setStatus($param['id'], (int)$param['status']);

        return self::success(['affected' => $affected]);
    }
}