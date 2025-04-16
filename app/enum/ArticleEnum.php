<?php

namespace app\enum;

class ArticleEnum
{
    const DRAFT = 1;
    const MODEL = 2;
    const MODEL_ERROR = 3;
    const REVIEW = 4;
    const RELEASE = 5;
    const REMOVE = 6;



    const ORIGINAL = 1;
    const REPOST = 2;


    public static $statusArr = [
        self::DRAFT => "草稿箱",
        self::MODEL => "生成中",
        self::MODEL_ERROR => "生成失败",
        self::REVIEW => "待审核",
        self::RELEASE => "发布",
        self::REMOVE => "下架",
    ];
    public static $typesArr = [
        self::ORIGINAL => "原创",
        self::REPOST => "转载",
    ];

}
