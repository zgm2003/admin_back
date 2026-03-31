ALTER TABLE `order_items`
  DROP COLUMN `item_id`,
  DROP COLUMN `image`,
  DROP COLUMN `snapshot`;

ALTER TABLE `orders`
  DROP COLUMN `remark`;

ALTER TABLE `pay_transactions`
  DROP INDEX `uk_client_request_no`,
  DROP COLUMN `client_request_no`;
