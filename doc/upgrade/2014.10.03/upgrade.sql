
-- SQL migration from versions 2014.10.03 and earlier
-- Use the upgrade script upgrade_20141003.php!

ALTER TABLE :StartingOrder CHANGE COLUMN PoolNumber GroupNumber SMALLINT NOT NULL;
SHOW WARNINGS;
