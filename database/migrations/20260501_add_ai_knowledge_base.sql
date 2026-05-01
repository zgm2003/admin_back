CREATE TABLE IF NOT EXISTS `ai_knowledge_bases` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(80) NOT NULL COMMENT '知识库名称',
  `description` varchar(500) DEFAULT NULL COMMENT '描述',
  `owner_user_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建用户ID，第一版权限预留',
  `visibility` varchar(20) NOT NULL DEFAULT 'private' COMMENT 'private/team/public，第一版权限预留',
  `permission_json` json DEFAULT NULL COMMENT '权限配置预留',
  `chunk_size` int UNSIGNED NOT NULL DEFAULT 800 COMMENT '切片长度',
  `chunk_overlap` int UNSIGNED NOT NULL DEFAULT 120 COMMENT '切片重叠',
  `top_k` int UNSIGNED NOT NULL DEFAULT 5 COMMENT '默认召回数量',
  `score_threshold` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '关键词分数阈值',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  KEY `idx_ai_knowledge_bases_status_id` (`status`, `is_del`, `id`),
  KEY `idx_ai_knowledge_bases_owner_id` (`owner_user_id`, `id`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI知识库';

CREATE TABLE IF NOT EXISTS `ai_knowledge_documents` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `knowledge_base_id` int UNSIGNED NOT NULL COMMENT 'ai_knowledge_bases.id',
  `title` varchar(120) NOT NULL COMMENT '文档标题',
  `source_type` varchar(20) NOT NULL DEFAULT 'manual' COMMENT 'manual/text/file/url',
  `content` longtext NOT NULL COMMENT '文档原文',
  `chunk_count` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '切片数量',
  `index_status` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '1已索引 2索引失败',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  KEY `idx_ai_knowledge_documents_kb_id` (`knowledge_base_id`, `id`),
  KEY `idx_ai_knowledge_documents_kb_status_id` (`knowledge_base_id`, `status`, `is_del`, `id`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI知识库文档';

CREATE TABLE IF NOT EXISTS `ai_knowledge_chunks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `knowledge_base_id` int UNSIGNED NOT NULL COMMENT 'ai_knowledge_bases.id',
  `document_id` int UNSIGNED NOT NULL COMMENT 'ai_knowledge_documents.id',
  `chunk_no` int UNSIGNED NOT NULL COMMENT '文档内切片序号，从1开始',
  `content` longtext NOT NULL COMMENT '切片内容',
  `token_estimate` int UNSIGNED NOT NULL DEFAULT 1 COMMENT '粗略 token 估算',
  `metadata_json` json DEFAULT NULL COMMENT '切片元数据',
  `embedding_model` varchar(120) DEFAULT NULL COMMENT '预留：embedding 模型',
  `embedding_dim` int UNSIGNED DEFAULT NULL COMMENT '预留：向量维度',
  `embedding_json` json DEFAULT NULL COMMENT '预留：小规模向量存储',
  `vector_store` varchar(60) DEFAULT NULL COMMENT '预留：外部向量库',
  `vector_point_id` varchar(120) DEFAULT NULL COMMENT '预留：外部向量点ID',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  KEY `idx_ai_knowledge_chunks_kb_status_id` (`knowledge_base_id`, `status`, `is_del`, `id`),
  KEY `idx_ai_knowledge_chunks_doc_no` (`document_id`, `chunk_no`),
  FULLTEXT KEY `ft_ai_knowledge_chunks_content` (`content`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI知识库切片';

CREATE TABLE IF NOT EXISTS `ai_agent_knowledge_bases` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `agent_id` int UNSIGNED NOT NULL COMMENT 'ai_agents.id',
  `knowledge_base_id` int UNSIGNED NOT NULL COMMENT 'ai_knowledge_bases.id',
  `config_json` json DEFAULT NULL COMMENT '绑定级配置',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 2禁用',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT 'soft delete: 1 deleted, 2 normal',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created time',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ai_agent_knowledge_bases_agent_kb` (`agent_id`, `knowledge_base_id`),
  KEY `idx_ai_agent_knowledge_bases_kb_status` (`knowledge_base_id`, `status`, `is_del`),
  KEY `idx_ai_agent_knowledge_bases_agent_status` (`agent_id`, `status`, `is_del`)
) ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_0900_ai_ci
COMMENT = 'AI智能体知识库绑定';

UPDATE `permissions`
SET `sort` = `sort` + 1,
    `updated_at` = NOW()
WHERE `platform` = 'admin'
  AND `parent_id` = (SELECT p.`id` FROM (SELECT `id` FROM `permissions` WHERE `platform` = 'admin' AND `i18n_key` = 'menu.ai' AND `is_del` = 2 LIMIT 1) p)
  AND `is_del` = 2
  AND `sort` >= 3
  AND `i18n_key` <> 'menu.ai_knowledge'
  AND NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `permissions` WHERE `platform` = 'admin' AND `path` = '/ai/knowledge' AND `is_del` = 2 LIMIT 1) existing_menu
  );

INSERT INTO `permissions` (
  `name`, `path`, `icon`, `parent_id`, `component`, `platform`, `type`, `sort`, `code`, `i18n_key`, `show_menu`, `status`, `is_del`, `created_at`, `updated_at`
)
SELECT
  '知识库', '/ai/knowledge', 'Collection', p.id, 'ai/knowledge', 'admin', 2, 3, NULL, 'menu.ai_knowledge', 1, 1, 2, NOW(), NOW()
FROM `permissions` p
WHERE p.i18n_key = 'menu.ai'
  AND p.platform = 'admin'
  AND p.is_del = 2
  AND NOT EXISTS (
    SELECT 1 FROM `permissions` existing
    WHERE existing.platform = 'admin'
      AND existing.path = '/ai/knowledge'
      AND existing.is_del = 2
  );

UPDATE `permissions`
SET `name` = '知识库',
    `path` = '/ai/knowledge',
    `component` = 'ai/knowledge',
    `i18n_key` = 'menu.ai_knowledge',
    `show_menu` = 1,
    `status` = 1,
    `is_del` = 2,
    `sort` = 3,
    `updated_at` = NOW()
WHERE `platform` = 'admin'
  AND (`path` = '/ai/knowledge' OR `i18n_key` = 'menu.ai_knowledge');

INSERT INTO `permissions` (
  `name`, `path`, `icon`, `parent_id`, `component`, `platform`, `type`, `sort`, `code`, `i18n_key`, `show_menu`, `status`, `is_del`, `created_at`, `updated_at`
)
SELECT item.`name`, '', '', menu.`id`, NULL, 'admin', 3, item.`sort`, item.`code`, '', 1, 1, 2, NOW(), NOW()
FROM (
  SELECT '知识库新增' AS `name`, 'ai_knowledge_add' AS `code`, 1 AS `sort`
  UNION ALL SELECT '知识库编辑', 'ai_knowledge_edit', 2
  UNION ALL SELECT '知识库删除', 'ai_knowledge_del', 3
  UNION ALL SELECT '知识库状态', 'ai_knowledge_status', 4
  UNION ALL SELECT '知识库文档新增', 'ai_knowledge_document_add', 5
  UNION ALL SELECT '知识库文档编辑', 'ai_knowledge_document_edit', 6
  UNION ALL SELECT '知识库文档删除', 'ai_knowledge_document_del', 7
  UNION ALL SELECT '知识库重建索引', 'ai_knowledge_reindex', 8
  UNION ALL SELECT '知识库召回测试', 'ai_knowledge_retrieval_test', 9
) item
JOIN `permissions` menu
  ON menu.platform = 'admin'
 AND menu.path = '/ai/knowledge'
 AND menu.is_del = 2
WHERE NOT EXISTS (
  SELECT 1 FROM `permissions` existing
  WHERE existing.platform = 'admin'
    AND existing.code = item.`code`
    AND existing.is_del = 2
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `is_del`, `created_at`, `updated_at`)
SELECT 2, p.id, 2, NOW(), NOW()
FROM `permissions` p
WHERE p.platform = 'admin'
  AND (p.path = '/ai/knowledge' OR p.code LIKE 'ai_knowledge%')
  AND p.is_del = 2
  AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.role_id = 2
      AND rp.permission_id = p.id
      AND rp.is_del = 2
  );
