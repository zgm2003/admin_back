<?php

namespace tests\Ai;

use app\service\Ai\CineGenerationService;
use PHPUnit\Framework\TestCase;

class CineGenerationServiceTest extends TestCase
{
    public function testBuildGenerationPromptContainsOperationalJsonContract(): void
    {
        $service = new CineGenerationService();

        $prompt = $service->buildGenerationPrompt([
            'title' => '雨夜短信',
            'source_text' => '她在雨夜收到一条来自三年前自己的短信。',
            'style' => '电影感悬疑，冷色调',
            'duration_seconds' => 30,
            'aspect_ratio' => '9:16',
        ]);

        self::assertStringContainsString('只输出 JSON', $prompt);
        self::assertStringContainsString('"draft"', $prompt);
        self::assertStringContainsString('"shotlist"', $prompt);
        self::assertStringNotContainsString('"feed_pack"', $prompt);
        self::assertStringContainsString('当前阶段只生成草稿和分镜脚本', $prompt);
        self::assertStringContainsString('Codex 不生成最终 MP4', $prompt);
        self::assertStringContainsString('她在雨夜收到一条来自三年前自己的短信。', $prompt);
    }

    public function testParseModelContentExtractsFencedJsonAndNormalizesProjectPayload(): void
    {
        $service = new CineGenerationService();
        $content = <<<'TEXT'
```json
{
  "draft": {
    "logline": "女主收到来自过去的短信。",
    "story_flow": ["收到短信", "意识到时间错位"],
    "concept": "30 秒竖屏悬疑短片"
  },
  "shotlist": [
    {
      "shot_id": "S01",
      "duration_seconds": 4,
      "scene": "雨夜楼下",
      "subject": "女主",
      "action": "低头看手机",
      "image_prompt": "cinematic rainy night keyframe"
    }
  ],
  "feed_pack": [
    {
      "segment": "S01",
      "duration_seconds": 4,
      "upload_images": ["storyboard-images/S01.png"],
      "prompt": "保持同一张脸，雨夜，缓慢推近。"
    }
  ]
}
```
TEXT;

        $parsed = $service->parseModelContent($content);

        self::assertSame('女主收到来自过去的短信。', $parsed['draft']['logline']);
        self::assertSame('S01', $parsed['shotlist'][0]['shot_id']);
        self::assertSame(['storyboard-images/S01.png'], $parsed['feed_pack'][0]['upload_images']);
        self::assertSame($content, $parsed['model_origin']);
    }

    public function testParseModelContentBuildsImageQueueAndVideoPromptFromDraftOnlyPayload(): void
    {
        $service = new CineGenerationService();

        $parsed = $service->parseModelContent(json_encode([
            'draft' => [
                'logline' => '外卖员误入不存在的楼层。',
                'story_flow' => ['进入医院', '电梯停在 13 楼', '发现红色弹珠'],
            ],
            'shotlist' => [
                [
                    'shot_id' => 'S01',
                    'duration_seconds' => 6,
                    'scene' => '废弃医院门口',
                    'subject' => '外卖员',
                    'action' => '抬头确认门牌',
                    'shot_size' => '中近景',
                    'lighting' => '冷色雨夜光',
                    'image_prompt' => '废弃医院门口，外卖员抬头，冷色雨夜，静态分镜图片',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE), [
            'duration_seconds' => 30,
            'aspect_ratio' => '9:16',
            'style' => '电影感悬疑',
        ]);

        self::assertSame('storyboard-images/S01.png', $parsed['image_queue'][0]['filename']);
        self::assertSame(['storyboard-images/S01.png'], $parsed['feed_pack'][0]['upload_images']);
        self::assertStringContainsString('FORMAT: 9:16', $parsed['feed_pack'][0]['prompt']);
        self::assertStringContainsString('生成分镜', $parsed['continuity_review']['handoff_notes'][0]);
        self::assertStringContainsString('## 分镜脚本', $parsed['deliverable_markdown']);
    }

    public function testParseModelContentFallsBackToReadableDraftWhenModelDoesNotReturnJson(): void
    {
        $service = new CineGenerationService();

        $parsed = $service->parseModelContent('这是模型返回的一段普通文本。');

        self::assertSame('这是模型返回的一段普通文本。', $parsed['draft']['summary']);
        self::assertSame([], $parsed['shotlist']);
        self::assertSame([], $parsed['feed_pack']);
    }
}
