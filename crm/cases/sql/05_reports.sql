-- Saved/named report templates (report type + chosen columns).
DROP TABLE IF EXISTS `report_template`;
CREATE TABLE `report_template` (
  `template_id` INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `report_type` VARCHAR(40)  NOT NULL,
  `columns`     TEXT NOT NULL,
  `filters`     TEXT NULL,
  `created_by`  INT NULL,
  `created_at`  DATETIME NULL,
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
