<?php

namespace tests\Ai;

use app\enum\AiEnum;
use app\lib\Ai\ToolSchemaConverter;
use app\lib\Ai\ToolExecutor;
use NeuronAI\Providers\OpenAI\Responses\ToolMapper;
use PHPUnit\Framework\TestCase;

class CineToolContractTest extends TestCase
{
    public function testCineKeyframeToolPreparesImageRequestPackage(): void
    {
        $record = (object)[
            'code' => 'cine_generate_keyframe',
            'executor_type' => AiEnum::EXECUTOR_INTERNAL,
            'executor_config' => [],
        ];

        $result = ToolExecutor::execute($record, [
            'shot_id' => 'S01',
            'image_prompt' => '雨夜街口，女主低头看手机，电影感冷色调，静态分镜图片',
            'aspect_ratio' => '9:16',
            'style' => '电影感悬疑',
            'continuity_anchor' => '同一件米色风衣，同一部黑色手机',
            'reference_images' => ['https://example.test/ref.png'],
            'dry_run' => true,
        ]);

        $payload = json_decode($result, true);

        self::assertSame('prepared', $payload['status']);
        self::assertSame('cine_keyframe', $payload['model_scene']);
        self::assertSame('gpt-image-2', $payload['model_code']);
        self::assertSame('S01', $payload['shot_id']);
        self::assertSame('9:16', $payload['request']['aspect_ratio']);
        self::assertStringContainsString('静态分镜图片', $payload['request']['prompt']);
        self::assertStringContainsString('no watermark', $payload['request']['prompt']);
    }

    public function testCineMigrationRegistersKeyframeTool(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260430_add_ai_cine_factory.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('cine_generate_keyframe', $content);
        self::assertStringContainsString('短剧分镜图片生成', $content);
        self::assertStringContainsString('gpt-image-2', $content);
        self::assertStringContainsString('cine_keyframe', $content);
    }

    public function testCineKeyframeToolArraySchemaIncludesItemsForOpenAIResponses(): void
    {
        $record = (object)[
            'code' => 'cine_generate_keyframe',
            'name' => '短剧分镜图片生成',
            'description' => '把短剧分镜 image_prompt 交给图片模型生成静态分镜图片。',
            'schema_json' => [
                'properties' => [
                    'image_prompt' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => '静态分镜图片提示词',
                    ],
                    'reference_images' => [
                        'type' => 'array',
                        'required' => false,
                        'description' => '参考图 URL 列表',
                    ],
                ],
            ],
        ];

        $tool = ToolSchemaConverter::toNeuronTool($record);
        $payload = (new ToolMapper())->map([$tool])[0];

        self::assertSame('array', $payload['parameters']['properties']['reference_images']['type']);
        self::assertSame(
            ['type' => 'string'],
            $payload['parameters']['properties']['reference_images']['items']
        );
    }
}
