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
-- Dumping data for table `inventory_materials`
--

LOCK TABLES `inventory_materials` WRITE;
/*!40000 ALTER TABLE `inventory_materials` DISABLE KEYS */;
INSERT INTO `inventory_materials` VALUES (1,'I001','cotton sheet',1.36),(3,'I002','linen',2.00),(4,'I005','wood',4.99),(5,'I003','linen',5.00),(6,'I003','cotton',1.01);
/*!40000 ALTER TABLE `inventory_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `inventory_labor`
--

LOCK TABLES `inventory_labor` WRITE;
/*!40000 ALTER TABLE `inventory_labor` DISABLE KEYS */;
INSERT INTO `inventory_labor` VALUES (1,'I001','sewing a dress',80.01),(2,'I002','digging',5.01),(3,'I005','swimming',2.00),(4,'I003','holy',3.00);
/*!40000 ALTER TABLE `inventory_labor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `inventory_energy`
--

LOCK TABLES `inventory_energy` WRITE;
/*!40000 ALTER TABLE `inventory_energy` DISABLE KEYS */;
INSERT INTO `inventory_energy` VALUES (1,'I001','light while sewing',1.01),(2,'I001','sewing machine power',2.01),(3,'I002','power',3.01),(4,'I005','lights',1.00),(5,'I003','fire',10.00),(6,'I003','wood',2.00);
/*!40000 ALTER TABLE `inventory_energy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `inventory_equipment`
--

LOCK TABLES `inventory_equipment` WRITE;
/*!40000 ALTER TABLE `inventory_equipment` DISABLE KEYS */;
INSERT INTO `inventory_equipment` VALUES (1,'I003','treadmill',13.00);
/*!40000 ALTER TABLE `inventory_equipment` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-08 21:30:31
