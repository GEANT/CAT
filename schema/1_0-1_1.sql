/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
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

