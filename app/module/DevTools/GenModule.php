<?php

namespace app\module\DevTools;

use app\dep\DevTools\GenDep;
use app\module\BaseModule;
use app\service\DevTools\CodeGenerator;

/**
 * 代码生成器模块
 * 负责：读取数据库表结构、智能推断表单/字典类型、预览和生成 CRUD 代码
 * 生成范围：Controller / Module / Dep / Model / Validate / 前端页面 / API / 路由
 */
class GenModule extends BaseModule
{
    /** 忽略的系统表（不参与代码生成） */
    private const IGNORE_TABLES = [
        'migrations', 'failed_jobs', 'password_resets', 'personal_access_tokens',
    ];

    /**
     * 获取数据库表列表（过滤系统表后返回）
     */
    public function tables($request): array
    {
        $tables = $this->dep(GenDep::class)->getTables();

        // 过滤系统表
        $tables = array_filter($tables, fn($t) => !\in_array($t['table_name'], self::IGNORE_TABLES));

        return self::success(array_values($tables));
    }

    /**
     * 获取表字段结构（附带智能推断的表单类型、字典类型、显示配置）
     */
    public function columns($request): array
    {
        $param = $request->all();
        $tableName = $param['table'] ?? '';
        $dep = $this->dep(GenDep::class);

        self::throwIf(!$tableName, '请选择表');
        self::throwIf(!$dep->tableExists($tableName), '表不存在');

        $columns = $dep->getColumns($tableName);

        // 为每个字段添加默认配置（列表显示、搜索、表单、组件类型）
        foreach ($columns as &$col) {
            $name = $col['column_name'];

            // 列表显示：排除敏感字段、系统字段、长文本
            $col['show_in_list'] = !\in_array($name, [
                'password', 'deleted_at', 'is_del', 'content', 'description', 'remark',
            ]);

            // 搜索条件：只有明确的"名称"类字段才默认开启
            $col['show_in_search'] = \in_array($name, [
                'name', 'title', 'username', 'email', 'nickname',
            ]);

            // 表单显示：排除主键、时间戳、软删除标记
            $col['show_in_form'] = !\in_array($name, [
                'id', 'created_at', 'updated_at', 'deleted_at', 'is_del',
            ]);

            $col['form_type'] = self::guessFormType($col);
            $col['dict_type'] = self::guessDictType($col);
        }

        return self::success($columns);
    }

    /**
     * 预览生成代码（不写入文件，仅返回各文件内容供前端展示）
     */
    public function preview($request): array
    {
        $config = $this->validateConfig($request->all());
        $files = (new CodeGenerator($config))->preview();

        return self::success($files);
    }

    /**
     * 生成代码文件（写入磁盘）
     */
    public function generate($request): array
    {
        $config = $this->validateConfig($request->all());
        $result = (new CodeGenerator($config))->generate();

        return self::success($result, '生成成功');
    }

    // ==================== 私有方法 ====================

    /**
     * 校验生成配置（表名、模块名、业务域、字段列表）
     * 模块名和业务域必须大驼峰格式
     */
    private function validateConfig(array $param): array
    {
        $config = [
            'table_name'  => $param['table_name'] ?? '',
            'module_name' => $param['module_name'] ?? '',
            'domain'      => $param['domain'] ?? 'System',
            'menu_name'   => $param['menu_name'] ?? '',
            'route_path'  => $param['route_path'] ?? '',
            'columns'     => $param['columns'] ?? [],
        ];

        self::throwIf(!$config['table_name'], '请选择表');
        self::throwIf(!$config['module_name'], '请输入模块名');
        self::throwIf(!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $config['module_name']), '模块名必须是大驼峰格式，如 Article');
        self::throwIf(!$config['domain'], '请选择业务域');
        self::throwIf(!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $config['domain']), '业务域必须是大驼峰格式，如 System');
        self::throwIf(empty($config['columns']), '请配置字段');

        // 默认路由路径：/domain/moduleName（小驼峰）
        if (!$config['route_path']) {
            $config['route_path'] = '/' . lcfirst($config['domain']) . '/' . lcfirst($config['module_name']);
        }
        // 默认菜单名称：模块名
        if (!$config['menu_name']) {
            $config['menu_name'] = $config['module_name'];
        }

        return $config;
    }

    /**
     * 根据字段名和数据类型智能推断表单组件类型
     * 优先级：字段名语义 > 数据库类型 > 默认 input
     */
    private static function guessFormType(array $col): string
    {
        $name = $col['column_name'];
        $type = $col['data_type'];

        // 密码字段
        if (str_contains($name, 'password')) {
            return 'password';
        }

        // 图片/头像/封面类字段
        if (str_contains($name, 'image') || str_contains($name, 'avatar')
            || str_contains($name, 'logo') || str_contains($name, 'icon')
            || str_contains($name, 'cover') || str_contains($name, 'photo')) {
            return 'image';
        }

        // 富文本（content + 长文本类型）
        if (str_contains($name, 'content') && \in_array($type, ['text', 'longtext', 'mediumtext'])) {
            return 'editor';
        }

        // 文本域（描述、备注、简介类）
        if (str_contains($name, 'desc') || str_contains($name, 'remark')
            || str_contains($name, 'note') || str_contains($name, 'intro')) {
            return 'textarea';
        }

        // 下拉选择（状态、类型、布尔、性别、等级）
        if (str_contains($name, 'status') || str_contains($name, 'type')
            || str_contains($name, 'is_') || str_contains($name, 'sex')
            || str_contains($name, 'gender') || str_contains($name, 'level')) {
            return 'select';
        }

        // 日期时间：优先按数据库类型判断
        if ($type === 'date') {
            return 'date';
        }
        if ($type === 'datetime' || $type === 'timestamp') {
            return 'datetime';
        }

        // 日期时间：再按字段名判断
        if (str_contains($name, 'time') || str_contains($name, '_at')) {
            return 'datetime';
        }
        if (str_contains($name, 'date') || str_contains($name, 'birthday')) {
            return 'date';
        }

        // 长文本类型
        if (\in_array($type, ['text', 'longtext', 'mediumtext'])) {
            return 'textarea';
        }

        // 数值类型（tinyint(1) 视为布尔选择）
        if (\in_array($type, ['int', 'bigint', 'tinyint', 'smallint', 'decimal', 'float', 'double'])) {
            if ($type === 'tinyint' && isset($col['max_length']) && $col['max_length'] == 1) {
                return 'select';
            }
            return 'number';
        }

        return 'input';
    }

    /**
     * 根据字段名推断字典类型（status → status, is_* → yes_no, sex/gender → sex）
     */
    private static function guessDictType(array $col): string
    {
        $name = $col['column_name'];

        if ($name === 'status') {
            return 'status';
        }
        if (str_starts_with($name, 'is_')) {
            return 'yes_no';
        }
        if ($name === 'sex' || $name === 'gender') {
            return 'sex';
        }

        return '';
    }
}