<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CineEnum;
use app\enum\CommonEnum;
use app\model\Ai\CineProjectModel;
use support\Model;

class CineProjectDep extends BaseDep
{
    private const JSON_COLUMNS = [
        'draft_json',
        'shotlist_json',
        'feed_pack_json',
        'reference_images_json',
        'tool_config_json',
        'continuity_review',
    ];

    protected function createModel(): Model
    {
        return new CineProjectModel();
    }

    public function add(array $data): int
    {
        return parent::add($this->normalizeJsonColumns($data));
    }

    public function update($ids, array $data): int
    {
        return parent::update($ids, $this->normalizeJsonColumns($data));
    }

    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', '%' . $param['title'] . '%'))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->when(!empty($param['agent_id']), fn($q) => $q->where('agent_id', (int)$param['agent_id']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    public function statusCount(?string $title = null): array
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($title), fn($q) => $q->where('title', 'like', '%' . $title . '%'))
            ->selectRaw('status, COUNT(*) as num')
            ->groupBy('status')
            ->pluck('num', 'status')
            ->toArray();
    }

    public function transitStatusFromAllowed(int $id, array $fromStatuses, int $toStatus, array $extra = []): int
    {
        $data = $this->normalizeJsonColumns(array_merge(['status' => $toStatus, 'status_msg' => null], $extra));

        return $this->model
            ->where('id', $id)
            ->whereIn('status', $fromStatuses)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }

    public function transitStatus(int $id, int $fromStatus, int $toStatus, array $extra = []): int
    {
        return $this->transitStatusFromAllowed($id, [$fromStatus], $toStatus, $extra);
    }

    public function saveGenerationResult(int $id, array $parsed): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->update($this->normalizeJsonColumns([
                'status' => CineEnum::STATUS_READY,
                'status_msg' => null,
                'deliverable_markdown' => $parsed['deliverable_markdown'] ?? '',
                'draft_json' => $parsed['draft'] ?? [],
                'shotlist_json' => $parsed['shotlist'] ?? [],
                'feed_pack_json' => $parsed['feed_pack'] ?? [],
                'continuity_review' => $parsed['continuity_review'] ?? [],
                'model_origin' => $parsed['model_origin'] ?? '',
            ]));
    }

    public function markFailed(int $id, string $msg): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->whereNotIn('status', [CineEnum::STATUS_COMPLETED])
            ->update([
                'status' => CineEnum::STATUS_FAILED,
                'status_msg' => mb_substr($msg, 0, 500),
            ]);
    }

    public function markCompleted(int $id): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CineEnum::STATUS_IMAGE_GENERATING)
            ->update([
                'status' => CineEnum::STATUS_COMPLETED,
                'status_msg' => null,
            ]);
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
