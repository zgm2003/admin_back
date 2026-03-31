<?php

namespace app\module\Pay;

use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayNotifyLogDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use app\lib\Pay\PaySdk;
use app\module\BaseModule;
use app\service\Pay\PayDomainService;
use RuntimeException;
use support\Log;
use support\Request;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;

class PayNotifyModule extends BaseModule
{
    public function wechat(Request $request): array
    {
        return $this->handleCallback($request, PayEnum::CHANNEL_WECHAT);
    }

    public function alipay(Request $request): array
    {
        return $this->handleCallback($request, PayEnum::CHANNEL_ALIPAY);
    }

    private function handleCallback(Request $request, int $channelType): array
    {
        $ip = $request->getRealIp();
        $headers = (array) $request->header();
        $rawData = $request->all();
        if ($rawData === []) {
            $decodedBody = json_decode($request->rawBody(), true);
            if (is_array($decodedBody)) {
                $rawData = $decodedBody;
            }
        }
        $logId = 0;
        try {
            $logId = $this->dep(PayNotifyLogDep::class)->add([
                'channel' => $channelType,
                'notify_type' => PayEnum::NOTIFY_PAY,
                'transaction_no' => $rawData['out_trade_no'] ?? $rawData['transaction_no'] ?? '',
                'trade_no' => $rawData['trade_no'] ?? $rawData['transaction_id'] ?? '',
                'headers' => $headers,
                'raw_data' => $rawData,
                'process_status' => PayEnum::NOTIFY_PROCESS_PENDING,
                'process_msg' => '待处理',
                'ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PayNotify] 回调日志写入失败', [
                'channel' => $channelType,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $data = $channelType === PayEnum::CHANNEL_WECHAT
                ? $this->verifyWechatCallback($request, $rawData, $headers)
                : $this->verifyAlipayCallback($rawData);

            $transactionNo = (string) ($data['out_trade_no'] ?? '');
            $tradeNo = (string) ($data['trade_no'] ?? $data['transaction_id'] ?? '');
            if ($transactionNo === '') {
                throw new RuntimeException('回调缺少订单号');
            }

            if ($logId > 0) {
                $this->dep(PayNotifyLogDep::class)->update($logId, [
                    'transaction_no' => $transactionNo,
                    'trade_no' => $tradeNo,
                ]);
            }

            $result = $this->svc(PayDomainService::class)->handlePaySuccess($transactionNo, $tradeNo, $channelType, $data);
            $processStatus = $result['status'] === 'success'
                ? PayEnum::NOTIFY_PROCESS_SUCCESS
                : PayEnum::NOTIFY_PROCESS_IGNORED;
            if ($logId > 0) {
                $this->dep(PayNotifyLogDep::class)->updateProcess($logId, $processStatus, (string) ($result['message'] ?? '处理完成'));
            }

            Log::info('[PayNotify] 支付回调处理成功', [
                'channel' => $channelType,
                'transaction_no' => $transactionNo,
                'trade_no' => $tradeNo,
                'result' => $result,
            ]);

            return $channelType === PayEnum::CHANNEL_WECHAT
                ? $this->wechatResponse(true, 'OK')
                : $this->alipayResponse(true, 'SUCCESS');
        } catch (\Throwable $e) {
            if ($logId > 0) {
                $this->dep(PayNotifyLogDep::class)->updateProcess($logId, PayEnum::NOTIFY_PROCESS_FAILED, $e->getMessage());
            }
            Log::error('[PayNotify] 支付回调处理异常', [
                'channel' => $channelType,
                'error' => $e->getMessage(),
            ]);

            return $channelType === PayEnum::CHANNEL_WECHAT
                ? $this->wechatResponse(false, $e->getMessage())
                : $this->alipayResponse(false, $e->getMessage());
        }
    }

    private function verifyWechatCallback(Request $request, array $rawData, array $headers): array
    {
        $transactionNo = (string) ($rawData['out_trade_no'] ?? '');
        $channel = $transactionNo !== ''
            ? $this->findChannelByTransactionNo($transactionNo, PayEnum::CHANNEL_WECHAT)
            : null;

        $channel ??= $this->dep(PayChannelDep::class)->getPreferredActiveByChannel(PayEnum::CHANNEL_WECHAT);
        if (!$channel) {
            throw new RuntimeException('未配置微信支付渠道');
        }

        PaySdk::initWechat((int) $channel->id);
        $data = Pay::wechat()->callback([
            'body' => $request->rawBody(),
            'headers' => $headers,
        ]);

        if (!($data instanceof Collection)) {
            throw new RuntimeException('签名验证失败');
        }

        return $data->toArray();
    }

    private function verifyAlipayCallback(array $rawData): array
    {
        $transactionNo = (string) ($rawData['out_trade_no'] ?? '');
        $channel = $transactionNo !== ''
            ? $this->findChannelByTransactionNo($transactionNo, PayEnum::CHANNEL_ALIPAY)
            : null;

        $channel ??= $this->dep(PayChannelDep::class)->getPreferredActiveByChannel(PayEnum::CHANNEL_ALIPAY);
        if (!$channel) {
            throw new RuntimeException('未配置支付宝渠道');
        }

        PaySdk::initAlipay((int) $channel->id);
        $data = Pay::alipay()->callback($rawData);

        if (!($data instanceof Collection)) {
            throw new RuntimeException('签名验证失败');
        }

        return $data->toArray();
    }

    private function findChannelByTransactionNo(string $transactionNo, int $channelType): ?object
    {
        $txn = $this->dep(PayTransactionDep::class)->findByTransactionNo($transactionNo);
        if (!$txn || !$txn->channel_id) {
            return null;
        }

        $channel = $this->dep(PayChannelDep::class)->findActive((int) $txn->channel_id);
        if ($channel && (int) $channel->channel === $channelType) {
            return $channel;
        }

        return null;
    }

    private function wechatResponse(bool $success, string $message): array
    {
        if ($success) {
            return ['code' => 'SUCCESS', 'message' => 'OK'];
        }

        return ['code' => 'FAIL', 'message' => $message];
    }

    private function alipayResponse(bool $success, string $message): array
    {
        if ($success) {
            return ['code' => 'success', 'msg' => $message];
        }

        return ['code' => 'fail', 'msg' => $message];
    }
}
