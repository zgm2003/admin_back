<?php

namespace app\service\Pay;

use app\dep\Pay\UserWalletDep;
use app\dep\Pay\WalletTransactionDep;
use app\enum\PayEnum;
/**
 * 钱包服务（贫血服务，无状态）
 * 所有余额变动方法均为幂等的，供 Module 层调用
 * 事务边界由调用方（PayModule 等）控制
 */
class WalletService
{
    // ==================== 钱包查找/创建 ====================

    public function getOrCreateWallet(int $userId): array
    {
        $dep = new UserWalletDep();
        $wallet = $dep->findByUserId($userId);
        if ($wallet) {
            return $wallet->toArray();
        }
        $id = $dep->add([
            'user_id'        => $userId,
            'balance'        => 0,
            'frozen'         => 0,
            'total_recharge' => 0,
            'total_consume'  => 0,
            'version'        => 0,
        ]);
        return $dep->find($id)->toArray();
    }

    // ==================== 幂等检查 ====================

    public function hasProcessed(string $bizActionNo): bool
    {
        return (new WalletTransactionDep())->existsByBizActionNo($bizActionNo);
    }

    // ==================== 充值入账（幂等）====================

    /** @return bool true=入账成功，false=已入账（幂等跳过） */
    public function creditRecharge(int $userId, int $amount, string $orderNo, int $orderId, int $sourceId): bool
    {
        $bizActionNo = "WALLET:RECHARGE:{$orderNo}";
        if ($this->hasProcessed($bizActionNo)) {
            return false;
        }

        $walletDep = new UserWalletDep();
        $wallet = $this->getOrCreateWallet($userId);

        $affected = $walletDep->creditRecharge($wallet['id'], $wallet['version'], $amount);
        if ($affected === 0) {
            throw new \RuntimeException('钱包版本冲突，请重试');
        }

        (new WalletTransactionDep())->add([
            'biz_action_no'    => $bizActionNo,
            'user_id'          => $userId,
            'wallet_id'        => $wallet['id'],
            'type'             => PayEnum::WALLET_RECHARGE,
            'available_delta'   => $amount,
            'frozen_delta'     => 0,
            'balance_before'   => $wallet['balance'],
            'balance_after'    => $wallet['balance'] + $amount,
            'frozen_before'   => $wallet['frozen'],
            'frozen_after'    => $wallet['frozen'],
            'order_id'         => $orderId,
            'order_no'         => $orderNo,
            'source_type'      => PayEnum::WALLET_SOURCE_FULFILL,
            'source_id'        => $sourceId,
            'title'            => '充值入账',
            'remark'           => "充值入账",
            'operator_id'      => 0,
            'ext'              => json_encode(['amount' => $amount], JSON_UNESCAPED_UNICODE),
        ]);

        return true;
    }

    // ==================== 消费扣款（幂等）====================

    /** @return bool true=扣款成功，false=余额不足/已扣过 */
    public function debitConsume(int $userId, int $amount, string $bizActionNo, int $orderId, string $orderNo): bool
    {
        if ($this->hasProcessed($bizActionNo)) {
            return false;
        }

        $walletDep = new UserWalletDep();
        $wallet = $this->getOrCreateWallet($userId);

        if ($wallet['balance'] < $amount) {
            return false;
        }

        $affected = $walletDep->debitConsume($wallet['id'], $wallet['version'], $amount);
        if ($affected === 0) {
            return false;
        }

        (new WalletTransactionDep())->add([
            'biz_action_no'    => $bizActionNo,
            'user_id'          => $userId,
            'wallet_id'        => $wallet['id'],
            'type'             => PayEnum::WALLET_CONSUME,
            'available_delta'  => -$amount,
            'frozen_delta'     => 0,
            'balance_before'   => $wallet['balance'],
            'balance_after'   => $wallet['balance'] - $amount,
            'frozen_before'   => $wallet['frozen'],
            'frozen_after'    => $wallet['frozen'],
            'order_id'         => $orderId,
            'order_no'         => $orderNo,
            'source_type'      => PayEnum::WALLET_SOURCE_FULFILL,
            'source_id'        => 0,
            'title'            => '消费扣款',
            'remark'           => "消费扣款 biz_action_no={$bizActionNo}",
            'operator_id'      => 0,
            'ext'              => json_encode(['biz_action_no' => $bizActionNo], JSON_UNESCAPED_UNICODE),
        ]);

        return true;
    }
}
