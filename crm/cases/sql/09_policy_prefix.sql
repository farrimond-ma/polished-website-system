-- Polished policy numbers use the format ZCLP/<number>/1 (was POL/<number>/01).
UPDATE `scheme` SET `policy_no_prefix`='ZCLP', `policy_no_suffix`='1' WHERE `scheme_id`=1;
