<?php

namespace app\module\Pay;

use app\dep\Pay\UserWalletDep;
use app\dep\Pay\WalletTransactionDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\service\Pay\WalletService;
use app\validate\Pay\UserWalletValidate;
use support\Log;

/**
 * 钱包管理模块
 */
class UserWalletModule extends BaseModule
{
    /** 初始化 */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->getDict();

        $dict['wallet_type_arr'] = DictService::enumToDict(PayEnum::$walletTypeArr);
        $dict['wallet_source_arr'] = DictService::enumToDict(PayEnum::$walletSourceArr);

        return self::success(['dict' => $dict]);
    }

    /** 钱包列表 */
    public function list($request): array
    {
        $param = $this->validate($request, UserWalletValidate::list());
        $res = $this->dep(UserWalletDep::class)->list($param);

        $list = $res->map(function ($item) {
            return [
                'id'              => $item->id,
                'user_id'         => $item->user_id,
                'balance'         => $item->balance,
                'frozen'          => $item->frozen,
                'available'       => $item->balance - $item->frozen,
                'total_recharge'  => $item->total_recharge,
                'total_consume'   => $item->total_consume,
                'total_refund'    => $item->total_refund,
                'created_at'      => $item->created_at,
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /** 钱包流水 */
    public function transactions($request): array
    {
        $param = $this->validate($request, UserWalletValidate::transactions());
        $res = $this->dep(WalletTransactionDep::class)->list($param);

        $list = $res->map(function ($item) {
            return [
                'id'               => $item->id,
                'biz_action_no'    => $item->biz_action_no,
                'type'             => $item->type,
                'type_text'        => PayEnum::$walletTypeArr[$item->type] ?? '',
                'available_delta'  => $item->available_delta,
                'frozen_delta'     => $item->frozen_delta,
                'balance_before'    => $item->balance_before,
                'balance_after'    => $item->balance_after,
                'order_no'         => $item->order_no,
                'title'            => $item->title,
                'remark'           => $item->remark,
                'created_at'       => $item->created_at,
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /** 调账（管理员） */
    public function adjust($request): array
    {
        $param = $this->validate($request, UserWalletValidate::adjust());
        $operatorId = (int) ($request->user_id ?? 0);

        $walletSvc = new WalletService();
        $wallet = $walletSvc->getOrCreateWallet((int) $param['user_id']);

        $delta = (int) $param['delta'];
        $bizActionNo = "WALLET:ADJUST:" . date('YmdHis') . ':' . rand(1000, 9999);

        if ($delta < 0) {
            $abs = abs($delta);
            self::throwIf($wallet['balance'] < $abs, '可用余额不足，无法调减');
        }

        $walletDep = $this->dep(UserWalletDep::class);
        $affected = $walletDep->adjustBalance($wallet['id'], $wallet['version'], $delta);

        self::throwIf($affected === 0, '调账失败：版本冲突或余额不足');

        // 记录流水
        (new WalletTransactionDep())->add([
            'biz_action_no'    => $bizActionNo,
            'user_id'          => $wallet['user_id'],
            'wallet_id'        => $wallet['id'],
            'type'             => PayEnum::WALLET_ADJUST,
            'available_delta'  => $delta,
            'frozen_delta'     => 0,
            'balance_before'   => $wallet['balance'],
            'balance_after'    => $wallet['balance'] + $delta,
            'order_id'         => 0,
            'order_no'         => '',
            'source_type'      => PayEnum::WALLET_SOURCE_MANUAL,
            'source_id'        => 0,
            'title'            => '系统调账',
            'remark'           => $param['reason'] ?? '',
            'operator_id'      => $operatorId,
            'ext'              => json_encode(['reason' => $param['reason'] ?? ''], JSON_UNESCAPED_UNICODE),
        ]);

        Log::info('[WalletAdjust] 管理员调账', [
            'user_id'     => $param['user_id'],
            'delta'       => $delta,
            'operator_id' => $operatorId,
            'reason'      => $param['reason'] ?? '',
        ]);

        return self::success();
    }
}
