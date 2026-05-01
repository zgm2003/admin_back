# AI Chat Runtime 2.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild AI chat as a Run-first runtime where streamable HTTP subscribes to run events, queue workers execute LLM/RAG/image work, and chat can generate images as a first-version acceptance requirement.

**Architecture:** Keep the current `/api/admin/AiChat/stream` frontend contract, but change backend semantics from “HTTP request executes model” to “HTTP request creates run, queues `ai_run_execute`, then relays run events”. Store final truth in `ai_runs`, `ai_run_steps`, and `ai_messages`; use Redis Stream only as a short-lived event bus. Image generation is implemented as a run step and writes `meta_json.blocks` so the message can contain text plus image cards.

**Tech Stack:** PHP 8/Webman, Webman Redis Queue, `support\Redis`, Guzzle, existing Dep layer, Neuron AI, MySQL JSON fields, Vue 3 `<script setup lang="ts">`, Element Plus.

---

## Non-negotiable constraints

- Do not put model execution inside WebSocket handlers.
- Do not keep direct `AiChatService::chatStream()` execution inside `AiChatModule::sendStream()` after Task 5.
- Do not add model-management “multimodal capability” gates. Runtime attempts image generation and records real upstream errors.
- Do not break old pure-text messages. `content` remains the fallback; `meta_json.blocks` is additive.
- Image generation is MVP scope. The implementation is not complete until a chat message can produce an image block.
- Backend layering stays `Controller -> Module -> Dep -> Model`; Modules and Services use Deps, not raw models.

## File structure

### Backend

- Create `tests/Ai/AiChatRuntime2ContractTest.php` — contracts for queue split, event publisher, image generation, and blocks.
- Create `app/service/Ai/AiRunEventPublisher.php` — Redis Stream publish/read for run events.
- Create `app/service/Ai/AiChatRunExecutor.php` — executes existing `ai_runs` from queue.
- Create `app/service/Ai/AiImageIntentDetector.php` — detects explicit image-generation intent.
- Create `app/service/Ai/AiImageGenerationService.php` — calls `/images/generations`, uploads to `ai_chat_images`, returns an image block.
- Create `app/queue/redis/slow/AiRunExecute.php` — Redis Queue consumer for `ai_run_execute`.
- Modify `app/module/Ai/AiChatModule.php` — stream endpoint creates run, dispatches queue, relays events.
- Modify `app/enum/AiEnum.php` — add `STEP_TYPE_IMAGE`.

### Frontend

- Modify `src/api/ai/chat.ts` — accept `image_generating` and `image_done`.
- Modify `src/views/Main/ai/chat/composables/types.ts` — add `MessageBlock`.
- Modify `src/views/Main/ai/chat/composables/useStreamChat.ts` — append image blocks during stream.
- Modify `src/views/Main/ai/chat/components/MessageList/index.vue` — render `meta_json.blocks`.

---

## Task 1: Backend contracts first

**Files:**
- Create: `E:/admin/admin_back/tests/Ai/AiChatRuntime2ContractTest.php`

- [ ] **Step 1: Write failing contract tests**

Create `tests/Ai/AiChatRuntime2ContractTest.php`:

