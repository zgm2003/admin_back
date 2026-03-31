<?php

namespace app\controller\Pay;

use app\controller\Controller;
use app\module\Pay\PayReconcileModule;
use support\Request;
use support\Response;

/**
 * 对账管理
 */
class PayReconcileController extends Controller
{
    public function init(Request $request) { return $this->run([PayReconcileModule::class, 'init'], $request); }
    public function list(Request $request) { return $this->run([PayReconcileModule::class, 'list'], $request); }
    public function detail(Request $request) { return $this->run([PayReconcileModule::class, 'detail'], $request); }
    /** @OperationLog("重试对账任务") @Permission("pay_reconcile_retry") */
    public function retry(Request $request) { return $this->run([PayReconcileModule::class, 'retry'], $request); }
    public function download(Request $request): Response
    {
        [$data, $code, $msg] = (new PayReconcileModule())->download($request);
        if ($code !== 0) {
            return json(compact('code', 'data', 'msg'));
        }

        $path = (string) ($data['path'] ?? '');
        $filename = (string) ($data['filename'] ?? basename($path));
        $content = file_get_contents($path);

        return new Response(200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ], $content === false ? '' : $content);
    }
}
