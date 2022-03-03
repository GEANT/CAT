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
DROP TABLE IF EXISTS `silverbullet_certificate`;
DROP TABLE IF EXISTS `silverbullet_user`;
DROP TABLE IF EXISTS `silverbullet_invitation`;

CREATE TABLE `federation` (
  `federation_id` varchar(16) NOT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`federation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `institution` (
  `inst_id` int(11) NOT NULL AUTO_INCREMENT,
  `country` char(100) DEFAULT NULL,
  `type` enum('IdP','SP','IdPSP') NOT NULL DEFAULT 'IdPSP',
  `external_db_id` varchar(64) DEFAULT NULL,
  `external_db_syncstate` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`inst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `profile_option_dict` (
  `name` char(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `flag` varchar(255) DEFAULT NULL,
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

CREATE TABLE `federation_servercerts` (
  `federation_id` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  `ca_name` varchar(16),
  `request_serial` int(11) NOT NULL,
  `distinguished_name` varchar(255) NOT NULL,
  `status` enum('REQUESTED','ISSUED','REVOKED'),
  `expiry` date,
  `certificate` longblob,
  `revocation_pin` varchar(16),
  UNIQUE KEY `cert_id` (`ca_name`,`request_serial`)
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
  `invite_fortype` enum('IdP','SP','IdPSP') NOT NULL DEFAULT 'IdPSP',
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
  `openroaming` int(2) NOT NULL DEFAULT 4,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `deployment` (
  `deployment_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(2) NOT NULL DEFAULT '0',
  `port_instance_1` int(11) NOT NULL DEFAULT 1812,
  `port_instance_2` int(11) NOT NULL DEFAULT 1812,
  `secret` varchar(16) DEFAULT NULL,
  `radius_instance_1` varchar(64) DEFAULT NULL,
  `radius_instance_2` varchar(64) DEFAULT NULL,
  `radius_status_1` tinyint(1) DEFAULT '0',
  `radius_status_2` tinyint(1) DEFAULT '0',
  `consortium` varchar(64) DEFAULT NULL,
  `last_change` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`deployment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `deployment_option` (
  `deployment_id` int(11) NOT NULL DEFAULT '0',
  `option_name` varchar(32) DEFAULT NULL,
  `option_lang` varchar(8) DEFAULT NULL,
  `option_value` longblob,
  `row` int(11) NOT NULL AUTO_INCREMENT,
  KEY `option_name` (`option_name`),
  KEY `rowindex` (`row`),
  CONSTRAINT `deployment_option_ibfk_1` FOREIGN KEY (`option_name`) REFERENCES `profile_option_dict` (`name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `managed_sp_servers` (
  `server_id` varchar(64) NOT NULL,
  `mgmt_hostname` varchar(64) NOT NULL,
  `radius_ip4` varchar(64) DEFAULT NULL,
  `radius_ip6` varchar(64) DEFAULT NULL,
  `location_lon` double NOT NULL,
  `location_lat` double NOT NULL,
  `consortium` varchar(64) NOT NULL DEFAULT 'eduroam',
  `pool` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  PRIMARY KEY (`server_id`)
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
  `installer_time` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  `lang` char(4) NOT NULL,
  `mime` varchar(50) DEFAULT NULL,
  `eap_type` int(4),
  `openroaming` int(1),
  UNIQUE KEY `profile_device_lang` (`device_id`,`profile_id`,`lang`, `openroaming`)
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
('device-specific:geantlink','Use GEANTlink TTLS supplicant for W8', 'boolean',NULL),
('eap-specific:tls_use_other_id','use different user name','boolean',NULL),
('eap:ca_file','certificate of the CA signing the RADIUS server key','file',NULL),
('eap:server_name','name of authorized RADIUS server','string',NULL),
('general:geo_coordinates','geographical coordinates of the institution or a campus','coordinates',NULL),
('general:instname','name of the institution in multiple languages','string','ML'),
('general:instshortname','short name of the institution (acronym etc) in multiple languages','string','ML'),
('general:logo_file','file data containing institution logo','file',NULL),
('media:SSID','additional SSID to configure, WPA2/AES only','string',NULL),
('media:wired','should wired interfaces be configured','boolean',NULL),
('media:remove_SSID','SSIDs to remove during installation','string',NULL),
('media:consortium_OI','Hotspot 2.0 consortium OIs to configure','string',NULL),
('media:force_proxy','URL of a mandatory content filter proxy','string',NULL),
('media:openroaming','enum switch to select desired OpenRoaming integration','enum_openroaming','VALUES:ask,always,ask-preagreed,always-preagreed'),
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
('fed:url', 'URL to the homepage of a federation', 'string', 'ML'),
('fed:logo_file','logo of the NRO/federation','file', NULL),
('fed:css_file','custom CSS to be applied on any skin','file',NULL),
('fed:custominvite','custom text to send with new IdP invitations','text', NULL),
('fed:desired_skin','UI skin to use - if not exist, fall back to default','string',NULL),
('fed:include_logo_installers','whether or not the fed logo should be visible in installers','boolean', NULL),
('fed:silverbullet','enable Silver Bullet in this federation','boolean',NULL),
('fed:silverbullet-noterm','to tell us we should not terminate EAP for this federation silverbullet','boolean',NULL),
('fed:silverbullet-maxusers','maximum number of users per silverbullet profile','integer',NULL),
('fed:minted_ca_file','set of default CAs to add to new IdPs on signup','file',NULL),
('fed:openroaming','Allow IdP OpenRoaming Opt-In','boolean',NULL),
('fed:openroaming_customtarget','custom NAPTR discovery target','string',NULL),
('managedsp:vlan','VLAN tag to add if Managed IdP user logs into hotspot of organisation','integer',NULL),
('managedsp:realmforvlan','a realm which should get this VLAN tag, in addition to the Managed IdP ones (those are handled ex officio','string',NULL),
('managedsp:operatorname','Operator-Name attribute to be added to requests','string',NULL),
('hiddenmanagedsp:tou_accepted','were the terms of use accepted?','boolean',NULL);

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
  `extrainfo` longblob DEFAULT NULL,
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

CREATE TABLE `diagnosticrun` (
  `test_id` VARCHAR(128) NOT NULL,
  `last_touched` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `realm` VARCHAR(128) NOT NULL,
  `visited_flr` VARCHAR(10) DEFAULT NULL, 
  `visited_hotspot` VARCHAR(128) DEFAULT NULL,
  `suspects` LONGBLOB DEFAULT NULL,
  `evidence` LONGBLOB DEFAULT NULL,
  `questionsasked` LONGBLOB DEFAULT NULL,
  `concluded` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`test_id`))
ENGINE = InnoDB CHARSET=utf8;

CREATE TABLE `activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country` varchar(16) DEFAULT NULL,
  `realm` varchar(255) DEFAULT NULL,
  `operatorname` varchar(255) DEFAULT NULL,
  `mac` varchar(17) DEFAULT NULL,
  `cui` varchar(255) DEFAULT NULL,
  `result` varchar(4) DEFAULT NULL,
  `activity_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
