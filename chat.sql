-- ============================================================
-- 聊天室功能 - 建表 SQL
-- ============================================================

-- 聊天会话表
CREATE TABLE `chat_conversations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '会话类型: 1=私聊, 2=群聊',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '群聊名称（私聊为空）',
  `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '群聊头像',
  `announcement` text COMMENT '群公告',
  `owner_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '群主用户ID（私聊为0）',
  `last_message_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后一条消息ID',
  `last_message_at` datetime NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT '最后消息时间',
  `last_message_preview` varchar(200) NOT NULL DEFAULT '' COMMENT '最后消息摘要',
  `member_count` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '成员数量',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '软删除: 1=是, 2=否',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_last_message_at` (`last_message_at`),
  KEY `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天会话表';

-- 会话参与者表
CREATE TABLE `chat_participants` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int UNSIGNED NOT NULL COMMENT '会话ID',
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `role` tinyint UNSIGNED NOT NULL DEFAULT 3 COMMENT '角色: 1=群主, 2=管理员, 3=成员',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=正常, 2=已退出, 3=被移除',
  `last_read_message_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '最后已读消息ID',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '用户删除会话: 1=是, 2=否',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conversation_user` (`conversation_id`, `user_id`),
  KEY `idx_user_status` (`user_id`, `is_del`, `status`),
  KEY `idx_conversation_status` (`conversation_id`, `is_del`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会话参与者表';

-- 聊天消息表
CREATE TABLE `chat_messages` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int UNSIGNED NOT NULL COMMENT '会话ID',
  `sender_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '发送者ID（系统消息为0）',
  `type` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '消息类型: 1=文本, 2=图片, 3=文件, 4=系统',
  `content` text NOT NULL COMMENT '消息内容',
  `meta_json` json DEFAULT NULL COMMENT '附加信息（图片尺寸、文件名/大小等）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天消息表';

-- 联系人表
CREATE TABLE `chat_contacts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT '用户ID',
  `contact_user_id` int UNSIGNED NOT NULL COMMENT '联系人用户ID',
  `is_initiator` tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为发起方: 1=是(我加的别人), 0=否(别人加的我)',
  `status` tinyint UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 1=待确认, 2=已确认',
  `is_del` tinyint UNSIGNED NOT NULL DEFAULT 2 COMMENT '软删除: 1=是, 2=否',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_contact` (`user_id`, `contact_user_id`),
  KEY `idx_user_status` (`user_id`, `status`, `is_del`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='联系人表';
