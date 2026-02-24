<?php

namespace app\validate\Ai;

use app\enum\CommonEnum;
use app\enum\GoodsEnum;
use Respect\Validation\Validator as v;

class GoodsValidate
{
    public static function add(): array
    {
        return [
            'title'              => v::optional(v::stringType()->length(0, 255))->setName('商品标题'),
            'main_img'           => v::optional(v::stringType()->length(0, 512))->setName('商品主图'),
            'platform'           => v::optional(v::intVal()->in(array_keys(GoodsEnum::$platformArr)))->setName('平台'),
            'link'               => v::optional(v::stringType())->setName('商品链接'),
            'image_list'         => v::optional(v::arrayType())->setName('图片列表'),
        ];
    }

    public static function edit(): array
    {
        return [
            'id'                 => v::intVal()->positive()->setName('ID'),
            'title'              => v::optional(v::stringType()->length(0, 255))->setName('商品标题'),
            'main_img'           => v::optional(v::stringType()->length(0, 512))->setName('商品主图'),
            'link'               => v::optional(v::stringType())->setName('商品链接'),
            'tips'               => v::optional(v::stringType())->setName('提示词'),
            'point'              => v::optional(v::stringType())->setName('卖点'),
            'script_text'        => v::optional(v::stringType())->setName('口播词'),
            'image_list'         => v::optional(v::arrayType())->setName('图片列表'),
            'image_list_success' => v::optional(v::arrayType())->setName('选中图片列表'),
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
            'page_size'    => v::optional(v::intVal()->between(CommonEnum::PAGE_SIZE_MIN, CommonEnum::PAGE_SIZE_MAX)),
            'current_page' => v::optional(v::intVal()->positive()),
            'title'        => v::optional(v::stringType()),
            'platform'     => v::optional(v::intVal()->in(array_keys(GoodsEnum::$platformArr))),
            'status'       => v::optional(v::intVal()->in(array_keys(GoodsEnum::$statusArr))),
        ];
    }

    /**
     * 插件提交数据验证
     */
    public static function submit(): array
    {
        return [
            'images'      => v::arrayType()->setName('图片列表'),
            'title'       => v::optional(v::stringType()->length(0, 255))->setName('商品标题'),
            'platform'    => v::optional(v::stringType())->setName('平台域名'),
            'link'        => v::optional(v::stringType())->setName('商品链接'),
        ];
    }

    /**
     * OCR识别
     */
    public static function ocr(): array
    {
        return [
            'id' => v::intVal()->positive()->setName('ID'),
            'image_list_success' => v::optional(v::arrayType())->setName('选中图片列表'),
        ];
    }

    /**
     * 生成口播词
     */
    public static function generate(): array
    {
        return [
            'id'       => v::intVal()->positive()->setName('ID'),
            'agent_id' => v::intVal()->positive()->setName('智能体ID'),
            'tips'     => v::optional(v::stringType())->setName('提示词'),
        ];
    }

    /**
     * TTS语音合成
     */
    public static function tts(): array
    {
        return [
            'id'          => v::intVal()->positive()->setName('ID'),
            'voice'       => v::optional(v::stringType()->in(array_keys(GoodsEnum::$voiceArr)))->setName('音色'),
            'script_text' => v::optional(v::stringType())->setName('口播词'),
        ];
    }
}
