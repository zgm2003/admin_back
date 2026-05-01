# Agent 2.0 / 超级智能体架构设计

日期：2026-05-01  
范围：`E:\admin` 工作区，涉及 `admin_back` 与 `admin_front_ts`，本文件只写设计，不实现业务代码。

## 1. 结论

当前 `chat / rag / tool / workflow` 四选一模式是错误抽象。它把“能力”当成了“身份”，导致工具、RAG、Workflow 互斥，智能体天然被做弱。

Agent 2.0 的目标不是再加一个模式，而是把系统升级成：

```text
统一 Agent Runtime
+ 默认可对话
+ 能力可挂载
+ 场景可多选
+ 工具 / RAG / Workflow / 图片 / 文件 / 记忆按需启用
+ 运行时错误可观测
```

模型管理不负责硬判断“是否支持多模态”。模型能不能画图、看图、调用工具，运行时真实调用验证；失败就记录 `ai_runs / ai_run_steps` 并返回清晰错误。

## 2. 当前代码问题

已核实的关键点：

- `AiEnum::$modeArr` 当前定义为 `chat/rag/tool/workflow` 互斥模式：`E:/admin/admin_back/app/enum/AiEnum.php:55`。
- `AiAgentsModule::add()` 只有 `mode=tool` 才同步工具绑定：`E:/admin/admin_back/app/module/Ai/AiAgentsModule.php:115`。
- `AiAgentsModule::edit()` 只有 `mode=tool` 才更新工具绑定：`E:/admin/admin_back/app/module/Ai/AiAgentsModule.php:159`。
- `NeuronAgentFactory::createAgent()` 只有 `mode=tool` 才注入工具：`E:/admin/admin_back/app/lib/Ai/NeuronAgentFactory.php:158`。
- 前端只有 `form.mode === 'tool'` 才展示工具选择：`E:/admin/admin_front_ts/src/views/Main/ai/agents/index.vue:259`。
- `scene` 当前是单字段单选：`E:/admin/admin.sql:48`，无法让同一个 Agent 同时服务“短剧 / 对话 / 电商口播”等多个场景。

这会产生三个直接后果：

1. 不能做“全能 Agent”。
2. 不能做“多场景 Agent”。
3. 不能把工具、RAG、Workflow 组合起来。

## 3. 设计原则

### 3.1 Good Taste：能力不是模式

旧模型：

```text
Agent.mode = chat | rag | tool | workflow
```

新模型：

```text
Agent.capabilities = {
  chat: true,
  tools: true,
  rag: false,
  workflow: false,
  image: true,
  file: false,
  memory: true
}
```

`chat` 是默认能力，不需要用户选择。`tools/rag/workflow/image/file/memory` 是可挂载能力。

### 3.2 不破坏现有场景

现有短剧、电商口播、对话能力不能被重做砸烂。`mode` 和 `scene` 字段保留为兼容字段；新逻辑优先使用新表和新 JSON，旧数据迁移后仍能跑。

### 3.3 场景是业务入口，不是 Agent 身份

场景用于决定：

- 哪个业务入口能选择这个 Agent；
- 该场景追加什么提示词；
- 该场景允许哪些工具 / 配置覆盖；
- 该场景的运行参数。

场景必须支持多选。

### 3.4 模型能力运行时验证

不在模型管理里硬阻断多模态。运行时根据具体能力发起调用：

```text
用户请求图片生成
→ Agent Runtime 调用图片能力
→ 上游不支持 / 超时 / 返回错误
→ ai_runs 标记失败
→ ai_run_steps 记录 image/tool/llm 错误
→ UI 展示明确错误，可重试
```

## 4. 开源组件调研与取舍

### 4.0 开源优先硬约束

本项目的 Agent 2.0 组件路线按 **OSS-first** 执行：

```text
优先级 1：已在项目内使用、许可证宽松、能直接融入 PHP/Vue 栈的开源组件
优先级 2：可自托管、协议开放、能通过适配层接入的开源组件
优先级 3：只作为产品/交互参考，不内嵌、不绑定运行时的平台级项目
拒绝项：云端黑盒、闭源 SDK 锁死核心链路、许可证不清、为了“看起来高级”强行引入第二套主运行时
```

