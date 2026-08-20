<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\System;

use PDO;
use RuntimeException;
use Throwable;

final class RolePermissionSeeder
{
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /** @var array<string, array{name: string, description: string}> */
    private const ROLES = [
        'student' => ['name' => 'Student', 'description' => 'Học sinh hoặc sinh viên'],
        'teacher' => ['name' => 'Teacher', 'description' => 'Giáo viên hoặc huấn luyện viên'],
        'school' => ['name' => 'School', 'description' => 'Nhân sự quản trị nhà trường'],
        'enterprise' => ['name' => 'Enterprise', 'description' => 'Nhân sự doanh nghiệp'],
    ];

    private const COMMON_PERMISSIONS = [
        'account.read_own',
        'account.update_own',
        'account.password_change_own',
        'session.logout_own',
        'notification.read_own',
        'notification.mark_read_own',
    ];

    private const ROLE_PERMISSIONS = [
        'student' => [
            'student_profile.read_own', 'student_profile.update_own', 'student_profile.share_own', 'student_dashboard.read_own',
            'privacy_consent.read_own', 'privacy_consent.manage_own',
            'student_skill.read_own', 'student_skill.manage_own', 'talent_test.read_catalog',
            'test_attempt.create_own', 'test_attempt.read_own', 'test_attempt.submit_own',
            'assessment.read_own', 'ai_recommendation.read_own', 'activity.read_available',
            'activity_registration.create_own', 'activity_registration.read_own',
            'activity_registration.cancel_own', 'checkin.create_own', 'experience_log.read_own',
            'badge.read_own', 'certificate.read_own', 'partner.read_public', 'project.read_available',
            'internship_post.read_available', 'internship_application.create_own',
            'internship_application.read_own', 'internship_application.withdraw_own',
            'contact_request.read_own', 'contact_request.respond_own',
        ],
        'teacher' => [
            'teacher_profile.read_own',
            'teacher_profile.update_own',
            'teacher_dashboard.read_own',
            'activity.read_managed',
            'activity.create_managed',
            'activity.update_managed',
            'activity_registration.read_managed',
            'qr_session.create_managed',
            'qr_session.read_managed',
            'qr_session.revoke_managed',
            'checkin.read_managed',
            'assessment.read_managed',
            'assessment.update_managed',
        ],
        'school' => [
            'school_profile.read_own', 'school_profile.update_own', 'school_dashboard.read_own',
            'school_analytics.read_own', 'class.read_own_school', 'class.create_own_school',
            'class.update_own_school', 'class.archive_own_school', 'class.export_own_school',
            'student_profile.read_own_school', 'student_profile.verify_own_school',
            'student_profile.create_own_school', 'student_profile.update_own_school',
            'teacher_profile.read_own_school', 'teacher_profile.invite_own_school',
            'teacher_profile.update_role_own_school', 'teacher_profile.deactivate_own_school',
            'activity.read_own_school',
            'activity.create_own_school', 'activity.update_own_school', 'activity.archive_own_school',
            'activity_registration.read_own_school', 'report.create_own_school',
            'report.read_own_school', 'report.download_own_school', 'project.read_own_school',
            'project.create_own_school', 'project.update_own_school',
            'sponsorship.read_own_school_project', 'notification.send_own_school',
        ],
        'enterprise' => [
            'business_profile.read_own', 'business_profile.update_own', 'business_dashboard.read_own',
            'talent.search_consented', 'talent.read_consented', 'contact_request.create_own_business',
            'contact_request.read_own_business', 'contact_request.cancel_own_business',
            'internship_post.read_own_business', 'internship_post.create_own_business',
            'internship_post.update_own_business', 'internship_post.publish_own_business',
            'internship_post.close_own_business', 'internship_application.read_own_business',
            'internship_application.review_own_business',
            'internship_application.read_cv_own_business', 'project.read_sponsorable',
            'sponsorship.create_own_business', 'sponsorship.read_own_business',
            'sponsorship.update_own_business', 'sponsorship.cancel_own_business',
        ],
    ];

    public function run(PDO $pdo): void
    {
        $this->assertRequiredTables($pdo);
        $pdo->beginTransaction();

        try {
            $this->migrateLegacyBusinessRole($pdo);
            $this->upsertRoles($pdo);
            $permissions = $this->allPermissions();
            $this->upsertPermissions($pdo, $permissions);
            $this->synchronizeMappings($pdo);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function runWithinTransaction(PDO $pdo): void
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('runWithinTransaction requires an active transaction.');
        }
        $this->assertRequiredTables($pdo);
        $this->migrateLegacyBusinessRole($pdo);
        $this->upsertRoles($pdo);
        $this->upsertPermissions($pdo, $this->allPermissions());
        $this->synchronizeMappings($pdo);
    }

    /** @return array<string, int> */
    public function expectedCounts(): array
    {
        $mappingCount = 0;
        foreach (self::ROLE_PERMISSIONS as $permissions) {
            $mappingCount += count(self::COMMON_PERMISSIONS) + count($permissions);
        }

        return ['roles' => count(self::ROLES), 'permissions' => count($this->allPermissions()), 'mappings' => $mappingCount];
    }

