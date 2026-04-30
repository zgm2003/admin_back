<?php

namespace app\model\Ai;

use app\model\BaseModel;

class CineProjectModel extends BaseModel
{
    protected $table = 'cine_projects';

    protected $casts = [
        'duration_seconds' => 'integer',
        'agent_id' => 'integer',
        'status' => 'integer',
        'draft_json' => 'json',
        'shotlist_json' => 'json',
        'feed_pack_json' => 'json',
        'reference_images_json' => 'json',
        'tool_config_json' => 'json',
        'continuity_review' => 'json',
        'is_del' => 'integer',
    ];
}
