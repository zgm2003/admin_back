CREATE TABLE IF NOT EXISTS `cine_projects` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建用户',
  `title` varchar(120) NOT NULL DEFAULT '' COMMENT '项目标题',
  `source_text` longtext NOT NULL COMMENT '原始素材',
  `style` varchar(255) NOT NULL DEFAULT '' COMMENT '视觉/叙事风格',
  `duration_seconds` int UNSIGNED NOT NULL DEFAULT 30 COMMENT '目标时长秒',
  `aspect_ratio` varchar(20) NOT NULL DEFAULT '9:16' COMMENT '画幅',
  `mode` varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft/visual',
  `agent_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用的AI智能体',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1草稿箱 2草稿生成中 3待审查 4分镜生成中 5已完成 6生成失败',
  `status_msg` varchar(500) DEFAULT NULL COMMENT '状态说明',
  `deliverable_markdown` longtext COMMENT '用户可读交付稿',
  `draft_json` json DEFAULT NULL COMMENT '成片预览/故事流程',
  `shotlist_json` json DEFAULT NULL COMMENT '分镜表',
  `feed_pack_json` json DEFAULT NULL COMMENT '外部视频工具生成提示词',
  `reference_images_json` json DEFAULT NULL COMMENT '人物/场景/风格参考图',
  `tool_config_json` json DEFAULT NULL COMMENT '工具配置快照',
  `continuity_review` json DEFAULT NULL COMMENT '连续性审查',
  `model_origin` longtext COMMENT '模型原始输出',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  KEY `idx_cine_projects_status_id` (`status`, `id`),
  KEY `idx_cine_projects_agent_id` (`agent_id`),
  KEY `idx_cine_projects_user_id_id` (`user_id`, `id`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI短剧工厂项目';

CREATE TABLE IF NOT EXISTS `cine_assets` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `project_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '短剧项目ID',
  `asset_type` varchar(30) NOT NULL DEFAULT 'keyframe' COMMENT 'reference/keyframe',
  `shot_id` varchar(20) NOT NULL DEFAULT '' COMMENT '镜头ID',
  `prompt` longtext COMMENT '图片/素材生成提示词',
  `file_url` varchar(512) DEFAULT NULL COMMENT '文件URL',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1待生成 2生成中 3已完成 4失败',
  `status_msg` varchar(500) DEFAULT NULL COMMENT '状态说明',
  `sort` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
  `meta_json` json DEFAULT NULL COMMENT '素材元数据',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  KEY `idx_cine_assets_project_sort` (`project_id`, `sort`),
  KEY `idx_cine_assets_project_shot` (`project_id`, `shot_id`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI短剧工厂素材';

INSERT INTO `permissions` (
  `name`, `path`, `icon`, `parent_id`, `component`, `platform`, `type`, `sort`, `code`, `i18n_key`, `show_menu`, `status`, `is_del`, `created_at`, `updated_at`
)
SELECT
  'AI短剧工厂', '/ai/cine', 'VideoCamera', p.id, 'ai/cine', 'admin', 2, 8, NULL, 'menu.ai_cine', 1, 1, 2, NOW(), NOW()
FROM `permissions` p
WHERE p.i18n_key = 'menu.ai'
  AND p.is_del = 2
  AND NOT EXISTS (
    SELECT 1 FROM `permissions` existing
    WHERE existing.platform = 'admin'
      AND existing.path = '/ai/cine'
      AND existing.is_del = 2
  );

UPDATE `permissions`
SET `name` = 'AI短剧工厂',
    `path` = '/ai/cine',
    `component` = 'ai/cine',
    `i18n_key` = 'menu.ai_cine',
    `show_menu` = 1,
    `status` = 1,
    `is_del` = 2,
    `updated_at` = NOW()
WHERE `platform` = 'admin'
  AND (`path` = '/ai/cine' OR `i18n_key` = 'menu.ai_cine');

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `is_del`, `created_at`, `updated_at`)
SELECT 2, p.id, 2, NOW(), NOW()
FROM `permissions` p
WHERE p.platform = 'admin'
  AND p.path = '/ai/cine'
  AND p.is_del = 2
  AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.role_id = 2
      AND rp.permission_id = p.id
      AND rp.is_del = 2
  );

INSERT INTO `ai_agents` (`name`, `model_id`, `avatar`, `system_prompt`, `mode`, `scene`, `status`, `is_del`, `created_at`, `updated_at`)
SELECT
  'AI短剧导演',
  m.id,
  NULL,
  '你是 AI短剧工厂的导演智能体。你负责把素材转成可审查的草稿、分镜脚本、分镜图片提示词、外部视频工具生成提示词和连续性审查。你不声称生成最终 MP4。',
  'tool',
  'cine_project',
  1,
  2,
  NOW(),
  NOW()
