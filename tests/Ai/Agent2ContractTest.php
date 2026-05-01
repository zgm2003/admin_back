<?php

namespace tests\Ai;

use app\enum\AiEnum;
use PHPUnit\Framework\TestCase;

class Agent2ContractTest extends TestCase
{
    public function testAgent2MigrationAddsCapabilitiesAndMultiSceneTable(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database/migrations/20260501_agent_2_super_agent.sql';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('capabilities_json', $content);
        self::assertStringContainsString('runtime_config_json', $content);
        self::assertStringContainsString('policy_json', $content);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `ai_agent_scenes`', $content);
        self::assertStringContainsString('UNIQUE KEY `uniq_agent_scene` (`agent_id`, `scene_code`)', $content);
        self::assertStringContainsString('JSON_OBJECT', $content);
    }

    public function testAgent2EnumDefinesCapabilities(): void
    {
        self::assertSame('tools', AiEnum::CAPABILITY_TOOLS);
        self::assertSame('rag', AiEnum::CAPABILITY_RAG);
        self::assertSame('workflow', AiEnum::CAPABILITY_WORKFLOW);
        self::assertSame('image', AiEnum::CAPABILITY_IMAGE);
        self::assertArrayHasKey(AiEnum::CAPABILITY_MEMORY, AiEnum::$capabilityArr);
    }

    public function testAgentModelCastsAgent2JsonFields(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/model/Ai/AiAgentsModel.php';
        $content = file_get_contents($path);

        self::assertStringContainsString("'capabilities_json' => 'json'", $content);
        self::assertStringContainsString("'runtime_config_json' => 'json'", $content);
        self::assertStringContainsString("'policy_json' => 'json'", $content);
    }
}
