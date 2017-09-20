/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
DROP TABLE IF EXISTS `eap_method`;
DROP TABLE IF EXISTS `profile_option`;
DROP TABLE IF EXISTS `profile`;
DROP TABLE IF EXISTS `ownership`;
DROP TABLE IF EXISTS `invitations`;
DROP TABLE IF EXISTS `institution_option`;
DROP TABLE IF EXISTS `institution`;
DROP TABLE IF EXISTS `downloads`;
DROP TABLE IF EXISTS `user_options`;
DROP TABLE IF EXISTS `supported_eap`;
DROP TABLE IF EXISTS `federation_option`;
DROP TABLE IF EXISTS `federation`;
DROP TABLE IF EXISTS `profile_option_dict`;

CREATE TABLE `federation` (
  `federation_id` varchar(16) NOT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`federation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `institution` (
  `inst_id` int(11) NOT NULL AUTO_INCREMENT,
  `country` char(100) DEFAULT NULL,
  `external_db_id` varchar(64) DEFAULT NULL,
  `external_db_syncstate` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`inst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `profile_option_dict` (
  `name` char(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `flag` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `federation_option` (
  `federation_id` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  `option_name` varchar(32) DEFAULT NULL,
  `option_lang` varchar(8) DEFAULT NULL,
  `option_value` longblob,
  `row` int(11) NOT NULL AUTO_INCREMENT,
  KEY `option_name` (`option_name`),
  KEY `rowindex` (`row`),
  CONSTRAINT `federation_option_ibfk_1` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `institution_option` (
  `institution_id` int(11) NOT NULL DEFAULT '0',
  `option_name` varchar(32) DEFAULT NULL,
  `option_lang` varchar(8) DEFAULT NULL,
  `option_value` longblob,
  `row` int(11) NOT NULL AUTO_INCREMENT,
  KEY `option_name` (`option_name`),
  KEY `rowindex` (`row`),
  CONSTRAINT `institution_option_ibfk_1` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `external_db_uniquehandle` varchar(64) DEFAULT NULL,
  `country` varchar(16) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `invite_token` varchar(80) NOT NULL,
  `invite_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `cat_institution_id` varchar(64) DEFAULT NULL,
  `invite_issuer_level` varchar(16) NOT NULL DEFAULT 'LEGACY',
  `invite_dest_mail` varchar(128) NOT NULL DEFAULT 'LEGACY',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ownership` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(2048) NOT NULL,
  `institution_id` int(11) NOT NULL,
  `blesslevel` varchar(16) NOT NULL DEFAULT 'FED',
  `orig_mail` varchar(128) NOT NULL DEFAULT 'LEGACY-NO-MAIL-KNOWN',
  PRIMARY KEY (`id`),
  KEY `institution_id` (`institution_id`),
  CONSTRAINT `ownership_ibfk_1` FOREIGN KEY (`institution_id`) REFERENCES `institution` (`inst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `profile` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL DEFAULT '0',
  `realm` varchar(255) DEFAULT NULL,
  `use_anon_outer` int(1) NOT NULL DEFAULT '1',
  `checkuser_outer` int(1) NOT NULL DEFAULT '1',
  `checkuser_value` varchar(128) DEFAULT NULL,
  `verify_userinput_suffix` int(1) NOT NULL DEFAULT '1',
  `hint_userinput_suffix` int(1) NOT NULL DEFAULT '1',
  `showtime` tinyint(1) DEFAULT '1',
  `sufficient_config` tinyint(1) NULL DEFAULT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status_dns` int(11) DEFAULT NULL,
  `status_cert` int(11) DEFAULT NULL,
  `status_reachability` int(11) DEFAULT NULL,
  `status_TLS` int(11) DEFAULT NULL,
  `last_status_check` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `profile_option` (
  `profile_id` int(11) NOT NULL DEFAULT '0',
  `eap_method_id` int(11) DEFAULT '0',
  `device_id` varchar(32) DEFAULT NULL,
  `option_name` varchar(32) DEFAULT NULL,
  `option_lang` varchar(8) DEFAULT NULL,
  `option_value` longblob,
  `row` int(11) NOT NULL AUTO_INCREMENT,
  KEY `option_name` (`option_name`),
  KEY `rowindex` (`row`),
  CONSTRAINT `profile_option_ibfk_1` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `supported_eap` (
  `supported_eap_id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL DEFAULT '0',
  `eap_method_id` int(11) NOT NULL DEFAULT '0',
  `preference` int(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`supported_eap_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `downloads` (
  `profile_id` int(11) NOT NULL,
  `device_id` varchar(32) NOT NULL,
  `downloads_admin` int(11) NOT NULL DEFAULT '0',
  `downloads_user` int(11) NOT NULL DEFAULT '0',
  `downloads_silverbullet` int(11) NOT NULL DEFAULT '0',
  `download_path` varchar(1024) DEFAULT NULL,
  `installer_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lang` char(4) NOT NULL,
  `mime` varchar(50) DEFAULT NULL,
  `eap_type` int(4),
  UNIQUE KEY `profile_device_lang` (`device_id`,`profile_id`,`lang`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `user_options` ( 
  `row` int(11) NOT NULL AUTO_INCREMENT, 
  `user_id` varchar(2048) NOT NULL, 
  `option_name` varchar(32) DEFAULT NULL, 
  `option_lang` varchar(8) DEFAULT NULL,
  `option_value` longblob,
  KEY `rowindex` (`row`),
  KEY `foreign_key_options` (`option_name`), 
  CONSTRAINT `foreign_key_options` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE VIEW `v_active_inst` AS select distinct `profile`.`inst_id` AS `inst_id` from `profile` where (`profile`.`showtime` = 1);

INSERT INTO `profile_option_dict` VALUES 
('device-specific:customtext','extra text to be displayed to the user when downloading an installer for this device','text','ML'),
('device-specific:redirect','URL to redirect the user to when he selects this device','string','ML'),
('eap-specific:customtext','extra text to be displayed to the user when downloading an installer for this EAP type','text','ML'),
('eap-specific:tls_use_other_id','use different user name','boolean',NULL),
('eap:ca_file','certificate of the CA signing the RADIUS server key','file',NULL),
('eap:server_name','name of authorized RADIUS server','string',NULL),
('general:geo_coordinates','geographical coordinates of the institution or a campus','coordinates',NULL),
('general:instname','name of the institution in multiple languages','string','ML'),
('general:logo_file','file data containing institution logo','file',NULL),
('media:SSID','additional SSID to configure, WPA2/AES only','string',NULL),
('media:SSID_with_legacy','additional SSID to configure, WPA2/AES and WPA/TKIP','string',NULL),
('media:wired','should wired interfaces be configured','boolean',NULL),
('media:remove_SSID','SSIDs to remove during installation','string',NULL),
('media:consortium_OI','Hotspot 2.0 consortium OIs to configure','string',NULL),
('profile:name','The user-friendly name of this profile, in multiple languages','string','ML'),
('profile:customsuffix','The filename suffix to use for the generated installers','string','ML'),
('profile:description','extra text to describe the profile to end-users','text','ML'),
('profile:production','Profile is ready and can be displayed on download page','boolean',NULL),
('hiddenprofile:tou_accepted','were the terms of use accepted?','boolean',NULL),
('support:email','email for users to contact for local instructions','string','ML'),
('support:info_file','consent file displayed to the users','file','ML'),
('support:phone','telephone number for users to contact for local instructions','string','ML'),
('support:url','URL where the user will find local instructions','string','ML'),
('user:email','email address of the user (from IdP)','string',NULL),
('user:fedadmin','contains federation names for which this user is an admin','string', NULL),
('user:realname','a friendly display name of the user','string', NULL),
('fed:realname','a friendly display name of the NRO/federation','string', 'ML'),
('fed:logo_file','logo of the NRO/federation','file', NULL),
('fed:css_file','custom CSS to be applied on any skin','file',NULL),
('fed:custominvite','custom text to send with new IdP invitations','text', NULL),
('fed:desired_skin','UI skin to use - if not exist, fall back to default','string',NULL),
('fed:include_logo_installers','whether or not the fed logo should be visible in installers','boolean', NULL),
('fed:silverbullet','enable Silver Bullet in this federation','boolean',NULL),
('fed:silverbullet-noterm','to tell us we should not terminate EAP for this federation silverbullet','boolean',NULL),
('fed:silverbullet-maxusers','maximum number of users per silverbullet profile','integer',NULL);
