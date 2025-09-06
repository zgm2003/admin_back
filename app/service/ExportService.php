<?php
namespace app\service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    /**
     * 通用导出方法
     * @param array $headers ['字段名'=>'列标题']
     * @param array $data 数据数组
     * @param string $prefix 文件名前缀
     * @return string 下载URL
     */
    public function export(array $headers, array $data, string $prefix = 'export'): string
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
        // 写数据时强制转字符串，防止 null/对象导致 Excel 错误
        foreach ($data as $row) {
            $colLetter = 'A';
            foreach ($headers as $key => $title) {
                $sheet->setCellValueExplicit($colLetter . $rowNum, strval($row[$key] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $colLetter++;
            }
            $rowNum++;
        }


        // 文件路径（按日期分类）
        $dateDir = date('Ymd');
        $exportDir = __DIR__ . '/../../public/export/' . $dateDir;
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $fileName = $prefix . '_' . date('Ymd_His') . '.xlsx';
        $filePath = $exportDir . '/' . $fileName;

        // 保存 Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // 返回下载 URL
        $appUrl = rtrim(getenv('APP_URL'), '/');
        return $appUrl . '/export/' . $dateDir . '/' . $fileName;
    }
}
