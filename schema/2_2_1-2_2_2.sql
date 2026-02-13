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
 
 * Created: 29 dec 2025
 */

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
ALTER TABLE `managed_sp_servers` ADD COLUMN (`port_range` varchar(12) DEFAULT NULL,
  `max_clients` int (11) DEFAULT NULL);