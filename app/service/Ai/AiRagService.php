<?php

namespace app\service\Ai;

use app\dep\Ai\AiKnowledgeChunksDep;

class AiRagService
{
    private const CHINESE_STOP_WORDS = [
        '为什么', '是什么', '是不是', '怎么样', '怎么办', '如何', '哪些', '哪个', '什么',
        '问题', '原因', '情况',
        '不应该', '应该', '需要', '可以', '不能', '只有', '一个', '一种',
        '这个', '那个', '以及', '还是', '或者', '并且', '如果', '那么',
        '要做', '怎么', '多少', '的是', '要', '做', '的', '了', '吗', '呢', '啊', '吧',
    ];

    public static function chunkText(string $text, int $chunkSize = 800, int $overlap = 120): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $chunkSize = max(1, $chunkSize);
        $overlap = max(0, min($overlap, $chunkSize - 1));
        $step = max(1, $chunkSize - $overlap);
        $length = mb_strlen($text);

        if ($length <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $lastEnd = 0;
        for ($offset = 0; $offset + $chunkSize <= $length; $offset += $step) {
            $chunk = trim(mb_substr($text, $offset, $chunkSize));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $lastEnd = $offset + $chunkSize;
        }

        if ($lastEnd < $length) {
            $tailOffset = max(0, $length - $chunkSize);
            $tail = trim(mb_substr($text, $tailOffset, $chunkSize));
            if ($tail !== '' && end($chunks) !== $tail) {
                $chunks[] = $tail;
            }
        }

        return $chunks;
    }

    public static function keywordScore(string $content, string $query): float
    {
        $content = trim($content);
        $terms = self::queryTerms($query);
        if ($content === '' || empty($terms)) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($terms as $term) {
            $count = self::substrCountInsensitive($content, $term);
            if ($count <= 0) {
                continue;
            }
            $score += $count;
            if (mb_strlen($term) >= 2) {
                $score += 0.25;
            }
        }

        return $score;
    }

    public static function buildContextPrompt(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $lines = [
            '以下是可参考的知识库片段。回答时优先依据这些片段；如果片段不足以回答，请明确说明不确定，不要编造。',
        ];

        foreach (array_values($chunks) as $index => $chunk) {
            $title = trim((string)($chunk['document_title'] ?? '未命名文档'));
            $chunkNo = (int)($chunk['chunk_no'] ?? ($index + 1));
            $score = isset($chunk['score']) ? '，score=' . round((float)$chunk['score'], 2) : '';
            $content = trim((string)($chunk['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $lines[] = '';
            $lines[] = '[' . ($index + 1) . "] 来源：{$title} #{$chunkNo}{$score}";
            $lines[] = $content;
        }

        return trim(implode("\n", $lines));
    }

    public static function buildAugmentedSystemPrompt(string $basePrompt, array $chunks): string
    {
        $contextPrompt = self::buildContextPrompt($chunks);
        if ($contextPrompt === '') {
            return trim($basePrompt);
        }

        return trim(trim($basePrompt) . "\n\n" . $contextPrompt);
    }

    public static function retrieveFromKnowledgeBases(array $knowledgeBaseIds, string $query, int $topK = 5, float $scoreThreshold = 0.0): array
    {
        $knowledgeBaseIds = array_values(array_unique(array_filter(array_map('intval', $knowledgeBaseIds))));
        if (empty($knowledgeBaseIds) || trim($query) === '') {
            return [];
        }

        $topK = max(1, min($topK, 20));
        $candidates = (new AiKnowledgeChunksDep())->getCandidateChunks($knowledgeBaseIds, self::queryTerms($query), 300);

        $ranked = [];
        foreach ($candidates as $candidate) {
            $score = self::keywordScore((string)$candidate->content, $query);
            if ($score < $scoreThreshold) {
                continue;
            }

            $ranked[] = [
                'knowledge_base_id' => (int)$candidate->knowledge_base_id,
                'document_id' => (int)$candidate->document_id,
                'document_title' => (string)($candidate->document_title ?? '未命名文档'),
                'chunk_no' => (int)$candidate->chunk_no,
                'content' => (string)$candidate->content,
                'score' => $score,
            ];
        }

        usort($ranked, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($ranked, 0, $topK);
    }

    public static function tokenEstimate(string $text): int
    {
        return max(1, (int)ceil(mb_strlen($text) / 2));
    }

    /**
     * @return array<int, string>
     */
    public static function queryTerms(string $query): array
    {
        $query = mb_strtolower(trim($query));
        $terms = [];

        preg_match_all('/[A-Za-z0-9_]+/u', $query, $latinMatches);
        foreach ($latinMatches[0] ?? [] as $term) {
            if (self::isUsefulQueryTerm($term)) {
                $terms[] = $term;
            }
        }

        preg_match_all('/[\p{Han}]+/u', $query, $chineseMatches);
        foreach ($chineseMatches[0] ?? [] as $term) {
            array_push($terms, ...self::chineseQueryTerms($term));
        }

        return array_values(array_unique(array_filter(
            array_map('trim', $terms),
            static fn(string $term) => self::isUsefulQueryTerm($term)
        )));
    }

    /**
     * MySQL keyword fallback has no real Chinese segmenter. Keep this deliberately
     * simple: remove common question glue, then emit longer n-grams before shorter
     * ones so candidate filtering still finds business terms like "知识库" and "权限控制".
     *
     * @return array<int, string>
     */
    private static function chineseQueryTerms(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $terms = [];

        if (mb_strlen($text) <= 12) {
            $terms[] = $text;
        }

        $cleaned = str_replace(self::CHINESE_STOP_WORDS, '', $text);
        if ($cleaned === '') {
            return $terms;
        }

        if ($cleaned !== $text) {
            $terms[] = $cleaned;
        }

        $length = mb_strlen($cleaned);
        foreach ([4, 3, 2] as $size) {
            if ($length < $size) {
                continue;
            }

            for ($offset = 0; $offset <= $length - $size; $offset++) {
                $terms[] = mb_substr($cleaned, $offset, $size);
            }
        }

        return $terms;
    }

    private static function isUsefulQueryTerm(string $term): bool
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return false;
        }

        return !in_array($term, self::CHINESE_STOP_WORDS, true);
    }

    private static function substrCountInsensitive(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }

        $haystack = mb_strtolower($haystack);
        $needle = mb_strtolower($needle);
        $count = 0;
        $offset = 0;
        $needleLength = mb_strlen($needle);

        while (($position = mb_strpos($haystack, $needle, $offset)) !== false) {
            $count++;
            $offset = $position + $needleLength;
        }

        return $count;
    }
}
