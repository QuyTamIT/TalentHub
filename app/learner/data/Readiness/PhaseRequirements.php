<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Readiness;

use InvalidArgumentException;

final class PhaseRequirements
{
    /** @var array<int,array{requires_database:bool,config_keys:list<string>,tables:list<string>,columns:array<string,list<string>>,indexes:array<string,list<string>>}> */
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
            2 => $this->definition(true, [], ['student_skills', 'skills', 'certificates', 'project_members', 'projects', 'experience_logs', 'student_badges', 'badges'], [
                'student_skills' => ['studentId', 'skillId'], 'skills' => ['id'], 'certificates' => ['id', 'studentId'],
                'project_members' => ['projectId', 'studentId'], 'projects' => ['id'], 'experience_logs' => ['studentId'], 'student_badges' => ['studentId', 'badgeId'], 'badges' => ['id'],
            ]),
            3 => $this->definition(true, [], ['student_profiles', 'student_skills', 'certificates', 'project_members', 'projects'], [
                'student_profiles' => ['id'], 'student_skills' => ['studentId'], 'certificates' => ['studentId'], 'project_members' => ['studentId'], 'projects' => ['id'],
            ]),
            4 => $this->definition(true, [], ['activities', 'activity_registrations'], [
                'activities' => ['id', 'schoolId', 'status'], 'activity_registrations' => ['id', 'activityId', 'studentId', 'status'],
            ], ['activity_registrations' => ['uq_activity_registrations_activity_student']]),
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
            9 => $this->definition(true, [], ['badges', 'student_badges', 'experience_logs'], [
                'badges' => ['id'], 'student_badges' => ['studentId', 'badgeId'], 'experience_logs' => ['studentId'],
            ], ['student_badges' => ['uq_student_badges_student_badge']]),
            10 => $this->definition(true, [], ['learner_forward_migrations'], ['learner_forward_migrations' => ['version', 'name', 'checksum', 'description', 'appliedAt']]),
            11 => $this->definition(true, [], ['learner_forward_migrations'], ['learner_forward_migrations' => ['version', 'name', 'checksum', 'description', 'appliedAt']]),
        ];
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

    private function definition(bool $requiresDatabase, array $configKeys = [], array $tables = [], array $columns = [], array $indexes = []): array
    {
        return [
            'requires_database' => $requiresDatabase,
            'config_keys' => $configKeys,
            'tables' => $tables,
            'columns' => $columns,
            'indexes' => $indexes,
        ];
    }
}
