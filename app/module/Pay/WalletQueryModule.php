<?php

namespace app\module\Pay;

use app\dep\Pay\UserWalletDep;
use app\dep\Pay\WalletTransactionDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\validate\Pay\UserWalletValidate;
use support\Request;

class WalletQueryModule extends BaseModule
{
    public function walletInfo(Request $request): array
    {
        $userId = (int) $request->userId;
        $wallet = $this->dep(UserWalletDep::class)->findByUserId($userId);

        if (!$wallet) {
            return self::success(['wallet_exists' => CommonEnum::NO]);
        }

        return self::success([
            'wallet_exists' => CommonEnum::YES,
            'balance' => $wallet->balance,
            'frozen' => $wallet->frozen,
            'total_recharge' => $wallet->total_recharge,
            'total_consume' => $wallet->total_consume,
            'created_at' => $wallet->created_at,
        ]);
    }

    public function walletBills(Request $request): array
    {
        $userId = (int) $request->userId;
        $param = $this->validate($request, UserWalletValidate::transactions());
        $param['current_page'] = (int) ($param['current_page'] ?? 1);
        $param['page_size'] = (int) ($param['page_size'] ?? 20);

        $res = $this->dep(WalletTransactionDep::class)->listByUserId($userId, $param['current_page'], $param['page_size']);
        $list = $res->map(fn($item) => [
            'id' => $item->id,
            'biz_action_no' => $item->biz_action_no,
            'type' => $item->type,
            'type_text' => PayEnum::$walletTypeArr[$item->type] ?? '',
            'available_delta' => $item->available_delta,
            'frozen_delta' => $item->frozen_delta,
            'balance_before' => $item->balance_before,
            'balance_after' => $item->balance_after,
            'title' => $item->title,
            'remark' => $item->remark,
            'order_no' => $item->order_no,
            'created_at' => $item->created_at,
        ]);

        $pageData = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $pageData);
    }
}
