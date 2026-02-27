<?php

namespace app\module\Ai;

use app\dep\Ai\AiAssistantToolsDep;
use app\dep\Ai\AiToolsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use app\validate\Ai\AiToolsValidate;

/**
 * AI 工具管理模块
 * 负责：工具 CRUD、状态切换、智能体工具绑定
 */
class AiToolsModule extends BaseModule
{
    /**
     * 初始化（返回执行器类型 + 状态字典）
     */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setAiExecutorTypeArr()
            ->setCommonStatusArr()
            ->getDict();

        return self::success($data);
    }

    /**
     * 工具列表（分页）
     */
    public function list($request): array
    {
        $param = $this->validate($request, AiToolsValidate::list());
        $param['page_size']    ??= 15;
        $param['current_page'] ??= 1;

        $res = $this->dep(AiToolsDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'              => $item->id,
            'name'            => $item->name,
            'code'            => $item->code,
            'description'     => $item->description,
            'schema_json'     => $item->schema_json,
            'executor_type'   => $item->executor_type,
            'executor_name'   => AiEnum::$executorTypeArr[$item->executor_type] ?? '',
            'executor_config' => $item->executor_config,
            'status'          => $item->status,
            'status_name'     => CommonEnum::$statusArr[$item->status] ?? '',
            'created_at'      => $item->created_at,
            'updated_at'      => $item->updated_at,
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
     * 新增工具
     */
    public function add($request): array
    {
        $param = $this->validate($request, AiToolsValidate::add());

        // code 唯一性校验
        self::throwIf(
            $this->dep(AiToolsDep::class)->existsByCode($param['code']),
            '工具编码已存在'
        );

        // executor_config 校验
        $this->validateExecutorConfig((int)$param['executor_type'], $param['executor_config'] ?? []);

        $this->dep(AiToolsDep::class)->add([
            'name'            => $param['name'],
            'code'            => $param['code'],
            'description'     => $param['description'] ?? null,
            'schema_json'     => isset($param['schema_json']) ? json_encode($param['schema_json'], JSON_UNESCAPED_UNICODE) : null,
            'executor_type'   => (int)$param['executor_type'],
            'executor_config' => isset($param['executor_config']) ? json_encode($param['executor_config'], JSON_UNESCAPED_UNICODE) : null,
            'status'          => $param['status'] ?? CommonEnum::YES,
            'is_del'          => CommonEnum::NO,
        ]);

        return self::success();
    }

    /**
     * 编辑工具
     */
    public function edit($request): array
    {
        $param = $this->validate($request, AiToolsValidate::edit());
        $id = (int)$param['id'];
        $dep = $this->dep(AiToolsDep::class);

        $row = $dep->get($id);
        self::throwNotFound($row, '记录不存在');

        // code 唯一性校验（排除自身）
        if (!empty($param['code'])) {
            self::throwIf($dep->existsByCode($param['code'], $id), '工具编码已存在');
        }

        $executorType = (int)($param['executor_type'] ?? $row->executor_type);
        if (isset($param['executor_config'])) {
            $this->validateExecutorConfig($executorType, $param['executor_config']);
        }

        $data = [];
        foreach (['name', 'code', 'description', 'executor_type', 'status'] as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        if (isset($param['schema_json'])) {
            $data['schema_json'] = json_encode($param['schema_json'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($param['executor_config'])) {
            $data['executor_config'] = json_encode($param['executor_config'], JSON_UNESCAPED_UNICODE);
        }

        $dep->update($id, $data);

        return self::success();
    }

    /**
     * 删除工具（软删除，污染 code）
     */
    public function del($request): array
    {
        $param = $this->validate($request, AiToolsValidate::del());
        $affected = $this->dep(AiToolsDep::class)->softDelete($param['id']);

        return self::success(['affected' => $affected]);
    }

    /**
     * 状态切换
     */
    public function status($request): array
    {
        $param = $this->validate($request, AiToolsValidate::status());
        $affected = $this->dep(AiToolsDep::class)->setStatus($param['id'], (int)$param['status']);

        return self::success(['affected' => $affected]);
    }

    /**
     * 智能体工具绑定（批量同步）
     */
    public function bindTools($request): array
    {
        $param = $this->validate($request, AiToolsValidate::bindTools());
        $agentId = (int)$param['agent_id'];
        $toolIds = array_map('intval', $param['tool_ids']);

        $this->withTransaction(function () use ($agentId, $toolIds) {
            $this->dep(AiAssistantToolsDep::class)->syncBindings($agentId, $toolIds);
        });

        return self::success();
    }

    /**
     * 获取智能体已绑定工具 + 全部可用工具
     */
    public function getAgentTools($request): array
    {
        $param = $this->validate($request, AiToolsValidate::getAgentTools());
        $agentId = (int)($param['agent_id'] ?? 0);

        $boundToolIds = [];
        if ($agentId > 0) {
            $bindings = $this->dep(AiAssistantToolsDep::class)->getBindingsByAgentId($agentId);
            $boundToolIds = $bindings->pluck('tool_id')->toArray();
        }

        $allTools = $this->dep(AiToolsDep::class)->getAllActive();

        return self::success([
            'bound_tool_ids' => $boundToolIds,
            'all_tools'      => $allTools->map(fn($t) => [
                'value' => $t->id,
                'label' => $t->name,
                'code'  => $t->code,
            ])->toArray(),
        ]);
    }

    // ==================== 私有方法 ====================

    /**
     * 校验执行器配置
     */
    private function validateExecutorConfig(int $executorType, array $config): void
    {
        if ($executorType === AiEnum::EXECUTOR_HTTP_WHITELIST) {
            self::throwIf(
                empty($config['url']) || !str_starts_with($config['url'], 'https://'),
                'HTTP白名单执行器的 URL 必须以 https:// 开头'
            );
        }

        if ($executorType === AiEnum::EXECUTOR_SQL_READONLY) {
            self::throwIf(
                empty($config['sql']) || !preg_match('/^\s*SELECT\b/i', $config['sql']),
                '只读SQL执行器的 SQL 必须以 SELECT 开头'
            );
        }
    }
}
