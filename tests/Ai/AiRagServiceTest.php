<?php

namespace tests\Ai;

use app\service\Ai\AiRagService;
use PHPUnit\Framework\TestCase;

class AiRagServiceTest extends TestCase
{
    public function testKnowledgeBaseMigrationDefinesCoreTablesMenuAndPermissionReserveFields(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260501_add_ai_knowledge_base.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        foreach ([
            'CREATE TABLE IF NOT EXISTS `ai_knowledge_bases`',
            'CREATE TABLE IF NOT EXISTS `ai_knowledge_documents`',
            'CREATE TABLE IF NOT EXISTS `ai_knowledge_chunks`',
            'CREATE TABLE IF NOT EXISTS `ai_agent_knowledge_bases`',
            '`owner_user_id`',
            '`visibility`',
            '`permission_json`',
            'menu.ai_knowledge',
            '/ai/knowledge',
        ] as $needle) {
            self::assertStringContainsString($needle, $content);
        }
    }

    public function testKnowledgeBaseRoutesAreRegisteredUnderAdminApi(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes/admin.php');

        self::assertNotFalse($routes);
        foreach ([
            'init',
            'list',
            'detail',
            'add',
            'edit',
            'del',
            'status',
            'documents',
            'addDocument',
            'editDocument',
            'delDocument',
            'reindexDocument',
            'chunks',
            'retrievalTest',
        ] as $action) {
            self::assertStringContainsString("Route::post('/AiKnowledgeBases/{$action}'", $routes);
        }
    }

    public function testChunkTextKeepsOverlapAndNeverReturnsEmptyChunks(): void
    {
        $chunks = AiRagService::chunkText('abcdefghij', 4, 2);

        self::assertSame(['abcd', 'cdef', 'efgh', 'ghij'], $chunks);
    }

    public function testKeywordScoreRewardsRepeatedQueryTerms(): void
    {
        $score = AiRagService::keywordScore(
            'RAG 知识库 召回 召回 chunk',
            'RAG 召回'
        );

        self::assertGreaterThan(2, $score);
    }

    public function testBuildContextPromptIncludesSourcesAndChunks(): void
    {
        $prompt = AiRagService::buildContextPrompt([
            [
                'document_title' => '运营手册',
                'chunk_no' => 2,
                'score' => 3.5,
                'content' => '回答必须优先引用知识库内容。',
            ],
        ]);

        self::assertStringContainsString('以下是可参考的知识库片段', $prompt);
        self::assertStringContainsString('来源：运营手册 #2', $prompt);
        self::assertStringContainsString('回答必须优先引用知识库内容。', $prompt);
    }

    public function testBuildAugmentedSystemPromptAppendsContextWithoutDroppingBasePrompt(): void
    {
        $prompt = AiRagService::buildAugmentedSystemPrompt('你是客服助手。', [
            [
                'document_title' => '售后政策',
                'chunk_no' => 1,
                'score' => 4.2,
                'content' => '七天内可申请退换。',
            ],
        ]);

        self::assertStringStartsWith('你是客服助手。', $prompt);
        self::assertStringContainsString('以下是可参考的知识库片段', $prompt);
        self::assertStringContainsString('七天内可申请退换。', $prompt);
    }
}
