/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * Author:  swinter
 * Created: 17.12.2015
 */

DELETE FROM `profile_option` WHERE option_name = 'profile:QR-user';
DELETE FROM `profile_option_dict` WHERE name = 'profile:QR-user';

INSERT INTO `profile_option_dict` VALUES 
('media:force_proxy','URL of a mandatory content filter proxy','string',NULL),
('fed:realname','a friendly display name of the NRO/federation','string', 'ML'),
('fed:logo_file','logo of the NRO/federation','file', NULL),
('fed:css_file','custom CSS to be applied on any skin','file',NULL),
('fed:custominvite','custom text to send with new IdP invitations','text', NULL),
('fed:desired_skin','UI skin to use - if not exist, fall back to default','string',NULL),
('fed:include_logo_installers','whether or not the fed logo should be visible in installers','boolean', NULL),
('fed:silverbullet','enable Silver Bullet in this federation','boolean',NULL),
('fed:silverbullet-noterm','to tell us we should not terminate EAP for this federation silverbullet','boolean',NULL),
('fed:silverbullet-maxusers','maximum number of users per silverbullet profile','integer',NULL),
('fed:minted_ca_file','set of default CAs to add to new IdPs on signup','file',NULL),
('hiddenprofile:tou_accepted','were the terms of use accepted?','boolean',NULL),
('profile:customsuffix','The filename suffix to use for the generated installers','string','ML'),
('fed:url', 'URL to the homepage of a federation', 'string', 'ML'),
('device-specific:geantlink','Use GEANTlink TTLS supplicant for W8', 'boolean',NULL),
('device-specific:builtin_ttls','Use builtin TTLS supplicant for Windows 10', 'boolean',NULL);


CREATE TABLE `federation` (
  `federation_id` varchar(16) NOT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`federation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `federation_option` (
  `federation_id` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  `option_name` varchar(32) DEFAULT NULL,
  `option_value` longblob,
  `row` int(11) NOT NULL AUTO_INCREMENT,
  KEY `option_name` (`option_name`),
  KEY `rowindex` (`row`),
  CONSTRAINT `federation_option_ibfk_1` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `profile` ADD COLUMN `checkuser_outer` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `profile` ADD COLUMN `checkuser_value` varchar(128) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `verify_userinput_suffix` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `profile` ADD COLUMN `hint_userinput_suffix` int(1) NOT NULL DEFAULT '0';

ALTER TABLE `user_options` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `federation_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `institution_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `profile_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;

UPDATE downloads SET installer_time = "2000-01-01 00:00:00" WHERE installer_time < "2000-01-01 00:00:00";
ALTER TABLE `downloads` CHANGE COLUMN `installer_time` `installer_time` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00';
ALTER TABLE `downloads` ADD COLUMN `downloads_silverbullet` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `user_options` DROP KEY `rowindex`, CHANGE COLUMN `id` `row` int primary key auto_increment, ADD KEY `rowindex` (`row`);

ALTER TABLE ownership DROP KEY `pair`;
ALTER TABLE ownership CHANGE COLUMN `user_id` `user_id` VARCHAR(2048) NOT NULL;

UPDATE institution SET country = UPPER(country);

CREATE VIEW `v_active_inst` AS select distinct `profile`.`inst_id` AS `inst_id` from `profile` where (`profile`.`showtime` = 1);

CREATE TABLE `silverbullet_user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `profile_id` INT(11) NOT NULL COMMENT '',
  `username` VARCHAR(45) NOT NULL COMMENT '',
  `expiry` TIMESTAMP DEFAULT '2000-01-01 00:00:00' COMMENT '',
  `last_ack` TIMESTAMP NOT NULL DEFAULT NOW() COMMENT '',
  `deactivation_status` ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `deactivation_time` TIMESTAMP DEFAULT '2000-01-01 00:00:00',
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
  `token` VARCHAR(128) NOT NULL COMMENT '',
  `quantity` TINYINT(3) NOT NULL DEFAULT 1 COMMENT '',
  `expiry` TIMESTAMP DEFAULT '2000-01-01 00:00:00' COMMENT '',
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
  `ca_type` enum('RSA','ECDSA') NOT NULL DEFAULT 'RSA',
  `serial_number` BLOB NULL COMMENT '',
  `cn` VARCHAR(128) NULL COMMENT '',
  `issued` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '', /* new field */
  `expiry` TIMESTAMP DEFAULT '2001-01-01 00:00:00' COMMENT '',
  `device` VARCHAR(128) DEFAULT NULL,
  `revocation_status` ENUM('NOT_REVOKED', 'REVOKED') NOT NULL DEFAULT 'NOT_REVOKED',
  `revocation_time` TIMESTAMP DEFAULT '2001-01-01 00:00:00',
  `OCSP` BLOB DEFAULT NULL,
  `OCSP_timestamp` TIMESTAMP DEFAULT '2001-01-01 00:00:00',
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