<?php

namespace app\dep;

use app\model\AddressModel;
use support\Cache;
use support\Model;

/**
 * 地址 Dep
 * 注意：此表没有 is_del 字段，数据几乎不变，使用永久缓存
 */
class AddressDep extends BaseDep
{
    const CACHE_KEY_ALL_MAP = 'addr_all_map';

    protected function createModel(): Model
    {
        return new AddressModel();
    }

    // ==================== 查询方法 ====================

    /**
     * 根据名称查询
     */
    public function findByName(string $name)
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * 获取全量地址 Map（永久缓存）
     * @return array id => address_row
     */
    public function getAllMap(): array
    {
        $cached = Cache::get(self::CACHE_KEY_ALL_MAP);
        if ($cached !== null) {
            return $cached;
        }

        $all = $this->model->get();
        $map = [];
        foreach ($all as $item) {
            $map[$item->id] = $item->toArray();
        }

        Cache::set(self::CACHE_KEY_ALL_MAP, $map); // 无 TTL = 永久

        return $map;
    }

    /**
     * 清除地址缓存
     */
    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_ALL_MAP);
    }

    /**
     * 根据 district_id 构建完整地址路径（省-市-区）
     */
    public function buildAddressPath(int $districtId): string
    {
        if (!$districtId) {
            return '';
        }

        $map = $this->getAllMap();
        $parts = [];
        $currentId = $districtId;
        $visited = []; // 防止死循环

        while (isset($map[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $node = $map[$currentId];
            // Redis 反序列化后是数组
            $name = is_array($node) ? $node['name'] : $node->name;
            $parentId = is_array($node) ? $node['parent_id'] : $node->parent_id;

            array_unshift($parts, $name);
            if ($parentId === -1) {
                break;
            }
            $currentId = $parentId;
        }

        return implode('-', $parts);
    }

    /**
     * 覆盖父类方法：此表没有 is_del 字段
     */
    public function get(int $id)
    {
        return $this->find($id);
    }
}
