<?php

namespace app\service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    /**
     * 通用导出方法 - 保存到 public/export
     * @param array $headers ['字段名'=>'列标题']
     * @param array $data 数据数组
     * @param string $prefix 文件名前缀
     * @return array ['url' => '下载URL', 'file_name' => '文件名', 'file_size' => '文件大小', 'row_count' => '行数']
     */
    public function export(array $headers, array $data, string $prefix = 'export'): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 写表头
        $colLetter = 'A';
        foreach ($headers as $key => $title) {
            $sheet->setCellValue($colLetter . '1', $title);
            $colLetter++;
        }

        // 写数据
        $rowNum = 2;
        foreach ($data as $row) {
            $colLetter = 'A';
            foreach ($headers as $key => $title) {
                $sheet->setCellValueExplicit(
                    $colLetter . $rowNum, 
                    strval($row[$key] ?? ''), 
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $colLetter++;
            }
            $rowNum++;
        }

        // 文件路径（按日期分类）
        $dateDir = date('Ymd');
        $exportDir = public_path() . '/export/' . $dateDir;
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $fileName = $prefix . '_' . date('Ymd_His') . '_' . uniqid() . '.xlsx';
        $filePath = $exportDir . '/' . $fileName;

        // 保存 Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // 返回下载 URL（只存相对路径，前端动态拼接完整地址）
        return [
            'url' => '/export/' . $dateDir . '/' . $fileName,
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'row_count' => count($data),
            'file_path' => $filePath,  // 本地路径，方便清理
        ];
    }
}
