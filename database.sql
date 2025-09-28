/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.2-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: school_quality
-- ------------------------------------------------------
-- Server version	11.8.2-MariaDB-1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `log_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `completed_tasks`
--

DROP TABLE IF EXISTS `completed_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `completed_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `recipient_role` enum('W1','W2') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rolled_back') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `completed_tasks_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  CONSTRAINT `completed_tasks_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `completed_tasks`
--

LOCK TABLES `completed_tasks` WRITE;
/*!40000 ALTER TABLE `completed_tasks` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `completed_tasks` VALUES
(15,9,3,'W1','uploads/completed_tasks/68c91afe71bb3.pdf','rolled_back','2025-09-16 08:08:30','2025-09-16 08:10:38',6,'andaa vizuri report',1),
(16,9,3,'W1','uploads/completed_tasks/68c91bc40f64d.pdf','approved','2025-09-16 08:11:48','2025-09-16 08:12:48',4,NULL,0);
/*!40000 ALTER TABLE `completed_tasks` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `inspection_id` (`inspection_id`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `inspections`
--

DROP TABLE IF EXISTS `inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `inspected_by` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `findings` text NOT NULL,
  `recommendations` text NOT NULL,
  `compliance_level` enum('Excellent','Good','Needs Improvement','Poor') DEFAULT NULL,
  `status` enum('Pending','Reviewed','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `school_id` (`school_id`),
  KEY `inspected_by` (`inspected_by`),
  CONSTRAINT `inspections_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspections_ibfk_2` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspections`
--

LOCK TABLES `inspections` WRITE;
/*!40000 ALTER TABLE `inspections` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `inspections` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `status` enum('pending','replied','closed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reply_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `receiver_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `permissions_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `permissions` VALUES
(1,3,'Juma Ally','Permission','Am Sick','Rejected',4,'2025-09-08 10:37:27',0),
(2,3,'Juma Ally','Permission','Am Sick','Rejected',4,'2025-09-08 10:37:34',0),
(3,3,'Juma Ally','Permission','Am sick kiongozi','Approved',4,'2025-09-16 10:24:46',0),
(4,7,'Amina Juma','Permission','Naomba ruhusa naumwa malaria','Approved',4,'2025-09-17 05:03:29',0),
(5,9,'Hamadi Juma','Permission','Naumwa','Approved',4,'2025-09-17 08:05:04',0);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `role_codes`
--

DROP TABLE IF EXISTS `role_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('admin','employee','W1','W2') NOT NULL,
  `code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_codes`
--

LOCK TABLES `role_codes` WRITE;
/*!40000 ALTER TABLE `role_codes` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `role_codes` VALUES
(1,'admin','AD990127',1,'2025-08-25 06:17:35'),
(2,'employee','EM231096',1,'2025-08-25 06:17:35'),
(3,'W1','WQ124321',1,'2025-08-25 06:17:35'),
(4,'W2','WC231452',1,'2025-08-25 06:17:35');
/*!40000 ALTER TABLE `role_codes` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(150) NOT NULL,
  `head_name` varchar(255) DEFAULT NULL,
  `num_students` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `type` enum('Primary','Secondary','College','Other') DEFAULT 'Primary',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ward` varchar(100) NOT NULL DEFAULT '',
  `head_phone` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `schools` VALUES
(8,'CholeSamvula','Mr Robert Mwakipesile',6100,NULL,'Primary','2025-09-17 05:18:34','Chole','0624523106');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_logs`
--

LOCK TABLES `security_logs` WRITE;
/*!40000 ALTER TABLE `security_logs` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `security_logs` VALUES
(1,7,'register_success','New user registered with role: employee','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0','2025-09-16 14:24:11'),
(2,8,'register_success','New user registered with role: employee','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0','2025-09-17 09:18:03'),
(3,9,'register_success','New user registered with role: employee','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0','2025-09-17 10:52:31');
/*!40000 ALTER TABLE `security_logs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `stored_work`
--

DROP TABLE IF EXISTS `stored_work`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stored_work` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stored_work_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stored_work`
--

LOCK TABLES `stored_work` WRITE;
/*!40000 ALTER TABLE `stored_work` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `stored_work` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deadline` datetime NOT NULL DEFAULT current_timestamp(),
  `school_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `school_id` (`school_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `tasks` VALUES
(9,'Ukaguzi shule ya sekondari kurui','XXXX',NULL,3,4,'2025-09-16 07:49:04','2025-09-17 22:02:00',NULL),
(10,'Kuandaa taarifa ya Mwezi','vvvvvv',NULL,6,4,'2025-09-17 06:15:46','2025-10-07 02:00:00',NULL),
(11,'Kuandaa taarifa ya Mwezi','vvvvvv',NULL,6,4,'2025-09-17 06:15:52','2025-10-07 02:00:00',NULL),
(12,'Ukaguzi wa Shule','Naomba ukakague shule ya masaki',NULL,8,4,'2025-09-17 06:20:49','2025-09-10 12:20:00',NULL),
(13,'Ukaguzi wa Shule','Naomba ukakague shule ya masaki',NULL,8,4,'2025-09-17 06:20:56','2025-09-10 12:20:00',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `user_logs`
--

DROP TABLE IF EXISTS `user_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` enum('login','logout') NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=338 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_logs`
--

LOCK TABLES `user_logs` WRITE;
/*!40000 ALTER TABLE `user_logs` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `user_logs` VALUES
(333,1,'logout','2025-09-27 08:14:52',NULL,NULL),
(334,1,'login','2025-09-27 08:18:42','::1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(335,1,'logout','2025-09-27 08:19:31',NULL,NULL),
(336,3,'login','2025-09-27 08:19:44','::1','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(337,3,'login','2025-09-27 08:20:24','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0');
/*!40000 ALTER TABLE `user_logs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','W1','W2') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `users` VALUES
(1,'Heri Wambo','heriwambo27@gmail.com','$2y$10$esHBb5DmeOfugUOpiE26QeF6ve0R/4dFrgG44Psk0OCRZ7CzSaTpu','admin','2025-08-25 08:42:58','approved'),
(3,'Juma Ally','jumaally@gmail.com','$2y$12$XIWq.lZsIgJMrT5g8ggz3uPmMdaFXG50xweERzBO7qJpd4ZSVCxiW','employee','2025-08-29 03:52:24','approved'),
(4,'Hassan Ally','hassanally@gmail.com','$2y$12$JGYc.uIfQVLHzWz2dISG..h01pOkdxuI1SItXvpNymZVGnrnitk8a','W1','2025-09-05 20:56:33','approved'),
(5,'Robert John','robertjohn@gmail.com','$2y$12$V53fCztBrf.4hdqtepS8QeDpdG0eyfXWZKAYMnf8ULQ6ylJ2xZ1Ya','employee','2025-09-08 07:06:44','approved'),
(6,'Mwajuma Ally','mwajuma@gmail.com','$2y$12$l6HeTqeNPxd0.9U3qaJx.euxrQdNEDGsL7MPKAV1t81fh9clhWA4W','W2','2025-09-10 01:52:10','approved'),
(7,'Amina Juma','aminajuma@gmail.com','$2y$12$XrtTQmI82jALLQD3kCw/CeUSVhuJzVOg2UmKqAHYz.YIBTSaJYe1K','employee','2025-09-16 11:24:11','approved'),
(8,'John Samson','johnsamson@gmail.com','$2y$12$vJTcuaSYxb9m/kzjBxShsOH37CM5I6RYjHl9kwqKI3QeH8L7v6iEq','employee','2025-09-17 06:18:03','approved'),
(9,'Hamadi Juma','hamadijuma@gmail.com','$2y$12$mZpZ1Dn4Q5i4QuIHCXHlKuJz8yk6NxlzezgGJkFOS0RcOwRLjItN6','employee','2025-09-17 07:52:31','approved');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-09-28 13:21:58
