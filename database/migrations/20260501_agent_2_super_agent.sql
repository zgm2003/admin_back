ALTER TABLE `ai_agents`
  ADD COLUMN `capabilities_json` json NULL COMMENT 'Agent能力配置：chat/tools/rag/workflow' AFTER `scene`,
  ADD COLUMN `runtime_config_json` json NULL COMMENT '运行参数：timeout/retry/max_tokens/reasoning等' AFTER `capabilities_json`,
  ADD COLUMN `policy_json` json NULL COMMENT '权限策略：工具调用上限、失败策略、审批等' AFTER `runtime_config_json`;

CREATE TABLE IF NOT EXISTS `ai_agent_scenes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `agent_id` int UNSIGNED NOT NULL COMMENT 'ai_agents.id',
  `scene_code` varchar(60) NOT NULL COMMENT '场景编码，如 goods_script/cine_project/cine_keyframe',
  `prompt_overlay` text NULL COMMENT '该场景追加提示词',
  `config_json` json NULL COMMENT '该场景运行配置覆盖',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '2正常 1删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_agent_scene` (`agent_id`, `scene_code`),
  KEY `idx_scene_status_del` (`scene_code`, `status`, `is_del`),
  KEY `idx_agent_del` (`agent_id`, `is_del`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI智能体多场景绑定';

INSERT INTO `ai_agent_scenes` (`agent_id`, `scene_code`, `status`, `is_del`, `created_at`, `updated_at`)
SELECT a.`id`, a.`scene`, 1, 2, NOW(), NOW()
FROM `ai_agents` a
WHERE a.`scene` IS NOT NULL
  AND a.`scene` <> ''
  AND a.`is_del` = 2
  AND NOT EXISTS (
    SELECT 1 FROM `ai_agent_scenes` s
    WHERE s.`agent_id` = a.`id`
      AND s.`scene_code` = a.`scene`
  );

UPDATE `ai_agents`
SET `capabilities_json` = JSON_OBJECT(
  'chat', true,
  'tools', IF(`mode` = 'tool', true, false),
  'rag', IF(`mode` = 'rag', true, false),
  'workflow', IF(`mode` = 'workflow', true, false)
)
WHERE `capabilities_json` IS NULL;
