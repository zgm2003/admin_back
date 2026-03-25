<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\lib\Pay\PaySdk;
use app\module\Pay\PayNotifyModule;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use support\Request;
use support\Response;

/**
 * 支付回调（第三方回调入口，无需登录）
 *
 * 不能走 Controller::run() 的标准 JSON 包装。
 * 回调成功响应必须复用支付 SDK 约定的原始协议格式。
 */
class PayNotifyController extends Controller
{
    public function wechat(Request $request): Response
    {
        $result = (new PayNotifyModule())->wechat($request);

        if (($result['code'] ?? '') === 'SUCCESS') {
            return $this->toSupportResponse((new PaySdk())->wechatSuccess());
        }

        return new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function alipay(Request $request): Response
    {
        $result = (new PayNotifyModule())->alipay($request);

        if (($result['code'] ?? '') === 'success') {
            return $this->toSupportResponse((new PaySdk())->alipaySuccess());
        }

        return new Response(200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ], 'fail');
    }

    private function toSupportResponse(PsrResponseInterface $response): Response
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return new Response($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
