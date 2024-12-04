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


ALTER TABLE profile ADD COLUMN `preference` int DEFAULT '1000';

CREATE TABLE `downloads_history` (
  `profile_id` int NOT NULL,
  `device_id` varchar(32) NOT NULL,
  `downloads_admin` int NOT NULL DEFAULT '0',
  `downloads_user` int NOT NULL DEFAULT '0',
  `downloads_silverbullet` int NOT NULL DEFAULT '0',
  `openroaming` int DEFAULT '0',
  `stat_date` date DEFAULT NULL,
  UNIQUE KEY `profile_device` (`profile_id`,`device_id`,`stat_date`),
  KEY `profile_id` (`profile_id`),
  KEY `device_id` (`device_id`),
  KEY `stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
