
-- SQL migration from versions 2014.10.03 and earlier
-- Use the upgrade script upgrade_20141003.php!
-- Second phase script

-- after migrating playerlimits to pools, delete playerlimits column
ALTER TABLE :Event DROP COLUMN PlayerLimit;
SHOW WARNINGS;
