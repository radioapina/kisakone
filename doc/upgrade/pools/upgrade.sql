
-- SQL migration from versions 2014.10.03 and earlier
-- Use the upgrade script upgrade_20141003.php!

-- change the incorrect term PoolNumber to GroupNumber as we'll introduce real pools
ALTER TABLE :StartingOrder CHANGE COLUMN PoolNumber GroupNumber SMALLINT NOT NULL;
SHOW WARNINGS;

-- introduce the Pool
CREATE TABLE :Pool (
  id INT NOT NULL AUTO_INCREMENT,
  PoolNumber INT NOT NULL DEFAULT 1,
  PoolText VARCHAR(20) DEFAULT NULL,
  Event INT NOT NULL,
  PlayerLimit INT NOT NULL DEFAULT 999,
  PRIMARY KEY(id),
  FOREIGN KEY (Event) REFERENCES :Event(id),
  INDEX(PoolNumber)
) ENGINE=InnoDB;
SHOW WARNINGS;

-- Add PoolCount to Event
ALTER TABLE :Event ADD COLUMN PoolCount INT NOT NULL DEFAULT 1 AFTER AdBanner;
SHOW WARNINGS;

-- Reference Pool from other tables
ALTER TABLE :Round ADD COLUMN Pool INT NOT NULL DEFAULT 1 AFTER Event;
SHOW WARNINGS;

ALTER TABLE :ClassInEvent ADD COLUMN Pool INT NOT NULL DEFAULT 1 AFTER Event;
SHOW WARNINGS;

