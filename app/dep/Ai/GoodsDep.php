<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\GoodsModel;
use support\Model;

class GoodsDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new GoodsModel();
    }

    /**
     * 列表查询（分页 + 过滤）
     */
    public function list(array $param)
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['title']), fn($q) => $q->where('title', 'like', '%' . $param['title'] . '%'))
            ->when(isset($param['platform']) && $param['platform'] !== '', fn($q) => $q->where('platform', (int)$param['platform']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int)$param['status']))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);
    }

    /**
     * 乐观锁状态流转
     * 只有当前状态匹配 $fromStatus 时才更新，返回受影响行数
     * 并发安全：两个请求同时操作同一条记录，只有一个能成功
     */
    public function transitStatus(int $id, int $fromStatus, int $toStatus, array $extra = []): int
    {
        $data = array_merge(['status' => $toStatus, 'status_msg' => null], $extra);
        return $this->model
            ->where('id', $id)
            ->where('status', $fromStatus)
            ->where('is_del', CommonEnum::NO)
            ->update($data);
    }

    /**
     * 标记失败（任意非终态 → 失败）
     */
    public function markFailed(int $id, string $msg): int
    {
        return $this->model
            ->where('id', $id)
            ->where('is_del', CommonEnum::NO)
            ->whereNotIn('status', [\app\enum\GoodsEnum::STATUS_COMPLETED])
            ->update(['status' => \app\enum\GoodsEnum::STATUS_FAILED, 'status_msg' => $msg]);
    }

    /**
     * 按状态统计数量
     */
    public function statusCount(?string $title = null, ?int $platform = null): array
    {
        return $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($title), fn($q) => $q->where('title', 'like', '%' . $title . '%'))
            ->when($platform !== null, fn($q) => $q->where('platform', $platform))
            ->selectRaw('status, COUNT(*) as num')
            ->groupBy('status')
            ->pluck('num', 'status')
            ->toArray();
    }

    /**
     * 批量清除音频和字幕链接（定时清理用）
     */
    public function clearAudioUrl(array $ids): int
    {
        return $this->model
            ->whereIn('id', $ids)
            ->where(fn($q) => $q->whereNotNull('audio_url')->orWhereNotNull('srt_url'))
            ->update(['audio_url' => null, 'srt_url' => null]);
    }
}
