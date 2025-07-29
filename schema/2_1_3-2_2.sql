/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2) and from the Horizon Europe programme 
 * under Grant Agreement No: 101100680  (GN5-1).
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
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * Created: 20 Nov 2024
 */

CREATE TABLE IF NOT EXISTS `edugain` (
`country` varchar(16) DEFAULT NULL,
`ROid` char(5) DEFAULT NULL,
`reg_auth` varchar(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS `activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country` varchar(16) DEFAULT NULL,
  `realm` varchar(255) DEFAULT NULL,
  `operatorname` varchar(255) DEFAULT NULL,
  `mac` varchar(17) DEFAULT NULL,
  `cui` varchar(255) DEFAULT NULL,
  `ap_id` varchar(1024) DEFAULT NULL,
  `result` varchar(4) DEFAULT NULL,
  `activity_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `operatorname` (`operatorname`),
  KEY `activity_time` (`activity_time`),
  KEY `result` (`result`),
  KEY `mac` (`mac`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

ALTER TABLE `activity` ADD COLUMN (
  `prot` varchar(64) DEFAULT NULL,
  `owner` varchar(20) DEFAULT NULL,
  `outer_user` varchar(128) DEFAULT NULL);

ALTER TABLE `activity` ADD KEY `outer_user` (`outer_user`), ADD KEY `owner` (`owner`);

ALTER TABLE `deployment` ADD COLUMN (`radsec_priv` blob DEFAULT NULL,
  `radsec_cert` blob DEFAULT NULL,
  `radsec_cert_serial_number` blob DEFAULT NULL,
  `pskkey` varchar(128) DEFAULT NULL);

ALTER TABLE `deployment` CHANGE COLUMN radius_instance_1 radius_instance_1 varchar(64);
ALTER TABLE `deployment` CHANGE COLUMN radius_instance_2 radius_instance_2 varchar(64);
ALTER TABLE `deployment` CHANGE COLUMN secret secret varchar(64);
ALTER TABLE `managed_sp_servers` ADD COLUMN (`server_token` varchar(64) DEFAULT NULL,
  `server_secret` varchar(64) DEFAULT NULL,
  `server_iv` varchar(64) DEFAULT NULL);

INSERT INTO `profile_option_dict` VALUES ('fed:autoregister-synced', 'allow admins listed in eduroam DB to become admins for synced CAT institutions', 'boolean', NULL);
INSERT INTO `profile_option_dict` VALUES ('fed:autoregister-new-inst', 'allow admins listed in eduroam DB to create new institutions', 'boolean', NULL);
INSERT INTO `profile_option_dict` VALUES ('fed:autoregister-entitlement', 'allow entitlement and scope based addition of admins to CAT institutions', 'boolean', NULL);
INSERT INTO `profile_option_dict` VALUES ('fed:entitlement-attr', 'the entitlement value used by this federation for self-registration, will override the default geant:eduroam:inst:admin', 'string', NULL);
INSERT INTO `profile_option_dict` (name, description, type, flag) VALUES('general:instaltname','alternative name of the institution to be used in Disco search as keyword','string', NULL);
INSERT INTO `profile_option_dict` (name, description,type,flag) values ('managedsp:guest_vlan','VLAN tag to add for guest users','integer',NULL);
DELETE FROM `profile_option_dict` WHERE name = 'fed:desired_skin';
DELETE FROM `profile_option_dict` WHERE name = 'fed:css_file';