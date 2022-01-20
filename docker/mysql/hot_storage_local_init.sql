CREATE DATABASE IF NOT EXISTS parariusearcher;

USE mysql;

CREATE USER 'pararius_local'@'%' IDENTIFIED BY 'secret';

FLUSH PRIVILEGES;

GRANT ALL PRIVILEGES ON *.* TO 'pararius_local'@'%';

FLUSH PRIVILEGES;

USE parariusearcher;

CREATE TABLE IF NOT EXISTS `listings` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `url` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;