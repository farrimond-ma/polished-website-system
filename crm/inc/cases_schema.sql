-- GENERATED FILE — do not edit by hand.
-- Built from SchemeServe V2/webapp SQL by scratchpad/build-cases-sql.cjs; see crm/cases/README.md.

-- =====================================================================
--  POLISHED INSURANCE / ALLIED CLEANING SCHEME  -- SchemeServe backend replica
--  Target: MySQL 5.7+ / MariaDB 10.3+  (SiteGround-compatible)
--  Import:  Site Tools -> MySQL -> create DB + user -> phpMyAdmin -> Import this file
--  Charset: utf8mb4    Engine: InnoDB
--  Built from Underwriting Manual v1.6 + live backend (cases 9568516, 9676245).
--  Money/rate figures for the two sample cases are as observed on 05 Aug 2026.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- drop (safe re-import) ----------






















-- =====================================================================
--  REFERENCE / CONFIG TABLES
-- =====================================================================
CREATE TABLE IF NOT EXISTS `insurer` (
  `insurer_id`  INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  PRIMARY KEY (`insurer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `scheme` (
  `scheme_id`     INT NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `product_name`  VARCHAR(120) NOT NULL,
  `insurer_id`    INT NOT NULL,
  `mga`           VARCHAR(120) NULL,
  `broker`        VARCHAR(120) NULL,
  `wording_ref`   VARCHAR(40)  NULL,
  `agreement_ref` VARCHAR(60)  NULL,
  PRIMARY KEY (`scheme_id`),
  CONSTRAINT `fk_scheme_insurer` FOREIGN KEY (`insurer_id`) REFERENCES `insurer`(`insurer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- app_user: the CRM already provides this table (logins live there)
CREATE TABLE IF NOT EXISTS `agent` (
  `agent_id` INT NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(120) NOT NULL,
  PRIMARY KEY (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `import_batch` (
  `import_batch_id` INT NOT NULL,
  `imported_at`     DATETIME NULL,
  `description`     VARCHAR(160) NULL,
  PRIMARY KEY (`import_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cover_part_ref` (
  `part_code` VARCHAR(4) NOT NULL,          -- A,B,C,D,J
  `part_name` VARCHAR(80) NOT NULL,
  `basis`     VARCHAR(40) NULL,             -- wageroll / turnover / flat
  `operative` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `question_ref` (
  `question_id`   INT NOT NULL AUTO_INCREMENT,
  `part_name`     VARCHAR(60) NOT NULL,     -- backend Part / section
  `question_key`  VARCHAR(60) NULL,
  `question_text` VARCHAR(400) NOT NULL,
  `answer_type`   VARCHAR(30) NULL,         -- radio/dropdown/text/currency/date/yesno
  `options`       VARCHAR(400) NULL,
  `backend_only`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `endorsement` (
  `endorsement_code` VARCHAR(30) NOT NULL,
  `title`            VARCHAR(160) NOT NULL,
  `body`             TEXT NULL,
  PRIMARY KEY (`endorsement_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Flexible rate table: percent rates, flat premiums and grid cells all live here.
CREATE TABLE IF NOT EXISTS `rate_table` (
  `rate_id`       INT NOT NULL AUTO_INCREMENT,
  `scheme_id`     INT NOT NULL,
  `part_code`     VARCHAR(4) NOT NULL,
  `pl_limit`      DECIMAL(12,2) NULL,        -- PL limit band where relevant
  `band_label`    VARCHAR(80) NOT NULL,      -- e.g. 'Turnover up to 100m'
  `exposure_basis`VARCHAR(20) NULL,          -- turnover / wageroll / flat
  `turnover_band` DECIMAL(12,2) NULL,        -- upper bound for PI grid
  `rate_pct`      DECIMAL(8,5) NULL,         -- percentage rate
  `flat_amount`   DECIMAL(10,2) NULL,        -- flat premium / min premium
  `source`        VARCHAR(60) NOT NULL,      -- Manual v1.6 / Live 9676245 / TBC
  `note`          VARCHAR(200) NULL,
  PRIMARY KEY (`rate_id`),
  KEY `ix_rate_lookup` (`scheme_id`,`part_code`,`pl_limit`),
  CONSTRAINT `fk_rate_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `scheme`(`scheme_id`),
  CONSTRAINT `fk_rate_part`   FOREIGN KEY (`part_code`) REFERENCES `cover_part_ref`(`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  CORE POLICY-ADMIN TABLES
-- =====================================================================
CREATE TABLE IF NOT EXISTS `client` (
  `client_ref`     VARCHAR(12) NOT NULL,      -- e.g. LIL1, MRG5
  `title`          VARCHAR(20) NULL,
  `first_name`     VARCHAR(60) NULL,
  `last_name`      VARCHAR(60) NULL,
  `joint_names`    VARCHAR(160) NULL,
  `trading_name`   VARCHAR(160) NULL,
  `addr1`          VARCHAR(120) NULL,
  `addr2`          VARCHAR(120) NULL,
  `town`           VARCHAR(80) NULL,
  `county`         VARCHAR(80) NULL,
  `postcode`       VARCHAR(12) NULL,
  `phone_landline` VARCHAR(30) NULL,
  `phone_mobile`   VARCHAR(30) NULL,
  `email`          VARCHAR(160) NULL,
  `domicile`       VARCHAR(40) NULL DEFAULT 'United Kingdom',
  PRIMARY KEY (`client_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_policy` (
  `case_id`         BIGINT NOT NULL,
  `scheme_id`       INT NOT NULL,
  `client_ref`      VARCHAR(12) NOT NULL,
  `agent_id`        INT NULL,
  `created_at`      DATETIME NULL,
  `status`          VARCHAR(20) NOT NULL,     -- Draft / On Cover / Cancelled / Lapsed
  `import_batch_id` INT NULL,
  PRIMARY KEY (`case_id`),
  KEY `ix_case_client` (`client_ref`),
  CONSTRAINT `fk_case_scheme` FOREIGN KEY (`scheme_id`)  REFERENCES `scheme`(`scheme_id`),
  CONSTRAINT `fk_case_client` FOREIGN KEY (`client_ref`) REFERENCES `client`(`client_ref`),
  CONSTRAINT `fk_case_agent`  FOREIGN KEY (`agent_id`)   REFERENCES `agent`(`agent_id`),
  CONSTRAINT `fk_case_batch`  FOREIGN KEY (`import_batch_id`) REFERENCES `import_batch`(`import_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `policy_term` (
  `term_id`          BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`          BIGINT NOT NULL,
  `endorsement_ref`  VARCHAR(40) NULL,        -- SchemeServe eId
  `transaction_type` VARCHAR(20) NOT NULL,    -- New Business/Renewal/Adjustment/Cancellation
  `sequence_label`   VARCHAR(30) NULL,        -- 'New Business','1st','2nd','adj: 31 Jul 2026'
  `status`           VARCHAR(20) NOT NULL,
  `inception_date`   DATE NULL,
  `expiry_date`      DATE NULL,
  `lta_date`         DATE NULL,
  `effective_date`   DATE NULL,
  `total_premium`    DECIMAL(10,2) NULL,
  `adjustment`       DECIMAL(10,2) NULL,
  `balance`          DECIMAL(10,2) NULL,      -- +overpaid / -owing
  PRIMARY KEY (`term_id`),
  KEY `ix_term_case` (`case_id`),
  CONSTRAINT `fk_term_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `risk_answer` (
  `answer_id`     BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`       BIGINT NOT NULL,
  `part_name`     VARCHAR(60) NOT NULL,
  `question_key`  VARCHAR(60) NULL,
  `question_text` VARCHAR(400) NULL,
  `answer_value`  VARCHAR(400) NULL,
  PRIMARY KEY (`answer_id`),
  KEY `ix_answer_term` (`term_id`),
  CONSTRAINT `fk_answer_term` FOREIGN KEY (`term_id`) REFERENCES `policy_term`(`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cover_selection` (
  `id`                 BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`            BIGINT NOT NULL,
  `part_code`          VARCHAR(4) NOT NULL,
  `included`           TINYINT(1) NOT NULL DEFAULT 0,
  `limit_of_indemnity` DECIMAL(14,2) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cover_term` (`term_id`),
  CONSTRAINT `fk_cover_term` FOREIGN KEY (`term_id`)   REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_cover_part` FOREIGN KEY (`part_code`) REFERENCES `cover_part_ref`(`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rating_line` (
  `id`         BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`    BIGINT NOT NULL,
  `part_code`  VARCHAR(4) NOT NULL,
  `insurer_id` INT NULL,
  `line_label` VARCHAR(80) NOT NULL,
  `exposure`   DECIMAL(14,2) NULL,
  `rate_pct`   DECIMAL(8,5) NULL,
  `premium`    DECIMAL(10,4) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_rl_term` (`term_id`),
  CONSTRAINT `fk_rl_term` FOREIGN KEY (`term_id`)   REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_rl_part` FOREIGN KEY (`part_code`) REFERENCES `cover_part_ref`(`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rating_adjustment` (
  `id`                         BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`                    BIGINT NOT NULL,
  `part_code`                  VARCHAR(4) NOT NULL,
  `basic_total`                DECIMAL(10,2) NULL,
  `minimum_premium`            DECIMAL(10,2) NULL,
  `ni_load_pct`                DECIMAL(6,4) NULL,
  `experience_load_pct`        DECIMAL(6,4) NULL,
  `discretionary_pct`          DECIMAL(6,4) NULL,
  `net_total`                  DECIMAL(10,2) NULL,
  `commission_from_insurer_pct`DECIMAL(6,4) NULL,
  `commission_to_allied_pct`   DECIMAL(6,4) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ra_term` (`term_id`),
  CONSTRAINT `fk_ra_term` FOREIGN KEY (`term_id`)   REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_ra_part` FOREIGN KEY (`part_code`) REFERENCES `cover_part_ref`(`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `applied_endorsement` (
  `id`               BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`          BIGINT NOT NULL,
  `endorsement_code` VARCHAR(30) NOT NULL,
  `applies_to_text`  VARCHAR(400) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ae_term` (`term_id`),
  CONSTRAINT `fk_ae_term` FOREIGN KEY (`term_id`)          REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_ae_code` FOREIGN KEY (`endorsement_code`) REFERENCES `endorsement`(`endorsement_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document` (
  `doc_id`       BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`      BIGINT NOT NULL,
  `doc_type`     VARCHAR(40) NOT NULL,        -- Schedule/Wording/Summary/Statement of Facts/Receipt/EL Certificate
  `name`         VARCHAR(200) NULL,
  `generated_at` DATETIME NULL,
  `generated_by` INT UNSIGNED NULL,
  PRIMARY KEY (`doc_id`),
  KEY `ix_doc_term` (`term_id`),
  CONSTRAINT `fk_doc_term` FOREIGN KEY (`term_id`)      REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`generated_by`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `claim` (
  `claim_id`     BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`      BIGINT NOT NULL,
  `description`  VARCHAR(400) NULL,
  `date_of_loss` DATE NULL,
  `status`       VARCHAR(30) NULL,
  `paid`         DECIMAL(12,2) NULL,
  `outstanding`  DECIMAL(12,2) NULL,
  PRIMARY KEY (`claim_id`),
  KEY `ix_claim_case` (`case_id`),
  CONSTRAINT `fk_claim_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `note` (
  `note_id`    BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`    BIGINT NOT NULL,
  `body`       VARCHAR(1000) NULL,
  `reference`  VARCHAR(80) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`note_id`),
  KEY `ix_note_case` (`case_id`),
  CONSTRAINT `fk_note_case` FOREIGN KEY (`case_id`)    REFERENCES `case_policy`(`case_id`),
  CONSTRAINT `fk_note_user` FOREIGN KEY (`created_by`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `money_transaction` (
  `txn_id`     BIGINT NOT NULL,              -- SchemeServe transaction id
  `case_id`    BIGINT NOT NULL,
  `type`       VARCHAR(40) NOT NULL,         -- Cash Payment / Direct Debit / Card / Refund
  `txn_date`   DATE NULL,
  `amount`     DECIMAL(10,2) NULL,
  `result`     VARCHAR(20) NULL,             -- OK / Pending / Failed
  PRIMARY KEY (`txn_id`),
  KEY `ix_txn_case` (`case_id`),
  CONSTRAINT `fk_txn_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `debiting_schedule` (
  `schedule_id` BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`     BIGINT NOT NULL,
  `description` VARCHAR(160) NULL,
  `frequency`   VARCHAR(30) NULL,
  `next_date`   DATE NULL,
  `amount`      DECIMAL(10,2) NULL,
  `status`      VARCHAR(20) NULL,
  PRIMARY KEY (`schedule_id`),
  KEY `ix_sched_case` (`case_id`),
  CONSTRAINT `fk_sched_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_log` (
  `activity_id` BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`     BIGINT NOT NULL,
  `type`        VARCHAR(30) NOT NULL,        -- FileUpload/Policy/Document/Note/Email
  `details`     VARCHAR(1000) NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`activity_id`),
  KEY `ix_act_case` (`case_id`),
  CONSTRAINT `fk_act_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`),
  CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  SEED : reference data
-- =====================================================================
INSERT IGNORE INTO `insurer` (`insurer_id`,`name`) VALUES (1,'Zurich Insurance');

INSERT IGNORE INTO `scheme` (`scheme_id`,`name`,`product_name`,`insurer_id`,`mga`,`broker`,`wording_ref`,`agreement_ref`)
VALUES (1,'Polished Cleaners Scheme','Polished Insurance',1,'Camberford Underwriting','Allied Insurance Services','ZCX631AA','B1053CU/ZUR-SDA-Allied03/24');

INSERT IGNORE INTO `agent` (`agent_id`,`name`) VALUES (1,'Polished Cleaners Scheme Agent');

INSERT IGNORE INTO `import_batch` (`import_batch_id`,`imported_at`,`description`) VALUES
 (24733,NULL,'Legacy import batch'),
 (24915,NULL,'Legacy import batch');

INSERT IGNORE INTO `cover_part_ref` (`part_code`,`part_name`,`basis`,`operative`) VALUES
 ('A','Employers'' Liability','wageroll',1),
 ('B','Public & Products Liability','turnover',1),
 ('C','Directors'' & Officers'' Liability','flat',1),
 ('D','Professional Indemnity','flat',1),
 ('J','Contract Works Construction','flat',1),
 ('E','Material Damage (All Risks)','n/a',0);

-- ---- Question set (Parts + backend-only sections) ----
INSERT IGNORE INTO `question_ref` (`part_name`,`question_key`,`question_text`,`answer_type`,`options`,`backend_only`) VALUES
 ('INTERNAL ADMIN','pay_method','How does the client pay the premium?','dropdown',NULL,1),
 ('INTERNAL ADMIN','pay_account_name','Account Name','text',NULL,1),
 ('HIDDEN Questions',NULL,'Backend-only hidden questions','text',NULL,1),
 ('Cleaning Insurance Quote','who_works','Who works in your business?','radio','Just me|Me and others',0),
 ('Cleaning Insurance Quote','years_exp','How many years experience do you have working in the cleaning industry?','dropdown','Under 1 year|1 to 2 years|2 to 3 years|Over 3 years',0),
 ('Cleaning Insurance Quote','business_type','What type of business do you operate?','dropdown','Sole Proprietor|Limited company|Legal Partnership|Community Interest Company',0),
 ('Cleaning Insurance Quote','shares_50','Do you own more than 50% of the shares in the Ltd company?','yesno','Yes|No',0),
 ('Cleaning Insurance Quote','another_director','So there is another Director of the business?','yesno','Yes|No',0),
 ('Cleaning Insurance Quote','el_ok','In that case you will legally need to have Employers Liability Insurance. Is that OK?','yesno','Yes|No',0),
 ('Cleaning Insurance Quote','pl_only','So you wish to proceed with just Public Liability Insurance?','yesno','Yes|No',0),
 ('Cleaning Insurance Quote','who_else','Who else carries out work for the business?','dropdown','Employed Cleaners|Self Employed Cleaners|A mixture|Business partner(s)|Fellow Directors',0),
 ('Public Liability Insurance','pl_limit','What limit do you require for Public Liability?','dropdown','1000000|2000000|5000000',0),
 ('Public Liability Insurance','cover_start','And when do you need cover to start from?','date',NULL,0),
 ('Employers Liability Insurance','turnover','What is your estimated annual turnover?','currency',NULL,0),
 ('Employers Liability Insurance','wr_employed','Estimated annual Wageroll for your employed cleaners','currency',NULL,0),
 ('Employers Liability Insurance','wr_selfemp','Estimated annual payments to self-employed cleaners','currency',NULL,0),
 ('Employers Liability Insurance','bfsc','Do you make any payments to Bona Fide Sub Contractors?','yesno','Yes|No',0),
 ('Your Details','title','What is your name? (Title)','dropdown','Mr|Mrs|Miss|Ms|Dr|Prof.|Capt.|Lord|Lady|Major|Rev.|Master|Exec(s) of|Mx',0),
 ('Working At Height','max_height','What is the maximum height (in metres) you work at?','currency',NULL,0),
 ('Optional Covers','owned_plant','Level of cover for owned plant, tools and equipment','dropdown','1000|6000|10000|15000|20000|25000|30000|35000|50000',0),
 ('Optional Covers','hired_plant','Level of cover for hired-in plant','dropdown','50000|100000',0),
 ('Claims','claims_5yr','Any loss/damage/claim in the last 5 years?','yesno','Yes|No',0),
 ('Declaration','declaration_agree','Can you agree to the statements above?','yesno','Yes|No',0);

-- ---- Endorsement catalogue ----
INSERT IGNORE INTO `endorsement` (`endorsement_code`,`title`) VALUES
 ('ZCL001-A','Treatment Exclusion'),
 ('BSPKZCL-001','Treatment Exclusion Clarifying Clause'),
 ('ZCL005-A','Professional Advice Exclusion'),
 ('BSPKZCL-007','Professional Advice Clarifying Clause'),
 ('ZCL007-A','Efficacy and Contractual Liability Exclusion'),
 ('BSPKZCL-011','Efficacy and Contractual Liability Clarifying Clause'),
 ('ZCL007-H','Failure To Perform Exclusion - Paint Dyes Pigments and Surface Coatings Products'),
 ('ZCL007-I','Failure To Perform Exclusion - Fertilizers Herbicides Pesticides and Insecticides'),
 ('ZCL008-A','Environmental Clean Up Costs Exclusion'),
 ('BSPKZCL-012','Environmental Clean Up Costs Clarifying Clause'),
 ('ZCL009-D','Fidelity Guarantee Extension - Limit 50,000 (Cleaning Wording)'),
 ('ZCL010-A','Financial Loss Exclusion'),
 ('BSPKZCL-014','Financial Loss Clarifying Clause'),
 ('MISC/ZCL-020','Pest Control Condition'),
 ('MISC/ZCL-013','Firearms Exclusion');

-- ---- Rate tables ----
-- Part A Employers' Liability (wageroll %), incl. 15m/100m height bands (live)
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
 (1,'A',NULL,'Internal Wage Roll','wageroll',0.55000,NULL,'Live 9676245',NULL),
 (1,'A',NULL,'Ground Wage Roll','wageroll',0.93500,NULL,'Live 9676245','Outside, not at height'),
 (1,'A',NULL,'Up to 15m Wage Roll','wageroll',3.00000,NULL,'Live 9676245','Height band <=15m'),
 (1,'A',NULL,'Up to 100m Wage Roll','wageroll',3.00000,NULL,'Live 9676245','Height band <=100m'),
 (1,'A',NULL,'EL Minimum Premium','flat',NULL,100.00,'Manual v1.6',NULL);
-- Part B Public & Products Liability (turnover %) by limit, incl. 15m/100m split
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
 (1,'B',1000000,'Turnover Internal & Ground','turnover',0.08600,NULL,'Manual v1.6',NULL),
 (1,'B',1000000,'Turnover up to 15m','turnover',0.09675,NULL,'Manual v1.6','Height <=15m'),
 (1,'B',1000000,'Turnover up to 100m','turnover',NULL,NULL,'TBC','Confirm 100m band rate on SchemeServe'),
 (1,'B',1000000,'Turnover for BFSC','turnover',0.12900,NULL,'Manual v1.6',NULL),
 (1,'B',1000000,'PL Minimum Premium','flat',NULL,100.00,'Manual v1.6',NULL),
 (1,'B',2000000,'Turnover Internal & Ground','turnover',0.09675,NULL,'Live 9676245',NULL),
 (1,'B',2000000,'Turnover up to 15m','turnover',0.10750,NULL,'Live 9676245','Height <=15m'),
 (1,'B',2000000,'Turnover up to 100m','turnover',0.11825,NULL,'Live 9676245','Height <=100m - NOT in manual'),
 (1,'B',2000000,'Turnover for BFSC','turnover',0.15050,NULL,'Live 9676245',NULL),
 (1,'B',2000000,'PL Minimum Premium','flat',NULL,125.00,'Manual v1.6',NULL),
 (1,'B',5000000,'Turnover Internal & Ground','turnover',0.13975,NULL,'Manual v1.6',NULL),
 (1,'B',5000000,'Turnover up to 15m','turnover',0.15050,NULL,'Manual v1.6','Height <=15m'),
 (1,'B',5000000,'Turnover up to 100m','turnover',NULL,NULL,'TBC','Confirm 100m band rate on SchemeServe'),
 (1,'B',5000000,'Turnover for BFSC','turnover',0.21500,NULL,'Manual v1.6',NULL),
 (1,'B',5000000,'PL Minimum Premium','flat',NULL,200.00,'Manual v1.6',NULL);
-- Part C D&O flat
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`flat_amount`,`source`) VALUES
 (1,'C','D&O Flat Premium (100,000 LOI)','flat',110.00,'Manual v1.6');
-- Part D PI grid (turnover_band upper x limit)
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
 (1,'D',100000,'PI up to 250k turnover','flat',250000,190.00,'Manual v1.6'),
 (1,'D',100000,'PI up to 500k turnover','flat',500000,200.00,'Manual v1.6'),
 (1,'D',100000,'PI up to 750k turnover','flat',750000,220.00,'Manual v1.6'),
 (1,'D',100000,'PI up to 1m turnover','flat',1000000,245.00,'Manual v1.6'),
 (1,'D',100000,'PI up to 1.5m turnover','flat',1500000,290.00,'Manual v1.6'),
 (1,'D',250000,'PI up to 250k turnover','flat',250000,245.00,'Manual v1.6'),
 (1,'D',250000,'PI up to 500k turnover','flat',500000,260.00,'Manual v1.6'),
 (1,'D',250000,'PI up to 750k turnover','flat',750000,290.00,'Manual v1.6'),
 (1,'D',250000,'PI up to 1m turnover','flat',1000000,310.00,'Manual v1.6'),
 (1,'D',250000,'PI up to 1.5m turnover','flat',1500000,330.00,'Manual v1.6');
-- Part J owned + hired plant flat
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
 (1,'J','Owned Plant 1,000','flat',1000,50.00,'Manual v1.6'),
 (1,'J','Owned Plant 6,000','flat',6000,100.00,'Manual v1.6'),
 (1,'J','Owned Plant 10,000','flat',10000,150.00,'Manual v1.6'),
 (1,'J','Owned Plant 15,000','flat',15000,200.00,'Manual v1.6'),
 (1,'J','Owned Plant 20,000','flat',20000,250.00,'Manual v1.6'),
 (1,'J','Owned Plant 25,000','flat',25000,300.00,'Manual v1.6'),
 (1,'J','Owned Plant 30,000','flat',30000,400.00,'Manual v1.6'),
 (1,'J','Owned Plant 35,000','flat',35000,400.00,'Manual v1.6'),
 (1,'J','Owned Plant 50,000','flat',50000,500.00,'Manual v1.6'),
 (1,'J','Hired Plant 5k charges / 50k SI','flat',50000,150.00,'Manual v1.6'),
 (1,'J','Hired Plant 10k charges / 50k SI','flat',50000,300.00,'Manual v1.6'),
 (1,'J','Hired Plant 5k charges / 100k SI','flat',100000,300.00,'Manual v1.6'),
 (1,'J','Hired Plant 10k charges / 100k SI','flat',100000,500.00,'Manual v1.6');
-- Loadings & discounts (percent)
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`rate_pct`,`source`,`note`) VALUES
 (1,'A','NI Load (EL)','percent',50.00000,'Manual v1.6','Also applies to min premium'),
 (1,'B','NI Load (PL)','percent',25.00000,'Manual v1.6','Also applies to min premium'),
 (1,'A','Fidelity Guarantee (ZCL009-D)','percent',0.10000,'Manual v1.6','% of wageroll'),
 (1,'B','Discount No Claims','percent',10.00000,'Manual v1.6',NULL),
 (1,'B','Discount Low Claims','percent',5.00000,'Manual v1.6','Only if No Claims not applied'),
 (1,'B','Discount Established 5+ yrs','percent',5.00000,'Manual v1.6',NULL),
 (1,'A','Discount Height excl ladders','percent',20.00000,'Manual v1.6','EL height rate only'),
 (1,'A','Discount Federation Window Cleaners','percent',10.00000,'Manual v1.6','EL ladder work only'),
 (1,'B','Discount Professional Accreditations','percent',5.00000,'Manual v1.6',NULL),
 (1,'B','Discount H&S Policy','percent',5.00000,'Manual v1.6',NULL),
 (1,'B','Commission From Insurer','percent',25.00000,'Live 9676245',NULL),
 (1,'B','Commission To Allied','percent',0.00000,'Live 9676245',NULL);

-- =====================================================================
--  SEED : sample clients + policies (as observed live)
-- =====================================================================

-- (sample client/case records from the reference copy are not loaded)

CREATE OR REPLACE VIEW `v_policy_summary` AS
SELECT c.`case_id`, c.`status` AS case_status, s.`product_name`, cl.`client_ref`,
       COALESCE(cl.`joint_names`, CONCAT_WS(' ', cl.`title`, cl.`first_name`, cl.`last_name`)) AS client_name,
       cl.`trading_name`, t.`transaction_type`, t.`sequence_label`,
       t.`inception_date`, t.`expiry_date`, t.`total_premium`, t.`balance`
FROM `case_policy` c
JOIN `scheme` s   ON s.`scheme_id` = c.`scheme_id`
JOIN `client` cl  ON cl.`client_ref` = c.`client_ref`
LEFT JOIN `policy_term` t ON t.`case_id` = c.`case_id`;

CREATE OR REPLACE VIEW `v_premium_breakdown` AS
SELECT t.`case_id`, ra.`part_code`, p.`part_name`,
       ra.`basic_total`, ra.`minimum_premium`, ra.`net_total`,
       ra.`commission_from_insurer_pct`
FROM `rating_adjustment` ra
JOIN `policy_term` t   ON t.`term_id` = ra.`term_id`
JOIN `cover_part_ref` p ON p.`part_code` = ra.`part_code`;


-- ===== 01_rates_update.sql =====
-- =====================================================================
--  RATES UPDATE from the live SchemeServe NP config
--  ("Polished Cleaners Scheme NP.csv"). Run AFTER schemeserve_backend_mysql.sql.
--  Corrects PL 100m rates, adds the £10m limit, and adds the risk-group
--  minimum-net premiums, IPT/fee/commission config, and Contents cover.
-- =====================================================================

-- 1) Fix the two 'TBC' PL 100m rates + tag source
UPDATE `rate_table` SET `rate_pct`=0.10750, `source`='Live NP config', `note`='Height <=100m'
 WHERE `part_code`='B' AND `pl_limit`=1000000 AND `band_label`='Turnover up to 100m';
UPDATE `rate_table` SET `rate_pct`=0.18275, `source`='Live NP config', `note`='Height <=100m'
 WHERE `part_code`='B' AND `pl_limit`=5000000 AND `band_label`='Turnover up to 100m';

-- 2) Add the £10,000,000 PL limit band (present in the live config)
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
 (1,'B',10000000,'Turnover Internal & Ground','turnover',0.26875,NULL,'Live NP config',NULL),
 (1,'B',10000000,'Turnover up to 15m','turnover',0.30100,NULL,'Live NP config','Height <=15m'),
 (1,'B',10000000,'Turnover up to 100m','turnover',0.21500,NULL,'Live NP config','Height <=100m'),
 (1,'B',10000000,'Turnover for BFSC','turnover',0.43000,NULL,'Live NP config',NULL);

-- 3) Risk-group minimum NET premiums (replace the manual's flat minimums).
--    risk_group 7083 = Public Liability (Main), 7249 = Employers' Liability.

CREATE TABLE IF NOT EXISTS `min_net_premium` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `risk_group`     INT NOT NULL,
  `part_code`      VARCHAR(4) NOT NULL,
  `who_works`      VARCHAR(20) NULL,      -- 'Just me' / 'Me and others' / NULL=any
  `pl_limit`       DECIMAL(12,2) NULL,
  `over_75k`       VARCHAR(3) NULL,       -- Yes/No/NULL
  `just_partner`   VARCHAR(3) NULL,       -- Yes/NULL
  `height_work`    VARCHAR(3) NULL,       -- Yes/NULL
  `min_net`        DECIMAL(10,2) NOT NULL,
  `source`         VARCHAR(40) NOT NULL DEFAULT 'Live NP config',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `min_net_premium` (`risk_group`,`part_code`,`who_works`,`pl_limit`,`over_75k`,`just_partner`,`height_work`,`min_net`) VALUES
 -- PL (7083)
 (7083,'B','Me and others',1000000,NULL,NULL,NULL,120),
 (7083,'B','Me and others',2000000,NULL,NULL,NULL,140),
 (7083,'B','Me and others',5000000,NULL,NULL,NULL,225),
 (7083,'B','Me and others',10000000,NULL,NULL,NULL,450),
 (7083,'B','Just me',1000000,'No',NULL,NULL,120),
 (7083,'B','Just me',2000000,'No',NULL,NULL,140),
 (7083,'B','Just me',5000000,'No',NULL,NULL,225),
 (7083,'B','Just me',10000000,'No',NULL,NULL,450),
 (7083,'B','Just me',1000000,'Yes',NULL,NULL,150),
 (7083,'B','Just me',2000000,'Yes',NULL,NULL,175),
 (7083,'B','Just me',5000000,'Yes',NULL,NULL,281.25),
 (7083,'B','Just me',10000000,'Yes',NULL,NULL,562.50),
 (7083,'B','Me and others',1000000,NULL,'Yes',NULL,180),  -- legal partnership (partners only)
 (7083,'B','Me and others',2000000,NULL,'Yes',NULL,210),
 (7083,'B','Me and others',5000000,NULL,'Yes',NULL,337.50),
 (7083,'B','Me and others',10000000,NULL,'Yes',NULL,675),
 -- EL (7249)
 (7249,'A','Me and others',NULL,NULL,NULL,'No',132),
 (7249,'A','Me and others',NULL,NULL,NULL,'Yes',250),
 (7249,'A','Me and others',NULL,NULL,'Yes',NULL,0);

-- 4) Scheme financial config (IPT, commission, policy fee, credit interest)

CREATE TABLE IF NOT EXISTS `scheme_config` (
  `ckey`   VARCHAR(40) NOT NULL,
  `cvalue` DECIMAL(10,4) NOT NULL,
  `note`   VARCHAR(120) NULL,
  PRIMARY KEY (`ckey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `scheme_config` (`ckey`,`cvalue`,`note`) VALUES
 ('ipt_pct',12.0,'Insurance Premium Tax %'),
 ('commission_from_insurer_pct',25.0,'All risk groups'),
 ('policy_fee_pct',12.5,'% of (net + IPT)'),
 ('credit_interest_pct',5.96,'Instalment credit charge %');

-- 5) Contents / Material Damage cover options (live; Part E now operative here)

CREATE TABLE IF NOT EXISTS `contents_option` (
  `opt`           VARCHAR(20) NOT NULL,
  `contents_si`   DECIMAL(10,2) NOT NULL,
  `stock_si`      DECIMAL(10,2) NOT NULL,
  `computers_si`  DECIMAL(10,2) NOT NULL,
  `documents_si`  DECIMAL(10,2) NOT NULL,
  `fee`           DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`opt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `contents_option` (`opt`,`contents_si`,`stock_si`,`computers_si`,`documents_si`,`fee`) VALUES
 ('Option 1',10000,0,0,0,150),
 ('Option 2',20000,5000,10000,5000,250),
 ('Option 3',30000,5000,15000,10000,350),
 ('Option 4',50000,5000,25000,10000,450);

-- 6) Owned plant: live config has NO £30,000 tier -> remove for accuracy
DELETE FROM `rate_table` WHERE `part_code`='J' AND `band_label`='Owned Plant 30,000';

-- 7) Hired-in plant: live model is keyed on hiring charges only (SI = charges).
DELETE FROM `rate_table` WHERE `part_code`='J' AND `band_label` LIKE 'Hired Plant%';
INSERT IGNORE INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
 (1,'J','Hired Plant hiring charges up to 5,000','flat',5000,150,'Live NP config'),
 (1,'J','Hired Plant hiring charges up to 10,000','flat',10000,300,'Live NP config');

-- 8) Experience (new-venture) loading by years band — applies to PL (Main) and EL when present.
--    From the live NP config: <1yr 10%, 1-2yr 7.5%, 2-3yr 5%, 3+ 0%. (Uniform across activities;
--    Carpet & Upholstery has no <1yr row = refer, handled as an underwriting flag.)

CREATE TABLE IF NOT EXISTS `experience_loading` (
  `years_band` VARCHAR(30) NOT NULL,
  `load_pct`   DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (`years_band`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO `experience_loading` (`years_band`,`load_pct`) VALUES
 ('Less than 1 year',10.0),
 ('Between 1 and 2 years',7.5),
 ('Between 2 and 3 years',5.0),
 ('Over 3 years',0.0);


-- ===== 03_documents.sql =====
-- Document add-ons: policy-number config, per-case insurer policy number, saved-document path.
-- Run after the earlier SQL files. Each ALTER is separate (SQLite-compatible).

ALTER TABLE `scheme` ADD COLUMN `policy_no_prefix` VARCHAR(20) NULL;
ALTER TABLE `scheme` ADD COLUMN `policy_no_suffix` VARCHAR(20) NULL;
UPDATE `scheme` SET `policy_no_prefix`='ZCLP', `policy_no_suffix`='1' WHERE `scheme_id`=1;

ALTER TABLE `case_policy` ADD COLUMN `insurer_policy_number` VARCHAR(40) NULL;

ALTER TABLE `document` ADD COLUMN `file_path` VARCHAR(255) NULL;


-- ===== 04_adjustments.sql =====
-- Mid-term adjustments: store the quote inputs on each term so an MTA can pre-fill and recompute.
ALTER TABLE `policy_term` ADD COLUMN `quote_inputs` TEXT NULL;


-- ===== 05_reports.sql =====
-- Saved/named report templates (report type + chosen columns).

CREATE TABLE IF NOT EXISTS `report_template` (
  `template_id` INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `report_type` VARCHAR(40)  NOT NULL,
  `columns`     TEXT NOT NULL,
  `filters`     TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===== 06_import.sql =====
-- Tag records that came from a bordereau import (so they can be shown as provisional
-- and bulk-managed until the full SchemeServe data dump replaces them).
ALTER TABLE `case_policy` ADD COLUMN `source` VARCHAR(30) NULL;


-- ===== 07_mta_fee.sql =====
-- Store the (editable) policy fee charged on a mid-term adjustment.
ALTER TABLE `policy_term` ADD COLUMN `policy_fee` DECIMAL(10,2) NULL;


-- ===== 08_bordereau.sql =====
-- Store the per-part premium breakdown on each term, for the insurer bordereau report.
ALTER TABLE `policy_term` ADD COLUMN `premium_breakdown` TEXT NULL;


-- ===== 09_policy_prefix.sql =====
-- Polished policy numbers use the format ZCLP/<number>/1 (was POL/<number>/01).
UPDATE `scheme` SET `policy_no_prefix`='ZCLP', `policy_no_suffix`='1' WHERE `scheme_id`=1;

