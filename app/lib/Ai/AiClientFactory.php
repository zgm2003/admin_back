<?php

namespace app\lib\Ai;

use app\enum\AiEnum;
use app\lib\Ai\Clients\OpenAiCompatClient;
use RuntimeException;

/**
 * AI 客户端工厂
 * 根据驱动类型创建对应的客户端实例
 */
class AiClientFactory
{
    /**
     * 已注册的驱动映射
     */
    private static array $drivers = [
        // OpenAI 兼容接口驱动
        AiEnum::DRIVER_QWEN => OpenAiCompatClient::class,
        AiEnum::DRIVER_DEEPSEEK => OpenAiCompatClient::class,
        AiEnum::DRIVER_MOONSHOT => OpenAiCompatClient::class,
        AiEnum::DRIVER_ZHIPU => OpenAiCompatClient::class,
        AiEnum::DRIVER_OPENAI => OpenAiCompatClient::class,
        // 以后可扩展：
        // AiEnum::DRIVER_WENXIN => WenxinClient::class,
        // AiEnum::DRIVER_HUNYUAN => HunyuanClient::class,
    ];

    /**
     * 各驱动的默认 baseUrl
     */
    private static array $defaultBaseUrls = [
        AiEnum::DRIVER_QWEN => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
        AiEnum::DRIVER_DEEPSEEK => 'https://api.deepseek.com/v1',
        AiEnum::DRIVER_MOONSHOT => 'https://api.moonshot.cn/v1',
        AiEnum::DRIVER_ZHIPU => 'https://open.bigmodel.cn/api/paas/v4',
        AiEnum::DRIVER_OPENAI => 'https://api.openai.com/v1',
    ];

    /**
     * 创建客户端实例
     * @param string $driver 驱动类型
     * @return AiClientInterface
     * @throws RuntimeException
     */
    public static function create(string $driver): AiClientInterface
    {
        if (!isset(self::$drivers[$driver])) {
            throw new RuntimeException("不支持的 AI 驱动: {$driver}");
        }

        $clientClass = self::$drivers[$driver];
        $baseUrl = self::$defaultBaseUrls[$driver] ?? '';

        return new $clientClass($baseUrl);
    }

    /**
     * 获取驱动的默认 baseUrl
     * @param string $driver
     * @return string
     */
    public static function getDefaultBaseUrl(string $driver): string
    {
        return self::$defaultBaseUrls[$driver] ?? '';
    }

    /**
     * 判断驱动是否支持
     * @param string $driver
     * @return bool
     */
    public static function isSupported(string $driver): bool
    {
        return isset(self::$drivers[$driver]);
    }
}