```php
<?php

namespace tests\Ai;

use PHPUnit\Framework\TestCase;

class AiChatRuntime2ContractTest extends TestCase
{
    public function testRunEventPublisherUsesRedisStreamWithRunScopedKeys(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiRunEventPublisher.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('class AiRunEventPublisher', $content);
        self::assertStringContainsString("ai:run:{", $content);
        self::assertStringContainsString('xAdd', $content);
        self::assertStringContainsString('xRead', $content);
        self::assertStringContainsString('expire', $content);
    }

    public function testStreamModuleQueuesRunInsteadOfExecutingModelDirectly(): void
    {
        $path = dirname(__DIR__, 2) . '/app/module/Ai/AiChatModule.php';
        $content = file_get_contents($path);

        self::assertStringContainsString("RedisQueue::send('ai_run_execute'", $content);
        self::assertStringContainsString('relayRunEvents', $content);

        $sendStreamStart = strpos($content, 'public function sendStream');
        $privateStart = strpos($content, '// ==================== 私有方法', $sendStreamStart);
        $sendStreamBody = substr($content, $sendStreamStart, $privateStart - $sendStreamStart);
        self::assertStringNotContainsString('AiChatService::chatStream(', $sendStreamBody);
    }

    public function testQueueConsumerDelegatesToRunExecutor(): void
    {
        $path = dirname(__DIR__, 2) . '/app/queue/redis/slow/AiRunExecute.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString("public $queue = 'ai_run_execute'", $content);
        self::assertStringContainsString('AiChatRunExecutor', $content);
        self::assertStringContainsString("execute((int)\$data['run_id'])", $content);
    }

    public function testRunExecutorPublishesTextAndImageEvents(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiChatRunExecutor.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('class AiChatRunExecutor', $content);
        self::assertStringContainsString('AiChatService::chatStream(', $content);
        self::assertStringContainsString("publish(\$runId, 'content'", $content);
        self::assertStringContainsString('AiImageIntentDetector', $content);
        self::assertStringContainsString('AiImageGenerationService', $content);
        self::assertStringContainsString("publish(\$runId, 'image_done'", $content);
    }

    public function testImageGenerationServicePersistsAiChatImageBlock(): void
    {
        $path = dirname(__DIR__, 2) . '/app/service/Ai/AiImageGenerationService.php';
        $content = is_file($path) ? file_get_contents($path) : '';

        self::assertStringContainsString('/images/generations', $content);
        self::assertStringContainsString('FOLDER_AI_CHAT_IMAGES', $content);
        self::assertStringContainsString("'type' => 'image'", $content);
        self::assertStringContainsString("'url' =>", $content);
        self::assertStringContainsString('b64_json', $content);
    }

    public function testFrontendMessageBlocksAreTypedAndRendered(): void
    {
        $typesPath = dirname(__DIR__, 3) . '/admin_front_ts/src/views/Main/ai/chat/composables/types.ts';
        $listPath = dirname(__DIR__, 3) . '/admin_front_ts/src/views/Main/ai/chat/components/MessageList/index.vue';

        $types = is_file($typesPath) ? file_get_contents($typesPath) : '';
        $list = is_file($listPath) ? file_get_contents($listPath) : '';

        self::assertStringContainsString('MessageBlock', $types);
        self::assertStringContainsString("type: 'image'", $types);
        self::assertStringContainsString('getMessageBlocks', $list);
        self::assertStringContainsString("block.type === 'image'", $list);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```powershell
cd E:\admin\admin_back
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php
```

Expected: FAIL because the new files/classes and frontend block rendering do not exist yet.

- [ ] **Step 3: Commit**

```powershell
git -C E:\admin\admin_back add tests/Ai/AiChatRuntime2ContractTest.php
git -C E:\admin\admin_back commit -m "test(ai): lock chat runtime contracts"
```

---

## Task 2: Add Redis run event publisher

**Files:**
- Create: `E:/admin/admin_back/app/service/Ai/AiRunEventPublisher.php`

- [ ] **Step 1: Implement publisher**

Create `app/service/Ai/AiRunEventPublisher.php`:

```php
<?php

namespace app\service\Ai;

use support\Redis;

class AiRunEventPublisher
{
    private const KEY_PREFIX = 'ai:run:';
    private const KEY_SUFFIX = ':events';
    private const TTL_SECONDS = 86400;

    public function publish(int $runId, string $event, array $data = []): string
    {
        $key = $this->key($runId);
        $id = Redis::xAdd($key, '*', [
            'event' => $event,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => (string)time(),
        ]);
        Redis::expire($key, self::TTL_SECONDS);
        return (string)$id;
    }

    public function publishError(int $runId, string $message, array $extra = []): string
    {
        return $this->publish($runId, 'error', array_merge(['msg' => $message], $extra));
    }

