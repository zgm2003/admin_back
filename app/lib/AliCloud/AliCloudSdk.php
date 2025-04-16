<?php

namespace app\lib\AliCloud;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;



class AliCloudSdk
{
    /**
     * 获取 Token 的封装方法
     *
     * @return array|null 返回 token 数组，包含 "Id"、"ExpireTime" 等信息；失败则返回 null
     */
    public static function getToken()
    {
        try {
            // 设置全局客户端
            AlibabaCloud::accessKeyClient(
                getenv('ALIBABA_CLOUD_ACCESS_KEY_ID'),
                getenv('ALIBABA_CLOUD_ACCESS_KEY_SECRET')
            )
                ->regionId("cn-shanghai")
                ->asDefaultClient();

            // 请求获取 token
            $response = AlibabaCloud::nlsCloudMeta()
                ->v20180518()
                ->createToken()
                ->request();

            // 从响应中获取 Token 节点
            $token = isset($response["Token"]) ? $response["Token"] : null;
            if ($token !== null) {
                return $token;
            } else {
                return null;
            }
        } catch (ClientException $exception) {
            // 记录客户端错误
            error_log("ClientException: " . $exception->getErrorMessage());
            return null;
        } catch (ServerException $exception) {
            // 记录服务端错误
            error_log("ServerException: " . $exception->getErrorMessage());
            return null;
        }
    }



}
