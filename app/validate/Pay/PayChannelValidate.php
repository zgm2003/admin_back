<?php

namespace app\validate\Pay;

use Respect\Validation\Validator as v;
use app\enum\CommonEnum;

class PayChannelValidate
{
    public static function add(): array
    {
        return [
            'name'              => v::stringType()->length(1, 50)->setName('渠道名称'),
            'channel'           => v::intVal()->in(array_keys(\app\enum\PayEnum::$channelArr))->setName('渠道类型'),
            'mch_id'            => v::stringType()->length(1, 64)->setName('商户号'),
            'app_id'            => v::optional(v::stringType()->length(0, 64))->setName('应用ID'),
            'notify_url'        => v::optional(v::stringType()->length(0, 512))->setName('异步回调地址'),
            'return_url'        => v::optional(v::stringType()->length(0, 512))->setName('同步回跳地址'),
            'app_private_key'      => v::optional(v::stringType())->setName('应用私钥'),
            'app_private_key_hint' => v::optional(v::stringType()->length(0, 20))->setName('私钥提示'),
            'public_cert_path'  => v::optional(v::stringType()->length(0, 512))->setName('公钥证书路径'),
            'platform_cert_path'=> v::optional(v::stringType()->length(0, 512))->setName('平台证书路径'),
            'root_cert_path'    => v::optional(v::stringType()->length(0, 512))->setName('根证书路径'),
            'sort'              => v::optional(v::intVal()->between(0, 9999))->setName('排序'),
            'is_sandbox'        => v::optional(v::intVal()->in([1, 2]))->setName('沙箱环境'),
            'status'            => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'remark'            => v::optional(v::stringType()->length(0, 255))->setName('备注'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'                => v::intVal()->positive()->setName('ID'),
            'name'              => v::optional(v::stringType()->length(1, 50))->setName('渠道名称'),
            'channel'           => v::optional(v::intVal()->in(array_keys(\app\enum\PayEnum::$channelArr)))->setName('渠道类型'),
            'mch_id'            => v::optional(v::stringType()->length(1, 64))->setName('商户号'),
            'app_id'            => v::optional(v::stringType()->length(0, 64))->setName('应用ID'),
            'notify_url'        => v::optional(v::stringType()->length(0, 512))->setName('异步回调地址'),
            'return_url'        => v::optional(v::stringType()->length(0, 512))->setName('同步回跳地址'),
            'app_private_key'      => v::optional(v::stringType())->setName('应用私钥'),
            'app_private_key_hint' => v::optional(v::stringType()->length(0, 20))->setName('私钥提示'),
            'public_cert_path'  => v::optional(v::stringType()->length(0, 512))->setName('公钥证书路径'),
            'platform_cert_path'=> v::optional(v::stringType()->length(0, 512))->setName('平台证书路径'),
            'root_cert_path'    => v::optional(v::stringType()->length(0, 512))->setName('根证书路径'),
            'sort'              => v::optional(v::intVal()->between(0, 9999))->setName('排序'),
            'is_sandbox'        => v::optional(v::intVal()->in([1, 2]))->setName('沙箱环境'),
            'status'            => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr)))->setName('状态'),
            'remark'            => v::optional(v::stringType()->length(0, 255))->setName('备注'),
        ];
    }

    public static function del(): array
    {
        return [
            'id' => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
        ];
    }

    public static function list(): array
    {
        return [
            'page'       => v::optional(v::intVal()->positive()),
            'page_size'  => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'channel'    => v::optional(v::intVal()->in(array_keys(\app\enum\PayEnum::$channelArr))),
            'status'     => v::optional(v::intVal()->in(array_keys(CommonEnum::$statusArr))),
            'name'       => v::optional(v::stringType()),
        ];
    }

    public static function setStatus(): array
    {
        return [
            'id'     => v::oneOf(v::intVal()->positive(), v::arrayType())->setName('ID'),
            'status' => v::intVal()->in(array_keys(CommonEnum::$statusArr))->setName('状态'),
        ];
    }
}
