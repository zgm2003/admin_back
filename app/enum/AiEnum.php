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
}
