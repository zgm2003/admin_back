# Agent 2.0 Super Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade AI agents from mutually-exclusive `chat/rag/tool/workflow` modes to an OSS-first super-agent model with capabilities, multi-scene binding, and runtime tool injection independent of `mode=tool`.

**Architecture:** Keep the existing Webman PHP + Vue 3 stack. Add a normalized `ai_agent_scenes` table and JSON capability/runtime/policy fields on `ai_agents`; keep `mode` and `scene` as compatibility fields. Backend Module/Dep owns queries and synchronization, Factory reads capabilities, and the frontend becomes a capability panel plus multi-scene selector.

**Tech Stack:** PHP 8/Webman, Eloquent via project Dep layer, MySQL migrations, Neuron AI, Vue 3 `<script setup>` + TypeScript, Element Plus, existing `AppDialog/AppTable/Search/el-select-v2` components.

---

## File structure

### Backend

- Create `database/migrations/20260501_agent_2_super_agent.sql`
  - Adds `capabilities_json`, `runtime_config_json`, `policy_json` to `ai_agents`.
  - Creates `ai_agent_scenes`.
  - Backfills old `scene` into `ai_agent_scenes`.
  - Backfills old `mode` into `capabilities_json`.
- Create `app/model/Ai/AiAgentScenesModel.php`
  - Table mapping and JSON casts only.
- Create `app/dep/Ai/AiAgentScenesDep.php`
  - All reads/writes for `ai_agent_scenes`, including sync and batch mapping.
- Modify `app/model/Ai/AiAgentsModel.php`
  - Add JSON casts for new agent config fields.
- Modify `app/enum/AiEnum.php`
  - Add capability constants and `capabilityArr` dictionary.
- Modify `app/dep/Ai/AiAgentsDep.php`
  - Include new fields in list selects.
  - Add scene-aware methods that prefer `ai_agent_scenes` and fallback to legacy `ai_agents.scene`.
- Modify `app/module/Ai/AiAgentsModule.php`
  - Init returns capability dict.
  - List returns `capabilities`, `scene_codes`, `scene_names`.
  - Add/edit sync scenes and tools regardless of `mode`.
- Modify `app/validate/Ai/AiAgentsValidate.php`
  - Accept `capabilities`, `scene_codes`, `runtime_config`, `policy`.
- Modify `app/lib/Ai/NeuronAgentFactory.php`
  - Inject tools based on `capabilities.tools`, not `mode=tool`.
- Create `tests/Ai/Agent2ContractTest.php`
  - Static contract tests for migration, enum, module, factory, and Dep behavior strings.

### Frontend

- Modify `admin_front_ts/src/api/ai/agents.ts`
  - Add types for capabilities, scene codes, runtime/policy.
- Modify `admin_front_ts/src/views/Main/ai/agents/composables/helpers.ts`
  - Form state uses `capabilities` and `scene_codes`.
  - Mutation payload always sends `tool_ids`, not only `mode=tool`.
- Modify `admin_front_ts/src/views/Main/ai/agents/index.vue`
  - Replace primary mode selector with capability switches.
  - Replace scene single select with multi-select.
  - Show tools when `capabilities.tools` is true.
  - Keep `mode` hidden/compat default as `chat`.
- Modify i18n files found by `rg "aiAgents" admin_front_ts/src -g "*.ts" -g "*.json"`.
  - Add labels for capabilities, multi-scene, runtime/policy if needed.

---

## Task 1: Backend schema and static contracts

**Files:**
- Create: `E:/admin/admin_back/database/migrations/20260501_agent_2_super_agent.sql`
- Create: `E:/admin/admin_back/app/model/Ai/AiAgentScenesModel.php`
- Modify: `E:/admin/admin_back/app/model/Ai/AiAgentsModel.php`
- Modify: `E:/admin/admin_back/app/enum/AiEnum.php`
- Create: `E:/admin/admin_back/tests/Ai/Agent2ContractTest.php`

- [ ] **Step 1: Write the failing contract test**

Create `tests/Ai/Agent2ContractTest.php` with:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: FAIL because the migration/model/enum fields do not exist yet.

- [ ] **Step 3: Add migration SQL**

