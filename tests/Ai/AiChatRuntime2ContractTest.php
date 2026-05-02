<?php

namespace tests\Ai;

use app\service\Ai\AiImageIntentDetector;
use app\service\Ai\AiRunEventPublisher;
use PHPUnit\Framework\TestCase;

class AiChatRuntime2ContractTest extends TestCase
{
    public function testRunEventPublisherUsesRedisStreamWithRunScopedKeys(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiRunEventPublisher.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('class AiRunEventPublisher', $content);
        self::assertStringContainsString("KEY_PREFIX = 'ai:run:'", $content);
        self::assertStringContainsString("KEY_SUFFIX = ':events'", $content);
        self::assertStringContainsString('xAdd', $content);
        self::assertStringContainsString('xRead', $content);
        self::assertStringContainsString('expire', $content);

        self::assertSame('ai:run:{123}:events', (new AiRunEventPublisher())->key(123));
    }

    public function testImageIntentDetectorRecognizesCommandAndChineseNaturalLanguage(): void
    {
        $detector = new AiImageIntentDetector();

        self::assertSame(
            ['prompt' => '一只穿西装的猫', 'source' => 'command'],
            $detector->detect('/生成图片：一只穿西装的猫')
        );

        self::assertSame(
            'natural_language',
            $detector->detect('帮我画一张电影感封面图')['source'] ?? null
        );

        self::assertNull($detector->detect('今天帮我总结一下知识库内容'));
    }

    public function testStreamModuleQueuesRunInsteadOfExecutingModelDirectly(): void
    {
        $path = dirname(__DIR__, 2) . '/app/module/Ai/AiChatModule.php';
        $content = file_get_contents($path);

        self::assertStringContainsString("RedisQueue::send('ai_run_execute'", $content);
        self::assertStringContainsString('use Webman\RedisQueue\Redis as RedisQueue;', $content);
        self::assertStringNotContainsString('use Webman\RedisQueue\Client as RedisQueue;', $content);
        self::assertStringContainsString('public function startStream', $content);
        self::assertStringContainsString('public function events', $content);
        self::assertStringContainsString('relayRunEvents', $content);
        self::assertStringContainsString('AI 运行投递失败', $content);
        self::assertStringContainsString('AI 队列未开始执行', $content);
        self::assertStringContainsString('markFailed($runId', $content);

        $sendStreamStart = strpos($content, 'public function sendStream');
        $privateStart = strpos($content, '// ==================== 私有方法', $sendStreamStart);
        $sendStreamBody = substr($content, $sendStreamStart, $privateStart - $sendStreamStart);
        self::assertStringNotContainsString('AiChatService::chatStream(', $sendStreamBody);
    }

    public function testStreamableRoutesAvoidLongHeldSseAsTheOnlyChatTransport(): void
    {
        $routePath = dirname(__DIR__, 2) . '/routes/admin.php';
        $apiPath = dirname(__DIR__, 3) . '/admin_front_ts/src/api/ai/chat.ts';

        $routes = file_get_contents($routePath);
        self::assertStringContainsString("/AiChat/start", $routes);
        self::assertStringContainsString("/AiChat/events", $routes);

        if (!is_file($apiPath)) {
            self::markTestSkipped('Frontend sibling repository is not available.');
        }

        $api = file_get_contents($apiPath);
        self::assertStringContainsString('/api/admin/AiChat/start', $api);
        self::assertStringContainsString('/api/admin/AiChat/events', $api);
        self::assertStringContainsString('streamByPolling', $api);
        self::assertStringNotContainsString("streamPost('/api/admin/AiChat/stream'", $api);
    }

    public function testWindowsSlowQueueHasMultipleIndependentConsumers(): void
    {
        $path = dirname(__DIR__, 2) . '/config/plugin/webman/redis-queue/process.php';
        $content = file_get_contents($path);

        self::assertStringContainsString('consumer_slow_2', $content);
        self::assertStringContainsString('consumer_slow_3', $content);
        self::assertStringContainsString('consumer_slow_4', $content);
        self::assertStringContainsString("DIRECTORY_SEPARATOR === '\\\\'", $content);
    }

    public function testQueueConsumerDelegatesToRunExecutor(): void
    {
        $path = dirname(__DIR__, 2) . '/app/queue/redis/slow/AiRunExecute.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString("public \$queue = 'ai_run_execute'", $content);
        self::assertStringContainsString('AiChatRunExecutor', $content);
        self::assertStringContainsString("execute((int)\$data['run_id'])", $content);
        self::assertStringContainsString('onConsumeFailure', $content);
        self::assertStringContainsString('AiRunEventPublisher', $content);
    }

    public function testRunExecutorPublishesTextAndImageEvents(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiChatRunExecutor.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('class AiChatRunExecutor', $content);
        self::assertStringContainsString('AiChatService::chatStream(', $content);
        self::assertStringContainsString("publish(\$runId, 'content'", $content);
        self::assertStringContainsString('AiImageIntentDetector', $content);
        self::assertStringContainsString('AiImageGenerationService', $content);
        self::assertStringContainsString("'run_started'", $content);
        self::assertStringContainsString("publish(\$runId, 'done'", $content);
        self::assertStringContainsString("publish(\$runId, 'image_done'", $content);
    }

    public function testImageGenerationServicePersistsAiChatImageBlock(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiImageGenerationService.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('/images/generations', $content);
        self::assertStringContainsString('FOLDER_AI_CHAT_IMAGES', $content);
        self::assertStringContainsString("'type' => 'image'", $content);
        self::assertStringContainsString("'url' =>", $content);
        self::assertStringContainsString('b64_json', $content);
    }

    public function testFrontendMessageBlocksAreTypedAndRendered(): void
    {
        $typesPath = dirname(__DIR__, 3) . '/admin_front_ts/src/views/Main/ai/chat/composables/types.ts';
        $listPath = dirname(__DIR__, 3) . '/admin_front_ts/src/views/Main/ai/chat/components/MessageList/index.vue';

        if (!is_file($typesPath) || !is_file($listPath)) {
            self::markTestSkipped('Frontend sibling repository is not available.');
        }

        $types = file_get_contents($typesPath);
        $list = file_get_contents($listPath);

        self::assertStringContainsString('MessageBlock', $types);
        self::assertStringContainsString("type: 'image'", $types);
        self::assertStringContainsString('getMessageBlocks', $list);
        self::assertStringContainsString("block.type === 'image'", $list);
    }
}
