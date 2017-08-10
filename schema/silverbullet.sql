/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
DROP TABLE IF EXISTS `silverbullet_certificate`;
DROP TABLE IF EXISTS `silverbullet_user`;
DROP TABLE IF EXISTS `silverbullet_invitation`;

CREATE TABLE `silverbullet_user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `username` VARCHAR(45) NOT NULL COMMENT '',
  `expiry` TIMESTAMP DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `last_ack` TIMESTAMP NOT NULL DEFAULT NOW() COMMENT '',
  `deactivation_status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `deactivation_time` TIMESTAMP DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`, `profile_id`)  COMMENT '',
  INDEX `fk_silverbullet_user_profile1_idx` (`profile_id` ASC)  COMMENT '',
  UNIQUE INDEX `username_UNIQUE` (`profile_id` ASC, `username` ASC)  COMMENT '',
  CONSTRAINT `fk_silverbullet_user_profile1`
    FOREIGN KEY (`profile_id`)
    REFERENCES `profile` (`profile_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `silverbullet_invitation` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `silverbullet_user_id` INT(11) NOT NULL COMMENT '',
  `token` VARCHAR(45) NOT NULL COMMENT '',
  `quantity` TINYINT(3) NOT NULL DEFAULT 1 COMMENT '',
  `expiry` TIMESTAMP DEFAULT '0000-00-00 00:00:00' COMMENT '',
  PRIMARY KEY (`id`, `profile_id`, `silverbullet_user_id`)  COMMENT '',
  INDEX `fk_silverbullet_invitation_silverbullet_user1_idx` (`silverbullet_user_id` ASC, `profile_id` ASC)  COMMENT '',
  CONSTRAINT `fk_silverbullet_invitation_silverbullet_user1`
    FOREIGN KEY (`silverbullet_user_id` , `profile_id`)
    REFERENCES `silverbullet_user` (`id` , `profile_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB CHARSET=utf8;

CREATE TABLE `silverbullet_certificate` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `silverbullet_user_id` INT(11) NOT NULL COMMENT '',
  `silverbullet_invitation_id` INT(11) NOT NULL COMMENT '', /* new field */
  /* `one_time_token` VARCHAR(45) NOT NULL COMMENT '',  remove this one */
  `serial_number` BLOB NULL COMMENT '',
  `cn` VARCHAR(128) NULL COMMENT '',
  `issued` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '', /* new field */
  `expiry` TIMESTAMP DEFAULT '0000-00-00 00:00:00' COMMENT '',
  `device` VARCHAR(128) DEFAULT NULL,
  `revocation_status` ENUM('NOT_REVOKED', 'REVOKED') NOT NULL DEFAULT 'NOT_REVOKED',
  `revocation_time` TIMESTAMP DEFAULT '0000-00-00 00:00:00',
  `OCSP` BLOB DEFAULT NULL,
  `OCSP_timestamp` TIMESTAMP DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`, `profile_id`, `silverbullet_user_id`)  COMMENT '',
  INDEX `fk_silverbullet_certificate_silverbullet_user1_idx` (`silverbullet_user_id` ASC, `profile_id` ASC)  COMMENT '',
  INDEX `fk_silverbullet_certificate_silverbullet_invitation1_idx` (`silverbullet_invitation_id` ASC)  COMMENT '', /* new index */
  CONSTRAINT `fk_silverbullet_certificate_silverbullet_user1`
    FOREIGN KEY (`silverbullet_user_id` , `profile_id`)
    REFERENCES `silverbullet_user` (`id` , `profile_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION ,
  CONSTRAINT `fk_silverbullet_certificate_silverbullet_invitation1` /* new constraint */
    FOREIGN KEY (`silverbullet_invitation_id`)
    REFERENCES `silverbullet_invitation` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB CHARSET=utf8;

