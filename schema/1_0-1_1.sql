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
ALTER TABLE `profile` ADD `sufficient_config` tinyint(1) NULL DEFAULT NULL;
ALTER table `downloads` add `mime` varchar(50) NULL DEFAULT NULL;
DROP TABLE `eap_method`;
INSERT INTO `profile_option_dict` VALUES 
('media:SSID','additional SSID to configure, WPA2/AES only','string',NULL),
('media:SSID_with_legacy','additional SSID to configure, WPA2/AES and WPA/TKIP','string',NULL),
('media:wired','should wired interfaces be configured','boolean',NULL),
('media:remove_SSID','SSIDs to remove during installation','string',NULL),
('media:consortium_OI','Hotspot 2.0 consortium OIs to configure','string',NULL);

UPDATE institution_option SET option_name = "media:SSID" WHERE option_name = "general:SSID";
UPDATE institution_option SET option_name = "media:SSID_with_legacy" WHERE option_name = "general:SSID_with_legacy";

DELETE FROM `profile_option_dict` WHERE
name = "general:SSID" OR
name = "general:SSID_with_legacy";

