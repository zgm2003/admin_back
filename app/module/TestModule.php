<?php

namespace app\module;

//导入部分
use app\common\RabbitMQ;
use app\dep\TestDep;
use app\enum\CommonEnum;
use app\service\DictService;
use Carbon\Carbon;
use Webman\RedisQueue\Redis;

class TestModule extends BaseModule
{
    public $TestDep;

    public function __construct()
    {
        $this->TestDep = new TestDep();
    }


    public function init(){

        $dictService = new DictService();

        $dict = $dictService
            ->getDict();

        $data['dict'] = $dict;

        return self::response($data);

    }


    public function add($request)
    {
        $param = $request->all();

        $dep = $this->TestDep;

        foreach (['password','newpassword','respassword'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }
        
        $data = [
            'mobile_id' => $param['mobile_id'],
        
        ];

        $dep->add($data);
        return self::response();
    }

    public function del($request)
    {

        $param = $request->all();

        $dep = $this->TestDep;

        $dep->del($param['id'],['is_del'=>CommonEnum::YES]);

        return self::response();
    }

    public function edit($request)
    {
        $param = $request->all()();
        $dep = $this->TestDep;

        foreach (['password','newpassword','respassword'] as $f) {
            if (empty($param[$f])) {
                return self::response([], "{$f} 不能为空", 100);
            }
        }



        $data = [
            'mobile_id' => $param['mobile_id'],
        ];

        $dep->edit($param['id'], $data);

        return self::response();
    }
    public function batchEdit($request)
    {

        $param = $request->all();
        $dep = $this->TestDep;
        $id = $param['ids'];
        if ($param['field'] == 'status') {
            $data = [
                'status' => $param['status'],
            ];
            $dep->edit($id, $data);
        }

        return self::response();
    }
    public function list($request)
    {

        $dep = new TestDep();
        $param = $request->all();
        $param['page_size'] = isset($param['page_size']) ? $param['page_size'] : 50;
        $param['current_page'] = isset($param['current_page']) ? $param['current_page'] : 1;

        $resList = $dep->list($param);

        $data['list'] = $resList->map(function ($item){
            return [
                'id' => $item['id'],
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
    public function test($request)
    {
        $param = $request->all();
//        // 队列名
        $queue = 'test-test';
//        // 数据，可以直接传数组，无需序列化
        $data = [
            'id' => 1
        ];
//        // 投递消息
        Redis::send($queue, $data);
        // 投递延迟消息，消息会在60秒后处理
//        Redis::send($queue, $data, 60);
        $data = [
            'msg' => 'hello world',
            'a' => $param,
        ];

        return self::response($data);
    }

    public function sendTest($request)
    {
        $param = $request->all();
        $data = [
            'id' => $param['id']
        ];
        $mq = new RabbitMQ(config('rabbitmq'));
        $mq->send('test_queue', json_encode($data));
        $mq->close();
        return self::response();
    }



}

