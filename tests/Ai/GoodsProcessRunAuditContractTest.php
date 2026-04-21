<?php

namespace tests\Ai;

use app\dep\Ai\AiMessagesDep;
use app\dep\Ai\AiRunsDep;
use app\enum\AiEnum;
use app\enum\CommonEnum;
use app\model\Ai\AiMessagesModel;
use app\model\Ai\AiRunsModel;
use app\queue\redis\slow\GoodsProcess;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GoodsProcessRunAuditContractTest extends TestCase
{
    private static Capsule $capsule;

    public static function setUpBeforeClass(): void
    {
        self::$capsule = new Capsule();
        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        AiRunsModel::setConnectionResolver(self::$capsule->getDatabaseManager());
        AiMessagesModel::setConnectionResolver(self::$capsule->getDatabaseManager());

        self::$capsule->schema()->create('ai_runs', function ($table) {
            $table->increments('id');
            $table->string('request_id', 64)->unique();
            $table->integer('user_id')->default(0);
            $table->integer('agent_id')->default(0);
            $table->integer('conversation_id')->default(0);
            $table->integer('user_message_id')->nullable();
            $table->integer('assistant_message_id')->nullable();
            $table->integer('run_status')->default(AiEnum::RUN_STATUS_RUNNING);
            $table->string('error_msg', 500)->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('model_snapshot', 80)->nullable();
            $table->text('meta_json')->nullable();
            $table->integer('is_del')->default(CommonEnum::NO);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        self::$capsule->schema()->create('ai_messages', function ($table) {
            $table->increments('id');
            $table->integer('conversation_id')->default(0);
            $table->integer('role');
            $table->text('content');
            $table->text('meta_json')->nullable();
            $table->integer('is_del')->default(CommonEnum::NO);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    protected function setUp(): void
    {
        self::$capsule->table('ai_runs')->truncate();
        self::$capsule->table('ai_messages')->truncate();
    }

    public function testSeedGenerateRunAuditStoresUserAndPromptMessage(): void
    {
        self::assertTrue(
            method_exists(GoodsProcess::class, 'seedGenerateRunAudit'),
            'GoodsProcess::seedGenerateRunAudit() should exist to persist the triggering user and prompt content for goods AI runs.'
        );

        $process = new GoodsProcess();
        $method = new ReflectionMethod(GoodsProcess::class, 'seedGenerateRunAudit');
        $method->setAccessible(true);

        [$runId, $userMessageId] = $method->invoke(
            $process,
            new AiRunsDep(),
            new AiMessagesDep(),
            7,
            3,
            'req-seed-1',
            'qwen-plus',
            '请根据商品信息生成口播词',
            21,
            '测试商品'
        );

        $run = AiRunsModel::query()->findOrFail($runId);
        $message = AiMessagesModel::query()->findOrFail($userMessageId);

        self::assertSame(7, (int)$run->user_id);
        self::assertSame($userMessageId, (int)$run->user_message_id);
        self::assertSame(AiEnum::ROLE_USER, (int)$message->role);
        self::assertSame('请根据商品信息生成口播词', $message->content);
        self::assertSame([
            'scene' => AiEnum::SCENE_GOODS_SCRIPT,
            'goods_id' => 21,
            'goods_title' => '测试商品',
        ], $message->meta_json);
    }

    public function testStoreGenerateAssistantMessagePersistsRunMetadata(): void
    {
        self::assertTrue(
            method_exists(GoodsProcess::class, 'storeGenerateAssistantMessage'),
            'GoodsProcess::storeGenerateAssistantMessage() should exist to keep the generated goods copy in ai_messages.'
        );

        $process = new GoodsProcess();
        $method = new ReflectionMethod(GoodsProcess::class, 'storeGenerateAssistantMessage');
        $method->setAccessible(true);

        $assistantMessageId = $method->invoke(
            $process,
            new AiMessagesDep(),
            '这是生成好的口播词',
            'req-assistant-1',
            21,
            '测试商品'
        );

        $message = AiMessagesModel::query()->findOrFail($assistantMessageId);

        self::assertSame(AiEnum::ROLE_ASSISTANT, (int)$message->role);
        self::assertSame('这是生成好的口播词', $message->content);
        self::assertSame([
            'scene' => AiEnum::SCENE_GOODS_SCRIPT,
            'goods_id' => 21,
            'goods_title' => '测试商品',
            'run_request_id' => 'req-assistant-1',
        ], $message->meta_json);
    }

    public function testGoodsGenerateQueueCarriesUserIdAndRunSuccessPersistsAssistantMessageId(): void
    {
        $goodsModuleContent = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Ai/GoodsModule.php');
        $goodsProcessContent = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/queue/redis/slow/GoodsProcess.php');

        self::assertNotFalse($goodsModuleContent);
        self::assertNotFalse($goodsProcessContent);

        $normalizedModule = preg_replace('/\s+/', ' ', $goodsModuleContent);
        $normalizedProcess = preg_replace('/\s+/', ' ', $goodsProcessContent);

        self::assertStringContainsString("'user_id' => (int)\$request->userId", $normalizedModule);
        self::assertStringContainsString("'assistant_message_id' => \$assistantMessageId", $normalizedProcess);
    }
}
