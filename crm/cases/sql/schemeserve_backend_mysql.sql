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
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `debiting_schedule`;
DROP TABLE IF EXISTS `money_transaction`;
DROP TABLE IF EXISTS `note`;
DROP TABLE IF EXISTS `claim`;
DROP TABLE IF EXISTS `document`;
DROP TABLE IF EXISTS `applied_endorsement`;
DROP TABLE IF EXISTS `rating_adjustment`;
DROP TABLE IF EXISTS `rating_line`;
DROP TABLE IF EXISTS `cover_selection`;
DROP TABLE IF EXISTS `risk_answer`;
DROP TABLE IF EXISTS `policy_term`;
DROP TABLE IF EXISTS `case_policy`;
DROP TABLE IF EXISTS `client`;
DROP TABLE IF EXISTS `rate_table`;
DROP TABLE IF EXISTS `endorsement`;
DROP TABLE IF EXISTS `question_ref`;
DROP TABLE IF EXISTS `cover_part_ref`;
DROP TABLE IF EXISTS `import_batch`;
DROP TABLE IF EXISTS `agent`;
DROP TABLE IF EXISTS `app_user`;
DROP TABLE IF EXISTS `scheme`;
DROP TABLE IF EXISTS `insurer`;

-- =====================================================================
--  REFERENCE / CONFIG TABLES
-- =====================================================================
CREATE TABLE `insurer` (
  `insurer_id`  INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  PRIMARY KEY (`insurer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scheme` (
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

CREATE TABLE `app_user` (
  `user_id`      INT NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(60) NOT NULL,
  `display_name` VARCHAR(120) NULL,
  `role`         VARCHAR(40)  NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `agent` (
  `agent_id` INT NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(120) NOT NULL,
  PRIMARY KEY (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `import_batch` (
  `import_batch_id` INT NOT NULL,
  `imported_at`     DATETIME NULL,
  `description`     VARCHAR(160) NULL,
  PRIMARY KEY (`import_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cover_part_ref` (
  `part_code` VARCHAR(4) NOT NULL,          -- A,B,C,D,J
  `part_name` VARCHAR(80) NOT NULL,
  `basis`     VARCHAR(40) NULL,             -- wageroll / turnover / flat
  `operative` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`part_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `question_ref` (
  `question_id`   INT NOT NULL AUTO_INCREMENT,
  `part_name`     VARCHAR(60) NOT NULL,     -- backend Part / section
  `question_key`  VARCHAR(60) NULL,
  `question_text` VARCHAR(400) NOT NULL,
  `answer_type`   VARCHAR(30) NULL,         -- radio/dropdown/text/currency/date/yesno
  `options`       VARCHAR(400) NULL,
  `backend_only`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `endorsement` (
  `endorsement_code` VARCHAR(30) NOT NULL,
  `title`            VARCHAR(160) NOT NULL,
  `body`             TEXT NULL,
  PRIMARY KEY (`endorsement_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Flexible rate table: percent rates, flat premiums and grid cells all live here.
CREATE TABLE `rate_table` (
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
CREATE TABLE `client` (
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

CREATE TABLE `case_policy` (
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

CREATE TABLE `policy_term` (
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

CREATE TABLE `risk_answer` (
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

CREATE TABLE `cover_selection` (
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

CREATE TABLE `rating_line` (
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

CREATE TABLE `rating_adjustment` (
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

CREATE TABLE `applied_endorsement` (
  `id`               BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`          BIGINT NOT NULL,
  `endorsement_code` VARCHAR(30) NOT NULL,
  `applies_to_text`  VARCHAR(400) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ae_term` (`term_id`),
  CONSTRAINT `fk_ae_term` FOREIGN KEY (`term_id`)          REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_ae_code` FOREIGN KEY (`endorsement_code`) REFERENCES `endorsement`(`endorsement_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `document` (
  `doc_id`       BIGINT NOT NULL AUTO_INCREMENT,
  `term_id`      BIGINT NOT NULL,
  `doc_type`     VARCHAR(40) NOT NULL,        -- Schedule/Wording/Summary/Statement of Facts/Receipt/EL Certificate
  `name`         VARCHAR(200) NULL,
  `generated_at` DATETIME NULL,
  `generated_by` INT NULL,
  PRIMARY KEY (`doc_id`),
  KEY `ix_doc_term` (`term_id`),
  CONSTRAINT `fk_doc_term` FOREIGN KEY (`term_id`)      REFERENCES `policy_term`(`term_id`),
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`generated_by`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `claim` (
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

CREATE TABLE `note` (
  `note_id`    BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`    BIGINT NOT NULL,
  `body`       VARCHAR(1000) NULL,
  `reference`  VARCHAR(80) NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`note_id`),
  KEY `ix_note_case` (`case_id`),
  CONSTRAINT `fk_note_case` FOREIGN KEY (`case_id`)    REFERENCES `case_policy`(`case_id`),
  CONSTRAINT `fk_note_user` FOREIGN KEY (`created_by`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `money_transaction` (
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

CREATE TABLE `debiting_schedule` (
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

CREATE TABLE `activity_log` (
  `activity_id` BIGINT NOT NULL AUTO_INCREMENT,
  `case_id`     BIGINT NOT NULL,
  `type`        VARCHAR(30) NOT NULL,        -- FileUpload/Policy/Document/Note/Email
  `details`     VARCHAR(1000) NULL,
  `user_id`     INT NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`activity_id`),
  KEY `ix_act_case` (`case_id`),
  CONSTRAINT `fk_act_case` FOREIGN KEY (`case_id`) REFERENCES `case_policy`(`case_id`),
  CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `app_user`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  SEED : reference data
-- =====================================================================
INSERT INTO `insurer` (`insurer_id`,`name`) VALUES (1,'Zurich Insurance');

INSERT INTO `scheme` (`scheme_id`,`name`,`product_name`,`insurer_id`,`mga`,`broker`,`wording_ref`,`agreement_ref`)
VALUES (1,'Polished Cleaners Scheme','Polished Insurance',1,'Camberford Underwriting','Allied Insurance Services','ZCX631AA','B1053CU/ZUR-SDA-Allied03/24');

INSERT INTO `app_user` (`user_id`,`username`,`display_name`,`role`) VALUES
 (1,'System','System','system'),
 (2,'mark','Mark Farrimond','agent'),
 (3,'kay','Kay Blackburn','administrator');

INSERT INTO `agent` (`agent_id`,`name`) VALUES (1,'Polished Cleaners Scheme Agent');

INSERT INTO `import_batch` (`import_batch_id`,`imported_at`,`description`) VALUES
 (24733,NULL,'Legacy import batch'),
 (24915,NULL,'Legacy import batch');

INSERT INTO `cover_part_ref` (`part_code`,`part_name`,`basis`,`operative`) VALUES
 ('A','Employers'' Liability','wageroll',1),
 ('B','Public & Products Liability','turnover',1),
 ('C','Directors'' & Officers'' Liability','flat',1),
 ('D','Professional Indemnity','flat',1),
 ('J','Contract Works Construction','flat',1),
 ('E','Material Damage (All Risks)','n/a',0);

-- ---- Question set (Parts + backend-only sections) ----
INSERT INTO `question_ref` (`part_name`,`question_key`,`question_text`,`answer_type`,`options`,`backend_only`) VALUES
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
INSERT INTO `endorsement` (`endorsement_code`,`title`) VALUES
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
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
 (1,'A',NULL,'Internal Wage Roll','wageroll',0.55000,NULL,'Live 9676245',NULL),
 (1,'A',NULL,'Ground Wage Roll','wageroll',0.93500,NULL,'Live 9676245','Outside, not at height'),
 (1,'A',NULL,'Up to 15m Wage Roll','wageroll',3.00000,NULL,'Live 9676245','Height band <=15m'),
 (1,'A',NULL,'Up to 100m Wage Roll','wageroll',3.00000,NULL,'Live 9676245','Height band <=100m'),
 (1,'A',NULL,'EL Minimum Premium','flat',NULL,100.00,'Manual v1.6',NULL);
-- Part B Public & Products Liability (turnover %) by limit, incl. 15m/100m split
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
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
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`flat_amount`,`source`) VALUES
 (1,'C','D&O Flat Premium (100,000 LOI)','flat',110.00,'Manual v1.6');
-- Part D PI grid (turnover_band upper x limit)
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
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
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
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
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`rate_pct`,`source`,`note`) VALUES
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
INSERT INTO `client` (`client_ref`,`title`,`first_name`,`last_name`,`joint_names`,`trading_name`,`addr1`,`addr2`,`town`,`county`,`postcode`,`phone_mobile`,`domicile`) VALUES
 ('LIL1',NULL,NULL,NULL,'Liliana''s cleaning services LTD','Liliana Cleaning Services','Caer Oak View','Brecon Road','Crickhowell','Powys','NP8 1DG','07891081754','United Kingdom'),
 ('MRG5','Mr','Greg','Thomson','Greg Thomson and Kirstie Mair','Bedazzled Cleaning','113 Jellicoe Avenue',NULL,'Gosport','Hampshire','PO12 2PB',NULL,'United Kingdom');

INSERT INTO `case_policy` (`case_id`,`scheme_id`,`client_ref`,`agent_id`,`created_at`,`status`,`import_batch_id`) VALUES
 (9676245,1,'LIL1',1,'2023-07-19 12:50:00','On Cover',24915),
 (9568516,1,'MRG5',1,'2023-06-29 00:25:00','Cancelled',24733);

-- --- Case 9676245 (On Cover) current term = 2nd renewal ---
INSERT INTO `policy_term` (`term_id`,`case_id`,`endorsement_ref`,`transaction_type`,`sequence_label`,`status`,`inception_date`,`expiry_date`,`lta_date`,`effective_date`,`total_premium`,`adjustment`,`balance`) VALUES
 (1,9676245,'ENDORSEMENT_ID_17436653','Renewal','2nd: 14 Sep 2025','On Cover','2025-09-14','2026-09-13',NULL,'2025-09-14',379.31,0.00,0.00);

INSERT INTO `cover_selection` (`term_id`,`part_code`,`included`,`limit_of_indemnity`) VALUES
 (1,'B',1,2000000),
 (1,'A',1,10000000);

INSERT INTO `rating_line` (`term_id`,`part_code`,`insurer_id`,`line_label`,`exposure`,`rate_pct`,`premium`) VALUES
 (1,'B',1,'Turnover Internal & Ground',75000,0.09675,72.5625),
 (1,'B',1,'Turnover up to 15m',0,0.10750,0.0000),
 (1,'B',1,'Turnover up to 100m',0,0.11825,0.0000),
 (1,'B',1,'Turnover for BFSC',0,0.15050,0.0000),
 (1,'A',1,'Internal Wage Roll',25000,0.55000,137.5000),
 (1,'A',1,'Ground Wage Roll',0,0.93500,0.0000),
 (1,'A',1,'Up to 15m Wage Roll',0,3.00000,0.0000),
 (1,'A',1,'Up to 100m Wage Roll',0,3.00000,0.0000);

INSERT INTO `rating_adjustment` (`term_id`,`part_code`,`basic_total`,`minimum_premium`,`ni_load_pct`,`experience_load_pct`,`discretionary_pct`,`net_total`,`commission_from_insurer_pct`,`commission_to_allied_pct`) VALUES
 (1,'B',72.56,90.98,0.0000,0.0000,0.0000,163.54,25.0000,0.0000),
 (1,'A',137.50,0.00,0.0000,0.0000,0.0000,137.50,25.0000,0.0000);

INSERT INTO `money_transaction` (`txn_id`,`case_id`,`type`,`txn_date`,`amount`,`result`) VALUES
 (759562,9676245,'Cash Payment','2025-09-01',379.31,'OK'),
 (738030,9676245,'Cash Payment','2024-10-15',379.31,'OK');

-- --- Case 9568516 (Cancelled) current view = adjustment ---
INSERT INTO `policy_term` (`term_id`,`case_id`,`endorsement_ref`,`transaction_type`,`sequence_label`,`status`,`inception_date`,`expiry_date`,`lta_date`,`effective_date`,`total_premium`,`adjustment`,`balance`) VALUES
 (2,9568516,'ENDORSEMENT_ID_19785789','Adjustment','adj: 31 Jul 2026','Cancelled','2026-07-31','2026-08-28',NULL,'2026-08-03',473.50,-40.87,40.87);

INSERT INTO `note` (`case_id`,`body`,`reference`,`created_by`,`created_at`) VALUES
 (9568516,'Loan confirmed. Client name Greg Thomson, Address 113 Jellicoe Avenue, Gosport, Hampshire, United Kingdom','5013836106-04',3,'2025-08-29 10:06:59');

INSERT INTO `document` (`term_id`,`doc_type`,`name`,`generated_at`,`generated_by`) VALUES
 (2,'Receipt','Receipt - DD','2025-08-29 10:07:22',3);

INSERT INTO `activity_log` (`case_id`,`type`,`details`,`user_id`,`created_at`) VALUES
 (9568516,'FileUpload','Bedazzled Cleaning - Greg conf Ok to cancel.msg',3,'2026-08-03 10:06:21'),
 (9568516,'Policy','Policy status changed from (Record 19785789) Draft to Cancelled (CancelPolicy_Overlay)',2,'2026-08-03 10:00:34'),
 (9568516,'Policy','Policy inception date changed 01/01/0001 to 03/08/2026 (InitiateAdjustment SetInceptionDate)',1,'2026-08-03 10:00:33'),
 (9568516,'FileUpload','Bedazzled - Req conf of canc from both parties and conf of no claims.msg',3,'2026-08-03 09:38:28'),
 (9568516,'Document','New Document (Receipt - DD) Generated',3,'2025-08-29 10:07:22'),
 (9676245,'Email','Renewal invitation issued',3,'2025-09-01 09:00:00');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  VIEWS : convenience
-- =====================================================================
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