第一版允许引入开源组件，但必须满足三条：

1. **不能替代现有 Webman + Vue 主架构**。否则就是另起炉灶，不是升级。
2. **不能让短剧、电商口播、对话现有场景断掉**。先兼容，后增强。
3. **必须能回滚**。新增依赖必须集中在能力层或 UI 增强层，不能散进业务流程里。

开源候选表：

| 组件 | 许可证/开放性 | 定位 | 第一版动作 |
| --- | --- | --- | --- |
| Neuron AI | MIT | PHP Agent Runtime，覆盖 Agent/Tools/RAG/MCP/Workflow | 保留为核心 |
| MCP | 开放协议/SDK | 工具生态入口 | 预留表和适配层 |
| Vue Flow | MIT | Vue 3 节点画布 | Phase 3 引入 |
| Monaco Editor | MIT | Prompt/JSON/Policy 编辑器 | 可提前引入 |
| Qdrant | Apache-2.0 | RAG 向量库 | RAG 成熟后引入 |
| Langfuse | 核心 MIT，部分 EE 目录另有许可 | LLM trace/评估/Prompt 管理 | 本地 trace 不够时再接 |
| Flowise | Apache-2.0 | 可视化 AI Agent 产品参考 | 参考，不嵌入 |
| Node-RED | Apache-2.0 | 流程编排产品参考 | 参考，不嵌入 |
| Rete.js | MIT | 通用节点编辑器 | 作为 Vue Flow 备选 |

结论：**开源不是把一整套 Dify/Flowise 塞进来。开源也要有品味。** 第一刀仍然是把现有 Agent 抽象改对；组件只在该出现的层出现。

来源：

- Neuron AI GitHub / MIT: https://github.com/neuron-core/neuron-ai
- Vue Flow GitHub / MIT: https://github.com/bcakmakoglu/vue-flow
- Monaco Editor GitHub / MIT: https://github.com/microsoft/monaco-editor
- Qdrant GitHub / Apache-2.0: https://github.com/qdrant/qdrant
- Langfuse GitHub / License: https://github.com/langfuse/langfuse
- Flowise GitHub / Apache-2.0: https://github.com/FlowiseAI/Flowise
- Node-RED GitHub / Apache-2.0: https://github.com/node-red/node-red
- Rete.js GitHub / MIT: https://github.com/retejs/rete

### 4.1 保留 Neuron AI 作为核心 Agent Runtime

当前后端已安装 `neuron-core/neuron-ai:^3.0`：`E:/admin/admin_back/composer.json:50`。本地 vendor 已有 `Agent / Tools / RAG / MCP / Workflow` 目录。

官方文档也证明 Neuron 覆盖我们要的核心能力：

- Agent 支持 memory、tools、function calls。
- RAG 是 Agent 的扩展，RAG agent 仍然可以挂 tools。
- MCP Connector 可以把 MCP server 暴露的工具接入 Agent。
- Workflow 是事件驱动、节点化、支持流式和 human-in-the-loop 的编排层。

决策：第一版不引入 LangGraph / Dify 作为主运行时，先把 Neuron 打穿。否则会把 PHP 后端切成 Python/Node/PHP 三套运行时，复杂度立刻爆炸。

来源：

- Neuron Agent: https://docs.neuron-ai.dev/neuron-v3/agent/agent
- Neuron Tools: https://docs.neuron-ai.dev/agent/tools
- Neuron RAG: https://docs.neuron-ai.dev/neuron-v3/rag/rag
- Neuron MCP Connector: https://docs.neuron-ai.dev/advanced/mcp-connector
- Neuron Workflow: https://docs.neuron-ai.dev/neuron-v3/workflow/getting-started

### 4.2 MCP：作为工具生态入口

MCP 适合作为“超级智能体工具市场”的协议边界。我们不需要一开始实现完整 MCP Studio，但可以预留：

