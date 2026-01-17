<?php

namespace app\module\DevTools;

use app\dep\DevTools\GenDep;
use app\module\BaseModule;
use app\service\DevTools\CodeGenerator;

/**
 * 代码生成器 - 业务层
 */
class GenModule extends BaseModule
{
    protected GenDep $genDep;

    // 忽略的系统表
    private array $ignoreTables = [
        'migrations', 'failed_jobs', 'password_resets', 'personal_access_tokens'
    ];

    public function __construct()
    {
        $this->genDep = new GenDep();
    }

    /**
     * 获取数据库表列表
     */
    public function tables($request): array
    {
        $tables = $this->genDep->getTables();
        
        // 过滤系统表
        $tables = array_filter($tables, fn($t) => !in_array($t['table_name'], $this->ignoreTables));
        
        return self::success(array_values($tables));
    }

    /**
     * 获取表字段结构
     */
    public function columns($request): array
    {
        $param = $request->all();
        $tableName = $param['table'] ?? '';
        
        self::throwIf(!$tableName, '请选择表');
        self::throwIf(!$this->genDep->tableExists($tableName), '表不存在');

        $columns = $this->genDep->getColumns($tableName);
        
        // 为每个字段添加默认配置
        foreach ($columns as &$col) {
            $name = $col['column_name'];
            
            // 列表显示：排除敏感字段、系统字段、长文本
            $col['show_in_list'] = !in_array($name, [
                'password', 'deleted_at', 'is_del', 'content', 'description', 'remark'
            ]);
            
            // 搜索条件：只有明确的"名称"类字段才默认开启
            $col['show_in_search'] = in_array($name, [
                'name', 'title', 'username', 'email', 'nickname'
            ]);
            
            // 表单显示：排除主键、时间戳、软删除标记
            $col['show_in_form'] = !in_array($name, [
                'id', 'created_at', 'updated_at', 'deleted_at', 'is_del'
            ]);
            
            $col['form_type'] = $this->guessFormType($col);
            $col['dict_type'] = $this->guessDictType($col);
        }

        return self::success($columns);
    }

    /**
     * 预览生成代码
     */
    public function preview($request): array
    {
        $param = $request->all();
        $config = $this->validateConfig($param);
        
        $generator = new CodeGenerator($config);
        $files = $generator->preview();

        return self::success($files);
    }

    /**
     * 生成代码文件
     */
    public function generate($request): array
    {
        $param = $request->all();
        $config = $this->validateConfig($param);
        
        $generator = new CodeGenerator($config);
        $result = $generator->generate();

        return self::success($result, '生成成功');
    }

    /**
     * 验证配置
     */
    private function validateConfig(array $param): array
    {
        $config = [
            'table_name' => $param['table_name'] ?? '',
            'module_name' => $param['module_name'] ?? '',
            'domain' => $param['domain'] ?? 'System',
            'menu_name' => $param['menu_name'] ?? '',
            'route_path' => $param['route_path'] ?? '',
            'columns' => $param['columns'] ?? [],
        ];

        self::throwIf(!$config['table_name'], '请选择表');
        self::throwIf(!$config['module_name'], '请输入模块名');
        self::throwIf(!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $config['module_name']), '模块名必须是大驼峰格式，如 Article');
        self::throwIf(!$config['domain'], '请选择业务域');
        self::throwIf(!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $config['domain']), '业务域必须是大驼峰格式，如 System');
        self::throwIf(empty($config['columns']), '请配置字段');

        // 默认值
        if (!$config['route_path']) {
            $config['route_path'] = '/' . lcfirst($config['domain']) . '/' . lcfirst($config['module_name']);
        }
        if (!$config['menu_name']) {
            $config['menu_name'] = $config['module_name'];
        }

        return $config;
    }

    /**
     * 猜测表单类型
     */
    private function guessFormType(array $col): string
    {
        $name = $col['column_name'];
        $type = $col['data_type'];
        $comment = $col['column_comment'] ?? '';

        // 密码字段
        if (str_contains($name, 'password')) {
            return 'password';
        }
        
        // 图片字段
        if (str_contains($name, 'image') || str_contains($name, 'avatar') || 
            str_contains($name, 'logo') || str_contains($name, 'icon') ||
            str_contains($name, 'cover') || str_contains($name, 'photo')) {
            return 'image';
        }
        
        // 富文本字段
        if (str_contains($name, 'content') && in_array($type, ['text', 'longtext', 'mediumtext'])) {
            return 'editor';
        }
        
        // 文本域字段
        if (str_contains($name, 'desc') || str_contains($name, 'remark') || 
            str_contains($name, 'note') || str_contains($name, 'intro')) {
            return 'textarea';
        }
        
        // 下拉选择字段
        if (str_contains($name, 'status') || str_contains($name, 'type') || 
            str_contains($name, 'is_') || str_contains($name, 'sex') ||
            str_contains($name, 'gender') || str_contains($name, 'level')) {
            return 'select';
        }
        
        // 日期时间字段
        if (str_contains($name, 'time') || str_contains($name, '_at')) {
            return 'datetime';
        }
        if (str_contains($name, 'date')) {
            return 'date';
        }

        // 根据数据类型猜测
        if (in_array($type, ['text', 'longtext', 'mediumtext'])) {
            return 'textarea';
        }
        if (in_array($type, ['int', 'bigint', 'tinyint', 'smallint', 'decimal', 'float', 'double'])) {
            // 如果是 tinyint(1) 可能是布尔值
            if ($type === 'tinyint' && isset($col['max_length']) && $col['max_length'] == 1) {
                return 'select';
            }
            return 'number';
        }

        return 'input';
    }

    /**
     * 猜测字典类型
     */
    private function guessDictType(array $col): string
    {
        $name = $col['column_name'];
        
        if ($name === 'status') return 'status';
        if (str_starts_with($name, 'is_')) return 'yes_no';
        if ($name === 'sex') return 'sex';
        
        return '';
    }
}
