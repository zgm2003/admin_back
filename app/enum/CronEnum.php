<?php

namespace app\enum;

class CronEnum
{
    // 常用 Cron 表达式（秒 分 时 日 月 周）
    const EVERY_MINUTE = '0 * * * * *';
    const EVERY_5_MINUTES = '0 */5 * * * *';
    const EVERY_10_MINUTES = '0 */10 * * * *';
    const EVERY_30_MINUTES = '0 */30 * * * *';
    const EVERY_HOUR = '0 0 * * * *';
    const EVERY_2_HOURS = '0 0 */2 * * *';
    const EVERY_6_HOURS = '0 0 */6 * * *';
    const EVERY_12_HOURS = '0 0 */12 * * *';
    const DAILY_0AM = '0 0 0 * * *';
    const DAILY_1AM = '0 0 1 * * *';
    const DAILY_2AM = '0 0 2 * * *';
    const DAILY_6AM = '0 0 6 * * *';
    const DAILY_12PM = '0 0 12 * * *';
    const WEEKLY_MONDAY = '0 0 0 * * 1';
    const MONTHLY_1ST = '0 0 0 1 * *';

    public static array $presetArr = [
        self::EVERY_MINUTE => '每分钟',
        self::EVERY_5_MINUTES => '每5分钟',
        self::EVERY_10_MINUTES => '每10分钟',
        self::EVERY_30_MINUTES => '每30分钟',
        self::EVERY_HOUR => '每小时',
        self::EVERY_2_HOURS => '每2小时',
        self::EVERY_6_HOURS => '每6小时',
        self::EVERY_12_HOURS => '每12小时',
        self::DAILY_0AM => '每天0点',
        self::DAILY_1AM => '每天凌晨1点',
        self::DAILY_2AM => '每天凌晨2点',
        self::DAILY_6AM => '每天早上6点',
        self::DAILY_12PM => '每天中午12点',
        self::WEEKLY_MONDAY => '每周一0点',
        self::MONTHLY_1ST => '每月1号0点',
    ];
}
