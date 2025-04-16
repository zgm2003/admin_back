<?php

namespace app\module\AiWorkLine\E_commerce;

use app\dep\SystemDep;
use app\lib\PDDSdk;
use app\module\BaseModule;
use app\service\DictService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//use App\Exports\PinduoduoGoodsExport;
//use Maatwebsite\Excel\Facades\Excel;

class PinDuoDuoChangeGoodsModule extends BaseModule
{
    public function init($request)
    {
        $dictService = new DictService();

        $dictService->setPinduoduoSort();

        $data['dict'] = $dictService->getDict();
        $service = new PDDSdk();
        $res = $service->optList();
        $data['optList'] = [];
        if (isset($res['goods_opt_get_response']['goods_opt_list']) && !empty($res['goods_opt_get_response']['goods_opt_list'])) {
            // 遍历商品列表
            $data['optList'] = array_map(function ($item) {
                return [
                    'label' => $item['opt_name'],
                    'value' => $item['opt_id']
                ];
            }, $res['goods_opt_get_response']['goods_opt_list']);

        }

        $res = $service->catList();
        $data['catList'] = [];
        if (isset($res['goods_cats_get_response']['goods_cats_list']) && !empty($res['goods_cats_get_response']['goods_cats_list'])) {
            // 遍历商品列表
            $data['catList'] = array_map(function ($item) {
                return [
                    'label' => $item['cat_name'],
                    'value' => $item['cat_id']
                ];
            }, $res['goods_cats_get_response']['goods_cats_list']);

        }
        return self::response($data);
    }

    public function list($request)
    {
        // 获取请求参数
        $param = $request->all();

        $service = new PDDSdk();

        $range_list = [];


        // 佣金
        if (!empty($param['min_commission_rate']) || !empty($param['max_commission_rate'])) {
            $range_list[] = [
                'range_id' => 2,
                'range_from' => $param['min_commission_rate'] * 10 ?? 0,
                'range_to' => $param['max_commission_rate'] * 10 ?? 1000,
            ];
        }

        $res = $service->searchGoods(
            $param['current_page'] ?? "1",
            $param['page_size'] ?? '20',
            $param['sort_type'] ?? 0,
            $param['opt_id'] ?? '',
            $param['cat_id'] ?? '',
            $range_list,
            $param['keyword'] ?? '',
        );

        $data['list'] = [];

        // 判断 goods_list 是否存在并非空
        if (isset($res['goods_search_response']['goods_list']) && !empty($res['goods_search_response']['goods_list'])) {

            // 遍历商品列表
            $data['list'] = array_map(function ($item) {
                return [
                    'goods_id' => $item['goods_id'],
                    'goods_sign' => $item['goods_sign'],
                    'sales' => $item['sales_tip'],
                    'title' => $item['goods_name'],
                    'main_img' => $item['goods_image_url'],
                    'price' => $item['min_normal_price'] / 100,
                    'shop_title' => $item['mall_name'],
                    'commission_rate' => $item['promotion_rate'],
                ];
            }, $res['goods_search_response']['goods_list']);

            $data['total'] = $res['goods_search_response']['total_count'];
        }

        // 返回封装后的数据
        return self::response($data);
    }


    public function export($request)
    {
        // 获取传入的全部数据，格式如前端示例
        $param = $request->all();

        // 初始化导出数据数组
        $data = [];
        foreach ($param as $item) {
            $data[] = [
                'goods_id' => $item['goods_id'] ?? '',
//                'goods_sign'      => $item['goods_sign'] ?? '',
                'sales' => $item['sales'] ?? '',
                'title' => $item['title'] ?? '',
                'main_img' => $item['main_img'] ?? '',
                'price' => $item['price'] ?? '',
                'shop_title' => $item['shop_title'] ?? '',
                'commission_rate' => $item['commission_rate'] ?? '',
                'commission' => $item['commission'],
            ];
        }

        // 创建 PhpSpreadsheet 对象
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头（可以根据需要调整字段名称）
        $headers = [
            'A1' => '商品ID',
            'B1' => '销量',
            'C1' => '标题',
            'D1' => '主图',
            'E1' => '价格',
            'F1' => '店铺名称',
            'G1' => '佣金比例',
            'H1' => '佣金'
        ];
        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        // 设置数据行，从第2行开始
        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValueExplicit("A{$row}", (string)$item['goods_id'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $item['sales']);
            $sheet->setCellValue("C{$row}", $item['title']);
            $sheet->setCellValue("D{$row}", $item['main_img']);
            $sheet->setCellValue("E{$row}", $item['price']);
            $sheet->setCellValue("F{$row}", $item['shop_title']);
            $sheet->setCellValue("G{$row}", $item['commission_rate']);
            $sheet->setCellValue("H{$row}", $item['commission']);
            $row++;
        }

        // 动态生成文件名，例如：export_20250415_185407.xlsx
        $fileName = 'export_' . date('Ymd_His') . '.xlsx';
        // 假设 public_path() 是你项目中返回 public 目录的绝对路径的辅助函数
        $filePath = public_path() . '/' . $fileName;

        // 生成 Excel 文件
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // 根据 APP_URL 生成公开访问的下载链接
        $appUrl = getenv('APP_URL');
        $link = rtrim($appUrl, '/') . '/' . $fileName;

        // 返回统一的响应格式，可根据项目中 response 统一规范进行调整
        return self::response(['link' => $link, 'name' => $fileName]);
    }


}

