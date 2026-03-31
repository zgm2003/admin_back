ALTER TABLE `pay_reconcile_tasks`
  CHANGE COLUMN `platform_file` `platform_file_url` varchar(512) NOT NULL DEFAULT '' COMMENT '平台账单文件URL',
  CHANGE COLUMN `local_file` `local_file_url` varchar(512) NOT NULL DEFAULT '' COMMENT '本地账单文件URL',
  CHANGE COLUMN `diff_file` `diff_file_url` varchar(512) NOT NULL DEFAULT '' COMMENT '差异文件URL';
