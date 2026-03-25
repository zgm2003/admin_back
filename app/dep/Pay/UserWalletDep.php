<?php

namespace app\dep\Pay;

use app\dep\BaseDep;
use app\model\Pay\UserWalletModel;
use app\enum\CommonEnum;
use support\Db;
use support\Model;

class UserWalletDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new UserWalletModel();
    }

    public function findByUserId(int $userId): ?Model
    {
        return $this->query()
            ->where('user_id', $userId)
            ->first();
    }

    /** 充值入账：balance +=, total_recharge +=, version += 1 */
    public function creditRecharge(int $walletId, int $version, int $amount): int
    {
        return $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO)
            ->update([
                'balance'        => Db::raw("balance + {$amount}"),
                'total_recharge' => Db::raw("total_recharge + {$amount}"),
                'version'        => Db::raw('version + 1'),
            ]);
    }

    /** 消费扣款：balance -=, total_consume +=, version += 1 */
    public function debitConsume(int $walletId, int $version, int $amount): int
    {
        return $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO)
            ->whereRaw("balance >= {$amount}")
            ->update([
                'balance'       => Db::raw("balance - {$amount}"),
                'total_consume' => Db::raw("total_consume + {$amount}"),
                'version'       => Db::raw('version + 1'),
            ]);
    }

    /** 退款冻结：balance -=, frozen +=, version += 1 */
    public function freezeForRefund(int $walletId, int $version, int $amount): int
    {
        return $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO)
            ->whereRaw("balance >= {$amount}")
            ->update([
                'balance' => Db::raw("balance - {$amount}"),
                'frozen'  => Db::raw("frozen + {$amount}"),
                'version' => Db::raw('version + 1'),
            ]);
    }

    /** 退款完成：frozen -=, total_refund +=, version += 1 */
    public function finalizeRefund(int $walletId, int $version, int $amount): int
    {
        return $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO)
            ->whereRaw("frozen >= {$amount}")
            ->update([
                'frozen'       => Db::raw("frozen - {$amount}"),
                'total_refund'  => Db::raw("total_refund + {$amount}"),
                'version'       => Db::raw('version + 1'),
            ]);
    }

    /** 退款解冻：frozen -=, balance +=, version += 1 */
    public function unfreezeRefund(int $walletId, int $version, int $amount): int
    {
        return $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO)
            ->whereRaw("frozen >= {$amount}")
            ->update([
                'frozen'  => Db::raw("frozen - {$amount}"),
                'balance' => Db::raw("balance + {$amount}"),
                'version' => Db::raw('version + 1'),
            ]);
    }

    /** 管理员调账 */
    public function adjustBalance(int $walletId, int $version, int $delta): int
    {
        $query = $this->query()
            ->where('id', $walletId)
            ->where('version', $version)
            ->where('is_del', CommonEnum::NO);

        $updates = ['version' => Db::raw('version + 1')];

        if ($delta >= 0) {
            $updates['balance'] = Db::raw("balance + {$delta}");
        } else {
            $abs = abs($delta);
            $query->whereRaw("balance >= {$abs}");
            $updates['balance'] = Db::raw("balance - {$abs}");
        }

        return $query->update($updates);
    }

    public function list(array $param)
    {
        return $this->model
            ->select([
                'id', 'user_id', 'balance', 'frozen',
                'total_recharge', 'total_consume', 'total_refund', 'created_at',
            ])
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['user_id']), fn($q) => $q->where('user_id', (int) $param['user_id']))
            ->when(!empty($param['start_date']), fn($q) => $q->where('created_at', '>=', $param['start_date']))
            ->when(!empty($param['end_date']), fn($q) => $q->where('created_at', '<=', $param['end_date'] . ' 23:59:59'))
            ->orderBy('id', 'desc')
            ->paginate($param['page_size'] ?? 20, ['*'], 'page', $param['page'] ?? 1);
    }
}
