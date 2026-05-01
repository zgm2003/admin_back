<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentScenesDep;
use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\AiAgentKnowledgeBasesDep;
use app\dep\Ai\AiAssistantToolsDep;
use app\dep\Ai\AiKnowledgeBasesDep;
use app\dep\Ai\AiModelsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Ai\AiAgentsValidate;

/**
 * AI 智能体管理模块
 * 负责：智能体 CRUD、状态切换
 * 智能体绑定模型，新增/编辑时校验模型存在且启用
 */
class AiAgentsModule extends BaseModule
{
    /**
     * 初始化（返回模式、能力、场景、状态字典 + 可用模型列表）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setAiModeArr()
            ->setAiCapabilityArr()
            ->setAiSceneArr()
            ->setCommonStatusArr()
            ->getDict();

        // 可用模型列表（供下拉选择，label 带驱动名称）
        $models = $this->dep(AiModelsDep::class)->getAllActive();
        $data['dict']['model_list'] = $models->map(fn($item) => [
            'value' => $item->id,
            'label' => "{$item->name} (" . (AiEnum::$driverArr[$item->driver] ?? $item->driver) . ')',
        ])->toArray();

        $knowledgeBases = $this->dep(AiKnowledgeBasesDep::class)->getAllActiveOptions();
        $data['dict']['knowledge_base_list'] = $knowledgeBases->map(fn($item) => [
            'value' => $item->id,
            'label' => $item->name,
        ])->toArray();

        return self::success($data);
    }

    /**
     * 智能体列表（分页，批量预加载模型信息避免 N+1）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiAgentsValidate::list());
        $res = $this->dep(AiAgentsDep::class)->list($param);

        // 批量预加载关联模型（只取列表需要的字段，避免拉取 api_key_enc 等大字段）
        $modelIds = $res->pluck('model_id')->unique()->toArray();
        $modelMap = $this->dep(AiModelsDep::class)->getMap($modelIds, ['id', 'name', 'driver', 'model_code', 'is_del']);
        $agentIds = $res->pluck('id')->unique()->toArray();
        $sceneCodeMap = $this->dep(AiAgentScenesDep::class)->getSceneCodesByAgentIds($agentIds);
        $knowledgeBaseIdMap = $this->dep(AiAgentKnowledgeBasesDep::class)->getKnowledgeBaseIdsByAgentIds($agentIds);
        $allKnowledgeBaseIds = array_values(array_unique(array_merge(...array_values($knowledgeBaseIdMap ?: [[]]))));
        $knowledgeBaseMap = $this->dep(AiKnowledgeBasesDep::class)->getMapActive($allKnowledgeBaseIds, ['id', 'name']);

        $list = $res->map(function ($item) use ($modelMap, $sceneCodeMap, $knowledgeBaseIdMap, $knowledgeBaseMap) {
            $model = $modelMap->get($item->model_id);
            $modelDeleted = $model && $model->is_del == CommonEnum::YES;
            $sceneCodes = $sceneCodeMap[(int)$item->id] ?? $this->normalizeSceneCodes([], $item->scene);
            $sceneNames = array_map(
                static fn(string $scene) => AiEnum::$sceneArr[$scene] ?? $scene,
                $sceneCodes
            );
            $capabilities = $this->normalizeCapabilities($item->capabilities_json ?? null, $item->mode ?? AiEnum::MODE_CHAT);
            $knowledgeBaseIds = array_values(array_map('intval', $knowledgeBaseIdMap[(int)$item->id] ?? []));
            $knowledgeBaseNames = array_values(array_filter(array_map(
                static fn(int $id) => $knowledgeBaseMap->get($id)?->name,
                $knowledgeBaseIds
            )));

            return [
                'id'             => $item->id,
                'name'           => $item->name,
                'model_id'       => $item->model_id,
                'model_name'     => $model?->name ?? '',
                'model_deleted'  => $modelDeleted,
                'driver'         => $model?->driver ?? '',
                'driver_name'    => $model ? (AiEnum::$driverArr[$model->driver] ?? $model->driver) : '',
                'model_code'     => $model?->model_code ?? '',
                'avatar'         => $item->avatar,
                'system_prompt'  => $item->system_prompt,
                'mode'           => $item->mode,
                'mode_name'      => AiEnum::$modeArr[$item->mode] ?? $item->mode,
                'scene'          => $item->scene ?: ($sceneCodes[0] ?? null),
                'scene_name'     => $sceneNames[0] ?? '',
                'scene_codes'    => $sceneCodes,
                'scene_names'    => $sceneNames,
                'capabilities'   => $capabilities,
                'knowledge_base_ids' => $knowledgeBaseIds,
                'knowledge_base_names' => $knowledgeBaseNames,
                'runtime_config' => $this->normalizeArrayConfig($item->runtime_config_json ?? null),
                'policy'         => $this->normalizeArrayConfig($item->policy_json ?? null),
                'status'         => $item->status,
                'status_name'    => CommonEnum::$statusArr[$item->status] ?? '',
                'created_at'     => $item->created_at,
                'updated_at'     => $item->updated_at,
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
        $param = $this->validate($request, AiAgentsValidate::add());

        // 校验关联模型
        $model = $this->dep(AiModelsDep::class)->get((int)$param['model_id']);
        self::throwNotFound($model, '关联的模型不存在');
        self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');

        $capabilities = $this->normalizeCapabilities($param['capabilities'] ?? null, $param['mode'] ?? AiEnum::MODE_CHAT);
        $sceneCodes = $this->normalizeSceneCodes($param['scene_codes'] ?? [], $param['scene'] ?? null);
        $legacyScene = $sceneCodes[0] ?? ($param['scene'] ?? null);

        $agentId = $this->withTransaction(function () use ($param, $capabilities, $sceneCodes, $legacyScene): int {
            $agentId = $this->dep(AiAgentsDep::class)->add([
                'name'                => $param['name'],
                'model_id'            => (int)$param['model_id'],
                'avatar'              => $param['avatar'] ?? null,
                'system_prompt'       => $param['system_prompt'] ?? null,
                'mode'                => $param['mode'] ?? AiEnum::MODE_CHAT,
                'scene'               => $legacyScene,
                'capabilities_json'   => $this->encodeJson($capabilities),
                'runtime_config_json' => $this->encodeNullableJson($param['runtime_config'] ?? null),
                'policy_json'         => $this->encodeNullableJson($param['policy'] ?? null),
                'status'              => $param['status'] ?? CommonEnum::YES,
                'is_del'              => CommonEnum::NO,
            ]);

            $this->dep(AiAgentScenesDep::class)->syncScenes($agentId, $sceneCodes);

            if (isset($param['tool_ids'])) {
                $this->dep(AiAssistantToolsDep::class)->syncBindings($agentId, array_map('intval', $param['tool_ids'] ?? []));
            }
            if ($this->capabilityEnabled($capabilities, AiEnum::CAPABILITY_RAG)) {
                $this->dep(AiAgentKnowledgeBasesDep::class)->syncBindings($agentId, $param['knowledge_base_ids'] ?? []);
            }

            return $agentId;
        });

        return self::success(['id' => $agentId]);
    }

    /**
     * 编辑智能体（校验记录存在 + 关联模型存在且启用）
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiAgentsValidate::edit());
        $id = (int)$param['id'];
        $dep = $this->dep(AiAgentsDep::class);

        $row = $dep->get($id);
        self::throwNotFound($row, '记录不存在');

        // 校验关联模型（仅当提供了 model_id 时校验）
        if (!empty($param['model_id'])) {
            $model = $this->dep(AiModelsDep::class)->get((int)$param['model_id']);
            self::throwNotFound($model, '关联的模型不存在');
            self::throwIf($model->status !== CommonEnum::YES, '关联的模型已禁用');
        }

        // 仅更新提供了的字段
        $fields = ['name', 'model_id', 'avatar', 'system_prompt', 'mode', 'scene', 'status'];
        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $param)) {
                $data[$field] = match ($field) {
                    'model_id', 'status' => (int)$param[$field],
                    default => $param[$field],
                };
            }
        }

        if (array_key_exists('capabilities', $param)) {
            $data['capabilities_json'] = $this->normalizeCapabilities($param['capabilities'], $param['mode'] ?? $row->mode ?? AiEnum::MODE_CHAT);
        }
        if (array_key_exists('runtime_config', $param)) {
            $data['runtime_config_json'] = $this->encodeNullableJson($param['runtime_config']);
        }
        if (array_key_exists('policy', $param)) {
            $data['policy_json'] = $this->encodeNullableJson($param['policy']);
        }
        if (array_key_exists('scene_codes', $param)) {
            $sceneCodes = $this->normalizeSceneCodes($param['scene_codes'], $param['scene'] ?? null);
            $data['scene'] = $sceneCodes[0] ?? null;
        } else {
            $sceneCodes = null;
        }

        $effectiveCapabilities = $data['capabilities_json'] ?? $this->normalizeCapabilities($row->capabilities_json ?? null, $row->mode ?? AiEnum::MODE_CHAT);
        if (isset($data['capabilities_json'])) {
            $data['capabilities_json'] = $this->encodeJson($effectiveCapabilities);
        }

        $this->withTransaction(function () use ($dep, $id, $data, $param, $sceneCodes, $effectiveCapabilities): void {
            $dep->update($id, $data);

            if (is_array($sceneCodes)) {
                $this->dep(AiAgentScenesDep::class)->syncScenes($id, $sceneCodes);
            }

            if (isset($param['tool_ids'])) {
                $this->dep(AiAssistantToolsDep::class)->syncBindings($id, array_map('intval', $param['tool_ids'] ?? []));
            }

            if (array_key_exists('knowledge_base_ids', $param) || !$this->capabilityEnabled($effectiveCapabilities, AiEnum::CAPABILITY_RAG)) {
                $knowledgeBaseIds = $this->capabilityEnabled($effectiveCapabilities, AiEnum::CAPABILITY_RAG)
                    ? ($param['knowledge_base_ids'] ?? [])
                    : [];
                $this->dep(AiAgentKnowledgeBasesDep::class)->syncBindings($id, $knowledgeBaseIds);
            }
        });

        return self::success();
    }

    /**
     * 删除智能体（支持批量，软删除）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiAgentsValidate::del());
        $affected = $this->dep(AiAgentsDep::class)->delete($param['id']);

        return self::success(['affected' => $affected]);
    }

    /**
     * 切换智能体状态（支持批量）
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiAgentsValidate::status());
        $affected = $this->dep(AiAgentsDep::class)->setStatus($param['id'], (int)$param['status']);

        return self::success(['affected' => $affected]);
    }

    private function normalizeCapabilities(mixed $capabilities, string $mode): array
    {
        $defaults = [
            AiEnum::CAPABILITY_CHAT     => true,
            AiEnum::CAPABILITY_TOOLS    => $mode === AiEnum::MODE_TOOL,
            AiEnum::CAPABILITY_RAG      => $mode === AiEnum::MODE_RAG,
            AiEnum::CAPABILITY_WORKFLOW => $mode === AiEnum::MODE_WORKFLOW,
        ];

        $capabilities = $this->normalizeArrayConfig($capabilities);
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $capabilities)) {
                $defaults[$key] = (bool)$capabilities[$key];
            }
        }
        $defaults[AiEnum::CAPABILITY_CHAT] = true;

        return $defaults;
    }

    private function normalizeSceneCodes(array $sceneCodes, ?string $legacyScene): array
    {
        if ($legacyScene !== null && $legacyScene !== '') {
            $sceneCodes[] = $legacyScene;
        }

        $allowed = array_keys(AiEnum::$sceneArr);
        return array_values(array_unique(array_filter(
            array_map('strval', $sceneCodes),
            static fn(string $scene) => $scene !== '' && in_array($scene, $allowed, true)
        )));
    }

    private function capabilityEnabled(array $capabilities, string $key): bool
    {
        return (bool)($capabilities[$key] ?? false);
    }

    private function normalizeArrayConfig(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function encodeNullableJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->encodeJson($this->normalizeArrayConfig($value));
    }
}