    public function read(int $runId, string $lastId = '0-0', int $blockMs = 1000): array
    {
        $key = $this->key($runId);
        $result = Redis::xRead([$key => $lastId], 10, $blockMs);
        $rows = $result[$key] ?? [];
        $events = [];
        foreach ($rows as $id => $row) {
            $decoded = json_decode((string)($row['data'] ?? '{}'), true);
            $events[] = [
                'id' => (string)$id,
                'event' => (string)($row['event'] ?? 'message'),
                'data' => is_array($decoded) ? $decoded : [],
            ];
        }
        return $events;
    }

    public function key(int $runId): string
    {
        return self::KEY_PREFIX . '{' . $runId . '}' . self::KEY_SUFFIX;
    }
}
```

- [ ] **Step 2: Verify**

```powershell
cd E:\admin\admin_back
php -l app\service\Ai\AiRunEventPublisher.php
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php --filter RunEventPublisher
```

Expected: syntax OK and publisher contract PASS.

- [ ] **Step 3: Commit**

```powershell
git -C E:\admin\admin_back add app/service/Ai/AiRunEventPublisher.php tests/Ai/AiChatRuntime2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): add run event publisher"
```

---

## Task 3: Add image intent and generation service

**Files:**
- Create: `E:/admin/admin_back/app/service/Ai/AiImageIntentDetector.php`
- Create: `E:/admin/admin_back/app/service/Ai/AiImageGenerationService.php`

- [ ] **Step 1: Add image intent detector**

Create `app/service/Ai/AiImageIntentDetector.php`:

```php
<?php

namespace app\service\Ai;

class AiImageIntentDetector
{
    public function detect(string $content): ?array
    {
        $text = trim($content);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\/(image|img|画图|生成图片)\s+(.+)$/iu', $text, $match)) {
            return ['prompt' => trim($match[2]), 'source' => 'command'];
        }

        foreach ([
            '/(?:生成|画|绘制|做)一?张.+(?:图|图片|海报|封面|插画)/u',
            '/(?:帮我|给我).*(?:生成|画|绘制).*(?:图|图片|海报|封面|插画)/u',
        ] as $pattern) {
            if (preg_match($pattern, $text)) {
                return ['prompt' => $text, 'source' => 'natural_language'];
            }
        }

        return null;
    }
}
```

- [ ] **Step 2: Add generic chat image generation service**

Create `app/service/Ai/AiImageGenerationService.php` by extracting the generic parts of `CineImageGenerationService`: use the selected run model, call `$baseUri . '/images/generations'`, request `response_format => 'b64_json'`, upload with `UploadConfigEnum::FOLDER_AI_CHAT_IMAGES`, and return:

```php
return [
    'type' => 'image',
    'url' => (string)($upload['url'] ?? ''),
    'alt' => mb_substr($prompt, 0, 120),
    'meta' => [
        'model_code' => (string)($model->model_code ?? ''),
        'prompt' => $prompt,
        'upload' => $upload,
        'revised_prompt' => $this->extractRevisedPrompt($payload),
    ],
];
```

The implementation must preserve the same failure behavior as `CineImageGenerationService`: non-2xx HTTP, invalid JSON, missing image data, and base64 decode failure all throw `RuntimeException` with a clear message.

- [ ] **Step 3: Verify**

```powershell
cd E:\admin\admin_back
php -l app\service\Ai\AiImageIntentDetector.php
php -l app\service\Ai\AiImageGenerationService.php
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php --filter ImageGenerationService
```

Expected: syntax OK and image service contract PASS.

- [ ] **Step 4: Commit**

```powershell
git -C E:\admin\admin_back add app/service/Ai/AiImageIntentDetector.php app/service/Ai/AiImageGenerationService.php tests/Ai/AiChatRuntime2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): add chat image generation service"
```

---

## Task 4: Add queue consumer and run executor

**Files:**
- Create: `E:/admin/admin_back/app/service/Ai/AiChatRunExecutor.php`
- Create: `E:/admin/admin_back/app/queue/redis/slow/AiRunExecute.php`
- Modify: `E:/admin/admin_back/app/enum/AiEnum.php`

- [ ] **Step 1: Add image step enum**

Modify `AiEnum`:

```php
const STEP_TYPE_IMAGE = 7;
```

Add label:

```php
self::STEP_TYPE_IMAGE => '图片生成',
```

- [ ] **Step 2: Add queue consumer**

Create `app/queue/redis/slow/AiRunExecute.php`:

```php
<?php

