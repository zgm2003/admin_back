<?php

namespace app\validate\DevTools;

use Respect\Validation\Validator as v;
use app\enum\UploadConfigEnum;

class TauriVersionValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->min(1)->max(100)),
            'current_page' => v::optional(v::intVal()->min(1)),
            'platform'     => v::optional(v::in(array_keys(UploadConfigEnum::$tauriPlatformArr))),
        ];
    }

    public static function add(): array
    {
        return [
            'version'   => v::stringType()->notEmpty()->length(1, 20)->setName('版本号'),
            'notes'     => v::optional(v::stringType()->length(0, 1000)),
            'file_url'  => v::stringType()->notEmpty()->url()->setName('文件地址'),
            'signature' => v::stringType()->notEmpty()->setName('签名'),
            'platform'  => v::in(array_keys(UploadConfigEnum::$tauriPlatformArr))->setName('平台'),
            'file_size' => v::optional(v::intVal()),
        ];
    }

    public static function setLatest(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
        ];
    }
}
