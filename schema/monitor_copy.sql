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
 * Author:  twoln
 */

DROP TABLE IF EXISTS `view_active_SP_location_eduroamdb`;
CREATE TABLE `view_active_SP_location_eduroamdb` (
  `country` char(3) DEFAULT NULL,
  `country_eng` char(128) DEFAULT NULL,
  `institutionid` bigint DEFAULT NULL,
  `inst_name` varchar(2048) DEFAULT NULL,
  `sp_location` blob,
  `sp_location_contact` varchar(2048) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

DROP TABLE IF EXISTS `view_active_institution`;
CREATE TABLE `view_active_institution` (
  `ROid` char(5) DEFAULT NULL,
  `instid` char(50) DEFAULT NULL,
  `inst_realm` varchar(341) DEFAULT NULL,
  `country` char(5) DEFAULT NULL,
  `name` varchar(512) DEFAULT NULL,
  `contact` longblob,
  `type` tinyint DEFAULT '1',
  `last_change` timestamp NULL DEFAULT '0000-00-00 00:00:00',
  `inst_realm_sorted` varchar(341) DEFAULT NULL,
  `cat_sync_id` int DEFAULT NULL,
  `instid1` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `view_admin`;
CREATE TABLE `view_admin` (
  `id` int unsigned DEFAULT NULL,
  `eptid` char(255) CHARACTER SET utf8mb3 DEFAULT NULL,
  `email` char(255) CHARACTER SET utf8mb3 DEFAULT NULL,
  `common_name` char(255) CHARACTER SET utf8mb3 DEFAULT NULL,
  `id_role` int DEFAULT NULL,
  `role` varchar(255) CHARACTER SET utf8mb3 DEFAULT NULL,
  `id_obj` int DEFAULT NULL,
  `realm` char(255) CHARACTER SET utf8mb3 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `view_institution_admins`;
CREATE TABLE `view_institution_admins` (
  `name` varchar(128) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `phone` varchar(128) DEFAULT NULL,
  `inst_realm` varchar(128) DEFAULT NULL,
  `instid` char(50) DEFAULT NULL,
  `ROid` char(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `view_tls_inst`;
CREATE TABLE `view_tls_inst` (
  `ROid` char(5) CHARACTER SET utf8mb3 DEFAULT NULL,
  `country` char(5) CHARACTER SET utf8mb3 DEFAULT NULL,
  `instid` char(50) CHARACTER SET utf8mb3 DEFAULT NULL,
  `stage` tinyint DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `inst_name` varchar(2048) CHARACTER SET utf8mb3 DEFAULT NULL,
  `servers` text CHARACTER SET utf8mb3,
  `contacts` text CHARACTER SET utf8mb3,
  `ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `view_tls_ro`;
CREATE TABLE `view_tls_ro` (
  `ROid` char(5) DEFAULT NULL,
  `country` char(5) DEFAULT NULL,
  `stage` tinyint DEFAULT NULL,
  `servers` text,
  `contacts` text,
  `ts` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

