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

    public function testAgentModuleSyncsToolsAndScenesWithoutModeToolGate(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Ai/AiAgentsModule.php';
        $content = file_get_contents($path);

        self::assertStringContainsString('setAiCapabilityArr', $content);
        self::assertStringContainsString('syncScenes', $content);
        self::assertStringContainsString("isset(\$param['tool_ids'])", $content);
        self::assertStringNotContainsString("=== AiEnum::MODE_TOOL && !empty(\$param['tool_ids'])", $content);
    }

    public function testAgentSceneDepExposesSceneSyncAndBatchMap(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/dep/Ai/AiAgentScenesDep.php';
        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertStringContainsString('function syncScenes(int $agentId, array $sceneCodes', $content);
        self::assertStringContainsString('function getSceneCodesByAgentIds(array $agentIds)', $content);
        self::assertStringContainsString('function getAgentIdsBySceneCode(string $sceneCode)', $content);
    }

    public function testNeuronFactoryUsesCapabilityToolsInsteadOfModeTool(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/lib/Ai/NeuronAgentFactory.php';
        $content = file_get_contents($path);

        self::assertStringContainsString('agentHasCapability', $content);
        self::assertStringContainsString('AiEnum::CAPABILITY_TOOLS', $content);
        self::assertStringNotContainsString("(\$agent->mode ?? '') === AiEnum::MODE_TOOL", $content);
    }
}
