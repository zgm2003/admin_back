<?php

namespace app\enum;

class AiEnum
{
    // AI 驱动类型（与 Neuron AI Provider 一一对应）
    const DRIVER_OPENAI    = 'openai';
    const DRIVER_CLAUDE    = 'claude';
    const DRIVER_DEEPSEEK  = 'deepseek';
    const DRIVER_GEMINI    = 'gemini';
    const DRIVER_MISTRAL   = 'mistral';
    const DRIVER_COHERE    = 'cohere';
    const DRIVER_GROK      = 'grok';
    const DRIVER_OLLAMA    = 'ollama';
    const DRIVER_HUGGINGFACE = 'huggingface';
    // OpenAI 兼容接口（通义千问、Moonshot、智谱、混元、反代服务等）
    const DRIVER_QWEN      = 'qwen';
    const DRIVER_MOONSHOT  = 'moonshot';
    const DRIVER_ZHIPU     = 'zhipu';
    const DRIVER_HUNYUAN   = 'hunyuan';
    const DRIVER_WENXIN    = 'wenxin';

    public static $driverArr = [
        // Neuron AI 原生 Provider
        self::DRIVER_OPENAI      => 'OpenAI',
        self::DRIVER_CLAUDE      => 'Claude',
        self::DRIVER_DEEPSEEK    => 'DeepSeek',
        self::DRIVER_GEMINI      => 'Gemini',
        self::DRIVER_MISTRAL     => 'Mistral',
        self::DRIVER_COHERE      => 'Cohere',
        self::DRIVER_GROK        => 'Grok (xAI)',
        self::DRIVER_OLLAMA      => 'Ollama (本地)',
        self::DRIVER_HUGGINGFACE => 'HuggingFace',
        // OpenAI 兼容（走 OpenAILike）
        self::DRIVER_QWEN        => '通义千问',
        self::DRIVER_MOONSHOT    => 'Moonshot',
        self::DRIVER_ZHIPU       => '智谱',
        self::DRIVER_HUNYUAN     => '混元',
        self::DRIVER_WENXIN      => '文心一言',
    ];

    // 消息角色
    const ROLE_USER = 1;
    const ROLE_ASSISTANT = 2;
    const ROLE_SYSTEM = 3;

    public static $roleArr = [
        self::ROLE_USER => 'user',
        self::ROLE_ASSISTANT => 'assistant',
        self::ROLE_SYSTEM => 'system',
    ];

    // AI 智能体模式
    const MODE_CHAT = 'chat';
    const MODE_RAG = 'rag';
    const MODE_TOOL = 'tool';
    const MODE_WORKFLOW = 'workflow';

    public static $modeArr = [
        self::MODE_CHAT => '对话',
        self::MODE_RAG => 'RAG',
        self::MODE_TOOL => '工具',
        self::MODE_WORKFLOW => '工作流',
    ];

    // AI 智能体能力（chat 默认启用，不作为前端开关）
    const CAPABILITY_CHAT = 'chat';
    const CAPABILITY_TOOLS = 'tools';
    const CAPABILITY_RAG = 'rag';
    const CAPABILITY_WORKFLOW = 'workflow';

    public static $capabilityArr = [
        self::CAPABILITY_TOOLS => '工具调用',
        self::CAPABILITY_RAG => 'RAG知识库',
        self::CAPABILITY_WORKFLOW => '工作流编排',
    ];

    // AI 智能体场景（仅特殊用途，普通对话不设场景）
    const SCENE_GOODS_SCRIPT = 'goods_script';
    const SCENE_CINE_PROJECT = 'cine_project';
    const SCENE_CINE_KEYFRAME = 'cine_keyframe';

    public static $sceneArr = [
        self::SCENE_GOODS_SCRIPT => '商品口播生成',
        self::SCENE_CINE_PROJECT => 'AI短剧工厂',
        self::SCENE_CINE_KEYFRAME => '短剧分镜图片生成',
    ];

