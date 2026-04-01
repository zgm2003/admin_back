<?php

namespace app\service\System;

use app\enum\UploadConfigEnum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel 导出服务
 * 负责：通用数据导出为 .xlsx 文件，并上传到当前启用的对象存储
 */
class ExportService
{
    /**
     * 通用导出方法
     *
     * @param array  $headers ['字段名' => '列标题']
     * @param array  $data    数据数组
     * @param string $prefix  文件名前缀
     * @return array{url: string, file_name: string, file_size: int, row_count: int}
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

        $fileName = "{$prefix}_" . date('Ymd_His') . '_' . uniqid() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('导出文件生成失败');
        }
        $dateDir = date('Ymd');
        $upload = (new UploadService())->uploadContent(
            $content,
            UploadConfigEnum::FOLDER_EXPORTS,
            $fileName,
            $dateDir
        );

        return [
            'url'       => $upload['url'],
            'file_name' => $fileName,
            'file_size' => $upload['size'],
            'row_count' => \count($data),
        ];
    }
}
