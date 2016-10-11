CREATE TABLE IF NOT EXISTS `certificate` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `inst_id` INT(11) NOT NULL COMMENT '',
  `user_id` VARCHAR(255) NOT NULL COMMENT '',
  `expiry` TIMESTAMP NULL COMMENT '',
  `document` BLOB NULL COMMENT '',
  PRIMARY KEY (`id`, `inst_id`)  COMMENT '',
  INDEX `fk_certificate_institution1_idx` (`inst_id` ASC)  COMMENT '',
  CONSTRAINT `fk_certificate_institution1`
    FOREIGN KEY (`inst_id`)
    REFERENCES `cat`.`institution` (`inst_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `profile_option_dict` VALUES 
('user:silverbullet','contains federation name for which this silverbullet user belongs','integer', NULL);
