<?php

namespace app\model\DevTools;

use support\Model;

class ExportTaskModel extends Model
{
    protected $table = 'export_tasks';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
