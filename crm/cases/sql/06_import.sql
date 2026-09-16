-- Tag records that came from a bordereau import (so they can be shown as provisional
-- and bulk-managed until the full SchemeServe data dump replaces them).
ALTER TABLE `case_policy` ADD COLUMN `source` VARCHAR(30) NULL;
