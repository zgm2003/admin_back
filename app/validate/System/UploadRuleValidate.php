<?php

namespace app\validate\System;

use Respect\Validation\Validator as v;

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
            'page_size'    => v::optional(v::intVal()),
            'current_page' => v::optional(v::intVal()),
        ];
    }
}

