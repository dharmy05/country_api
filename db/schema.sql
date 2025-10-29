-- db/schema.sql for Country Currency & Exchange API

CREATE TABLE IF NOT EXISTS `countries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `capital` VARCHAR(255) DEFAULT NULL,
  `region` VARCHAR(100) DEFAULT NULL,
  `population` BIGINT NOT NULL DEFAULT 0,
  `currency_code` VARCHAR(10) DEFAULT NULL,
  `exchange_rate` DOUBLE DEFAULT NULL,
  `estimated_gdp` DOUBLE DEFAULT NULL,
  `flag_url` VARCHAR(512) DEFAULT NULL,
  `last_refreshed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_countries_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `meta` (
  `k` VARCHAR(100) NOT NULL,
  `v` TEXT DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