namespace app\queue\redis\slow;

use app\service\Ai\AiChatRunExecutor;
use Webman\RedisQueue\Consumer;

class AiRunExecute implements Consumer
{
    public $queue = 'ai_run_execute';
    public $connection = 'default';

    public function consume($data): void
    {
        if (empty($data['run_id'])) {
            log_daily('queue_ai_run_execute')->warning('ai_run_execute missing run_id', ['data' => $data]);
            return;
        }
        (new AiChatRunExecutor())->execute((int)$data['run_id']);
    }
}
```

- [ ] **Step 3: Add run executor**

Create `app/service/Ai/AiChatRunExecutor.php`. It must:

```text
execute(run_id)
  -> load ai_runs / conversation / agent / model / user message by Dep
  -> decode run.meta_json.runtime_overrides and user_message.meta_json.attachments
  -> if AiImageIntentDetector detects image:
       publish image_generating
       create STEP_TYPE_IMAGE
       call AiImageGenerationService
       save assistant message with blocks: text + image
       publish image_done
       publish done
     else:
       build history with AiChatService::buildMessages
       call AiChatService::chatStream
       publish content deltas
       save assistant message with text block
       publish done
  -> on Throwable:
       markFailed
       publish error
       emit ai.run.failed
```

Critical insert rule: when writing `payload_json` or `meta_json` through Dep `add()`, encode arrays with `json_encode(..., JSON_UNESCAPED_UNICODE)` because `BaseDep::add()` uses `insertGetId()` and does not apply model casts.

- [ ] **Step 4: Verify**

```powershell
cd E:\admin\admin_back
php -l app\queue\redis\slow\AiRunExecute.php
php -l app\service\Ai\AiChatRunExecutor.php
php -l app\enum\AiEnum.php
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php --filter "QueueConsumer|RunExecutor"
```

Expected: syntax OK and queue/executor contracts PASS.

- [ ] **Step 5: Commit**

```powershell
git -C E:\admin\admin_back add app/queue/redis/slow/AiRunExecute.php app/service/Ai/AiChatRunExecutor.php app/enum/AiEnum.php tests/Ai/AiChatRuntime2ContractTest.php
git -C E:\admin\admin_back commit -m "feat(ai): execute chat runs in queue"
```

---

## Task 5: Refactor `/AiChat/stream` to queue and relay events

**Files:**
- Modify: `E:/admin/admin_back/app/module/Ai/AiChatModule.php`

- [ ] **Step 1: Add run event relay method**

Add `use app\service\Ai\AiRunEventPublisher;`.

Add:

```php
private function relayRunEvents(int $runId, callable $onChunk): void
{
    $publisher = new AiRunEventPublisher();
    $lastId = '0-0';
    $terminal = false;
    $deadline = time() + 3600;

    while (!$terminal && time() < $deadline) {
        foreach ($publisher->read($runId, $lastId, 1000) as $event) {
            $lastId = $event['id'];
            $onChunk($event['event'], $event['data']);
            if (in_array($event['event'], ['done', 'error', 'canceled'], true)) {
                $terminal = true;
                break;
            }
        }

        $run = $this->dep(AiRunsDep::class)->find($runId);
        if (!$run || $run->run_status !== AiEnum::RUN_STATUS_RUNNING) {
            if (!$terminal && $run && $run->run_status === AiEnum::RUN_STATUS_FAIL) {
                $onChunk('error', ['msg' => $run->error_msg ?: 'AI 调用失败']);
            }
            break;
        }
    }
}
```

- [ ] **Step 2: Change `sendStream()`**

`sendStream()` must only:

```text
prepareChat()
createRun(..., meta_json.runtime_overrides)
emit run event to client
RedisQueue::send('ai_run_execute', ['run_id' => $runId, 'user_id' => $userId])
relayRunEvents()
autoGenerateTitle() for new conversation
return success
```

It must not call `AiChatService::chatStream()` directly.

- [ ] **Step 3: Make `createRun()` accept meta JSON**

Change signature:

```php
private function createRun(string $requestId, int $userId, array $ctx, bool $isStream = false, array $meta = []): int
```

Add to insert payload:

```php
'meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
```

- [ ] **Step 4: Verify**

```powershell
cd E:\admin\admin_back
php -l app\module\Ai\AiChatModule.php
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php --filter StreamModule
```

Expected: syntax OK and stream module contract PASS.

- [ ] **Step 5: Commit**

```powershell
git -C E:\admin\admin_back add app/module/Ai/AiChatModule.php tests/Ai/AiChatRuntime2ContractTest.php
git -C E:\admin\admin_back commit -m "refactor(ai): queue stream chat runs"
```

---

## Task 6: Frontend message blocks and image stream events

**Files:**
- Modify: `E:/admin/admin_front_ts/src/api/ai/chat.ts`
- Modify: `E:/admin/admin_front_ts/src/views/Main/ai/chat/composables/types.ts`
- Modify: `E:/admin/admin_front_ts/src/views/Main/ai/chat/composables/useStreamChat.ts`
- Modify: `E:/admin/admin_front_ts/src/views/Main/ai/chat/components/MessageList/index.vue`

- [ ] **Step 1: Add block types**

In `types.ts`:

```ts
export type MessageBlock =
  | { type: 'text'; text: string }
  | { type: 'image'; url: string; alt?: string; meta?: Record<string, unknown> }
  | { type: 'tool'; name: string; status: 'calling' | 'success' | 'failed'; result?: string }
  | { type: 'error'; message: string; scope?: string }
