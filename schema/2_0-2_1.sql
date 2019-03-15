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
 * Created: 08.03.2019
 */

ALTER TABLE `institution` ADD COLUMN `type` enum('IdP','SP','IdPSP') NOT NULL DEFAULT 'IdPSP';

CREATE TABLE `deployment` (
  `deployment_id` int(11) NOT NULL AUTO_INCREMENT,
  `inst_id` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(2) NOT NULL DEFAULT '0',
  `port_instance_1` int(11) NOT NULL DEFAULT 1812,
  `port_instance_2` int(11) NOT NULL DEFAULT 1812,
  `secret` varchar(16) DEFAULT NULL,
  `radius_instance_1` varchar(32) DEFAULT NULL,
  `radius_instance_2` varchar(32) DEFAULT NULL,
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
  `pool` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  PRIMARY KEY (`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO profile_option_dict (name, description,type,flag) VALUES ('managedsp:vlan','VLAN tag to add if Managed IdP user logs into hotspot of organisation','integer',NULL),
('managedsp:realmforvlan','a realm which should get this VLAN tag, in addition to the Managed IdP ones (those are handled ex officio','string',NULL);
