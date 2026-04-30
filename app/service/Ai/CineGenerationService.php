<?php

namespace app\service\Ai;

use app\enum\CineEnum;
use app\enum\CommonEnum;

/**
 * AI 短剧生成服务
 *
 * 只负责提示词契约和模型输出归一化，不负责 HTTP、队列、数据库写入。
 */
class CineGenerationService
{
    /**
     * 构建给文本主控模型的生成提示词。
     */
    public function buildGenerationPrompt(array $project): string
    {
        $title = trim((string)($project['title'] ?? '未命名短剧'));
        $sourceText = trim((string)($project['source_text'] ?? ''));
        $style = trim((string)($project['style'] ?? '电影感，克制表演'));
        $durationSeconds = (int)($project['duration_seconds'] ?? 30);
        $aspectRatio = trim((string)($project['aspect_ratio'] ?? '9:16'));

        return implode("\n", [
            '你是短剧导演。请把用户素材改成一个可审查的短剧草稿。',
            '',
            '硬规则：',
            '- 只输出 JSON，不要 Markdown，不要解释。',
            '- 当前阶段只生成草稿和分镜脚本，不要调用工具；分镜图片由用户点击“生成分镜”后执行。',
            '- Codex 不生成最终 MP4；最终视频由外部视频工具合成。',
            '- 最多 5 个镜头，总时长尽量接近目标时长。',
            '- 每个镜头必须可拍：主体、动作、表演、景别、运镜、光影、连续性都要明确。',
            '- image_prompt 只描述静态分镜图片，不要写视频运动。',
            '- 每个字段只写一句短句，禁止长段落，避免 JSON 被截断。',
            '',
            '输出 JSON 结构必须是：',
            '{',
            '  "draft": {',
            '    "logline": "一句话成片预览",',
            '    "story_flow": ["开场", "异常出现", "推进", "收束"],',
            '    "concept": "短片方案",',
            '    "character_anchors": ["人物脸型/发型/服装/道具锚点"],',
            '    "continuity_notes": ["人物/服装/道具/光影连续性"]',
            '  },',
            '  "shotlist": [',
            '    {',
            '      "shot_id": "S01",',
            '      "duration_seconds": 3,',
            '      "scene": "场景",',
            '      "subject": "主体",',
            '      "action": "可见动作",',
            '      "performance_detail": "微表演",',
            '      "shot_size": "景别",',
            '      "camera_movement": "运镜",',
            '      "composition": "构图",',
            '      "lighting": "光影/美术",',
            '      "dialogue_or_voiceover": "对白或旁白，可为空",',
            '      "image_prompt": "静态分镜图片提示词",',
            '      "continuity_from_previous": "与上一镜头连续性",',
            '      "video_prompt_note": "外部视频工具这一镜头的运动/转场要点"',
            '    }',
            '  ]',
            '}',
            '',
            "项目标题：{$title}",
            "目标时长：{$durationSeconds} 秒",
            "画幅：{$aspectRatio}",
            "风格：{$style}",
            '',
            '原始素材：',
            $sourceText,
        ]);
    }

    /**
     * 将模型输出归一化为项目可存储结构。
     *
     * @return array{deliverable_markdown: string, draft: array, shotlist: array, feed_pack: array, image_queue: array, continuity_review: array, model_origin: string}
     */
    public function parseModelContent(string $content, array $project = []): array
    {
        $payload = $this->decodeJsonPayload($content);
        if (!\is_array($payload)) {
            return [
                'deliverable_markdown' => trim($content),
                'draft' => ['summary' => trim($content)],
                'shotlist' => [],
                'feed_pack' => [],
                'image_queue' => [],
                'continuity_review' => [],
                'model_origin' => $content,
            ];
        }

        $draft = \is_array($payload['draft'] ?? null) ? $payload['draft'] : [];
        $shotlist = $this->normalizeShotlist(
            \is_array($payload['shotlist'] ?? null) ? array_values($payload['shotlist']) : [],
            $project
        );

        $feedPack = \is_array($payload['feed_pack'] ?? null) ? array_values($payload['feed_pack']) : [];
        if (empty($feedPack)) {
            $feedPack = $this->buildFeedPack($shotlist, $project);
        }

        $imageQueue = \is_array($payload['image_queue'] ?? null) ? array_values($payload['image_queue']) : [];
        if (empty($imageQueue)) {
            $imageQueue = $this->buildImageQueue($shotlist);
        }

        $continuityReview = \is_array($payload['continuity_review'] ?? null)
            ? $payload['continuity_review']
            : $this->buildContinuityReview();

        return [
            'deliverable_markdown' => trim((string)($payload['deliverable_markdown'] ?? '')) ?: $this->buildDeliverableMarkdown($draft, $shotlist, $feedPack, $continuityReview),
            'draft' => $draft,
            'shotlist' => $shotlist,
            'feed_pack' => $feedPack,
            'image_queue' => $imageQueue,
            'continuity_review' => $continuityReview,
            'model_origin' => $content,
        ];
    }

