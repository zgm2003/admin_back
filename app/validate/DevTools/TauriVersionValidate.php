<?php

namespace app\validate\DevTools;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;
use app\enum\UploadConfigEnum;

class TauriVersionValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
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
            'force_update' => v::optional(v::intVal()->in([CommonEnum::YES, CommonEnum::NO])),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'        => v::intVal()->setName('ID'),
            'version'   => v::optional(v::stringType()->length(1, 20)),
            'notes'     => v::optional(v::stringType()->length(0, 1000)),
            'file_url'  => v::optional(v::stringType()->url()),
            'signature' => v::optional(v::stringType()),
            'file_size' => v::optional(v::intVal()),
            'force_update' => v::optional(v::intVal()->in([CommonEnum::YES, CommonEnum::NO])),
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

    public static function forceUpdate(): array
    {
        return [
            'id' => v::intVal()->setName('ID'),
            'force_update' => v::intVal()->in([CommonEnum::YES, CommonEnum::NO])->setName('强制更新'),
        ];
    }

    public static function clientInit(): array
    {
        return [
            'version' => v::notEmpty()->setName('版本号'),
            'platform' => v::optional(v::stringType())->setName('平台'),
        ];
    }
}
