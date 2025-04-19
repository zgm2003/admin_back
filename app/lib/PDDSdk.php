<?php
namespace app\lib;

use app\dep\AiWorkLine\E_commerce\AccountDep;
use app\enum\AccountEnum;
use GuzzleHttp\Client;

class PDDSdk
{
    private $client_id;
    private $client_secret;

    public function __construct()
    {
        $this->client_id = getenv('PINDUODUO_CLIENT_ID');
        $this->client_secret = getenv('PINDUODUO_SECRET');
    }

    // 获取公共参数
    private function getCommonParams($type,$needToken = true)
    {
        $arr = [
            'type' => $type,
            'client_id' => $this->client_id,
            'timestamp' => time(),
            'data_type' => 'JSON',
        ];
        if($needToken){
            $arr['access_token'] = $this->getAccessToken();
        }
        return $arr;
    }

    private function getSignedParams($params)
    {
        $params['sign'] = $this->generateSign($params, $this->client_secret);
        return $params;
    }


    public function getToken($code)
    {
        $url = 'https://gw-api.pinduoduo.com/api/router';

        // 获取公共参数
        $params = $this->getCommonParams('pdd.pop.auth.token.create',false);
        $params['code'] = $code;

        // 签名参数
        $params = $this->getSignedParams($params);

        $client = new Client();
        $response = $client->request('GET', $url, [
            'query' => $params,
            'verify' => false
        ]);

        return json_decode($response->getBody(), true);
    }

    // 商品搜索
    public function searchGoods($page = 1, $pageSize = 100, $sort_type = 0, $opt_id = '', $cat_id = '', $range_list = [],$keyword = '')
    {
        $url = 'https://gw-api.pinduoduo.com/api/router';

        // 获取公共参数
        $params = $this->getCommonParams('pdd.ddk.oauth.goods.search');
        $params = array_merge($params, [
            'page' => $page,
            'page_size' => $pageSize,
            'sort_type' => $sort_type,
            'opt_id' => $opt_id,
            'cat_id' => $cat_id,
            'keyword' => $keyword,
            'pid' => '41723549_295790389',
        ]);

        // 如果有传入 range_list，添加到请求参数中
        if (!empty($range_list)) {
            $params['range_list'] = json_encode($range_list);
        }

        // 签名参数
        $params = $this->getSignedParams($params);

        $client = new Client();
        $response = $client->request('GET', $url, [
            'query' => $params,
            'verify' => false
        ]);

        return json_decode($response->getBody(), true);
    }


    public function optList()
    {
        $url = 'https://gw-api.pinduoduo.com/api/router';

        // 获取公共参数
        $params = $this->getCommonParams('pdd.goods.opt.get');
        $params = array_merge($params, [
           'parent_opt_id' => 0,

        ]);

        // 签名参数
        $params = $this->getSignedParams($params);

        $client = new Client();
        $response = $client->request('GET', $url, [
            'query' => $params,
            'verify' => false
        ]);

        return json_decode($response->getBody(), true);
    }

    public function catList()
    {
        $url = 'https://gw-api.pinduoduo.com/api/router';

        // 获取公共参数
        $params = $this->getCommonParams('pdd.goods.cats.get');
        $params = array_merge($params, [
            'parent_cat_id' => 0,

        ]);

        // 签名参数
        $params = $this->getSignedParams($params);

        $client = new Client();
        $response = $client->request('GET', $url, [
            'query' => $params,
            'verify' => false
        ]);

        return json_decode($response->getBody(), true);
    }

    public function goodsDetail($goods_sign)
    {
        $url = 'https://gw-api.pinduoduo.com/api/router';

        // 获取公共参数
        $params = $this->getCommonParams('pdd.ddk.oauth.goods.detail');
        $params = array_merge($params, [
            'goods_id' => $goods_sign,
            'access_token' => $this->getAccessToken(),
        ]);

        // 签名参数
        $params = $this->getSignedParams($params);

        $client = new Client();
        $response = $client->request('GET', $url, [
            'query' => $params,
            'verify' => false
        ]);

        return json_decode($response->getBody(), true);
    }



    // 签名生成函数
    private function generateSign($params, $client_secret)
    {
        ksort($params);
        $string_to_sign = $client_secret;
        foreach ($params as $key => $value) {
            $string_to_sign .= $key . $value;
        }
        $string_to_sign .= $client_secret;

        return strtoupper(md5($string_to_sign));
    }


    private function getAccessToken()
    {
        $accountDep = new AccountDep();
        $resAccount = $accountDep->firstByPlatform(AccountEnum::PINDUODUO);
        return $resAccount->token;
    }
}
