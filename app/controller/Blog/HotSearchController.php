<?php
namespace app\controller\Blog;


use app\controller\Controller;
use GuzzleHttp\Client;
use support\Request;

class HotSearchController extends Controller{
    public function hostSearch(Request $request)
    {
        // 获取请求参数中的 type
        $type = $request->input('type');

        // 获取环境变量中的 API 密钥
        $accessKey = getenv('CUAPI_ACCESS_KEY');
        $secretKey = getenv('CUAPI_SECRET_KEY');

        // 创建 Guzzle 客户端实例
        $client = new Client();

        // 构造 API 请求的 URL 和参数
        $url = "https://www.coderutil.com/api/resou/v1/{$type}";
        $paramMap = [
            'access-key' => $accessKey,
            'secret-key' => $secretKey,
        ];

        try {
            // 发送 GET 请求
            $response = $client->get($url, [
                'query' => $paramMap // Guzzle 中的查询参数传递方式
            ]);

            // 获取响应的内容
            $result = json_decode($response->getBody()->getContents(), true);

            // 返回成功的 JSON 响应
            return response(json_encode($result),200);
        } catch (\Exception $e) {
            // 错误处理，返回失败信息
            return response(json_encode(['message' => $e->getMessage()]),500);

        }
    }


}
