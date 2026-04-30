<?php

namespace app\queue\redis\slow;

use app\dep\Ai\CineAssetDep;
use app\dep\Ai\CineProjectDep;
use app\service\Ai\CineImageGenerationService;
use Webman\RedisQueue\Consumer;

class CineImageProcess implements Consumer
{
    public $queue = 'cine_image_process';
    public $connection = 'default';

    public function consume($data)
    {
        $projectId = (int)($data['id'] ?? 0);
        $assetIds = \is_array($data['asset_ids'] ?? null) ? $data['asset_ids'] : [];
        $projectDep = new CineProjectDep();
        $assetDep = new CineAssetDep();

        $project = $projectDep->getOrFail($projectId);
        $assets = $assetDep->getGenerateTargetsByProjectId($projectId, $assetIds);
        if ($assets->isEmpty()) {
            $projectDep->markCompleted($projectId);
            $this->log('短剧分镜无待生成图片', ['id' => $projectId]);
            return;
        }

        $this->log('开始生成短剧分镜图片', ['id' => $projectId, 'count' => $assets->count()]);

        try {
            $service = new CineImageGenerationService();
            foreach ($assets as $asset) {
                $this->generateAsset($service, $assetDep, $project, $asset);
            }

            $projectDep->markCompleted($projectId);
            $this->log('短剧分镜生成完成', ['id' => $projectId]);
        } catch (\Throwable $e) {
            $projectDep->markFailed($projectId, '分镜生成失败: ' . $e->getMessage());
            $this->log('短剧分镜生成失败', ['id' => $projectId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function generateAsset(CineImageGenerationService $service, CineAssetDep $assetDep, $project, $asset): void
    {
        $assetId = (int)$asset->id;
        $meta = \is_array($asset->meta_json) ? $asset->meta_json : [];
        $assetDep->markGenerating($assetId);

        try {
            $result = $service->generateKeyframe([
                'project_id' => (int)$project->id,
                'asset_id' => $assetId,
                'shot_id' => (string)$asset->shot_id,
                'image_prompt' => (string)$asset->prompt,
                'aspect_ratio' => (string)$project->aspect_ratio,
                'style' => (string)$project->style,
                'continuity_anchor' => $this->buildContinuityAnchor($project, $asset),
                'reference_images' => $this->normalizeReferenceImages($project->reference_images_json),
            ]);

            $assetDep->markReady($assetId, (string)($result['file_url'] ?? ''), array_merge($meta, [
                'generated_at' => date('Y-m-d H:i:s'),
                'model_code' => $result['model_code'] ?? null,
                'agent_id' => $result['agent_id'] ?? null,
                'upload' => $result['upload'] ?? null,
                'revised_prompt' => $result['revised_prompt'] ?? null,
            ]));
        } catch (\Throwable $e) {
            $assetDep->markFailed($assetId, $e->getMessage(), array_merge($meta, [
                'failed_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    private function buildContinuityAnchor($project, $asset): string
    {
        $parts = [];
        $draft = \is_array($project->draft_json) ? $project->draft_json : [];
        foreach (($draft['character_anchors'] ?? []) as $anchor) {
            $parts[] = (string)$anchor;
        }
        foreach (($draft['continuity_notes'] ?? []) as $note) {
            $parts[] = (string)$note;
        }

        $shotlist = \is_array($project->shotlist_json) ? $project->shotlist_json : [];
        foreach ($shotlist as $shot) {
            if (!\is_array($shot) || (string)($shot['shot_id'] ?? '') !== (string)$asset->shot_id) {
                continue;
            }
            foreach (['subject', 'scene', 'composition', 'lighting', 'continuity_from_previous'] as $field) {
                if (!empty($shot[$field])) {
                    $parts[] = (string)$shot[$field];
                }
            }
            break;
        }

        return mb_substr(implode('；', array_filter($parts)), 0, 1000);
    }

    private function normalizeReferenceImages($referenceImages): array
    {
        if (!\is_array($referenceImages)) {
            return [];
        }

        $urls = [];
        foreach ($referenceImages as $item) {
            if (\is_string($item)) {
                $urls[] = $item;
                continue;
            }
            if (\is_array($item) && !empty($item['url'])) {
                $urls[] = (string)$item['url'];
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    public function onConsumeFailure(\Throwable $e, $package)
    {
        $data = $package['data'] ?? [];
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            (new CineProjectDep())->markFailed($id, '分镜最终失败: ' . $e->getMessage());
        }
        $this->log('短剧分镜队列消费最终失败', ['id' => $id, 'error' => $e->getMessage()]);
    }

    private function log(string $msg, array $context = []): void
    {
        if (!function_exists('log_daily')) {
            return;
        }

        log_daily("queue_{$this->queue}")->info($msg, $context);
    }
}
