-- Document add-ons: policy-number config, per-case insurer policy number, saved-document path.
-- Run after the earlier SQL files. Each ALTER is separate (SQLite-compatible).

ALTER TABLE `scheme` ADD COLUMN `policy_no_prefix` VARCHAR(20) NULL;
ALTER TABLE `scheme` ADD COLUMN `policy_no_suffix` VARCHAR(20) NULL;
UPDATE `scheme` SET `policy_no_prefix`='ZCLP', `policy_no_suffix`='1' WHERE `scheme_id`=1;

ALTER TABLE `case_policy` ADD COLUMN `insurer_policy_number` VARCHAR(40) NULL;

ALTER TABLE `document` ADD COLUMN `file_path` VARCHAR(255) NULL;
