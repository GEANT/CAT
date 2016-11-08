CREATE TABLE IF NOT EXISTS `silverbullet_user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `username` VARCHAR(45) NOT NULL COMMENT '',
  `one_time_token` VARCHAR(45) NOT NULL COMMENT '',
  `token_expiry` TIMESTAMP NOT NULL COMMENT '',
  PRIMARY KEY (`id`, `profile_id`)  COMMENT '',
  INDEX `fk_silverbullet_user_profile1_idx` (`profile_id` ASC)  COMMENT '',
  CONSTRAINT `fk_silverbullet_user_profile1`
    FOREIGN KEY (`profile_id`)
    REFERENCES `profile` (`profile_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `silverbullet_certificate` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `silverbullet_user_id` INT(11) NOT NULL COMMENT '',
  `serial_number` BLOB NULL COMMENT '',
  `cn` VARCHAR(45) NULL COMMENT '',
  `expiry` TIMESTAMP NULL DEFAULT NULL COMMENT '',
  PRIMARY KEY (`id`, `profile_id`, `silverbullet_user_id`)  COMMENT '',
  INDEX `fk_silverbullet_certificate_silverbullet_user1_idx` (`silverbullet_user_id` ASC, `profile_id` ASC)  COMMENT '',
  CONSTRAINT `fk_silverbullet_certificate_silverbullet_user1`
    FOREIGN KEY (`silverbullet_user_id` , `profile_id`)
    REFERENCES `silverbullet_user` (`id` , `profile_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB  DEFAULT CHARSET=utf8;
