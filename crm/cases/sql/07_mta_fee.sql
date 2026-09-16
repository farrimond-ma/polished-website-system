-- Store the (editable) policy fee charged on a mid-term adjustment.
ALTER TABLE `policy_term` ADD COLUMN `policy_fee` DECIMAL(10,2) NULL;
