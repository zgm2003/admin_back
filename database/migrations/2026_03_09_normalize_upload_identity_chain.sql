-- Normalize upload configuration identity chain from BIGINT UNSIGNED to INT UNSIGNED.
-- Reason: upload_driver / upload_rule / upload_setting are low-cardinality configuration tables,
-- not append-heavy history tables. The application already validates and uses these ids as normal ints.

ALTER TABLE `upload_driver`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `upload_rule`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `upload_setting`
  MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN `driver_id` INT UNSIGNED NOT NULL,
  MODIFY COLUMN `rule_id` INT UNSIGNED NOT NULL;
