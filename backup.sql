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
  `imageUrl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES ('I001','P001','T-Shirt, White, S','T-Shirts','Color: White, Size: S','TS-WHT-S',50,10,'images/frog_tshirt_2.png'),('I002','P002','Tumbler, 20oz, White','Tumblers','Color: White (for sublimation)','TUM20-WHT',39,5,'images/frog_tumbler_3.png'),('I003','P004','Artwork Print Blank Canvas 8x10','Artwork','Material: Canvas, Size: 8x10','ART-CAN-810',20,5,'images/frog_painter_2.png'),('I004','P006','Window Wrap, Small Business','Window Wraps','Size: 3x4 ft','WW-SB-34',15,3,'images/frog_windowwrap_2.png'),('I005','P003','Custom Tumbler (24oz)','Tumblers','This new tumbler rumbles with just the right size :)','NEW-SKU',1,6,'images/frog_tumbler_2.png'),('I006','P005','New Inventory Item','Sublimation','New item description','NEW-SKU',0,5,'images/frog_mug.png'),('I007','P003','Custom Tumbler (30oz)','Tumblers','New item description','NEW-SKU',0,5,'images/frog_tumbler_2.png'),('I008','P007','New Test thumbs','Test','New item description','NEW-SKU',1,5,'images/placeholder.png');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_energy`
--

LOCK TABLES `inventory_energy` WRITE;
/*!40000 ALTER TABLE `inventory_energy` DISABLE KEYS */;
INSERT INTO `inventory_energy` VALUES (1,'I001','light while sewing',1.00),(2,'I001','sewing machine power',2.00),(3,'I002','power',3.00),(4,'I005','lights',1.00),(5,'I003','fire',10.00);
/*!40000 ALTER TABLE `inventory_energy` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_labor`
--

LOCK TABLES `inventory_labor` WRITE;
/*!40000 ALTER TABLE `inventory_labor` DISABLE KEYS */;
INSERT INTO `inventory_labor` VALUES (1,'I001','sewing a dress',79.99),(2,'I002','digging',5.00),(3,'I005','swimming',2.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_materials`
--

LOCK TABLES `inventory_materials` WRITE;
/*!40000 ALTER TABLE `inventory_materials` DISABLE KEYS */;
INSERT INTO `inventory_materials` VALUES (1,'I001','cotton sheet',1.32),(3,'I002','linen',2.00),(4,'I005','wood',5.00);
/*!40000 ALTER TABLE `inventory_materials` ENABLE KEYS */;
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
INSERT INTO `products` VALUES ('P001','Test Product','test',19.98,'Test','TEST','Supplier A','Available sizes: S, M, L, XL, XXL; Various colors','images/frog_tshirt_1.png'),('P002','Custom Tumbler (20oz)','Tumblers',19.99,'Insulated 20oz tumbler for sublimation','TUM20-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/product_custom-tumbler-20oz.png'),('P003','Custom Tumbler (30oz)','Tumblers',24.98,'Insulated 30oz tumbler for sublimation','TUM30-BASE','Supplier B','Stainless steel, includes lid and straw','images/products/product_custom-tumbler-30oz.png'),('P004','Custom Artwork Print','Artwork',49.99,'Prints of custom digital or hand-drawn artwork','ART-PRNT-BASE','Supplier C','Sizes: 8x10, 11x14, 16x20; Paper/Canvas','images/frog_painter_1.png'),('P005','Sublimation Blank Item (e.g., Mug)','Sublimation',14.99,'Blank items ready for sublimation transfer','SUB-MUG-BASE','Supplier B','Specify blank type in description','images/products/product_sublimation-blank-item-e-g-mug.png'),('P006','Custom Window Wrap','Window Wraps',39.99,'Vinyl window wraps, custom sizes and designs','WW-BASE','Supplier D','Per sq ft pricing may apply','images/products/product_custom-window-wrap.png'),('P007','New Test Thumbs','Test',500.00,'Description pending','TEST-BASE','TBD','New product group','images/products/product_new-test-thumbs.png');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
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
INSERT INTO `users` VALUES ('U001','admin','pass.123','admin@whimsicalfrog.com','Admin','Admin','Admin4','WhimsicalFrog','4047878900','91 Singletree Lane','nothing','Dawsonville','GA','30534'),('U002','customer','pass.123','customer@example.com','Customer','Customer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),('Uwc8nl7kg','sarah','pass.123','sarah@catn8.us','Customer','Customer','Sarah','Graves','6788979763','4765 Fourth Rail Ln','the end','Cumming','GA','30041');
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

-- Dump completed on 2025-06-07 23:26:40
