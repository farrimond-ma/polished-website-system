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
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`pl_limit`,`band_label`,`exposure_basis`,`rate_pct`,`flat_amount`,`source`,`note`) VALUES
 (1,'B',10000000,'Turnover Internal & Ground','turnover',0.26875,NULL,'Live NP config',NULL),
 (1,'B',10000000,'Turnover up to 15m','turnover',0.30100,NULL,'Live NP config','Height <=15m'),
 (1,'B',10000000,'Turnover up to 100m','turnover',0.21500,NULL,'Live NP config','Height <=100m'),
 (1,'B',10000000,'Turnover for BFSC','turnover',0.43000,NULL,'Live NP config',NULL);

-- 3) Risk-group minimum NET premiums (replace the manual's flat minimums).
--    risk_group 7083 = Public Liability (Main), 7249 = Employers' Liability.
DROP TABLE IF EXISTS `min_net_premium`;
CREATE TABLE `min_net_premium` (
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
INSERT INTO `min_net_premium` (`risk_group`,`part_code`,`who_works`,`pl_limit`,`over_75k`,`just_partner`,`height_work`,`min_net`) VALUES
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
DROP TABLE IF EXISTS `scheme_config`;
CREATE TABLE `scheme_config` (
  `ckey`   VARCHAR(40) NOT NULL,
  `cvalue` DECIMAL(10,4) NOT NULL,
  `note`   VARCHAR(120) NULL,
  PRIMARY KEY (`ckey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `scheme_config` (`ckey`,`cvalue`,`note`) VALUES
 ('ipt_pct',12.0,'Insurance Premium Tax %'),
 ('commission_from_insurer_pct',25.0,'All risk groups'),
 ('policy_fee_pct',12.5,'% of (net + IPT)'),
 ('credit_interest_pct',5.96,'Instalment credit charge %');

-- 5) Contents / Material Damage cover options (live; Part E now operative here)
DROP TABLE IF EXISTS `contents_option`;
CREATE TABLE `contents_option` (
  `opt`           VARCHAR(20) NOT NULL,
  `contents_si`   DECIMAL(10,2) NOT NULL,
  `stock_si`      DECIMAL(10,2) NOT NULL,
  `computers_si`  DECIMAL(10,2) NOT NULL,
  `documents_si`  DECIMAL(10,2) NOT NULL,
  `fee`           DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`opt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `contents_option` (`opt`,`contents_si`,`stock_si`,`computers_si`,`documents_si`,`fee`) VALUES
 ('Option 1',10000,0,0,0,150),
 ('Option 2',20000,5000,10000,5000,250),
 ('Option 3',30000,5000,15000,10000,350),
 ('Option 4',50000,5000,25000,10000,450);

-- 6) Owned plant: live config has NO £30,000 tier -> remove for accuracy
DELETE FROM `rate_table` WHERE `part_code`='J' AND `band_label`='Owned Plant 30,000';

-- 7) Hired-in plant: live model is keyed on hiring charges only (SI = charges).
DELETE FROM `rate_table` WHERE `part_code`='J' AND `band_label` LIKE 'Hired Plant%';
INSERT INTO `rate_table` (`scheme_id`,`part_code`,`band_label`,`exposure_basis`,`turnover_band`,`flat_amount`,`source`) VALUES
 (1,'J','Hired Plant hiring charges up to 5,000','flat',5000,150,'Live NP config'),
 (1,'J','Hired Plant hiring charges up to 10,000','flat',10000,300,'Live NP config');

-- 8) Experience (new-venture) loading by years band — applies to PL (Main) and EL when present.
--    From the live NP config: <1yr 10%, 1-2yr 7.5%, 2-3yr 5%, 3+ 0%. (Uniform across activities;
--    Carpet & Upholstery has no <1yr row = refer, handled as an underwriting flag.)
DROP TABLE IF EXISTS `experience_loading`;
CREATE TABLE `experience_loading` (
  `years_band` VARCHAR(30) NOT NULL,
  `load_pct`   DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (`years_band`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `experience_loading` (`years_band`,`load_pct`) VALUES
 ('Less than 1 year',10.0),
 ('Between 1 and 2 years',7.5),
 ('Between 2 and 3 years',5.0),
 ('Over 3 years',0.0);
