<?php

namespace app\lib\Ai;

use app\enum\AiEnum;
use app\lib\Crypto\KeyVault;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\Grok;
use RuntimeException;

/**
 * Neuron AI Agent 工厂
 * 根据数据库中的模型配置 + 智能体配置，创建 Neuron Agent 实例
 */
class NeuronAgentFactory
{
    /**
     * 各驱动的默认 baseUrl（OpenAILike 类型需要）
     */
    private static array $defaultBaseUrls = [
        AiEnum::DRIVER_QWEN     => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
        AiEnum::DRIVER_MOONSHOT => 'https://api.moonshot.cn/v1',
        AiEnum::DRIVER_ZHIPU    => 'https://open.bigmodel.cn/api/paas/v4',
        AiEnum::DRIVER_HUNYUAN  => 'https://api.hunyuan.cloud.tencent.com/v1',
    ];

    /**
     * 创建 Neuron AI Provider
     *
     * @param object $model 数据库模型记录（ai_models 表）
     * @param array|null $runtimeParams 用户实时调整的参数（temperature/max_tokens 等，从前端传入）
     * @return array{0: ?AIProviderInterface, 1: ?string} [provider, error]
     */
    public static function createProvider(object $model, ?array $runtimeParams = null): array
    {
        $driver    = $model->driver;
        $modelCode = $model->model_code;
        $endpoint  = $model->endpoint ?? '';

        // Ollama 是本地 Provider，不需要 API Key
        $apiKey = '';
        if ($driver !== AiEnum::DRIVER_OLLAMA) {
            try {
                $apiKey = KeyVault::decrypt($model->api_key_enc ?? '');
            } catch (RuntimeException $e) {
                return [null, "API Key 解密失败: {$e->getMessage()}"];
            }
            if (empty($apiKey)) {
                return [null, '模型未配置 API Key'];
            }
        }

        // 只传用户显式设置的运行时参数，其余让 Provider 官方默认值生效
        $parameters = self::mergeParameters($runtimeParams);

        try {
            $provider = match ($driver) {
                // ---- Neuron AI 原生 Provider ----
                AiEnum::DRIVER_OPENAI   => new OpenAI(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_DEEPSEEK => new Deepseek(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_CLAUDE   => new Anthropic(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_GEMINI   => new Gemini(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_MISTRAL  => new Mistral(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_COHERE   => new Cohere(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_GROK     => new Grok(key: $apiKey, model: $modelCode, parameters: $parameters),
                AiEnum::DRIVER_OLLAMA   => new Ollama(
                    url: !empty($endpoint) ? $endpoint : 'http://localhost:11434/api',
                    model: $modelCode,
                    parameters: $parameters,
                ),
                // ---- OpenAI 兼容接口 ----
                default => self::createOpenAILike($driver, $apiKey, $modelCode, $endpoint, $parameters),
            };
        } catch (\Throwable $e) {
            return [null, "创建 Provider 失败: {$e->getMessage()}"];
        }

        // 如果原生 Provider 配置了自定义 endpoint，用 OpenAILike 替代
        $nativeWithEndpoint = [
            AiEnum::DRIVER_OPENAI, AiEnum::DRIVER_DEEPSEEK,
            AiEnum::DRIVER_GROK, AiEnum::DRIVER_MISTRAL,
        ];
        if (!empty($endpoint) && \in_array($driver, $nativeWithEndpoint, true)) {
            $provider = new OpenAILike(
                baseUri: rtrim($endpoint, '/'),
                key: $apiKey,
                model: $modelCode,
                parameters: $parameters,
            );
        }

        return [$provider, null];
    }

    /**
     * 创建 OpenAILike Provider（通用 OpenAI 兼容接口 + 反代服务）
     */
    private static function createOpenAILike(
        string $driver, string $apiKey, string $modelCode, string $endpoint, array $parameters
    ): OpenAILike {
        $baseUri = !empty($endpoint)
            ? rtrim($endpoint, '/')
            : (self::$defaultBaseUrls[$driver] ?? '');

        if (empty($baseUri)) {
            throw new RuntimeException("驱动 {$driver} 未配置 endpoint 且无默认 baseUrl");
        }

        return new OpenAILike(
            baseUri: $baseUri,
            key: $apiKey,
            model: $modelCode,
            parameters: $parameters,
        );
    }

    /**
     * 根据模型 + 智能体配置创建完整的 Neuron Agent
     */
    public static function createAgent(object $model, object $agent, ?array $runtimeParams = null): array
    {
        [$provider, $error] = self::createProvider($model, $runtimeParams);
        if ($error) {
            return [null, $error];
        }

        $instructions = $agent->system_prompt ?? '';

        $neuronAgent = Agent::make()
            ->setAiProvider($provider)
            ->setInstructions($instructions);

        return [$neuronAgent, null];
    }

    /**
     * 合并运行时参数（用户在聊天界面实时调整的 temperature/max_tokens 等）
     * 只传用户显式设置的值，其余让 Provider 官方默认值生效
     */
    private static function mergeParameters(?array $runtimeParams): array
    {
        if (empty($runtimeParams) || !\is_array($runtimeParams)) {
            return [];
        }

        $params = [];

        // 只允许白名单参数透传，防止注入奇怪的东西
        $allowedKeys = ['temperature', 'max_tokens', 'top_p', 'top_k', 'frequency_penalty', 'presence_penalty'];
        foreach ($allowedKeys as $key) {
            if (isset($runtimeParams[$key]) && $runtimeParams[$key] !== null) {
                $params[$key] = is_numeric($runtimeParams[$key])
                    ? (str_contains((string)$runtimeParams[$key], '.') ? (float)$runtimeParams[$key] : (int)$runtimeParams[$key])
                    : $runtimeParams[$key];
            }
        }

        return $params;
    }
}
