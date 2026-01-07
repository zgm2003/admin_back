<?php

namespace app\enum;

class AiEnum
{
    // AI 驱动类型
    const DRIVER_OPENAI = 'openai';
    const DRIVER_CLAUDE = 'claude';
    const DRIVER_QWEN = 'qwen';
    const DRIVER_WENXIN = 'wenxin';
    const DRIVER_ZHIPU = 'zhipu';
    const DRIVER_MOONSHOT = 'moonshot';
    const DRIVER_DEEPSEEK = 'deepseek';
    const DRIVER_HUNYUAN = 'hunyuan';

    public static $driverArr = [
        self::DRIVER_OPENAI => 'OpenAI',
        self::DRIVER_CLAUDE => 'Claude',
        self::DRIVER_QWEN => 'Qwen',
        self::DRIVER_WENXIN => '文心一言',
        self::DRIVER_ZHIPU => '智谱',
        self::DRIVER_MOONSHOT => 'Moonshot',
        self::DRIVER_DEEPSEEK => 'DeepSeek',
        self::DRIVER_HUNYUAN => '混元',
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

    // AI 运行状态 (ai_runs.run_status)
    const RUN_STATUS_RUNNING = 1;   // 运行中
    const RUN_STATUS_SUCCESS = 2;   // 成功
    const RUN_STATUS_FAIL = 3;      // 失败
    const RUN_STATUS_CANCELED = 4;  // 已取消

    public static $runStatusArr = [
        self::RUN_STATUS_RUNNING => '运行中',
        self::RUN_STATUS_SUCCESS => '成功',
        self::RUN_STATUS_FAIL => '失败',
        self::RUN_STATUS_CANCELED => '已取消',
    ];

    // AI 运行步骤类型 (ai_run_steps.step_type)
    const STEP_TYPE_PROMPT = 1;      // 提示词构建
    const STEP_TYPE_RAG = 2;         // RAG 检索
    const STEP_TYPE_LLM = 3;         // LLM 调用
    const STEP_TYPE_TOOL_CALL = 4;   // 工具调用
    const STEP_TYPE_TOOL_RESULT = 5; // 工具返回
    const STEP_TYPE_FINALIZE = 6;    // 最终化

    public static $stepTypeArr = [
        self::STEP_TYPE_PROMPT => '提示词构建',
        self::STEP_TYPE_RAG => 'RAG检索',
        self::STEP_TYPE_LLM => 'LLM调用',
        self::STEP_TYPE_TOOL_CALL => '工具调用',
        self::STEP_TYPE_TOOL_RESULT => '工具返回',
        self::STEP_TYPE_FINALIZE => '最终化',
    ];

    // 步骤状态 (ai_run_steps.status)
    const STEP_STATUS_SUCCESS = 1;
    const STEP_STATUS_FAIL = 2;

    public static $stepStatusArr = [
        self::STEP_STATUS_SUCCESS => '成功',
        self::STEP_STATUS_FAIL => '失败',
    ];
}
