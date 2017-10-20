/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

CREATE TABLE IF NOT EXISTS `silverbullet_invitation` (
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

ALTER TABLE `silverbullet_certificate` 
ADD COLUMN `silverbullet_invitation_id` INT(11) NOT NULL AFTER `silverbullet_user_id`,
ADD COLUMN `issued` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '',
ADD INDEX `fk_silverbullet_certificate_silverbullet_invitation1_idx` (`silverbullet_invitation_id` ASC)  COMMENT '',
ADD CONSTRAINT `fk_silverbullet_certificate_silverbullet_invitation1`
    FOREIGN KEY (`silverbullet_invitation_id`)
    REFERENCES `silverbullet_invitation` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION;
/* ALTER TABLE `silverbullet_certificate` ADD COLUMN `issued` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '';*/


DROP PROCEDURE IF EXISTS migrateInvitationTokens;
DELIMITER ;;

CREATE PROCEDURE migrateInvitationTokens()
BEGIN
  DECLARE invitations INT DEFAULT 0;
  DECLARE n INT DEFAULT 0;
  DECLARE i INT DEFAULT 0;
  DECLARE serialvar BLOB DEFAULT NULL;
  DECLARE cnvar VARCHAR(45) DEFAULT '';
  DECLARE revocationvar INT DEFAULT 1;
  SELECT COUNT(*) FROM `silverbullet_invitation` INTO invitations;
  SELECT COUNT(*) FROM `silverbullet_certificate` INTO n;
  IF invitations = 0 THEN
    WHILE i < n DO
      SELECT `serial_number`, `cn`, `revocation_status` FROM `silverbullet_certificate` LIMIT i, 1 INTO serialvar, cnvar, revocationvar;
      IF serialvar IS NULL AND cnvar IS NULL AND revocationvar = 0 THEN
          INSERT INTO `silverbullet_invitation` (profile_id, silverbullet_user_id, token, quantity, expiry) SELECT profile_id, silverbullet_user_id, one_time_token, 1, expiry FROM `silverbullet_certificate` LIMIT i, 1;
      END IF;
      SET i = i + 1;
    END WHILE;
  END IF;
END;
;;

DELIMITER ;
CALL migrateInvitationTokens();
