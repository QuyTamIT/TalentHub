<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use InvalidArgumentException;

require_once __DIR__ . '/TalentPassportOptionalSchema.php';

final class PhaseRequirements
{
    /** @var array<int,array{requires_database:bool,config_keys:list<string>,tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>,optional_table_groups:array<string,list<string>>,foreign_keys:array<string,list<array{from:string,table:string,to:string}>>}> */
    private array $requirements;

    public function __construct()
    {
        $this->requirements = [
            0 => $this->definition(false),
            1 => $this->definition(true, [], [
                'roles', 'permissions', 'users', 'role_permissions', 'schools', 'classes', 'student_profiles',
            ], [
                'roles' => ['id', 'code'],
                'permissions' => ['id', 'code'],
                'users' => ['id', 'roleId', 'email', 'passwordHash', 'fullName', 'status'],
                'role_permissions' => ['roleId', 'permissionId'],
                'schools' => ['id', 'name', 'status'],
                'classes' => ['id', 'schoolId', 'name', 'gradeLevel', 'academicYear', 'status'],
                'student_profiles' => ['id', 'userId', 'classId', 'dateOfBirth', 'phone', 'studyStatus'],
            ], [
                'roles' => ['uq_roles_code'],
                'permissions' => ['uq_permissions_code'],
                'users' => ['uq_users_email', 'idx_users_role_status'],
                'role_permissions' => ['PRIMARY'],
                'classes' => ['idx_classes_school_status'],
                'student_profiles' => ['uq_student_profiles_user', 'idx_student_profiles_class_status'],
            ]),
            2 => $this->definition(true, [], [
                'users', 'student_profiles', 'classes', 'schools',
                'skills', 'student_skills',
                'activities', 'activity_registrations', 'checkins', 'experience_logs',
                'talent_tests', 'test_attempts', 'test_results',
                'assessments', 'assessment_scores', 'assessment_criteria',
            ], [
                'users' => ['id', 'fullName', 'email', 'status'],
                'student_profiles' => ['id', 'userId', 'classId', 'studyStatus'],
                'classes' => ['id', 'schoolId', 'name', 'gradeLevel', 'academicYear', 'status'],
                'schools' => ['id', 'name', 'status'],
                'skills' => ['id', 'code', 'name', 'category', 'status'],
                'student_skills' => ['studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt'],
                'activities' => ['id', 'schoolId', 'createdByTeacherId', 'title', 'category', 'startAt', 'endAt', 'status'],
                'activity_registrations' => ['id', 'activityId', 'studentId', 'status', 'registeredAt'],
                'checkins' => ['id', 'registrationId', 'qrSessionId', 'status', 'checkedInAt', 'confirmedAt'],
                'experience_logs' => ['id', 'studentId', 'activityId', 'checkinId', 'hours', 'status', 'confirmedAt'],
                'talent_tests' => ['id', 'code', 'name', 'type', 'status'],
                'test_attempts' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt'],
                'test_results' => ['attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'],
                'assessments' => ['id', 'teacherId', 'studentId', 'activityId', 'overallScore', 'comment', 'status', 'publishedAt', 'version'],
                'assessment_scores' => ['assessmentId', 'criteriaId', 'score'],
                'assessment_criteria' => ['id', 'code', 'name', 'minScore', 'maxScore', 'displayOrder', 'status'],
            ], [
                'student_profiles' => ['uq_student_profiles_user', 'idx_student_profiles_class_status'],
                'classes' => ['idx_classes_school_status'],
                'skills' => ['uq_skills_code', 'idx_skills_status_category'],
                'student_skills' => ['uq_student_skills_student_skill_source', 'idx_student_skills_student_verification'],
                'activities' => ['idx_activities_teacher_status', 'idx_activities_school_start'],
                'activity_registrations' => ['uq_activity_registrations_activity_student', 'idx_activity_registrations_student_status'],
                'checkins' => ['uq_checkins_registration', 'idx_checkins_qr_session'],
                'experience_logs' => ['uq_experience_logs_checkin', 'idx_experience_logs_student_status'],
                'test_attempts' => ['idx_test_attempts_student_status'],
                'test_results' => ['uq_test_results_attempt'],
                'assessments' => ['uq_assessments_teacher_student_activity', 'idx_assessments_student_status'],
                'assessment_scores' => ['uq_assessment_scores_assessment_criteria'],
                'assessment_criteria' => ['uq_assessment_criteria_code', 'idx_assessment_criteria_status_order'],
            ], TalentPassportOptionalSchema::tableGroups()),
            3 => $this->definition(true, [], [
                'users', 'student_profiles', 'student_profile_details', 'student_profile_shares', 'privacy_consents',
                'skills', 'student_skills', 'certificates', 'projects', 'project_members',
            ], [
                'users' => ['id', 'fullName', 'email', 'status'],
                'student_profiles' => ['id', 'userId', 'classId', 'studyStatus'],
                'student_profile_details' => ['studentId', 'location', 'bio', 'avatarUrl', 'headline'],
                'student_profile_shares' => ['id', 'studentId', 'consentId', 'tokenHash', 'sharedFieldsJson', 'expiresAt', 'revokedAt'],
                'privacy_consents' => [
                    'id', 'studentId', 'scope', 'isGranted', 'policyVersion',
                    'grantedAt', 'revokedAt', 'createdAt',
                ],
                'skills' => ['id', 'code', 'name', 'category', 'status'],
                'student_skills' => ['studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus'],
                'certificates' => ['id', 'studentId', 'title', 'issuingOrganization', 'issueDate', 'verificationStatus'],
                'projects' => ['id', 'title', 'category', 'status', 'startAt', 'endAt', 'createdAt'],
                'project_members' => ['id', 'projectId', 'studentId', 'role', 'contribution', 'status'],
            ], [
                'student_profile_shares' => ['uq_student_profile_shares_token_hash', 'idx_student_profile_shares_student_active', 'idx_student_profile_shares_consent'],
                'certificates' => ['idx_certificates_student_status'],
                'project_members' => ['uq_project_members_student'],
            ], [
                'badges' => ['badges', 'badge_rule_definitions', 'student_badges'],
            ], [
                'student_profile_shares' => [[
                    'from' => 'consentId',
                    'table' => 'privacy_consents',
                    'to' => 'id',
                ]],
            ]),
            4 => $this->definition(true, [], ['activities', 'activity_registrations', 'activity_registration_policies'], [
                'activities' => ['id', 'schoolId', 'status', 'startAt', 'endAt', 'capacity'],
                'activity_registrations' => [
                    'id', 'activityId', 'studentId', 'status', 'registeredAt', 'updatedAt',
                    'cancelledAt', 'cancellationReason',
                ],
                'activity_registration_policies' => [
                    'activityId', 'registrationOpensAt', 'registrationClosesAt',
                    'cancellationClosesAt', 'approvalMode',
                ],
            ], [
                'activity_registrations' => ['uq_activity_registrations_activity_student', 'idx_activity_registrations_student_status'],
                'activity_registration_policies' => ['PRIMARY', 'idx_activity_registration_policies_close'],
            ], [], [
                'activity_registration_policies' => [[
                    'from' => 'activityId',
                    'table' => 'activities',
                    'to' => 'id',
                ]],
            ]),
            5 => $this->definition(true, [], ['activity_registrations', 'checkins', 'experience_logs'], [
                'activity_registrations' => ['id', 'studentId'], 'checkins' => ['registrationId'], 'experience_logs' => ['checkinId', 'studentId'],
            ], ['checkins' => ['uq_checkins_registration'], 'experience_logs' => ['uq_experience_logs_checkin']]),
            6 => $this->definition(true, [], ['talent_tests', 'test_questions', 'test_attempts', 'test_results'], [
                'talent_tests' => ['id'], 'test_questions' => ['id', 'testId'], 'test_attempts' => ['id', 'studentId'], 'test_results' => ['attemptId'],
            ]),
            7 => $this->definition(true, [], ['internship_posts', 'internship_applications', 'application_status_history', 'application_profile_snapshots'], [
                'internship_posts' => ['id', 'enterpriseId'], 'internship_applications' => ['id', 'postId', 'studentId'],
                'application_status_history' => ['applicationId'], 'application_profile_snapshots' => ['applicationId'],
            ]),
            8 => $this->definition(true, [], ['notifications', 'learner_notification_preferences'], [
                'notifications' => ['id', 'userId'], 'learner_notification_preferences' => ['studentId', 'notificationType'],
            ]),
            9 => $this->definition(true, [], ['badges', 'badge_rule_definitions', 'student_badges', 'experience_logs'], [
                'badges' => ['id'], 'badge_rule_definitions' => ['id', 'badgeId'], 'student_badges' => ['studentId', 'badgeId'], 'experience_logs' => ['studentId'],
            ], ['student_badges' => ['uq_student_badges_award']]),
            10 => $this->definition(true, [], ['learner_forward_migrations'], ['learner_forward_migrations' => ['version', 'name', 'checksum', 'description', 'appliedAt']]),
        ];
        $this->requirements[11] = $this->mergeDefinitions(array_slice($this->requirements, 1, 10, true));
    }

