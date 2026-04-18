<?php

namespace app\dep\Chat;

use app\dep\BaseDep;
use app\model\Chat\ChatContactModel;
use app\enum\ChatEnum;
use app\enum\CommonEnum;
use support\Model;

class ChatContactDep extends BaseDep
{
    protected function createModel(): Model
    {
        return new ChatContactModel();
    }

    /**
     * 创建双向联系人记录（A→B 和 B→A），初始状态为待确认
     */
    public function createBidirectional(int $userIdA, int $userIdB): void
    {
        $now = date('Y-m-d H:i:s');
        $this->model->insert([
            [
                'user_id' => $userIdA,
                'contact_user_id' => $userIdB,
                'is_initiator' => CommonEnum::YES,
                'status' => ChatEnum::CONTACT_PENDING,
                'is_del' => CommonEnum::NO,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $userIdB,
                'contact_user_id' => $userIdA,
                'is_initiator' => CommonEnum::NO,
                'status' => ChatEnum::CONTACT_PENDING,
                'is_del' => CommonEnum::NO,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * 确认双向联系人（将 A→B 和 B→A 都更新为已确认）
     */
    public function confirmBidirectional(int $userIdA, int $userIdB): int
    {
        return $this->query()
            ->where(function ($q) use ($userIdA, $userIdB) {
                $q->where(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdA)->where('contact_user_id', $userIdB);
                })->orWhere(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdB)->where('contact_user_id', $userIdA);
                });
            })
            ->where('is_del', CommonEnum::NO)
            ->update(['status' => ChatEnum::CONTACT_CONFIRMED]);
    }

    /**
     * 软删除双向联系人记录
     */
    public function softDeleteBidirectional(int $userIdA, int $userIdB): int
    {
        return $this->query()
            ->where(function ($q) use ($userIdA, $userIdB) {
                $q->where(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdA)->where('contact_user_id', $userIdB);
                })->orWhere(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdB)->where('contact_user_id', $userIdA);
                });
            })
            ->where('is_del', CommonEnum::NO)
            ->update(['is_del' => CommonEnum::YES]);
    }

    /**
     * 查询用户的已确认联系人列表（status=CONFIRMED, is_del=NO）
     * 关联用户表和资料表获取 username 和 avatar
     *
     * @return \Illuminate\Support\Collection
     */
    public function getConfirmedContacts(int $userId)
    {
        return $this->query()
            ->join('users', 'users.id', '=', 'chat_contacts.contact_user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'chat_contacts.contact_user_id')
            ->where('chat_contacts.user_id', $userId)
            ->where('chat_contacts.status', ChatEnum::CONTACT_CONFIRMED)
            ->where('chat_contacts.is_del', CommonEnum::NO)
            ->select([
                'chat_contacts.id',
                'chat_contacts.contact_user_id',
                'chat_contacts.status',
                'chat_contacts.is_initiator',
                'chat_contacts.created_at',
                'users.username',
                'user_profiles.avatar',
            ])
            ->get();
    }

    /**
     * 获取用户已确认好友的 user_id 列表
     *
     * @return array<int>
     */
    public function getConfirmedContactUserIds(int $userId): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('status', ChatEnum::CONTACT_CONFIRMED)
            ->where('is_del', CommonEnum::NO)
            ->pluck('contact_user_id')
            ->map(static fn($id) => (int) $id)
            ->toArray();
    }

    /**
     * 查询用户的所有联系人（含待确认），关联用户表和资料表
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllContacts(int $userId)
    {
        return $this->query()
            ->join('users', 'users.id', '=', 'chat_contacts.contact_user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'chat_contacts.contact_user_id')
            ->where('chat_contacts.user_id', $userId)
            ->where('chat_contacts.is_del', CommonEnum::NO)
            ->select([
                'chat_contacts.id',
                'chat_contacts.contact_user_id',
                'chat_contacts.status',
                'chat_contacts.is_initiator',
                'chat_contacts.created_at',
                'users.username',
                'user_profiles.avatar',
            ])
            ->orderByDesc('chat_contacts.created_at')
            ->get();
    }

    /**
     * 检查两个用户是否为已确认的联系人
     */
    public function isConfirmedContact(int $userIdA, int $userIdB): bool
    {
        return $this->query()
            ->where('user_id', $userIdA)
            ->where('contact_user_id', $userIdB)
            ->where('status', ChatEnum::CONTACT_CONFIRMED)
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }

    /**
     * 检查联系人关系是否存在（任一方向，未删除）
     */
    public function contactExists(int $userIdA, int $userIdB): bool
    {
        return $this->query()
            ->where(function ($q) use ($userIdA, $userIdB) {
                $q->where(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdA)->where('contact_user_id', $userIdB);
                })->orWhere(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdB)->where('contact_user_id', $userIdA);
                });
            })
            ->where('is_del', CommonEnum::NO)
            ->exists();
    }


    /**
     * 检查是否存在已软删除的双向联系人记录
     */
    public function existsDeletedBidirectional(int $userIdA, int $userIdB): bool
    {
        return $this->query()
            ->where(function ($q) use ($userIdA, $userIdB) {
                $q->where(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdA)->where('contact_user_id', $userIdB);
                })->orWhere(function ($q2) use ($userIdA, $userIdB) {
                    $q2->where('user_id', $userIdB)->where('contact_user_id', $userIdA);
                });
            })
            ->where('is_del', CommonEnum::YES)
            ->exists();
    }

    /**
     * 复活已软删除的双向联系人记录，重置为待确认状态
     */
    public function reactivateBidirectional(int $initiatorId, int $targetId): void
    {
        $now = date('Y-m-d H:i:s');

        // 发起方记录
        $this->query()
            ->where('user_id', $initiatorId)
            ->where('contact_user_id', $targetId)
            ->where('is_del', CommonEnum::YES)
            ->update([
                'is_initiator' => CommonEnum::YES,
                'status' => ChatEnum::CONTACT_PENDING,
                'is_del' => CommonEnum::NO,
                'updated_at' => $now,
            ]);

        // 接收方记录
        $this->query()
            ->where('user_id', $targetId)
            ->where('contact_user_id', $initiatorId)
            ->where('is_del', CommonEnum::YES)
            ->update([
                'is_initiator' => CommonEnum::NO,
                'status' => ChatEnum::CONTACT_PENDING,
                'is_del' => CommonEnum::NO,
                'updated_at' => $now,
            ]);
    }

    /**
     * 获取指定联系人记录
     */
    public function getContact(int $userId, int $contactUserId)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('contact_user_id', $contactUserId)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }
}
