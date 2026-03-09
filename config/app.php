<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;

return [
    // 生产环境应设置为 false，通过环境变量 APP_DEBUG 控制
    'debug' => getenv('APP_DEBUG', false),
    'error_reporting' => E_ALL,
    'default_timezone' => 'Asia/Shanghai',
    'request_class' => Request::class,
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,

    'cors_allowed_origins' => array_values(array_filter(array_unique(array_merge(
        ['https://zgm2003.cn'],
        array_map('trim', explode(',', (string) (getenv('CORS_ALLOWED_ORIGINS') ?: '')))
    )))),

    // 用于hash token的pepper（不要泄露，不要放前端）
    'token_pepper' => env('TOKEN_PEPPER', ''),

    // 敏感数据加密密钥（用于 API Key、云存储密钥等加密存储）
    'vault_key' => getenv('VAULT_KEY', ''),
];
