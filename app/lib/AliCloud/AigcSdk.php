<?php

namespace app\lib\AliCloud;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class AigcSdk
{
    public $cookie;

    public $client;

    public $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = getenv('AIGC_API_KEY');
    }

    public function chat($system,$user)
    {
        log_daily('chat')->info('chat',['system'=>$system,'user'=>$user]);
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json'
        ];
        $options = [
            'json' => [
                'model' => 'qwen-plus',
                'input' => [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $system
                        ],
                        [
                            'role' => 'user',
                            'content' => $user
                        ],
                    ]
                ],
                "parameters" => [
                    "result_format" => 'message'
                ]
            ],
            "verify" => false
        ];
        $request = new Request('POST', 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation', $headers);
        $res = $this->client->sendAsync($request, $options)->wait();
        log_daily('chat')->info('chat res',['content'=> $res->getBody()]);

        return json_decode($res->getBody(), true);
    }
}
