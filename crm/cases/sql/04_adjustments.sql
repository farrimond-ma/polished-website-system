-- Mid-term adjustments: store the quote inputs on each term so an MTA can pre-fill and recompute.
ALTER TABLE `policy_term` ADD COLUMN `quote_inputs` TEXT NULL;
