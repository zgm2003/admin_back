<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CineEnum;
use app\enum\CommonEnum;
use app\model\Ai\CineAssetModel;
use support\Model;

class CineAssetDep extends BaseDep
{
    private const JSON_COLUMNS = ['meta_json'];

    protected function createModel(): Model
    {
        return new CineAssetModel();
    }

    public function add(array $data): int
    {
        return parent::add($this->normalizeJsonColumns($data));
    }

    public function update($ids, array $data): int
    {
        return parent::update($ids, $this->normalizeJsonColumns($data));
    }

    public function getByProjectId(int $projectId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->where('is_del', CommonEnum::NO)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function getGenerateTargetsByProjectId(int $projectId, array $assetIds = [])
    {
        $query = $this->model
            ->where('project_id', $projectId)
            ->where('asset_type', CineEnum::ASSET_TYPE_KEYFRAME)
            ->where('is_del', CommonEnum::NO)
            ->whereIn('status', [CineEnum::ASSET_STATUS_PENDING, CineEnum::ASSET_STATUS_FAILED])
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds), static fn(int $id) => $id > 0)));
        if (!empty($assetIds)) {
            $query->whereIn('id', $assetIds);
        }

        return $query->get();
    }

    public function countKeyframesByProjectId(int $projectId): int
    {
        return $this->model
            ->where('project_id', $projectId)
            ->where('asset_type', CineEnum::ASSET_TYPE_KEYFRAME)
            ->where('is_del', CommonEnum::NO)
            ->count();
    }

    public function markGenerating(int $id): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->update([
                'status' => CineEnum::ASSET_STATUS_GENERATING,
                'status_msg' => null,
            ]);
    }

    public function markReady(int $id, string $fileUrl, array $meta = []): int
    {
        return $this->update($id, [
            'file_url' => $fileUrl,
            'status' => CineEnum::ASSET_STATUS_READY,
            'status_msg' => null,
            'meta_json' => $meta,
        ]);
    }

    public function markFailed(int $id, string $msg, array $meta = []): int
    {
        $data = [
            'status' => CineEnum::ASSET_STATUS_FAILED,
            'status_msg' => mb_substr($msg, 0, 500),
        ];

        if (!empty($meta)) {
            $data['meta_json'] = $meta;
        }

        return $this->update($id, $data);
    }

    public function softDeleteByProjectId(int $projectId): int
    {
        return $this->model
            ->where('project_id', $projectId)
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    public function replaceProjectAssets(int $projectId, array $assets): void
    {
        $this->softDeleteByProjectId($projectId);
        foreach ($assets as $asset) {
            $this->add($asset);
        }
    }

    private function normalizeJsonColumns(array $data): array
    {
        foreach (self::JSON_COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }

            if (is_array($data[$column])) {
                $data[$column] = json_encode($data[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $data;
    }
}
