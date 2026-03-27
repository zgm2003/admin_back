<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\PayChannelModel;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use support\Model;

class PayChannelDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new PayChannelModel();
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'name', 'channel', 'mch_id', 'app_id',
                'app_private_key_hint', 'app_private_key_enc',
                'public_cert_path', 'platform_cert_path', 'root_cert_path',
                'notify_url', 'return_url', 'is_sandbox', 'sort', 'status', 'remark', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(isset($param['channel']) && $param['channel'] !== '', fn($q) => $q->where('channel', (int) $param['channel']))
            ->when(isset($param['status']) && $param['status'] !== '', fn($q) => $q->where('status', (int) $param['status']))
            ->when(!empty($param['name']), fn($q) => $q->where('name', 'like', $param['name'] . '%'))
            ->orderBy('channel', 'asc')
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }

    /**
     * 获取活跃渠道（生产优先，沙盒兜底）
     * 注意：生产环境（is_sandbox=2）优先；沙盒环境需单独处理
     */
    public function getActiveByChannel(int $channel)
    {
        return $this->model
            ->where('channel', $channel)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->where('is_sandbox', CommonEnum::NO)
            ->orderBy('sort', 'asc')
            ->first();
    }

    /**
     * 获取活跃沙盒渠道（仅用于测试环境）
     */
    public function getActiveSandboxByChannel(int $channel)
    {
        return $this->model
            ->where('channel', $channel)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->where('is_sandbox', CommonEnum::YES)
            ->orderBy('sort', 'asc')
            ->first();
    }

    /**
     * 按渠道类型获取可用渠道
     * 生产优先，沙盒兜底
     */
    public function getPreferredActiveByChannel(int $channel): ?Model
    {
        return $this->getActiveByChannel($channel) ?: $this->getActiveSandboxByChannel($channel);
    }

    /**
     * 兼容解析渠道输入：
     * - 优先按 pay_channel.id 查
     * - 查不到时，若入参本身是渠道类型枚举，则按渠道类型兜底
     */
    public function resolveActiveInput(int $input): ?Model
    {
        if ($input <= 0) {
            return null;
        }

        $channel = $this->findActive($input);
        if ($channel) {
            return $channel;
        }

        if (!isset(PayEnum::$channelArr[$input])) {
            return null;
        }

        return $this->getPreferredActiveByChannel($input);
    }

    public function existsByChannelMchApp(int $channel, string $mchId, string $appId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('channel', $channel)
            ->where('mch_id', $mchId)
            ->where('app_id', $appId)
            ->where('is_del', CommonEnum::NO);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function findActive(int $id): ?Model
    {
        return $this->model
            ->where('id', $id)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    public function getActiveByMchId(int $channel, string $mchId): ?Model
    {
        return $this->model
            ->where('channel', $channel)
            ->where('mch_id', $mchId)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    public function getActiveByAppId(int $channel, string $appId): ?Model
    {
        return $this->model
            ->where('channel', $channel)
            ->where('app_id', $appId)
            ->where('status', CommonEnum::YES)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }
}
