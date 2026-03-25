<?php

namespace app\module\Pay;

use app\dep\Pay\PayReconcileTaskDep;
use app\enum\PayEnum;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Pay\PayReconcileValidate;

/**
 * 对账管理模块
 */
class PayReconcileModule extends BaseModule
{
    private const BILL_TYPE_LABELS = [
        1 => '支付',
        2 => '退款',
    ];

    /** 初始化 */
    public function init($request): array
    {
        $dict = $this->svc(DictService::class)
            ->setPayChannelArr()
            ->getDict();

        $dict['reconcile_status_arr'] = DictService::enumToDict(PayEnum::$reconcileStatusArr);
        $dict['bill_type_arr'] = array_map(
            fn(int $value, string $label) => compact('label', 'value'),
            array_keys(self::BILL_TYPE_LABELS),
            self::BILL_TYPE_LABELS
        );

        return self::success(['dict' => $dict]);
    }

    /** 列表 */
    public function list($request): array
    {
        $param = $this->validate($request, PayReconcileValidate::list());
        $res = $this->dep(PayReconcileTaskDep::class)->list($param);

        $list = $res->map(function ($item) {
            return [
                'id'               => $item->id,
                'reconcile_date'   => $item->reconcile_date,
                'channel'          => $item->channel,
                'channel_text'     => PayEnum::$channelArr[$item->channel] ?? '',
                'bill_type'        => $item->bill_type,
                'bill_type_text'   => self::BILL_TYPE_LABELS[$item->bill_type] ?? '',
                'status'           => $item->status,
                'status_text'      => PayEnum::$reconcileStatusArr[$item->status] ?? '',
                'platform_count'   => $item->platform_count,
                'platform_amount'  => $item->platform_amount,
                'local_count'      => $item->local_count,
                'local_amount'     => $item->local_amount,
                'diff_count'       => $item->diff_count,
                'diff_amount'      => $item->diff_amount,
                'started_at'       => $item->started_at,
                'finished_at'      => $item->finished_at,
                'created_at'       => $item->created_at,
            ];
        });

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /** 详情 */
    public function detail($request): array
    {
        $param = $this->validate($request, PayReconcileValidate::detail());
        $task = $this->dep(PayReconcileTaskDep::class)->getOrFail($param['id']);

        return self::success([
            'task' => [
                'id'               => $task->id,
                'reconcile_date'   => $task->reconcile_date,
                'channel'          => $task->channel,
                'channel_text'     => PayEnum::$channelArr[$task->channel] ?? '',
                'channel_id'       => $task->channel_id,
                'bill_type'        => $task->bill_type,
                'bill_type_text'   => self::BILL_TYPE_LABELS[$task->bill_type] ?? '',
                'status'           => $task->status,
                'status_text'      => PayEnum::$reconcileStatusArr[$task->status] ?? '',
                'platform_count'   => $task->platform_count,
                'platform_amount'  => $task->platform_amount,
                'local_count'      => $task->local_count,
                'local_amount'     => $task->local_amount,
                'diff_count'       => $task->diff_count,
                'diff_amount'      => $task->diff_amount,
                'platform_file'    => $task->platform_file,
                'local_file'      => $task->local_file,
                'diff_file'        => $task->diff_file,
                'started_at'       => $task->started_at,
                'finished_at'      => $task->finished_at,
                'error_msg'       => $task->error_msg,
                'created_at'       => $task->created_at,
            ],
        ]);
    }

    /** 重试 */
    public function retry($request): array
    {
        $param = $this->validate($request, PayReconcileValidate::retry());
        $task = $this->dep(PayReconcileTaskDep::class)->getOrFail($param['id']);

        self::throwIf($task->status !== PayEnum::RECONCILE_FAILED, '当前状态不支持重试');

        $this->dep(PayReconcileTaskDep::class)->update($task->id, [
            'status'        => PayEnum::RECONCILE_PENDING,
            'started_at'    => null,
            'finished_at'   => null,
            'platform_file' => '',
            'local_file'    => '',
            'diff_file'     => '',
            'diff_count'    => 0,
            'diff_amount'   => 0,
            'error_msg'     => '',
        ]);

        return self::success();
    }
}
