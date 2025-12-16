<?php

namespace app\module;

//导入部分
use app\dep\TestDep;
use app\enum\CommonEnum;
use app\process\CleanExportTask;
use app\service\DictService;
use Carbon\Carbon;
use Webman\RedisQueue\Redis;
use app\validate\Test\TestValidate;

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

        return self::success($data);

    }


    public function add($request)
    {
        try { $param = $this->validate($request, TestValidate::add()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }

        $dep = $this->TestDep;

        foreach (['password','newpassword','respassword'] as $f) {
            if (empty($param[$f])) {
                return self::error("{$f} 不能为空");
            }
        }
        
        $data = [
            'mobile_id' => $param['mobile_id'],
        
        ];

        $dep->add($data);
        return self::success();
    }

    public function del($request)
    {
        try { $param = $this->validate($request, TestValidate::del()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }

        $dep = $this->TestDep;

        $dep->del($param['id'],['is_del'=>CommonEnum::YES]);

        return self::success();
    }

    public function edit($request)
    {
        try { $param = $this->validate($request, TestValidate::edit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $dep = $this->TestDep;
        if ($param['newpassword'] !== $param['respassword']) {
            return self::error('两次输入不一致');
        }

        $data = [
            'mobile_id' => $param['mobile_id'],
        ];

        $dep->edit($param['id'], $data);

        return self::success();
    }
    public function batchEdit($request)
    {
        try { $param = $this->validate($request, TestValidate::batchEdit()); }
        catch (\RuntimeException $e) { return self::error($e->getMessage()); }
        $dep = $this->TestDep;
        $id = $param['ids'];
        if ($param['field'] == 'status') {
            $data = [
                'status' => $param['status'],
            ];
            $dep->edit($id, $data);
        }

        return self::success();
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

        return self::paginate($data['list'], $data['page']);
    }
//    public function test($request)
//    {
//        $param = $request->all();
//        // 队列名
//        $queue = 'test-test';
//        // 数据，可以直接传数组，无需序列化
//        $data = [
//            'id' => 1
//        ];
//       // 投递消息
//        Redis::send($queue, $data);
//        // 投递延迟消息，消息会在60秒后处理
////        Redis::send($queue, $data, 60);
//        $data = [
//            'msg' => 'hello world',
//            'a' => $param,
//        ];
//
//        return self::response($data);
//    }

    public function test($request)
    {
        $param = $request->all();
        $sdk = new CleanExportTask();
        $sdk->cleanOldFiles();
        return self::success();
    }

    // 已移除 RabbitMQ 相关示例方法



}