```text
ai_mcp_servers
ai_mcp_tools_cache
Agent capabilities.tools + mcp_server_ids
```

短期仍使用现有 `ai_tools` + `ai_assistant_tools`；中期接入 Neuron `McpConnector`。

来源：

- MCP SDKs: https://modelcontextprotocol.io/docs/sdk
- Neuron MCP Connector: https://docs.neuron-ai.dev/advanced/mcp-connector

### 4.3 Vue Flow：后续做 Workflow/能力画布

`admin_front_ts` 是 Vue 3。Vue Flow 官方支持 Vue 3 的交互图，具备缩放、拖拽、自定义节点、边、嵌套节点、Controls、MiniMap 等能力。适合做后续 Agent Studio 的工作流画布。

但第一版不建议马上引入画布。先把配置模型改对；画布是第二阶段。

来源：https://vueflow.dev/

### 4.4 Monaco Editor：Prompt / JSON Schema / Policy 编辑器

Monaco 是 VS Code 同源的浏览器编辑器，适合编辑：

- system prompt；
- tool JSON Schema；
- runtime_config_json；
- policy_json；
- workflow DSL。

第一版可以暂时用 Element Plus textarea；如果要把配置页做得像产品，Monaco 是合理引入。

来源：https://github.com/microsoft/monaco-editor

### 4.5 Qdrant：RAG 向量库候选

Qdrant 是成熟向量数据库，适合后续知识库/RAG。第一版如果还没做知识库，不需要先上 Qdrant；但表结构应该预留 `knowledge_ids` / `ai_agent_knowledge`。

来源：https://qdrant.tech/documentation/overview/what-is-qdrant/

### 4.6 Langfuse：观测平台候选，不作为第一刀

现有数据库已有 `ai_runs`、`ai_run_steps`，能覆盖第一版 trace。Langfuse 可以自托管，适合更强的 LLM observability、prompt 管理、评估、trace 图。

但它会带来 Postgres、ClickHouse、Redis、对象存储等部署成本。第一版不引入，先把本地 `ai_runs / ai_run_steps` 用起来；后面如果需要统一 LLMOps，再接 Langfuse。

来源：https://langfuse.com/self-hosting

### 4.7 LangGraph / Dify：参考，不内嵌

LangGraph 强在持久化状态、人类审批、长流程 agent orchestration，但引入它意味着增加 Node/Python 运行时和跨服务协议。Dify 是完整平台，不是轻量组件，适合参考产品形态，不适合塞进现有 Webman 后端。

决策：不作为第一版依赖。可以参考它们的“节点编排、状态持久化、工具审批、trace UI”思想。

来源：

- LangGraph.js: https://langchain-ai.github.io/langgraphjs/reference/modules/langgraph.html
- Dify: https://dify.ai/

## 5. 推荐方案

### 方案 A：最小 JSON 改造

只给 `ai_agents` 加：

```text
capabilities_json
scene_codes_json
runtime_config_json
policy_json
```

优点：改得快。  
缺点：场景查询、场景约束、场景配置会变烂；后面一定补债。

不推荐。

### 方案 B：轻量规范化，推荐

保留 `ai_agents` 主表，新增多场景绑定表：

```text
ai_agents
├─ capabilities_json
├─ runtime_config_json
├─ policy_json
├─ mode       // 兼容旧数据
└─ scene      // 兼容旧数据

ai_agent_scenes
├─ agent_id
├─ scene_code
├─ prompt_overlay
├─ config_json
├─ status
├─ is_del
├─ created_at
└─ updated_at
```

工具继续用现有：

```text
ai_tools
ai_assistant_tools
```

优点：改动有限，场景多选干净，兼容旧业务。  
缺点：RAG/Workflow 绑定暂时还不是完整平台化。

推荐。

### 方案 C：完整 Agent Studio 平台

一次性引入：

- Vue Flow 画布；
- Monaco；
- Qdrant；
- MCP Server 管理；
- Langfuse；
- Workflow DSL；
- Knowledge Base 管理；
- Tool Marketplace。