FROM `ai_models` m
WHERE m.model_code = 'gpt-5.5'
  AND m.is_del = 2
  AND m.status = 1
  AND NOT EXISTS (
    SELECT 1 FROM `ai_agents` a
    WHERE a.scene = 'cine_project'
      AND a.is_del = 2
  )
ORDER BY m.id DESC
LIMIT 1;

INSERT INTO `ai_agents` (`name`, `model_id`, `avatar`, `system_prompt`, `mode`, `scene`, `status`, `is_del`, `created_at`, `updated_at`)
SELECT
  'AI短剧分镜图片生成',
  m.id,
  NULL,
  '你是 AI短剧工厂的分镜图片生成智能体。你只负责把分镜 image_prompt 生成静态分镜图片，不生成视频、不加字幕、不加水印，并优先保持人物、服装、道具和场景连续性。',
  'tool',
  'cine_keyframe',
  1,
  2,
  NOW(),
  NOW()
FROM `ai_models` m
WHERE m.model_code = 'gpt-image-2'
  AND m.is_del = 2
  AND m.status = 1
  AND NOT EXISTS (
    SELECT 1 FROM `ai_agents` a
    WHERE a.scene = 'cine_keyframe'
      AND a.is_del = 2
  )
ORDER BY m.id DESC
LIMIT 1;

INSERT INTO `ai_tools` (
  `name`, `code`, `description`, `schema_json`, `executor_type`, `executor_config`, `status`, `is_del`, `created_at`, `updated_at`
)
VALUES (
  '短剧分镜图片生成',
  'cine_generate_keyframe',
  '把短剧分镜 image_prompt 交给 cine_keyframe/gpt-image-2 生成静态分镜图片并返回 file_url；不生成最终视频。',
  JSON_OBJECT(
    'properties', JSON_OBJECT(
      'shot_id', JSON_OBJECT('type', 'string', 'required', false, 'description', '镜头编号，如 S01'),
      'image_prompt', JSON_OBJECT('type', 'string', 'required', true, 'description', '静态分镜图片提示词'),
      'aspect_ratio', JSON_OBJECT('type', 'string', 'required', false, 'description', '画幅，如 9:16'),
      'style', JSON_OBJECT('type', 'string', 'required', false, 'description', '视觉风格'),
      'continuity_anchor', JSON_OBJECT('type', 'string', 'required', false, 'description', '人物/服装/道具/光影连续性锚点'),
      'reference_images', JSON_OBJECT('type', 'array', 'items', JSON_OBJECT('type', 'string'), 'required', false, 'description', '参考图 URL 列表'),
      'dry_run', JSON_OBJECT('type', 'boolean', 'required', false, 'description', '仅测试请求包，不调用图片模型')
    )
  ),
  1,
  JSON_ARRAY(),
  1,
  2,
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `schema_json` = VALUES(`schema_json`),
  `executor_type` = VALUES(`executor_type`),
  `executor_config` = VALUES(`executor_config`),
  `status` = 1,
  `is_del` = 2,
  `updated_at` = NOW();

UPDATE `ai_agents`
SET `mode` = 'tool',
    `updated_at` = NOW()
WHERE `scene` IN ('cine_project', 'cine_keyframe')
  AND `is_del` = 2;

UPDATE `ai_agents`
SET `name` = 'AI短剧导演',
    `system_prompt` = '你是 AI短剧工厂的导演智能体。你负责把素材转成可审查的草稿、分镜脚本、分镜图片提示词、外部视频工具生成提示词和连续性审查。你不声称生成最终 MP4。',
    `updated_at` = NOW()
WHERE `scene` = 'cine_project'
  AND `is_del` = 2;

UPDATE `ai_agents`
SET `name` = 'AI短剧分镜图片生成',
    `system_prompt` = '你是 AI短剧工厂的分镜图片生成智能体。你只负责把分镜 image_prompt 生成静态分镜图片，不生成视频、不加字幕、不加水印，并优先保持人物、服装、道具和场景连续性。',
    `updated_at` = NOW()
WHERE `scene` = 'cine_keyframe'
  AND `is_del` = 2;

INSERT INTO `ai_assistant_tools` (`assistant_id`, `tool_id`, `config_json`, `status`, `is_del`, `created_at`, `updated_at`)
SELECT a.id, t.id, NULL, 1, 2, NOW(), NOW()
FROM `ai_agents` a
JOIN `ai_tools` t ON t.code = 'cine_generate_keyframe' AND t.is_del = 2
WHERE a.scene IN ('cine_project', 'cine_keyframe')
  AND a.is_del = 2
ON DUPLICATE KEY UPDATE
  `status` = 1,
  `is_del` = 2,
  `updated_at` = NOW();
