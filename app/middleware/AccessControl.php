<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class AccessControl implements MiddlewareInterface
{
    private const TRUSTED_LOCAL_ORIGIN_PATTERN = '/^https?:\/\/(localhost|127\.0\.0\.1)(:\d{1,5})?$/i';
    private const TRUSTED_EXTENSION_ORIGIN_PATTERN = '/^chrome-extension:\/\/[a-p]{32}$/i';

    public function process(Request $request, callable $handler): Response
    {
        $response = $request->method() === 'OPTIONS' ? response('') : $handler($request);

        return $response->withHeaders(self::buildCorsHeaders($request));
    }

    public static function buildCorsHeaders(Request $request): array
    {
        $headers = [
            'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => $request->header('access-control-request-method', '*'),
            'Access-Control-Allow-Headers' => $request->header('access-control-request-headers', '*'),
        ];

        return array_merge($headers, self::buildOriginHeaders($request->header('origin')));
    }

    public static function buildOriginHeaders(?string $origin): array
    {
        $allowOrigin = self::resolveAllowedOrigin($origin);
        if ($allowOrigin === null) {
            return [];
        }

        return [
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Origin' => $allowOrigin,
        ];
    }

    public static function resolveAllowedOrigin(?string $origin): ?string
    {
        if (!is_string($origin) || trim($origin) === '') {
            return null;
        }

        $origin = trim($origin);

        if (preg_match(self::TRUSTED_LOCAL_ORIGIN_PATTERN, $origin) === 1) {
            return $origin;
        }

        if (preg_match(self::TRUSTED_EXTENSION_ORIGIN_PATTERN, $origin) === 1) {
            return $origin;
        }


        if (in_array($origin, self::allowedOrigins(), true)) {
            return $origin;
        }

        return null;
    }

    public static function allowedOrigins(): array
    {
        $configured = config('app.cors_allowed_origins', []);
        if (!is_array($configured)) {
            $configured = [];
        }

        $configured = array_values(array_filter(array_map(
            static fn ($origin) => is_string($origin) ? trim($origin) : '',
            $configured
        )));

        return array_values(array_unique(array_merge(
            ['https://zgm2003.cn', 'https://www.zgm2003.cn'],
            $configured
        )));
    }
}
