<?php
/**
 * AI 模块配置
 */

return [
    // API Key 加密密钥（从 .env 读取，必须 32 字节）
    'vault_key' => getenv('AI_VAULT_KEY', ''),
];
