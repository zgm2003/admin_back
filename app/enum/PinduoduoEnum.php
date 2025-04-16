<?php

namespace app\enum;

class PinduoduoEnum
{
    const COMPREHENSIVE = 0; // 综合排序
    const COMMISSION_RATE_ASC = 1; // 按佣金比率升序
    const COMMISSION_RATE_DESC = 2; // 按佣金比率降序
//    const PRICE_ASC = 3; // 按价格升序
//    const PRICE_DESC = 4; // 按价格降序
    const SALES_ASC = 5; // 按销量升序
    const SALES_DESC = 6; // 按销量降序
//    const COUPON_AMOUNT_ASC = 7; // 优惠券金额升序
//    const COUPON_AMOUNT_DESC = 8; // 优惠券金额降序
//    const AFTER_COUPON_PRICE_ASC = 9; // 券后价升序
//    const AFTER_COUPON_PRICE_DESC = 10; // 券后价降序
//    const JOIN_TIME_ASC = 11; // 加入多多进宝时间升序
//    const JOIN_TIME_DESC = 12; // 加入多多进宝时间降序
    const COMMISSION_AMOUNT_ASC = 13; // 按佣金金额升序
    const COMMISSION_AMOUNT_DESC = 14; // 按佣金金额降序
//    const SHOP_DESCRIPTION_SCORE_ASC = 15; // 店铺描述评分升序
//    const SHOP_DESCRIPTION_SCORE_DESC = 16; // 店铺描述评分降序
//    const SHOP_LOGISTICS_SCORE_ASC = 17; // 店铺物流评分升序
//    const SHOP_LOGISTICS_SCORE_DESC = 18; // 店铺物流评分降序
//    const SHOP_SERVICE_SCORE_ASC = 19; // 店铺服务评分升序
//    const SHOP_SERVICE_SCORE_DESC = 20; // 店铺服务评分降序
//    const DESCRIPTION_SCORE_PERCENT_ASC = 27; // 描述评分击败同类店铺百分比升序
//    const DESCRIPTION_SCORE_PERCENT_DESC = 28; // 描述评分击败同类店铺百分比降序
//    const LOGISTICS_SCORE_PERCENT_ASC = 29; // 物流评分击败同类店铺百分比升序
//    const LOGISTICS_SCORE_PERCENT_DESC = 30; // 物流评分击败同类店铺百分比降序
//    const SERVICE_SCORE_PERCENT_ASC = 31; // 服务评分击败同类店铺百分比升序
//    const SERVICE_SCORE_PERCENT_DESC = 32; // 服务评分击败同类店铺百分比降序

    public static $sortArr = [
        self::COMPREHENSIVE => '综合排序',
        self::COMMISSION_RATE_ASC => '按佣金比率升序',
        self::COMMISSION_RATE_DESC => '按佣金比率降序',
//        self::PRICE_ASC => '按价格升序',
//        self::PRICE_DESC => '按价格降序',
        self::SALES_ASC => '按销量升序',
        self::SALES_DESC => '按销量降序',
//        self::COUPON_AMOUNT_ASC => '优惠券金额升序',
//        self::COUPON_AMOUNT_DESC => '优惠券金额降序',
//        self::AFTER_COUPON_PRICE_ASC => '券后价升序',
//        self::AFTER_COUPON_PRICE_DESC => '券后价降序',
//        self::JOIN_TIME_ASC => '加入多多进宝时间升序',
//        self::JOIN_TIME_DESC => '加入多多进宝时间降序',
        self::COMMISSION_AMOUNT_ASC => '按佣金金额升序',
        self::COMMISSION_AMOUNT_DESC => '按佣金金额降序',
//        self::SHOP_DESCRIPTION_SCORE_ASC => '店铺描述评分升序',
//        self::SHOP_DESCRIPTION_SCORE_DESC => '店铺描述评分降序',
//        self::SHOP_LOGISTICS_SCORE_ASC => '店铺物流评分升序',
//        self::SHOP_LOGISTICS_SCORE_DESC => '店铺物流评分降序',
//        self::SHOP_SERVICE_SCORE_ASC => '店铺服务评分升序',
//        self::SHOP_SERVICE_SCORE_DESC => '店铺服务评分降序',
//        self::DESCRIPTION_SCORE_PERCENT_ASC => '描述评分击败同类店铺百分比升序',
//        self::DESCRIPTION_SCORE_PERCENT_DESC => '描述评分击败同类店铺百分比降序',
//        self::LOGISTICS_SCORE_PERCENT_ASC => '物流评分击败同类店铺百分比升序',
//        self::LOGISTICS_SCORE_PERCENT_DESC => '物流评分击败同类店铺百分比降序',
//        self::SERVICE_SCORE_PERCENT_ASC => '服务评分击败同类店铺百分比升序',
//        self::SERVICE_SCORE_PERCENT_DESC => '服务评分击败同类店铺百分比降序',
    ];
}
