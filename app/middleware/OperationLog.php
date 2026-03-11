<?php

namespace app\middleware;

use app\enum\CommonEnum;
use app\service\Common\AnnotationHelper;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Webman\RedisQueue\Redis;

class OperationLog implements MiddlewareInterface
{
    private const MASK = '******';

    private const ALWAYS_MASK_FIELDS = [
        'password',
        'old_password',
        'new_password',
        'newpassword',
        'confirm_password',
        'refresh_token',
        'access_token',
        'token',
        'authorization',
        'secret',
        'secret_id',
        'secret_key',
        'api_key',
        'captcha',
        'captcha_code',
        'sms_code',
        'email_code',
        'verification_code',
    ];

    public function process(Request $request, callable $handler): Response
    {
        $action = AnnotationHelper::getOperationLogAnnotation($request);
        if (!$action) {
            return $handler($request);
        }

        $userId = $request->userId ?? 0;
        $rawReq = $request->all();

        try {
            $response = $handler($request);

            $content = $response->rawBody();
            $parsed = json_decode($content, true);
            $bizSuccess = isset($parsed['code'])
                ? ($parsed['code'] === 0)
                : ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300);

            $data = [
                'user_id' => $userId,
                'action' => $action,
                'request_data' => self::encodeForLog($rawReq, true),
                'response_data' => self::encodeForLog($parsed ?? ['raw' => $content]),
                'is_success' => $bizSuccess ? CommonEnum::YES : CommonEnum::NO,
            ];
        } catch (Throwable $e) {
            $data = [
                'user_id' => $userId,
                'action' => $action,
                'request_data' => self::encodeForLog($rawReq, true),
                'response_data' => self::encodeForLog([
                    'error' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : 'hidden',
                ]),
                'is_success' => CommonEnum::NO,
            ];

            Redis::send('operation_log', $data);
            throw $e;
        }

        Redis::send('operation_log', $data);

        return $response;
    }

    public static function sanitizeForLog(mixed $data, bool $maskGenericCode = false): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $normalizedKey = is_string($key) ? strtolower($key) : (string) $key;
                if (self::shouldMaskField($normalizedKey, $maskGenericCode)) {
                    $sanitized[$key] = self::MASK;
                    continue;
                }

                $sanitized[$key] = self::sanitizeForLog($value, $maskGenericCode);
            }
            return $sanitized;
        }

        if (is_object($data)) {
            return self::sanitizeForLog(get_object_vars($data), $maskGenericCode);
        }

        return $data;
    }

    private static function encodeForLog(mixed $data, bool $maskGenericCode = false): string
    {
        return json_encode(self::sanitizeForLog($data, $maskGenericCode), JSON_UNESCAPED_UNICODE);
    }

    private static function shouldMaskField(string $field, bool $maskGenericCode): bool
    {
        if ($maskGenericCode && $field === 'code') {
            return true;
        }

        return in_array($field, self::ALWAYS_MASK_FIELDS, true);
    }
}
