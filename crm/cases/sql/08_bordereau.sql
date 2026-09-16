-- Store the per-part premium breakdown on each term, for the insurer bordereau report.
ALTER TABLE `policy_term` ADD COLUMN `premium_breakdown` TEXT NULL;
