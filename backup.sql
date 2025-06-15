-- MySQL dump 10.13  Distrib 9.3.0, for macos15.2 (arm64)
--
-- Host: localhost    Database: whimsicalfrog
-- ------------------------------------------------------
-- Server version	9.3.0

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
-- Table structure for table `discount_codes`
--

DROP TABLE IF EXISTS `discount_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discount_codes` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'percentage',
  `value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT '0.00',
  `max_uses` int DEFAULT '0',
  `current_uses` int DEFAULT '0',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discount_codes`
--

LOCK TABLES `discount_codes` WRITE;
/*!40000 ALTER TABLE `discount_codes` DISABLE KEYS */;
INSERT INTO `discount_codes` VALUES ('DC001','SUMMER20','percentage',20.00,50.00,100,12,'2025-05-29','2025-06-28','active'),('DC002','WELCOME10','percentage',10.00,0.00,0,45,'2025-04-09','2026-04-09','active'),('DC003','FREESHIP','fixed',5.99,25.00,200,87,'2025-05-09','2025-06-23','active');
/*!40000 ALTER TABLE `discount_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_campaigns`
--

DROP TABLE IF EXISTS `email_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_campaigns` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_audience` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'all',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_campaigns`
--

LOCK TABLES `email_campaigns` WRITE;
/*!40000 ALTER TABLE `email_campaigns` DISABLE KEYS */;
INSERT INTO `email_campaigns` VALUES ('EC001','Summer Sale Announcement','🌞 Summer Sale - 20% Off All Products!','<h1>Summer Sale!</h1><p>Enjoy 20% off all products this summer. Use code SUMMER20 at checkout.</p>','all','draft','2025-06-08 23:54:10',NULL),('EC002','New Product Launch','Introducing Our New Custom Tumblers!','<h1>New Products Alert!</h1><p>Check out our new line of custom tumblers with unique designs.</p>','customers','scheduled','2025-06-06 23:54:10','2025-06-11 23:54:10'),('EC003','Customer Feedback Request','We Value Your Feedback!','<h1>How Did We Do?</h1><p>Please take a moment to share your experience with our products and service.</p>','customers','sent','2025-05-29 23:54:10','2025-06-01 23:54:10');
/*!40000 ALTER TABLE `email_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_subscribers`
--

DROP TABLE IF EXISTS `email_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_subscribers` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscribe_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_email_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_subscribers`
--

LOCK TABLES `email_subscribers` WRITE;
/*!40000 ALTER TABLE `email_subscribers` DISABLE KEYS */;
INSERT INTO `email_subscribers` VALUES ('ES001','customer@example.com','Test','Customer','active','website','2025-05-09 23:54:10','2025-06-01 23:54:10'),('ES002','jane.doe@example.com','Jane','Doe','active','checkout','2025-05-24 23:54:10','2025-06-01 23:54:10'),('ES003','john.smith@example.com','John','Smith','active','website','2025-06-03 23:54:10',NULL);
/*!40000 ALTER TABLE `email_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `productId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sku` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stockLevel` int DEFAULT NULL,
  `reorderPoint` int DEFAULT NULL,
  `costPrice` decimal(10,2) DEFAULT '0.00',
  `retailPrice` decimal(10,2) DEFAULT '0.00',
  `imageUrl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES ('I001','TS001','Whimsical Frog T-Shirt','Order your whimsical frog t-shirt today!','WF-TS-001',2,10,9.00,40.00,'images/products/TS001A.png'),('I002','TU001','Frog Painter Tumbler','Painter frog on a tumbler. How silly!','WF-TU-001',38,5,10.02,30.00,'images/products/TU001A.png'),('I003','AW001','Frog Painter','Get the frog painter printed on a sheet of photo paper','WF-AR-001',19,7,4.00,43.00,'images/products/AW001A.png'),('I004','GN001','Window Wrap, Small Business','Size: 3x4 ft','WF-WW-001',13,3,45.00,289.99,'images/products/GN001A.png'),('I005','TU002','Frog Tumbler','Get the whimsical frog on a 24oz tumbler','WF-TU-002',0,6,7.50,30.00,'images/products/TU002A.png'),('I006','MG001','Frog Mug','Coffee mug with whimsical frog on the side!','WF-SU-002',0,5,10.00,34.99,'images/products/MG001A.png'),('I007','TU002','Frog Tumbler 30oz','30oz Tumbler with Whimsical Frog on it!','WF-TU-003',0,5,8.00,34.99,'images/products/TU002A.png'),('I008','TS002','Clown Frog','Order your clown frog t-shirt today!','WF-TS-002',0,5,10.00,40.00,'images/products/TS002A.webp');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_energy`
--

DROP TABLE IF EXISTS `inventory_energy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_energy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inventoryId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_energy_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_energy`
--

LOCK TABLES `inventory_energy` WRITE;
/*!40000 ALTER TABLE `inventory_energy` DISABLE KEYS */;
INSERT INTO `inventory_energy` VALUES (3,'I002','power',3.01),(4,'I005','lights',1.00),(5,'I003','fire',10.01),(6,'I003','wood',2.00),(7,'I001','fire',2.00);
/*!40000 ALTER TABLE `inventory_energy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_equipment`
--

DROP TABLE IF EXISTS `inventory_equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inventoryId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_equipment_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_equipment`
--

LOCK TABLES `inventory_equipment` WRITE;
/*!40000 ALTER TABLE `inventory_equipment` DISABLE KEYS */;
INSERT INTO `inventory_equipment` VALUES (1,'I003','treadmill',13.00),(3,'I001','hitch',2.00);
/*!40000 ALTER TABLE `inventory_equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_labor`
--

DROP TABLE IF EXISTS `inventory_labor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_labor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inventoryId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_labor_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_labor`
--

LOCK TABLES `inventory_labor` WRITE;
/*!40000 ALTER TABLE `inventory_labor` DISABLE KEYS */;
INSERT INTO `inventory_labor` VALUES (2,'I002','digging',5.01),(3,'I005','swimming',2.00),(4,'I003','holy',3.00),(5,'I001','pedaling',2.00);
/*!40000 ALTER TABLE `inventory_labor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_materials`
--

DROP TABLE IF EXISTS `inventory_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inventoryId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_materials_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_materials`
--

LOCK TABLES `inventory_materials` WRITE;
/*!40000 ALTER TABLE `inventory_materials` DISABLE KEYS */;
INSERT INTO `inventory_materials` VALUES (1,'I001','cotton',3.00),(3,'I002','linen',2.00),(4,'I005','wood',4.99),(5,'I003','linen',5.00),(6,'I003','cotton',1.01);
/*!40000 ALTER TABLE `inventory_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_id_migration_backup`
--

DROP TABLE IF EXISTS `order_id_migration_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_id_migration_backup` (
  `old_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_id` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `migration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`old_id`),
  KEY `idx_new_id` (`new_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_id_migration_backup`
--

LOCK TABLES `order_id_migration_backup` WRITE;
/*!40000 ALTER TABLE `order_id_migration_backup` DISABLE KEYS */;
INSERT INTO `order_id_migration_backup` VALUES ('C001-250613-LOC-209','01F13L23','2025-06-13 23:03:41'),('O12345','01F08P46','2025-06-13 23:03:41'),('O23456','02F06P18','2025-06-13 23:03:41'),('O34567','02F01P84','2025-06-13 23:03:41'),('O684994b9c3770','01F11P48','2025-06-13 23:03:41'),('O68499586ba8aa','01F11P31','2025-06-13 23:03:41'),('O6849960b9560f','01F11P99','2025-06-13 23:03:41'),('O68499c3d39a19','01F11P17','2025-06-13 23:03:41'),('O6849a12969a7c','01F11P58','2025-06-13 23:03:41'),('O6849aa8095023','01F11P83','2025-06-13 23:03:41'),('TEST001','01F09P07','2025-06-13 23:03:41'),('TEST002','01F09P61','2025-06-13 23:03:41'),('TEST003','01F09P87','2025-06-13 23:03:41');
/*!40000 ALTER TABLE `order_id_migration_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` varchar(32) NOT NULL,
  `orderId` varchar(25) DEFAULT NULL,
  `productId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `orderId` (`orderId`),
  KEY `productId` (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES ('OI001','64F14P61','TS001',1,19.97),('OI002','01F08P46','TS001',2,19.99),('OI003','01F08P46','TU001',1,19.99),('OI004','02F06P18','MG001',1,24.99),('OI005','02F01P84','AW001',3,49.99),('OI006','01F11P17','TS001',1,19.97),('OI007','01F11P17','AW001',1,12.98),('OI008','01F11P31','AW001',1,49.99),('OI009','01F11P48','AW001',1,49.99),('OI010','01F11P58','TU002',2,18.99),('OI011','01F11P58','AW001',1,12.98),('OI012','01F11P58','TU001',1,14.98),('OI013','01F11P58','GN001',3,89.99),('OI014','01F11P58','MG001',2,24.99),('OI015','01F11P83','TS002',1,19.98),('OI016','01F11P83','GN001',2,89.99),('OI017','01F11P99','MG001',1,14.99),('OI018','01F13L23','TS001',2,19.97),('OI019','01F13L23','GN001',1,89.99),('OI020','01F13L23','TS002',1,19.98),('OI021','01F13P82','TS002',2,19.98),('OI022','01F13P82','MG001',3,24.99),('OI023','17F14P82','TU002',3,18.99),('OI024','62F15P45','TU001',1,14.98);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items_backup_migration`
--

DROP TABLE IF EXISTS `order_items_backup_migration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items_backup_migration` (
  `id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `orderId` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `productId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items_backup_migration`
--

LOCK TABLES `order_items_backup_migration` WRITE;
/*!40000 ALTER TABLE `order_items_backup_migration` DISABLE KEYS */;
INSERT INTO `order_items_backup_migration` VALUES ('OI001','64F14P61','TS001',1,19.97),('OI002','01F08P46','TS001',2,19.99),('OI003','01F08P46','TU001',1,19.99),('OI004','02F06P18','MG001',1,24.99),('OI005','02F01P84','AW001',3,49.99),('OI006','01F11P17','TS001',1,19.97),('OI007','01F11P17','AW001',1,12.98),('OI008','01F11P31','AW001',1,49.99),('OI009','01F11P48','AW001',1,49.99),('OI010','01F11P58','TU002',2,18.99),('OI011','01F11P58','AW001',1,12.98),('OI012','01F11P58','TU001',1,14.98),('OI013','01F11P58','GN001',3,89.99),('OI014','01F11P58','MG001',2,24.99),('OI015','01F11P83','TS002',1,19.98),('OI016','01F11P83','GN001',2,89.99),('OI017','01F11P99','MG001',1,14.99),('OI018','01F13L23','TS001',2,19.97),('OI019','01F13L23','GN001',1,89.99),('OI020','01F13L23','TS002',1,19.98),('OI021','01F13P82','TS002',2,19.98),('OI022','01F13P82','MG001',3,24.99);
/*!40000 ALTER TABLE `order_items_backup_migration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` varchar(25) NOT NULL,
  `userId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paymentMethod` varchar(50) NOT NULL DEFAULT 'Credit Card',
  `checkNumber` varchar(64) DEFAULT NULL COMMENT 'Check number if payment method is Check',
  `shippingAddress` text,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `trackingNumber` varchar(100) DEFAULT NULL,
  `paymentStatus` varchar(20) NOT NULL DEFAULT 'Pending',
  `paymentDate` date DEFAULT NULL COMMENT 'Date when the payment was received or processed',
  `paymentNotes` text COMMENT 'Specific notes related to the payment transaction',
  `fulfillmentNotes` text,
  `shippingMethod` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES ('01F08P46','F13001',59.97,'Credit Card',NULL,'{\"name\":\"Admin User\",\"street\":\"123 Main St\",\"city\":\"Dawsonville\",\"state\":\"GA\",\"zip\":\"30534\"}','Shipped','2025-06-08 14:25:15','','Received',NULL,NULL,NULL,'Customer Pickup'),('01F09P07','F13001',45.99,'Check','1234',NULL,'Pending','2025-06-09 19:27:21',NULL,'Received',NULL,'Check cleared on 6/9/25','2025-06-12 20:52 - test','Customer Pickup'),('01F09P61','F13001',29.99,'Cash',NULL,NULL,'Pending','2025-06-09 19:27:21',NULL,'Received','2025-06-09','Cash payment received in store',NULL,'Customer Pickup'),('01F09P87','F13001',67.50,'Check','5678',NULL,'Pending','2025-06-09 19:27:21',NULL,'Pending',NULL,'Check #5678 - customer promises to deliver tomorrow',NULL,'Customer Pickup'),('01F11P17','F13001',32.95,'Cash',NULL,NULL,'Pending','2025-06-11 19:09:49',NULL,'Pending',NULL,'2025-06-13 21:27 - they gave us a down payment of $1. Woohoo!','2025-06-13 21:26 - it is printing now','Customer Pickup'),('01F11P31','F13001',53.99,'Cash',NULL,NULL,'Pending','2025-06-11 18:41:10',NULL,'Pending',NULL,NULL,NULL,'Customer Pickup'),('01F11P48','F13001',53.99,'Cash','',NULL,'Pending','2025-06-11 18:37:45','','Received',NULL,'',NULL,'Customer Pickup'),('01F11P58','F13001',385.89,'Cash',NULL,NULL,'Delivered','2025-06-11 19:30:49',NULL,'Received','2025-06-10','2025-06-13 21:16 - gave me cash','2025-06-13 20:10 - test fulnote\n2025-06-13 21:08 - test fulfilment note','Customer Pickup'),('01F11P83','F13001',199.96,'Cash',NULL,NULL,'Delivered','2025-06-11 20:10:40',NULL,'Received','2025-06-13',NULL,'2025-06-13 13:13 - test fullfilment note\n2025-06-13 13:14 - another test fulfilment note\n2025-06-13 16:23 - test ful\n2025-06-13 18:59 - gfgf\n2025-06-13 18:59 - eer\n2025-06-13 18:59 - ttt\n2025-06-13 18:59 - jjj\n2025-06-13 19:03 - gg\n2025-06-13 20:56 - trhrt','Customer Pickup'),('01F11P99','F13001',16.19,'Cash',NULL,NULL,'Pending','2025-06-11 18:43:23',NULL,'Pending',NULL,NULL,NULL,'Customer Pickup'),('01F13L23','F13001',161.90,'Cash',NULL,NULL,'Pending','2025-06-14 01:38:25',NULL,'Pending',NULL,'2025-06-14 22:17 - pay note','2025-06-14 22:17 - fulfil note','USPS'),('01F13P82','U001',124.12,'Cash',NULL,NULL,'Pending','2025-06-14 02:59:15',NULL,'Pending',NULL,NULL,NULL,'Customer Pickup'),('02F01P84','F13002',149.95,'Credit Card',NULL,'{\"name\":\"Test Customer\",\"street\":\"456 Oak Ave\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\"}','Delivered','2025-06-01 14:25:15',NULL,'Received',NULL,NULL,NULL,'Customer Pickup'),('02F06P18','F13002',24.99,'PayPal',NULL,'{\"name\":\"Test Customer\",\"street\":\"456 Oak Ave\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\"}','Shipped','2025-06-06 14:25:15','1234','Received',NULL,NULL,NULL,'Customer Pickup'),('17F14P82','F14009',61.53,'Check',NULL,NULL,'Processing','2025-06-14 05:11:24',NULL,'Received','2025-06-14',NULL,NULL,'USPS'),('62F15P45','F13001',16.18,'Cash',NULL,NULL,'Pending','2025-06-16 00:42:11',NULL,'Pending',NULL,NULL,NULL,'Customer Pickup'),('64F14P61','F14004',19.97,'Cash',NULL,NULL,'Processing','2025-06-14 04:20:52',NULL,'Received','2025-06-14',NULL,NULL,'Local Delivery');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_primary` (`product_id`,`is_primary`),
  KEY `idx_sort` (`product_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,'TS001','images/products/TS001A.png',1,0,'T-Shirt, White, S','2025-06-14 03:18:34','2025-06-15 19:37:08'),(2,'TU001','images/products/TU001A.png',1,0,'Tumbler, 20oz, White','2025-06-14 03:18:34','2025-06-14 03:34:11'),(3,'AW001','images/products/AW001A.png',1,0,'Frog Painter','2025-06-14 03:18:34','2025-06-14 03:34:11'),(4,'GN001','images/products/GN001A.png',1,0,'Window Wrap, Small Business','2025-06-14 03:18:34','2025-06-14 03:34:11'),(5,'TU002','images/products/TU002A.png',1,0,'Custom Tumbler (30oz)','2025-06-14 03:18:34','2025-06-14 03:34:11'),(6,'MG001','images/products/MG001A.png',1,0,'Frog Mug','2025-06-14 03:18:34','2025-06-14 03:34:11'),(7,'TS002','images/products/TS002A.webp',1,0,'Clown Frog','2025-06-14 03:18:34','2025-06-14 03:34:11'),(8,'TEST001','images/products/TEST001A.png',1,0,'Test Product Primary Image','2025-06-14 03:30:59','2025-06-14 03:34:11'),(9,'TEST001','images/products/TEST001B.webp',0,1,'Test Product Image A','2025-06-14 03:30:59','2025-06-14 03:34:11'),(10,'TEST001','images/products/TEST001C.png',0,2,'Test Product Image B','2025-06-14 03:30:59','2025-06-14 03:34:11'),(12,'TS001','images/products/TS001C.png',0,2,'T-Shirt, White, S - Side View','2025-06-15 12:35:11','2025-06-15 19:04:10'),(15,'TS001','images/products/TS001B.webp',0,1,'Whimsical Frog T-Shirt','2025-06-15 19:46:12','2025-06-15 19:46:12'),(16,'TS001','images/products/TS001D.png',0,3,'Whimsical Frog T-Shirt','2025-06-15 19:53:33','2025-06-15 19:53:33');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `productType` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `basePrice` decimal(10,2) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `defaultSKU_Base` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES ('AW001','Frog Painter','Artwork',12.98,'Get the frog painter printed on a sheet of photo paper','ART-PRNT-BASE','Supplier C','Sizes: 8x10, 11x14, 16x20; Paper/Canvas','images/products/AW001A.png'),('GN001','Window Wrap, Small Business','Window Wraps',89.99,'Size: 3x4 ft','WW-BASE','Supplier D','Per sq ft pricing may apply','images/products/GN001A.png'),('MG001','Frog Mug','Sublimation',24.99,'Coffee mug with whimsical frog on the side!','SUB-MUG-BASE','Supplier B','Specify blank type in description','images/products/MG001A.png'),('TS001','Whimsical Frog T-Shirt','T-Shirts',40.00,'Order your whimsical frog t-shirt today!','TEST','Supplier A','Available sizes: S, M, L, XL, XXL; Various colors','images/products/TS001A.png'),('TS002','Clown Frog','T-Shirts',19.98,'Order your clown frog t-shirt today!','TEST-BASE','TBD','New product group','images/products/TS002A.webp'),('TU001','Tumbler, 20oz, White','Tumblers',14.98,'Painter frog on a tumbler. How silly!','TUM20-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/TU001A.png'),('TU002','Custom Tumbler (30oz)','Tumblers',18.99,'30oz Tumbler with Whimsical Frog on it!','TUM30-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/TU002A.png');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_accounts`
--

DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_accounts` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connected` tinyint(1) DEFAULT '0',
  `auth_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_accounts`
--

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
INSERT INTO `social_accounts` VALUES ('SA001','facebook','Whimsical Frog Crafts',1,NULL,'2025-06-08 23:54:10'),('SA002','instagram','@whimsicalfrog',1,NULL,'2025-06-08 23:54:10');
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_posts`
--

DROP TABLE IF EXISTS `social_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_posts` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `scheduled_date` timestamp NULL DEFAULT NULL,
  `posted_date` timestamp NULL DEFAULT NULL,
  `account_id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `social_posts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `social_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_posts`
--

LOCK TABLES `social_posts` WRITE;
/*!40000 ALTER TABLE `social_posts` DISABLE KEYS */;
INSERT INTO `social_posts` VALUES ('SP001','facebook','Check out our new summer collection! Perfect for those hot days. #WhimsicalFrog #SummerVibes','images/products/product_custom-tumbler-20oz.png','scheduled','2025-06-10 23:54:10',NULL,'SA001'),('SP002','instagram','Our new tumblers keep your drinks cold for 24 hours! Perfect for summer adventures. #WhimsicalFrog #StayHydrated','images/products/product_custom-tumbler-30oz.png','posted','2025-06-05 23:54:10','2025-06-05 23:54:10','SA002'),('SP003','facebook','Use code SUMMER20 for 20% off all products this week only! #WhimsicalFrog #SummerSale',NULL,'draft',NULL,NULL,'SA001');
/*!40000 ALTER TABLE `social_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roleType` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstName` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastName` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addressLine1` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addressLine2` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zipCode` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES ('F13001','admin','pass.123','admin@whimsicalfrog.com','Admin','Admin','Admin4','WhimsicalFrog','4047878900','91 Singletree Lane','nothing','Dawsonville','GA','30534'),('F13002','customer','pass.123','customer@example.com','Customer','Customer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),('F13003','sarah','pass.123','sarah@catn8.us','Customer','Customer','Sarah','Graves','6788979763','4765 Fourth Rail Ln','the end','Cumming','GA','30041'),('F14004','testuser2','testpass123','test2@example.com','Customer',NULL,'Test2','User2',NULL,NULL,NULL,NULL,NULL,NULL),('F14005','jon','Liv3itup!','jon@catn8.us','Customer',NULL,'Jon','Graves',NULL,NULL,NULL,NULL,NULL,NULL),('F14006','ezra','Liv3itup!','ezra@catn8.us','Customer',NULL,'Ezra','Rodriguez-Nerey',NULL,NULL,NULL,NULL,NULL,NULL),('F14007','reuel','Liv3itup!','reuel@catn8.us','Customer',NULL,'Reuel','Rodriguez-Nerey',NULL,NULL,NULL,NULL,NULL,NULL),('F14008','veronica','Liv3itup!','veronica@catn8.us','Customer',NULL,'Veronica','Rodriguez',NULL,NULL,NULL,NULL,NULL,NULL),('F14009','trinity','Liv3itup!','trinity.graves@gmail.com','Customer',NULL,'Trinity','Graves',NULL,NULL,NULL,NULL,NULL,NULL),('U962','testuser','testpass123','test@example.com','Customer',NULL,'Test','User',NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-15 17:00:35
