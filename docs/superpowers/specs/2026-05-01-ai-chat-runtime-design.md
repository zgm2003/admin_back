# AI Chat Runtime 2.0 设计规格

日期：2026-05-01  
范围：`E:\admin\admin_back`、`E:\admin\admin_front_ts` 的 AI 对话链路。  
目标：把 AI 对话从“请求里同步跑模型”的玩具形态，升级为“Run 驱动、可流式、可恢复、可多模态、上下文可追踪”的产品形态。

## 1. 结论

第一刀不做 Coze 画布，不把“生成完成通知”当核心，也不为了 WebSocket 重写全站。

正确方向是：

```text
Run-first AI Chat Runtime
+ 当前页面继续流式输出
+ 模型/图片/长工具调用从 HTTP 请求生命周期里拆出去
+ 最终消息落库
+ 消息支持 text/image/tool/error blocks
+ 上下文按 Agent 默认、场景、RAG、会话、单次消息分层组装
```

协议策略：

```text
当前页实时文本：streamable HTTP 订阅 run events
后台执行：Redis Queue worker
事件中转：Redis Stream / 内存事件发布，短 TTL
最终真相：ai_runs / ai_run_steps / ai_messages
WebSocket：保留基础设施，后续用于全局状态/通知，不作为第一刀
```

这不是“继续旧 SSE”。旧问题在于请求里直接跑模型；新方案里 streamable HTTP 只订阅事件，不执行重活。

## 2. Linus 三问

### 2.1 这是个真问题吗？

是。当前 `AiChatModule::sendStream()` 在 HTTP 长连接里直接调用 `AiChatService::chatStream()`。这会把模型等待时间绑定到 PHP worker 生命周期。图片生成超时、本页离开流中断、长工具调用卡住，本质都是同一类问题。

### 2.2 有更简单的方法吗？

有。不需要一上来全站 WebSocket 化，也不需要引入新平台。项目已经有：

- `ai_runs`：一次发送的运行记录；
- `ai_run_steps`：RAG、LLM、工具等步骤记录；
- `ai_messages.meta_json`：可承载 blocks；
- Redis Queue：可跑后台任务；
- GatewayWorker：后续可推送状态。

第一刀只需要把执行和订阅分离。

### 2.3 会破坏什么吗？

不能破坏现有前端调用。`/api/admin/AiChat/stream` 的事件契约先保持：

```text
conversation -> run -> content/tool_call/tool_result -> done/error/canceled
```

内部实现可以改成 queue + event subscription，但前端第一阶段不必大改。

## 3. 非目标

本阶段明确不做：

1. Coze / Dify 那种节点画布。
2. 全局生成完成通知、浏览器通知、消息中心提醒。
3. 纯 WebSocket RPC 聊天。
4. 把模型多模态能力做成模型管理硬配置。
5. 为了消息 blocks 立刻重建消息表。
6. 长期记忆完整系统。RAG 和摘要记忆可以预留，不在本阶段强行做复杂。

## 4. 现状证据

关键现状：

- 后端已有 AI Chat stream 路由：`E:/admin/admin_back/routes/admin.php`。
- 现有 stream 是 HTTP POST + SSE 风格事件：`E:/admin/admin_back/app/controller/Ai/AiChatController.php`。
- 现有前端是 `fetch POST + ReadableStream`，不是原生 `EventSource`：`E:/admin/admin_front_ts/src/lib/http/stream.ts`。
- 后端当前在 `sendStream()` 内直接跑模型流：`E:/admin/admin_back/app/module/Ai/AiChatModule.php`。
- 项目已有 WebSocket/GatewayWorker：`E:/admin/admin_back/config/plugin/webman/gateway-worker/process.php`。
- 布局层已初始化 WebSocket：`E:/admin/admin_front_ts/src/views/Layout/index.vue`。
- AI 消息已有 `meta_json` 字段，适合第一版承载 blocks：`ai_messages.meta_json`。
- AI Run 已有 `meta_json` 字段，适合记录上下文快照和运行参数覆盖：`ai_runs.meta_json`。

## 5. 目标架构

### 5.1 新运行链路

```text
前端调用 /AiChat/stream
  -> 后端校验参数
  -> 创建/确认 conversation
  -> 保存 user message
  -> 创建 ai_run
  -> 立即返回 conversation/run 事件
  -> 投递 ai_chat_run 队列任务
  -> 当前 HTTP 连接只订阅 run event stream

Queue worker 执行 ai_chat_run
  -> 加载 agent/model/conversation/messages
  -> 组装上下文
  -> 执行 RAG / LLM / tool / image step
  -> 写 ai_run_steps
  -> 发布 run events
  -> 最终写 ai_messages
  -> markSuccess / markFailed

前端
  -> 收到 content delta 实时渲染
  -> 收到 done 后把临时消息 ID 替换成真实 message_id
  -> 刷新/切回时从 ai_messages 拉最终状态
```

