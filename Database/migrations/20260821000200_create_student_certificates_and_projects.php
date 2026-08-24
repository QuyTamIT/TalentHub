<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create student certificates, projects, and project members tables';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('users');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('teacher_profiles');
        $context->assertTableExists('schools');

        $timeZoneStatement = $context->pdo()->query('SELECT @@session.time_zone');
        $timeZone = $timeZoneStatement === false ? false : $timeZoneStatement->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new \RuntimeException('Certificates and projects migration requires MySQL session time zone +00:00.');
        }

        if ($context->tableExists('certificates')) {
            $this->assertCertificatesContract($context);
        }

        if ($context->tableExists('projects')) {
            $this->assertProjectsContract($context);
        }

        if ($context->tableExists('project_members')) {
            $this->assertProjectMembersContract($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS certificates (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  issuingOrganization VARCHAR(255) NOT NULL,
  issueDate DATE NOT NULL,
  expiryDate DATE NULL,
  credentialId VARCHAR(255) NULL,
  credentialUrl VARCHAR(500) NULL,
  verificationStatus VARCHAR(32) NOT NULL DEFAULT 'unverified',
  verifiedBy CHAR(36) NULL,
  verifiedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_certificates_student_status (studentId, verificationStatus),
  CONSTRAINT fk_certificates_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_certificates_verified_by FOREIGN KEY (verifiedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_certificates_verification_status CHECK (verificationStatus IN ('unverified','verified','rejected')),
  CONSTRAINT chk_certificates_expiry CHECK (expiryDate IS NULL OR expiryDate >= issueDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS projects (
  id CHAR(36) NOT NULL,
  schoolId CHAR(36) NULL,
  mentorTeacherId CHAR(36) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  projectUrl VARCHAR(500) NULL,
  startAt DATE NULL,
  endAt DATE NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_projects_school_status (schoolId, status),
  KEY idx_projects_mentor (mentorTeacherId),
  CONSTRAINT fk_projects_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_projects_mentor FOREIGN KEY (mentorTeacherId) REFERENCES teacher_profiles(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_projects_status CHECK (status IN ('draft','in_progress','completed','archived')),
  CONSTRAINT chk_projects_dates CHECK (endAt IS NULL OR startAt IS NULL OR endAt >= startAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS project_members (
  id CHAR(36) NOT NULL,
  projectId CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  role VARCHAR(100) NOT NULL DEFAULT 'member',
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  joinedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  leftAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_project_members_student (projectId, studentId),
  KEY idx_project_members_student_status (studentId, status),
  CONSTRAINT fk_project_members_project FOREIGN KEY (projectId) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_members_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_project_members_status CHECK (status IN ('active','left','removed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $isSqlite = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$isSqlite) {
            $pdo->exec(<<<'SQL'
                INSERT IGNORE INTO permissions (id, code, description, createdAt, updatedAt)
                VALUES ('0d6a9e22-679a-5d5c-a658-15b93e078ec8', 'certificate.manage_own', 'TalentHub permission: certificate / manage own', CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6));
            SQL
            );
            $pdo->exec(<<<'SQL'
                INSERT IGNORE INTO role_permissions (roleId, permissionId, createdAt)
                SELECT r.id, p.id, CURRENT_TIMESTAMP(6)
                FROM roles r, permissions p
                WHERE r.code = 'student' AND p.code = 'certificate.manage_own';
            SQL
            );
        }
    }

    public function down(MigrationContext $context): void
    {
        // Certificates and projects migration is intentionally forward-only to preserve user evidence and projects.
    }

    private function assertCertificatesContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->query(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'certificates'
        SQL
        );
        $columns = $statement ? array_fill_keys($statement->fetchAll(\PDO::FETCH_COLUMN), true) : [];
        foreach (['id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'expiryDate', 'credentialId', 'credentialUrl', 'verificationStatus', 'verifiedBy', 'verifiedAt', 'createdAt', 'updatedAt'] as $col) {
            if (!isset($columns[$col])) {
                throw new \RuntimeException("certificates table missing column: {$col}");
            }
        }
    }

    private function assertProjectsContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->query(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'projects'
        SQL
        );
        $columns = $statement ? array_fill_keys($statement->fetchAll(\PDO::FETCH_COLUMN), true) : [];
        foreach (['id', 'schoolId', 'mentorTeacherId', 'title', 'description', 'projectUrl', 'startAt', 'endAt', 'status', 'createdAt', 'updatedAt'] as $col) {
            if (!isset($columns[$col])) {
                throw new \RuntimeException("projects table missing column: {$col}");
            }
        }
    }

    private function assertProjectMembersContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->query(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'project_members'
        SQL
        );
        $columns = $statement ? array_fill_keys($statement->fetchAll(\PDO::FETCH_COLUMN), true) : [];
        foreach (['id', 'projectId', 'studentId', 'role', 'status', 'joinedAt', 'leftAt', 'createdAt', 'updatedAt'] as $col) {
            if (!isset($columns[$col])) {
                throw new \RuntimeException("project_members table missing column: {$col}");
            }
        }
    }
};
