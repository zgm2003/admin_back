<?php

namespace app\enum;

class CineEnum
{
    const STATUS_DRAFT = 1;
    const STATUS_GENERATING = 2;
    const STATUS_READY = 3;
    const STATUS_IMAGE_GENERATING = 4;
    const STATUS_COMPLETED = 5;
    const STATUS_FAILED = 6;

    public static $statusArr = [
        self::STATUS_DRAFT => '草稿箱',
        self::STATUS_GENERATING => '草稿生成中',
        self::STATUS_READY => '待审查',
        self::STATUS_IMAGE_GENERATING => '分镜生成中',
        self::STATUS_COMPLETED => '已完成',
        self::STATUS_FAILED => '生成失败',
    ];

    const MODE_DRAFT = 'draft';
    const MODE_VISUAL = 'visual';

    public static $modeArr = [
        self::MODE_DRAFT => '草稿阶段',
        self::MODE_VISUAL => '分镜阶段',
    ];

    const ASSET_TYPE_REFERENCE = 'reference';
    const ASSET_TYPE_KEYFRAME = 'keyframe';

    const ASSET_STATUS_PENDING = 1;
    const ASSET_STATUS_GENERATING = 2;
    const ASSET_STATUS_READY = 3;
    const ASSET_STATUS_FAILED = 4;

    public static $assetStatusArr = [
        self::ASSET_STATUS_PENDING => '待生成',
        self::ASSET_STATUS_GENERATING => '生成中',
        self::ASSET_STATUS_READY => '已完成',
        self::ASSET_STATUS_FAILED => '失败',
    ];
}
