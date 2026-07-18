-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mmswarehousesystem
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `co_cycles`
--

DROP TABLE IF EXISTS `co_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `co_cycles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `co_cycles`
--

LOCK TABLES `co_cycles` WRITE;
/*!40000 ALTER TABLE `co_cycles` DISABLE KEYS */;
/*!40000 ALTER TABLE `co_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_closing_items`
--

DROP TABLE IF EXISTS `daily_closing_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_closing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_qty_ctn` int(11) DEFAULT 0,
  `physical_qty_ctn` int(11) DEFAULT 0,
  `variance_ctn` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `daily_closing_items_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `daily_closing_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_closing_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_closing_items`
--

LOCK TABLES `daily_closing_items` WRITE;
/*!40000 ALTER TABLE `daily_closing_items` DISABLE KEYS */;
INSERT INTO `daily_closing_items` VALUES (1,1,349,0,34,34),(2,1,348,0,50,50),(3,1,347,0,0,0),(4,1,350,0,0,0),(5,1,345,0,0,0),(6,1,344,0,0,0),(7,1,346,0,0,0),(8,1,343,0,0,0),(9,1,409,0,0,0),(10,1,422,0,0,0),(11,1,304,0,0,0),(12,1,305,0,0,0),(13,1,307,0,0,0),(14,1,293,62,62,0),(15,1,291,144,144,0),(16,1,297,36,36,0),(17,1,308,0,0,0),(18,1,301,0,0,0),(19,1,290,264,264,0),(20,1,302,0,0,0),(21,1,303,0,0,0),(22,1,306,1,1,0),(23,1,299,0,0,0),(24,1,298,0,0,0),(25,1,310,144,144,0),(26,1,309,3456,3456,0),(27,1,311,7320,7320,0),(28,1,294,216,216,0),(29,1,295,0,0,0),(30,1,296,72,72,0),(31,1,413,0,0,0),(32,1,406,432,432,0),(33,1,383,0,0,0),(34,1,396,0,0,0),(35,1,397,0,0,0),(36,1,395,0,0,0),(37,1,336,36,36,0),(38,1,362,0,0,0),(39,1,359,0,0,0),(40,1,356,0,0,0),(41,1,341,0,0,0),(42,1,352,0,0,0),(43,1,417,0,0,0),(44,1,360,0,0,0),(45,1,390,0,0,0),(46,1,342,0,0,0),(47,1,357,0,0,0),(48,1,418,0,0,0),(49,1,351,0,0,0),(50,1,416,0,0,0),(51,1,338,0,0,0),(52,1,339,0,0,0),(53,1,340,0,0,0),(54,1,361,0,0,0),(55,1,366,0,0,0),(56,1,365,0,0,0),(57,1,358,0,0,0),(58,1,363,0,0,0),(59,1,354,0,0,0),(60,1,353,0,0,0),(61,1,355,0,0,0),(62,1,391,0,0,0),(63,1,393,0,0,0),(64,1,407,0,0,0),(65,1,408,0,0,0),(66,1,320,0,0,0),(67,1,321,0,0,0),(68,1,313,150,150,0),(69,1,314,0,0,0),(70,1,315,0,0,0),(71,1,312,75,75,0),(72,1,319,0,0,0),(73,1,323,0,0,0),(74,1,322,0,0,0),(75,1,411,0,0,0),(76,1,410,0,0,0),(77,1,412,0,0,0),(78,1,325,0,0,0),(79,1,324,0,0,0),(80,1,318,75,75,0),(81,1,317,0,0,0),(82,1,316,0,0,0),(83,1,326,0,0,0),(84,1,392,0,0,0),(85,1,394,0,0,0),(86,1,384,0,0,0),(87,1,415,0,0,0),(88,1,414,0,0,0),(89,1,280,0,0,0),(90,1,286,0,0,0),(91,1,278,0,0,0),(92,1,277,0,0,0),(93,1,281,0,0,0),(94,1,279,0,0,0),(95,1,276,0,0,0),(96,1,285,0,0,0),(97,1,284,0,0,0),(98,1,283,0,0,0),(99,1,282,0,0,0),(100,1,288,0,0,0),(101,1,287,0,0,0),(102,1,289,6144,6144,0),(103,1,337,0,0,0),(104,1,333,0,0,0),(105,1,335,0,0,0),(106,1,421,0,0,0),(107,1,334,0,0,0),(108,1,420,0,0,0),(109,1,327,0,0,0),(110,1,329,0,0,0),(111,1,331,0,0,0),(112,1,328,0,0,0),(113,1,330,0,0,0),(114,1,332,0,0,0),(115,1,371,0,0,0),(116,1,375,0,0,0),(117,1,374,0,0,0),(118,1,376,0,0,0),(119,1,372,0,0,0),(120,1,377,0,0,0),(121,1,373,0,0,0),(122,1,386,0,0,0),(123,1,387,0,0,0),(124,1,388,0,0,0),(125,1,385,0,0,0),(126,1,389,0,0,0),(127,1,382,0,0,0),(128,1,380,0,0,0),(129,1,379,0,0,0),(130,1,381,0,0,0),(131,1,378,0,0,0),(132,1,369,0,0,0),(133,1,370,0,0,0),(134,1,367,0,0,0),(135,1,368,0,0,0),(136,1,419,0,0,0),(137,1,398,0,0,0),(138,1,400,0,0,0),(139,1,399,0,0,0),(140,1,364,0,0,0),(141,1,401,0,0,0),(142,1,403,0,0,0),(143,1,404,0,0,0),(144,1,405,0,0,0),(145,1,402,0,0,0),(146,1,300,0,0,0);
/*!40000 ALTER TABLE `daily_closing_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_closing_reports`
--

DROP TABLE IF EXISTS `daily_closing_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_closing_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_date` date NOT NULL,
  `checked_by` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `audit_date` (`audit_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_closing_reports`
--

LOCK TABLES `daily_closing_reports` WRITE;
/*!40000 ALTER TABLE `daily_closing_reports` DISABLE KEYS */;
INSERT INTO `daily_closing_reports` VALUES (1,'2026-07-09','FADIAH','Completed','2026-07-09 09:31:37');
/*!40000 ALTER TABLE `daily_closing_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_reconciliation`
--

DROP TABLE IF EXISTS `daily_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_reconciliation` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `category` varchar(20) DEFAULT NULL,
  `system_qty_cartons` int(11) DEFAULT 0,
  `invoice_qty_cartons` int(11) DEFAULT 0,
  `invoice_numbers` text DEFAULT NULL,
  `variance` int(11) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `verified_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_recon` (`date`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_reconciliation`
--

LOCK TABLES `daily_reconciliation` WRITE;
/*!40000 ALTER TABLE `daily_reconciliation` DISABLE KEYS */;
INSERT INTO `daily_reconciliation` VALUES (0,'2026-04-19','Commercial',0,0,'',0,'',NULL,'2026-04-19 03:50:54');
/*!40000 ALTER TABLE `daily_reconciliation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `damage_logs`
--

DROP TABLE IF EXISTS `damage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `damage_logs` (
  `id` int(11) NOT NULL,
  `report_date` datetime DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty_damaged` int(11) DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `stage` enum('Receiving','Storage','Loading','Return') DEFAULT NULL,
  `reported_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damage_logs`
--

LOCK TABLES `damage_logs` WRITE;
/*!40000 ALTER TABLE `damage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `damage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deliveries_pss`
--

DROP TABLE IF EXISTS `deliveries_pss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deliveries_pss` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `do_number` varchar(20) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `hd_id` int(11) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL,
  `co_cycle_id` int(11) DEFAULT NULL,
  `pallets_out_red` int(11) DEFAULT 0,
  `pallets_out_green` int(11) DEFAULT 0,
  `pallets_out_orange` int(11) DEFAULT 0,
  `status` enum('Draft','Loaded','Delivered','Verified') DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deliveries_pss`
--

LOCK TABLES `deliveries_pss` WRITE;
/*!40000 ALTER TABLE `deliveries_pss` DISABLE KEYS */;
/*!40000 ALTER TABLE `deliveries_pss` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_items_pss`
--

DROP TABLE IF EXISTS `delivery_items_pss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_items_pss` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) DEFAULT NULL,
  `inventory_batch_id` int(11) DEFAULT NULL,
  `qty_cartons` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_items_pss`
--

LOCK TABLES `delivery_items_pss` WRITE;
/*!40000 ALTER TABLE `delivery_items_pss` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_items_pss` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hds`
--

DROP TABLE IF EXISTS `hds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `short_code` varchar(10) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hds`
--

LOCK TABLES `hds` WRITE;
/*!40000 ALTER TABLE `hds` DISABLE KEYS */;
INSERT INTO `hds` VALUES (2,'MOHD HAFIZI TALIB','FIZI',NULL,'Active'),(3,'WALI KHAN','WALI',NULL,'Active'),(5,'NOIDORA ABDULLAH','DORA',NULL,'Active'),(7,'AHMAD TARMIZI MOHAMED','MMS','01120621990','Active'),(8,'SHARIFAH MUNIRAH','SYA',NULL,'Active'),(9,'SITI NOOR IDAYU','AYU',NULL,'Active');
/*!40000 ALTER TABLE `hds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_batches`
--

DROP TABLE IF EXISTS `import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_batches` (
  `id` int(11) NOT NULL,
  `contract_name` varchar(255) DEFAULT NULL,
  `last_delivery_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_batches`
--

LOCK TABLES `import_batches` WRITE;
/*!40000 ALTER TABLE `import_batches` DISABLE KEYS */;
INSERT INTO `import_batches` VALUES (1,'Contract of October 2025','2025-11-05','2025-12-08 03:51:35');
/*!40000 ALTER TABLE `import_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_cos`
--

DROP TABLE IF EXISTS `import_cos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_cos` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `co_number` varchar(50) DEFAULT NULL,
  `bil_tp` int(11) DEFAULT NULL,
  `consumption_start` date DEFAULT NULL,
  `consumption_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_cos`
--

LOCK TABLES `import_cos` WRITE;
/*!40000 ALTER TABLE `import_cos` DISABLE KEYS */;
INSERT INTO `import_cos` VALUES (1,1,'CO8',25,'2025-11-11','2025-12-18'),(2,1,'CO1',19,'2026-01-11','2026-01-29');
/*!40000 ALTER TABLE `import_cos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_transactions`
--

DROP TABLE IF EXISTS `import_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_transactions` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `kod_sekolah` varchar(50) DEFAULT NULL,
  `bil_murid` int(11) DEFAULT NULL,
  `no_sap` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_transactions`
--

LOCK TABLES `import_transactions` WRITE;
/*!40000 ALTER TABLE `import_transactions` DISABLE KEYS */;
INSERT INTO `import_transactions` VALUES (1,1,'TBA0001',176,'BESUT001 (CO8/NOVEMBER/2025)'),(2,1,'TBA0002',123,'BESUT002 (CO8/NOVEMBER/2025)'),(3,1,'TBA0003',166,'BESUT003 (CO8/NOVEMBER/2025)'),(4,1,'TBA0004',269,'BESUT004 (CO8/NOVEMBER/2025)'),(5,1,'TBA0005',407,'BESUT005 (CO8/NOVEMBER/2025)'),(6,1,'TBA0006',293,'BESUT006 (CO8/NOVEMBER/2025)'),(7,1,'TBA0007',168,'BESUT007 (CO8/NOVEMBER/2025)'),(8,1,'TBA0008',163,'BESUT008 (CO8/NOVEMBER/2025)'),(9,1,'TBA0012',222,'BESUT009 (CO8/NOVEMBER/2025)'),(10,1,'TBA0013',290,'BESUT010 (CO8/NOVEMBER/2025)'),(11,1,'TBA0014',258,'BESUT011 (CO8/NOVEMBER/2025)'),(12,1,'TBA0015',156,'BESUT012 (CO8/NOVEMBER/2025)'),(13,1,'TBA0017',289,'BESUT013 (CO8/NOVEMBER/2025)'),(14,1,'TBA0018',372,'BESUT014 (CO8/NOVEMBER/2025)'),(15,1,'TBA0019',80,'BESUT015 (CO8/NOVEMBER/2025)'),(16,1,'TBA0022',307,'BESUT016 (CO8/NOVEMBER/2025)'),(17,1,'TBA0023',228,'BESUT017 (CO8/NOVEMBER/2025)'),(18,1,'TBA0024',270,'BESUT018 (CO8/NOVEMBER/2025)'),(19,1,'TBA0025',182,'BESUT019 (CO8/NOVEMBER/2025)'),(20,1,'TBA0026',156,'BESUT020 (CO8/NOVEMBER/2025)'),(21,1,'TBA0027',267,'BESUT021 (CO8/NOVEMBER/2025)'),(22,1,'TBA0028',141,'BESUT022 (CO8/NOVEMBER/2025)'),(23,1,'TBA0029',80,'BESUT023 (CO8/NOVEMBER/2025)'),(24,1,'TBA0030',241,'BESUT024 (CO8/NOVEMBER/2025)'),(25,1,'TBA0031',145,'BESUT025 (CO8/NOVEMBER/2025)'),(26,1,'TBA0032',100,'BESUT026 (CO8/NOVEMBER/2025)'),(27,1,'TBA0033',89,'BESUT027 (CO8/NOVEMBER/2025)'),(28,1,'TBA0043',256,'BESUT028 (CO8/NOVEMBER/2025)'),(29,1,'TBA0044',214,'BESUT029 (CO8/NOVEMBER/2025)'),(30,1,'TBA0045',229,'BESUT030 (CO8/NOVEMBER/2025)'),(31,1,'TBA0046',164,'BESUT031 (CO8/NOVEMBER/2025)'),(32,1,'TBA0048',211,'BESUT032 (CO8/NOVEMBER/2025)'),(33,1,'TBA0049',351,'BESUT033 (CO8/NOVEMBER/2025)'),(34,1,'TBA0050',261,'BESUT034 (CO8/NOVEMBER/2025)'),(35,1,'TBA0051',113,'BESUT035 (CO8/NOVEMBER/2025)'),(36,1,'TBA0052',100,'BESUT036 (CO8/NOVEMBER/2025)'),(37,1,'TBA0053',77,'BESUT037 (CO8/NOVEMBER/2025)'),(38,1,'TBA0054',213,'BESUT038 (CO8/NOVEMBER/2025)'),(39,1,'TBA0055',98,'BESUT039 (CO8/NOVEMBER/2025)'),(40,1,'TBA0067',88,'BESUT040 (CO8/NOVEMBER/2025)'),(41,1,'TBA0068',23,'BESUT041 (CO8/NOVEMBER/2025)'),(42,1,'TBA0069',287,'BESUT042 (CO8/NOVEMBER/2025)'),(43,1,'TBA0070',259,'BESUT043 (CO8/NOVEMBER/2025)'),(44,1,'TBA0072',304,'BESUT044 (CO8/NOVEMBER/2025)'),(45,1,'TBA0073',144,'BESUT045 (CO8/NOVEMBER/2025)'),(46,1,'TBA0074',289,'BESUT046 (CO8/NOVEMBER/2025)'),(47,1,'TBA0075',117,'BESUT047 (CO8/NOVEMBER/2025)'),(48,1,'TBA0076',329,'BESUT048 (CO8/NOVEMBER/2025)'),(49,1,'TBA0077',159,'BESUT049 (CO8/NOVEMBER/2025)'),(50,1,'TBA0078',165,'BESUT050 (CO8/NOVEMBER/2025)'),(51,1,'TBA0079',86,'BESUT051 (CO8/NOVEMBER/2025)'),(52,1,'TBA0080',196,'BESUT052 (CO8/NOVEMBER/2025)'),(53,1,'TBB0061',184,'BESUT053 (CO8/NOVEMBER/2025)'),(54,1,'TBC0063',43,'BESUT054 (CO8/NOVEMBER/2025)'),(55,1,'TBA7001',136,'KUALANERUS001 (CO8/NOVEMBER/2025)'),(56,1,'TBA7002',303,'KUALANERUS002 (CO8/NOVEMBER/2025)'),(57,1,'TBA7003',185,'KUALANERUS003 (CO8/NOVEMBER/2025)'),(58,1,'TBA7004',200,'KUALANERUS004 (CO8/NOVEMBER/2025)'),(59,1,'TBA7005',225,'KUALANERUS005 (CO8/NOVEMBER/2025)'),(60,1,'TBA7006',266,'KUALANERUS006 (CO8/NOVEMBER/2025)'),(61,1,'TBA7007',152,'KUALANERUS007 (CO8/NOVEMBER/2025)'),(62,1,'TBA7008',267,'KUALANERUS008 (CO8/NOVEMBER/2025)'),(63,1,'TBA7009',113,'KUALANERUS009 (CO8/NOVEMBER/2025)'),(64,1,'TBA7010',123,'KUALANERUS010 (CO8/NOVEMBER/2025)'),(65,1,'TBA7011',158,'KUALANERUS011 (CO8/NOVEMBER/2025)'),(66,1,'TBA7012',32,'KUALANERUS012 (CO8/NOVEMBER/2025)'),(67,1,'TBA7013',171,'KUALANERUS013 (CO8/NOVEMBER/2025)'),(68,1,'TBA7014',142,'KUALANERUS014 (CO8/NOVEMBER/2025)'),(69,1,'TBA7015',106,'KUALANERUS015 (CO8/NOVEMBER/2025)'),(70,1,'TBA7016',89,'KUALANERUS016 (CO8/NOVEMBER/2025)'),(71,1,'TBA7017',48,'KUALANERUS017 (CO8/NOVEMBER/2025)'),(72,1,'TBA7018',113,'KUALANERUS018 (CO8/NOVEMBER/2025)'),(73,1,'TBA7019',155,'KUALANERUS019 (CO8/NOVEMBER/2025)'),(74,1,'TBA7020',241,'KUALANERUS020 (CO8/NOVEMBER/2025)'),(75,1,'TBA7021',251,'KUALANERUS021 (CO8/NOVEMBER/2025)'),(76,1,'TBA7022',198,'KUALANERUS022 (CO8/NOVEMBER/2025)'),(77,1,'TBA7023',185,'KUALANERUS023 (CO8/NOVEMBER/2025)'),(78,1,'TBA7024',133,'KUALANERUS024 (CO8/NOVEMBER/2025)'),(79,1,'TBA7025',126,'KUALANERUS025 (CO8/NOVEMBER/2025)'),(80,1,'TBA7026',317,'KUALANERUS026 (CO8/NOVEMBER/2025)'),(81,1,'TBA7027',94,'KUALANERUS027 (CO8/NOVEMBER/2025)'),(82,1,'TBA7028',134,'KUALANERUS028 (CO8/NOVEMBER/2025)'),(83,1,'TBA7029',211,'KUALANERUS029 (CO8/NOVEMBER/2025)'),(84,1,'TBA6006',62,'SETIU001 (CO8/NOVEMBER/2025)'),(85,1,'TBA6009',51,'SETIU002 (CO8/NOVEMBER/2025)'),(86,1,'TBA6010',85,'SETIU003 (CO8/NOVEMBER/2025)'),(87,1,'TBA6011',83,'SETIU004 (CO8/NOVEMBER/2025)'),(88,1,'TBA6016',57,'SETIU005 (CO8/NOVEMBER/2025)'),(89,1,'TBA6020',119,'SETIU006 (CO8/NOVEMBER/2025)'),(90,1,'TBA6021',119,'SETIU007 (CO8/NOVEMBER/2025)'),(91,1,'TBA6034',215,'SETIU008 (CO8/NOVEMBER/2025)'),(92,1,'TBA6035',155,'SETIU009 (CO8/NOVEMBER/2025)'),(93,1,'TBA6036',133,'SETIU010 (CO8/NOVEMBER/2025)'),(94,1,'TBA6037',75,'SETIU011 (CO8/NOVEMBER/2025)'),(95,1,'TBA6038',71,'SETIU012 (CO8/NOVEMBER/2025)'),(96,1,'TBA6040',140,'SETIU013 (CO8/NOVEMBER/2025)'),(97,1,'TBA6041',190,'SETIU014 (CO8/NOVEMBER/2025)'),(98,1,'TBA6042',170,'SETIU015 (CO8/NOVEMBER/2025)'),(99,1,'TBA6056',92,'SETIU016 (CO8/NOVEMBER/2025)'),(100,1,'TBA6058',228,'SETIU017 (CO8/NOVEMBER/2025)'),(101,1,'TBA6066',133,'SETIU018 (CO8/NOVEMBER/2025)'),(102,1,'TBA6068',88,'SETIU019 (CO8/NOVEMBER/2025)'),(103,1,'TBA6069',62,'SETIU020 (CO8/NOVEMBER/2025)'),(104,1,'TBA6070',63,'SETIU021 (CO8/NOVEMBER/2025)'),(105,1,'TBA6071',99,'SETIU022 (CO8/NOVEMBER/2025)'),(106,1,'TBA6073',31,'SETIU023 (CO8/NOVEMBER/2025)'),(107,1,'TBA6074',16,'SETIU024 (CO8/NOVEMBER/2025)'),(108,1,'TBA6075',40,'SETIU025 (CO8/NOVEMBER/2025)'),(109,1,'TBA6080',18,'SETIU026 (CO8/NOVEMBER/2025)'),(110,1,'TBA6082',45,'SETIU027 (CO8/NOVEMBER/2025)'),(111,1,'TBA6084',17,'SETIU028 (CO8/NOVEMBER/2025)'),(112,1,'TBA6085',131,'SETIU029 (CO8/NOVEMBER/2025)'),(113,1,'TBA6086',176,'SETIU030 (CO8/NOVEMBER/2025)'),(114,1,'TBA6087',124,'SETIU031 (CO8/NOVEMBER/2025)'),(115,1,'TBA6088',63,'SETIU032 (CO8/NOVEMBER/2025)'),(116,1,'TBA6089',131,'SETIU033 (CO8/NOVEMBER/2025)'),(117,1,'TBA6090',88,'SETIU034 (CO8/NOVEMBER/2025)'),(118,1,'TBA6091',44,'SETIU035 (CO8/NOVEMBER/2025)'),(119,1,'TBA6092',47,'SETIU036 (CO8/NOVEMBER/2025)'),(120,1,'TBA6113',27,'SETIU037 (CO8/NOVEMBER/2025)'),(121,1,'TBA6114',33,'SETIU038 (CO8/NOVEMBER/2025)'),(122,1,'TBA6115',20,'SETIU039 (CO8/NOVEMBER/2025)'),(123,1,'TBA6116',23,'SETIU040 (CO8/NOVEMBER/2025)'),(124,1,'TBA6117',113,'SETIU041 (CO8/NOVEMBER/2025)'),(125,1,'TBA6118',234,'SETIU042 (CO8/NOVEMBER/2025)'),(126,1,'TBA6119',96,'SETIU043 (CO8/NOVEMBER/2025)');
/*!40000 ALTER TABLE `import_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inbound_items`
--

DROP TABLE IF EXISTS `inbound_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inbound_items` (
  `id` int(11) NOT NULL,
  `inbound_id` int(11) DEFAULT NULL,
  `inbound_item_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty_received` int(11) DEFAULT NULL,
  `ordered_date` date DEFAULT NULL,
  `production_time` time DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inbound_items`
--

LOCK TABLES `inbound_items` WRITE;
/*!40000 ALTER TABLE `inbound_items` DISABLE KEYS */;
INSERT INTO `inbound_items` VALUES (42,21,NULL,336,NULL,36,NULL,NULL,NULL),(43,22,NULL,290,NULL,144,NULL,NULL,NULL),(44,22,NULL,290,NULL,144,NULL,NULL,NULL),(45,22,NULL,291,NULL,144,NULL,NULL,NULL),(46,22,NULL,293,NULL,72,NULL,NULL,NULL),(47,22,NULL,294,NULL,72,NULL,NULL,NULL),(48,22,NULL,296,NULL,72,NULL,NULL,NULL),(49,22,NULL,297,NULL,36,NULL,NULL,NULL),(50,22,NULL,306,NULL,1,NULL,NULL,NULL),(51,22,NULL,310,NULL,144,NULL,NULL,NULL),(52,22,NULL,311,NULL,144,NULL,NULL,NULL),(53,22,NULL,311,NULL,144,NULL,NULL,NULL),(54,22,NULL,312,NULL,75,NULL,NULL,NULL),(55,24,NULL,406,NULL,144,NULL,NULL,NULL),(56,24,NULL,406,NULL,144,NULL,NULL,NULL),(57,24,NULL,406,NULL,144,NULL,NULL,NULL),(0,0,NULL,309,NULL,3456,NULL,NULL,NULL),(0,0,NULL,311,NULL,3456,NULL,NULL,NULL),(0,0,NULL,311,NULL,3456,NULL,NULL,NULL),(0,0,NULL,289,NULL,6144,NULL,NULL,NULL),(0,25,NULL,311,'C10',144,'2026-06-24',NULL,'2026-09-02'),(0,25,NULL,313,'F9',75,'2026-06-24',NULL,'2026-03-04'),(0,26,NULL,313,'F9',75,NULL,NULL,'2026-03-04'),(0,27,NULL,318,'F9',75,NULL,NULL,'2026-06-29'),(0,28,NULL,294,'D9',144,NULL,NULL,'2026-02-11');
/*!40000 ALTER TABLE `inbound_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inbound_logs`
--

DROP TABLE IF EXISTS `inbound_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inbound_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `received_date` datetime DEFAULT current_timestamp(),
  `category` varchar(20) DEFAULT NULL,
  `supplier_do` varchar(50) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `temp_truck` decimal(4,1) DEFAULT NULL,
  `temp_stock` decimal(4,1) DEFAULT NULL,
  `pallet_qty_plain_wood` int(11) DEFAULT 0,
  `pallet_qty_loscam_red` int(11) DEFAULT 0,
  `pallet_qty_lhp_green` int(11) DEFAULT 0,
  `pallet_qty_ffm_orange` int(11) DEFAULT 0,
  `pallet_qty_ffm_green` int(11) DEFAULT 0,
  `pallet_qty_plastic_black` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `transporter_name` varchar(100) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_plate` varchar(50) DEFAULT NULL,
  `arrival_time` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inbound_logs`
--

LOCK TABLES `inbound_logs` WRITE;
/*!40000 ALTER TABLE `inbound_logs` DISABLE KEYS */;
INSERT INTO `inbound_logs` VALUES (1,'2025-09-08 00:00:00','PST','1010019801',NULL,NULL,NULL,0,0,0,0,0,0,'PO: ','','','','00:00:00'),(20,'2026-02-15 00:00:00','UHT','1000032425',NULL,NULL,NULL,0,2,0,0,0,0,'PO: 2602-16380','Constamarie Logistics Sdn Bhd','Zahidi','JPL6858','11:20:00'),(21,'2026-02-15 00:00:00','UHT','1000032425',NULL,NULL,NULL,0,0,0,0,0,0,'PO: 1000030892','','','','00:00:00'),(22,'2026-02-15 00:00:00','UHT','1000032425',NULL,NULL,NULL,0,0,0,0,0,0,'PO: 1000030892','','','','00:00:00'),(24,'2026-02-15 00:00:00','PSS','1080026351',NULL,NULL,NULL,0,0,0,0,0,0,'PO: 1070022565','','','','00:00:00'),(25,'2026-07-05 00:00:00','PST','1010019801',NULL,NULL,NULL,0,0,0,0,0,2,'PO: 356556','','fahim','','13:04:00'),(26,'2026-07-06 11:14:34','UHT','SR-20260706-BBD8',NULL,NULL,NULL,0,0,0,0,0,0,'Single GRN: Lot GGGITNO2CW1CH-1000H12A/BANO260304-MFF009-PA047/QTY900',NULL,NULL,NULL,NULL),(27,'2026-07-07 16:25:50','UHT','SR-20260707-7AB2',NULL,NULL,NULL,0,0,0,0,0,0,'Single GRN: Lot GGGITNO2CW2FC-1000S12A/BANO260629-MFF009-PN085/QTY900',NULL,NULL,NULL,NULL),(28,'2026-07-07 16:39:00','UHT','SR-20260707-1E7D',NULL,NULL,NULL,0,0,0,1,0,0,'Single GRN: Lot GGGITNO2YD1MG-0200H24A/BANO260211-MFD009-PA011/QTY3456',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `inbound_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_batches`
--

DROP TABLE IF EXISTS `inventory_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `lot_no_raw` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `production_date` date DEFAULT NULL,
  `qty_on_hand` int(11) DEFAULT NULL,
  `pallet_type` varchar(100) DEFAULT NULL,
  `pallet_id_tag` varchar(50) DEFAULT NULL,
  `location_status` enum('Warehouse','Buffer','Shop','Damaged') DEFAULT 'Warehouse',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_batches`
--

LOCK TABLES `inventory_batches` WRITE;
/*!40000 ALTER TABLE `inventory_batches` DISABLE KEYS */;
INSERT INTO `inventory_batches` VALUES (42,336,NULL,NULL,NULL,NULL,36,'',NULL,'Warehouse','2026-02-17 03:08:30'),(43,290,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:25:23'),(44,290,NULL,NULL,NULL,NULL,120,'',NULL,'Warehouse','2026-02-17 03:25:23'),(45,291,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:25:23'),(46,293,NULL,NULL,NULL,NULL,62,'',NULL,'Warehouse','2026-02-17 03:25:23'),(47,294,NULL,NULL,NULL,NULL,72,'',NULL,'Warehouse','2026-02-17 03:25:23'),(48,296,NULL,NULL,NULL,NULL,72,'',NULL,'Warehouse','2026-02-17 03:25:23'),(49,297,NULL,NULL,NULL,NULL,36,'',NULL,'Warehouse','2026-02-17 03:25:23'),(50,306,NULL,NULL,NULL,NULL,1,'',NULL,'Warehouse','2026-02-17 03:25:23'),(51,310,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:25:23'),(52,311,NULL,NULL,NULL,NULL,120,'',NULL,'Warehouse','2026-02-17 03:25:23'),(53,311,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:25:23'),(54,312,NULL,NULL,NULL,NULL,75,'',NULL,'Warehouse','2026-02-17 03:25:23'),(55,406,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:32:55'),(56,406,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:32:55'),(57,406,NULL,NULL,NULL,NULL,144,'',NULL,'Warehouse','2026-02-17 03:32:55'),(58,309,NULL,NULL,NULL,NULL,3456,'',NULL,'Warehouse','2026-04-27 04:54:14'),(59,311,NULL,NULL,NULL,NULL,3456,'',NULL,'Warehouse','2026-04-27 04:54:14'),(60,311,NULL,NULL,NULL,NULL,3456,'',NULL,'Warehouse','2026-04-27 04:54:14'),(61,289,NULL,NULL,NULL,NULL,6144,'',NULL,'Warehouse','2026-04-27 04:54:14'),(62,293,NULL,NULL,NULL,NULL,5,'',NULL,'Buffer','2026-07-04 06:46:14'),(63,293,NULL,NULL,NULL,NULL,5,'',NULL,'Buffer','2026-07-04 06:46:41'),(64,311,'C10',NULL,'2026-09-02',NULL,144,'Plastic Black',NULL,'Warehouse','2026-07-05 05:05:59'),(65,313,'F9',NULL,'2026-03-04',NULL,75,'Plastic Black',NULL,'Warehouse','2026-07-05 05:05:59'),(66,313,'F9','GGGITNO2CW1CH-1000H12A/BANO260304-MFF009-PA047/QTY900','2026-03-04',NULL,75,'Plain','PA047','Warehouse','2026-07-06 03:14:34'),(67,318,'F9','GGGITNO2CW2FC-1000S12A/BANO260629-MFF009-PN085/QTY900','2026-06-29',NULL,75,'Plain','PN085','Warehouse','2026-07-07 08:25:50'),(68,294,'D9','GGGITNO2YD1MG-0200H24A/BANO260211-MFD009-PA011/QTY3456','2026-02-11',NULL,144,'FFM Orange','','Warehouse','2026-07-07 08:39:00');
/*!40000 ALTER TABLE `inventory_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mms_logistik`
--

DROP TABLE IF EXISTS `mms_logistik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mms_logistik` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `date` date NOT NULL,
  `totalCartons` int(11) NOT NULL,
  `extraPacks` int(11) NOT NULL,
  `isDelivered` tinyint(1) DEFAULT 0,
  `isDocSigned` tinyint(1) DEFAULT 0,
  `dealer` varchar(50) DEFAULT 'admin',
  `co_no` varchar(100) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `plan_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dealer` (`dealer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mms_logistik`
--

LOCK TABLES `mms_logistik` WRITE;
/*!40000 ALTER TABLE `mms_logistik` DISABLE KEYS */;
INSERT INTO `mms_logistik` VALUES ('TBA0001','SK KAMPONG RAJA','BESUT','0000-00-00',228,18,1,1,'mms','','2026-03-11','2026-03-11'),('TBA0002','SK AMER','BESUT','0000-00-00',136,6,1,0,'fizi','','2026-03-05','2026-03-05'),('TBA0003','SK ALOR LINTAH','BESUT','0000-00-00',191,6,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA0004','SK KERANDANG','BESUT','0000-00-00',323,18,1,1,'wali','','2026-03-09','0000-00-00'),('TBA0005','SK KUALA BESUT','BESUT','0000-00-00',461,6,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0006','SK TEMBILA','BESUT','0000-00-00',343,18,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA0007','SK KAMPUNG NANGKA','BESUT','0000-00-00',197,12,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0008','SK PULAU PERHENTIAN','BESUT','0000-00-00',190,0,1,1,'mms','','0000-00-00','2026-03-15'),('TBA0012','SK BETING LINTANG','BESUT','0000-00-00',266,6,1,1,'mms','','0000-00-00','2026-03-15'),('TBA0013','SK GONG BAYOR','BESUT','0000-00-00',327,12,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0014','SK KELUANG','BESUT','0000-00-00',323,18,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0015','SK BUKIT TEMPURONG','BESUT','0000-00-00',187,12,1,1,'wali','','2026-03-09','0000-00-00'),('TBA0017','SK KAMPUNG BAHARU','BESUT','0000-00-00',361,6,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0018','SK ALOR PEROI','BESUT','0000-00-00',28,18,1,1,'mms','','2026-03-11','2026-03-11'),('TBA0019','SK TOK RAJA','BESUT','0000-00-00',93,18,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0022','SK PENGKALAN NYIREH','BESUT','0000-00-00',390,0,1,1,'mms','','2026-03-11','2026-03-11'),('TBA0023','SK PUSAT JERTEH','BESUT','0000-00-00',275,0,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA0024','SK BUKIT KENAK','BESUT','0000-00-00',323,18,1,1,'wali','','2026-03-09','0000-00-00'),('TBA0025','SK TANAH MERAH','BESUT','0000-00-00',243,18,1,1,'wali','','2026-03-09','0000-00-00'),('TBA0026','SK APAL','BESUT','0000-00-00',186,6,1,1,'wali','','2026-03-09','0000-00-00'),('TBA0027','SK JABI','BESUT','0000-00-00',345,0,1,1,'wali','','2026-03-10','0000-00-00'),('TBA0028','SK RENEK','BESUT','0000-00-00',187,12,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0029','SK OH','BESUT','0000-00-00',106,6,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0030','SK TEMPINIS','BESUT','0000-00-00',322,12,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA0031','SK BUKIT JEROK','BESUT','0000-00-00',158,18,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0032','SK TOK DOR','BESUT','0000-00-00',125,0,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0033','SK KAMPUNG BARU TOK DOR','BESUT','0000-00-00',108,18,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0043','SK PADANG LUAS','BESUT','0000-00-00',355,0,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA0044','SK BUKIT PUTERI','BESUT','0000-00-00',288,18,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA0045','SK AYER TERJUN','BESUT','0000-00-00',278,18,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA0046','SK TOK MOTONG','BESUT','0000-00-00',187,12,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA0048','SK DARAU','BESUT','0000-00-00',270,0,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0049','SK PASIR AKAR','BESUT','0000-00-00',391,6,1,1,'ayu','','2026-03-05','0000-00-00'),('TBA0050','SK SERI PAYONG','BESUT','0000-00-00',321,6,1,1,'ayu','','2026-03-10','0000-00-00'),('TBA0051','SK (FELDA) TENANG','BESUT','0000-00-00',147,12,1,1,'ayu','','2026-03-09','0000-00-00'),('TBA0052','SK PANCHOR','BESUT','0000-00-00',131,6,1,1,'ayu','','2026-03-09','0000-00-00'),('TBA0053','SK KAMPUNG LA','BESUT','0000-00-00',102,12,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0054','SK KERUAK','BESUT','0000-00-00',258,18,1,1,'dora','','0000-00-00','0000-00-00'),('TBA0055','SK KUALA KUBANG','BESUT','0000-00-00',126,6,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0067','SK (FELDA) SELASIH','BESUT','0000-00-00',92,12,1,0,'fizi','','2026-03-16','2026-03-11'),('TBA0068','SK PENDIDIKAN KHAS BESUT','BESUT','0000-00-00',28,18,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0069','SK KUALA BESUT 2','BESUT','0000-00-00',353,18,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0070','SK TENGKU MAHMUD 2','BESUT','0000-00-00',325,0,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0072','SK LUBUK KAWAH','BESUT','0000-00-00',352,12,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0073','SK ALOR KELADI','BESUT','0000-00-00',192,12,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0074','SK KAMPUNG NAIL','BESUT','0000-00-00',357,12,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0075','SK SEBERANG JERTEH','BESUT','0000-00-00',147,12,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA0076','SK PERMAISURI NUR ZAHIRAH','BESUT','0000-00-00',400,0,1,1,'mms','','2026-03-05','2026-03-05'),('TBA0077','SK PELAGAT','BESUT','0000-00-00',188,18,1,1,'dora','','2026-03-09','0000-00-00'),('TBA0078','SK ANAK IKAN','BESUT','0000-00-00',211,6,1,1,'dora','','2026-03-10','0000-00-00'),('TBA0079','SK PADANG LANDAK','BESUT','0000-00-00',107,12,1,1,'sya','','0000-00-00','2026-03-10'),('TBA0080','SK NYIUR TUJUH','BESUT','0000-00-00',225,0,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA6006','SK LUBOK TERAS','SETIU','0000-00-00',65,0,1,1,'wali','','0000-00-00','2026-03-11'),('TBA6009','SK KAMPUNG FIKRI','SETIU','0000-00-00',62,12,1,1,'dora','','2026-03-16','0000-00-00'),('TBA6010','SK KUALA SETIU','SETIU','0000-00-00',105,0,1,1,'ayu','','2026-03-05','0000-00-00'),('TBA6011','SK PENAREK','SETIU','0000-00-00',96,6,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA6016','SK MANGKOK','SETIU','0000-00-00',65,0,1,1,'dora','','2026-03-10','0000-00-00'),('TBA6020','SK TELAGA PAPAN','SETIU','0000-00-00',242,12,1,1,'dora','','2026-03-16','0000-00-00'),('TBA6021','SK BARI','SETIU','0000-00-00',126,6,1,1,'dora','','2026-03-10','2026-03-10'),('TBA6034','SK BINTANG','SETIU','0000-00-00',287,12,1,1,'wali','','2026-03-10','0000-00-00'),('TBA6035','SK GUNTONG','SETIU','0000-00-00',175,0,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA6036','SK KAMPUNG BULOH','SETIU','0000-00-00',181,6,1,1,'dora','','0000-00-00','0000-00-00'),('TBA6037','SK KAMPONG BESUT','SETIU','0000-00-00',82,12,1,1,'wali','','2026-03-10','0000-00-00'),('TBA6038','SK PUTERA JAYA','SETIU','0000-00-00',106,6,1,1,'ayu','','2026-03-10','0000-00-00'),('TBA6040','SK KG. RAHMAT','SETIU','0000-00-00',172,12,1,1,'wali','','2026-03-10','0000-00-00'),('TBA6041','SK CHALOK','SETIU','0000-00-00',228,18,1,1,'sya','','0000-00-00','2026-03-10'),('TBA6042','SK BUKIT PUTERA','SETIU','0000-00-00',186,6,1,0,'fizi','','2026-03-16','2026-03-11'),('TBA6056','SK GUNTONG LUAR','SETIU','0000-00-00',111,6,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA6058','SK MERANG','SETIU','0000-00-00',262,12,1,1,'mms','','2026-03-12','2026-03-12'),('TBA6066','SK (FELDA) CHALOK BARAT','SETIU','0000-00-00',151,6,1,1,'wali','','2026-03-10','0000-00-00'),('TBA6068','SK SUNGAI TONG','SETIU','0000-00-00',112,12,1,0,'fizi','','2026-03-05','2026-03-05'),('TBA6069','SK BATU 29','SETIU','0000-00-00',63,18,1,1,'wali','','2026-03-05','0000-00-00'),('TBA6070','SK LANGKAP','SETIU','0000-00-00',83,18,1,1,'wali','','2026-03-05','0000-00-00'),('TBA6071','SK PELONG','SETIU','0000-00-00',111,6,1,0,'fizi','','2026-03-16','2026-03-11'),('TBA6073','SK MERBAU MENYUSUT','SETIU','0000-00-00',37,12,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6074','SK BUKIT MUNDOK','SETIU','0000-00-00',30,0,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6075','SK KG BUKIT ULU NERUS','SETIU','0000-00-00',50,0,1,1,'sya','','2026-03-12','2026-03-12'),('TBA6080','SK PAK BA','SETIU','0000-00-00',31,6,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6082','SK KAMPUNG FIKRI SUNGAI TONG','SETIU','0000-00-00',33,18,1,1,'wali','','0000-00-00','2026-03-11'),('TBA6084','SK SUNGAI LAS','SETIU','0000-00-00',23,18,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6085','SK RHU SEPULUH','SETIU','0000-00-00',137,12,1,1,'dora','','2026-03-10','0000-00-00'),('TBA6086','SK BANGGOL','SETIU','0000-00-00',191,6,1,1,'ayu','','2026-03-05','0000-00-00'),('TBA6087','SK PANCHOR MERAH','SETIU','0000-00-00',151,6,1,0,'fizi','','0000-00-00','2026-03-09'),('TBA6088','SK PERMAISURI','SETIU','0000-00-00',73,18,1,0,'ayu','','2026-03-10','0000-00-00'),('TBA6089','SK SUNGAI LEREK','SETIU','0000-00-00',158,18,1,1,'dora','','2026-03-16','0000-00-00'),('TBA6090','SK LEMBAH BIDONG','SETIU','0000-00-00',117,12,1,1,'mms','','2026-03-12','2026-03-12'),('TBA6091','SK LEMBAH JAYA','SETIU','0000-00-00',52,12,1,1,'wali','','2026-03-10','0000-00-00'),('TBA6092','SK SERI KASAR','SETIU','0000-00-00',55,0,1,1,'wali','','2026-03-05','0000-00-00'),('TBA6113','SK KAMPONG JAYA','SETIU','0000-00-00',28,18,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6114','SK PAYONG BARU','SETIU','0000-00-00',42,12,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6115','SK KAMPONG TAYOR','SETIU','0000-00-00',27,12,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6116','SK BARI (SG TONG)','SETIU','0000-00-00',16,6,1,1,'sya','','2026-03-05','2026-03-05'),('TBA6117','SK JELAPANG','SETIU','0000-00-00',155,0,1,0,'fizi','','2026-03-16','2026-03-11'),('TBA6118','SK SAUJANA','SETIU','0000-00-00',291,6,1,1,'ayu','','2026-03-09','0000-00-00'),('TBA6119','SK SERI LANGKAP','SETIU','0000-00-00',85,0,1,0,'fizi','','2026-03-05','2026-03-05'),('TBA7001','SK DARAT BATU RAKIT','KUALA NERUS','0000-00-00',153,18,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7002','SK BUKIT TUNGGAL','KUALA NERUS','0000-00-00',328,18,1,1,'mms','','2026-03-03','2026-03-03'),('TBA7003','SK SEBERANG TAKIR','KUALA NERUS','0000-00-00',203,18,1,1,'mms','','2026-03-03','2026-03-03'),('TBA7004','SK MENGABANG TELIPOT','KUALA NERUS','0000-00-00',250,0,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7005','SK BUKIT GUNTONG','KUALA NERUS','0000-00-00',248,18,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7006','SK BATU RAKIT','KUALA NERUS','0000-00-00',275,0,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7007','SK MARAS','KUALA NERUS','0000-00-00',136,6,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7008','SK PAGAR BESI','KUALA NERUS','0000-00-00',288,18,1,1,'mms','','2026-03-02','2026-03-12'),('TBA7009','SK BUKIT CHEMPAKA','KUALA NERUS','0000-00-00',128,18,1,1,'wali','','0000-00-00','2026-03-11'),('TBA7010','SK BUKIT WAN','KUALA NERUS','0000-00-00',146,6,1,1,'ayu','','2026-03-11','0000-00-00'),('TBA7011','SK BUKIT NANAS','KUALA NERUS','0000-00-00',156,6,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7012','SK PECHAH ROTAN','KUALA NERUS','0000-00-00',43,18,1,1,'sya','','0000-00-00','0000-00-00'),('TBA7013','SK TOK JEMBAL','KUALA NERUS','0000-00-00',198,18,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7014','SK TOK JIRING','KUALA NERUS','0000-00-00',162,12,1,1,'sya','','0000-00-00','2026-03-11'),('TBA7015','SK KAMPUNG GEMUROH','KUALA NERUS','0000-00-00',117,12,1,1,'mms','','2026-03-10','2026-03-10'),('TBA7016','SK PULAU REDANG','KUALA NERUS','0000-00-00',102,12,1,1,'sya','','0000-00-00','2026-03-09'),('TBA7017','SK LKTP BELARA','KUALA NERUS','0000-00-00',56,6,1,0,'ayu','','2026-03-11','0000-00-00'),('TBA7018','SK BUKIT TOK BENG','KUALA NERUS','0000-00-00',107,12,1,1,'mms','','2026-03-03','2026-03-03'),('TBA7019','SK KOMPLEKS MENGABANG TELIPOT','KUALA NERUS','0000-00-00',186,6,1,1,'sya','','0000-00-00','2026-03-11'),('TBA7020','SK GONG BADAK','KUALA NERUS','0000-00-00',261,6,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7021','SK TELUK KETAPANG','KUALA NERUS','0000-00-00',263,18,1,1,'dora','','2026-03-11','2026-03-11'),('TBA7022','SK KOMPLEKS GONG BADAK','KUALA NERUS','0000-00-00',201,6,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7023','SK BUKIT TUMBUH','KUALA NERUS','0000-00-00',191,6,1,1,'mms','','2026-03-03','2026-03-03'),('TBA7024','SK TANJUNG GELAM','KUALA NERUS','0000-00-00',151,6,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7025','SK PADANG AIR','KUALA NERUS','0000-00-00',152,12,1,0,'fizi','','2026-03-05','2026-03-05'),('TBA7026','SK KOMPLEKS SEBERANG TAKIR','KUALA NERUS','0000-00-00',370,0,1,1,'mms','','2026-03-12','2026-03-12'),('TBA7027','SK INSTITUT PENDIDIKAN GURU KAMPUS DATO RAZALI ISMAIL','KUALA NERUS','0000-00-00',120,0,1,1,'mms','','2026-03-09','2026-03-09'),('TBA7028','SK PADANG KEMUNTING','KUALA NERUS','0000-00-00',167,12,1,0,'fizi','','2026-03-05','2026-03-05'),('TBA7029','SK KOMPLEKS TEMBESU','KUALA NERUS','0000-00-00',208,18,1,1,'mms','','2026-03-03','2026-03-03'),('TBB0061','SK TENGKU MAHMUD','BESUT','0000-00-00',217,12,1,1,'mms','','2026-03-05','2026-03-05'),('TBC0063','SJK(C) CHUNG HWA','BESUT','0000-00-00',45,0,1,1,'sya','','0000-00-00','2026-03-10');
/*!40000 ALTER TABLE `mms_logistik` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outbound_items`
--

DROP TABLE IF EXISTS `outbound_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outbound_items` (
  `id` int(11) NOT NULL,
  `outbound_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `batch` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outbound_items`
--

LOCK TABLES `outbound_items` WRITE;
/*!40000 ALTER TABLE `outbound_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `outbound_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outbound_logs`
--

DROP TABLE IF EXISTS `outbound_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outbound_logs` (
  `id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `customer` varchar(100) DEFAULT NULL,
  `doc_ref` varchar(50) DEFAULT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `category` varchar(20) DEFAULT 'Commercial',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outbound_logs`
--

LOCK TABLES `outbound_logs` WRITE;
/*!40000 ALTER TABLE `outbound_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `outbound_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pallet_ledger`
--

DROP TABLE IF EXISTS `pallet_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pallet_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `transaction_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `pallet_code` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pallet_code` (`pallet_code`),
  CONSTRAINT `pallet_ledger_ibfk_1` FOREIGN KEY (`pallet_code`) REFERENCES `pallet_types` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pallet_ledger`
--

LOCK TABLES `pallet_ledger` WRITE;
/*!40000 ALTER TABLE `pallet_ledger` DISABLE KEYS */;
INSERT INTO `pallet_ledger` VALUES (1,'2026-07-07 17:03:46','ADJUSTMENT','red',7,'Manual Adjustment','stock yang dah ada di warehouse \r\n'),(2,'2026-07-07 17:04:17','ADJUSTMENT','red',-4,'Manual Adjustment','dah jual');
/*!40000 ALTER TABLE `pallet_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pallet_types`
--

DROP TABLE IF EXISTS `pallet_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pallet_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pallet_types`
--

LOCK TABLES `pallet_types` WRITE;
/*!40000 ALTER TABLE `pallet_types` DISABLE KEYS */;
INSERT INTO `pallet_types` VALUES (1,'Plain Wood','plain'),(2,'Loscam Red','red'),(3,'LHP Green','lhp'),(4,'FFM Orange','orange'),(5,'FFM Green','ffm'),(6,'Plastic Black','black');
/*!40000 ALTER TABLE `pallet_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `qrcode` varchar(100) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `uom` varchar(20) DEFAULT 'Carton',
  `is_active` tinyint(4) DEFAULT 1,
  `pack_size` int(11) DEFAULT 1,
  `pallet_capacity` int(11) DEFAULT 60,
  `pcs_per_carton` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=423 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (276,'UHT FF Moola Choco Malt 100ml',NULL,NULL,'UHT','pcs',1,32,192,1),(277,'UHT FF Fresh 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(278,'UHT FF Chocolate 125ml','9556001234567',NULL,'UHT','pcs',1,32,192,1),(279,'UHT FF Kurma 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(280,'UHT FF Banana 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(281,'UHT FF Grow Up 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(282,'UHT FF Yog Strawberry 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(283,'UHT FF Yog Mix Berry 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(284,'UHT FF Yog Mango 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(285,'UHT FF Soy Ori 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(286,'UHT FF Choc Soy 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(287,'UHT Yarra Full Cream 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(288,'UHT Yarra Chocolate 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(289,'UHT Yarra Strawberry 125ml',NULL,NULL,'UHT','pcs',1,32,192,1),(290,'UHT FF Kurma 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(291,'UHT FF Chocolate 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(293,'UHT FF Banana 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(294,'UHT FF Yog Mango 200ml',NULL,'O2YD1MG-0200H24A','UHT','pcs',1,24,192,1),(295,'UHT FF Yog Mix Berry 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(296,'UHT FF Yog Strawberry 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(297,'UHT FF Fresh 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(298,'UHT FF Soy Ori 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(299,'UHT FF Soy Chocolate 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(300,'UHT FF Caf',NULL,NULL,'UHT','pcs',1,24,192,1),(301,'UHT FF Henry Jones A2 Organic 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(302,'UHT FF Lactose Free 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(303,'UHT FF Oat 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(304,'UHT FF Almond 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(305,'UHT FF Almond Barista 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(306,'UHT FF Oat Barista 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(307,'UHT FF Almond Unsweetened 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(308,'UHT FF Grow Up 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(309,'UHT FF Yarra Full Cream 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(310,'UHT FF Yarra Chocolate 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(311,'UHT FF Yarra Strawberry 200ml',NULL,'O2CW6SB-0200S24A','UHT','pcs',1,24,192,1),(312,'UHT FF Kurma 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(313,'UHT FF Chocolate 1l','345336563538','O2CW1CH-1000H12A','UHT','pcs',1,12,144,1),(314,'UHT FF Fresh 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(315,'UHT FF Henry Jones A2 Organic 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(316,'UHT Yarra Master Barista 1l (W/CAP)',NULL,NULL,'UHT','pcs',1,12,144,1),(317,'UHT Yarra Master Barista 1l (CAP)',NULL,NULL,'UHT','pcs',1,12,144,1),(318,'UHT Yarra Full Cream Professional 1l',NULL,'O2CW2FC-1000S12A','UHT','pcs',1,12,144,1),(319,'UHT FF Oat 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(320,'UHT FF Almond 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(321,'UHT FF Almond Unsweetened 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(322,'UHT FF Soy Ori 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(323,'UHT FF Soy Chocolate 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(324,'UHT Yarra Full Cream 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(325,'UHT Yarra Chocolate 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(326,'UHT Yarra Strawberry 1l',NULL,NULL,'UHT','pcs',1,12,144,1),(327,'Grow Powder 1-3yo 30gx10',NULL,NULL,'Powder','box',1,12,144,1),(328,'Grow Powder 4-6yo 30gx10',NULL,NULL,'Powder','box',1,12,144,1),(329,'Grow Powder 1-3yo 500g',NULL,NULL,'Powder','box',1,12,144,1),(330,'Grow Powder 4-6yo 500g',NULL,NULL,'Powder','box',1,12,144,1),(331,'Grow Powder 1-3yo 800g',NULL,NULL,'Powder','box',1,12,144,1),(332,'Grow Powder 4-6yo 800g',NULL,NULL,'Powder','box',1,12,144,1),(333,'Chocomalt 800g',NULL,NULL,'Powder','pack',1,12,144,1),(334,'Chocomalt Powder 35gx10',NULL,NULL,'Powder','pack',1,12,144,1),(335,'Chocomalt Kaw 1 Kg',NULL,NULL,'Powder','pack',1,12,144,1),(336,'Full Cream Milk Powder 800g',NULL,NULL,'Powder','pack',1,12,144,1),(337,'Chocomalt 2kg',NULL,NULL,'Powder','pack',1,6,0,1),(338,'PST Pure Fresh Milk 1L',NULL,NULL,'PST','bottle',1,12,80,1),(339,'PST Pure Fresh Milk 2L','','','PST','bottle',1,6,80,1),(340,'PST Pure Lactose Free 1L',NULL,NULL,'PST','bottle',1,12,80,1),(341,'PST Chocolate 1L',NULL,NULL,'PST','bottle',1,12,80,1),(342,'PST Kurma 2L',NULL,NULL,'PST','bottle',1,6,80,1),(343,'PST Yogurt Strawberry 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(344,'PST Yogurt Mixberries 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(345,'PST Yogurt Mango 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(346,'PST Yogurt Natural 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(347,'PST Kurma 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(348,'PST Chocolate 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(349,'PST Cafe Latte 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(350,'PST Yogurt Fruit Punch 200ml',NULL,NULL,'PST','bottle',1,12,0,1),(351,'PST Pure Fresh 568ml',NULL,NULL,'PST','bottle',1,12,0,1),(352,'PST Chocolate 568ml',NULL,NULL,'PST','bottle',1,12,0,1),(353,'PST Yogurt Mixberries 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(354,'PST Yogurt Mango 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(355,'PST Yogurt Strawberry 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(356,'PST Cafe Latte 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(357,'PST Kurma 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(358,'PST Strawberry 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(359,'PST Banana 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(360,'PST Chocomint 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(361,'PST Rasberries 700ml',NULL,NULL,'PST','bottle',1,12,0,1),(362,'PST Aus Organic Milk 1L',NULL,NULL,'PST','bottle',1,12,80,1),(363,'PST Yarra Aus 1L',NULL,NULL,'PST','bottle',1,12,80,1),(364,'PST Barista',NULL,NULL,'PST','bottle',1,12,0,1),(365,'PST Skinny 2L',NULL,NULL,'PST','bottle',1,12,80,1),(366,'PST Skinny 1L',NULL,NULL,'PST','bottle',1,12,80,1),(367,'PST Yogurt Tub 1.4Kg',NULL,NULL,'PST','bottle',1,6,0,1),(368,'PST Yogurt Tub 400G',NULL,NULL,'PST','bottle',1,12,0,1),(369,'PST Yarra Natural  Yogurt 470G',NULL,NULL,'PST','tub',1,12,0,1),(370,'PST Yarra Natural Yogurt 1.4Kg',NULL,NULL,'PST','tub',1,6,0,1),(371,'PST Farm Yogurt 120G - Apricot',NULL,NULL,'PST','tub',1,12,0,1),(372,'PST Farm Yogurt 120G - Peach',NULL,NULL,'PST','tub',1,12,0,1),(373,'PST Farm Yogurt 120G - Strawberry',NULL,NULL,'PST','tub',1,12,0,1),(374,'PST Farm Yogurt 120G - Mix Berry',NULL,NULL,'PST','tub',1,12,0,1),(375,'PST Farm Yogurt 120G - Mango',NULL,NULL,'PST','tub',1,12,0,1),(376,'PST Farm Yogurt 120G - Natural',NULL,NULL,'PST','tub',1,12,0,1),(377,'PST Farm Yogurt 120G - Pumpkin',NULL,NULL,'PST','tub',1,12,0,1),(378,'PST Greek Yogurt Natural 120G',NULL,NULL,'PST','tub',1,12,0,1),(379,'PST Greek Yogurt Apricot & Seeds 120G',NULL,NULL,'PST','tub',1,12,0,1),(380,'PST Greek Yogurt Aloevera & Peach 120G',NULL,NULL,'PST','tub',1,12,0,1),(381,'PST Greek Yogurt Mulberries & Strawberry 120G',NULL,NULL,'PST','tub',1,12,0,1),(382,'PST Greek Yogurt 470G',NULL,NULL,'PST','tub',1,12,0,1),(383,'Cooking Cream 1L',NULL,NULL,'Cooking','pcs',1,12,0,1),(384,'Whipping Cream 1L',NULL,NULL,'Cooking','pcs',1,12,0,1),(385,'PST GC Original',NULL,NULL,'PST','bottle',1,30,0,1),(386,'PST GC Apple',NULL,NULL,'PST','bottle',1,30,0,1),(387,'PST GC Grape',NULL,NULL,'PST','bottle',1,30,0,1),(388,'PST GC Melon',NULL,NULL,'PST','bottle',1,30,0,1),(389,'PST GC Tutti Frutti',NULL,NULL,'PST','bottle',1,30,0,1),(390,'PST GC F1 Glass 1L',NULL,NULL,'PST','bottle',1,8,0,1),(391,'Salted Butter 200G',NULL,NULL,'Cooking','pcs',1,40,0,1),(392,'Unsalted Butter 200G',NULL,NULL,'Cooking','pcs',1,40,0,1),(393,'Salted Butter 9G',NULL,NULL,'Cooking','pcs',1,200,0,1),(394,'Unsalted Butter 9G',NULL,NULL,'Cooking','pcs',1,200,0,1),(395,'Cream Hauz - Vanilla',NULL,NULL,'IceCream','pcs',1,24,0,1),(396,'Cream Hauz - Chocolate',NULL,NULL,'IceCream','pcs',1,24,0,1),(397,'Cream Hauz - Classic',NULL,NULL,'IceCream','pcs',1,24,0,1),(398,'FF Choco Bar',NULL,NULL,'IceCream','pcs',1,30,0,1),(399,'FF Lime - One Two Juice',NULL,NULL,'IceCream','pcs',1,30,0,1),(400,'FF Lemon Ice Tea',NULL,NULL,'IceCream','pcs',1,30,0,1),(401,'Rompin - Premium Beef',NULL,NULL,'Beef','pack',1,20,0,1),(402,'Rompin - Tetel',NULL,NULL,'Beef','pack',1,20,0,1),(403,'Rompin - Ribs',NULL,NULL,'Beef','pack',1,18,0,1),(404,'Rompin - Striploin',NULL,NULL,'Beef','pack',1,0,0,1),(405,'Rompin - Tenderloin',NULL,NULL,'Beef','pack',1,0,0,1),(406,'UHT Yarra Full Cream (School) 200ml',NULL,NULL,'PSS','pcs',1,24,144,1),(407,'UHT Almond Milk Original 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(408,'UHT Almond Milk Unsweetened 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(409,'UHT Almond Milk Unsweetened 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(410,'UHT Oat Milk Original 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(411,'UHT Oat Milk Barista 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(412,'UHT Soy Milk Original 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(413,'UHT Soy Milk Original 200ml',NULL,NULL,'UHT','pcs',1,24,192,1),(414,'Yarra Master Barista 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(415,'Yarra Full Cream Professional 1L',NULL,NULL,'UHT','pcs',1,12,144,1),(416,'PST Pure Fresh Milk 1L',NULL,NULL,'PST','bottle',1,12,80,1),(417,'PST Chocolate Milk 1L',NULL,NULL,'PST','bottle',1,12,80,1),(418,'PST Kurma Milk 1L',NULL,NULL,'PST','bottle',1,12,80,1),(419,'Yogurt Natural 1kg (Tub)',NULL,NULL,'PST','tub',1,6,0,1),(420,'Greek Yogurt Natural 120g',NULL,NULL,'PST','tub',1,12,0,1),(421,'Chocomalt Powder 1kg',NULL,NULL,'Powder','pack',1,12,0,1),(422,'UHT Chocolate 200ml (School)',NULL,NULL,'PSS','pcs',1,24,192,1);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_code` varchar(20) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `zone_code` varchar(100) DEFAULT NULL,
  `default_hd_id` int(11) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `address` text DEFAULT NULL,
  `no_tel` varchar(50) DEFAULT NULL,
  `co_number` varchar(50) DEFAULT NULL,
  `sap_no` varchar(50) DEFAULT NULL,
  `tender_no` varchar(50) DEFAULT NULL,
  `contract_no` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_school_code` (`school_code`),
  KEY `fk_school_hd` (`default_hd_id`),
  CONSTRAINT `fk_school_hd` FOREIGN KEY (`default_hd_id`) REFERENCES `hds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=631 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES (505,'TBA0001','SK KAMPONG RAJA','PPD BESUT',7,176,'KAMPONG RAJA,','09-6957714','CO8','BESUT001 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(506,'TBA0002','SK AMER','PPD BESUT',2,123,'KAMPUNG AMER,','09-6958743','CO8','BESUT002 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(507,'TBA0003','SK ALOR LINTAH','PPD BESUT',2,166,'KAMPUNG ALOR LINTAH,','09-6971215','CO8','BESUT003 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(508,'TBA0004','SK KERANDANG','PPD BESUT',3,269,'KAMPUNG TANAH MERAH,','09-6943064','CO8','BESUT004 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(509,'TBA0005','SK KUALA BESUT','PPD BESUT',7,407,'JALAN BESAR,','09-6902354','CO8','BESUT005 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(510,'TBA0006','SK TEMBILA','PPD BESUT',2,293,'KAMPUNG TEMBILA,','09-6950937','CO8','BESUT006 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(511,'TBA0007','SK KAMPUNG NANGKA','PPD BESUT',7,168,'KAMPUNG LAMPU,','09-6918870','CO8','BESUT007 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(512,'TBA0008','SK PULAU PERHENTIAN','PPD BESUT',7,163,'PEJABAT POS KUALA BESUT','09-6977106','CO8','BESUT008 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(513,'TBA0012','SK BETING LINTANG','PPD BESUT',7,222,'KAMPUNG BETING LINTANG,','09-6920130','CO8','BESUT009 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(514,'TBA0013','SK GONG BAYOR','PPD BESUT',5,290,'KAMPUNG GONG BAYOR,','09-6920050','CO8','BESUT010 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(515,'TBA0014','SK KELUANG','PPD BESUT',8,258,'KAMPUNG KELUANG,','09-6957223','CO8','BESUT011 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(516,'TBA0015','SK BUKIT TEMPURONG','PPD BESUT',3,156,'KAMPUNG TASIK MENGKUANG,','09-6943148','CO8','BESUT012 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(517,'TBA0017','SK KAMPUNG BAHARU','PPD BESUT',7,289,'KAMPUNG BAHARU,','09-6910678','CO8','BESUT013 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(518,'TBA0018','SK ALOR PEROI','PPD BESUT',7,372,'KAMPUNG ALOR PEROI,','09-6910318','CO8','BESUT014 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(519,'TBA0019','SK TOK RAJA','PPD BESUT',7,80,'KAMPUNG TOK RAJA,','09-6976273','CO8','BESUT015 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(520,'TBA0022','SK PENGKALAN NYIREH','PPD BESUT',7,307,'KAMPUNG PENGKALAN NYIREH,','09-6956058','CO8','BESUT016 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(521,'TBA0023','SK PUSAT JERTEH','PPD BESUT',9,228,'KAMPUNG GONG RENGAS','09-6971144','CO8','BESUT017 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(522,'TBA0024','SK BUKIT KENAK','PPD BESUT',3,270,'KAMPUNG BUKIT KENAK,','09-6973790','CO8','BESUT018 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(523,'TBA0025','SK TANAH MERAH','PPD BESUT',3,182,'KAMPUNG GONG TANAH MERAH,','09-6979418','CO8','BESUT019 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(524,'TBA0026','SK APAL','PPD BESUT',3,156,'KAMPUNG APAL,','09-6942146','CO8','BESUT020 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(525,'TBA0027','SK JABI','PPD BESUT',3,267,'KAMPUNG JABI,','09-6941809','CO8','BESUT021 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(526,'TBA0028','SK RENEK','PPD BESUT',5,141,'KAMPUNG RENEK,','09-6974944','CO8','BESUT022 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(527,'TBA0029','SK OH','PPD BESUT',8,80,'KAMPUNG OH,','09-6920030','CO8','BESUT023 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(528,'TBA0030','SK TEMPINIS','PPD BESUT',2,241,'KAMPUNG TEMPINIS,','09-6975189','CO8','BESUT024 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(529,'TBA0031','SK BUKIT JEROK','PPD BESUT',5,145,'KAMPUNG ANAK MUSANG,','09-6977721','CO8','BESUT025 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(530,'TBA0032','SK TOK DOR','PPD BESUT',5,100,'KAMPUNG TOK DOR,','09-6920978','CO8','BESUT026 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(531,'TBA0033','SK KAMPUNG BARU TOK DOR','PPD BESUT',5,89,'KAMPUNG BARU TOK DOR,','09-6921615','CO8','BESUT027 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(532,'TBA0043','SK PADANG LUAS','PPD BESUT',9,256,'KAMPUNG PADANG LUAS,','09-6972359','CO8','BESUT028 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(533,'TBA0044','SK BUKIT PUTERI','PPD BESUT',9,214,'KAMPUNG DENGIR,','09-6971409','CO8','BESUT029 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(534,'TBA0045','SK AYER TERJUN','PPD BESUT',9,229,'KAMPUNG AYER TERJUN,','09-6921004','CO8','BESUT030 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(535,'TBA0046','SK TOK MOTONG','PPD BESUT',9,164,'KAMPUNG TOK MOTONG,','09-6979192','CO8','BESUT031 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(536,'TBA0048','SK DARAU','PPD BESUT',8,211,'KAMPUNG DARAU,','09-6972885','CO8','BESUT032 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(537,'TBA0049','SK PASIR AKAR','PPD BESUT',9,351,'KAMPUNG PASIR AKAR,','09-6902141','CO8','BESUT033 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(538,'TBA0050','SK SERI PAYONG','PPD BESUT',9,261,'KAMPUNG BUKIT PAYONG,','09-60612595','CO8','BESUT034 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(539,'TBA0051','SK (FELDA) TENANG','PPD BESUT',9,113,'KAMPUNG FELDA TENANG,','09-6062001','CO8','BESUT035 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(540,'TBA0052','SK PANCHOR','PPD BESUT',9,100,'KAMPUNG PANCHOR,','09-6060488','CO8','BESUT036 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(541,'TBA0053','SK KAMPUNG LA','PPD BESUT',8,77,'KAMPUNG LA,','09-6060810','CO8','BESUT037 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(542,'TBA0054','SK KERUAK','PPD BESUT',5,213,'KAMPUNG KERUAK,','09-606 0830','CO8','BESUT038 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(543,'TBA0055','SK KUALA KUBANG','PPD BESUT',5,98,'KAMPUNG KUALA KUBANG,','09-6942363','CO8','BESUT039 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(544,'TBA0067','SK (FELDA) SELASIH','PPD BESUT',2,88,'KAMPUNG FELDA SELASIH,','09-6978167','CO8','BESUT040 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(545,'TBA0068','SK PENDIDIKAN KHAS BESUT','PPD BESUT',3,23,'KAMPUNG ALOR LINTANG,','09-6902430','CO8','BESUT041 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(546,'TBA0069','SK KUALA BESUT 2','PPD BESUT',7,287,'KUALA BESUT','09-6902354','CO8','BESUT042 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(547,'TBA0070','SK TENGKU MAHMUD 2','PPD BESUT',5,259,'JALAN GONG KEPAS DALAM,','09-6957795','CO8','BESUT043 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(548,'TBA0072','SK LUBUK KAWAH','PPD BESUT',7,304,'KAMPUNG CHERANG MELILING,','09-6976397','CO8','BESUT044 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(549,'TBA0073','SK ALOR KELADI','PPD BESUT',5,144,'KAMPUNG ALOR KELADI,','09-6971946','CO8','BESUT045 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(550,'TBA0074','SK KAMPUNG NAIL','PPD BESUT',7,289,'KAMPUNG NAIL,','09-6910218','CO8','BESUT046 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(551,'TBA0075','SK SEBERANG JERTEH','PPD BESUT',2,117,'KAMPUNG PENGKALAN SENTOL,','09-6975229','CO8','BESUT047 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(552,'TBA0076','SK PERMAISURI NUR ZAHIRAH','PPD BESUT',7,329,'KAMPUNG LIMBONGAN,','09-6953248','CO8','BESUT048 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(553,'TBA0077','SK PELAGAT','PPD BESUT',5,159,'KAMPUNG PADANG LUAS,','09-6902296','CO8','BESUT049 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(554,'TBA0078','SK ANAK IKAN','PPD BESUT',5,165,'KAMPUNG TOK DOR,','09-6925298','CO8','BESUT050 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(555,'TBA0079','SK PADANG LANDAK','PPD BESUT',9,86,'KAMPUNG PADANG LANDAK,','09-6979045','CO8','BESUT051 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(556,'TBA0080','SK NYIUR TUJUH','PPD BESUT',2,196,'KAMPUNG NYIUR TUJUH,','09-6920080','CO8','BESUT052 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(557,'TBB0061','SK TENGKU MAHMUD','PPD BESUT',7,184,'KAMPUNG ALOR LINTANG,','09-6979346','CO8','BESUT053 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(558,'TBC0063','SJK(C) CHUNG HWA','PPD BESUT',5,43,'SIMPANG TIGA,','09-6971632','CO8','BESUT054 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(559,'TBA6006','SK LUBOK TERAS','PPD SETIU',3,62,'W/POS KUALA TELEMONG','09-8241082','CO8','SETIU001 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(560,'TBA6009','SK KAMPUNG FIKRI','PPD SETIU',8,51,'KG. FIKRI PANTAI','09-6021207','CO8','SETIU002 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(561,'TBA6010','SK KUALA SETIU','PPD SETIU',5,85,'KG. KUALA SETIU','09-6924322','CO8','SETIU003 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(562,'TBA6011','SK PENAREK','PPD SETIU',5,83,'KG. PENAREK','09-8241368','CO8','SETIU004 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(563,'TBA6016','SK MANGKOK','PPD SETIU',9,57,'KG. MANGKOK','09-6924723','CO8','SETIU005 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(564,'TBA6020','SK TELAGA PAPAN','PPD SETIU',5,119,'KAMPUNG TELAGA PAPAN','09-8248964','CO8','SETIU006 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(565,'TBA6021','SK BARI','PPD SETIU',5,119,'MERANG','09-8240025','CO8','SETIU007 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(566,'TBA6034','SK BINTANG','PPD SETIU',3,215,'BANDAR PERMAISURI','09-6974705','CO8','SETIU008 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(567,'TBA6035','SK GUNTONG','PPD SETIU',9,155,'KAMPUNG GUNTONG','09-6927252','CO8','SETIU009 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(568,'TBA6036','SK KAMPUNG BULOH','PPD SETIU',9,133,'BANDAR PERMAISURI','09-6099332','CO8','SETIU010 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(569,'TBA6037','SK KAMPONG BESUT','PPD SETIU',3,75,'JALAN ULU SELADANG, KG. BESUT','09-6923618','CO8','SETIU011 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(570,'TBA6038','SK PUTERA JAYA','PPD SETIU',5,71,'PUTERA JAYA','09-6092600','CO8','SETIU012 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(571,'TBA6040','SK KG. RAHMAT','PPD SETIU',3,140,'FELDA CHALOK','09-6097620','CO8','SETIU013 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(572,'TBA6041','SK CHALOK','PPD SETIU',8,190,'KG. GONG TERAP','09-6576866','CO8','SETIU014 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(573,'TBA6042','SK BUKIT PUTERA','PPD SETIU',2,170,'KAMPUNG BUKIT PUTERA CHALOK','09-6576917','CO8','SETIU015 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(574,'TBA6056','SK GUNTONG LUAR','PPD SETIU',2,92,'KG GUNTONG LUAR','09-6097931','CO8','SETIU016 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(575,'TBA6058','SK MERANG','PPD SETIU',7,228,'BANDAR PERMAISURI','09-6532063','CO8','SETIU017 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(576,'TBA6066','SK (FELDA) CHALOK BARAT','PPD SETIU',8,133,'FELDA CHALOK BARAT','09-6571099','CO8','SETIU018 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(577,'TBA6068','SK SUNGAI TONG','PPD SETIU',2,88,'KG. SUNGAI TONG','09-6572828','CO8','SETIU019 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(578,'TBA6069','SK BATU 29','PPD SETIU',2,62,'W/POS SUNGAI TONG','09-6571839','CO8','SETIU020 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(579,'TBA6070','SK LANGKAP','PPD SETIU',5,63,'KAMPUNG LANGKAP','09-8244487','CO8','SETIU021 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(580,'TBA6071','SK PELONG','PPD SETIU',5,99,'KG. BUKIT GENTING PELONG','09-8242627','CO8','SETIU022 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(581,'TBA6073','SK MERBAU MENYUSUT','PPD SETIU',5,31,'SUNGAI TONG','09-8241623','CO8','SETIU023 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(582,'TBA6074','SK BUKIT MUNDOK','PPD SETIU',3,16,'NO. 48, PETI SURAT MASYARAKAT','09-8240926','CO8','SETIU024 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(583,'TBA6075','SK KG BUKIT ULU NERUS','PPD SETIU',9,40,'KAMPUNG BUKIT ULU NERUS','09-8240642','CO8','SETIU025 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(584,'TBA6080','SK PAK BA','PPD SETIU',9,18,'KG. BARU PAK BA SG TONG','09-8241923','CO8','SETIU026 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(585,'TBA6082','SK KAMPUNG FIKRI SUNGAI TONG','PPD SETIU',3,45,'KAMPUNG FIKRI SUNGAI TONG','09-8242796','CO8','SETIU027 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(586,'TBA6084','SK SUNGAI LAS','PPD SETIU',3,17,'KAMPUNG SUNGAI LAS','09-8244641','CO8','SETIU028 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(587,'TBA6085','SK RHU SEPULUH','PPD SETIU',5,131,'KAMPUNG RHU SEPULUH','09-8247197','CO8','SETIU029 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(588,'TBA6086','SK BANGGOL','PPD SETIU',9,176,'KAMPUNG BANGGOL JALAN PENARIK','09-6092337','CO8','SETIU030 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(589,'TBA6087','SK PANCHOR MERAH','PPD SETIU',2,124,'KAMPUNG PANCHOR MERAH','09-6923626','CO8','SETIU031 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(590,'TBA6088','SK PERMAISURI','PPD SETIU',5,63,'JALAN BINJAI MANIS','09-6097506','CO8','SETIU032 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(591,'TBA6089','SK SUNGAI LEREK','PPD SETIU',5,131,'KG. SUNGAI LEREK','09-6576048','CO8','SETIU033 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(592,'TBA6090','SK LEMBAH BIDONG','PPD SETIU',7,88,'MERANG','09-6531387','CO8','SETIU034 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(593,'TBA6091','SK LEMBAH JAYA','PPD SETIU',3,44,'BANDAR PERMAISURI','09-6092500','CO8','SETIU035 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(594,'TBA6092','SK SERI KASAR','PPD SETIU',3,47,'KAMPUNG KASAR','09-8242622','CO8','SETIU036 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(595,'TBA6113','SK KAMPONG JAYA','PPD SETIU',3,27,'SG TONG','09-8240021','CO8','SETIU037 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(596,'TBA6114','SK PAYONG BARU','PPD SETIU',5,33,'KAMPUNG PAYONG','09-8242629','CO8','SETIU038 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(597,'TBA6115','SK KAMPONG TAYOR','PPD SETIU',9,20,'W/POS TAYOR TENGAH','09-8241061','CO8','SETIU039 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(598,'TBA6116','SK BARI (SG TONG)','PPD SETIU',5,23,'SUNGAI TONG','09-8240826','CO8','SETIU040 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(599,'TBA6117','SK JELAPANG','PPD SETIU',2,113,'KAMPUNG JELAPANG','09-8241260','CO8','SETIU041 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(600,'TBA6118','SK SAUJANA','PPD SETIU',9,234,'SAUJANA','09-6021198','CO8','SETIU042 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(601,'TBA6119','SK SERI LANGKAP','PPD SETIU',2,96,'LOT 5388, KG. SUNGAI TONG','09-6572352','CO8','SETIU043 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(602,'TBA7001','SK DARAT BATU RAKIT','PPD KUALA NERUS',7,136,'KG. DARAT BATU RAKIT','09-6694177','CO8','KUALANERUS001 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(603,'TBA7002','SK BUKIT TUNGGAL','PPD KUALA NERUS',7,303,'KAMPUNG BUKIT TUNGGAL','09-6664263','CO8','KUALANERUS002 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(604,'TBA7003','SK SEBERANG TAKIR','PPD KUALA NERUS',7,185,'KAMPUNG SEBERANG TAKIR','09-6626242','CO8','KUALANERUS003 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(605,'TBA7004','SK MENGABANG TELIPOT','PPD KUALA NERUS',7,200,'KG. MENGABANG TELIPOT','09-6696266','CO8','KUALANERUS004 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(606,'TBA7005','SK BUKIT GUNTONG','PPD KUALA NERUS',7,225,'KG. BUKIT GUNTONG TEPOH','09-6664364','CO8','KUALANERUS005 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(607,'TBA7006','SK BATU RAKIT','PPD KUALA NERUS',7,266,'JALAN BATU RAKIT','09-6696227','CO8','KUALANERUS006 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(608,'TBA7007','SK MARAS','PPD KUALA NERUS',7,152,'KG. MARAS','09-6696655','CO8','KUALANERUS007 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(609,'TBA7008','SK PAGAR BESI','PPD KUALA NERUS',7,267,'BATU RAKIT','09-6697161','CO8','KUALANERUS008 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(610,'TBA7009','SK BUKIT CHEMPAKA','PPD KUALA NERUS',3,113,'W/POS WAKAF MESIRA','09-6697851','CO8','KUALANERUS009 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(611,'TBA7010','SK BUKIT WAN','PPD KUALA NERUS',9,123,'KAMPUNG BUKIT WAN','09-6691126','CO8','KUALANERUS010 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(612,'TBA7011','SK BUKIT NANAS','PPD KUALA NERUS',7,158,'TEPOH','09-6625164','CO8','KUALANERUS011 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(613,'TBA7012','SK PECHAH ROTAN','PPD KUALA NERUS',7,32,'BATU RAKIT','09-6693636','CO8','KUALANERUS012 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(614,'TBA7013','SK TOK JEMBAL','PPD KUALA NERUS',7,171,'KG. TOK JEMBAL','09-6621255','CO8','KUALANERUS013 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(615,'TBA7014','SK TOK JIRING','PPD KUALA NERUS',8,142,'KAMPUNG TOK JIRING','09-6664637','CO8','KUALANERUS014 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(616,'TBA7015','SK KAMPUNG GEMUROH','PPD KUALA NERUS',7,106,'W/P TEPOH','09-6667882','CO8','KUALANERUS015 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(617,'TBA7016','SK PULAU REDANG','PPD KUALA NERUS',7,89,'KG. BARU PULAU REDANG','09-6307600','CO8','KUALANERUS016 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(618,'TBA7017','SK LKTP BELARA','PPD KUALA NERUS',9,48,'FELDA BELARA','09-6576182','CO8','KUALANERUS017 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(619,'TBA7018','SK BUKIT TOK BENG','PPD KUALA NERUS',7,113,'JALAN TENGKU AMPUAN BARIAH','09-6626236','CO8','KUALANERUS018 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(620,'TBA7019','SK KOMPLEKS MENGABANG TELIPOT','PPD KUALA NERUS',8,155,'WAKAF TENGAH','09-6696466','CO8','KUALANERUS019 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(621,'TBA7020','SK GONG BADAK','PPD KUALA NERUS',7,241,'GONG BADAK','09-6698368','CO8','KUALANERUS020 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(622,'TBA7021','SK TELUK KETAPANG','PPD KUALA NERUS',5,251,'SEBERANG TAKIR','09-6665885','CO8','KUALANERUS021 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(623,'TBA7022','SK KOMPLEKS GONG BADAK','PPD KUALA NERUS',7,198,'JALAN GONG BADAK','09-6672472','CO8','KUALANERUS022 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(624,'TBA7023','SK BUKIT TUMBUH','PPD KUALA NERUS',7,185,'BUKIT TUMBUH','09-6673455','CO8','KUALANERUS023 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(625,'TBA7024','SK TANJUNG GELAM','PPD KUALA NERUS',7,133,'KAMPUNG PAK TUYU, MENGABANG TELIPOT','09-6699510','CO8','KUALANERUS024 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(626,'TBA7025','SK PADANG AIR','PPD KUALA NERUS',8,126,'BT 9  JALAN KOTA BHARU','09-6671058','CO8','KUALANERUS025 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(627,'TBA7026','SK KOMPLEKS SEBERANG TAKIR','PPD KUALA NERUS',7,317,'KAMPUNG BATIN, SEBERANG TAKIR','09-6672510','CO8','KUALANERUS026 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(628,'TBA7027','SK INSTITUT PENDIDIKAN GURU KAMPUS DATO RAZALI ISMAIL','PPD KUALA NERUS',7,94,'IPG KAMPUS DATO` RAZALI ISMAIL','09-6696470','CO8','KUALANERUS027 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(629,'TBA7028','SK PADANG KEMUNTING','PPD KUALA NERUS',2,134,'KG. PADANG KEMUNTING BATU RAKIT','09-6694548','CO8','KUALANERUS028 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889'),(630,'TBA7029','SK KOMPLEKS TEMBESU','PPD KUALA NERUS',7,211,'KAMPUNG BUKIT LAPAN','09-6661106','CO8','KUALANERUS029 (CO8/NOVEMBER/2025)','QT240000000030911','CO250000000558889');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spoilage_logs`
--

DROP TABLE IF EXISTS `spoilage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spoilage_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `photo_path` text DEFAULT NULL,
  `claim_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `supplier_submitted_at` date DEFAULT NULL,
  `cn_number` varchar(50) DEFAULT NULL,
  `cn_date` date DEFAULT NULL,
  `reported_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spoilage_logs`
--

LOCK TABLES `spoilage_logs` WRITE;
/*!40000 ALTER TABLE `spoilage_logs` DISABLE KEYS */;
INSERT INTO `spoilage_logs` VALUES (5,9,1,'Pest Damage','test 5','2026/02/14/1771051786_7255.png','Approved','2026-02-20','CN 12124524354','2026-02-24','2026-02-14','2026-02-14 06:49:46'),(7,5,5,'Crushed','test 6','2026/02/14/1771052607_bd3d.png','Pending',NULL,NULL,NULL,'2026-02-14','2026-02-14 07:03:27'),(8,44,24,'Leaking','damage box','2026/04/12/1775960706_7bb6.jpg','Pending',NULL,NULL,NULL,'2026-04-12','2026-04-12 02:25:06'),(9,52,24,'Leaking','','2026/04/19/1776590239_c7b5.jpeg','Pending',NULL,NULL,NULL,'2026-04-19','2026-04-19 09:17:19');
/*!40000 ALTER TABLE `spoilage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-01 03:24:52'),(2,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-01 04:16:14'),(3,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-01 04:20:40'),(4,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-01 04:20:42'),(5,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 04:21:44'),(6,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 04:21:47'),(7,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-01 04:22:19'),(8,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-01 04:36:03'),(9,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-01 04:36:06'),(10,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-01 04:51:41'),(11,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 04:51:44'),(12,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 05:01:30'),(13,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-01 05:01:33'),(14,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-01 07:44:18'),(15,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-01 07:44:20'),(16,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-01 07:49:42'),(17,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 07:49:46'),(18,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 07:50:24'),(19,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 07:50:25'),(20,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 08:09:11'),(21,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-01 08:09:15'),(22,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-01 09:02:57'),(23,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 09:03:05'),(24,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 09:04:50'),(25,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-01 09:04:52'),(26,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-01 09:24:14'),(27,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 01:41:39'),(28,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 02:31:20'),(29,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 02:31:21'),(30,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 03:54:12'),(31,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 03:54:16'),(32,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 04:09:48'),(33,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 04:09:49'),(34,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 04:27:37'),(35,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 04:27:44'),(36,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 04:42:46'),(37,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-02 04:43:22'),(38,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-02 04:52:24'),(39,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-02 04:52:28'),(40,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-04 03:09:47'),(41,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-04 04:19:05'),(42,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 04:19:21'),(43,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 05:25:16'),(44,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 05:25:17'),(45,11,'shahrul','Stock Transfer','inventory_batches',46,'Pindah 5 ctn dari Warehouse ke Buffer','::1','2026-07-04 06:46:14'),(46,11,'shahrul','Stock Transfer','inventory_batches',46,'Pindah 5 ctn dari Warehouse ke Buffer','::1','2026-07-04 06:46:41'),(47,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 08:08:22'),(48,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 08:19:54'),(49,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 08:26:55'),(50,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 08:46:35'),(51,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 08:56:49'),(52,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:08:06'),(53,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:08:09'),(54,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:08:11'),(55,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:08:15'),(56,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:11:57'),(57,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:13:02'),(58,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:13:03'),(59,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:13:04'),(60,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:13:33'),(61,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:13:36'),(62,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:56:01'),(63,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-04 09:56:03'),(64,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-04 09:57:16'),(65,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 02:34:57'),(66,11,'shahrul','Received Inbound Stock','inbound_logs',25,'Stok diterima: GRN ID 25, Supplier DO 1010019801 (2 jenis item).','::1','2026-07-05 05:05:59'),(67,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 06:36:09'),(68,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 06:36:11'),(69,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 06:55:15'),(70,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 06:55:19'),(71,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-05 07:17:24'),(72,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 07:41:25'),(73,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 07:41:27'),(74,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 07:46:46'),(75,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 07:46:48'),(76,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 07:48:46'),(77,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 07:48:48'),(78,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 07:55:11'),(79,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 07:55:13'),(80,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 08:06:37'),(81,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 08:06:39'),(82,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 08:09:03'),(83,11,'shahrul','User Logged In','users_hub',11,'Pengguna \'shahrul\' berjaya log masuk dari IP ::1.','::1','2026-07-05 08:09:04'),(84,11,'shahrul','User Logged Out','users_hub',11,'Pengguna \'shahrul\' log keluar secara manual.','::1','2026-07-05 08:09:19'),(85,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-05 08:09:22'),(86,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 01:46:13'),(87,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-06 01:57:42'),(88,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 02:07:51'),(89,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-06 02:31:43'),(90,10,'fadiah','User Logged In','users_hub',10,'Pengguna \'fadiah\' berjaya log masuk dari IP ::1.','::1','2026-07-06 02:31:45'),(91,10,'fadiah','User Logged Out','users_hub',10,'Pengguna \'fadiah\' log keluar secara manual.','::1','2026-07-06 03:01:31'),(92,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 03:01:50'),(93,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-06 03:13:36'),(94,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 03:13:37'),(95,1,'admin','Received Stock (Single)','inbound_logs',26,'Single GRN diproses: ID 26, Rujukan SR-20260706-BBD8 (Qty: 75 ctn).','::1','2026-07-06 03:14:34'),(96,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-06 03:17:20'),(97,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 03:17:25'),(98,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 03:33:35'),(99,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-06 09:26:34'),(100,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 09:26:36'),(101,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-06 09:28:56'),(102,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-06 09:28:57'),(103,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-06 09:33:37'),(104,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 01:25:54'),(105,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 01:46:19'),(106,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 01:46:21'),(107,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 02:00:51'),(108,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 02:00:52'),(109,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 06:27:32'),(110,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 06:27:34'),(111,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 08:10:10'),(112,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 08:10:12'),(113,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 08:10:33'),(114,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 08:10:35'),(115,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 08:24:32'),(116,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 08:24:33'),(117,1,'admin','Received Stock (Single)','inbound_logs',27,'Single GRN diproses: ID 27, Rujukan SR-20260707-7AB2 (Qty: 75 ctn).','::1','2026-07-07 08:25:50'),(118,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 08:31:34'),(119,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 08:31:37'),(120,1,'admin','Received Stock (Single)','inbound_logs',28,'Single GRN diproses: ID 28, Rujukan SR-20260707-1E7D (Qty: 144 ctn).','::1','2026-07-07 08:39:00'),(121,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 08:41:11'),(122,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 08:41:13'),(123,1,'admin','Adjusted Pallet Stock','pallet_ledger',NULL,'Pallet red adjusted: add 7. Notes: stock yang dah ada di warehouse \r\n','::1','2026-07-07 09:03:46'),(124,1,'admin','Adjusted Pallet Stock','pallet_ledger',NULL,'Pallet red adjusted: sub 4. Notes: dah jual','::1','2026-07-07 09:04:17'),(125,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-07 09:22:57'),(126,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-07 09:22:59'),(127,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 01:25:59'),(128,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 02:58:23'),(129,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 02:58:25'),(130,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 03:00:04'),(131,1,'admin','User Logged In','users_hub',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 03:00:09'),(132,1,'admin','User Logged Out','users_hub',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 03:00:10'),(133,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:08:33'),(134,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 04:10:11'),(135,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:10:12'),(136,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 04:37:28'),(137,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:37:32'),(138,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 04:37:35'),(139,2,'ayu','User Logged In','users',2,'Pengguna \'ayu\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:39:28'),(140,2,'ayu','User Logged Out','users',2,'Pengguna \'ayu\' log keluar secara manual.','::1','2026-07-09 04:40:03'),(141,3,'dora','User Logged In','users',3,'Pengguna \'dora\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:40:27'),(142,3,'dora','User Logged Out','users',3,'Pengguna \'dora\' log keluar secara manual.','::1','2026-07-09 04:42:39'),(143,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:42:42'),(144,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 04:56:40'),(145,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 04:57:48'),(146,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 06:39:43'),(147,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 06:39:45'),(148,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 06:39:46'),(149,3,'dora','User Logged In','users',3,'Pengguna \'dora\' berjaya log masuk dari IP ::1.','::1','2026-07-09 06:39:54'),(150,3,'dora','User Logged Out','users',3,'Pengguna \'dora\' log keluar secara manual.','::1','2026-07-09 06:40:11'),(151,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 06:40:15'),(152,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 08:25:55'),(153,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 08:26:04'),(154,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-09 09:48:18'),(155,3,'dora','User Logged In','users',3,'Pengguna \'dora\' berjaya log masuk dari IP ::1.','::1','2026-07-09 09:48:21'),(156,3,'dora','User Logged Out','users',3,'Pengguna \'dora\' log keluar secara manual.','::1','2026-07-09 09:48:25'),(157,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-09 09:48:31'),(158,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-11 01:35:54'),(159,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-11 01:37:41'),(160,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-11 01:37:43'),(161,1,'admin','User Logged Out','users',1,'Pengguna \'admin\' log keluar secara manual.','::1','2026-07-11 01:37:46'),(162,3,'dora','User Logged In','users',3,'Pengguna \'dora\' berjaya log masuk dari IP ::1.','::1','2026-07-11 01:37:49'),(163,3,'dora','User Logged Out','users',3,'Pengguna \'dora\' log keluar secara manual.','::1','2026-07-11 01:38:12'),(164,1,'admin','User Logged In','users',1,'Pengguna \'admin\' berjaya log masuk dari IP ::1.','::1','2026-07-11 01:41:04');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jomcha_requests`
--

DROP TABLE IF EXISTS `jomcha_requests`;
CREATE TABLE `jomcha_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_date` date NOT NULL,
  `requested_by` varchar(100) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `jomcha_request_items`
--

DROP TABLE IF EXISTS `jomcha_request_items`;
CREATE TABLE `jomcha_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty_requested` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_jomcha_request` (`request_id`),
  KEY `fk_jomcha_product` (`product_id`),
  CONSTRAINT `fk_jomcha_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_jomcha_request` FOREIGN KEY (`request_id`) REFERENCES `jomcha_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `jomcha_stock_takes`
--

DROP TABLE IF EXISTS `jomcha_stock_takes`;
CREATE TABLE `jomcha_stock_takes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `jcb_chiller_1_ctn` int(11) NOT NULL DEFAULT 0,
  `jcb_chiller_1_pcs` int(11) NOT NULL DEFAULT 0,
  `jcb_chiller_2_ctn` int(11) NOT NULL DEFAULT 0,
  `jcb_chiller_2_pcs` int(11) NOT NULL DEFAULT 0,
  `jcb_rack_ctn` int(11) NOT NULL DEFAULT 0,
  `jcb_rack_pcs` int(11) NOT NULL DEFAULT 0,
  `mms_rack_ctn` int(11) NOT NULL DEFAULT 0,
  `mms_rack_pcs` int(11) NOT NULL DEFAULT 0,
  `mms_chiller_1_ctn` int(11) NOT NULL DEFAULT 0,
  `mms_chiller_1_pcs` int(11) NOT NULL DEFAULT 0,
  `mms_chiller_2_ctn` int(11) NOT NULL DEFAULT 0,
  `mms_chiller_2_pcs` int(11) NOT NULL DEFAULT 0,
  `mms_freezer_meat_ctn` int(11) NOT NULL DEFAULT 0,
  `mms_freezer_meat_pcs` int(11) NOT NULL DEFAULT 0,
  `mms_freezer_ice_cream_ctn` int(11) NOT NULL DEFAULT 0,
  `mms_freezer_ice_cream_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_rack_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_rack_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_pallet_1_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_pallet_1_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_pallet_2_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_pallet_2_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_chiller_1_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_chiller_1_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_chiller_2_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_chiller_2_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_freezer_1_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_freezer_1_pcs` int(11) NOT NULL DEFAULT 0,
  `sa_freezer_2_ctn` int(11) NOT NULL DEFAULT 0,
  `sa_freezer_2_pcs` int(11) NOT NULL DEFAULT 0,
  `physical_qty` int(11) NOT NULL,
  `theoretical_qty` int(11) NOT NULL,
  `variance` int(11) NOT NULL,
  `taken_by` varchar(100) NOT NULL,
  `take_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `jomcha_stock_takes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `jomcha_sales`
--

DROP TABLE IF EXISTS `jomcha_sales`;
CREATE TABLE `jomcha_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `jomcha_sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `jomcha_damaged_stock`
--

DROP TABLE IF EXISTS `jomcha_damaged_stock`;
CREATE TABLE `jomcha_damaged_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `image_data` longtext DEFAULT NULL,
  `reported_by` varchar(100) NOT NULL,
  `issue_type` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Dilaporkan',
  `returned_by` varchar(100) DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `jomcha_damaged_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','dealer') DEFAULT 'dealer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `hd_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user_hd` (`hd_id`),
  CONSTRAINT `fk_user_hd` FOREIGN KEY (`hd_id`) REFERENCES `hds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','MMS Administrator','mms123','admin',1,NULL),(2,'ayu','Siti Noor Idayu','mms1ayu','dealer',1,9),(3,'dora','Noidora Abdullah','mms2dora','dealer',1,5),(4,'fizi','Mohd Hafizi','mms3fizi','dealer',1,2),(5,'mila','Mila Admin','2026mila','admin',1,NULL),(6,'mms','Ahmad Tarmizi','620mms','dealer',1,7),(7,'sya','Sharifah Munirah','mms4sya','dealer',1,8),(8,'wali','Wali Khan','mms5wali','dealer',1,3),(9,'zul','Zul','zul620','dealer',1,NULL),(10,'fadiah','Fadiah','mmsfadiah','admin',1,NULL),(11,'shahrul','Shahrul','mmsshahrul','admin',1,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `v_name` varchar(50) NOT NULL,
  `v_capacity` int(11) NOT NULL,
  `owner` varchar(50) DEFAULT 'admin',
  `is_enabled` tinyint(1) DEFAULT 1,
  `v_priority` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (47,'QCQ620',230,'admin',1,1),(48,'TDD260',440,'admin',1,1),(50,'Mat Can 1',1440,'admin',1,1),(51,'Mat Can 2',1440,'admin',1,1),(62,'BMX1592',36,'mms',1,1),(63,'Navara',200,'fizi',1,1),(64,'QCQ620',230,'wali',1,1),(65,'TDD260',440,'wali',1,1),(66,'Mat Can 1',1440,'wali',1,1),(67,'Mat Can 2',1440,'wali',1,1);
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-11  9:48:43
