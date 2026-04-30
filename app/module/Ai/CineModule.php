<?php

namespace app\module\Ai;

use app\dep\Ai\AiAgentsDep;
use app\dep\Ai\CineAssetDep;
use app\dep\Ai\CineProjectDep;
use app\enum\AiEnum;
use app\enum\CineEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\validate\Ai\CineValidate;

class CineModule extends BaseModule
{
    public function init($request): array
    {
        $agents = $this->dep(AiAgentsDep::class)->getActiveBySceneAndMode(AiEnum::SCENE_CINE_PROJECT, AiEnum::MODE_TOOL);

        return self::success([
            'dict' => [
                'cine_status_arr' => $this->toOptions(CineEnum::$statusArr),
                'cine_agent_list' => $agents->map(fn($item) => [
                    'value' => $item->id,
                    'label' => $item->name,
                ])->toArray(),
            ],
        ]);
    }

    public function statusCount($request): array
    {
        $param = $this->validate($request, CineValidate::statusCount());
        $countMap = $this->dep(CineProjectDep::class)->statusCount($param['title'] ?? null);
        $total = array_sum($countMap);

        $result = [['label' => '全部', 'value' => '', 'num' => $total]];
        foreach (CineEnum::$statusArr as $value => $label) {
            $result[] = ['label' => $label, 'value' => $value, 'num' => $countMap[$value] ?? 0];
        }

        return self::success($result);
    }

    public function list($request): array
    {
        $param = $this->validate($request, CineValidate::list());
        $param['page_size'] ??= 15;
        $param['current_page'] ??= 1;

        $res = $this->dep(CineProjectDep::class)->list($param);
        $list = $res->map(fn($item) => $this->formatProject($item, false));

        return self::paginate($list, [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ]);
    }

    public function detail($request): array
    {
        $param = $this->validate($request, CineValidate::detail());
        $project = $this->dep(CineProjectDep::class)->getOrFail((int)$param['id']);

        return self::success($this->formatProject($project, true));
    }

    public function add($request): array
    {
        $param = $this->validate($request, CineValidate::add());

        $data = [
            'user_id' => (int)($request->userId ?? 0),
            'title' => $param['title'],
            'source_text' => $param['source_text'],
            'style' => $param['style'] ?? '电影感，克制表演',
            'duration_seconds' => (int)($param['duration_seconds'] ?? 30),
            'aspect_ratio' => $param['aspect_ratio'] ?? '9:16',
            'mode' => $param['mode'] ?? CineEnum::MODE_DRAFT,
            'agent_id' => (int)($param['agent_id'] ?? 0),
            'reference_images_json' => $param['reference_images_json'] ?? null,
            'tool_config_json' => $param['tool_config_json'] ?? null,
            'status' => CineEnum::STATUS_DRAFT,
            'is_del' => CommonEnum::NO,
        ];

        $id = $this->dep(CineProjectDep::class)->add($data);

        return self::success(['id' => $id]);
    }

    public function edit($request): array
    {
        $param = $this->validate($request, CineValidate::edit());
        $id = (int)$param['id'];
        $dep = $this->dep(CineProjectDep::class);
        $project = $dep->getOrFail($id);

        self::throwIf((int)$project->status === CineEnum::STATUS_GENERATING, '草稿生成中，不能编辑');
        self::throwIf((int)$project->status === CineEnum::STATUS_IMAGE_GENERATING, '分镜生成中，不能编辑');

        $data = [];
        foreach ([
            'title',
            'source_text',
            'style',
            'duration_seconds',
            'aspect_ratio',
            'mode',
            'agent_id',
            'reference_images_json',
            'tool_config_json',
            'deliverable_markdown',
        ] as $field) {
            if (array_key_exists($field, $param)) {
                $data[$field] = $param[$field];
            }
        }

        if (!empty($data)) {
            $dep->update($id, $data);
        }

        return self::success();
    }

    public function del($request): array
    {
        $param = $this->validate($request, CineValidate::del());
        $affected = $this->dep(CineProjectDep::class)->delete($param['id']);

        return self::success(['affected' => $affected]);
    }