    /**
     * 根据分镜生成可落库的图片资产队列。
     */
    public function buildKeyframeAssets(int $projectId, array $shotlist): array
    {
        $assets = [];
        foreach ($shotlist as $index => $shot) {
            if (!\is_array($shot)) {
                continue;
            }

            $prompt = trim((string)($shot['image_prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }

            $shotId = trim((string)($shot['shot_id'] ?? sprintf('S%02d', $index + 1)));
            $assets[] = [
                'project_id' => $projectId,
                'asset_type' => CineEnum::ASSET_TYPE_KEYFRAME,
                'shot_id' => $shotId,
                'prompt' => $prompt,
                'file_url' => null,
                'status' => CineEnum::ASSET_STATUS_PENDING,
                'status_msg' => null,
                'sort' => $index + 1,
                'meta_json' => [
                    'filename' => "storyboard-images/{$shotId}.png",
                    'source' => 'shotlist.image_prompt',
                ],
                'is_del' => CommonEnum::NO,
            ];
        }

        return $assets;
    }

    private function normalizeShotlist(array $shotlist, array $project): array
    {
        $durationSeconds = max(1, (int)($project['duration_seconds'] ?? 30));
        $fallbackDuration = max(1, (int)floor($durationSeconds / max(1, min(5, count($shotlist) ?: 5))));
        $style = trim((string)($project['style'] ?? '电影感，克制表演'));

        $normalized = [];
        foreach (array_slice($shotlist, 0, 5) as $index => $shot) {
            if (!\is_array($shot)) {
                continue;
            }

            $shotId = trim((string)($shot['shot_id'] ?? ''));
            if ($shotId === '') {
                $shotId = sprintf('S%02d', $index + 1);
            }

            $item = $shot;
            $item['shot_id'] = $shotId;
            $item['duration_seconds'] = max(1, (int)($shot['duration_seconds'] ?? $fallbackDuration));

            foreach ([
                'scene', 'subject', 'action', 'performance_detail', 'shot_size',
                'camera_movement', 'composition', 'lighting', 'dialogue_or_voiceover',
                'image_prompt', 'continuity_from_previous', 'video_prompt_note',
            ] as $field) {
                $item[$field] = trim((string)($shot[$field] ?? ''));
            }

            if ($item['image_prompt'] === '') {
                $item['image_prompt'] = trim(implode('，', array_filter([
                    $item['scene'],
                    $item['subject'],
                    $item['action'],
                    $item['shot_size'],
                    $item['lighting'],
                    $style,
                    '静态分镜图片',
                    'no watermark',
                ])));
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function buildImageQueue(array $shotlist): array
    {
        return array_values(array_map(static function (array $shot): array {
            $shotId = (string)($shot['shot_id'] ?? '');
            return [
                'shot_id' => $shotId,
                'filename' => "storyboard-images/{$shotId}.png",
                'prompt' => (string)($shot['image_prompt'] ?? ''),
            ];
        }, $shotlist));
    }

    private function buildFeedPack(array $shotlist, array $project): array
    {
        if (empty($shotlist)) {
            return [];
        }

        $segments = [];
        $current = [];
        $currentDuration = 0;

        foreach ($shotlist as $shot) {
            $duration = max(1, (int)($shot['duration_seconds'] ?? 1));
            if (!empty($current) && $currentDuration + $duration > 15) {
                $segments[] = $this->buildFeedSegment($current, $project);
                $current = [];
                $currentDuration = 0;
            }

            $current[] = $shot;
            $currentDuration += $duration;
        }

        if (!empty($current)) {
            $segments[] = $this->buildFeedSegment($current, $project);
        }

        return $segments;
    }

    private function buildFeedSegment(array $shots, array $project): array
    {
        $ids = array_values(array_map(static fn(array $shot): string => (string)($shot['shot_id'] ?? ''), $shots));
        $ids = array_values(array_filter($ids, static fn(string $id): bool => $id !== ''));
        $duration = array_sum(array_map(static fn(array $shot): int => max(1, (int)($shot['duration_seconds'] ?? 1)), $shots));
        $aspectRatio = trim((string)($project['aspect_ratio'] ?? '9:16'));
        $style = trim((string)($project['style'] ?? '电影感，克制表演'));

        $timeline = [];
        foreach ($shots as $shot) {
            $timeline[] = sprintf(
                '%s %ss：%s；%s；%s',
                (string)($shot['shot_id'] ?? '-'),
                (string)($shot['duration_seconds'] ?? '-'),
                (string)($shot['action'] ?? ''),
                (string)($shot['camera_movement'] ?? ''),
                (string)($shot['video_prompt_note'] ?? '')
            );
        }

        $segment = count($ids) <= 1 ? ($ids[0] ?? 'S01') : ($ids[0] . '-' . $ids[count($ids) - 1]);

        return [
            'segment' => $segment,
            'duration_seconds' => $duration,
            'upload_images' => array_map(static fn(string $id): string => "storyboard-images/{$id}.png", $ids),
            'prompt' => implode("\n", [
                "FORMAT: {$aspectRatio}，{$duration} 秒，{$style}",
                '上传上述分镜图，按顺序生成短剧片段；锁定同一人物、服装、道具和场景连续性。',
                '时间线：' . implode(' / ', $timeline),
                '禁止：字幕、水印、logo、额外人物、面部漂移、服装突变、无关转场。',
            ]),
        ];
    }

    private function buildContinuityReview(): array
    {
        return [
            'blockers' => [],
            'warnings' => [],
            'handoff_notes' => [
                '草稿通过审查后再点击“生成分镜”生成静态分镜图片。',
                '最终视频合成在外部视频工具完成。',
            ],
        ];
    }

    private function decodeJsonPayload(string $content): mixed
    {
        $text = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first === false || $last === false || $last <= $first) {
            return null;
        }

        $decoded = json_decode(substr($text, $first, $last - $first + 1), true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function buildDeliverableMarkdown(array $draft, array $shotlist, array $feedPack, array $continuityReview): string
    {
        $lines = [];
        $lines[] = '# AI短剧草稿';
        $lines[] = '';
        $lines[] = '## 成片预览';
        $lines[] = (string)($draft['logline'] ?? $draft['concept'] ?? '模型未返回成片预览。');
        $lines[] = '';
        $lines[] = '## 故事全流程';
        foreach (($draft['story_flow'] ?? []) as $beat) {
            $lines[] = '- ' . (string)$beat;
        }
        $lines[] = '';
        $lines[] = '## 分镜脚本';
        foreach ($shotlist as $shot) {
            if (!\is_array($shot)) {
                continue;
            }
            $lines[] = sprintf(
                '- %s｜%ss｜%s｜%s｜%s',
                (string)($shot['shot_id'] ?? '-'),
                (string)($shot['duration_seconds'] ?? '-'),
                (string)($shot['shot_size'] ?? '-'),
                (string)($shot['action'] ?? '-'),
                (string)($shot['continuity_from_previous'] ?? '-')
            );
        }
        $lines[] = '';
        $lines[] = '## 视频生成提示词';
        foreach ($feedPack as $pack) {
            if (!\is_array($pack)) {
                continue;
            }
            $lines[] = '### ' . (string)($pack['segment'] ?? '片段');
            $images = $pack['upload_images'] ?? [];
            $lines[] = '上传图片：' . (empty($images) ? '无' : implode('、', array_map('strval', $images)));
            $lines[] = '复制提示词：';
            $lines[] = (string)($pack['prompt'] ?? '');
            $lines[] = '';
        }
        $lines[] = '## 连续性审查';
        foreach (($continuityReview['blockers'] ?? []) as $item) {
            $lines[] = '- 阻断：' . (string)$item;
        }
        foreach (($continuityReview['warnings'] ?? []) as $item) {
            $lines[] = '- 提醒：' . (string)$item;
        }
        foreach (($continuityReview['handoff_notes'] ?? []) as $item) {
            $lines[] = '- 交付：' . (string)$item;
        }

        return trim(implode("\n", $lines));
    }
}
