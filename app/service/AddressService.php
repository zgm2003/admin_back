<?php

namespace app\service;

use app\dep\AddressDep;

/**
 * 地址服务
 * 负责：根据区县 ID 向上回溯构建完整地址路径（省-市-区）
 */
class AddressService
{
    private static ?AddressDep $dep = null;

    private static function dep(): AddressDep
    {
        return self::$dep ??= new AddressDep();
    }

    /**
     * 根据 district_id 构建完整地址路径（省-市-区）
     * 从叶子节点向上回溯至根节点（parent_id = 0），带环检测
     */
    public static function buildAddressPath(int $districtId): string
    {
        if (!$districtId) {
            return '';
        }

        $map = self::dep()->getAllMap();
        $parts = [];
        $currentId = $districtId;
        $visited = [];

        while (isset($map[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $node = $map[$currentId];

            // Redis 反序列化后是数组，ORM 查询是对象
            $name     = \is_array($node) ? $node['name'] : $node->name;
            $parentId = (int)(\is_array($node) ? $node['parent_id'] : $node->parent_id);

            array_unshift($parts, $name);

            if ($parentId <= 0) {
                break;
            }
            $currentId = $parentId;
        }

        return implode('-', $parts);
    }
}