    public function generate($request): array
    {
        $param = $this->validate($request, CineValidate::generate());
        $id = (int)$param['id'];
        $dep = $this->dep(CineProjectDep::class);
        $project = $dep->getOrFail($id);

        self::throwIf(trim((string)$project->source_text) === '', '原始素材不能为空');

        $agentId = (int)($param['agent_id'] ?? $project->agent_id ?? 0);
        if ($agentId > 0) {
            $agent = $this->dep(AiAgentsDep::class)->get($agentId);
        } else {
            $agent = $this->dep(AiAgentsDep::class)->getBySceneAndMode(AiEnum::SCENE_CINE_PROJECT, AiEnum::MODE_TOOL);
            $agentId = (int)($agent->id ?? 0);
        }

        self::throwIf(!$agent || (int)$agent->status !== CommonEnum::YES, '请先配置并启用 AI短剧工厂 智能体');
        self::throwIf(($agent->mode ?? '') !== AiEnum::MODE_TOOL, 'AI短剧工厂智能体必须设置为工具模式，才能调用短剧工具链');

        $affected = $dep->transitStatusFromAllowed(
            $id,
            [CineEnum::STATUS_DRAFT, CineEnum::STATUS_READY, CineEnum::STATUS_COMPLETED, CineEnum::STATUS_FAILED],
            CineEnum::STATUS_GENERATING,
            ['agent_id' => $agentId]
        );
        self::throwIf($affected === 0, '状态已变更，请刷新后重试');

        \Webman\RedisQueue\Client::send('cine_process', [
            'id' => $id,
            'user_id' => (int)($request->userId ?? 0),
            'agent_id' => $agentId,
        ]);

        return self::success(['msg' => '草稿生成任务已提交']);
    }

    public function generateStoryboard($request): array
    {
        return $this->generateKeyframes($request);
    }

    public function generateKeyframes($request): array
    {
        $param = $this->validate($request, CineValidate::generateKeyframes());
        $id = (int)$param['id'];
        $assetIds = \is_array($param['asset_ids'] ?? null) ? $param['asset_ids'] : [];
        $projectDep = $this->dep(CineProjectDep::class);
        $project = $projectDep->getOrFail($id);

        self::throwIf((int)$project->status === CineEnum::STATUS_GENERATING, '草稿生成中，不能生成分镜');
        self::throwIf((int)$project->status === CineEnum::STATUS_IMAGE_GENERATING, '分镜生成中，请勿重复提交');
        self::throwIf(!\in_array((int)$project->status, [CineEnum::STATUS_READY, CineEnum::STATUS_COMPLETED, CineEnum::STATUS_FAILED], true), '请先生成草稿');
        self::throwIf($this->dep(CineAssetDep::class)->countKeyframesByProjectId($id) === 0, '没有可生成的分镜图片，请先生成草稿');

        $affected = $projectDep->transitStatusFromAllowed(
            $id,
            [CineEnum::STATUS_READY, CineEnum::STATUS_COMPLETED, CineEnum::STATUS_FAILED],
            CineEnum::STATUS_IMAGE_GENERATING
        );
        self::throwIf($affected === 0, '状态已变更，请刷新后重试');

        \Webman\RedisQueue\Client::send('cine_image_process', [
            'id' => $id,
            'asset_ids' => array_values($assetIds),
        ]);

        return self::success(['msg' => '分镜生成任务已提交']);
    }

    private function formatProject($item, bool $withAssets): array
    {
        $data = [
            'id' => (int)$item->id,
            'user_id' => (int)$item->user_id,
            'title' => $item->title,
            'source_text' => $item->source_text,
            'style' => $item->style,
            'duration_seconds' => (int)$item->duration_seconds,
            'aspect_ratio' => $item->aspect_ratio,
            'mode' => $item->mode,
            'mode_name' => CineEnum::$modeArr[$item->mode] ?? '',
            'agent_id' => (int)$item->agent_id,
            'status' => (int)$item->status,
            'status_name' => CineEnum::$statusArr[$item->status] ?? '',
            'status_msg' => $item->status_msg,
            'deliverable_markdown' => $item->deliverable_markdown,
            'draft_json' => $item->draft_json,
            'shotlist_json' => $item->shotlist_json,
            'feed_pack_json' => $item->feed_pack_json,
            'reference_images_json' => $item->reference_images_json,
            'tool_config_json' => $item->tool_config_json,
            'continuity_review' => $item->continuity_review,
            'model_origin' => $item->model_origin,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];

        if ($withAssets) {
            $data['assets'] = $this->dep(CineAssetDep::class)
                ->getByProjectId((int)$item->id)
                ->map(fn($asset) => [
                    'id' => (int)$asset->id,
                    'project_id' => (int)$asset->project_id,
                    'asset_type' => $asset->asset_type,
                    'shot_id' => $asset->shot_id,
                    'prompt' => $asset->prompt,
                    'file_url' => $asset->file_url,
                    'status' => (int)$asset->status,
                    'status_name' => CineEnum::$assetStatusArr[$asset->status] ?? '',
                    'status_msg' => $asset->status_msg,
                    'sort' => (int)$asset->sort,
                    'meta_json' => $asset->meta_json,
                ])->toArray();
        }

        return $data;
    }

    private function toOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
