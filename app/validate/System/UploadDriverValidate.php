<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UploadDriverValidate
{
    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'driver'       => v::optional(v::stringType()),
        ];
    }

    public static function add(): array
    {
        return [
            'driver'        => v::stringType()->length(1, 20)->setName('driver'),
            'secret_id'     => v::stringType()->length(1, 255)->setName('secret_id'),
            'secret_key'    => v::stringType()->length(1, 255)->setName('secret_key'),
            'bucket'        => v::stringType()->length(1, 255)->setName('bucket'),
            'region'        => v::stringType()->length(1, 100)->setName('region'),
            'appid'         => v::optional(v::stringType())->setName('appid'),
            'role_arn'    => v::optional(v::stringType())->setName('role_arn'),
            'endpoint'      => v::optional(v::stringType())->setName('endpoint'),
            'bucket_domain' => v::optional(v::stringType())->setName('bucket_domain'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'            => v::intVal()->setName('id'),
            'driver'        => v::stringType()->length(1, 20)->setName('driver'),
            'secret_id'     => v::stringType()->length(1, 255)->setName('secret_id'),
            'secret_key'    => v::stringType()->length(1, 255)->setName('secret_key'),
            'bucket'        => v::stringType()->length(1, 255)->setName('bucket'),
            'region'        => v::stringType()->length(1, 100)->setName('region'),
            'appid'         => v::optional(v::stringType())->setName('appid'),
            'role_arn'    => v::optional(v::stringType())->setName('role_arn'),
            'endpoint'      => v::optional(v::stringType())->setName('endpoint'),
            'bucket_domain' => v::optional(v::stringType())->setName('bucket_domain'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }
}

