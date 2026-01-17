<?php

namespace app\dep\DevTools;

use support\Db;

/**
 * 代码生成器 - 数据层
 */
class GenDep
{
    /**
     * 获取所有数据库表
     */
    public function getTables(): array
    {
        $database = getenv('DB_DATABASE');
        $tables = Db::select("
            SELECT 
                TABLE_NAME as table_name,
                TABLE_COMMENT as table_comment,
                CREATE_TIME as created_at
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY CREATE_TIME DESC
        ", [$database]);

        return array_map(fn($t) => (array)$t, $tables);
    }

    /**
     * 获取表字段结构
     */
    public function getColumns(string $tableName): array
    {
        $database = getenv('DB_DATABASE');
        $columns = Db::select("
            SELECT 
                COLUMN_NAME as column_name,
                COLUMN_COMMENT as column_comment,
                DATA_TYPE as data_type,
                COLUMN_TYPE as column_type,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                IS_NULLABLE as is_nullable,
                COLUMN_KEY as column_key,
                COLUMN_DEFAULT as column_default,
                EXTRA as extra
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$database, $tableName]);

        return array_map(fn($c) => (array)$c, $columns);
    }

    /**
     * 检查表是否存在
     */
    public function tableExists(string $tableName): bool
    {
        $database = getenv('DB_DATABASE');
        $result = Db::select("
            SELECT COUNT(*) as cnt 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [$database, $tableName]);
        
        return $result[0]->cnt > 0;
    }
}
