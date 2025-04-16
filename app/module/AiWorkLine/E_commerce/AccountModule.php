<?php

namespace app\module\AiWorkLine\E_commerce;

use app\dep\AiWorkLine\E_commerce\AccountDep;
use app\enum\AccountEnum;
use app\enum\CommonEnum;
use app\module\BaseModule;
use app\service\DictService;
use Carbon\Carbon;


class AccountModule extends BaseModule
{
    public $AccountDep;

    public function __construct()
    {
        $this->AccountDep = new AccountDep();
    }
    public function init($request)
    {
        $dictService = new DictService();
        $data['dict'] = $dictService
            ->setPlatformArr()
            ->getDict();
        return self::response($data);
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->AccountDep;

        $dep->del($param['id'], ['is_del' => CommonEnum::YES]);

        return self::response();
    }


    public function list($request)
    {
        $dep = $this->AccountDep;
        $param = $request->all();

        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 10;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;
        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item) {
            // 将 token_exp_at 转换为 Carbon 实例
            $tokenExpAt = Carbon::createFromTimestamp($item['token_exp_at']);

            return [
                'id' => $item['id'],
                'platform_id' => $item['platform_id'],
                'platform' => $item['platform'],
                'platform_name' => AccountEnum::$platformArr[$item['platform']],
                'username' => $item['username'],
                'token' => $item['token'],
                'token_exp_at' => $tokenExpAt->toDateTimeString(), // 格式化为日期字符串
                'is_exp' => $tokenExpAt->isPast() ? 1 : 2, // 使用 Carbon 的 isPast() 方法来判断是否过期
                'is_del' => $item['is_del'],
                'created_at' => $item['created_at']->toDateTimeString(),
                'updated_at' => $item['updated_at']->toDateTimeString()
            ];
        });

        $data['page'] = [
            'page_size' => $param['page_size'],
            'current_page' => $param['current_page'],
            'total_page' => $resList->lastPage(),
            'total' => $resList->total(),
        ];

        return self::response($data);
    }


}

