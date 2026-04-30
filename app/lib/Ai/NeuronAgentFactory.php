<?php

namespace app\lib\Ai;

use app\enum\AiEnum;
use app\lib\Crypto\KeyVault;
use NeuronAI\Agent\Agent;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\OpenAILikeResponses;
use NeuronAI\Providers\XAI\Grok;
use RuntimeException;
use support\Log;

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
        $parameters = self::mergeParameters($driver, $runtimeParams);
        $httpClient = self::createHttpClient($runtimeParams);

        try {
            $provider = match ($driver) {
                // ---- Neuron AI 原生 Provider ----
                AiEnum::DRIVER_OPENAI   => new OpenAILikeResponses(
                    baseUri: !empty($endpoint) ? rtrim($endpoint, '/') : 'https://api.openai.com/v1',
                    key: $apiKey,
                    model: $modelCode,
                    parameters: $parameters,
                    httpClient: $httpClient,
                ),
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
                default => self::createOpenAILike($driver, $apiKey, $modelCode, $endpoint, $parameters, $httpClient),
            };
        } catch (\Throwable $e) {
            return [null, "创建 Provider 失败: {$e->getMessage()}"];
        }

        // 如果原生 Provider 配置了自定义 endpoint，用 OpenAILike 替代
        $nativeWithEndpoint = [
            AiEnum::DRIVER_DEEPSEEK,
            AiEnum::DRIVER_GROK, AiEnum::DRIVER_MISTRAL,
        ];
        if (!empty($endpoint) && \in_array($driver, $nativeWithEndpoint, true)) {
            $provider = new OpenAILike(
                baseUri: rtrim($endpoint, '/'),
                key: $apiKey,
                model: $modelCode,
                parameters: $parameters,
                httpClient: $httpClient,
            );
        }

        return [$provider, null];
    }

    /**
     * 创建 OpenAILike Provider（通用 OpenAI 兼容接口 + 反代服务）
     */
    private static function createOpenAILike(
        string $driver,
        string $apiKey,
        string $modelCode,
        string $endpoint,
        array $parameters,
        ?GuzzleHttpClient $httpClient = null
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
            httpClient: $httpClient,
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

        // mode=tool 时注入工具。个别后台批处理可显式关闭工具，避免草稿阶段把图片工具塞给模型。
        $disableTools = (bool)($runtimeParams['disable_tools'] ?? false);
        if (!$disableTools && ($agent->mode ?? '') === AiEnum::MODE_TOOL) {
            $tools = self::loadAgentTools((int)$agent->id);
            if (!empty($tools)) {
                $neuronAgent->addTool($tools);
            }
        }

        return [$neuronAgent, null];
    }

    /**
     * 加载智能体绑定的工具并转换为 Neuron Tool 对象
     */
    private static function loadAgentTools(int $agentId): array
    {
        $dep = new \app\dep\Ai\AiAssistantToolsDep();
        $toolRecords = $dep->getActiveToolsByAgentId($agentId);

        $tools = [];
        foreach ($toolRecords as $record) {
            try {
                $tools[] = ToolSchemaConverter::toNeuronTool($record);
            } catch (\Throwable $e) {
                Log::warning("[NeuronAgentFactory] 工具转换失败: {$record->code}, {$e->getMessage()}");
            }
        }
        return $tools;
    }

    /**
     * 合并运行时参数（用户在聊天界面实时调整的 temperature/max_tokens 等）
     * 只传用户显式设置的值，其余让 Provider 官方默认值生效
     */
    private static function mergeParameters(string $driver, ?array $runtimeParams): array
    {
        if (empty($runtimeParams) || !\is_array($runtimeParams)) {
            return [];
        }

        $params = [];

        // 只允许白名单参数透传，防止注入奇怪的东西
        $allowedKeys = ['temperature', 'top_p', 'top_k', 'frequency_penalty', 'presence_penalty'];
        foreach ($allowedKeys as $key) {
            if (isset($runtimeParams[$key]) && $runtimeParams[$key] !== null) {
                $params[$key] = is_numeric($runtimeParams[$key])
                    ? (str_contains((string)$runtimeParams[$key], '.') ? (float)$runtimeParams[$key] : (int)$runtimeParams[$key])
                    : $runtimeParams[$key];
            }
        }

        $maxOutputTokens = $runtimeParams['max_output_tokens'] ?? $runtimeParams['max_tokens'] ?? null;
        if ($maxOutputTokens !== null) {
            $tokenKey = $driver === AiEnum::DRIVER_OPENAI ? 'max_output_tokens' : 'max_tokens';
            $params[$tokenKey] = max(1, (int)$maxOutputTokens);
        }

        if (!empty($runtimeParams['reasoning_effort']) && $driver === AiEnum::DRIVER_OPENAI) {
            $effort = (string)$runtimeParams['reasoning_effort'];
            if (\in_array($effort, ['low', 'medium', 'high', 'xhigh'], true)) {
                $params['reasoning'] = ['effort' => $effort];
            }
        }

        return $params;
    }

    private static function createHttpClient(?array $runtimeParams): ?GuzzleHttpClient
    {
        if (empty($runtimeParams) || !\is_array($runtimeParams)) {
            return null;
        }

        $timeout = self::floatParam($runtimeParams, 'http_timeout');
        $connectTimeout = self::floatParam($runtimeParams, 'connect_timeout');
        if ($timeout === null && $connectTimeout === null) {
            return null;
        }

        return new GuzzleHttpClient(
            timeout: self::clampFloat($timeout ?? 60.0, 1.0, 600.0),
            connectTimeout: self::clampFloat($connectTimeout ?? 10.0, 1.0, 60.0),
        );
    }

    private static function floatParam(array $params, string $key): ?float
    {
        if (!isset($params[$key]) || !is_numeric($params[$key])) {
            return null;
        }

        return (float)$params[$key];
    }

    private static function clampFloat(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }
}
