<?php

namespace app\module\AiWorkLine\E_commerce\platform;


use app\dep\AiWorkLine\E_commerce\AccountDep;
use app\enum\AccountEnum;
use app\lib\AliCloud\AliCloudSdk;
use app\module\BaseModule;


class AliCloudModule extends BaseModule
{


    public function getToken()
    {
        $sdk = new AliCloudSdk();
        $accountDep = new AccountDep();
        $res = $sdk->getToken();
        $platformId = $res['UserId'];
        $resAccount = $accountDep->firstByPlatformId($platformId);
        $data = [
            'platform_id' => $platformId,
            'token' => $res['Id'],
            'platform' => AccountEnum::ALICLOUD,
            'token_exp_at' => $res['ExpireTime'],
            'username' => "管理员"
        ];
        if ($resAccount){
            $accountDep->edit($resAccount['id'],$data);
        }else{
            $accountDep->add($data);
        }
        return self::response();
    }
}