Create `database/migrations/20260501_agent_2_super_agent.sql` with:

```sql
ALTER TABLE `ai_agents`
  ADD COLUMN `capabilities_json` json NULL COMMENT 'Agent能力配置：chat/tools/rag/workflow/image/file/memory' AFTER `scene`,
  ADD COLUMN `runtime_config_json` json NULL COMMENT '运行参数：timeout/retry/max_tokens/reasoning等' AFTER `capabilities_json`,
  ADD COLUMN `policy_json` json NULL COMMENT '权限策略：工具调用上限、失败策略、审批等' AFTER `runtime_config_json`;

CREATE TABLE IF NOT EXISTS `ai_agent_scenes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `agent_id` int UNSIGNED NOT NULL COMMENT 'ai_agents.id',
  `scene_code` varchar(60) NOT NULL COMMENT '场景编码，如 goods_script/cine_project/cine_keyframe',
  `prompt_overlay` text NULL COMMENT '该场景追加提示词',
  `config_json` json NULL COMMENT '该场景运行配置覆盖',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '2正常 1删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_agent_scene` (`agent_id`, `scene_code`),
  KEY `idx_scene_status_del` (`scene_code`, `status`, `is_del`),
  KEY `idx_agent_del` (`agent_id`, `is_del`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI智能体多场景绑定';

INSERT INTO `ai_agent_scenes` (`agent_id`, `scene_code`, `status`, `is_del`, `created_at`, `updated_at`)
SELECT a.`id`, a.`scene`, 1, 2, NOW(), NOW()
FROM `ai_agents` a
WHERE a.`scene` IS NOT NULL
  AND a.`scene` <> ''
  AND a.`is_del` = 2
  AND NOT EXISTS (
    SELECT 1 FROM `ai_agent_scenes` s
    WHERE s.`agent_id` = a.`id`
      AND s.`scene_code` = a.`scene`
  );

UPDATE `ai_agents`
SET `capabilities_json` = JSON_OBJECT(
  'chat', true,
  'tools', IF(`mode` = 'tool', true, false),
  'rag', IF(`mode` = 'rag', true, false),
  'workflow', IF(`mode` = 'workflow', true, false),
  'image', false,
  'file', false,
  'memory', true
)
WHERE `capabilities_json` IS NULL;
```

- [ ] **Step 4: Add scene model and JSON casts**

Create `app/model/Ai/AiAgentScenesModel.php`:

```php
<?php

namespace app\model\Ai;

use app\model\BaseModel;

class AiAgentScenesModel extends BaseModel
{
    protected $table = 'ai_agent_scenes';

    protected $casts = [
        'config_json' => 'json',
    ];

    protected $hidden = [
        'is_del',
    ];
}
```

Modify `app/model/Ai/AiAgentsModel.php` casts:

```php
protected $casts = [
    'capabilities_json' => 'json',
    'runtime_config_json' => 'json',
    'policy_json' => 'json',
];
```

- [ ] **Step 5: Add capability enum constants**

Modify `app/enum/AiEnum.php` after mode definitions:

```php
const CAPABILITY_CHAT = 'chat';
const CAPABILITY_TOOLS = 'tools';
const CAPABILITY_RAG = 'rag';
const CAPABILITY_WORKFLOW = 'workflow';
const CAPABILITY_IMAGE = 'image';
const CAPABILITY_FILE = 'file';
const CAPABILITY_MEMORY = 'memory';

public static $capabilityArr = [
    self::CAPABILITY_TOOLS => '工具调用',
    self::CAPABILITY_RAG => 'RAG知识库',
    self::CAPABILITY_WORKFLOW => '工作流编排',
    self::CAPABILITY_IMAGE => '图片能力',
    self::CAPABILITY_FILE => '文件理解',
    self::CAPABILITY_MEMORY => '长期记忆',
];
```

Do not include `chat` in the user-facing toggle list because chat is always on.

- [ ] **Step 6: Run backend test**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: PASS.

- [ ] **Step 7: Commit Task 1**

```powershell
git -C E:\admin\admin_back add database/migrations/20260501_agent_2_super_agent.sql app/model/Ai/AiAgentScenesModel.php app/model/Ai/AiAgentsModel.php app/enum/AiEnum.php tests/Ai/Agent2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): add agent capability schema"
```

---

## Task 2: Backend scene Dep and Agent Module contract

**Files:**
- Create: `E:/admin/admin_back/app/dep/Ai/AiAgentScenesDep.php`
- Modify: `E:/admin/admin_back/app/dep/Ai/AiAgentsDep.php`
- Modify: `E:/admin/admin_back/app/module/Ai/AiAgentsModule.php`
- Modify: `E:/admin/admin_back/app/validate/Ai/AiAgentsValidate.php`
- Modify: `E:/admin/admin_back/tests/Ai/Agent2ContractTest.php`

- [ ] **Step 1: Extend the contract test**

Append these methods to `tests/Ai/Agent2ContractTest.php`:

```php
public function testAgentModuleSyncsToolsAndScenesWithoutModeToolGate(): void
{
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/module/Ai/AiAgentsModule.php';
    $content = file_get_contents($path);

    self::assertStringContainsString('setAiCapabilityArr', $content);
    self::assertStringContainsString('syncScenes', $content);
    self::assertStringContainsString("isset($param['tool_ids'])", $content);
    self::assertStringNotContainsString("=== AiEnum::MODE_TOOL && !empty($param['tool_ids'])", $content);
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
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: FAIL because `AiAgentScenesDep` and module changes are not present.

- [ ] **Step 3: Create AiAgentScenesDep**

Create `app/dep/Ai/AiAgentScenesDep.php` with methods:

```php
<?php

namespace app\dep\Ai;

use app\dep\BaseDep;
use app\enum\CommonEnum;
use app\model\Ai\AiAgentScenesModel;
use support\Model;

class AiAgentScenesDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new AiAgentScenesModel();
    }

    public function syncScenes(int $agentId, array $sceneCodes, array $sceneConfigs = []): void
    {
        $sceneCodes = array_values(array_unique(array_filter(
            array_map('strval', $sceneCodes),
            static fn(string $sceneCode) => $sceneCode !== ''
        )));

        $current = $this->model
            ->where('agent_id', $agentId)
            ->where('is_del', CommonEnum::NO)
            ->pluck('scene_code')
            ->toArray();

        $toAdd = array_diff($sceneCodes, $current);
        $toRemove = array_diff($current, $sceneCodes);

        foreach ($toAdd as $sceneCode) {
            $this->bindOrRestore($agentId, $sceneCode, $sceneConfigs[$sceneCode] ?? []);
        }

        foreach (array_intersect($sceneCodes, $current) as $sceneCode) {
            if (array_key_exists($sceneCode, $sceneConfigs)) {
                $this->model
                    ->where('agent_id', $agentId)
                    ->where('scene_code', $sceneCode)
                    ->where('is_del', CommonEnum::NO)
                    ->update([
                        'prompt_overlay' => $sceneConfigs[$sceneCode]['prompt_overlay'] ?? null,
                        'config_json' => $sceneConfigs[$sceneCode]['config_json'] ?? null,
                        'status' => (int)($sceneConfigs[$sceneCode]['status'] ?? CommonEnum::YES),
                    ]);
            }
        }

        if (!empty($toRemove)) {
            $this->model
                ->where('agent_id', $agentId)
                ->whereIn('scene_code', $toRemove)
                ->where('is_del', CommonEnum::NO)
                ->update(['is_del' => CommonEnum::YES]);
        }
    }

    public function bindOrRestore(int $agentId, string $sceneCode, array $config = []): int
    {
        $deleted = $this->model
            ->where('agent_id', $agentId)
            ->where('scene_code', $sceneCode)
            ->where('is_del', CommonEnum::YES)
            ->first();

        $data = [
            'prompt_overlay' => $config['prompt_overlay'] ?? null,
            'config_json' => $config['config_json'] ?? null,
            'status' => (int)($config['status'] ?? CommonEnum::YES),
            'is_del' => CommonEnum::NO,
        ];

        if ($deleted) {
            return $this->model->where('id', $deleted->id)->update($data);
        }

        $this->add($data + [
            'agent_id' => $agentId,
            'scene_code' => $sceneCode,
        ]);

        return 1;
    }

    public function getSceneCodesByAgentIds(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if (empty($agentIds)) {
            return [];
        }

        $rows = $this->model
            ->whereIn('agent_id', $agentIds)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->select(['agent_id', 'scene_code'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->agent_id][] = $row->scene_code;
        }

        return $map;
    }

    public function getAgentIdsBySceneCode(string $sceneCode): array
    {
        return $this->model
            ->where('scene_code', $sceneCode)
            ->where('is_del', CommonEnum::NO)
            ->where('status', CommonEnum::YES)
            ->pluck('agent_id')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }
}
```

- [ ] **Step 4: Update AiAgentsDep**

Modify list columns to include:

```php
'capabilities_json', 'runtime_config_json', 'policy_json',
```

Update scene methods to prefer `AiAgentScenesDep`:

```php
$agentIds = (new AiAgentScenesDep())->getAgentIdsBySceneCode($scene);
$query = $this->model->where('is_del', CommonEnum::NO)->where('status', CommonEnum::YES);
if (!empty($agentIds)) {
    return $query->whereIn('id', $agentIds)->orderBy('id', 'desc')->first();
}
return $query->where('scene', $scene)->orderBy('id', 'desc')->first();
```

Use the same pattern for `getActiveByScene()` and `getActiveByScenes()`.

- [ ] **Step 5: Update validation**

Allow new fields in add/edit:

```php
'capabilities' => v::optional(v::arrayType())->setName('能力配置'),
'scene_codes' => v::optional(v::arrayType()->each(v::stringType()->in(array_keys(AiEnum::$sceneArr))))->setName('场景列表'),
'runtime_config' => v::optional(v::arrayType())->setName('运行配置'),
'policy' => v::optional(v::arrayType())->setName('策略配置'),
```

- [ ] **Step 6: Update module behavior**

In `init()`, call `setAiCapabilityArr()`.

In `add()` and `edit()`:

```php
$capabilities = $this->normalizeCapabilities($param['capabilities'] ?? null, $param['mode'] ?? 'chat');
$sceneCodes = $this->normalizeSceneCodes($param['scene_codes'] ?? [], $param['scene'] ?? null);

// store capabilities_json/runtime_config_json/policy_json
// sync scenes using AiAgentScenesDep
// sync tools when isset($param['tool_ids']), not when mode=tool
```

Add private helper methods in `AiAgentsModule`:

```php
private function normalizeCapabilities(?array $capabilities, string $mode): array
private function normalizeSceneCodes(array $sceneCodes, ?string $legacyScene): array
private function capabilityEnabled(array $capabilities, string $key): bool
```

- [ ] **Step 7: Run backend test**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: PASS.

- [ ] **Step 8: Commit Task 2**

```powershell
git -C E:\admin\admin_back add app/dep/Ai/AiAgentScenesDep.php app/dep/Ai/AiAgentsDep.php app/module/Ai/AiAgentsModule.php app/validate/Ai/AiAgentsValidate.php tests/Ai/Agent2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): support agent capabilities and scenes"
```

---

## Task 3: Runtime factory uses capabilities, not mode

**Files:**
- Modify: `E:/admin/admin_back/app/lib/Ai/NeuronAgentFactory.php`
- Modify: `E:/admin/admin_back/tests/Ai/Agent2ContractTest.php`

- [ ] **Step 1: Add contract test**

Append:

```php
public function testNeuronFactoryUsesCapabilityToolsInsteadOfModeTool(): void
{
    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app/lib/Ai/NeuronAgentFactory.php';
    $content = file_get_contents($path);

    self::assertStringContainsString('agentHasCapability', $content);
    self::assertStringContainsString("AiEnum::CAPABILITY_TOOLS", $content);
    self::assertStringNotContainsString("($agent->mode ?? '') === AiEnum::MODE_TOOL", $content);
}
```

- [ ] **Step 2: Run failing test**

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: FAIL until factory changes.

- [ ] **Step 3: Implement capability check**

In `NeuronAgentFactory::createAgent()` replace mode check with:

```php
$disableTools = (bool)($runtimeParams['disable_tools'] ?? false);
if (!$disableTools && self::agentHasCapability($agent, AiEnum::CAPABILITY_TOOLS)) {
    $tools = self::loadAgentTools((int)$agent->id);
    if (!empty($tools)) {
        $neuronAgent->addTool($tools);
    }
}
```

Add helper:

```php
private static function agentHasCapability(object $agent, string $capability): bool
{
    $capabilities = $agent->capabilities_json ?? [];
    if (is_string($capabilities)) {
        $decoded = json_decode($capabilities, true);
        $capabilities = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($capabilities)) {
        $capabilities = [];
    }

    if (array_key_exists($capability, $capabilities)) {
        return (bool)$capabilities[$capability];
    }

    return $capability === AiEnum::CAPABILITY_TOOLS
        && ($agent->mode ?? '') === AiEnum::MODE_TOOL;
}
```

The fallback preserves old rows until migration is applied.

- [ ] **Step 4: Run backend test**

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: PASS.

- [ ] **Step 5: Commit Task 3**

```powershell
git -C E:\admin\admin_back add app/lib/Ai/NeuronAgentFactory.php tests/Ai/Agent2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): load tools from agent capabilities"
```

---

## Task 4: Frontend types and form helpers

**Files:**
- Modify: `E:/admin/admin_front_ts/src/api/ai/agents.ts`
- Modify: `E:/admin/admin_front_ts/src/views/Main/ai/agents/composables/helpers.ts`

- [ ] **Step 1: Update API types**

Add:

```ts
export interface AiAgentCapabilities {
  chat?: boolean
  tools?: boolean
  rag?: boolean
  workflow?: boolean
  image?: boolean
  file?: boolean
  memory?: boolean
}
```

Extend `AiAgentItem`:

```ts
capabilities: AiAgentCapabilities
scene_codes: string[]
scene_names: string[]
runtime_config?: Record<string, unknown> | null
policy?: Record<string, unknown> | null
```

Extend init dict:

```ts
ai_capability_arr: DictOption<string>[]
```

Extend mutation params:

```ts
capabilities?: AiAgentCapabilities
scene_codes?: string[]
runtime_config?: Record<string, unknown> | null
policy?: Record<string, unknown> | null
tool_ids?: number[]
```

- [ ] **Step 2: Update form helper**

Change `AgentFormState`:

```ts
capabilities: Required<AiAgentCapabilities>
scene_codes: string[]
runtime_config: Record<string, unknown> | null
policy: Record<string, unknown> | null
```

Default capabilities:

```ts
capabilities: {
  chat: true,
  tools: false,
  rag: false,
  workflow: false,
  image: false,
  file: false,
  memory: true,
},
scene_codes: [],
runtime_config: null,
policy: null,
```

Payload must always include:

```ts
capabilities: form.capabilities,
scene_codes: form.scene_codes,
tool_ids: form.tool_ids,
```

Keep legacy compatibility:

```ts
mode: form.mode || 'chat',
scene: form.scene_codes[0] || form.scene || null,
```

- [ ] **Step 3: Type-check quick target**

Run from frontend repo:

```powershell
cd E:\admin\admin_front_ts
npx vue-tsc -b
```

Expected: may fail until Task 5 updates `index.vue`; errors should point to missing properties in `index.vue`.

- [ ] **Step 4: Commit only if type-check is clean**

If `npx vue-tsc -b` passes:

```powershell
git -C E:\admin\admin_front_ts add src/api/ai/agents.ts src/views/Main/ai/agents/composables/helpers.ts
git -C E:\admin\admin_front_ts commit -m "feat(ai): add agent capability form types"
```

If it fails because `index.vue` still uses old fields, defer commit to Task 5.

---

## Task 5: Frontend Agent configuration UI

**Files:**
- Modify: `E:/admin/admin_front_ts/src/views/Main/ai/agents/index.vue`
- Modify i18n files found by `rg "aiAgents" E:/admin/admin_front_ts/src -g "*.ts" -g "*.json"`

- [ ] **Step 1: Replace edit form mapping**

When editing, set:

```ts
capabilities: {
  ...createDefaultAgentForm().capabilities,
  ...(row.capabilities || {}),
},
scene_codes: row.scene_codes?.length ? row.scene_codes : (row.scene ? [row.scene] : []),
```

- [ ] **Step 2: Replace mode selector UI**

Remove the primary `mode` select from visible form. Add capability switches:

```vue
<el-col :span="24">
  <el-form-item :label="t('aiAgents.capabilities')">
    <div class="capability-grid">
      <el-check-tag
        v-for="item in dict.ai_capability_arr"
        :key="item.value"
        :checked="Boolean(form.capabilities[item.value as keyof typeof form.capabilities])"
        @change="(checked) => toggleCapability(item.value, checked)"
      >
        {{ item.label }}
      </el-check-tag>
    </div>
  </el-form-item>
</el-col>
```

- [ ] **Step 3: Replace scene selector with multi-select**

```vue
<el-select-v2 v-model="form.scene_codes" :options="dict.ai_scene_arr" multiple filterable style="width:100%" />
```

- [ ] **Step 4: Show tools by capability**

Change condition from:

```vue
v-if="form.mode === 'tool'"
```

to:

```vue
v-if="form.capabilities.tools"
```

- [ ] **Step 5: Add helper method**

```ts
function toggleCapability(key: string, checked: boolean) {
  if (key === 'chat') return
  form.value.capabilities[key as keyof typeof form.value.capabilities] = checked
  if (key === 'tools') {
    if (checked) {
      void loadToolOptions(typeof form.value.id === 'number' ? form.value.id : undefined).then((ids) => {
        form.value.tool_ids = form.value.tool_ids.length ? form.value.tool_ids : ids
      })
    } else {
      toolOptions.value = []
      form.value.tool_ids = []
    }
  }
}
```

- [ ] **Step 6: Add minimal scoped CSS**

```css
.capability-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
```

- [ ] **Step 7: Run frontend validation**

```powershell
cd E:\admin\admin_front_ts
npx vue-tsc -b
```

Expected: PASS.

- [ ] **Step 8: Commit frontend changes**

```powershell
git -C E:\admin\admin_front_ts add src/api/ai/agents.ts src/views/Main/ai/agents/composables/helpers.ts src/views/Main/ai/agents/index.vue
git -C E:\admin\admin_front_ts commit -m "feat(ai): configure agent capabilities and scenes"
```

---

## Task 6: Final verification and cleanup

**Files:**
- Verify all touched backend/frontend files.

- [ ] **Step 1: PHP syntax check**

Run:

```powershell
cd E:\admin\admin_back
php -l app\model\Ai\AiAgentScenesModel.php
php -l app\dep\Ai\AiAgentScenesDep.php
php -l app\dep\Ai\AiAgentsDep.php
php -l app\module\Ai\AiAgentsModule.php
php -l app\validate\Ai\AiAgentsValidate.php
php -l app\lib\Ai\NeuronAgentFactory.php
php -l app\enum\AiEnum.php
php -l tests\Ai\Agent2ContractTest.php
```

Expected: every command prints `No syntax errors detected`.

- [ ] **Step 2: Backend contract tests**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Agent2ContractTest
```

Expected: PASS.

- [ ] **Step 3: Existing AI regression tests**

Run:

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit --filter Cine
```

Expected: PASS. If this fails because of environment-only issues, capture the exact error and do not claim pass.

- [ ] **Step 4: Frontend type check**

Run:

```powershell
cd E:\admin\admin_front_ts
npx vue-tsc -b
```

Expected: PASS.

- [ ] **Step 5: Diff hygiene**

Run:

```powershell
git -C E:\admin\admin_back diff --check
git -C E:\admin\admin_front_ts diff --check
git -C E:\admin\admin_back status --short --branch
git -C E:\admin\admin_front_ts status --short --branch
```

Expected: no whitespace errors; either clean or only expected committed-ahead state.

- [ ] **Step 6: Final summary**

Report:

```text
Outcome: Agent 2.0 capability/multi-scene config implemented.
Backend commits: <hashes>
Frontend commits: <hashes>
Verification: <exact commands and pass/fail>
Known limitations: RAG/Workflow UI resources are reserved, not fully implemented in Phase 1.
```
