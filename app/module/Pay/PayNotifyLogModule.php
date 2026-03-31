<?php

namespace app\module\Pay;

use app\dep\Pay\PayNotifyLogDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Pay\PayNotifyLogValidate;

class PayNotifyLogModule extends BaseModule
{
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setPayChannelArr()
            ->getDict();

        $dict['notify_type_arr'] = DictService::enumToDict(PayEnum::$notifyTypeArr);
        $dict['notify_process_status_arr'] = DictService::enumToDict(PayEnum::$notifyProcessStatusArr);

        return self::success(['dict' => $dict]);
    }

    public function list($request): array
    {
        $param = $this->validate($request, PayNotifyLogValidate::list());
        $res = $this->dep(PayNotifyLogDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id' => $item->id,
            'channel' => $item->channel,
            'channel_text' => PayEnum::$channelArr[$item->channel] ?? '',
            'notify_type' => $item->notify_type,
            'notify_type_text' => PayEnum::$notifyTypeArr[$item->notify_type] ?? '',
            'transaction_no' => $item->transaction_no,
            'trade_no' => $item->trade_no,
            'process_status' => $item->process_status,
            'process_status_text' => PayEnum::$notifyProcessStatusArr[$item->process_status] ?? '',
            'process_msg' => $item->process_msg,
            'ip' => $item->ip,
            'created_at' => $item->created_at,
        ]);

        $page = [
            'page_size' => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page' => $res->lastPage(),
            'total' => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    public function detail($request): array
    {
        $param = $this->validate($request, PayNotifyLogValidate::detail());
        $log = $this->dep(PayNotifyLogDep::class)->detail((int) $param['id']);
        self::throwNotFound($log, '回调日志不存在');

        return self::success([
            'log' => [
                'id' => $log->id,
                'channel' => $log->channel,
                'channel_text' => PayEnum::$channelArr[$log->channel] ?? '',
                'notify_type' => $log->notify_type,
                'notify_type_text' => PayEnum::$notifyTypeArr[$log->notify_type] ?? '',
                'transaction_no' => $log->transaction_no,
                'trade_no' => $log->trade_no,
                'process_status' => $log->process_status,
                'process_status_text' => PayEnum::$notifyProcessStatusArr[$log->process_status] ?? '',
                'process_msg' => $log->process_msg,
                'headers' => is_array($log->headers) ? $log->headers : [],
                'raw_data' => is_array($log->raw_data) ? $log->raw_data : [],
                'ip' => $log->ip,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at,
            ],
        ]);
    }
}
