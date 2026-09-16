-- Web-app authentication layer (run AFTER schemeserve_backend_mysql.sql).
-- Adds a password column to app_user and sets a default password for the seeded users.
-- DEFAULT PASSWORD for mark / kay / System is:  polished2026
-- >>> Change it after first login (see set_password.php) <<<

ALTER TABLE `app_user`
  ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `role`;

UPDATE `app_user`
   SET `password_hash` = '$2y$10$zeNFN9RcmWVE0SHP4PiafOAZvQe5EdzgUMO.PdRRFN/ojnohlEmaG'
 WHERE `username` IN ('mark','kay','System');