优点：看起来很牛。  
缺点：范围过大，容易半年做成半残废。

不推荐第一版。

## 6. 第一版数据结构

### 6.1 修改 `ai_agents`

新增字段：

```sql
ALTER TABLE `ai_agents`
  ADD COLUMN `capabilities_json` json NULL COMMENT 'Agent能力配置：tools/rag/workflow/image/file/memory' AFTER `scene`,
  ADD COLUMN `runtime_config_json` json NULL COMMENT '运行参数：timeout/retry/max_tokens/reasoning等' AFTER `capabilities_json`,
  ADD COLUMN `policy_json` json NULL COMMENT '权限策略：工具调用上限、审批、失败策略等' AFTER `runtime_config_json`;
```

默认能力：

```json
{
  "chat": true,
  "tools": false,
  "rag": false,
  "workflow": false,
  "image": false,
  "file": false,
  "memory": true
}
```

兼容迁移：

```text
mode=tool      -> capabilities.tools=true
mode=rag       -> capabilities.rag=true
mode=workflow  -> capabilities.workflow=true
其他           -> chat=true,memory=true
```

### 6.2 新增 `ai_agent_scenes`

```sql
CREATE TABLE `ai_agent_scenes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int unsigned NOT NULL COMMENT 'ai_agents.id',
  `scene_code` varchar(60) NOT NULL COMMENT '场景编码，如 goods_script/cine_project/cine_keyframe',
  `prompt_overlay` text NULL COMMENT '该场景追加提示词',
  `config_json` json NULL COMMENT '该场景运行配置覆盖',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '2正常 1删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_agent_scene` (`agent_id`, `scene_code`),
  KEY `idx_scene_status_del` (`scene_code`, `status`, `is_del`),
  KEY `idx_agent_del` (`agent_id`, `is_del`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI智能体多场景绑定';
```

迁移旧数据：

```text
ai_agents.scene 不为空
→ 插入 ai_agent_scenes(agent_id, scene_code)
→ 保留 ai_agents.scene 作为兼容字段
```

## 7. 后端设计

### 7.1 AiAgentsValidate

新增可校验字段：

```text
capabilities_json / capabilities
scene_codes
scene_configs
runtime_config_json / runtime_config
policy_json / policy
```

`mode` 保留但不再控制工具绑定。

### 7.2 AiAgentsModule

新增/调整职责：

- `init()` 返回：
  - `ai_capability_arr`
  - `ai_scene_arr`
  - `model_list`
  - `tool_list`
- `add()`：
  - 保存基础字段；
  - 保存 capabilities；
  - 同步 scene_codes 到 `ai_agent_scenes`；
  - 只要传了 `tool_ids` 就同步工具，不再要求 `mode=tool`。
- `edit()`：同上。
- `list()`：返回：
  - `capabilities`
  - `scene_codes`
  - `scene_names`
  - `tool_count`

### 7.3 AiAgentsDep

新增查询方法：

```text
getActiveBySceneCode(sceneCode)
getActiveBySceneCodes(sceneCodes)
syncScenes(agentId, sceneCodes, sceneConfigs)
getSceneCodesByAgentIds(agentIds)
```

旧方法 `getActiveByScene()` 保留，但内部优先查 `ai_agent_scenes`，查不到再 fallback 到 `ai_agents.scene`。

### 7.4 NeuronAgentFactory

核心改动：

```text
读取 capabilities_json
合并 runtime_config_json + 调用方 runtimeParams
如果 capabilities.tools=true 且 disable_tools=false → 加载工具
如果 capabilities.rag=true → 进入 RAG 构造路径（第一版可预留）
如果 capabilities.workflow=true → 进入 Workflow 构造路径（第一版可预留）
不再使用 mode=tool 判断是否注入工具
```

图片能力不通过模型管理硬校验；调用失败进入 `ai_runs / ai_run_steps`。

### 7.5 场景调用链

现有业务继续传 scene：

```text
GoodsProcess -> scene=goods_script
CineProcess -> scene=cine_project
CineImageProcess -> scene=cine_keyframe
Chat -> scene=chat/general
```

Runtime 根据 scene 找 Agent：

```text
scene_code
→ ai_agent_scenes
→ ai_agents
→ model
→ capabilities/tools/policy/runtime
→ NeuronAgentFactory
```

## 8. 前端设计

当前组件可以支撑第一版，不需要重写 UI 框架。

配置页改成四块：

### 8.1 基础配置

- 名称
- 模型
- 头像
- 状态
- 系统提示词

### 8.2 能力配置

用 checkbox/switch：

```text
工具调用
RAG知识库
工作流编排
图片生成/理解
文件理解
长期记忆
```

不要再让用户选 `chat/rag/tool/workflow` 单选模式。

### 8.3 场景绑定

`el-select-v2 multiple`：

```text
goods_script
cine_project
cine_keyframe
chat/general
future scenes
```

每个场景后续可展开配置：场景提示词覆盖、工具限制、运行参数覆盖。

### 8.4 资源绑定

第一版先做工具绑定：

- 只要 capabilities.tools=true，就展示工具选择。
- 绑定工具不再依赖 `mode=tool`。

后续再加：

- 知识库绑定；
- Workflow 绑定；
- MCP Server 绑定。

## 9. 错误处理与观测

当前已有：

- `ai_runs`
- `ai_run_steps`

第一版要把它们用扎实：

```text
run: 整体请求
step: prompt / rag / llm / tool_call / tool_result / image / finalize
```

图片生成超时这类错误必须记录：

```text
step_type=image 或 tool_call
status=fail
error_msg=cURL error 28: Operation timed out after 180011 milliseconds...
payload_json={ provider, endpoint, timeout, scene_code, agent_id }
```

UI 只需要展示：

```text
图片生成超时：上游 180 秒无响应。你可以重试，或调大超时/降低图片数量。
```

不要吞错，不要伪成功。

## 10. 分阶段实施

### Phase 1：Agent 2.0 配置模型

目标：把抽象改对。

- 新增 `capabilities_json/runtime_config_json/policy_json`。
- 新增 `ai_agent_scenes`。
- 工具绑定脱离 `mode=tool`。
- 前端改成“能力开关 + 场景多选”。
- 旧数据迁移。

### Phase 2：运行时增强

目标：让能力真正进 Runtime。

- `NeuronAgentFactory` 基于 capabilities 注入工具。
- 场景查询走 `ai_agent_scenes`。
- `ai_runs/ai_run_steps` 记录图片、工具、LLM 失败。
- 图片生成超时错误可观测、可重试。

### Phase 3：Agent Studio 增强

目标：变成产品级配置体验。

可引入：

- Monaco Editor：Prompt/JSON/Policy 编辑。
- Vue Flow：Workflow/能力画布。
- MCP Connector：外部工具生态。
- Qdrant：正式 RAG 知识库。
- Langfuse：如果本地 trace 不够，再接 LLMOps。

## 11. 不做的事

第一版不做：

- 不重写聊天系统。
- 不接 Dify 当主平台。
- 不上 LangGraph 当主运行时。
- 不做完整 Workflow 画布。
- 不做模型多模态硬阻断。
- 不做大而全知识库后台。

这些不是没价值，是现在做会把第一刀搞烂。

## 12. 验收标准

第一版验收：

1. 一个 Agent 可以同时绑定多个场景。
2. 一个 Agent 可以同时启用对话、工具、图片、记忆等能力。
3. 工具绑定不再依赖 `mode=tool`。
4. 旧的短剧、电商口播调用不坏。
5. 图片生成超时能在运行记录里看到明确错误。
6. 前端配置页不再出现“模式四选一”的主导交互。
7. `mode` 字段只作为兼容字段存在，不再决定 Runtime 能力。

## 13. 最终判断

这次不应该继续补丁式修 `mode=tool`。那是在屎山上刷漆。

正确第一刀是：

```text
Agent 仍然是 Agent
能力是能力
场景是场景
资源是资源
运行时负责真实执行与真实报错
```

这就是 Agent 2.0 的骨架。
