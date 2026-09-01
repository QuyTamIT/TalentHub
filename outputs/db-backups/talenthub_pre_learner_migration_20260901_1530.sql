-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: talenthub
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

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
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities` (
  `id` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `createdByTeacherId` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `startAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `endAt` timestamp NULL DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activities_schoolId` (`schoolId`),
  KEY `idx_activities_createdByTeacherId` (`createdByTeacherId`),
  CONSTRAINT `fk_activities_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activities_teacher` FOREIGN KEY (`createdByTeacherId`) REFERENCES `teacher_profiles` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_activities_capacity` CHECK (`capacity` >= 0),
  CONSTRAINT `chk_activities_time` CHECK (`endAt` is null or `endAt` >= `startAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_qr_tokens`
--

DROP TABLE IF EXISTS `activity_qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_qr_tokens` (
  `id` char(36) NOT NULL,
  `activityId` char(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_qr_tokens_token` (`token`),
  KEY `idx_activity_qr_tokens_activityId` (`activityId`),
  CONSTRAINT `fk_activity_qr_tokens_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_qr_tokens`
--

LOCK TABLES `activity_qr_tokens` WRITE;
/*!40000 ALTER TABLE `activity_qr_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_registrations`
--

DROP TABLE IF EXISTS `activity_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_registrations` (
  `id` char(36) NOT NULL,
  `activityId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `status` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_registrations_activityId` (`activityId`),
  KEY `idx_activity_registrations_studentId` (`studentId`),
  CONSTRAINT `fk_activity_registrations_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activity_registrations_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_registrations`
--

LOCK TABLES `activity_registrations` WRITE;
/*!40000 ALTER TABLE `activity_registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_recommendations`
--

DROP TABLE IF EXISTS `ai_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_recommendations` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `careerPath` varchar(255) NOT NULL,
  `suggestedActions` varchar(2000) NOT NULL,
  `confidenceScore` decimal(7,4) NOT NULL,
  `modelVersion` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_recommendations_studentId` (`studentId`),
  CONSTRAINT `fk_ai_recommendations_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_ai_recommendations_confidence` CHECK (`confidenceScore` >= 0 and `confidenceScore` <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_recommendations`
--

LOCK TABLES `ai_recommendations` WRITE;
/*!40000 ALTER TABLE `ai_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_status_history`
--

DROP TABLE IF EXISTS `application_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_status_history` (
  `id` char(36) NOT NULL,
  `applicationId` char(36) NOT NULL,
  `fromStatus` varchar(50) DEFAULT NULL,
  `toStatus` varchar(50) NOT NULL,
  `changedBy` char(36) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_history_application` (`applicationId`,`createdAt`),
  KEY `idx_application_history_changed_by` (`changedBy`),
  CONSTRAINT `fk_application_history_application` FOREIGN KEY (`applicationId`) REFERENCES `internship_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_history_user` FOREIGN KEY (`changedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_status_history`
--

LOCK TABLES `application_status_history` WRITE;
/*!40000 ALTER TABLE `application_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_criteria`
--

DROP TABLE IF EXISTS `assessment_criteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_criteria` (
  `id` char(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `minScore` decimal(7,2) NOT NULL,
  `maxScore` decimal(7,2) NOT NULL,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_assessment_criteria_score_range` CHECK (`maxScore` >= `minScore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_criteria`
--

LOCK TABLES `assessment_criteria` WRITE;
/*!40000 ALTER TABLE `assessment_criteria` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_criteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_scores`
--

DROP TABLE IF EXISTS `assessment_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_scores` (
  `id` char(36) NOT NULL,
  `assessmentId` char(36) NOT NULL,
  `criteriaId` char(36) NOT NULL,
  `score` decimal(7,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessment_scores_assessmentId` (`assessmentId`),
  KEY `idx_assessment_scores_criteriaId` (`criteriaId`),
  CONSTRAINT `fk_assessment_scores_assessment` FOREIGN KEY (`assessmentId`) REFERENCES `assessments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_scores_criteria` FOREIGN KEY (`criteriaId`) REFERENCES `assessment_criteria` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_scores`
--

LOCK TABLES `assessment_scores` WRITE;
/*!40000 ALTER TABLE `assessment_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessment_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessments`
--

DROP TABLE IF EXISTS `assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessments` (
  `id` char(36) NOT NULL,
  `teacherId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `activityId` char(36) NOT NULL,
  `overallScore` decimal(7,2) NOT NULL,
  `comment` varchar(1000) NOT NULL,
  `status` varchar(50) NOT NULL,
  `publishedAt` datetime(6) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_assessments_teacherId` (`teacherId`),
  KEY `idx_assessments_studentId` (`studentId`),
  KEY `idx_assessments_activityId` (`activityId`),
  CONSTRAINT `fk_assessments_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assessments_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessments_teacher` FOREIGN KEY (`teacherId`) REFERENCES `teacher_profiles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessments`
--

LOCK TABLES `assessments` WRITE;
/*!40000 ALTER TABLE `assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entityType` varchar(100) NOT NULL,
  `entityId` char(36) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_userId` (`userId`),
  KEY `idx_audit_logs_entity` (`entityType`,`entityId`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES ('014e58da-464e-45e2-9907-eeb0fe57e162','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-17 12:17:22'),('06b13352-0b3f-4b17-b96f-6f4fb2a50b77','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-17 12:20:00'),('097586ff-97c8-4edd-b353-4801f882b866','0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin.account_seeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:20:55'),('0a9b0d46-d3d2-442f-b192-167a2717206b','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 11:57:17'),('0f5f0d51-5665-4c89-a587-f8bb7472430b','0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin.organization_verification_changed','school','da811c4f-2f74-4fdd-80b0-dd6f26109783','2026-08-20 11:55:26'),('126ed0f3-5a06-41a3-a89d-bcaf5ad67d92','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:53:32'),('1eb55574-2075-4b18-b4ac-f594d9cd6758','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-17 12:19:59'),('31adea31-57f7-4c1c-933e-8f47778e6bff','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-20 11:51:16'),('3216c9a3-4dc5-4c3b-82db-8d1d8a9407b0','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-25 16:32:59'),('32de80aa-c936-4473-b959-11c2b5ce66a6','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-21 09:30:41'),('340a915f-a169-4c03-9c64-788237963d0d','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-17 11:38:59'),('356cf365-d540-479c-a993-ca213202ea54','14af79de-3daa-4e78-985e-6e48beed57a3','auth.student_registered','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-17 11:31:45'),('393e0887-95fb-4a32-92d4-9cd6a5ded155','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-20 11:49:25'),('3eb1f9b8-6e90-46bd-996d-00dd2c6bb482','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-18 09:59:24'),('443ff0fc-794c-4818-ac3a-d3c0f9ae0282','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-18 10:59:23'),('46acd542-cc77-47f1-ba08-ec65b308afa8','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-21 09:32:17'),('48e7371a-d2f9-4cf2-9608-cf8ebb27d872','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 12:07:47'),('515d965f-b579-4b0a-b15d-8cd3065b8016','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-17 11:38:59'),('5359d97b-ba08-43fc-af7b-6bd63e16a6cb','0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin.account_seeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:17:21'),('54dd9a2d-be34-447c-a9b8-4dd16680001a','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-20 11:50:31'),('696175c2-8050-4335-b3e7-5e91e01a93be','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:24:14'),('69c837e2-ab99-4f68-91b1-de1a9a2fbfe3','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-18 10:06:42'),('71820f4b-a471-43cf-bbae-cc912963dbd9','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-17 12:17:22'),('7480aedb-4181-4a50-8514-a52ad2425ce4','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 11:38:58'),('8990b55a-f979-4314-8767-0b8cb5d4b523','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-21 09:29:43'),('8a0c9de3-5e19-4cf2-b5fb-5d9e003ca534','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 12:20:03'),('9589b7a9-911d-4742-ae38-075f5b2a5c46','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:34:00'),('a1900a19-2dcf-4c84-a055-68ad06349e81','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-08-17 11:32:14'),('a7c271e8-a5df-4469-af9a-118e4d30a201','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:42:35'),('adb21986-ccb3-4ed5-9aa1-2451669bdf8e','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-09-01 08:22:25'),('b7ac699d-054d-4605-8cf7-7e7474059ebe','14af79de-3daa-4e78-985e-6e48beed57a3','auth.login_succeeded','user','14af79de-3daa-4e78-985e-6e48beed57a3','2026-09-01 08:28:42'),('bbc15783-ee22-4812-aea7-7b782aabc902','0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin.organization_registration_approved','organization_registration_request','31291912-a8f1-4cb1-80de-087324435412','2026-08-22 08:20:14'),('bf7ab8cd-28db-4ffa-93ac-6c5678ad0e60','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-21 09:31:13'),('c8eaf8fb-1b4c-4858-ba9e-ed0257c704a7','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 12:19:53'),('cbd671af-a1be-450b-a1a1-856441522d91','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 12:08:06'),('cd769cdd-f245-4f20-90bf-5e2d3bb4cdd2','0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin.account_seeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:59:11'),('d8fb158a-b767-4021-a9a0-8caa27a6ec5c','31000000-0000-4000-8000-000000000003','auth.login_succeeded','user','31000000-0000-4000-8000-000000000003','2026-08-17 11:56:43'),('daa38291-6f81-4e8b-806d-9021fbdcdd96','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-21 09:31:47'),('e07e8d51-c876-49f8-bc68-b8fee0543708','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 11:58:26'),('ed5361a8-f8d7-433c-8985-3ff0ee04529d','0d904523-7e8e-4f73-81d4-fa36f2de45a4','auth.login_succeeded','user','0d904523-7e8e-4f73-81d4-fa36f2de45a4','2026-08-20 11:32:55'),('ed813acc-c2d4-415c-b7bd-c1ad9dd4263b','31000000-0000-4000-8000-000000000002','auth.login_succeeded','user','31000000-0000-4000-8000-000000000002','2026-08-17 12:15:43'),('f833971d-6c53-4544-b09b-48f8e511c5e6','31000000-0000-4000-8000-000000000001','auth.login_succeeded','user','31000000-0000-4000-8000-000000000001','2026-08-17 12:10:30');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_rate_limits`
--

DROP TABLE IF EXISTS `auth_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_rate_limits` (
  `bucketKey` char(64) NOT NULL,
  `scope` varchar(20) NOT NULL,
  `failureCount` smallint(5) unsigned NOT NULL DEFAULT 0,
  `windowStartedAt` datetime(6) NOT NULL,
  `blockedUntil` datetime(6) DEFAULT NULL,
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`bucketKey`),
  KEY `idx_auth_rate_limits_cleanup` (`updatedAt`),
  KEY `idx_auth_rate_limits_blocked` (`blockedUntil`),
  CONSTRAINT `chk_auth_rate_limits_scope` CHECK (`scope` in ('identity','ip'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_rate_limits`
--

LOCK TABLES `auth_rate_limits` WRITE;
/*!40000 ALTER TABLE `auth_rate_limits` DISABLE KEYS */;
INSERT INTO `auth_rate_limits` VALUES ('4006d8f1fc13627cfeea5c2b81ab9e965f1be509daa48c45807edd643fb92114','identity',1,'2026-08-18 10:42:20.489563',NULL,'2026-08-18 10:42:20.492561'),('674dcc95a6c069401bc91c33b34af72a4fb8e76c512ff04a04e5fc43cd98dc0f','ip',2,'2026-08-18 10:58:11.040556',NULL,'2026-08-18 10:58:24.341170'),('c24f877b5f267f89edc3732f16b2960590590b48786f875fd064f885076695be','identity',2,'2026-08-18 10:58:11.032654',NULL,'2026-08-18 10:58:24.337149');
/*!40000 ALTER TABLE `auth_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `badges`
--

DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `badges` (
  `id` char(36) NOT NULL,
  `name` varchar(150) NOT NULL,
  `level` varchar(50) NOT NULL,
  `ruleCriteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`ruleCriteria`)),
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badges`
--

LOCK TABLES `badges` WRITE;
/*!40000 ALTER TABLE `badges` DISABLE KEYS */;
/*!40000 ALTER TABLE `badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `issuer` varchar(255) NOT NULL,
  `issueDate` date NOT NULL,
  `expiryDate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificates_studentId` (`studentId`),
  CONSTRAINT `fk_certificates_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checkins`
--

DROP TABLE IF EXISTS `checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checkins` (
  `id` char(36) NOT NULL,
  `registrationId` char(36) NOT NULL,
  `qrTokenId` char(36) NOT NULL,
  `checkedInAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_checkins_registrationId` (`registrationId`),
  KEY `idx_checkins_qrTokenId` (`qrTokenId`),
  CONSTRAINT `fk_checkins_qr_token` FOREIGN KEY (`qrTokenId`) REFERENCES `activity_qr_tokens` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_checkins_registration` FOREIGN KEY (`registrationId`) REFERENCES `activity_registrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checkins`
--

LOCK TABLES `checkins` WRITE;
/*!40000 ALTER TABLE `checkins` DISABLE KEYS */;
/*!40000 ALTER TABLE `checkins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gradeLevel` int(11) NOT NULL,
  `academicYear` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_classes_schoolId` (`schoolId`),
  CONSTRAINT `fk_classes_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES ('8e71b032-2fe9-404e-abfe-728587c6c3d0','da811c4f-2f74-4fdd-80b0-dd6f26109783','SE08201',8,'2026-2027');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_requests`
--

DROP TABLE IF EXISTS `contact_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_requests` (
  `id` char(36) NOT NULL,
  `enterpriseId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `status` varchar(50) NOT NULL,
  `message` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_requests_enterpriseId` (`enterpriseId`),
  KEY `idx_contact_requests_studentId` (`studentId`),
  CONSTRAINT `fk_contact_requests_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_contact_requests_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_requests`
--

LOCK TABLES `contact_requests` WRITE;
/*!40000 ALTER TABLE `contact_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enterprise_members`
--

DROP TABLE IF EXISTS `enterprise_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enterprise_members` (
  `id` char(36) NOT NULL,
  `enterpriseId` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise_members_enterpriseId` (`enterpriseId`),
  KEY `idx_enterprise_members_userId` (`userId`),
  CONSTRAINT `fk_enterprise_members_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enterprise_members_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enterprise_members`
--

LOCK TABLES `enterprise_members` WRITE;
/*!40000 ALTER TABLE `enterprise_members` DISABLE KEYS */;
INSERT INTO `enterprise_members` VALUES ('33000000-0000-4000-8000-000000000001','32000000-0000-4000-8000-000000000001','31000000-0000-4000-8000-000000000001','owner');
/*!40000 ALTER TABLE `enterprise_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enterprises`
--

DROP TABLE IF EXISTS `enterprises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enterprises` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `logoUrl` varchar(500) DEFAULT NULL,
  `industry` varchar(150) DEFAULT NULL,
  `companySize` varchar(100) DEFAULT NULL,
  `foundedYear` smallint(5) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `taxCode` varchar(50) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `verificationStatus` varchar(50) NOT NULL DEFAULT 'pending',
  `verificationNote` text DEFAULT NULL,
  `verifiedAt` timestamp NULL DEFAULT NULL,
  `verifiedBy` char(36) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_enterprises_verifiedBy` (`verifiedBy`),
  CONSTRAINT `fk_enterprises_verified_by` FOREIGN KEY (`verifiedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enterprises`
--

LOCK TABLES `enterprises` WRITE;
/*!40000 ALTER TABLE `enterprises` DISABLE KEYS */;
INSERT INTO `enterprises` VALUES ('32000000-0000-4000-8000-000000000001','Công ty TalentHub Demo','active',NULL,NULL,NULL,NULL,NULL,'enterprise@talenthub.local',NULL,NULL,NULL,NULL,'verified',NULL,NULL,NULL,'2026-08-17 11:38:57','2026-08-17 11:38:57');
/*!40000 ALTER TABLE `enterprises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `experience_logs`
--

DROP TABLE IF EXISTS `experience_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `experience_logs` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `activityId` char(36) NOT NULL,
  `checkinId` char(36) NOT NULL,
  `hours` decimal(7,2) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `auditReason` varchar(500) DEFAULT NULL,
  `confirmedAt` datetime(6) DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_experience_logs_studentId` (`studentId`),
  KEY `idx_experience_logs_activityId` (`activityId`),
  KEY `idx_experience_logs_checkinId` (`checkinId`),
  CONSTRAINT `fk_experience_logs_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_experience_logs_checkin` FOREIGN KEY (`checkinId`) REFERENCES `checkins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_experience_logs_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_experience_logs_hours` CHECK (`hours` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `experience_logs`
--

LOCK TABLES `experience_logs` WRITE;
/*!40000 ALTER TABLE `experience_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `experience_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internship_applications`
--

DROP TABLE IF EXISTS `internship_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internship_applications` (
  `id` char(36) NOT NULL,
  `postId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `status` varchar(50) NOT NULL,
  `matchScore` decimal(5,2) DEFAULT NULL,
  `reviewerId` char(36) DEFAULT NULL,
  `cvUrl` varchar(500) NOT NULL,
  `reviewerNote` varchar(1000) DEFAULT NULL,
  `appliedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_post_student` (`postId`,`studentId`),
  KEY `idx_internship_applications_postId` (`postId`),
  KEY `idx_internship_applications_studentId` (`studentId`),
  KEY `idx_application_status` (`status`),
  KEY `idx_application_reviewer` (`reviewerId`),
  CONSTRAINT `fk_application_reviewer` FOREIGN KEY (`reviewerId`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_applications_post` FOREIGN KEY (`postId`) REFERENCES `internship_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_applications_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internship_applications`
--

LOCK TABLES `internship_applications` WRITE;
/*!40000 ALTER TABLE `internship_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `internship_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internship_posts`
--

DROP TABLE IF EXISTS `internship_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internship_posts` (
  `id` char(36) NOT NULL,
  `enterpriseId` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `workMode` enum('onsite','remote','hybrid') NOT NULL DEFAULT 'onsite',
  `openings` int(10) unsigned NOT NULL DEFAULT 1,
  `targetStudents` varchar(255) DEFAULT NULL,
  `deadline` date NOT NULL,
  `status` varchar(50) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_internship_posts_enterpriseId` (`enterpriseId`),
  KEY `idx_internship_posts_enterprise` (`enterpriseId`),
  KEY `idx_internship_posts_status_deadline` (`status`,`deadline`),
  KEY `idx_internship_posts_work_mode` (`workMode`),
  CONSTRAINT `fk_internship_posts_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internship_posts`
--

LOCK TABLES `internship_posts` WRITE;
/*!40000 ALTER TABLE `internship_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `internship_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internship_requirements`
--

DROP TABLE IF EXISTS `internship_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internship_requirements` (
  `id` char(36) NOT NULL,
  `postId` char(36) NOT NULL,
  `skillId` char(36) NOT NULL,
  `minLevel` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_internship_requirements_postId` (`postId`),
  KEY `idx_internship_requirements_skillId` (`skillId`),
  CONSTRAINT `fk_internship_requirements_post` FOREIGN KEY (`postId`) REFERENCES `internship_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_requirements_skill` FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internship_requirements`
--

LOCK TABLES `internship_requirements` WRITE;
/*!40000 ALTER TABLE `internship_requirements` DISABLE KEYS */;
/*!40000 ALTER TABLE `internship_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learner_onboarding_states`
--

DROP TABLE IF EXISTS `learner_onboarding_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learner_onboarding_states` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'completed',
  `step` varchar(50) NOT NULL DEFAULT 'welcome',
  `isCompleted` tinyint(1) NOT NULL DEFAULT 1,
  `acceptedAt` datetime(6) DEFAULT NULL,
  `completedAt` datetime(6) DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_learner_onboarding_student` (`studentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learner_onboarding_states`
--

LOCK TABLES `learner_onboarding_states` WRITE;
/*!40000 ALTER TABLE `learner_onboarding_states` DISABLE KEYS */;
/*!40000 ALTER TABLE `learner_onboarding_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learner_schema_migrations`
--

DROP TABLE IF EXISTS `learner_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learner_schema_migrations` (
  `version` varchar(100) NOT NULL,
  `appliedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learner_schema_migrations`
--

LOCK TABLES `learner_schema_migrations` WRITE;
/*!40000 ALTER TABLE `learner_schema_migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `learner_schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `notificationType` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `relatedEntityType` varchar(100) DEFAULT NULL,
  `relatedEntityId` char(36) DEFAULT NULL,
  `deliveryChannel` varchar(50) NOT NULL,
  `notificationStatus` varchar(50) NOT NULL,
  `isRead` tinyint(1) NOT NULL DEFAULT 0,
  `readAt` timestamp NULL DEFAULT NULL,
  `sentAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_userId` (`userId`),
  KEY `idx_notifications_related_entity` (`relatedEntityType`,`relatedEntityId`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` char(36) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External order service stub shown in the ERD';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_registration_requests`
--

DROP TABLE IF EXISTS `organization_registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_registration_requests` (
  `id` char(36) NOT NULL,
  `type` varchar(20) NOT NULL,
  `organizationName` varchar(255) NOT NULL,
  `fullName` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` varchar(500) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `expiresAt` datetime(6) NOT NULL,
  `reviewedAt` datetime(6) DEFAULT NULL,
  `reviewedBy` char(36) DEFAULT NULL,
  `reviewNote` varchar(1000) DEFAULT NULL,
  `createdUserId` char(36) DEFAULT NULL,
  `createdOrganizationId` char(36) DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_org_registration_status_expiry` (`status`,`expiresAt`),
  KEY `idx_org_registration_email_status` (`email`,`status`),
  KEY `idx_org_registration_reviewer` (`reviewedBy`),
  CONSTRAINT `fk_org_registration_reviewer` FOREIGN KEY (`reviewedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_org_registration_type` CHECK (`type` in ('school','enterprise')),
  CONSTRAINT `chk_org_registration_status` CHECK (`status` in ('pending','approved','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_registration_requests`
--

LOCK TABLES `organization_registration_requests` WRITE;
/*!40000 ALTER TABLE `organization_registration_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `organization_registration_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_orders`
--

DROP TABLE IF EXISTS `payment_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_orders` (
  `id` char(36) NOT NULL,
  `orderId` char(36) NOT NULL,
  `paymentMethod` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `paymentStatus` varchar(50) NOT NULL,
  `provider` varchar(100) NOT NULL,
  `providerReference` varchar(255) DEFAULT NULL,
  `paidAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_orders_orderId` (`orderId`),
  CONSTRAINT `fk_payment_orders_order` FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_orders_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_orders`
--

LOCK TABLES `payment_orders` WRITE;
/*!40000 ALTER TABLE `payment_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_transactions` (
  `id` char(36) NOT NULL,
  `paymentOrderId` char(36) NOT NULL,
  `transactionCode` varchar(255) NOT NULL,
  `providerTransactionId` varchar(255) DEFAULT NULL,
  `transactionType` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transactionStatus` varchar(50) NOT NULL,
  `requestReference` varchar(255) NOT NULL,
  `failureCode` varchar(100) DEFAULT NULL,
  `failureMessage` text DEFAULT NULL,
  `processedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_transactions_code` (`transactionCode`),
  UNIQUE KEY `uk_payment_transactions_request_ref` (`requestReference`),
  KEY `idx_payment_transactions_paymentOrderId` (`paymentOrderId`),
  CONSTRAINT `fk_payment_transactions_payment_order` FOREIGN KEY (`paymentOrderId`) REFERENCES `payment_orders` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_transactions_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'activities.read','View assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(2,'activities.create','Create activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(3,'activities.update_own','Update owned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(4,'activity_registrations.read_assigned','View registrations of assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(5,'activity_registrations.update_assigned','Update registrations of assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(6,'assessment_criteria.read','View assessment criteria','2026-08-15 11:43:21','2026-08-15 11:43:21'),(7,'assessment_criteria.create_own_activity','Create criteria for owned or assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(8,'assessment_criteria.update_own_activity','Update criteria for owned or assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(9,'assessments.read_assigned','View assessments of assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(10,'assessments.create_assigned','Create assessments for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(11,'assessments.update_assigned','Update assessments for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(12,'assessments.publish_assigned','Publish assessments for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(13,'assessment_scores.read_assigned','View criterion scores for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(14,'assessment_scores.create_assigned','Create criterion scores for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(15,'assessment_scores.update_assigned','Update criterion scores for assigned activities','2026-08-15 11:43:21','2026-08-15 11:43:21'),(16,'admin.dashboard.read','TalentHub permission: admin.dashboard.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(17,'admin.user.read','TalentHub permission: admin.user.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(18,'admin.user.suspend','TalentHub permission: admin.user.suspend','2026-08-20 11:17:21','2026-08-20 11:17:21'),(19,'admin.user.restore','TalentHub permission: admin.user.restore','2026-08-20 11:17:21','2026-08-20 11:17:21'),(20,'admin.organization.read','TalentHub permission: admin.organization.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(21,'admin.organization.verify','TalentHub permission: admin.organization.verify','2026-08-20 11:17:21','2026-08-20 11:17:21'),(22,'admin.organization.suspend','TalentHub permission: admin.organization.suspend','2026-08-20 11:17:21','2026-08-20 11:17:21'),(23,'admin.rbac.read','TalentHub permission: admin.rbac.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(24,'admin.rbac.update','TalentHub permission: admin.rbac.update','2026-08-20 11:17:21','2026-08-20 11:17:21'),(25,'admin.audit.read','TalentHub permission: admin.audit.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(26,'admin.audit.export','TalentHub permission: admin.audit.export','2026-08-20 11:17:21','2026-08-20 11:17:21'),(27,'admin.incident.manage','TalentHub permission: admin.incident.manage','2026-08-20 11:17:21','2026-08-20 11:17:21'),(28,'admin.payment.read','TalentHub permission: admin.payment.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(29,'admin.payment.reconcile','TalentHub permission: admin.payment.reconcile','2026-08-20 11:17:21','2026-08-20 11:17:21'),(30,'admin.system.health.read','TalentHub permission: admin.system.health.read','2026-08-20 11:17:21','2026-08-20 11:17:21'),(48,'admin.user.create','TalentHub permission: admin.user.create','2026-08-20 11:59:11','2026-08-20 11:59:11'),(49,'admin.user.update','TalentHub permission: admin.user.update','2026-08-20 11:59:11','2026-08-20 11:59:11'),(50,'admin.user.delete','TalentHub permission: admin.user.delete','2026-08-20 11:59:11','2026-08-20 11:59:11');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `privacy_consents`
--

DROP TABLE IF EXISTS `privacy_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `privacy_consents` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `scope` varchar(50) NOT NULL,
  `isGranted` tinyint(1) NOT NULL DEFAULT 0,
  `policyVersion` varchar(50) NOT NULL,
  `revokedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_privacy_consents_studentId` (`studentId`),
  CONSTRAINT `fk_privacy_consents_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `privacy_consents`
--

LOCK TABLES `privacy_consents` WRITE;
/*!40000 ALTER TABLE `privacy_consents` DISABLE KEYS */;
/*!40000 ALTER TABLE `privacy_consents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_members`
--

DROP TABLE IF EXISTS `project_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_members` (
  `id` char(36) NOT NULL,
  `projectId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_members_projectId` (`projectId`),
  KEY `idx_project_members_studentId` (`studentId`),
  CONSTRAINT `fk_project_members_project` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_members_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_members`
--

LOCK TABLES `project_members` WRITE;
/*!40000 ALTER TABLE `project_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_sponsorships`
--

DROP TABLE IF EXISTS `project_sponsorships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_sponsorships` (
  `id` char(36) NOT NULL,
  `enterpriseId` char(36) NOT NULL,
  `projectId` char(36) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'VND',
  `status` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `cancelledAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project_sponsorships_enterpriseId` (`enterpriseId`),
  KEY `idx_project_sponsorships_projectId` (`projectId`),
  KEY `idx_sponsorship_enterprise` (`enterpriseId`),
  KEY `idx_sponsorship_project` (`projectId`),
  KEY `idx_sponsorship_status` (`status`),
  CONSTRAINT `fk_project_sponsorships_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_project_sponsorships_project` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_project_sponsorships_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_sponsorships`
--

LOCK TABLES `project_sponsorships` WRITE;
/*!40000 ALTER TABLE `project_sponsorships` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_sponsorships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `targetAmount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `raisedAmount` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_projects_schoolId` (`schoolId`),
  CONSTRAINT `fk_projects_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `generatedByUserId` char(36) NOT NULL,
  `reportType` varchar(50) NOT NULL,
  `fileUrl` varchar(500) NOT NULL,
  `periodStart` date NOT NULL,
  `periodEnd` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_schoolId` (`schoolId`),
  KEY `idx_reports_generatedByUserId` (`generatedByUserId`),
  CONSTRAINT `fk_reports_generated_by_user` FOREIGN KEY (`generatedByUserId`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reports_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_reports_period` CHECK (`periodEnd` >= `periodStart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,'2026-08-15 11:43:33'),(1,2,'2026-08-15 11:43:33'),(1,3,'2026-08-15 11:43:33'),(1,4,'2026-08-15 11:43:33'),(1,5,'2026-08-15 11:43:33'),(1,6,'2026-08-15 11:43:33'),(1,7,'2026-08-15 11:43:33'),(1,8,'2026-08-15 11:43:33'),(1,9,'2026-08-15 11:43:33'),(1,10,'2026-08-15 11:43:33'),(1,11,'2026-08-15 11:43:33'),(1,12,'2026-08-15 11:43:33'),(1,13,'2026-08-15 11:43:33'),(1,14,'2026-08-15 11:43:33'),(1,15,'2026-08-15 11:43:33'),(10,16,'2026-08-20 11:17:21'),(10,17,'2026-08-20 11:17:21'),(10,18,'2026-08-20 11:17:21'),(10,19,'2026-08-20 11:17:21'),(10,20,'2026-08-20 11:17:21'),(10,21,'2026-08-20 11:17:21'),(10,22,'2026-08-20 11:17:21'),(10,23,'2026-08-20 11:17:21'),(10,24,'2026-08-20 11:17:21'),(10,25,'2026-08-20 11:17:21'),(10,26,'2026-08-20 11:17:21'),(10,27,'2026-08-20 11:17:21'),(10,28,'2026-08-20 11:17:21'),(10,29,'2026-08-20 11:17:21'),(10,30,'2026-08-20 11:17:21'),(10,48,'2026-08-20 11:59:11'),(10,49,'2026-08-20 11:59:11'),(10,50,'2026-08-20 11:59:11');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'teacher','Teacher role','2026-08-15 11:43:10','2026-08-15 11:43:10'),(10,'platform_admin','TalentHub platform administrator','2026-08-20 11:17:21','2026-08-20 11:17:21');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` char(14) NOT NULL,
  `name` varchar(255) NOT NULL,
  `checksum` char(64) NOT NULL,
  `batch` int(10) unsigned NOT NULL,
  `executionMs` int(10) unsigned NOT NULL,
  `appliedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`),
  UNIQUE KEY `uq_schema_migrations_name` (`name`),
  KEY `idx_schema_migrations_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_members`
--

DROP TABLE IF EXISTS `school_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `school_members` (
  `id` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `memberRole` varchar(50) NOT NULL DEFAULT 'member',
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_members_user` (`userId`),
  KEY `idx_school_members_school` (`schoolId`),
  CONSTRAINT `fk_school_members_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_school_members_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_members`
--

LOCK TABLES `school_members` WRITE;
/*!40000 ALTER TABLE `school_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `school_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schools` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `logoUrl` varchar(500) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `level` varchar(100) DEFAULT NULL,
  `studentCount` int(10) unsigned NOT NULL DEFAULT 0,
  `teacherCount` int(10) unsigned NOT NULL DEFAULT 0,
  `academicYear` varchar(20) NOT NULL DEFAULT '2025-2026',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES ('da811c4f-2f74-4fdd-80b0-dd6f26109783','BTEC','verified',NULL,NULL,NULL,NULL,NULL,NULL,0,0,'2025-2026');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skills`
--

DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skills` (
  `id` char(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_skills_code` (`code`),
  KEY `idx_skills_status_category` (`status`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skills`
--

LOCK TABLES `skills` WRITE;
/*!40000 ALTER TABLE `skills` DISABLE KEYS */;
/*!40000 ALTER TABLE `skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sponsorship_status_history`
--

DROP TABLE IF EXISTS `sponsorship_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sponsorship_status_history` (
  `id` char(36) NOT NULL,
  `sponsorshipId` char(36) NOT NULL,
  `fromStatus` varchar(50) DEFAULT NULL,
  `toStatus` varchar(50) NOT NULL,
  `changedBy` char(36) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sponsorship_history_sponsorship` (`sponsorshipId`,`createdAt`),
  KEY `idx_sponsorship_history_changed_by` (`changedBy`),
  CONSTRAINT `fk_sponsorship_history_sponsorship` FOREIGN KEY (`sponsorshipId`) REFERENCES `project_sponsorships` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorship_history_user` FOREIGN KEY (`changedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sponsorship_status_history`
--

LOCK TABLES `sponsorship_status_history` WRITE;
/*!40000 ALTER TABLE `sponsorship_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `sponsorship_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_badges`
--

DROP TABLE IF EXISTS `student_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_badges` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `badgeId` char(36) NOT NULL,
  `awardedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `sourceEvent` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_badges_studentId` (`studentId`),
  KEY `idx_student_badges_badgeId` (`badgeId`),
  CONSTRAINT `fk_student_badges_badge` FOREIGN KEY (`badgeId`) REFERENCES `badges` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_badges_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_badges`
--

LOCK TABLES `student_badges` WRITE;
/*!40000 ALTER TABLE `student_badges` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_profiles`
--

DROP TABLE IF EXISTS `student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_profiles` (
  `id` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `classId` char(36) NOT NULL,
  `dateOfBirth` date NOT NULL,
  `phone` varchar(30) NOT NULL,
  `studyStatus` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_profiles_userId` (`userId`),
  KEY `idx_student_profiles_classId` (`classId`),
  CONSTRAINT `fk_student_profiles_class` FOREIGN KEY (`classId`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_profiles`
--

LOCK TABLES `student_profiles` WRITE;
/*!40000 ALTER TABLE `student_profiles` DISABLE KEYS */;
INSERT INTO `student_profiles` VALUES ('eb8db158-1090-4721-acd8-5420ac6e406a','14af79de-3daa-4e78-985e-6e48beed57a3','8e71b032-2fe9-404e-abfe-728587c6c3d0','2010-11-17','0123456789','active');
/*!40000 ALTER TABLE `student_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_skills`
--

DROP TABLE IF EXISTS `student_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_skills` (
  `id` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `skillId` char(36) NOT NULL,
  `levelScore` decimal(5,2) NOT NULL,
  `sourceType` varchar(50) NOT NULL DEFAULT 'import',
  `verificationStatus` varchar(50) NOT NULL DEFAULT 'self_declared',
  `verifiedAt` timestamp NULL DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_skills_student_skill_source` (`studentId`,`skillId`,`sourceType`),
  KEY `idx_student_skills_studentId` (`studentId`),
  KEY `idx_student_skills_skillId` (`skillId`),
  KEY `idx_student_skills_skill` (`skillId`),
  KEY `idx_student_skills_student_verification` (`studentId`,`verificationStatus`),
  CONSTRAINT `fk_student_skills_skill` FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_skills_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_skills`
--

LOCK TABLES `student_skills` WRITE;
/*!40000 ALTER TABLE `student_skills` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `talent_tests`
--

DROP TABLE IF EXISTS `talent_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talent_tests` (
  `id` char(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dimensions`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talent_tests`
--

LOCK TABLES `talent_tests` WRITE;
/*!40000 ALTER TABLE `talent_tests` DISABLE KEYS */;
/*!40000 ALTER TABLE `talent_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_profiles`
--

DROP TABLE IF EXISTS `teacher_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_profiles` (
  `id` char(36) NOT NULL,
  `userId` char(36) NOT NULL,
  `schoolId` char(36) NOT NULL,
  `isSchoolAdmin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_profiles_userId` (`userId`),
  KEY `idx_teacher_profiles_schoolId` (`schoolId`),
  CONSTRAINT `fk_teacher_profiles_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_profiles_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_profiles`
--

LOCK TABLES `teacher_profiles` WRITE;
/*!40000 ALTER TABLE `teacher_profiles` DISABLE KEYS */;
INSERT INTO `teacher_profiles` VALUES ('34000000-0000-4000-8000-000000000001','31000000-0000-4000-8000-000000000003','da811c4f-2f74-4fdd-80b0-dd6f26109783',0);
/*!40000 ALTER TABLE `teacher_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_attempts`
--

DROP TABLE IF EXISTS `test_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_attempts` (
  `id` char(36) NOT NULL,
  `testId` char(36) NOT NULL,
  `studentId` char(36) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'in_progress',
  `startedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `submittedAt` datetime(6) DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_test_attempts_testId` (`testId`),
  KEY `idx_test_attempts_studentId` (`studentId`),
  CONSTRAINT `fk_test_attempts_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_test_attempts_test` FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_attempts`
--

LOCK TABLES `test_attempts` WRITE;
/*!40000 ALTER TABLE `test_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_questions`
--

DROP TABLE IF EXISTS `test_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_questions` (
  `id` char(36) NOT NULL,
  `testId` char(36) NOT NULL,
  `content` varchar(1000) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options`)),
  PRIMARY KEY (`id`),
  KEY `idx_test_questions_testId` (`testId`),
  CONSTRAINT `fk_test_questions_test` FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_questions`
--

LOCK TABLES `test_questions` WRITE;
/*!40000 ALTER TABLE `test_questions` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_results`
--

DROP TABLE IF EXISTS `test_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_results` (
  `id` char(36) NOT NULL,
  `attemptId` char(36) NOT NULL,
  `resultCode` varchar(100) NOT NULL,
  `summary` varchar(4000) NOT NULL,
  `dimensionScoresJson` longtext NOT NULL,
  `scoringVersion` varchar(100) NOT NULL DEFAULT 'legacy',
  `createdAt` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_test_results_attemptId` (`attemptId`),
  CONSTRAINT `fk_test_results_attempt` FOREIGN KEY (`attemptId`) REFERENCES `test_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_results`
--

LOCK TABLES `test_results` WRITE;
/*!40000 ALTER TABLE `test_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `fullName` varchar(150) NOT NULL,
  `roles` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES ('0d904523-7e8e-4f73-81d4-fa36f2de45a4','admin@admin.com','$2y$10$W4w3QsK/dqRVvvd0sVy7Oe9vGBcBgGtaH3lLQekSscDGAPH03Vsjq','TalentHub Admin','platform_admin','active','2026-08-20 11:17:21'),('14af79de-3daa-4e78-985e-6e48beed57a3','nguyenvana@gmail.com','$2y$10$ItuWoSAVj2/K7rfG7V8q4eFwEwT5O4g1bB9ExkKWSZ1J9yD3g1yiC','Nguyễn Văn A','student','active','2026-08-17 11:31:45'),('31000000-0000-4000-8000-000000000001','enterprise@talenthub.local','$2y$10$aLoQlcI6nyZI8xLzlhBTk.2gAiBI04xPGvWsBY7rw0oiWtg3RL/eq','Nguyễn Minh Anh','enterprise','active','2026-08-17 11:38:57'),('31000000-0000-4000-8000-000000000002','school@talenthub.local','$2y$10$aLoQlcI6nyZI8xLzlhBTk.2gAiBI04xPGvWsBY7rw0oiWtg3RL/eq','Trần Hoàng Nam','school','active','2026-08-17 11:38:57'),('31000000-0000-4000-8000-000000000003','teacher@talenthub.local','$2y$10$aLoQlcI6nyZI8xLzlhBTk.2gAiBI04xPGvWsBY7rw0oiWtg3RL/eq','Lê Thu Hà','teacher','active','2026-08-17 11:38:57');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_events`
--

DROP TABLE IF EXISTS `webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_events` (
  `id` char(36) NOT NULL,
  `provider` varchar(100) NOT NULL,
  `providerEventId` varchar(255) NOT NULL,
  `eventType` varchar(100) NOT NULL,
  `paymentTransactionId` char(36) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `signatureValid` tinyint(1) NOT NULL DEFAULT 0,
  `processingStatus` varchar(50) NOT NULL,
  `attemptCount` int(11) NOT NULL DEFAULT 0,
  `errorMessage` text DEFAULT NULL,
  `receivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `processedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_events_paymentTransactionId` (`paymentTransactionId`),
  KEY `idx_webhook_events_provider_event` (`provider`,`providerEventId`),
  CONSTRAINT `fk_webhook_events_payment_transaction` FOREIGN KEY (`paymentTransactionId`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_webhook_events_attempt_count` CHECK (`attemptCount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_events`
--

LOCK TABLES `webhook_events` WRITE;
/*!40000 ALTER TABLE `webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'talenthub'
--

--
-- Dumping routines for database 'talenthub'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-01 15:31:36
