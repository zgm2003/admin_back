<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

if (!function_exists('get_trace_id')) {
    /**
     * 获取当前请求的 trace_id
     */
    function get_trace_id(): string
    {
        $request = request();
        return $request->traceId ?? '-';
    }
}

if (!function_exists('log_daily')) {
    function log_daily($name = 'webman')
    {
        $date = date('Y-m-d');
        $dir = runtime_path("logs/$date"); // runtime/logs/2025-04-14/

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $logFile = "$dir/{$name}.log";

        $handler = (new StreamHandler($logFile, Logger::DEBUG))
            ->setFormatter(new LineFormatter(null, null, true, true));

        return new Logger($name, [$handler]);
    }
}
if (!function_exists('isValidEmail')) {
    function isValidEmail($email)
    {
        // 定义电子邮件的正则表达式
        $regex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

        // 检查电子邮件是否符合格式
        return preg_match($regex, $email) === 1;
    }
}
if (!function_exists('is_valid_phone_number')) {
    function is_valid_phone_number($phone_number)
    {
        // 正则表达式，这里以中国大陆手机号为例
        $pattern = '/^1[3-9]\d{9}$/';
        if (preg_match($pattern, $phone_number)) {
            return true;
        } else {
            return false;
        }
    }
}
if (!function_exists('listToTree')) {
    function listToTree($list, $rootId = 0)
    {
        $arr = [];
        foreach ($list as $item) {
            if ($item['parent_id'] == $rootId) {
                if (!isset($item['is_last']) || !$item['is_last']) {
                    $children = listToTree($list, $item['id']);
                    if ($children) {
                        $item['children'] = $children;
                    }
                }
                $arr[] = $item;
            }
        }
        return $arr;
    }
}