```

Ensure message meta supports:

```ts
blocks?: MessageBlock[]
```

- [ ] **Step 2: Add image events**

In `api/ai/chat.ts`, add callbacks:

```ts
onImageGenerating?: (data: { prompt?: string }) => void
onImageDone?: (data: { url: string; block?: Record<string, unknown> }) => void
```

Handle events:

```ts
case 'image_generating':
  callbacks.onImageGenerating?.({ prompt: typeof data.prompt === 'string' ? data.prompt : undefined })
  break
case 'image_done':
  callbacks.onImageDone?.({
    url: requireStringField(data, 'url'),
    block: typeof data.block === 'object' && data.block !== null ? data.block as Record<string, unknown> : undefined,
  })
  break
```

- [ ] **Step 3: Append image block during stream**

In `useStreamChat.ts`, add callbacks that update the last assistant message:

```ts
onImageGenerating: () => {
  const session = getSession(requestAgentId)
  const lastMsg = session?.messages[session.messages.length - 1]
  if (lastMsg && lastMsg.role === AiRoleEnum.ASSISTANT) {
    lastMsg.content = lastMsg.content || '图片生成中...'
  }
  if (isActiveAgent(requestAgentId)) messages.value = [...messages.value]
},
onImageDone: (data) => {
  const session = getSession(requestAgentId)
  const lastMsg = session?.messages[session.messages.length - 1]
  if (lastMsg && lastMsg.role === AiRoleEnum.ASSISTANT) {
    if (!lastMsg.meta_json) lastMsg.meta_json = {}
    lastMsg.meta_json.blocks = [
      { type: 'text', text: lastMsg.content || '图片已生成' },
      data.block && data.block.type === 'image' ? data.block as any : { type: 'image', url: data.url },
    ]
  }
  if (isActiveAgent(requestAgentId)) messages.value = [...messages.value]
},
```

- [ ] **Step 4: Render blocks**

In `MessageList/index.vue`, add helpers:

```ts
const getMessageBlocks = (msg: Message) => msg.meta_json?.blocks ?? []
const hasMessageBlocks = (msg: Message) => getMessageBlocks(msg).length > 0
```

Render before old content fallback:

```vue
<template v-if="hasMessageBlocks(msg)">
  <div v-for="(block, blockIndex) in getMessageBlocks(msg)" :key="blockIndex" class="message-block">
    <div v-if="block.type === 'text'" class="message-text">{{ block.text }}</div>
    <el-image
      v-else-if="block.type === 'image'"
      class="message-image-block"
      :src="block.url"
      :preview-src-list="[block.url]"
      fit="cover"
      preview-teleported
    />
    <div v-else-if="block.type === 'error'" class="message-error-block">{{ block.message }}</div>
  </div>