    public function all(): array
    {
        return $this->requirements;
    }

    public function forPhase(int $phase): array
    {
        if (!isset($this->requirements[$phase])) {
            throw new InvalidArgumentException('Phase must be between 0 and 11.');
        }

        return $this->requirements[$phase];
    }

    /**
     * @param list<string> $configKeys
     * @param list<string> $tables
     * @param array<string,list<string>> $columns
     * @param array<string,list<string>> $indexes
     * @param array<string,list<string>> $optionalTableGroups
     * @param array<string,list<array{from:string,table:string,to:string}>> $foreignKeys
     * @return array{requires_database:bool,config_keys:list<string>,tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>,optional_table_groups:array<string,list<string>>,foreign_keys:array<string,list<array{from:string,table:string,to:string}>>}
     */
    private function definition(
        bool $requiresDatabase,
        array $configKeys = [],
        array $tables = [],
        array $columns = [],
        array $indexes = [],
        array $optionalTableGroups = [],
        array $foreignKeys = [],
    ): array {
        return [
            'requires_database' => $requiresDatabase,
            'config_keys' => $configKeys,
            'tables' => $tables,
            'columns' => $columns,
            'indexes' => $indexes,
            'optional_table_groups' => $optionalTableGroups,
            'foreign_keys' => $foreignKeys,
        ];
    }