### 5.2 为什么这样不堵 PHP

旧方案：

```text
HTTP worker = 等模型 + 发流
```

新方案：

```text
Queue worker = 等模型
HTTP stream worker = 只读 run events 并转发
```

HTTP stream 连接仍然是长连接，但它不再承担模型执行。压力从“每个连接卡一个模型调用”降为“每个连接读轻量事件”。图片生成、文件理解、长工具调用不再占住请求 worker。

## 6. 传输协议决策

### 6.1 第一阶段：streamable HTTP 作为当前页通道

继续保留 `AiChatApi.stream()` 的调用形态，避免前端大面积破坏。

但后端语义变更为：

```text
stream endpoint = submit + subscribe
不是 submit + execute
```

这样当前页面仍然有 ChatGPT 式打字效果，且不需要先把 AI Chat 改成 WebSocket 客户端。

### 6.2 WebSocket 的位置

WebSocket 是后续增强，不是第一刀核心。

后续适合承载：

- run 状态同步；
- 图片完成事件；
- 多标签页同步；
- 全局提醒；
- 移动端/桌面端后台推送。

但第一阶段不为“生成完成通知”改变主链路。

### 6.3 禁止的坏设计

禁止把模型调用写进 WebSocket `onMessage()`。那只是把 HTTP worker 堵塞换成 WebSocket worker 堵塞，味道更差。

## 7. Run Event 设计

第一阶段事件保持兼容，同时内部统一为 run event：

```text
conversation
run
content
tool_call
tool_result
image_generating
image_done
done
error
canceled
```

事件载荷原则：

- `content` 只发增量文本；
- `image_done` 只发图片 URL / asset_id / block_id；
- `tool_*` 发工具名、调用 ID、输入输出摘要；
- `done` 发 conversation_id、run_id、user_message_id、assistant_message_id；
- `error` 发可读错误，不吞掉上游错误。

Redis Stream 建议：

```text
key: ai:run:{run_id}:events
id: Redis stream id
ttl: 24h
```

数据库不保存每个 token delta。数据库只保存最终消息、run、step。否则 token 级写库是低品味浪费。

## 8. 消息 Blocks 设计

不立刻重建消息表。第一版沿用：

```text
ai_messages.content = 文本 fallback
ai_messages.meta_json.blocks = 结构化内容块
```

示例：

```json
{
  "blocks": [
    { "type": "text", "text": "我给你生成了一张短剧封面：" },
    { "type": "image", "url": "https://cdn.example/cover.png", "asset_id": 123, "alt": "短剧封面" },
    { "type": "tool", "name": "image_generation", "status": "success" }
  ],
  "run_request_id": "...",
  "provider_request_id": "..."
}
```

兼容规则：

- 没有 blocks：前端按旧 `content` 渲染。
- 有 blocks：前端优先渲染 blocks，`content` 作为复制、搜索、旧页面 fallback。
- 图片生成失败：可写 error block，但不伪装成正常图片。

## 9. 图片生成链路

图片生成不能再卡 HTTP 180 秒。

目标链路：

```text
用户要求生成图片
  -> 保存 user message
  -> 创建 ai_run
  -> worker 识别需要 image step
  -> 发布 image_generating
  -> 调用图片服务
  -> 上传/保存资产
  -> 发布 image_done
  -> 写 assistant message blocks
  -> markSuccess
```

第一版可以先用明确按钮/命令触发图片能力，后续再让 Agent 自动决定是否调用 image tool。

模型是否支持图片生成，不在模型管理里硬拦。运行时报错就进入 `run_failed` 或 error block，并在 run step 中记录上游错误。

## 10. 上下文配置边界

### 10.1 配置优先级

```text
平台硬限制
> 模型运行限制
> Agent 默认配置
> 场景 overlay
> 会话临时配置
> 单条消息输入/附件
```

### 10.2 Agent 配置：稳定身份和默认能力

Agent 配置负责“这个智能体默认是谁、会什么、怎么工作”。

放在 Agent：

```json
{
  "generation": {
    "temperature": 0.7,
    "max_tokens": 4096
  },
  "context": {
    "mode": "auto",
    "max_history": 20,
    "token_budget": 12000,
    "rag_top_k": 5,
    "summary_enabled": false
  },
  "image": {
    "size": "1024x1024",
    "quality": "auto"
  }
}
```

字段位置：`ai_agents.runtime_config_json`。

Agent 还负责：

- `system_prompt`；
- `capabilities_json`；
- `scene_codes`；
- 绑定工具；
- 绑定知识库；
- policy 限制。

### 10.3 对话配置：当前会话偏好

对话配置只做临时覆盖，不污染 Agent。

