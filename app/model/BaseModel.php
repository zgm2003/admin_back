<?php

namespace app\model;

use support\Model;

/**
 * 项目 Model 基类
 * 所有业务 Model 应继承此类
 *
 * created_at / updated_at 由 MySQL DEFAULT CURRENT_TIMESTAMP 自动维护
 * Eloquent 不干预，避免 insertGetId 等 QueryBuilder 操作时字段为空
 */
class BaseModel extends Model
{
    public $timestamps = false;
}
