-- --------------------------------------------------------
-- Máy chủ:                      127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Phiên bản:           12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for talenthub
CREATE DATABASE IF NOT EXISTS `talenthub` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `talenthub`;

-- Dumping structure for table talenthub.activities
CREATE TABLE IF NOT EXISTS `activities` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdByTeacherId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `startAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `endAt` timestamp NULL DEFAULT NULL,
  `capacity` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activities_schoolId` (`schoolId`),
  KEY `idx_activities_createdByTeacherId` (`createdByTeacherId`),
  CONSTRAINT `fk_activities_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_activities_teacher` FOREIGN KEY (`createdByTeacherId`) REFERENCES `teacher_profiles` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_activities_capacity` CHECK ((`capacity` >= 0)),
  CONSTRAINT `chk_activities_time` CHECK (((`endAt` is null) or (`endAt` >= `startAt`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.activities: ~0 rows (approximately)
DELETE FROM `activities`;

-- Dumping structure for table talenthub.activity_qr_sessions
CREATE TABLE IF NOT EXISTS `activity_qr_sessions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdByTeacherId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenHash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `expiresAt` timestamp NOT NULL,
  `maxScans` int unsigned NOT NULL,
  `usedScans` int unsigned NOT NULL DEFAULT '0',
  `revokedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activity_qr_sessions_token_hash` (`tokenHash`),
  KEY `idx_activity_qr_sessions_activity` (`activityId`),
  KEY `idx_activity_qr_sessions_teacher` (`createdByTeacherId`),
  KEY `idx_activity_qr_sessions_status_expiry` (`status`,`expiresAt`),
  CONSTRAINT `fk_activity_qr_sessions_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activity_qr_sessions_teacher` FOREIGN KEY (`createdByTeacherId`) REFERENCES `teacher_profiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_activity_qr_sessions_max_scans` CHECK ((`maxScans` > 0)),
  CONSTRAINT `chk_activity_qr_sessions_status` CHECK ((`status` in (_utf8mb4'active',_utf8mb4'expired',_utf8mb4'revoked'))),
  CONSTRAINT `chk_activity_qr_sessions_used_scans` CHECK ((`usedScans` <= `maxScans`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.activity_qr_sessions: ~0 rows (approximately)
DELETE FROM `activity_qr_sessions`;

-- Dumping structure for table talenthub.activity_qr_tokens
CREATE TABLE IF NOT EXISTS `activity_qr_tokens` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_qr_tokens_token` (`token`),
  KEY `idx_activity_qr_tokens_activityId` (`activityId`),
  CONSTRAINT `fk_activity_qr_tokens_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.activity_qr_tokens: ~0 rows (approximately)
DELETE FROM `activity_qr_tokens`;

-- Dumping structure for table talenthub.activity_registrations
CREATE TABLE IF NOT EXISTS `activity_registrations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_registrations_activityId` (`activityId`),
  KEY `idx_activity_registrations_studentId` (`studentId`),
  CONSTRAINT `fk_activity_registrations_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activity_registrations_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.activity_registrations: ~0 rows (approximately)
DELETE FROM `activity_registrations`;

-- Dumping structure for table talenthub.ai_recommendations
CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `careerPath` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `suggestedActions` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `confidenceScore` decimal(7,4) NOT NULL,
  `modelVersion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_recommendations_studentId` (`studentId`),
  CONSTRAINT `fk_ai_recommendations_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_ai_recommendations_confidence` CHECK (((`confidenceScore` >= 0) and (`confidenceScore` <= 1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.ai_recommendations: ~0 rows (approximately)
DELETE FROM `ai_recommendations`;

-- Dumping structure for table talenthub.application_status_history
CREATE TABLE IF NOT EXISTS `application_status_history` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicationId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fromStatus` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `toStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changedBy` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_history_application` (`applicationId`,`createdAt`),
  KEY `idx_application_history_changed_by` (`changedBy`),
  CONSTRAINT `fk_application_history_application` FOREIGN KEY (`applicationId`) REFERENCES `internship_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_history_user` FOREIGN KEY (`changedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.application_status_history: ~0 rows (approximately)
DELETE FROM `application_status_history`;

-- Dumping structure for table talenthub.assessments
CREATE TABLE IF NOT EXISTS `assessments` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacherId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `overallScore` decimal(7,2) NOT NULL,
  `comment` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessments_teacherId` (`teacherId`),
  KEY `idx_assessments_studentId` (`studentId`),
  KEY `idx_assessments_activityId` (`activityId`),
  CONSTRAINT `fk_assessments_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assessments_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessments_teacher` FOREIGN KEY (`teacherId`) REFERENCES `teacher_profiles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.assessments: ~0 rows (approximately)
DELETE FROM `assessments`;

-- Dumping structure for table talenthub.assessment_criteria
CREATE TABLE IF NOT EXISTS `assessment_criteria` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `minScore` decimal(7,2) NOT NULL,
  `maxScore` decimal(7,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_assessment_criteria_score_range` CHECK ((`maxScore` >= `minScore`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.assessment_criteria: ~0 rows (approximately)
DELETE FROM `assessment_criteria`;

-- Dumping structure for table talenthub.assessment_scores
CREATE TABLE IF NOT EXISTS `assessment_scores` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assessmentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `criteriaId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` decimal(7,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessment_scores_assessmentId` (`assessmentId`),
  KEY `idx_assessment_scores_criteriaId` (`criteriaId`),
  CONSTRAINT `fk_assessment_scores_assessment` FOREIGN KEY (`assessmentId`) REFERENCES `assessments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assessment_scores_criteria` FOREIGN KEY (`criteriaId`) REFERENCES `assessment_criteria` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.assessment_scores: ~0 rows (approximately)
DELETE FROM `assessment_scores`;

-- Dumping structure for table talenthub.audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entityType` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_userId` (`userId`),
  KEY `idx_audit_logs_entity` (`entityType`,`entityId`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.audit_logs: ~0 rows (approximately)
DELETE FROM `audit_logs`;
INSERT INTO `audit_logs` (`id`, `userId`, `action`, `entityType`, `entityId`, `createdAt`) VALUES
	('7139e386-16fd-4579-88c4-e5703d9795c7', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', 'auth.login_failed', 'user', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', '2026-08-16 14:22:08'),
	('81bd1744-8be2-4582-97b6-c22ce1b5b744', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', 'auth.login_succeeded', 'user', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', '2026-08-16 14:22:40'),
	('ec7a6354-ffb1-482b-b6ad-60602e5bb674', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', 'auth.student_registered', 'user', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', '2026-08-16 14:21:58');

-- Dumping structure for table talenthub.badges
CREATE TABLE IF NOT EXISTS `badges` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruleCriteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  CONSTRAINT `badges_chk_1` CHECK (json_valid(`ruleCriteria`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.badges: ~0 rows (approximately)
DELETE FROM `badges`;

-- Dumping structure for table talenthub.certificates
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issueDate` date NOT NULL,
  `expiryDate` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificates_studentId` (`studentId`),
  CONSTRAINT `fk_certificates_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.certificates: ~0 rows (approximately)
DELETE FROM `certificates`;

-- Dumping structure for table talenthub.checkins
CREATE TABLE IF NOT EXISTS `checkins` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registrationId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qrTokenId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checkedInAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_checkins_registrationId` (`registrationId`),
  KEY `idx_checkins_qrTokenId` (`qrTokenId`),
  CONSTRAINT `fk_checkins_qr_token` FOREIGN KEY (`qrTokenId`) REFERENCES `activity_qr_tokens` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_checkins_registration` FOREIGN KEY (`registrationId`) REFERENCES `activity_registrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.checkins: ~0 rows (approximately)
DELETE FROM `checkins`;

-- Dumping structure for table talenthub.classes
CREATE TABLE IF NOT EXISTS `classes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gradeLevel` int NOT NULL,
  `academicYear` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_classes_schoolId` (`schoolId`),
  CONSTRAINT `fk_classes_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.classes: ~0 rows (approximately)
DELETE FROM `classes`;
INSERT INTO `classes` (`id`, `schoolId`, `name`, `gradeLevel`, `academicYear`) VALUES
	('8e71b032-2fe9-404e-abfe-728587c6c3d0', 'da811c4f-2f74-4fdd-80b0-dd6f26109783', 'SE08201', 8, '2026-2027');

-- Dumping structure for table talenthub.contact_requests
CREATE TABLE IF NOT EXISTS `contact_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enterpriseId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_requests_enterpriseId` (`enterpriseId`),
  KEY `idx_contact_requests_studentId` (`studentId`),
  CONSTRAINT `fk_contact_requests_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_contact_requests_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.contact_requests: ~0 rows (approximately)
DELETE FROM `contact_requests`;

-- Dumping structure for table talenthub.enterprises
CREATE TABLE IF NOT EXISTS `enterprises` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logoUrl` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `industry` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verificationStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verificationNote` text COLLATE utf8mb4_unicode_ci,
  `verifiedAt` timestamp NULL DEFAULT NULL,
  `verifiedBy` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enterprises_verifiedBy` (`verifiedBy`),
  CONSTRAINT `fk_enterprises_verified_by` FOREIGN KEY (`verifiedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.enterprises: ~0 rows (approximately)
DELETE FROM `enterprises`;

-- Dumping structure for table talenthub.enterprise_members
CREATE TABLE IF NOT EXISTS `enterprise_members` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enterpriseId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise_members_enterpriseId` (`enterpriseId`),
  KEY `idx_enterprise_members_userId` (`userId`),
  CONSTRAINT `fk_enterprise_members_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enterprise_members_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.enterprise_members: ~0 rows (approximately)
DELETE FROM `enterprise_members`;

-- Dumping structure for table talenthub.experience_logs
CREATE TABLE IF NOT EXISTS `experience_logs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activityId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checkinId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hours` decimal(7,2) NOT NULL,
  `auditReason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_experience_logs_studentId` (`studentId`),
  KEY `idx_experience_logs_activityId` (`activityId`),
  KEY `idx_experience_logs_checkinId` (`checkinId`),
  CONSTRAINT `fk_experience_logs_activity` FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_experience_logs_checkin` FOREIGN KEY (`checkinId`) REFERENCES `checkins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_experience_logs_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_experience_logs_hours` CHECK ((`hours` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.experience_logs: ~0 rows (approximately)
DELETE FROM `experience_logs`;

-- Dumping structure for table talenthub.internship_applications
CREATE TABLE IF NOT EXISTS `internship_applications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matchScore` decimal(5,2) DEFAULT NULL,
  `reviewerId` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cvUrl` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewerNote` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appliedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

-- Dumping data for table talenthub.internship_applications: ~0 rows (approximately)
DELETE FROM `internship_applications`;

-- Dumping structure for table talenthub.internship_posts
CREATE TABLE IF NOT EXISTS `internship_posts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enterpriseId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `benefits` text COLLATE utf8mb4_unicode_ci,
  `duration` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `workMode` enum('onsite','remote','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'onsite',
  `openings` int unsigned NOT NULL DEFAULT '1',
  `targetStudents` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_internship_posts_enterpriseId` (`enterpriseId`),
  KEY `idx_internship_posts_enterprise` (`enterpriseId`),
  KEY `idx_internship_posts_status_deadline` (`status`,`deadline`),
  KEY `idx_internship_posts_work_mode` (`workMode`),
  CONSTRAINT `fk_internship_posts_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.internship_posts: ~0 rows (approximately)
DELETE FROM `internship_posts`;

-- Dumping structure for table talenthub.internship_requirements
CREATE TABLE IF NOT EXISTS `internship_requirements` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skillId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `minLevel` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_internship_requirements_postId` (`postId`),
  KEY `idx_internship_requirements_skillId` (`skillId`),
  CONSTRAINT `fk_internship_requirements_post` FOREIGN KEY (`postId`) REFERENCES `internship_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_requirements_skill` FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.internship_requirements: ~0 rows (approximately)
DELETE FROM `internship_requirements`;

-- Dumping structure for table talenthub.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notificationType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `relatedEntityType` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relatedEntityId` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliveryChannel` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notificationStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isRead` tinyint(1) NOT NULL DEFAULT '0',
  `readAt` timestamp NULL DEFAULT NULL,
  `sentAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_userId` (`userId`),
  KEY `idx_notifications_related_entity` (`relatedEntityType`,`relatedEntityId`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.notifications: ~0 rows (approximately)
DELETE FROM `notifications`;

-- Dumping structure for table talenthub.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External order service stub shown in the ERD';

-- Dumping data for table talenthub.orders: ~0 rows (approximately)
DELETE FROM `orders`;

-- Dumping structure for table talenthub.payment_orders
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orderId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paymentMethod` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paymentStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `providerReference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paidAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_orders_orderId` (`orderId`),
  CONSTRAINT `fk_payment_orders_order` FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_orders_amount` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.payment_orders: ~0 rows (approximately)
DELETE FROM `payment_orders`;

-- Dumping structure for table talenthub.payment_transactions
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paymentOrderId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transactionCode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `providerTransactionId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transactionType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transactionStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requestReference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failureCode` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failureMessage` text COLLATE utf8mb4_unicode_ci,
  `processedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_transactions_code` (`transactionCode`),
  UNIQUE KEY `uk_payment_transactions_request_ref` (`requestReference`),
  KEY `idx_payment_transactions_paymentOrderId` (`paymentOrderId`),
  CONSTRAINT `fk_payment_transactions_payment_order` FOREIGN KEY (`paymentOrderId`) REFERENCES `payment_orders` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_transactions_amount` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.payment_transactions: ~0 rows (approximately)
DELETE FROM `payment_transactions`;

-- Dumping structure for table talenthub.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `createdAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.permissions: ~0 rows (approximately)
DELETE FROM `permissions`;
INSERT INTO `permissions` (`id`, `code`, `description`, `createdAt`, `updatedAt`) VALUES
	('4e7b7d04-9985-11f1-a0de-74d4dd289692', 'qr_session.create_managed', 'Create QR sessions for activities managed by the teacher', '2026-08-16 22:15:25.133161', '2026-08-16 22:15:25.133161'),
	('4e7b8f77-9985-11f1-a0de-74d4dd289692', 'qr_session.read_managed', 'View QR sessions for activities managed by the teacher', '2026-08-16 22:15:25.133161', '2026-08-16 22:15:25.133161'),
	('4e7b93a1-9985-11f1-a0de-74d4dd289692', 'qr_session.revoke_managed', 'Revoke QR sessions for activities managed by the teacher', '2026-08-16 22:15:25.133161', '2026-08-16 22:15:25.133161'),
	('4e7b9629-9985-11f1-a0de-74d4dd289692', 'checkin.read_managed', 'View check-in records for activities managed by the teacher', '2026-08-16 22:15:25.133161', '2026-08-16 22:15:25.133161');

-- Dumping structure for table talenthub.privacy_consents
CREATE TABLE IF NOT EXISTS `privacy_consents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isGranted` tinyint(1) NOT NULL DEFAULT '0',
  `policyVersion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revokedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_privacy_consents_studentId` (`studentId`),
  CONSTRAINT `fk_privacy_consents_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.privacy_consents: ~0 rows (approximately)
DELETE FROM `privacy_consents`;

-- Dumping structure for table talenthub.projects
CREATE TABLE IF NOT EXISTS `projects` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `targetAmount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `raisedAmount` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_projects_schoolId` (`schoolId`),
  CONSTRAINT `fk_projects_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.projects: ~0 rows (approximately)
DELETE FROM `projects`;

-- Dumping structure for table talenthub.project_members
CREATE TABLE IF NOT EXISTS `project_members` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_members_projectId` (`projectId`),
  KEY `idx_project_members_studentId` (`studentId`),
  CONSTRAINT `fk_project_members_project` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_project_members_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.project_members: ~0 rows (approximately)
DELETE FROM `project_members`;

-- Dumping structure for table talenthub.project_sponsorships
CREATE TABLE IF NOT EXISTS `project_sponsorships` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enterpriseId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `projectId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `cancelledAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_sponsorships_enterpriseId` (`enterpriseId`),
  KEY `idx_project_sponsorships_projectId` (`projectId`),
  KEY `idx_sponsorship_enterprise` (`enterpriseId`),
  KEY `idx_sponsorship_project` (`projectId`),
  KEY `idx_sponsorship_status` (`status`),
  CONSTRAINT `fk_project_sponsorships_enterprise` FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_project_sponsorships_project` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_project_sponsorships_amount` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.project_sponsorships: ~0 rows (approximately)
DELETE FROM `project_sponsorships`;

-- Dumping structure for table talenthub.reports
CREATE TABLE IF NOT EXISTS `reports` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generatedByUserId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reportType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fileUrl` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `periodStart` date NOT NULL,
  `periodEnd` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_schoolId` (`schoolId`),
  KEY `idx_reports_generatedByUserId` (`generatedByUserId`),
  CONSTRAINT `fk_reports_generated_by_user` FOREIGN KEY (`generatedByUserId`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reports_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_reports_period` CHECK ((`periodEnd` >= `periodStart`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.reports: ~0 rows (approximately)
DELETE FROM `reports`;

-- Dumping structure for table talenthub.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isSystem` tinyint unsigned NOT NULL DEFAULT '1',
  `createdAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`),
  CONSTRAINT `chk_roles_is_system` CHECK ((`isSystem` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.roles: ~0 rows (approximately)
DELETE FROM `roles`;

-- Dumping structure for table talenthub.schema_migrations
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` char(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int unsigned NOT NULL,
  `executionMs` int unsigned NOT NULL,
  `appliedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`version`),
  UNIQUE KEY `uq_schema_migrations_name` (`name`),
  KEY `idx_schema_migrations_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.schema_migrations: ~0 rows (approximately)
DELETE FROM `schema_migrations`;

-- Dumping structure for table talenthub.schools
CREATE TABLE IF NOT EXISTS `schools` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.schools: ~0 rows (approximately)
DELETE FROM `schools`;
INSERT INTO `schools` (`id`, `name`, `status`) VALUES
	('da811c4f-2f74-4fdd-80b0-dd6f26109783', 'BTEC', 'active');

-- Dumping structure for table talenthub.skills
CREATE TABLE IF NOT EXISTS `skills` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.skills: ~0 rows (approximately)
DELETE FROM `skills`;

-- Dumping structure for table talenthub.sponsorship_status_history
CREATE TABLE IF NOT EXISTS `sponsorship_status_history` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sponsorshipId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fromStatus` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `toStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changedBy` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sponsorship_history_sponsorship` (`sponsorshipId`,`createdAt`),
  KEY `idx_sponsorship_history_changed_by` (`changedBy`),
  CONSTRAINT `fk_sponsorship_history_sponsorship` FOREIGN KEY (`sponsorshipId`) REFERENCES `project_sponsorships` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sponsorship_history_user` FOREIGN KEY (`changedBy`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.sponsorship_status_history: ~0 rows (approximately)
DELETE FROM `sponsorship_status_history`;

-- Dumping structure for table talenthub.student_badges
CREATE TABLE IF NOT EXISTS `student_badges` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `badgeId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `awardedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sourceEvent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_badges_studentId` (`studentId`),
  KEY `idx_student_badges_badgeId` (`badgeId`),
  CONSTRAINT `fk_student_badges_badge` FOREIGN KEY (`badgeId`) REFERENCES `badges` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_badges_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.student_badges: ~0 rows (approximately)
DELETE FROM `student_badges`;

-- Dumping structure for table talenthub.student_profiles
CREATE TABLE IF NOT EXISTS `student_profiles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dateOfBirth` date NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studyStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_profiles_userId` (`userId`),
  KEY `idx_student_profiles_classId` (`classId`),
  CONSTRAINT `fk_student_profiles_class` FOREIGN KEY (`classId`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.student_profiles: ~0 rows (approximately)
DELETE FROM `student_profiles`;
INSERT INTO `student_profiles` (`id`, `userId`, `classId`, `dateOfBirth`, `phone`, `studyStatus`) VALUES
	('a62df486-850b-401e-90de-da6fd0dfb750', '195c455c-dfe4-4f94-aea1-dafa1913ce6c', '8e71b032-2fe9-404e-abfe-728587c6c3d0', '2006-11-07', '0123456789', 'active');

-- Dumping structure for table talenthub.student_skills
CREATE TABLE IF NOT EXISTS `student_skills` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skillId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verifiedStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verifiedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_skills_studentId` (`studentId`),
  KEY `idx_student_skills_skillId` (`skillId`),
  CONSTRAINT `fk_student_skills_skill` FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_student_skills_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.student_skills: ~0 rows (approximately)
DELETE FROM `student_skills`;

-- Dumping structure for table talenthub.talent_tests
CREATE TABLE IF NOT EXISTS `talent_tests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `talent_tests_chk_1` CHECK (json_valid(`dimensions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.talent_tests: ~0 rows (approximately)
DELETE FROM `talent_tests`;

-- Dumping structure for table talenthub.teacher_profiles
CREATE TABLE IF NOT EXISTS `teacher_profiles` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schoolId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isSchoolAdmin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_teacher_profiles_userId` (`userId`),
  KEY `idx_teacher_profiles_schoolId` (`schoolId`),
  CONSTRAINT `fk_teacher_profiles_school` FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_profiles_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.teacher_profiles: ~0 rows (approximately)
DELETE FROM `teacher_profiles`;

-- Dumping structure for table talenthub.test_attempts
CREATE TABLE IF NOT EXISTS `test_attempts` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `testId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `studentId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `startedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completedAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_attempts_testId` (`testId`),
  KEY `idx_test_attempts_studentId` (`studentId`),
  CONSTRAINT `fk_test_attempts_student` FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_test_attempts_test` FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.test_attempts: ~0 rows (approximately)
DELETE FROM `test_attempts`;

-- Dumping structure for table talenthub.test_questions
CREATE TABLE IF NOT EXISTS `test_questions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `testId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_questions_testId` (`testId`),
  CONSTRAINT `fk_test_questions_test` FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_questions_chk_1` CHECK (json_valid(`options`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.test_questions: ~0 rows (approximately)
DELETE FROM `test_questions`;

-- Dumping structure for table talenthub.test_results
CREATE TABLE IF NOT EXISTS `test_results` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attemptId` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resultCode` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dimensionScores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_results_attemptId` (`attemptId`),
  CONSTRAINT `fk_test_results_attempt` FOREIGN KEY (`attemptId`) REFERENCES `test_attempts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `test_results_chk_1` CHECK (json_valid(`dimensionScores`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.test_results: ~0 rows (approximately)
DELETE FROM `test_results`;

-- Dumping structure for table talenthub.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `passwordHash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullName` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.users: ~0 rows (approximately)
DELETE FROM `users`;
INSERT INTO `users` (`id`, `email`, `passwordHash`, `fullName`, `roles`, `status`, `createdAt`) VALUES
	('195c455c-dfe4-4f94-aea1-dafa1913ce6c', 'nguyenvana@gmail.com', '$2y$10$Oc9dEWVpQwZHEwrynQLgdufKOG1CVDpcbwVhP8.lDBjF3ufOCCLXO', 'Nguyễn Văn A', 'student', 'active', '2026-08-16 14:21:58');

-- Dumping structure for table talenthub.webhook_events
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `providerEventId` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `eventType` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paymentTransactionId` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `signatureValid` tinyint(1) NOT NULL DEFAULT '0',
  `processingStatus` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attemptCount` int NOT NULL DEFAULT '0',
  `errorMessage` text COLLATE utf8mb4_unicode_ci,
  `receivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processedAt` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_events_paymentTransactionId` (`paymentTransactionId`),
  KEY `idx_webhook_events_provider_event` (`provider`,`providerEventId`),
  CONSTRAINT `fk_webhook_events_payment_transaction` FOREIGN KEY (`paymentTransactionId`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_webhook_events_attempt_count` CHECK ((`attemptCount` >= 0)),
  CONSTRAINT `webhook_events_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table talenthub.webhook_events: ~0 rows (approximately)
DELETE FROM `webhook_events`;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