第一版不新增 `ai_conversations.meta_json`，避免多打一刀迁移。会话级覆盖先随请求进入 `ai_runs.meta_json.runtime_overrides`，用于追踪和复现。

后续如果确实需要“这个会话永久使用深度上下文”，再给 `ai_conversations` 加 `runtime_config_json` 或 `meta_json`。

### 10.4 输入框高级设置

输入框不应该暴露一堆裸参数吓用户。

推荐 UI：

```text
上下文：自动 / 精简 / 标准 / 深度 / 无上下文
创造性：严谨 / 均衡 / 发散
输出长度：短 / 标准 / 长
```

后端再映射成：

```text
max_history
temperature
max_tokens
summary_enabled
rag_top_k
token_budget
```

保留高级 JSON/数值调参给管理端，不作为普通聊天默认入口。

## 11. 上下文组装顺序

每次 run 固定按这个顺序组装：

```text
1. Agent system_prompt
2. Scene prompt overlay
3. Policy / tool usage instruction
4. RAG retrieved chunks
5. Conversation summary（后续）
6. Recent message history
7. Current user message
8. Current attachments / image references
```

要求：

- 最终上下文快照写入 `ai_runs.meta_json.context_snapshot` 的摘要版本；
- RAG chunk 来源写入 `ai_run_steps.payload_json`；
- 不把完整超长 prompt 全量塞进列表接口；
- run detail 可以查看关键上下文来源。

## 12. 前端产品形态

第一阶段保留现有聊天页结构，重点改三件事：

1. `AiChatApi.stream()` 继续消费兼容事件。
2. `MessageList` 支持 `meta_json.blocks`。
3. `MessageInput` 的参数面板从裸温度/历史条数，逐步收敛成上下文/创造性/输出长度三个产品化选项。

图片生成完成后，当前页展示图片卡片；不在页面时通知不做。

## 13. 错误处理

错误不能静默，也不能只弹一句“失败”。

规则：

- Worker 捕获上游异常；
- 写 `ai_run_steps.status=fail`；
- 写 `ai_runs.run_status=fail`；
- 发布 `error` event；
- 前端移除 streaming 状态，保留用户消息；
- 如果已有部分 assistant 文本，可以保存为 partial block，并标记 `finish_reason=error`。

图片失败示例：

```json
{
  "type": "error",
  "scope": "image_generation",
  "message": "cURL error 28: Operation timed out after 180011 milliseconds"
}
```

## 14. 实施阶段

### Phase 1：执行和订阅分离

- 新增 `ai_chat_run` 队列任务。
- 抽出 `AiChatRunExecutor`，负责真正执行 LLM/RAG/tool。
- 新增 `AiRunEventPublisher`，写 Redis Stream。
- `/AiChat/stream` 改成创建 run + 投递任务 + 订阅事件。
- 保持前端事件契约不变。

### Phase 2：消息 blocks

- 后端保存 assistant message 时支持 `meta_json.blocks`。
- 前端 MessageList 优先渲染 blocks。
- 旧纯文本消息不受影响。

### Phase 3：图片生成异步化

- 把聊天里的图片生成接为 run step。
- 发布 `image_generating` / `image_done`。
- 图片结果进入 blocks。
- 超时变成 run step 错误，不拖死 HTTP 请求。

### Phase 4：上下文配置产品化

- Agent 默认 runtime config。
- 请求级 context preset。
- run detail 展示上下文来源。
- 输入框高级设置降噪。

### Phase 5：WebSocket 增强

- 如果需要，再把 run event 同步推给 WebSocket。
- 用于多标签页、全局状态、通知中心。
- 不影响第一阶段主链路。

## 15. 验证标准

后端：

- `php -l` 覆盖新增/修改 PHP 文件。
- PHPUnit 合同测试覆盖：
  - stream endpoint 不直接执行模型；
  - run job 会发布 content/done/error；
  - blocks 写入保持 content fallback；
  - context override 写入 run meta；
  - 图片超时进入 failed step。

前端：

- `npx.cmd vue-tsc -b`。
- `npm run build`。
- 手测：
  - 普通文本流式输出；
  - 取消生成；
  - 刷新后最终消息仍存在；
  - blocks 图片消息展示；
  - 旧纯文本消息展示不坏。

## 16. 最终取舍

本方案的关键不是“WebSocket 比 SSE 高级”，而是把重活从连接请求里拿出去。

第一刀选择：

```text
保留 streamable HTTP 的产品体验
重写后端执行模型为 queue run executor
用 Redis Stream 做当前页事件订阅
用 message blocks 支撑多模态
把上下文配置分层并可追踪
```

这条路最小破坏、最符合现有 Webman/Vue 架构，也能直接解决图片超时和 AI 对话产品力不足的问题。
