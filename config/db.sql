

CREATE TABLE IF NOT EXISTS `admin` ( 
    `admin_id` SMALLINT UNSIGNED NOT NULL, 
    `login` VARCHAR(8) NOT NULL, 
    `password` CHAR(60) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL, 
    PRIMARY KEY (`admin_id`), 
    UNIQUE `login` (`login`)
    ) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `contact` (
  `contact_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contact_id`),
  UNIQUE `email` (`email`)
) ENGINE = InnoDB;