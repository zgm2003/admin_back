<?php

namespace app\service;

use app\dep\AddressDep;

/**
 * 地址服务 - 业务格式化逻辑
 */
class AddressService
{
    private AddressDep $addressDep;

    public function __construct()
    {
        $this->addressDep = new AddressDep();
    }

    /**
     * 根据 district_id 构建完整地址路径（省-市-区）
     * 业务格式化逻辑，不属于纯数据访问
     */
    public function buildAddressPath(int $districtId): string
    {
        if (!$districtId) {
            return '';
        }

        $map = $this->addressDep->getAllMap();
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
}
