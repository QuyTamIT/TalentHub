<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use InvalidArgumentException;
use TalentHub\Learner\Data\Database\SchemaInspector;

final class TalentPassportOptionalSchema
{
    /**
     * @return array<string,array{tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>}>
     */
    public static function definitions(): array
    {
        return [
            'certificates' => [
                'tables' => ['certificates'],
                'columns' => [
                    'certificates' => [
                        'id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'expiryDate',
                        'credentialId', 'credentialUrl', 'verificationStatus', 'verifiedBy', 'verifiedAt',
                        'createdAt', 'updatedAt',
                    ],
                ],
                'indexes' => [
                    'certificates' => ['idx_certificates_student_status'],
                ],
            ],
            'projects' => [
                'tables' => ['projects', 'project_members'],
                'columns' => [
                    'projects' => [
                        'id', 'title', 'category', 'description', 'mentorTeacherId', 'schoolId', 'status',
                        'startAt', 'endAt', 'createdAt', 'updatedAt',
                    ],
                    'project_members' => [
                        'id', 'projectId', 'studentId', 'role', 'contribution', 'status', 'joinedAt',
                    ],
                ],
                'indexes' => [
                    'project_members' => ['uq_project_members_student'],
                ],
            ],
            'badges' => [
                'tables' => ['badges', 'student_badges'],
                'columns' => [
                    'badges' => [
                        'id', 'code', 'name', 'category', 'description', 'iconUrl', 'level', 'status', 'createdAt',
                    ],
                    'student_badges' => [
                        'id', 'studentId', 'badgeId', 'awardedAt', 'awardedBy', 'awardContext',
                    ],
                ],
                'indexes' => [
                    'badges' => ['uq_badges_code'],
                    'student_badges' => ['uq_student_badges_award'],
                ],
            ],
        ];
    }

    /** @return array<string,list<string>> */
    public static function tableGroups(): array
    {
        $groups = [];
        foreach (self::definitions() as $name => $definition) {
            $groups[$name] = $definition['tables'];
        }

        return $groups;
    }

    public static function status(SchemaInspector $inspector, string $capability): string
    {
        $definition = self::definitions()[$capability] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException("Unknown Talent Passport capability: {$capability}");
        }

        $presentCount = 0;
        foreach ($definition['tables'] as $table) {
            if ($inspector->hasTable($table)) {
                $presentCount++;
            }
        }

        if ($presentCount === 0) {
            return 'absent';
        }
        if ($presentCount !== count($definition['tables'])) {
            return 'partial';
        }

        foreach ($definition['columns'] as $table => $columns) {
            foreach ($columns as $column) {
                if (!$inspector->hasColumn($table, $column)) {
                    return 'incompatible';
                }
            }
        }
        foreach ($definition['indexes'] as $table => $indexes) {
            foreach ($indexes as $index) {
                if (!$inspector->hasIndex($table, $index)) {
                    return 'incompatible';
                }
            }
        }

        return 'available';
    }
}