    /**
     * @param array<int,array{requires_database:bool,config_keys:list<string>,tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>,optional_table_groups:array<string,list<string>>,foreign_keys:array<string,list<array{from:string,table:string,to:string}>>}> $definitions
     * @return array{requires_database:bool,config_keys:list<string>,tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>,optional_table_groups:array<string,list<string>>,foreign_keys:array<string,list<array{from:string,table:string,to:string}>>}
     */
    private function mergeDefinitions(array $definitions): array
    {
        $merged = $this->definition(true);
        foreach ($definitions as $definition) {
            foreach (['config_keys', 'tables'] as $listKey) {
                foreach ($definition[$listKey] as $value) {
                    if (!in_array($value, $merged[$listKey], true)) {
                        $merged[$listKey][] = $value;
                    }
                }
            }
            foreach (['columns', 'indexes', 'optional_table_groups'] as $mapKey) {
                foreach ($definition[$mapKey] as $table => $values) {
                    $merged[$mapKey][$table] ??= [];
                    foreach ($values as $value) {
                        if (!in_array($value, $merged[$mapKey][$table], true)) {
                            $merged[$mapKey][$table][] = $value;
                        }
                    }
                }
            }
            foreach ($definition['foreign_keys'] as $table => $foreignKeys) {
                $merged['foreign_keys'][$table] ??= [];
                foreach ($foreignKeys as $foreignKey) {
                    if (!in_array($foreignKey, $merged['foreign_keys'][$table], true)) {
                        $merged['foreign_keys'][$table][] = $foreignKey;
                    }
                }
            }
        }

        return $merged;
    }
}
