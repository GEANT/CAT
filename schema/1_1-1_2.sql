/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
/**
 * Author:  swinter
 * Created: 17.12.2015
 */
INSERT INTO `profile_option_dict` VALUES 
('fed:realname','a friendly display name of the NRO/federation','string', 'ML'),
('fed:logo_file','logo of the NRO/federation','file', NULL),
('fed:css_file','custom CSS to be applied on any skin','file',NULL),
('fed:custominvite','custom text to send with new IdP invitations','text', NULL),
('fed:desired_skin','UI skin to use - if not exist, fall back to default','string',NULL),
('fed:include_logo_installers','whether or not the fed logo should be visible in installers','boolean', NULL),
('fed:silverbullet','enable Silver Bullet in this federation','boolean',NULL),
('fed:silverbullet-noterm','to tell us we should not terminate EAP for this federation silverbullet','boolean',NULL),
('fed:silverbullet-maxusers','maximum number of users per silverbullet profile','integer',NULL),
('hiddenprofile:tou_accepted','were the terms of use accepted?','boolean',NULL),
('profile:customsuffix','The filename suffix to use for the generated installers','string','ML');

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

ALTER TABLE `profile` ADD COLUMN `status_dns` int(11) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `status_cert` int(11) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `status_reachability` int(11) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `status_TLS` int(11) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `last_status_check` timestamp NULL DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `checkuser_outer` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `profile` ADD COLUMN `checkuser_value` varchar(128) DEFAULT NULL;
ALTER TABLE `profile` ADD COLUMN `verify_userinput_suffix` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `profile` ADD COLUMN `hint_userinput_suffix` int(1) NOT NULL DEFAULT '0';

ALTER TABLE `user_options` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `federation_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `institution_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;
ALTER TABLE `profile_option` ADD COLUMN `option_lang` varchar(8) DEFAULT NULL;

ALTER TABLE `downloads` ADD COLUMN `downloads_silverbullet` int(11) NOT NULL DEFAULT '0';
ALTER TABLE `downloads` ADD `eap_type` int(4) NULL DEFAULT NULL;

ALTER TABLE `user_options` DROP KEY `rowindex`, CHANGE COLUMN `id` `row` int primary key auto_increment, ADD KEY `rowindex` (`row`);

ALTER TABLE ownership DROP KEY `pair`;
ALTER TABLE ownership CHANGE COLUMN `user_id` `user_id` VARCHAR(2048) NOT NULL;

UPDATE institution SET country = UPPER(country);

CREATE VIEW `v_active_inst` AS select distinct `profile`.`inst_id` AS `inst_id` from `profile` where (`profile`.`showtime` = 1);