    // AI 运行状态 (ai_runs.run_status)
    const RUN_STATUS_RUNNING = 1;
    const RUN_STATUS_SUCCESS = 2;
    const RUN_STATUS_FAIL = 3;
    const RUN_STATUS_CANCELED = 4;

    public static $runStatusArr = [
        self::RUN_STATUS_RUNNING => '运行中',
        self::RUN_STATUS_SUCCESS => '成功',
        self::RUN_STATUS_FAIL => '失败',
        self::RUN_STATUS_CANCELED => '已取消',
    ];

    // AI 运行步骤类型 (ai_run_steps.step_type)
    const STEP_TYPE_PROMPT = 1;
    const STEP_TYPE_RAG = 2;
    const STEP_TYPE_LLM = 3;
    const STEP_TYPE_TOOL_CALL = 4;
    const STEP_TYPE_TOOL_RESULT = 5;
    const STEP_TYPE_FINALIZE = 6;
    const STEP_TYPE_IMAGE = 7;

    public static $stepTypeArr = [
        self::STEP_TYPE_PROMPT => '提示词构建',
        self::STEP_TYPE_RAG => 'RAG检索',
        self::STEP_TYPE_LLM => 'LLM调用',
        self::STEP_TYPE_TOOL_CALL => '工具调用',
        self::STEP_TYPE_TOOL_RESULT => '工具返回',
        self::STEP_TYPE_FINALIZE => '最终化',
        self::STEP_TYPE_IMAGE => '图片生成',
    ];

    // 工具执行器类型
    const EXECUTOR_INTERNAL       = 1;
    const EXECUTOR_HTTP_WHITELIST = 2;
    const EXECUTOR_SQL_READONLY   = 3;

    public static $executorTypeArr = [
        self::EXECUTOR_INTERNAL       => '内置函数',
        self::EXECUTOR_HTTP_WHITELIST => 'HTTP白名单',
        self::EXECUTOR_SQL_READONLY   => '只读SQL',
    ];

    // 知识库可见性（第一版只预留权限，不做复杂 ACL）
    const KNOWLEDGE_VISIBILITY_PRIVATE = 'private';
    const KNOWLEDGE_VISIBILITY_TEAM = 'team';
    const KNOWLEDGE_VISIBILITY_PUBLIC = 'public';

    public static $knowledgeVisibilityArr = [
        self::KNOWLEDGE_VISIBILITY_PRIVATE => '私有',
        self::KNOWLEDGE_VISIBILITY_TEAM => '团队',
        self::KNOWLEDGE_VISIBILITY_PUBLIC => '公开',
    ];

    // 知识库文档索引状态
    const KNOWLEDGE_INDEX_INDEXED = 1;
    const KNOWLEDGE_INDEX_FAILED = 2;

    public static $knowledgeIndexStatusArr = [
        self::KNOWLEDGE_INDEX_INDEXED => '已索引',
        self::KNOWLEDGE_INDEX_FAILED => '索引失败',
    ];

    // 知识库文档来源
    const KNOWLEDGE_SOURCE_MANUAL = 'manual';
    const KNOWLEDGE_SOURCE_TEXT = 'text';
    const KNOWLEDGE_SOURCE_FILE = 'file';
    const KNOWLEDGE_SOURCE_URL = 'url';

    public static $knowledgeSourceTypeArr = [
        self::KNOWLEDGE_SOURCE_MANUAL => '手动录入',
        self::KNOWLEDGE_SOURCE_TEXT => '文本',
        self::KNOWLEDGE_SOURCE_FILE => '文件',
        self::KNOWLEDGE_SOURCE_URL => 'URL',
    ];

    // 步骤状态 (ai_run_steps.status)
    const STEP_STATUS_SUCCESS = 1;
    const STEP_STATUS_FAIL = 2;

    public static $stepStatusArr = [
        self::STEP_STATUS_SUCCESS => '成功',
        self::STEP_STATUS_FAIL => '失败',
    ];
}
