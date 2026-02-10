-- 认证平台管理表
CREATE TABLE `auth_platforms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT '平台标识（如 admin, app）',
  `name` varchar(100) NOT NULL COMMENT '平台名称',
  `login_types` json NOT NULL COMMENT '允许的登录方式 ["password","email","phone"]',
  `access_ttl` int unsigned NOT NULL DEFAULT 14400 COMMENT 'access_token 有效期（秒）',
  `refresh_ttl` int unsigned NOT NULL DEFAULT 1209600 COMMENT 'refresh_token 有效期（秒）',
  `bind_platform` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '绑定平台 1=是 2=否',
  `bind_device` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '绑定设备 1=是 2=否',
  `bind_ip` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '绑定IP 1=是 2=否',
  `single_session` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '单端登录 1=是 2=否',
  `max_sessions` int unsigned NOT NULL DEFAULT 5 COMMENT '最大会话数（0=不限）',
  `allow_register` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '允许注册 1=是 2=否',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态 1=启用 2=禁用',
  `is_del` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '软删除 1=已删 2=正常',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_del` (`status`, `is_del`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='认证平台管理';

-- 初始数据（迁移现有配置）
INSERT INTO `auth_platforms` (`code`, `name`, `login_types`, `access_ttl`, `refresh_ttl`, `bind_platform`, `bind_device`, `bind_ip`, `single_session`, `max_sessions`, `allow_register`, `status`) VALUES
('admin', 'PC后台', '["password","email"]', 14400, 1209600, 1, 2, 2, 1, 1, 2, 1),
('app', 'H5/APP', '["password","email","phone"]', 28800, 2592000, 1, 2, 2, 2, 5, 1, 1);
