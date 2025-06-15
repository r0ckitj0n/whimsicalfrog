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
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'percentage',
  `value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT '0.00',
  `max_uses` int DEFAULT '0',
  `current_uses` int DEFAULT '0',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discount_codes`
--

LOCK TABLES `discount_codes` WRITE;
/*!40000 ALTER TABLE `discount_codes` DISABLE KEYS */;
INSERT IGNORE INTO `discount_codes` VALUES ('DC001','SUMMER20','percentage',20.00,50.00,100,12,'2025-05-29','2025-06-28','active'),('DC002','WELCOME10','percentage',10.00,0.00,0,45,'2025-04-09','2026-04-09','active'),('DC003','FREESHIP','fixed',5.99,25.00,200,87,'2025-05-09','2025-06-23','active');
/*!40000 ALTER TABLE `discount_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_campaigns`
--

DROP TABLE IF EXISTS `email_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_campaigns` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_audience` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'all',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
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
INSERT IGNORE INTO `email_campaigns` VALUES ('EC001','Summer Sale Announcement','🌞 Summer Sale - 20% Off All Products!','<h1>Summer Sale!</h1><p>Enjoy 20% off all products this summer. Use code SUMMER20 at checkout.</p>','all','draft','2025-06-08 23:54:10',NULL),('EC002','New Product Launch','Introducing Our New Custom Tumblers!','<h1>New Products Alert!</h1><p>Check out our new line of custom tumblers with unique designs.</p>','customers','scheduled','2025-06-06 23:54:10','2025-06-11 23:54:10'),('EC003','Customer Feedback Request','We Value Your Feedback!','<h1>How Did We Do?</h1><p>Please take a moment to share your experience with our products and service.</p>','customers','sent','2025-05-29 23:54:10','2025-06-01 23:54:10');
/*!40000 ALTER TABLE `email_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_subscribers`
--

DROP TABLE IF EXISTS `email_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_subscribers` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
INSERT IGNORE INTO `email_subscribers` VALUES ('ES001','customer@example.com','Test','Customer','active','website','2025-05-09 23:54:10','2025-06-01 23:54:10'),('ES002','jane.doe@example.com','Jane','Doe','active','checkout','2025-05-24 23:54:10','2025-06-01 23:54:10'),('ES003','john.smith@example.com','John','Smith','active','website','2025-06-03 23:54:10',NULL);
/*!40000 ALTER TABLE `email_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `productId` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sku` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stockLevel` int DEFAULT NULL,
  `reorderPoint` int DEFAULT NULL,
  `costPrice` decimal(10,2) DEFAULT '0.00',
  `retailPrice` decimal(10,2) DEFAULT '0.00',
  `imageUrl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT IGNORE INTO `inventory` VALUES ('I001','P001','T-Shirt, White, S','T-Shirts','Color: White, Size: S','TS-WHT-S',5,10,81.39,19.97,'images/P001.png'),('I002','P002','Tumbler, 20oz, White','Tumblers','Color: White (for sublimation)','TUM20-WHT',39,5,10.02,14.99,'images/frog_tumbler_3.png'),('I003','P004','Artwork Print Blank Canvas 8x10','Artwork','Material: Canvas, Size: 8x10','ART-CAN-810',20,7,3.70,12.98,'images/frog_painter_2.png'),('I004','P006','Window Wrap, Small Business','Window Wraps','Size: 3x4 ft','WW-SB-34',15,3,45.00,89.99,'images/frog_windowwrap_2.png'),('I005','P003','Custom Tumbler (24oz)','Tumblers','This new tumbler rumbles with just the right size :)','NEW-SKU',1,6,7.50,16.99,'images/frog_tumbler_2.png'),('I006','P005','New Inventory Item','Sublimation','New item description','NEW-SKU',0,5,4.25,9.99,'images/frog_mug.png'),('I007','P003','Custom Tumbler (30oz)','Tumblers','New item description','NEW-SKU',0,5,8.00,18.99,'images/frog_tumbler_2.png'),('I008','P007','New Test thumbs','Test','New item description','NEW-SKU',1,5,2.50,7.99,'images/placeholder.png');
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
  `inventoryId` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_energy_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_energy`
--

LOCK TABLES `inventory_energy` WRITE;
/*!40000 ALTER TABLE `inventory_energy` DISABLE KEYS */;
INSERT IGNORE INTO `inventory_energy` VALUES (3,'I002','power',3.01),(4,'I005','lights',1.00),(5,'I003','fire',10.01),(6,'I003','wood',2.00);
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
  `inventoryId` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventoryId` (`inventoryId`),
  CONSTRAINT `inventory_equipment_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_equipment`
--

LOCK TABLES `inventory_equipment` WRITE;
/*!40000 ALTER TABLE `inventory_equipment` DISABLE KEYS */;
INSERT IGNORE INTO `inventory_equipment` VALUES (1,'I003','treadmill',13.00);
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
  `inventoryId` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
INSERT IGNORE INTO `inventory_labor` VALUES (1,'I001','sewing a dress',80.04),(2,'I002','digging',5.01),(3,'I005','swimming',2.00),(4,'I003','holy',3.00),(5,'I001','pedaling',2.01);
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
  `inventoryId` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
INSERT IGNORE INTO `inventory_materials` VALUES (1,'I001','cotton sheet',1.37),(3,'I002','linen',2.00),(4,'I005','wood',4.99),(5,'I003','linen',5.00),(6,'I003','cotton',1.01);
/*!40000 ALTER TABLE `inventory_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` varchar(16) NOT NULL,
  `orderId` varchar(16) NOT NULL,
  `productId` varchar(16) NOT NULL,
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
INSERT IGNORE INTO `order_items` VALUES ('OI12345','O12345','P001',2,19.99),('OI23456','O12345','P002',1,19.99),('OI34567','O23456','P005',1,24.99),('OI45678','O34567','P004',3,49.99);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT IGNORE INTO `orders` VALUES ('O12345','U001',59.97,'Credit Card',NULL,'{\"name\":\"Admin User\",\"street\":\"123 Main St\",\"city\":\"Dawsonville\",\"state\":\"GA\",\"zip\":\"30534\"}','Completed','2025-06-08 14:25:15','','Received',NULL,NULL),('O23456','U002',24.99,'PayPal',NULL,'{\"name\":\"Test Customer\",\"street\":\"456 Oak Ave\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\"}','Shipped','2025-06-06 14:25:15','1234','Received',NULL,NULL),('O34567','U002',149.95,'Credit Card',NULL,'{\"name\":\"Test Customer\",\"street\":\"456 Oak Ave\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\"}','Delivered','2025-06-01 14:25:15',NULL,'Received',NULL,NULL),('TEST001','U001',45.99,'Check','1234',NULL,'Pending','2025-06-09 19:27:21',NULL,'Pending',NULL,'Check cleared on 6/9/25'),('TEST002','U001',29.99,'Cash',NULL,NULL,'Pending','2025-06-09 19:27:21',NULL,'Received','2025-06-09','Cash payment received in store'),('TEST003','U001',67.50,'Check','5678',NULL,'Pending','2025-06-09 19:27:21',NULL,'Pending',NULL,'Check #5678 - customer promises to deliver tomorrow');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `productType` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `basePrice` decimal(10,2) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `defaultSKU_Base` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT IGNORE INTO `products` VALUES ('P001','Test Product','test',19.98,'Test','TEST','Supplier A','Available sizes: S, M, L, XL, XXL; Various colors','images/frog_tshirt_1.png'),('P002','Custom Tumbler (20oz)','Tumblers',19.99,'Insulated 20oz tumbler for sublimation','TUM20-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/product_custom-tumbler-20oz.png'),('P003','Custom Tumbler (30oz)','Tumblers',24.98,'Insulated 30oz tumbler for sublimation','TUM30-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/product_custom-tumbler-30oz.png'),('P004','Custom Artwork Print','Artwork',49.99,'Prints of custom digital or hand-drawn artwork','ART-PRNT-BASE','Supplier C','Sizes: 8x10, 11x14, 16x20; Paper/Canvas','images/frog_painter_1.png'),('P005','Sublimation Blank Item (e.g., Mug)','Sublimation',14.99,'Blank items ready for sublimation transfer','SUB-MUG-BASE','Supplier B','Specify blank type in description','images/products/product_sublimation-blank-item-e-g-mug.png'),('P006','Custom Window Wrap','Window Wraps',39.99,'Vinyl window wraps, custom sizes and designs','WW-BASE','Supplier D','Per sq ft pricing may apply','images/products/product_custom-window-wrap.png'),('P007','New Test Thumbs','Test',500.00,'Description pending','TEST-BASE','TBD','New product group','images/products/product_new-test-thumbs.png');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_accounts`
--

DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_accounts` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connected` tinyint(1) DEFAULT '0',
  `auth_token` text COLLATE utf8mb4_unicode_ci,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_accounts`
--

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
INSERT IGNORE INTO `social_accounts` VALUES ('SA001','facebook','Whimsical Frog Crafts',1,NULL,'2025-06-08 23:54:10'),('SA002','instagram','@whimsicalfrog',1,NULL,'2025-06-08 23:54:10');
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_posts`
--

DROP TABLE IF EXISTS `social_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_posts` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `scheduled_date` timestamp NULL DEFAULT NULL,
  `posted_date` timestamp NULL DEFAULT NULL,
  `account_id` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
INSERT IGNORE INTO `social_posts` VALUES ('SP001','facebook','Check out our new summer collection! Perfect for those hot days. #WhimsicalFrog #SummerVibes','images/products/product_custom-tumbler-20oz.png','scheduled','2025-06-10 23:54:10',NULL,'SA001'),('SP002','instagram','Our new tumblers keep your drinks cold for 24 hours! Perfect for summer adventures. #WhimsicalFrog #StayHydrated','images/products/product_custom-tumbler-30oz.png','posted','2025-06-05 23:54:10','2025-06-05 23:54:10','SA002'),('SP003','facebook','Use code SUMMER20 for 20% off all products this week only! #WhimsicalFrog #SummerSale',NULL,'draft',NULL,NULL,'SA001');
/*!40000 ALTER TABLE `social_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roleType` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstName` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastName` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addressLine1` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addressLine2` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zipCode` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT IGNORE INTO `users` VALUES ('U001','admin','pass.123','admin@whimsicalfrog.com','Admin','Admin','Admin4','WhimsicalFrog','4047878900','91 Singletree Lane','nothing','Dawsonville','GA','30534'),('U002','customer','pass.123','customer@example.com','Customer','Customer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),('Uwc8nl7kg','sarah','pass.123','sarah@catn8.us','Customer','Customer','Sarah','Graves','6788979763','4765 Fourth Rail Ln','the end','Cumming','GA','30041');
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

-- Dump completed on 2025-06-09 22:04:27
