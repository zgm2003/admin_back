<?php

namespace app\enum;

class UploadConfigEnum
{
    // 扩展名常量（统一风格）
    const EXT_JPEG  = 'jpeg';
    const EXT_JPG   = 'jpg';
    const EXT_GIF   = 'gif';
    const EXT_PNG   = 'png';
    const EXT_SVG   = 'svg';
    const EXT_ICO   = 'ico';
    const EXT_DOC   = 'doc';
    const EXT_PSD   = 'psd';
    const EXT_BMP   = 'bmp';
    const EXT_TIFF  = 'tiff';
    const EXT_WEBP  = 'webp';
    const EXT_TIF   = 'tif';
    const EXT_PJPEG = 'pjpeg';
    const EXT_DOCX  = 'docx';
    const EXT_PDF   = 'pdf';
    const EXT_TXT   = 'txt';
    const EXT_HTML  = 'html';
    const EXT_ZIP   = 'zip';
    const EXT_TAR   = 'tar';
    const EXT_CSS   = 'css';
    const EXT_CSV   = 'csv';
    const EXT_PPT   = 'ppt';
    const EXT_XLSX  = 'xlsx';
    const EXT_XLS   = 'xls';
    const EXT_XML   = 'xml';

    // 图片扩展名白名单（常量 => 显示名称）
    public static $imageExtArr = [
        self::EXT_JPEG  => 'jpeg',
        self::EXT_JPG   => 'jpg',
        self::EXT_GIF   => 'gif',
        self::EXT_PNG   => 'png',
        self::EXT_SVG   => 'svg',
        self::EXT_ICO   => 'ico',
        self::EXT_DOC   => 'doc',
        self::EXT_PSD   => 'psd',
        self::EXT_BMP   => 'bmp',
        self::EXT_TIFF  => 'tiff',
        self::EXT_WEBP  => 'webp',
        self::EXT_TIF   => 'tif',
        self::EXT_PJPEG => 'pjpeg',
    ];

    // 普通文件扩展名白名单（常量 => 显示名称）
    public static $fileExtArr = [
        self::EXT_DOCX => 'docx',
        self::EXT_PDF  => 'pdf',
        self::EXT_TXT  => 'txt',
        self::EXT_HTML => 'html',
        self::EXT_ZIP  => 'zip',
        self::EXT_TAR  => 'tar',
        self::EXT_DOC  => 'doc',
        self::EXT_CSS  => 'css',
        self::EXT_CSV  => 'csv',
        self::EXT_PPT  => 'ppt',
        self::EXT_XLSX => 'xlsx',
        self::EXT_XLS  => 'xls',
        self::EXT_XML  => 'xml',
    ];

    // 驱动（对象存储提供商）
    const DRIVER_COS   = 'cos';   // 腾讯云 COS
    const DRIVER_OSS   = 'oss';   // 阿里云 OSS
//    const DRIVER_S3    = 's3';    // AWS S3 及兼容
//    const DRIVER_QINIU = 'qiniu'; // 七牛云 Kodo
    public static $driverArr = [
        self::DRIVER_COS   => '腾讯云 COS',
        self::DRIVER_OSS   => '阿里云 OSS',
//        self::DRIVER_S3    => 'AWS S3',
//        self::DRIVER_QINIU => '七年云 Kodo',
    ];

        // 上传文件夹白名单（统一复数）
    const FOLDER_AVATARS = 'avatars';
    const FOLDER_IMAGES = 'images';
    const FOLDER_VIDEOS = 'videos';
    const FOLDER_COVER_IMAGES = 'cover_images';
    const FOLDER_AI_CHAT_IMAGES = 'ai_chat_images';
    const FOLDER_RELEASES = 'releases';
    public static $folderArr = [
        self::FOLDER_AVATARS => 'avatars',
        self::FOLDER_IMAGES => 'images',
        self::FOLDER_VIDEOS => 'videos',
        self::FOLDER_COVER_IMAGES => 'cover_images',
        self::FOLDER_AI_CHAT_IMAGES => 'ai_chat_images',
        self::FOLDER_RELEASES => 'releases',
    ];

    // Tauri 平台类型
    const PLATFORM_WINDOWS = 'windows-x86_64';
    const PLATFORM_MACOS = 'darwin-x86_64';
    public static $tauriPlatformArr = [
        self::PLATFORM_WINDOWS => 'Windows',
        self::PLATFORM_MACOS => 'macOS',
    ];
}
