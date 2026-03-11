<?php

namespace app\service\System;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel 导出服务
 * 负责：通用数据导出为 .xlsx 文件，保存到 public/export/{日期}/ 目录
 */
class ExportService
{
    /**
     * 通用导出方法
     *
     * @param array  $headers ['字段名' => '列标题']
     * @param array  $data    数据数组
     * @param string $prefix  文件名前缀
     * @return array{url: string, file_name: string, file_size: int, row_count: int, file_path: string}
     */
    public function export(array $headers, array $data, string $prefix = 'export'): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 写表头
        $colLetter = 'A';
        foreach ($headers as $title) {
            $sheet->setCellValue("{$colLetter}1", $title);
            $colLetter++;
        }

        // 写数据（全部以字符串类型写入，避免数字被科学计数法转换）
        $rowNum = 2;
        foreach ($data as $row) {
            $colLetter = 'A';
            foreach ($headers as $key => $title) {
                $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string)($row[$key] ?? ''), DataType::TYPE_STRING);
                $colLetter++;
            }
            $rowNum++;
        }

        // 按日期分目录存储
        $dateDir   = date('Ymd');
        $exportDir = public_path() . "/export/{$dateDir}";
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $fileName = "{$prefix}_" . date('Ymd_His') . '_' . uniqid() . '.xlsx';
        $filePath = "{$exportDir}/{$fileName}";

        (new Xlsx($spreadsheet))->save($filePath);

        $appUrl = rtrim(getenv('APP_URL') ?: '', '/');

        return [
            'url'       => "{$appUrl}/export/{$dateDir}/{$fileName}",
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'row_count' => \count($data),
            'file_path' => $filePath,
        ];
    }
}