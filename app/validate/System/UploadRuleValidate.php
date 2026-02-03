<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class UploadRuleValidate
{
    public static function add(): array
    {
        return [
            'title'       => v::stringType()->length(1, 100)->setName('title'),
            'max_size_mb' => v::intVal()->min(1)->max(10240)->setName('max_size_mb'),
            'image_exts'  => v::arrayType()->setName('image_exts'),
            'file_exts'   => v::arrayType()->setName('file_exts'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'          => v::intVal()->setName('id'),
            'title'       => v::stringType()->length(1, 100)->setName('title'),
            'max_size_mb' => v::intVal()->min(1)->max(10240)->setName('max_size_mb'),
            'image_exts'  => v::arrayType()->setName('image_exts'),
            'file_exts'   => v::arrayType()->setName('file_exts'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page_size'    => v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)->setName('每页数量'),
            'current_page' => v::intVal()->positive()->setName('当前页'),
            'title'        => v::optional(v::stringType()),
        ];
    }
}

