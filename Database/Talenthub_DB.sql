-- TalentHub database schema generated from Project_ERD_Updated(1).png
-- Target: MySQL 8.0 / MariaDB, executable from HeidiSQL
-- UUID values are stored as CHAR(36). The application should generate them
-- (for example, INSERT ... VALUES (UUID(), ...)).
-- ERD fields marked "enum" are represented by VARCHAR(50) because the image
-- does not define their permitted values. Replace them with ENUM/CHECK rules
-- after the project team confirms the exact status lists.

CREATE DATABASE IF NOT EXISTS `talenthub`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `talenthub`;

-- =========================================================
-- 1. AUTHENTICATION AND SCHOOL ADMINISTRATION
-- =========================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `passwordHash` VARCHAR(255) NOT NULL,
  `fullName` VARCHAR(150) NOT NULL,
  `roles` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schools` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classes` (
  `id` CHAR(36) NOT NULL,
  `schoolId` CHAR(36) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `gradeLevel` INT NOT NULL,
  `academicYear` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_classes_schoolId` (`schoolId`),
  CONSTRAINT `fk_classes_school`
    FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teacher_profiles` (
  `id` CHAR(36) NOT NULL,
  `userId` CHAR(36) NOT NULL,
  `schoolId` CHAR(36) NOT NULL,
  `isSchoolAdmin` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_profiles_userId` (`userId`),
  KEY `idx_teacher_profiles_schoolId` (`schoolId`),
  CONSTRAINT `fk_teacher_profiles_user`
    FOREIGN KEY (`userId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_profiles_school`
    FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_profiles` (
  `id` CHAR(36) NOT NULL,
  `userId` CHAR(36) NOT NULL,
  `classId` CHAR(36) NOT NULL,
  `dateOfBirth` DATE NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `studyStatus` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_profiles_userId` (`userId`),
  KEY `idx_student_profiles_classId` (`classId`),
  CONSTRAINT `fk_student_profiles_user`
    FOREIGN KEY (`userId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_student_profiles_class`
    FOREIGN KEY (`classId`) REFERENCES `classes` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. INDEPENDENT BUSINESS TABLES
-- =========================================================

CREATE TABLE IF NOT EXISTS `enterprises` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `logoUrl` VARCHAR(500) NULL,
  `industry` VARCHAR(150) NULL,
  `description` TEXT NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(30) NULL,
  `website` VARCHAR(500) NULL,
  `address` VARCHAR(500) NULL,
  `verificationStatus` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `verificationNote` TEXT NULL,
  `verifiedAt` TIMESTAMP NULL DEFAULT NULL,
  `verifiedBy` CHAR(36) NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enterprises_verifiedBy` (`verifiedBy`),
  CONSTRAINT `fk_enterprises_verified_by`
    FOREIGN KEY (`verifiedBy`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` CHAR(36) NOT NULL,
  `schoolId` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `targetAmount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `raisedAmount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_projects_schoolId` (`schoolId`),
  CONSTRAINT `fk_projects_school`
    FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skills` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `badges` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `level` VARCHAR(50) NOT NULL,
  `ruleCriteria` JSON NOT NULL,
  `isActive` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `talent_tests` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `dimensions` JSON NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessment_criteria` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `minScore` DECIMAL(7,2) NOT NULL,
  `maxScore` DECIMAL(7,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_assessment_criteria_score_range`
    CHECK (`maxScore` >= `minScore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` CHAR(36) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='External order service stub shown in the ERD';

-- =========================================================
-- 3. ACTIVITIES AND EXPERIENCE
-- =========================================================

CREATE TABLE IF NOT EXISTS `activities` (
  `id` CHAR(36) NOT NULL,
  `schoolId` CHAR(36) NOT NULL,
  `createdByTeacherId` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `startAt` TIMESTAMP NOT NULL,
  `endAt` TIMESTAMP NULL DEFAULT NULL,
  `capacity` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activities_schoolId` (`schoolId`),
  KEY `idx_activities_createdByTeacherId` (`createdByTeacherId`),
  CONSTRAINT `fk_activities_school`
    FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_activities_teacher`
    FOREIGN KEY (`createdByTeacherId`) REFERENCES `teacher_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_activities_capacity`
    CHECK (`capacity` >= 0),
  CONSTRAINT `chk_activities_time`
    CHECK (`endAt` IS NULL OR `endAt` >= `startAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_registrations` (
  `id` CHAR(36) NOT NULL,
  `activityId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_registrations_activityId` (`activityId`),
  KEY `idx_activity_registrations_studentId` (`studentId`),
  CONSTRAINT `fk_activity_registrations_activity`
    FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_activity_registrations_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_qr_tokens` (
  `id` CHAR(36) NOT NULL,
  `activityId` CHAR(36) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expiresAt` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_qr_tokens_token` (`token`),
  KEY `idx_activity_qr_tokens_activityId` (`activityId`),
  CONSTRAINT `fk_activity_qr_tokens_activity`
    FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkins` (
  `id` CHAR(36) NOT NULL,
  `registrationId` CHAR(36) NOT NULL,
  `qrTokenId` CHAR(36) NOT NULL,
  `checkedInAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_checkins_registrationId` (`registrationId`),
  KEY `idx_checkins_qrTokenId` (`qrTokenId`),
  CONSTRAINT `fk_checkins_registration`
    FOREIGN KEY (`registrationId`) REFERENCES `activity_registrations` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_checkins_qr_token`
    FOREIGN KEY (`qrTokenId`) REFERENCES `activity_qr_tokens` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `experience_logs` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `activityId` CHAR(36) NOT NULL,
  `checkinId` CHAR(36) NOT NULL,
  `hours` DECIMAL(7,2) NOT NULL,
  `auditReason` VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_experience_logs_studentId` (`studentId`),
  KEY `idx_experience_logs_activityId` (`activityId`),
  KEY `idx_experience_logs_checkinId` (`checkinId`),
  CONSTRAINT `fk_experience_logs_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_experience_logs_activity`
    FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_experience_logs_checkin`
    FOREIGN KEY (`checkinId`) REFERENCES `checkins` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_experience_logs_hours`
    CHECK (`hours` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. STUDENTS, SKILLS, PROJECTS AND GAMIFICATION
-- =========================================================

CREATE TABLE IF NOT EXISTS `certificates` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `issuer` VARCHAR(255) NOT NULL,
  `issueDate` DATE NOT NULL,
  `expiryDate` DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certificates_studentId` (`studentId`),
  CONSTRAINT `fk_certificates_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `privacy_consents` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `scope` VARCHAR(50) NOT NULL,
  `isGranted` BOOLEAN NOT NULL DEFAULT FALSE,
  `policyVersion` VARCHAR(50) NOT NULL,
  `revokedAt` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_privacy_consents_studentId` (`studentId`),
  CONSTRAINT `fk_privacy_consents_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_members` (
  `id` CHAR(36) NOT NULL,
  `projectId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_members_projectId` (`projectId`),
  KEY `idx_project_members_studentId` (`studentId`),
  CONSTRAINT `fk_project_members_project`
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_project_members_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_skills` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `skillId` CHAR(36) NOT NULL,
  `level` VARCHAR(50) NOT NULL,
  `verifiedStatus` VARCHAR(50) NOT NULL,
  `verifiedAt` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_skills_studentId` (`studentId`),
  KEY `idx_student_skills_skillId` (`skillId`),
  CONSTRAINT `fk_student_skills_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_student_skills_skill`
    FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_badges` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `badgeId` CHAR(36) NOT NULL,
  `awardedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sourceEvent` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_badges_studentId` (`studentId`),
  KEY `idx_student_badges_badgeId` (`badgeId`),
  CONSTRAINT `fk_student_badges_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_student_badges_badge`
    FOREIGN KEY (`badgeId`) REFERENCES `badges` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enterprise_members` (
  `id` CHAR(36) NOT NULL,
  `enterpriseId` CHAR(36) NOT NULL,
  `userId` CHAR(36) NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise_members_enterpriseId` (`enterpriseId`),
  KEY `idx_enterprise_members_userId` (`userId`),
  CONSTRAINT `fk_enterprise_members_enterprise`
    FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_enterprise_members_user`
    FOREIGN KEY (`userId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_sponsorships` (
  `id` CHAR(36) NOT NULL,
  `enterpriseId` CHAR(36) NOT NULL,
  `projectId` CHAR(36) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_sponsorships_enterpriseId` (`enterpriseId`),
  KEY `idx_project_sponsorships_projectId` (`projectId`),
  CONSTRAINT `fk_project_sponsorships_enterprise`
    FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_project_sponsorships_project`
    FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_project_sponsorships_amount`
    CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. TESTS AND ASSESSMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS `test_attempts` (
  `id` CHAR(36) NOT NULL,
  `testId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `startedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completedAt` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_attempts_testId` (`testId`),
  KEY `idx_test_attempts_studentId` (`studentId`),
  CONSTRAINT `fk_test_attempts_test`
    FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_test_attempts_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_questions` (
  `id` CHAR(36) NOT NULL,
  `testId` CHAR(36) NOT NULL,
  `content` VARCHAR(1000) NOT NULL,
  `options` JSON NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_questions_testId` (`testId`),
  CONSTRAINT `fk_test_questions_test`
    FOREIGN KEY (`testId`) REFERENCES `talent_tests` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_results` (
  `id` CHAR(36) NOT NULL,
  `attemptId` CHAR(36) NOT NULL,
  `resultCode` VARCHAR(100) NOT NULL,
  `summary` VARCHAR(1000) NOT NULL,
  `dimensionScores` JSON NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_test_results_attemptId` (`attemptId`),
  CONSTRAINT `fk_test_results_attempt`
    FOREIGN KEY (`attemptId`) REFERENCES `test_attempts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessments` (
  `id` CHAR(36) NOT NULL,
  `teacherId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `activityId` CHAR(36) NOT NULL,
  `overallScore` DECIMAL(7,2) NOT NULL,
  `comment` VARCHAR(1000) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessments_teacherId` (`teacherId`),
  KEY `idx_assessments_studentId` (`studentId`),
  KEY `idx_assessments_activityId` (`activityId`),
  CONSTRAINT `fk_assessments_teacher`
    FOREIGN KEY (`teacherId`) REFERENCES `teacher_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_assessments_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_assessments_activity`
    FOREIGN KEY (`activityId`) REFERENCES `activities` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessment_scores` (
  `id` CHAR(36) NOT NULL,
  `assessmentId` CHAR(36) NOT NULL,
  `criteriaId` CHAR(36) NOT NULL,
  `score` DECIMAL(7,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessment_scores_assessmentId` (`assessmentId`),
  KEY `idx_assessment_scores_criteriaId` (`criteriaId`),
  CONSTRAINT `fk_assessment_scores_assessment`
    FOREIGN KEY (`assessmentId`) REFERENCES `assessments` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_scores_criteria`
    FOREIGN KEY (`criteriaId`) REFERENCES `assessment_criteria` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. ENTERPRISE, INTERNSHIP AND GOVERNANCE
-- =========================================================

CREATE TABLE IF NOT EXISTS `reports` (
  `id` CHAR(36) NOT NULL,
  `schoolId` CHAR(36) NOT NULL,
  `generatedByUserId` CHAR(36) NOT NULL,
  `reportType` VARCHAR(50) NOT NULL,
  `fileUrl` VARCHAR(500) NOT NULL,
  `periodStart` DATE NOT NULL,
  `periodEnd` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_schoolId` (`schoolId`),
  KEY `idx_reports_generatedByUserId` (`generatedByUserId`),
  CONSTRAINT `fk_reports_school`
    FOREIGN KEY (`schoolId`) REFERENCES `schools` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_reports_generated_by_user`
    FOREIGN KEY (`generatedByUserId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_reports_period`
    CHECK (`periodEnd` >= `periodStart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` CHAR(36) NOT NULL,
  `userId` CHAR(36) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entityType` VARCHAR(100) NOT NULL,
  `entityId` CHAR(36) NOT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_userId` (`userId`),
  KEY `idx_audit_logs_entity` (`entityType`, `entityId`),
  CONSTRAINT `fk_audit_logs_user`
    FOREIGN KEY (`userId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_requests` (
  `id` CHAR(36) NOT NULL,
  `enterpriseId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_requests_enterpriseId` (`enterpriseId`),
  KEY `idx_contact_requests_studentId` (`studentId`),
  CONSTRAINT `fk_contact_requests_enterprise`
    FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_contact_requests_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `careerPath` VARCHAR(255) NOT NULL,
  `suggestedActions` VARCHAR(2000) NOT NULL,
  `confidenceScore` DECIMAL(7,4) NOT NULL,
  `modelVersion` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_recommendations_studentId` (`studentId`),
  CONSTRAINT `fk_ai_recommendations_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_ai_recommendations_confidence`
    CHECK (`confidenceScore` >= 0 AND `confidenceScore` <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_posts` (
  `id` CHAR(36) NOT NULL,
  `enterpriseId` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `deadline` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_internship_posts_enterpriseId` (`enterpriseId`),
  CONSTRAINT `fk_internship_posts_enterprise`
    FOREIGN KEY (`enterpriseId`) REFERENCES `enterprises` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_requirements` (
  `id` CHAR(36) NOT NULL,
  `postId` CHAR(36) NOT NULL,
  `skillId` CHAR(36) NOT NULL,
  `minLevel` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_internship_requirements_postId` (`postId`),
  KEY `idx_internship_requirements_skillId` (`skillId`),
  CONSTRAINT `fk_internship_requirements_post`
    FOREIGN KEY (`postId`) REFERENCES `internship_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_internship_requirements_skill`
    FOREIGN KEY (`skillId`) REFERENCES `skills` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_applications` (
  `id` CHAR(36) NOT NULL,
  `postId` CHAR(36) NOT NULL,
  `studentId` CHAR(36) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `cvUrl` VARCHAR(500) NOT NULL,
  `reviewerNote` VARCHAR(1000) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_internship_applications_postId` (`postId`),
  KEY `idx_internship_applications_studentId` (`studentId`),
  CONSTRAINT `fk_internship_applications_post`
    FOREIGN KEY (`postId`) REFERENCES `internship_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_internship_applications_student`
    FOREIGN KEY (`studentId`) REFERENCES `student_profiles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. NOTIFICATIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` CHAR(36) NOT NULL,
  `userId` CHAR(36) NOT NULL,
  `notificationType` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `relatedEntityType` VARCHAR(100) NULL,
  `relatedEntityId` CHAR(36) NULL,
  `deliveryChannel` VARCHAR(50) NOT NULL,
  `notificationStatus` VARCHAR(50) NOT NULL,
  `isRead` BOOLEAN NOT NULL DEFAULT FALSE,
  `readAt` TIMESTAMP NULL DEFAULT NULL,
  `sentAt` TIMESTAMP NULL DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_userId` (`userId`),
  KEY `idx_notifications_related_entity` (`relatedEntityType`, `relatedEntityId`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`userId`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. PAYMENTS AND WEBHOOKS
-- =========================================================

CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` CHAR(36) NOT NULL,
  `orderId` CHAR(36) NOT NULL,
  `paymentMethod` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL,
  `paymentStatus` VARCHAR(50) NOT NULL,
  `provider` VARCHAR(100) NOT NULL,
  `providerReference` VARCHAR(255) NULL,
  `paidAt` TIMESTAMP NULL DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_orders_orderId` (`orderId`),
  CONSTRAINT `fk_payment_orders_order`
    FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_payment_orders_amount`
    CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` CHAR(36) NOT NULL,
  `paymentOrderId` CHAR(36) NOT NULL,
  `transactionCode` VARCHAR(255) NOT NULL,
  `providerTransactionId` VARCHAR(255) NULL,
  `transactionType` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL,
  `transactionStatus` VARCHAR(50) NOT NULL,
  `requestReference` VARCHAR(255) NOT NULL,
  `failureCode` VARCHAR(100) NULL,
  `failureMessage` TEXT NULL,
  `processedAt` TIMESTAMP NULL DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_transactions_code` (`transactionCode`),
  UNIQUE KEY `uk_payment_transactions_request_ref` (`requestReference`),
  KEY `idx_payment_transactions_paymentOrderId` (`paymentOrderId`),
  CONSTRAINT `fk_payment_transactions_payment_order`
    FOREIGN KEY (`paymentOrderId`) REFERENCES `payment_orders` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_payment_transactions_amount`
    CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` CHAR(36) NOT NULL,
  `provider` VARCHAR(100) NOT NULL,
  `providerEventId` VARCHAR(255) NOT NULL,
  `eventType` VARCHAR(100) NOT NULL,
  `paymentTransactionId` CHAR(36) NULL,
  `payload` JSON NOT NULL,
  `signatureValid` BOOLEAN NOT NULL DEFAULT FALSE,
  `processingStatus` VARCHAR(50) NOT NULL,
  `attemptCount` INT NOT NULL DEFAULT 0,
  `errorMessage` TEXT NULL,
  `receivedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processedAt` TIMESTAMP NULL DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_events_paymentTransactionId` (`paymentTransactionId`),
  KEY `idx_webhook_events_provider_event` (`provider`, `providerEventId`),
  CONSTRAINT `fk_webhook_events_payment_transaction`
    FOREIGN KEY (`paymentTransactionId`) REFERENCES `payment_transactions` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_webhook_events_attempt_count`
    CHECK (`attemptCount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of TalentHub schema.
