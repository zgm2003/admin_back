<?php

namespace app\dep;

use app\enum\CommonEnum;
use app\model\AddressModel;
use support\Cache;
use support\Model;

/**
 * Address Dep
 */
class AddressDep extends BaseDep
{
    const CACHE_KEY_ALL_MAP = 'addr_all_map';

    protected function createModel(): Model
    {
        return new AddressModel();
    }

    // ==================== Query ====================

    public function findByName(string $name)
    {
        return $this->model
            ->where('name', $name)
            ->where('is_del', CommonEnum::NO)
            ->first();
    }

    /**
     * @return array id => address row
     */
    public function getAllMap(): array
    {
        $cached = Cache::get(self::CACHE_KEY_ALL_MAP);
        if ($cached !== null) {
            return $cached;
        }

        $all = $this->model->where('is_del', CommonEnum::NO)->get();
        $map = [];
        foreach ($all as $item) {
            $map[$item->id] = $item->toArray();
        }

        Cache::set(self::CACHE_KEY_ALL_MAP, $map);

        return $map;
    }

    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_ALL_MAP);
    }
}
