-- MySQL dump 10.13  Distrib 8.0.36, for Linux (x86_64)
--
-- Host: localhost    Database: monitor_copy
-- ------------------------------------------------------
-- Server version	8.0.36-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `view_active_SP_location_eduroamdb`
--

DROP TABLE IF EXISTS `view_active_SP_location_eduroamdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_active_SP_location_eduroamdb` (
  `country` char(3) DEFAULT NULL,
  `country_eng` char(128) DEFAULT NULL,
  `institutionid` bigint DEFAULT NULL,
  `inst_name` varchar(2048) DEFAULT NULL,
  `sp_location` blob,
  `sp_location_contact` varchar(2048) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `view_active_idp_institution`
--

DROP TABLE IF EXISTS `view_active_idp_institution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_active_idp_institution` (
  `id_institution` bigint unsigned DEFAULT NULL,
  `inst_realm` varchar(256) DEFAULT NULL,
  `country` varchar(128) DEFAULT NULL,
  `name` varchar(512) DEFAULT NULL,
  `contact` blob
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `view_active_institution`
--

DROP TABLE IF EXISTS `view_active_institution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_active_institution` (
  `id_institution` bigint unsigned DEFAULT NULL,
  `ROid` varchar(7) DEFAULT NULL,
  `inst_realm` char(255) DEFAULT NULL,
  `country` char(5) DEFAULT NULL,
  `name` varchar(512) DEFAULT NULL,
  `contact` blob,
  `type` enum('3','2','1') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `view_admin`
--

DROP TABLE IF EXISTS `view_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_admin` (
  `id` int unsigned DEFAULT NULL,
  `eptid` char(255) DEFAULT NULL,
  `email` char(255) DEFAULT NULL,
  `common_name` char(255) DEFAULT NULL,
  `id_role` int DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `id_obj` int DEFAULT NULL,
  `realm` char(255) DEFAULT NULL,
  KEY `eptid` (`eptid`),
  KEY `role` (`role`),
  KEY `realm` (`realm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `view_country_eduroamdb`
--

DROP TABLE IF EXISTS `view_country_eduroamdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `view_country_eduroamdb` (
  `country` char(3) DEFAULT NULL,
  `country_eng` char(128) DEFAULT NULL,
  `map_group` enum('APAN','SOAM','NOAM','Africa','Europe') DEFAULT 'Europe'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-06-07 12:47:52
