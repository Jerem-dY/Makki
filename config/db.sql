

CREATE TABLE IF NOT EXISTS `admin` ( 
    `admin_id` SMALLINT UNSIGNED NOT NULL, 
    `login` VARCHAR(8) NOT NULL, 
    `password` CHAR(60) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL, 
    PRIMARY KEY (`admin_id`), 
    UNIQUE `login` (`login`)
    ) ENGINE = InnoDB;

