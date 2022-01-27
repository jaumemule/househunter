CREATE DATABASE IF NOT EXISTS househunter;

USE mysql;

CREATE USER 'househunter_local'@'%' IDENTIFIED BY 'secret';

FLUSH PRIVILEGES;

GRANT ALL PRIVILEGES ON *.* TO 'househunter_local'@'%';

FLUSH PRIVILEGES;

USE househunter;

CREATE TABLE IF NOT EXISTS `listings` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `url` varchar(255) NOT NULL,
    `name` varchar(255),
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;