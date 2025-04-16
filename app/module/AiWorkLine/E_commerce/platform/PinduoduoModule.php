<?php

namespace app\module\AiWorkLine\E_commerce\platform;


use app\dep\AiWorkLine\E_commerce\AccountDep;
use app\enum\AccountEnum;
use app\lib\PDDSdk;
use app\module\BaseModule;


class PinduoduoModule extends BaseModule
{

    public function callback($request)
    {
        $params = $request->all();
        $code = $params['code'];
        $lib = new PDDSdk();
        $res = $lib->getToken($code);
        $platformId = $res['pop_auth_token_create_response']['owner_id'];
        $accountDep = new AccountDep();
        $resAccount = $accountDep->firstByPlatformId($platformId);
        $data=[
            'token'=>$res['pop_auth_token_create_response']['access_token'],
            'platform_id' => $platformId,
            'platform' => AccountEnum::PINDUODUO,
            'username'=>$res['pop_auth_token_create_response']['owner_name'],
            'token_exp_at' => $res['pop_auth_token_create_response']['expires_at']
        ];
        if ($resAccount){
            $accountDep->edit($resAccount['id'],$data);
        }else{
            $accountDep->add($data);
        }

        return self::response($res);
    }

    public function loginKey()
    {

        $clientId = getenv('PINDUODUO_CLIENT_ID');
        $returnUrl = getenv('PINDUODUO_RETURN_URL');

        return self::response(['clientId' => $clientId, 'returnUrl' => $returnUrl]);
    }
}