    /** @return array<string,list<string>> */
    public function expectedPermissionsByRole(): array
    {
        $result=[];
        foreach(array_keys(self::ROLES) as $roleCode){
            $codes=array_values(array_unique(array_merge(self::COMMON_PERMISSIONS,self::ROLE_PERMISSIONS[$roleCode])));
            sort($codes);$result[$roleCode]=$codes;
        }
        ksort($result);
        return $result;
    }

    private function assertRequiredTables(PDO $pdo): void
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => $table]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException("System seed requires migrated table: {$table}");
            }
        }
    }

    private function upsertRoles(PDO $pdo): void
    {
        $select = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
        $insert = $pdo->prepare(
            'INSERT INTO roles (id, code, name, description, isSystem) VALUES (:id, :code, :name, :description, 1)'
        );
        $update = $pdo->prepare(
            'UPDATE roles SET name = :name, description = :description, isSystem = 1 WHERE id = :id AND code = :code'
        );

        foreach (self::ROLES as $code => $role) {
            $id = self::roleId($code);
            $select->execute(['code' => $code]);
            $existingId = $select->fetchColumn();
            $params = ['id' => $id, 'code' => $code, 'name' => $role['name'], 'description' => $role['description']];
            if ($existingId === false) {
                $insert->execute($params);
            } else {
                $params['id'] = (string) $existingId;
                $update->execute($params);
            }
        }
    }

    /** @param list<string> $permissions */
    private function upsertPermissions(PDO $pdo, array $permissions): void
    {
        $select = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
        $insert = $pdo->prepare('INSERT INTO permissions (id, code, description) VALUES (:id, :code, :description)');
        $update = $pdo->prepare('UPDATE permissions SET description = :description WHERE id = :id AND code = :code');

        foreach ($permissions as $code) {
            $id = self::stableId("permission:{$code}");
            $select->execute(['code' => $code]);
            $existingId = $select->fetchColumn();
            $params = ['id' => $id, 'code' => $code, 'description' => self::description($code)];
            if ($existingId === false) {
                $insert->execute($params);
            } else {
                $params['id'] = (string) $existingId;
                $update->execute($params);
            }
        }
    }

    private function synchronizeMappings(PDO $pdo): void
    {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO role_permissions (roleId, permissionId) VALUES (:roleId, :permissionId)'
        );
        foreach (array_keys(self::ROLES) as $roleCode) {
            $roleId = $this->pdoSelectId($pdo, 'roles', $roleCode);
            $codes = array_values(array_unique(array_merge(self::COMMON_PERMISSIONS, self::ROLE_PERMISSIONS[$roleCode])));
            foreach ($codes as $code) {
                $permissionId = $this->pdoSelectId($pdo, 'permissions', $code);
                $insert->execute(['roleId' => $roleId, 'permissionId' => $permissionId]);
            }

            $allowedIds = array_map(fn (string $code): string => $this->pdoSelectId($pdo, 'permissions', $code), $codes);
            $deleteForRole = $pdo->prepare('DELETE FROM role_permissions WHERE roleId = ? AND permissionId NOT IN ('
                . implode(', ', array_fill(0, count($allowedIds), '?')) . ')');
            $deleteForRole->execute([$roleId, ...$allowedIds]);
        }
    }

    /** @return list<string> */
    private function allPermissions(): array
    {
        $all = self::COMMON_PERMISSIONS;
        foreach (self::ROLE_PERMISSIONS as $permissions) {
            $all = array_merge($all, $permissions);
        }
        $all = array_values(array_unique($all));
        sort($all);

        return $all;
    }

    private static function description(string $code): string
    {
        return 'TalentHub permission: ' . str_replace(['.', '_'], [' / ', ' '], $code);
    }

    private static function stableId(string $name): string
    {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) {
            throw new RuntimeException('Invalid UUID namespace.');
        }
        $hash = sha1($namespace . $name);
        return sprintf('%s-%s-5%s-%s%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8), substr($hash, 17, 3), substr($hash, 20, 12));
    }

    private static function roleId(string $code): string
    {
        return self::stableId('role:' . ($code === 'enterprise' ? 'business' : $code));
    }

    private function migrateLegacyBusinessRole(PDO $pdo): void
    {
        $enterprise = $pdo->query("SELECT id FROM roles WHERE code = 'enterprise' LIMIT 1")->fetchColumn();
        $business = $pdo->query("SELECT id FROM roles WHERE code = 'business' LIMIT 1")->fetchColumn();
        if ($enterprise !== false && $business !== false && $enterprise !== $business) {
            throw new RuntimeException('Both legacy business and canonical enterprise roles exist. Resolve duplicate role data first.');
        }
        if ($enterprise === false && $business !== false) {
            $statement = $pdo->prepare("UPDATE roles SET code = 'enterprise', name = 'Enterprise' WHERE id = ? AND code = 'business'");
            $statement->execute([$business]);
        }
    }

    private function pdoSelectId(PDO $pdo, string $table, string $code): string
    {
        $statement = $pdo->prepare("SELECT id FROM {$table} WHERE code = ?");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException("Missing {$table} row for code {$code}.");
        }
        return $id;
    }
}
