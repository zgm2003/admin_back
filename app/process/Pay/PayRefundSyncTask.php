<?php

namespace app\process\Pay;

use app\dep\Pay\PayRefundDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\Pay\PayRefundModule;
use app\process\BaseCronTask;
use RuntimeException;
use support\Log;
use Yansongda\Supports\Collection;

/**
 * 退款状态补查定时任务
 * 每3分钟扫描退款中记录，主动向第三方查退款结果
 */
class PayRefundSyncTask extends BaseCronTask
{
    protected function getTaskName(): string
    {
        return 'pay_refund_sync';
    }

    protected function handle(): ?string
    {
        // 10 分钟前的退款中记录
        $since = date('Y-m-d H:i:s', time() - 600);
        $refunds = (new PayRefundDep())->getPendingRefund($since, 100);

        $checked = 0;
        foreach ($refunds as $refund) {
            try {
                $this->checkRefund($refund);
                $checked++;
            } catch (\Throwable $e) {
                Log::error("[PayRefundSync] 查退款失败 refund_no={$refund['refund_no']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $checked > 0 ? "补查了 {$checked} 笔退款" : null;
    }

    private function checkRefund(array $refund): void
    {
        $channelId = $refund['channel_id'] ?? 0;
        $channel = $refund['channel'] ?? PayEnum::CHANNEL_WECHAT;

        // 调用第三方退款查询
        try {
            $queryResult = $this->queryThirdPartyRefund($channel, $channelId, $refund);
        } catch (RuntimeException $e) {
            Log::warning("[PayRefundSync] 第三方退款查询异常 refund_no={$refund['refund_no']}", [
                'error' => $e->getMessage(),
            ]);
            return; // 第三方查询超时/异常，跳过本次
        }

        // 根据第三方返回结果处理退款状态
        $refundStatus = $this->parseRefundStatus($queryResult);

        if ($refundStatus === 'SUCCESS') {
            Log::info("[PayRefundSync] 第三方退款成功 refund_no={$refund['refund_no']}", [
                'trade_refund_no' => $refund['trade_refund_no'] ?? '',
            ]);
            $this->handleRefundSuccess($refund);
        } elseif ($refundStatus === 'FAIL') {
            Log::warning("[PayRefundSync] 第三方退款失败 refund_no={$refund['refund_no']}", [
                'result' => is_object($queryResult) ? $queryResult->toArray() : $queryResult,
            ]);
            $this->handleRefundFail($refund, '第三方退款失败');
        } else {
            Log::info("[PayRefundSync] 第三方退款状态未知，保持现状 refund_no={$refund['refund_no']}", [
                'result' => is_object($queryResult) ? $queryResult->toArray() : $queryResult,
            ]);
        }
    }

    /**
     * 调用第三方退款查询
     */
    private function queryThirdPartyRefund(int $channel, int $channelId, array $refund): mixed
    {
        if ($channel === PayEnum::CHANNEL_WECHAT) {
            return $this->queryWechatRefund($channelId, $refund);
        }

        if ($channel === PayEnum::CHANNEL_ALIPAY) {
            return $this->queryAlipayRefund($channelId, $refund);
        }

        throw new RuntimeException('不支持的支付渠道');
    }

    /**
     * 微信退款查询
     */
    private function queryWechatRefund(int $channelId, array $refund): mixed
    {
        $sdk = new PaySdk();
        $sdk->initWechat($channelId);

        $params = [];

        if (!empty($refund['trade_no'])) {
            $params['transaction_id'] = $refund['trade_no'];
        } else {
            $params['out_trade_no'] = $refund['order_no'];
        }

        if (!empty($refund['trade_refund_no'])) {
            $params['refund_id'] = $refund['trade_refund_no'];
        }

        return \Yansongda\Pay\Pay::wechat()->refundQuery($params);
    }

    /**
     * 支付宝退款查询
     */
    private function queryAlipayRefund(int $channelId, array $refund): mixed
    {
        $sdk = new PaySdk();
        $sdk->initAlipay($channelId);

        $params = [];

        if (!empty($refund['trade_no'])) {
            $params['trade_no'] = $refund['trade_no'];
        } else {
            $params['out_trade_no'] = $refund['order_no'];
        }

        if (!empty($refund['trade_refund_no'])) {
            $params['out_request_no'] = $refund['trade_refund_no'];
        }

        return \Yansongda\Pay\Pay::alipay()->refundQuery($params);
    }

    /**
     * 解析第三方退款状态
     */
    private function parseRefundStatus(mixed $result): string
    {
        if ($result instanceof Collection) {
            $result = $result->toArray();
        }

        if (!is_array($result)) {
            return 'UNKNOWN';
        }

        // 微信退款状态：SUCCESS, CHANGE, REFUNDCLOSE（退款关闭）
        if (isset($result['result_code']) && $result['result_code'] === 'SUCCESS') {
            if (isset($result['refund_status_0'])) {
                if ($result['refund_status_0'] === 'SUCCESS') {
                    return 'SUCCESS';
                }
                if ($result['refund_status_0'] === 'REFUNDCLOSE') {
                    return 'FAIL';
                }
            }
            if (isset($result['refund_status'])) {
                if ($result['refund_status'] === 'SUCCESS') {
                    return 'SUCCESS';
                }
                if ($result['refund_status'] === 'REFUNDCLOSE') {
                    return 'FAIL';
                }
            }
        }

        // 支付宝退款状态：SUCCESS, FAIL, CHANGE（处理中）
        if (isset($result['refund_status']) && $result['refund_status'] === 'SUCCESS') {
            return 'SUCCESS';
        }
        if (isset($result['refund_status']) && $result['refund_status'] === 'FAIL') {
            return 'FAIL';
        }

        return 'UNKNOWN';
    }

    /**
     * 处理退款成功
     */
    private function handleRefundSuccess(array $refund): void
    {
        try {
            $refundModule = new PayRefundModule();
            $refundModule->handleRefundSuccess(
                $refund['refund_no'],
                $refund['trade_refund_no'] ?? '',
                ['source' => 'cron_refund_sync']
            );
        } catch (\Throwable $e) {
            Log::error("[PayRefundSync] 退款成功处理异常 refund_no={$refund['refund_no']}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理退款失败
     */
    private function handleRefundFail(array $refund, string $reason): void
    {
        try {
            $refundModule = new PayRefundModule();
            $refundModule->handleRefundFail($refund['refund_no'], $reason);
        } catch (\Throwable $e) {
            Log::error("[PayRefundSync] 退款失败处理异常 refund_no={$refund['refund_no']}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
