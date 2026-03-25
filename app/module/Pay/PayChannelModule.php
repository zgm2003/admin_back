<?php

namespace app\module\Pay;

use app\dep\Pay\PayChannelDep;
use app\enum\CommonEnum;
use app\enum\PayEnum;
use app\lib\Crypto\KeyVault;
use app\module\BaseModule;
use app\service\Common\DictService;
use app\validate\Pay\PayChannelValidate;

/**
 * 支付渠道管理模块
 */
class PayChannelModule extends BaseModule
{
    /** 初始化（返回渠道类型、状态字典） */
    public function init($request): array
    {
        $data['dict'] = $this->svc(DictService::class)
            ->setCommonStatusArr()
            ->setPayChannelArr()
            ->setPayMethodArr()
            ->getDict();

        return self::success($data);
    }

    /** 列表 */
    public function list($request): array
    {
        $param = $this->validate($request, PayChannelValidate::list());
        $res = $this->dep(PayChannelDep::class)->list($param);

        $list = $res->map(fn($item) => [
            'id'           => $item->id,
            'name'         => $item->name,
            'channel'      => $item->channel,
            'channel_name' => PayEnum::$channelArr[$item->channel] ?? '',
            'mch_id'       => $item->mch_id,
            'app_id'       => $item->app_id,
            'notify_url'   => $item->notify_url,
            'return_url'   => $item->return_url,
            'app_private_key_hint' => $item->app_private_key_hint ?? '',
            'public_cert_path'     => $item->public_cert_path ?? '',
            'platform_cert_path'  => $item->platform_cert_path ?? '',
            'root_cert_path'      => $item->root_cert_path ?? '',
            'is_sandbox'   => $item->is_sandbox,
            'is_sandbox_text' => $item->is_sandbox === CommonEnum::YES ? '是' : '否',
            'sort'         => $item->sort,
            'status'       => $item->status,
            'status_name'  => CommonEnum::$statusArr[$item->status] ?? '',
            'remark'       => $item->remark,
            'created_at'   => $item->created_at,
        ]);

        $page = [
            'page_size'    => $res->perPage(),
            'current_page' => $res->currentPage(),
            'total_page'   => $res->lastPage(),
            'total'        => $res->total(),
        ];

        return self::paginate($list, $page);
    }

    /** 新增 */
    public function add($request): array
    {
        $param = $this->validate($request, PayChannelValidate::add());
        $dep = $this->dep(PayChannelDep::class);

        self::throwIf(
            $dep->existsByChannelMchApp($param['channel'], $param['mch_id'], $param['app_id'] ?? ''),
            '该渠道+商户号+应用ID 组合已存在'
        );

        $data = [
            'name'                => $param['name'],
            'channel'             => (int) $param['channel'],
            'mch_id'              => $param['mch_id'],
            'app_id'              => $param['app_id'] ?? '',
            'notify_url'          => $param['notify_url'] ?? '',
            'return_url'          => $param['return_url'] ?? '',
            'public_cert_path'    => $param['public_cert_path'] ?? '',
            'platform_cert_path'  => $param['platform_cert_path'] ?? '',
            'root_cert_path'      => $param['root_cert_path'] ?? '',
            'is_sandbox'          => (int) ($param['is_sandbox'] ?? CommonEnum::NO),
            'sort'                => (int) ($param['sort'] ?? 0),
            'status'              => (int) ($param['status'] ?? CommonEnum::YES),
            'remark'              => $param['remark'] ?? '',
        ];

        if (!empty($param['app_private_key'])) {
            $data['app_private_key_enc'] = KeyVault::encrypt($param['app_private_key']);
            $data['app_private_key_hint'] = $param['app_private_key_hint'] ?? '';
        }

        $dep->add($data);

        return self::success();
    }

    /** 编辑 */
    public function edit($request): array
    {
        $param = $this->validate($request, PayChannelValidate::edit());
        $id = (int) $param['id'];
        $dep = $this->dep(PayChannelDep::class);

        $dep->getOrFail($id);

        if ($dep->existsByChannelMchApp(
            $param['channel'] ?? 0,
            $param['mch_id'] ?? '',
            $param['app_id'] ?? '',
            $id
        )) {
            self::throw('该渠道+商户号+应用ID 组合已存在');
        }

        $data = [
            'name'                => $param['name'] ?? null,
            'channel'             => isset($param['channel']) ? (int) $param['channel'] : null,
            'mch_id'              => $param['mch_id'] ?? null,
            'app_id'              => $param['app_id'] ?? null,
            'notify_url'          => $param['notify_url'] ?? null,
            'return_url'          => $param['return_url'] ?? null,
            'public_cert_path'    => $param['public_cert_path'] ?? null,
            'platform_cert_path'  => $param['platform_cert_path'] ?? null,
            'root_cert_path'      => $param['root_cert_path'] ?? null,
            'sort'                => isset($param['sort']) ? (int) $param['sort'] : null,
            'is_sandbox'          => isset($param['is_sandbox']) ? (int) $param['is_sandbox'] : null,
            'status'              => isset($param['status']) ? (int) $param['status'] : null,
            'remark'              => $param['remark'] ?? null,
        ];
        $data = array_filter($data, fn($v) => $v !== null);

        if (!empty($param['app_private_key'])) {
            $data['app_private_key_enc'] = KeyVault::encrypt($param['app_private_key']);
            $data['app_private_key_hint'] = $param['app_private_key_hint'] ?? '';
        }

        $dep->update($id, $data);

        return self::success();
    }

    /** 删除（软删除） */
    public function del($request): array
    {
        $param = $this->validate($request, PayChannelValidate::del());
        $affected = $this->dep(PayChannelDep::class)->delete($param['id']);

        return self::success(['affected' => $affected]);
    }

    /** 切换状态 */
    public function status($request): array
    {
        $param = $this->validate($request, PayChannelValidate::setStatus());
        $affected = $this->dep(PayChannelDep::class)->setStatus($param['id'], (int) $param['status']);

        return self::success(['affected' => $affected]);
    }
}
