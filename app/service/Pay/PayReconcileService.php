<?php

namespace app\service\Pay;

use app\dep\Pay\PayChannelDep;
use app\dep\Pay\PayReconcileTaskDep;
use app\dep\Pay\PayTransactionDep;
use app\enum\PayEnum;
use RuntimeException;

class PayReconcileService
{
    private PayChannelDep $payChannelDep;
    private PayChannelService $payChannelService;
    private PayReconcileTaskDep $payReconcileTaskDep;
    private PayTransactionDep $payTransactionDep;

    public function __construct()
    {
        $this->payChannelDep = new PayChannelDep();
        $this->payChannelService = new PayChannelService();
        $this->payReconcileTaskDep = new PayReconcileTaskDep();
        $this->payTransactionDep = new PayTransactionDep();
    }

    public function execute(int $taskId): void
    {
        $task = $this->payReconcileTaskDep->getOrFail($taskId);

        try {
            $channel = $this->payChannelDep->findActive((int) $task->channel_id);
            if (!$channel) {
                throw new RuntimeException('对账渠道不存在或已禁用');
            }

            $this->payReconcileTaskDep->update((int) $task->id, [
                'status' => PayEnum::RECONCILE_DOWNLOAD,
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => null,
                'error_msg' => '',
            ]);

            $localSummary = $this->payTransactionDep->getSuccessfulBillRows((int) $channel->id, (string) $task->reconcile_date);
            $localFile = $this->writeBillFile(
                (string) $task->reconcile_date,
                "local_bill_{$task->id}.csv",
                $this->buildLocalBillCsv($localSummary['rows'])
            );

            $this->payReconcileTaskDep->update((int) $task->id, [
                'status' => PayEnum::RECONCILE_COMPARING,
                'local_count' => $localSummary['count'],
                'local_amount' => $localSummary['amount'],
                'local_file' => $localFile['relative_path'],
            ]);

            $platformBill = $this->payChannelService->downloadTradeBill($channel, (string) $task->reconcile_date);
            $platformFile = $this->writeBillFile((string) $task->reconcile_date, $platformBill['filename'], $platformBill['content']);
            $platformSummary = $this->parsePlatformBill((int) $channel->channel, $platformBill['content']);
            $comparison = $this->compareBills($localSummary['rows'], $platformSummary['rows'], $localSummary['count'], $platformSummary['count']);
            $diffCount = $comparison['diff_count'];
            $diffAmount = $platformSummary['amount'] - $localSummary['amount'];
            $status = ($diffCount === 0 && $diffAmount === 0)
                ? PayEnum::RECONCILE_SUCCESS
                : PayEnum::RECONCILE_DIFF;

            $diffFilePath = '';
            if ($status === PayEnum::RECONCILE_DIFF) {
                $diffFile = $this->writeBillFile(
                    (string) $task->reconcile_date,
                    "diff_bill_{$task->id}.json",
                    json_encode([
                        'reconcile_date' => (string) $task->reconcile_date,
                        'channel' => (int) $channel->channel,
                        'channel_id' => (int) $channel->id,
                        'platform_count' => $platformSummary['count'],
                        'platform_amount' => $platformSummary['amount'],
                        'local_count' => $localSummary['count'],
                        'local_amount' => $localSummary['amount'],
                        'diff_count' => $diffCount,
                        'diff_amount' => $diffAmount,
                        'details' => $comparison['details'],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );

                $diffFilePath = $diffFile['relative_path'];
            }

            $this->payReconcileTaskDep->update((int) $task->id, [
                'status' => $status,
                'platform_count' => $platformSummary['count'],
                'platform_amount' => $platformSummary['amount'],
                'diff_count' => $diffCount,
                'diff_amount' => $diffAmount,
                'platform_file' => $platformFile['relative_path'],
                'diff_file' => $diffFilePath,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_msg' => '',
            ]);
        } catch (\Throwable $e) {
            $this->payReconcileTaskDep->update((int) $task->id, [
                'status' => PayEnum::RECONCILE_FAILED,
                'finished_at' => date('Y-m-d H:i:s'),
                'error_msg' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    public function getDownloadFile(int $taskId, string $type): array
    {
        $task = $this->payReconcileTaskDep->getOrFail($taskId);
        $field = match ($type) {
            'platform' => 'platform_file',
            'local' => 'local_file',
            'diff' => 'diff_file',
            default => throw new RuntimeException('不支持的文件类型'),
        };

        $relativePath = (string) ($task->{$field} ?? '');
        if ($relativePath === '') {
            throw new RuntimeException('当前文件不存在');
        }

        $absolutePath = $this->resolveStoragePath($relativePath);
        if (!is_file($absolutePath)) {
            throw new RuntimeException('文件不存在或已被清理');
        }

        return [
            'path' => $absolutePath,
            'filename' => basename($absolutePath),
        ];
    }

    public function getPendingTasks(int $limit = 20): array
    {
        return $this->payReconcileTaskDep->getExecutableTasks($limit);
    }

    private function buildLocalBillCsv(array $rows): string
    {
        $lines = ['transaction_no,order_no,trade_no,amount,paid_at'];
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $this->escapeCsvCell((string) ($row['transaction_no'] ?? '')),
                $this->escapeCsvCell((string) ($row['order_no'] ?? '')),
                $this->escapeCsvCell((string) ($row['trade_no'] ?? '')),
                $this->escapeCsvCell(number_format(((int) ($row['amount'] ?? 0)) / 100, 2, '.', '')),
                $this->escapeCsvCell((string) ($row['paid_at'] ?? '')),
            ]);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function parsePlatformBill(int $channel, string $content): array
    {
        $normalized = $this->normalizeBillText($content);

        return match ($channel) {
            PayEnum::CHANNEL_WECHAT => $this->parseWechatBillData($normalized),
            PayEnum::CHANNEL_ALIPAY => $this->parseAlipayBillData($normalized),
            default => throw new RuntimeException('当前渠道不支持解析账单'),
        };
    }

    private function parseWechatBillData(string $content): array
    {
        $rows = $this->parsePlatformRows($content, [
            'merchant_order_no' => ['商户订单号', '商户订单号号', '商户单号', '商户订单号(out_trade_no)'],
            'trade_no' => ['微信支付订单号', '交易号', '平台订单号'],
            'amount' => ['订单金额', '金额', '应结订单金额'],
        ]);

        if (
            preg_match('/总交易单数[^\d]*(\d+)/u', $content, $countMatches) === 1 &&
            preg_match('/总交易额[^\d-]*(-?\d+(?:\.\d+)?)/u', $content, $amountMatches) === 1
        ) {
            return [
                'count' => (int) $countMatches[1],
                'amount' => (int) round(((float) $amountMatches[1]) * 100),
                'rows' => $rows,
            ];
        }

        return $this->buildRowSummary($rows);
    }

    private function parseAlipayBillData(string $content): array
    {
        $rows = $this->parsePlatformRows($content, [
            'merchant_order_no' => ['商户订单号', '订单号', '商户订单号（out_trade_no）'],
            'trade_no' => ['支付宝交易号', '交易号'],
            'amount' => ['订单金额（元）', '订单金额', '交易金额', '金额'],
        ]);

        if (
            preg_match('/业务笔数[^\d]*(\d+)/u', $content, $countMatches) === 1 &&
            preg_match('/订单金额[^\d-]*(-?\d+(?:\.\d+)?)/u', $content, $amountMatches) === 1
        ) {
            return [
                'count' => (int) $countMatches[1],
                'amount' => (int) round(((float) $amountMatches[1]) * 100),
                'rows' => $rows,
            ];
        }

        return $this->buildRowSummary($rows);
    }

    private function buildRowSummary(array $rows): array
    {
        $amount = 0;
        foreach ($rows as $row) {
            $amount += (int) ($row['amount'] ?? 0);
        }

        return ['count' => count($rows), 'amount' => $amount, 'rows' => $rows];
    }

    private function parsePlatformRows(string $content, array $headersMap): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $header = null;
        $headerIndexes = [];
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cells = $this->parseDelimitedLine($line);
            if ($cells === []) {
                continue;
            }

            if ($header === null) {
                $candidateIndexes = $this->resolveHeaderIndexes($cells, $headersMap);
                if ($candidateIndexes !== null) {
                    $header = $cells;
                    $headerIndexes = $candidateIndexes;
                }
                continue;
            }

            if ($this->isSummaryLine($cells[0] ?? '')) {
                continue;
            }

            $merchantOrderNo = $this->pickCellByIndex($cells, $headerIndexes['merchant_order_no'] ?? null);
            $tradeNo = $this->pickCellByIndex($cells, $headerIndexes['trade_no'] ?? null);
            $amount = $this->parseAmountFen($this->pickCellByIndex($cells, $headerIndexes['amount'] ?? null));

            if ($merchantOrderNo === '' && $tradeNo === '' && $amount === null) {
                continue;
            }

            $rows[] = [
                'merchant_order_no' => $merchantOrderNo,
                'trade_no' => $tradeNo,
                'amount' => $amount ?? 0,
            ];
        }

        return $rows;
    }

    private function compareBills(array $localRows, array $platformRows, int $localCount, int $platformCount): array
    {
        $details = [
            'missing_on_platform' => [],
            'missing_on_local' => [],
            'amount_mismatch' => [],
            'parse_issue' => null,
        ];

        $localMap = [];
        foreach ($localRows as $row) {
            $key = (string) ($row['transaction_no'] ?? '');
            if ($key === '') {
                $key = (string) ($row['trade_no'] ?? '');
            }
            if ($key === '') {
                continue;
            }

            $localMap[$key] = [
                'transaction_no' => (string) ($row['transaction_no'] ?? ''),
                'trade_no' => (string) ($row['trade_no'] ?? ''),
                'amount' => (int) ($row['amount'] ?? 0),
            ];
        }

        $platformMap = [];
        foreach ($platformRows as $row) {
            $key = (string) ($row['merchant_order_no'] ?? '');
            if ($key === '') {
                $key = (string) ($row['trade_no'] ?? '');
            }
            if ($key === '') {
                continue;
            }

            $platformMap[$key] = [
                'merchant_order_no' => (string) ($row['merchant_order_no'] ?? ''),
                'trade_no' => (string) ($row['trade_no'] ?? ''),
                'amount' => (int) ($row['amount'] ?? 0),
            ];
        }

        if ($platformCount > 0 && $platformMap === []) {
            $details['parse_issue'] = '未能解析平台账单明细，无法做逐单比对';
            return [
                'diff_count' => 1,
                'details' => $details,
            ];
        }

        foreach ($localMap as $key => $row) {
            if (!isset($platformMap[$key])) {
                $details['missing_on_platform'][] = $row;
                continue;
            }

            if ((int) $platformMap[$key]['amount'] !== (int) $row['amount']) {
                $details['amount_mismatch'][] = [
                    'key' => $key,
                    'local' => $row,
                    'platform' => $platformMap[$key],
                ];
            }
        }

        foreach ($platformMap as $key => $row) {
            if (!isset($localMap[$key])) {
                $details['missing_on_local'][] = $row;
            }
        }

        return [
            'diff_count' => count($details['missing_on_platform']) + count($details['missing_on_local']) + count($details['amount_mismatch']),
            'details' => $details,
        ];
    }

    private function resolveHeaderIndexes(array $cells, array $headersMap): ?array
    {
        $indexes = [];
        foreach ($headersMap as $field => $candidates) {
            $indexes[$field] = null;
            foreach ($cells as $index => $cell) {
                $title = trim((string) $cell);
                if (in_array($title, $candidates, true)) {
                    $indexes[$field] = $index;
                    break;
                }
            }
        }

        if ($indexes['amount'] === null || ($indexes['merchant_order_no'] === null && $indexes['trade_no'] === null)) {
            return null;
        }

        return $indexes;
    }

    private function parseDelimitedLine(string $line): array
    {
        $cells = str_getcsv($line);
        if (count($cells) <= 1 && str_contains($line, "\t")) {
            $cells = str_getcsv($line, "\t");
        }

        return array_map(static fn($cell) => trim((string) $cell), $cells);
    }

    private function isSummaryLine(string $firstCell): bool
    {
        $firstCell = trim($firstCell);
        if ($firstCell === '') {
            return true;
        }

        foreach (['总交易单数', '总交易额', '业务笔数', '业务明细', '汇总', '总计'] as $keyword) {
            if (str_contains($firstCell, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function pickCellByIndex(array $cells, ?int $index): string
    {
        if ($index === null || !isset($cells[$index])) {
            return '';
        }

        return trim((string) $cells[$index]);
    }

    private function parseAmountFen(string $raw): ?int
    {
        $normalized = preg_replace('/[^\d\.-]/', '', $raw);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function normalizeBillText(string $content): string
    {
        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $converted = @mb_convert_encoding($content, 'UTF-8', 'GBK,GB2312,BIG5,UTF-8');
        return is_string($converted) && $converted !== '' ? $converted : $content;
    }

    private function writeBillFile(string $date, string $filename, string $content): array
    {
        $dir = runtime_path() . DIRECTORY_SEPARATOR . 'pay_reconcile' . DIRECTORY_SEPARATOR . $date;
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('创建对账目录失败');
        }

        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);

        return [
            'absolute_path' => $path,
            'relative_path' => 'pay_reconcile/' . $date . '/' . $filename,
        ];
    }

    private function resolveStoragePath(string $relativePath): string
    {
        return runtime_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function escapeCsvCell(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
