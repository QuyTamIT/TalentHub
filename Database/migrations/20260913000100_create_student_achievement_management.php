<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create canonical student certificate awards and online learning time tracking';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach ([
            'student_profiles',
            'school_certificate_catalog',
            'student_school_certificates',
            'badges',
            'badge_rule_definitions',
            'student_badges',
            'users',
            'roles',
            'permissions',
            'role_permissions',
        ] as $table) {
            $context->assertTableExists($table);
        }

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Student achievement migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        if (!$context->tableExists('student_certificates')) {
            $context->execute(<<<'SQL'
CREATE TABLE student_certificates (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  certificateCatalogId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'issued',
  issuedAt DATETIME(6) NOT NULL,
  issuedBy CHAR(36) NULL,
  issueSource VARCHAR(20) NOT NULL DEFAULT 'manual',
  reason VARCHAR(1000) NOT NULL,
  activityName VARCHAR(255) NULL,
  evidenceContext JSON NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_certificates_award (studentId, certificateCatalogId),
  KEY idx_student_certificates_student_status (studentId, status, issuedAt),
  KEY idx_student_certificates_catalog (certificateCatalogId, issuedAt),
  CONSTRAINT fk_student_certificates_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_student_certificates_catalog FOREIGN KEY (certificateCatalogId) REFERENCES school_certificate_catalog(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_student_certificates_issuer FOREIGN KEY (issuedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_student_certificates_status CHECK (status IN ('issued','revoked')),
  CONSTRAINT chk_student_certificates_source CHECK (issueSource IN ('manual','automatic','legacy'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        if (!$context->tableExists('student_learning_time_logs')) {
            $context->execute(<<<'SQL'
CREATE TABLE student_learning_time_logs (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  activityDate DATE NOT NULL,
  activeSeconds INT UNSIGNED NOT NULL DEFAULT 0,
  lastHeartbeatAt DATETIME(6) NOT NULL,
  lastSessionKey CHAR(36) NOT NULL,
  lastPagePath VARCHAR(255) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_learning_day (studentId, activityDate),
  KEY idx_student_learning_activity_date (activityDate, studentId),
  CONSTRAINT fk_student_learning_time_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_student_learning_active_seconds CHECK (activeSeconds <= 86400)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        $context->execute(<<<'SQL'
INSERT IGNORE INTO student_certificates (
  id, studentId, certificateCatalogId, status, issuedAt, issuedBy,
  issueSource, reason, activityName, evidenceContext, createdAt, updatedAt
)
SELECT
  ssc.id,
  ssc.studentId,
  ssc.certificateCatalogId,
  ssc.status,
  ssc.issuedAt,
  ssc.issuedBy,
  'legacy',
  COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ssc.evidenceContext, '$.reason')), ''), 'Dữ liệu chứng chỉ Nhà trường trước khi chuẩn hóa'),
  NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ssc.evidenceContext, '$.activityName')), ''),
  ssc.evidenceContext,
  ssc.createdAt,
  ssc.updatedAt
FROM student_school_certificates ssc
SQL);

        $badges = [
            ['a1300000-0000-4000-8000-000000000001', 'online_learning_60m', 'Chuyên cần trực tuyến', 'Hoàn thành 60 phút học tập chủ động trên TalentHub.', 1],
            ['a1300000-0000-4000-8000-000000000002', 'online_learning_300m', 'Nhịp học bền bỉ', 'Tích lũy 300 phút học tập chủ động trên TalentHub.', 2],
            ['a1300000-0000-4000-8000-000000000003', 'online_learning_1200m', 'Bậc thầy chuyên cần', 'Tích lũy 1.200 phút học tập chủ động trên TalentHub.', 3],
        ];
        $rules = [
            ['b1300000-0000-4000-8000-000000000001', $badges[0][0], 60],
            ['b1300000-0000-4000-8000-000000000002', $badges[1][0], 300],
            ['b1300000-0000-4000-8000-000000000003', $badges[2][0], 1200],
        ];

        $insertBadge = $pdo->prepare(<<<'SQL'
INSERT INTO badges (id, schoolId, code, name, category, description, iconUrl, level, status)
VALUES (:id, NULL, :code, :name, 'online_learning', :description, NULL, :level, 'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), level=VALUES(level), status='active'
SQL);
        $badgeIdByCode = [];
        $findBadgeId = $pdo->prepare('SELECT id FROM badges WHERE code = :code LIMIT 1');
        foreach ($badges as [$id, $code, $name, $description, $level]) {
            $insertBadge->execute(compact('id', 'code', 'name', 'description', 'level'));
            $findBadgeId->execute(['code' => $code]);
            $badgeIdByCode[$code] = (string) $findBadgeId->fetchColumn();
        }

        $insertRule = $pdo->prepare(<<<'SQL'
INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive)
VALUES (:id, :badgeId, 'threshold', :criteria, 1, 1)
ON DUPLICATE KEY UPDATE thresholdCriteria=VALUES(thresholdCriteria), isActive=1
SQL);
        foreach ($rules as $index => [$id, $badgeId, $minutes]) {
            $insertRule->execute([
                'id' => $id,
                'badgeId' => $badgeIdByCode[$badges[$index][1]] ?? $badgeId,
                'criteria' => json_encode([
                    'fact' => 'online_learning_minutes',
                    'operator' => 'gte',
                    'value' => $minutes,
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $context->execute(<<<'SQL'
INSERT IGNORE INTO role_permissions (roleId, permissionId, createdAt)
SELECT r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permissions p ON p.code = 'school_credential.manage_own'
WHERE r.code = 'teacher'
SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: awarded credentials and learning evidence must be retained.
    }
};