</template>
```

Add CSS:

```css
.message-block + .message-block { margin-top: 10px; }
.message-image-block {
  width: min(320px, 100%);
  max-height: 420px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--el-border-color-lighter);
}
.message-error-block {
  color: var(--el-color-danger);
  background: var(--el-color-danger-light-9);
  border: 1px solid var(--el-color-danger-light-7);
  border-radius: 8px;
  padding: 8px 10px;
}
```

- [ ] **Step 5: Verify frontend**

```powershell
cd E:\admin\admin_front_ts
npx.cmd vue-tsc -b
npm run build
```

Expected: both pass.

- [ ] **Step 6: Commit**

```powershell
git -C E:\admin\admin_front_ts add src/api/ai/chat.ts src/views/Main/ai/chat/composables/types.ts src/views/Main/ai/chat/composables/useStreamChat.ts src/views/Main/ai/chat/components/MessageList/index.vue
git -C E:\admin\admin_front_ts commit -m "feat(ai): render chat message blocks"
```

---

## Task 7: End-to-end verification

**Files:** no new files.

- [ ] **Step 1: Backend verification**

```powershell
cd E:\admin\admin_back
php -l app\service\Ai\AiRunEventPublisher.php
php -l app\service\Ai\AiImageIntentDetector.php
php -l app\service\Ai\AiImageGenerationService.php
php -l app\service\Ai\AiChatRunExecutor.php
php -l app\queue\redis\slow\AiRunExecute.php
php -l app\module\Ai\AiChatModule.php
php -l app\enum\AiEnum.php
vendor\bin\phpunit tests\Ai\AiChatRuntime2ContractTest.php
```

Expected: all syntax checks pass and contract test passes.

- [ ] **Step 2: Frontend verification**

```powershell
cd E:\admin\admin_front_ts
npx.cmd vue-tsc -b
npm run build
```

Expected: both pass. Existing chunk-size warnings are acceptable; type errors are not.

- [ ] **Step 3: Manual runtime check**

```text
1. Open AI chat page.
2. Send: 你好，简单介绍一下你自己。
3. Confirm text streams and final assistant message persists.
4. Send: /image 一张赛博朋克风格的猫咪头像，霓虹灯，高清。
5. Confirm the assistant message first shows generation progress, then displays an image card.
6. Refresh the page.
7. Confirm the image message is still visible from ai_messages.meta_json.blocks.
```

If image model is not configured, expected behavior is not a silent UI break. It must show an explicit upstream error and `ai_runs.run_status=3`.

- [ ] **Step 4: Clean diff check**

```powershell
git -C E:\admin\admin_back diff --check
git -C E:\admin\admin_front_ts diff --check
git -C E:\admin\admin_back status --short --branch
git -C E:\admin\admin_front_ts status --short --branch
```

Expected: no whitespace errors.

---

## Self-review notes

- Spec coverage: Run split is covered by Tasks 2, 4, 5. Image generation is covered by Tasks 3, 4, 6, 7. Message blocks are covered by Task 6.
- Scope control: WebSocket notifications are intentionally excluded. This keeps streamable HTTP as current-page UX and uses Redis Stream only as backend event relay.
- Compatibility: Existing frontend event names are preserved; old text messages continue to render through `content` fallback.
- Risk: `AiImageGenerationService` uses the selected agent model. If that model does not support `/images/generations`, runtime fails visibly. A later improvement can add `runtime_config_json.image.model_id`, but first implementation must not depend on capability metadata.
