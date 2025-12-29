<?php

namespace app\dep;


use app\model\AddressModel;
use app\enum\CommonEnum;
use support\Cache;

class AddressDep
{
    public $model;
    
    // Redis 缓存Key（永久缓存，地址数据几乎不变）
    const CACHE_KEY_ALL_MAP = 'addr_all_map';

    public function __construct()
    {
        $this->model = new AddressModel();
    }

    /**
     * 获取全量地址Map（永久缓存）
     * @return array  id => address_row
     */
    public function getAllMap(): array
    {
        // 尝试从Redis缓存获取
        $cached = Cache::get(self::CACHE_KEY_ALL_MAP);
        if ($cached !== null) {
            return $cached;
        }
        
        // 查询并永久缓存（不设TTL）
        $all = $this->model->get();
        $map = [];
        foreach ($all as $item) {
            $map[$item->id] = $item->toArray();
        }
        
        Cache::set(self::CACHE_KEY_ALL_MAP, $map); // 无TTL = 永久
        
        return $map;
    }

    /**
     * 清除地址缓存（引入新地址数据时手动调用）
     */
    public static function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY_ALL_MAP);
    }

    /**
     * 根据district_id构建完整地址路径（省-市-区）
     * @param int $districtId
     * @return string
     */
    public function buildAddressPath(int $districtId): string
    {
        if (!$districtId) return '';
        
        $map = $this->getAllMap();
        $parts = [];
        $currentId = $districtId;
        $visited = []; // 防止死循环
        
        while (isset($map[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $node = $map[$currentId];
            // Redis反序列化后是数组
            $name = is_array($node) ? $node['name'] : $node->name;
            $parentId = is_array($node) ? $node['parent_id'] : $node->parent_id;
            
            array_unshift($parts, $name);
            if ($parentId === -1) break;
            $currentId = $parentId;
        }
        
        return implode('-', $parts);
    }

    public function first($id)
    {
        $res = $this->model->where('id', $id)->first();
        return $res;
    }

    public function firstByName($name){
        $res = $this->model->where('name',$name)->first();
        return $res;
    }

    public function firstByMobile($mobile){
        $res = $this->model->where('mobile',$mobile)->first();
        return $res;
    }

    public function all()
    {

        $res = $this->model->all();

        return $res;
    }

    public function add($data)
    {
        $res = $this->model->insertGetId($data);
        return $res;
    }

    public function edit($id, $data)
    {
        if(!is_array($id)){
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->update($data);
        return $res;
    }

    public function batchEdit($ids, $data)
    {
        $res = $this->model->whereIn('id', $ids)->update($data);
        return $res;
    }

    public function del($id, $data)
    {
        if(!is_array($id)){
            $id = [$id];
        }
        $res = $this->model->whereIn('id', $id)->update($data);
        return $res;
    }


    public function list($param){
        $res = $this->model
            ->where('is_del', CommonEnum::NO)
            ->when(!empty($param['username']), function ($query) use ($param) {
                $query->where('username','like' ,"%{$param['username']}%");
            })
            ->when(!empty($param['nickname']), function ($query) use ($param) {
                $query->where('nickname','like' ,"%{$param['nickname']}%");
            })
            ->when(!empty($param['status']), function ($query) use ($param) {
                $query->where('status', $param['status']);
            })
            ->when(!empty($param['platform']), function ($query) use ($param) {
                $query->where('platform', $param['platform']);
            })
            ->when(!empty($param['platform_id']), function ($query) use ($param) {
                $query->where('platform_id', $param['platform_id']);
            })
            ->when(!empty($param['mobile_id']), function ($query) use ($param) {
                $query->where('mobile_id', $param['mobile_id']);
            })
            ->when(!empty($param['legal_type']), function ($query) use ($param) {
                $query->where('legal_type', $param['legal_type']);
            })
            ->when(!empty($param['date']), function ($query) use ($param) {
                // 假设 date 参数是一个包含两个日期的数组
                if (is_array($param['date']) && count($param['date']) === 2) {
                    $query->whereBetween('register_at', [$param['date'][0], $param['date'][1]]);
                }
            })
            ->orderBy('id','desc')
            ->paginate($param['page_size'], ['*'], 'page', $param['current_page']);

        return $res;
    }

}
