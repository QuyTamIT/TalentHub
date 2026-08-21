<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use Throwable;

require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/AssessmentScorer.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/ScoringResult.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/LikertScore.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/ScorerRegistry.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/HollandScorer.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/MbtiScorer.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/DiscScorer.php';
require_once dirname(__DIR__, 3) . '/app/learner/assessment/Scoring/MultipleIntelligenceScorer.php';

final class CompleteAiDemoSeeder
{
    private const CATALOG_CODES = [
        'holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high',
        'holland_college', 'mbti_college', 'disc_college', 'multiple_intelligence_college',
    ];

    private const SKILL_DEFS = [
        'python' => ['name' => 'Lập trình Python', 'category' => 'technical'],
        'data_analysis' => ['name' => 'Phân tích dữ liệu', 'category' => 'technical'],
        'communication' => ['name' => 'Giao tiếp', 'category' => 'soft'],
        'teamwork' => ['name' => 'Làm việc nhóm', 'category' => 'soft'],
        'leadership' => ['name' => 'Lãnh đạo', 'category' => 'soft'],
        'creative_design' => ['name' => 'Thiết kế sáng tạo', 'category' => 'creative'],
        'problem_solving' => ['name' => 'Giải quyết vấn đề', 'category' => 'technical'],
        'entrepreneurship' => ['name' => 'Khởi nghiệp', 'category' => 'business'],
        'research' => ['name' => 'Nghiên cứu', 'category' => 'academic'],
        'sports_discipline' => ['name' => 'Rèn luyện thể chất', 'category' => 'sports'],
    ];

    /** @var array<string,list<list<string>>> */
    private const EXPLICIT_NATURAL_KEYS = [
        'schools' => [['name']],
    ];

    private const THPT_SCHOOL_ID = '20000000-0000-4000-8000-000000000001';

    /** @var list<string> */
    private const THPT_TEACHER_PROFILE_IDS = [
        '20000000-0000-4000-8000-000000000050',
        '20000000-0000-4000-8000-000000000051',
        '20000000-0000-4000-8000-000000000052',
        '20000000-0000-4000-8000-000000000053',
        '20000000-0000-4000-8000-000000000054',
        '20000000-0000-4000-8000-000000000055',
    ];

    /** @return list<string> */
    public function touchedTables(): array
    {
        return [
            'schools',
            'users',
            'teacher_profiles',
            'classes',
            'student_profiles',
            'school_members',
            'skills',
            'student_skills',
            'learner_skill_evidence',
            'learner_ai_consent_events',
            'activities',
            'activity_registrations',
            'activity_qr_sessions',
            'checkins',
            'experience_logs',
            'assessment_criteria',
            'assessments',
            'assessment_scores',
            'talent_tests',
            'learner_assessment_versions',
            'learner_assessment_question_versions',
            'test_attempts',
            'learner_assessment_attempt_metadata',
            'learner_assessment_answers',
            'test_results',
        ];
    }

    /** @return array<string,int> */
    public function run(PDO $pdo, string $environment, string $password, DateTimeImmutable $clock): array
    {
        if (!in_array(strtolower($environment), ['local', 'test'], true)) {
            throw new RuntimeException('Complete AI demo seed is forbidden outside local/test.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('TALENTHUB_TEST_PASSWORD must contain at least 12 characters.');
        }
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') {
            throw new RuntimeException('Complete AI demo requires a selected MySQL schema.');
        }
        $this->assertParentsAndCatalog($pdo);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash demo password.');
        }

        $pdo->beginTransaction();
        try {
            $counts = [];
            $this->seedFptOrganization($pdo, $hash, $password, $clock, $counts);
            $this->seedSkills($pdo, $clock, $counts);
            $this->seedStudentSkillsAndEvidence($pdo, $clock, $counts);
            $this->seedConsent($pdo, $clock, $counts);
            $this->seedActivities($pdo, $clock, $counts);
            $this->seedRegistrations($pdo, $clock, $counts);
            $this->seedQrAndExperiences($pdo, $clock, $counts);
            $this->seedTeacherEvaluations($pdo, $clock, $counts);
            $this->seedAssessments($pdo, $clock, $counts);
            $pdo->commit();
            ksort($counts, SORT_STRING);
            return $counts;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function assertParentsAndCatalog(PDO $pdo): void
    {
        $this->assertExactFixtureIds($pdo, 'schools', [self::THPT_SCHOOL_ID], 'THPT school fixture ID');
        $this->assertExactFixtureIds($pdo, 'teacher_profiles', self::THPT_TEACHER_PROFILE_IDS, 'THPT teacher profile fixture IDs');
        $studentIds = array_column(
            array_filter(CompleteAiDemoDataset::learners(), static fn (array $learner): bool => $learner['band'] === 'high'),
            'student_id',
        );
        $this->assertExactFixtureIds($pdo, 'student_profiles', $studentIds, 'THPT student profile fixture IDs');
        // Roles
        foreach (['school', 'teacher', 'student'] as $code) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE code = :code');
            $stmt->execute(['code' => $code]);
            $count = (int) $stmt->fetchColumn();
            if ($count !== 1) {
                throw new RuntimeException('Role must exist exactly once: ' . $code . '.');
            }
        }
        // Catalog: exactly one published per code
        foreach (self::CATALOG_CODES as $code) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM talent_tests t JOIN learner_assessment_versions v ON v.testId=t.id WHERE t.code=:code AND v.status='published' AND t.status='published'");
            $stmt->execute(['code' => $code]);
            $cnt = (int) $stmt->fetchColumn();
            if ($cnt !== 1) {
                throw new RuntimeException('Catalog ' . $code . ' must have exactly one published version, got ' . $cnt . '.');
            }
        }
    }

    /** @param list<string> $expectedIds */
    private function assertExactFixtureIds(PDO $pdo, string $table, array $expectedIds, string $label): void
    {
        sort($expectedIds, SORT_STRING);
        $statement = $pdo->query('SELECT id FROM `' . str_replace('`', '``', $table) . "` WHERE id LIKE '20000000-%' ORDER BY id");
        $actualIds = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if ($actualIds !== $expectedIds) {
            throw new RuntimeException($label . ' do not exactly match the required SchoolDemoSeeder/CompleteAiDemoDataset IDs.');
        }
    }

    /** @param array<string,int> $counts */
    private function seedFptOrganization(PDO $pdo, string $hash, string $password, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        $fptSchoolId = CompleteAiDemoDataset::uuid('fpt', 'school', 'fpt-university');
        $fptAdminUserId = CompleteAiDemoDataset::uuid('fpt', 'user', 'fpt.admin@talenthub.vn');
        $fptAdminMemberId = CompleteAiDemoDataset::uuid('fpt', 'school-member', 'fpt.admin@talenthub.vn');

        // School
        $this->upsertOwned($pdo, 'schools', $fptSchoolId, [
            'id' => $fptSchoolId,
            'name' => 'Đại học FPT',
            'status' => 'active',
            'logoUrl' => '/assets/img/schools/logo-fpt-demo.png',
            'address' => 'Khu Công nghệ cao Hòa Lạc, Hà Nội (dữ liệu demo)',
            'phone' => '024-7300-5588',
            'email' => 'fpt.demo@talenthub.vn',
            'website' => 'https://fpt.demo.talenthub.local',
            'level' => 'Đại học',
            'academicYear' => '2026 - 2027',
        ], $counts, 'schools');

        // Admin user
        $schoolRoleId = $this->roleId($pdo, 'school');
        $this->upsertOwned($pdo, 'users', $fptAdminUserId, [
            'id' => $fptAdminUserId,
            'roleId' => $schoolRoleId,
            'email' => 'fpt.admin@talenthub.vn',
            'passwordHash' => $this->stableOwnedPasswordHash($pdo, $fptAdminUserId, $password, $hash),
            'fullName' => 'Ban Đào tạo Đại học FPT (Demo)',
            'status' => 'active',
        ], $counts, 'users');

        // School member
        $this->upsertOwned($pdo, 'school_members', $fptAdminMemberId, [
            'id' => $fptAdminMemberId,
            'schoolId' => $fptSchoolId,
            'userId' => $fptAdminUserId,
            'memberRole' => 'admin',
        ], $counts, 'school_members');

        // Lecturers
        $teacherRoleId = $this->roleId($pdo, 'teacher');
        $fptTeachers = CompleteAiDemoDataset::fptTeachers();
        $lecturerPhones = ['0929100001', '0929100002', '0929100003', '0929100004'];
        foreach ($fptTeachers as $idx => $t) {
            $userId = CompleteAiDemoDataset::uuid('fpt', 'user', $t['email']);
            $profileId = CompleteAiDemoDataset::uuid('fpt', 'teacher-profile', $t['key']);
            $this->upsertOwned($pdo, 'users', $userId, [
                'id' => $userId,
                'roleId' => $teacherRoleId,
                'email' => $t['email'],
                'passwordHash' => $this->stableOwnedPasswordHash($pdo, $userId, $password, $hash),
                'fullName' => $t['name'],
                'status' => 'active',
            ], $counts, 'users');
            $this->upsertOwned($pdo, 'teacher_profiles', $profileId, [
                'id' => $profileId,
                'userId' => $userId,
                'schoolId' => $fptSchoolId,
                'isSchoolAdmin' => 0,
                'phone' => $lecturerPhones[$idx],
                'specialization' => $t['specialization'],
                'bio' => 'Giảng viên ' . $t['specialization'] . ' (dữ liệu demo)',
            ], $counts, 'teacher_profiles');
        }

        // Classes (Nam 1-4)
        for ($year = 1; $year <= 4; $year++) {
            $classId = CompleteAiDemoDataset::uuid('fpt', 'class', 'year-' . $year);
            $this->upsertOwned($pdo, 'classes', $classId, [
                'id' => $classId,
                'schoolId' => $fptSchoolId,
                'name' => 'Năm ' . $year,
                'gradeLevel' => $year,
                'academicYear' => '2026 - 2027',
                'status' => 'active',
            ], $counts, 'classes');
        }

        // Students
        $studentRoleId = $this->roleId($pdo, 'student');
        $fptStudents = CompleteAiDemoDataset::fptStudents();
        $studentPhones = ['0929000001', '0929000002', '0929000003', '0929000004', '0929000005', '0929000006', '0929000007', '0929000008'];
        foreach ($fptStudents as $idx => $s) {
            $userId = CompleteAiDemoDataset::uuid('fpt', 'user', $s['email']);
            $profileId = CompleteAiDemoDataset::uuid('fpt', 'student-profile', $s['key']);
            $classId = CompleteAiDemoDataset::uuid('fpt', 'class', 'year-' . $s['year']);
            $this->upsertOwned($pdo, 'users', $userId, [
                'id' => $userId,
                'roleId' => $studentRoleId,
                'email' => $s['email'],
                'passwordHash' => $this->stableOwnedPasswordHash($pdo, $userId, $password, $hash),
                'fullName' => $s['name'],
                'status' => 'active',
            ], $counts, 'users');
            $this->upsertOwned($pdo, 'student_profiles', $profileId, [
                'id' => $profileId,
                'userId' => $userId,
                'classId' => $classId,
                'dateOfBirth' => $s['dob'],
                'phone' => $studentPhones[$idx],
                'studyStatus' => 'active',
            ], $counts, 'student_profiles');
        }

        // Refresh counters for FPT school
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE classId IN (SELECT id FROM classes WHERE schoolId = :sid) AND studyStatus = :st');
        $stmt->execute(['sid' => $fptSchoolId, 'st' => 'active']);
        $sc = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :sid');
        $stmt->execute(['sid' => $fptSchoolId]);
        $tc = (int) $stmt->fetchColumn();
        $upd = $pdo->prepare('UPDATE schools SET studentCount = :sc, teacherCount = :tc WHERE id = :id');
        $upd->execute(['sc' => $sc, 'tc' => $tc, 'id' => $fptSchoolId]);
    }

    /** @param array<string,int> $counts */
    private function seedSkills(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        foreach (self::SKILL_DEFS as $code => $def) {
            $id = CompleteAiDemoDataset::uuid('fpt', 'skill', $code);
            $existing = $pdo->prepare('SELECT id, status FROM skills WHERE code = :code ORDER BY id');
            $existing->execute(['code' => $code]);
            $existingSkill = $existing->fetch(PDO::FETCH_NUM);
            if (
                is_array($existingSkill)
                && (string) $existingSkill[0] !== $id
                && (string) $existingSkill[1] === 'active'
            ) {
                // Task 2 explicitly permits active shared skills to be reused by code.
                continue;
            }
            $this->upsertOwned($pdo, 'skills', $id, [
                'id' => $id,
                'code' => $code,
                'name' => $def['name'],
                'category' => $def['category'],
                'status' => 'active',
                'createdAt' => $now,
                'updatedAt' => $now,
            ], $counts, 'skills');
        }
    }

    /** @param array<string,int> $counts */
    private function seedStudentSkillsAndEvidence(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        $observedAt = $clock->modify('-20 days')->format('Y-m-d H:i:s.u');
        $plan = CompleteAiDemoDataset::skillPlan();
        // Resolve skill IDs by code
        $skillIds = [];
        foreach (array_keys(self::SKILL_DEFS) as $code) {
            $stmt = $pdo->prepare('SELECT id FROM skills WHERE code = :code AND status = :st LIMIT 1');
            $stmt->execute(['code' => $code, 'st' => 'active']);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                throw new RuntimeException('Missing skill: ' . $code);
            }
            $skillIds[$code] = (string) $id;
        }
        foreach ($plan as $studentId => $codes) {
            if (!str_starts_with($studentId, '22000000-')) {
                continue;
            }
            foreach ($codes as $code) {
                $skillId = $skillIds[$code];
                $ssId = CompleteAiDemoDataset::uuid('fpt', 'student-skill', $studentId . ':' . $code);
                // Level score deterministic 65-92
                $hash = hexdec(substr(hash('sha256', $studentId . ':' . $code), 0, 4));
                $score = 65 + ($hash % 28); // 65-92
                $verifiedAt = $clock->modify('-18 days')->format('Y-m-d H:i:s.u');
                $this->upsertStudentSkill($pdo, $ssId, $studentId, $skillId, $score, $verifiedAt, $now, $counts);
                $evId = CompleteAiDemoDataset::uuid('fpt', 'skill-evidence', $ssId);
                $this->upsertOwned($pdo, 'learner_skill_evidence', $evId, [
                    'id' => $evId,
                    'studentSkillId' => $ssId,
                    'evidenceType' => 'teacher_observation',
                    'evidenceRef' => 'demo://verified/' . $ssId,
                    'verificationStatus' => 'verified',
                    'observedAt' => $observedAt,
                ], $counts, 'learner_skill_evidence');
            }
        }
        // Also seed THPT learners' skills in thpt namespace (21000000)
        $thptLearners = array_filter(CompleteAiDemoDataset::learners(), static fn (array $l): bool => $l['band'] === 'high');
        foreach ($thptLearners as $learner) {
            $sid = $learner['student_id'];
            $codes = $plan[$sid] ?? [];
            foreach ($codes as $code) {
                $skillId = $skillIds[$code];
                // For THPT, use 21000000 namespace
                $ssId = CompleteAiDemoDataset::uuid('thpt', 'student-skill', $sid . ':' . $code);
                $hash = hexdec(substr(hash('sha256', $sid . ':' . $code), 0, 4));
                $score = 65 + ($hash % 28);
                $verifiedAt = $clock->modify('-18 days')->format('Y-m-d H:i:s.u');
                $this->upsertStudentSkill($pdo, $ssId, $sid, $skillId, $score, $verifiedAt, $now, $counts);
                $evId = CompleteAiDemoDataset::uuid('thpt', 'skill-evidence', $ssId);
                $this->upsertOwned($pdo, 'learner_skill_evidence', $evId, [
                    'id' => $evId,
                    'studentSkillId' => $ssId,
                    'evidenceType' => 'teacher_observation',
                    'evidenceRef' => 'demo://verified/' . $ssId,
                    'verificationStatus' => 'verified',
                    'observedAt' => $observedAt,
                ], $counts, 'learner_skill_evidence');
            }
        }
    }

    private function upsertStudentSkill(PDO $pdo, string $id, string $studentId, string $skillId, int $score, string $verifiedAt, string $now, array &$counts): void
    {
        $this->upsertOwned($pdo, 'student_skills', $id, [
            'id' => $id,
            'studentId' => $studentId,
            'skillId' => $skillId,
            'levelScore' => $score,
            'sourceType' => 'teacher',
            'verificationStatus' => 'verified',
            'verifiedAt' => $verifiedAt,
            'createdAt' => $now,
            'updatedAt' => $now,
        ], $counts, 'student_skills');
    }

    private function stableOwnedPasswordHash(PDO $pdo, string $userId, string $password, string $fallbackHash): string
    {
        $statement = $pdo->prepare('SELECT passwordHash FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        $existingHash = $statement->fetchColumn();
        if (is_string($existingHash) && $existingHash !== '' && password_verify($password, $existingHash)) {
            return $existingHash;
        }

        return $fallbackHash;
    }

    /** @param array<string,int> $counts */
    private function seedConsent(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        $learners = CompleteAiDemoDataset::learners();
        foreach ($learners as $learner) {
            $sid = $learner['student_id'];
            $owner = str_starts_with($sid, '22000000-') ? 'fpt' : 'thpt';
            foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
                $id = CompleteAiDemoDataset::uuid($owner, 'consent', $sid . ':' . $scope);
                $reqId = CompleteAiDemoDataset::uuid($owner, 'consent-req', $sid . ':' . $scope);
                // learner_ai_consent_events has uq on (studentId, scope, occurredAt, requestId) - we use fixed occurredAt per scope offset
                $offset = ['assessment' => 0, 'skills' => 1, 'activity' => 2, 'evaluation' => 3][$scope];
                $occurredAt = $clock->modify('-10 days')->modify('+' . $offset . ' seconds')->format('Y-m-d H:i:s.u');
                $this->assertNaturalKeysOwned($pdo, 'learner_ai_consent_events', $id, [
                    'id' => $id,
                    'studentId' => $sid,
                    'scope' => $scope,
                    'action' => 'granted',
                    'policyVersion' => 'learner-ai-consent-1.0',
                    'occurredAt' => $occurredAt,
                    'requestId' => $reqId,
                ]);
                $chk = $pdo->prepare('SELECT studentId, scope, action, policyVersion, occurredAt, requestId FROM learner_ai_consent_events WHERE id = :id');
                $chk->execute(['id' => $id]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);
                if (is_array($existing)) {
                    if (
                        $existing['studentId'] !== $sid
                        || $existing['scope'] !== $scope
                        || $existing['action'] !== 'granted'
                        || $existing['policyVersion'] !== 'learner-ai-consent-1.0'
                        || $existing['occurredAt'] !== $occurredAt
                        || $existing['requestId'] !== $reqId
                    ) {
                        throw new RuntimeException('Conflicting existing demo consent event: ' . $id);
                    }
                } else {
                    $ins = $pdo->prepare('INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES (:id, :sid, :sc, :act, :pv, :oa, :rid)');
                    $ins->execute(['id' => $id, 'sid' => $sid, 'sc' => $scope, 'act' => 'granted', 'pv' => 'learner-ai-consent-1.0', 'oa' => $occurredAt, 'rid' => $reqId]);
                    $counts['learner_ai_consent_events'] = ($counts['learner_ai_consent_events'] ?? 0) + 1;
                }
            }
        }
    }

    /** @param array<string,int> $counts */
    private function seedActivities(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        // Map THPT teacher profiles to activities (distribute round-robin)
        $thptTeacherIds = $pdo->query("SELECT id FROM teacher_profiles WHERE id LIKE '20000000-%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $fptTeacherIds = $pdo->query("SELECT id FROM teacher_profiles WHERE id LIKE '22000000-%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $thptSchoolId = '20000000-0000-4000-8000-000000000001';
        $fptSchoolId = CompleteAiDemoDataset::uuid('fpt', 'school', 'fpt-university');
        $allActivities = CompleteAiDemoDataset::activities($clock);
        $qrManagedActivityKeys = ['stem-lab', 'robotics', 'finance', 'design', 'ai-club', 'hackathon', 'marketing', 'ux'];
        $nowStr = $clock->format('Y-m-d H:i:s.u');
        foreach ($allActivities as $idx => $act) {
            $owner = $act['owner'];
            $activityId = CompleteAiDemoDataset::uuid($owner, 'activity', $act['key']);
            $teacherPool = $owner === 'thpt' ? $thptTeacherIds : $fptTeacherIds;
            $schoolId = $owner === 'thpt' ? $thptSchoolId : $fptSchoolId;
            $teacherId = in_array($act['key'], $qrManagedActivityKeys, true)
                ? $teacherPool[0]
                : $teacherPool[$idx % count($teacherPool)];
            $startAt = $clock->modify($act['start_offset'] . ' days')->setTime(8, 0, 0)->format('Y-m-d H:i:s.u');
            $endAt = $clock->modify($act['end_offset'] . ' days')->setTime(17, 0, 0)->format('Y-m-d H:i:s.u');
            $this->upsertOwned($pdo, 'activities', $activityId, [
                'id' => $activityId,
                'schoolId' => $schoolId,
                'createdByTeacherId' => $teacherId,
                'title' => $act['title'],
                'category' => $act['category'],
                'startAt' => $startAt,
                'endAt' => $endAt,
                'capacity' => 30,
                'status' => $act['status'],
            ], $counts, 'activities');
        }
    }

    /** @param array<string,int> $counts */
    private function seedRegistrations(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        $plan = CompleteAiDemoDataset::registrationPlan();
        foreach ($plan as $reg) {
            $owner = $reg['owner'];
            $activityId = CompleteAiDemoDataset::uuid($owner, 'activity', $reg['activity_key']);
            $regId = CompleteAiDemoDataset::uuid($owner, 'registration', $reg['key']);
            $this->upsertOwned($pdo, 'activity_registrations', $regId, [
                'id' => $regId,
                'activityId' => $activityId,
                'studentId' => $reg['student_id'],
                'status' => $reg['status'],
            ], $counts, 'activity_registrations');
        }
    }

    /** @param array<string,int> $counts */
    private function seedQrAndExperiences(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        $thptSchoolId = '20000000-0000-4000-8000-000000000001';
        $fptSchoolId = CompleteAiDemoDataset::uuid('fpt', 'school', 'fpt-university');
        // Determine activity ids for QR mapping
        $allActs = CompleteAiDemoDataset::activities($clock);
        $activityIds = [];
        foreach ($allActs as $act) {
            $activityIds[$act['key']] = CompleteAiDemoDataset::uuid($act['owner'], 'activity', $act['key']);
        }
        // QR sessions: 4 per org (active, expired A/B, revoked)
        $thptTeacherIds = $pdo->query("SELECT id FROM teacher_profiles WHERE schoolId = " . $pdo->quote($thptSchoolId) . " ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $fptTeacherIds = $pdo->query("SELECT id FROM teacher_profiles WHERE schoolId = " . $pdo->quote($fptSchoolId) . " ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

        $qrDefs = [
            'thpt' => [
                ['active', 'stem-lab', 'qr-thpt-active', 2, null, 'stem-lab'],
                ['expired', 'robotics', 'qr-thpt-exp-a', -74, null, 'robotics'],
                ['expired', 'finance', 'qr-thpt-exp-b', -60, null, 'finance'],
                ['revoked', 'design', 'qr-thpt-revoked', 1, -1, 'design'],
            ],
            'fpt' => [
                ['active', 'ai-club', 'qr-fpt-active', 2, null, 'ai-club'],
                ['expired', 'hackathon', 'qr-fpt-exp-a', -68, null, 'hackathon'],
                ['expired', 'marketing', 'qr-fpt-exp-b', -50, null, 'marketing'],
                ['revoked', 'ux', 'qr-fpt-revoked', 1, -1, 'ux'],
            ],
        ];

        $qrSessionIds = [];
        foreach (['thpt', 'fpt'] as $owner) {
            $teacherPool = $owner === 'thpt' ? $thptTeacherIds : $fptTeacherIds;
            foreach ($qrDefs[$owner] as [$status, $actKey, $sessionKey, $expireOff, $revokeOff, $linkedActKey]) {
                $activityId = $activityIds[$actKey];
                $qrId = CompleteAiDemoDataset::uuid($owner, 'qr-session', $sessionKey);
                $qrSessionIds[$sessionKey] = $qrId;
                $teacherId = $teacherPool[0];
                $expiresAt = $clock->modify($expireOff . ' hours')->format('Y-m-d H:i:s.u');
                // Keep the demo's active session usable through its ongoing activity.
                if ($status === 'active' && $linkedActKey !== null) {
                    $act = array_values(array_filter($allActs, static fn (array $a): bool => $a['key'] === $linkedActKey))[0];
                    $expiresAt = $clock->modify($act['end_offset'] . ' days')->setTime(17, 0, 0)->format('Y-m-d H:i:s.u');
                } elseif ($status === 'expired' && $linkedActKey !== null) {
                    $act = array_values(array_filter($allActs, static fn (array $a): bool => $a['key'] === $linkedActKey))[0];
                    $expiresAt = $clock->modify($act['end_offset'] . ' days')->setTime(18, 0, 0)->format('Y-m-d H:i:s.u');
                }
                $revokedAt = $revokeOff !== null ? $clock->modify($revokeOff . ' hours')->format('Y-m-d H:i:s.u') : null;
                $tokenHash = hash('sha256', 'talenthub-demo-qr-v1:' . $owner . ':' . $sessionKey);
                $existingUsedScans = $pdo->prepare('SELECT usedScans FROM activity_qr_sessions WHERE id = :id');
                $existingUsedScans->execute(['id' => $qrId]);
                $usedScans = $existingUsedScans->fetchColumn();
                $this->upsertOwned($pdo, 'activity_qr_sessions', $qrId, [
                    'id' => $qrId,
                    'activityId' => $activityId,
                    'createdByTeacherId' => $teacherId,
                    'tokenHash' => $tokenHash,
                    'status' => $status,
                    'expiresAt' => $expiresAt,
                    'maxScans' => 100,
                    'usedScans' => $usedScans === false ? 0 : (int) $usedScans,
                    'revokedAt' => $revokedAt,
                ], $counts, 'activity_qr_sessions');
            }
        }

        // Check-ins: 10 per org, split 5/5 across 2 completed activities' attended registrations
        // Map attended registrations to their checkin qr sessions
        $plan = CompleteAiDemoDataset::registrationPlan();
        $attendedByActivity = [];
        foreach ($plan as $reg) {
            if ($reg['status'] !== 'attended') {
                continue;
            }
            $attendedByActivity[$reg['activity_key']][] = $reg;
        }
        // Define which completed activities use which QR session
        $checkinQrMap = [
            'robotics' => 'qr-thpt-exp-a',
            'finance' => 'qr-thpt-exp-b',
            'hackathon' => 'qr-fpt-exp-a',
            'marketing' => 'qr-fpt-exp-b',
        ];
        // For remaining attended activities (football, volunteer, vovinam, etc.) - those are the 4 completed per org
        // But attended are only on robotics/finance and hackathon/marketing (first 2 completed)
        // Check actual activity keys used for attended
        $attendedActivityKeys = array_keys($attendedByActivity);
        // Assign QR sessions deterministically
        $qrByActivity = [];
        foreach ($attendedActivityKeys as $ak) {
            if (isset($checkinQrMap[$ak])) {
                $qrByActivity[$ak] = $checkinQrMap[$ak];
            } else {
                // Fallback: use first QR of that org
                $owner = null;
                foreach ($allActs as $a) {
                    if ($a['key'] === $ak) {
                        $owner = $a['owner'];
                        break;
                    }
                }
                $qrByActivity[$ak] = $owner === 'thpt' ? 'qr-thpt-exp-a' : 'qr-fpt-exp-a';
            }
        }

        $checkinIdx = 0;
        foreach ($plan as $reg) {
            if ($reg['status'] !== 'attended') {
                continue;
            }
            $owner = $reg['owner'];
            $regId = CompleteAiDemoDataset::uuid($owner, 'registration', $reg['key']);
            $checkinId = CompleteAiDemoDataset::uuid($owner, 'checkin', $reg['key']);
            $qrSessionKey = $qrByActivity[$reg['activity_key']] ?? ($owner === 'thpt' ? 'qr-thpt-exp-a' : 'qr-fpt-exp-a');
            $qrSessionId = $qrSessionIds[$qrSessionKey];
            // checkedInAt: within activity window
            $act = array_values(array_filter($allActs, static fn (array $a): bool => $a['key'] === $reg['activity_key']))[0];
            $startAt = $clock->modify($act['start_offset'] . ' days')->setTime(9, 0, 0);
            // Stagger checkins within activity
            $checkedInAt = $startAt->modify('+' . ($checkinIdx % 60) . ' minutes')->format('Y-m-d H:i:s.u');
            $confirmedAt = $startAt->modify('+' . (60 + ($checkinIdx % 60)) . ' minutes')->format('Y-m-d H:i:s.u');
            $this->upsertOwned($pdo, 'checkins', $checkinId, [
                'id' => $checkinId,
                'registrationId' => $regId,
                'qrSessionId' => $qrSessionId,
                'status' => 'confirmed',
                'checkedInAt' => $checkedInAt,
                'confirmedAt' => $confirmedAt,
            ], $counts, 'checkins');
            $checkinIdx++;
        }
        // Update usedScans for QR sessions that have checkins
        foreach ($qrSessionIds as $key => $qrId) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM checkins WHERE qrSessionId = :qid');
            $stmt->execute(['qid' => $qrId]);
            $used = (int) $stmt->fetchColumn();
            $upd = $pdo->prepare('UPDATE activity_qr_sessions SET usedScans = :used WHERE id = :id');
            $upd->execute(['used' => $used, 'id' => $qrId]);
        }

        // Experiences: one per attended registration (20 total)
        foreach ($plan as $reg) {
            if ($reg['status'] !== 'attended') {
                continue;
            }
            $owner = $reg['owner'];
            $regId = CompleteAiDemoDataset::uuid($owner, 'registration', $reg['key']);
            $checkinId = CompleteAiDemoDataset::uuid($owner, 'checkin', $reg['key']);
            $activityId = CompleteAiDemoDataset::uuid($owner, 'activity', $reg['activity_key']);
            $expId = CompleteAiDemoDataset::uuid($owner, 'experience', $reg['key']);
            $hash = hexdec(substr(hash('sha256', $reg['key']), 0, 4));
            $hours = 2 + ($hash % 5) + (($hash % 2) * 0.5); // 2-6.5
            $act = array_values(array_filter($allActs, static fn (array $a): bool => $a['key'] === $reg['activity_key']))[0];
            $confirmedAt = $clock->modify($act['end_offset'] . ' days')->setTime(18, 0, 0)->modify('+1 day')->format('Y-m-d H:i:s.u');
            $this->upsertOwned($pdo, 'experience_logs', $expId, [
                'id' => $expId,
                'studentId' => $reg['student_id'],
                'activityId' => $activityId,
                'checkinId' => $checkinId,
                'hours' => $hours,
                'status' => 'confirmed',
                'confirmedAt' => $confirmedAt,
            ], $counts, 'experience_logs');
        }
    }

    /** @param array<string,int> $counts */
    private function seedTeacherEvaluations(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $now = $clock->format('Y-m-d H:i:s.u');
        // Ensure 3 criteria exist
        $criteriaDefs = [
            ['teamwork', 'Làm việc nhóm', 'Đánh giá khả năng hợp tác', 0, 10, 1],
            ['initiative', 'Chủ động', 'Đánh giá sự chủ động', 0, 10, 2],
            ['execution', 'Thực thi', 'Đánh giá chất lượng thực thi', 0, 10, 3],
        ];
        $criteriaIds = [];
        foreach ($criteriaDefs as [$code, $name, $desc, $min, $max, $order]) {
            $id = CompleteAiDemoDataset::uuid('fpt', 'criteria', $code);
            $this->upsertOwned($pdo, 'assessment_criteria', $id, [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'description' => $desc,
                'minScore' => $min,
                'maxScore' => $max,
                'displayOrder' => $order,
                'status' => 'active',
                'createdAt' => $now,
                'updatedAt' => $now,
            ], $counts, 'assessment_criteria');
            $criteriaIds[$code] = $id;
        }

        // Map teacher per activity for evaluation ownership
        $allActs = CompleteAiDemoDataset::activities($clock);
        $activityTeacher = [];
        foreach ($allActs as $act) {
            $actId = CompleteAiDemoDataset::uuid($act['owner'], 'activity', $act['key']);
            $stmt = $pdo->prepare('SELECT createdByTeacherId FROM activities WHERE id = :id');
            $stmt->execute(['id' => $actId]);
            $tid = $stmt->fetchColumn();
            if ($tid !== false) {
                $activityTeacher[$actId] = (string) $tid;
            }
        }

        $plan = CompleteAiDemoDataset::registrationPlan();
        $publishedAt = $clock->modify('-5 days')->format('Y-m-d H:i:s.u');
        $comments = [
            'Em thể hiện tốt tinh thần hợp tác và chủ động trong hoạt động.',
            'Kết quả thực thi đạt yêu cầu, cần phát huy thêm khả năng sáng tạo.',
            'Tham gia tích cực, hoàn thành nhiệm vụ đúng hạn và có trách nhiệm.',
            'Có tiến bộ rõ rệt về kỹ năng làm việc nhóm và giải quyết vấn đề.',
        ];
        foreach ($plan as $reg) {
            if ($reg['status'] !== 'attended') {
                continue;
            }
            $owner = $reg['owner'];
            $activityId = CompleteAiDemoDataset::uuid($owner, 'activity', $reg['activity_key']);
            $teacherId = $activityTeacher[$activityId] ?? null;
            if ($teacherId === null) {
                continue;
            }
            $assId = CompleteAiDemoDataset::uuid($owner, 'assessment', $reg['key']);
            $hash = hexdec(substr(hash('sha256', $reg['key'] . ':overall'), 0, 4));
            $overall = 7.2 + (($hash % 23) / 10); // 7.2-9.4
            $overall = round($overall, 2);
            $comment = $comments[$hash % count($comments)];
            $this->upsertOwned($pdo, 'assessments', $assId, [
                'id' => $assId,
                'teacherId' => $teacherId,
                'studentId' => $reg['student_id'],
                'activityId' => $activityId,
                'overallScore' => $overall,
                'comment' => $comment,
                'status' => 'published',
                'publishedAt' => $publishedAt,
            ], $counts, 'assessments');

            // 3 criterion scores per assessment
            foreach (['teamwork', 'initiative', 'execution'] as $critCode) {
                $scoreId = CompleteAiDemoDataset::uuid($owner, 'assessment-score', $reg['key'] . ':' . $critCode);
                $critId = $criteriaIds[$critCode];
                $sh = hexdec(substr(hash('sha256', $reg['key'] . ':' . $critCode), 0, 4));
                $score = 7.0 + (($sh % 25) / 10); // 7.0-9.4
                $score = round($score, 2);
                $this->upsertOwned($pdo, 'assessment_scores', $scoreId, [
                    'id' => $scoreId,
                    'assessmentId' => $assId,
                    'criteriaId' => $critId,
                    'score' => $score,
                ], $counts, 'assessment_scores');
            }
        }
    }

    private function seedAssessments(PDO $pdo, DateTimeImmutable $clock, array &$counts): void
    {
        $plan = CompleteAiDemoDataset::assessmentPlan();
        $learners = CompleteAiDemoDataset::learners();
        $learnerIndex = [];
        foreach ($learners as $idx => $l) {
            $learnerIndex[$l['student_id']] = $idx;
        }
        $registry = $this->scorers();
        // Cache catalog data
        $catalogCache = [];
        foreach (self::CATALOG_CODES as $code) {
            $row = $pdo->query("SELECT t.id AS testId, v.id AS versionId, v.version, v.scoringVersion, v.schemaHash FROM talent_tests t JOIN learner_assessment_versions v ON v.testId=t.id WHERE t.code=" . $pdo->quote($code) . " AND v.status='published' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new RuntimeException('Missing catalog: ' . $code);
            }
            $qRows = $pdo->query("SELECT qv.questionId AS qid, qv.position, qv.dimensionCode, qv.required FROM learner_assessment_question_versions qv WHERE qv.versionId=" . $pdo->quote($row['versionId']) . " ORDER BY qv.position")->fetchAll(PDO::FETCH_ASSOC);
            $catalogCache[$code] = ['meta' => $row, 'questions' => $qRows];
        }

        foreach ($plan as $studentId => $codes) {
            $learnerIdx = $learnerIndex[$studentId] ?? 0;
            $owner = str_starts_with($studentId, '22000000-') ? 'fpt' : 'thpt';
            foreach ($codes as $code) {
                $cache = $catalogCache[$code];
                $testId = $cache['meta']['testId'];
                $versionId = $cache['meta']['versionId'];
                $version = $cache['meta']['version'];
                $scoringVersion = $cache['meta']['scoringVersion'];
                $schemaHash = $cache['meta']['schemaHash'];
                $questions = $cache['questions'];

                // Deterministic answers
                $answers = $this->deterministicAnswers($code, $questions, $learnerIdx, $studentId);
                // Score
                $scorerQuestions = array_map(static fn (array $q): array => [
                    'question_id' => $q['qid'],
                    'dimension_code' => $q['dimensionCode'],
                    'required' => (int) $q['required'],
                ], $questions);
                $scored = $registry->forVersion($scoringVersion)->score($scorerQuestions, $answers)->toArray();
                $submittedAt = $clock->modify('-' . (10 + $learnerIdx) . ' days')->format('Y-m-d H:i:s.u');
                $startedAt = (new DateTimeImmutable($submittedAt, new DateTimeZone('UTC')))->modify('-30 minutes')->format('Y-m-d H:i:s.u');
                $attemptId = CompleteAiDemoDataset::uuid($owner, 'attempt', $studentId . ':' . $code);
                $metadataId = CompleteAiDemoDataset::uuid($owner, 'attempt-meta', $studentId . ':' . $code);
                $resultId = CompleteAiDemoDataset::uuid($owner, 'result', $studentId . ':' . $code);
                // Input hash
                ksort($answers, SORT_STRING);
                $inputHash = hash('sha256', json_encode([
                    'assessment_version' => $version,
                    'scoring_version' => $scoringVersion,
                    'schema_hash' => $schemaHash,
                    'answers' => $answers,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                // Upsert test_attempts
                $this->upsertOwned($pdo, 'test_attempts', $attemptId, [
                    'id' => $attemptId,
                    'testId' => $testId,
                    'studentId' => $studentId,
                    'status' => 'submitted',
                    'startedAt' => $startedAt,
                    'submittedAt' => $submittedAt,
                ], $counts, 'test_attempts');
                // learner_assessment_attempt_metadata
                $this->upsertOwned($pdo, 'learner_assessment_attempt_metadata', $metadataId, [
                    'id' => $metadataId,
                    'attemptId' => $attemptId,
                    'versionId' => $versionId,
                    'status' => 'submitted',
                    'submittedAt' => $submittedAt,
                    'inputHash' => $inputHash,
                ], $counts, 'learner_assessment_attempt_metadata');
                // answers
                foreach ($answers as $qid => $val) {
                    $ansId = CompleteAiDemoDataset::uuid($owner, 'answer', $attemptId . ':' . $qid);
                    $this->upsertAnswer($pdo, $ansId, $attemptId, $qid, $val, $submittedAt, $counts);
                }
                // result
                $this->upsertOwned($pdo, 'test_results', $resultId, [
                    'id' => $resultId,
                    'attemptId' => $attemptId,
                    'resultCode' => $scored['result_code'],
                    'summary' => $scored['summary'],
                    'dimensionScoresJson' => json_encode($scored['dimension_scores'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'scoringVersion' => $scoringVersion,
                ], $counts, 'test_results');
            }
        }
    }

    /** @param list<array{qid:string,position:int,dimensionCode:string,required:int}> $questions @return array<string,int> */
    private function deterministicAnswers(string $code, array $questions, int $learnerIdx, string $studentId): array
    {
        // Determine primary dimension for hero vs others
        $heroes = CompleteAiDemoDataset::heroStudentIds();
        $isHero = $studentId === $heroes['high'] || $studentId === $heroes['college'];
        // Group dimensions by type
        $answers = [];
        if (str_starts_with($code, 'holland_')) {
            $dims = ['R', 'I', 'A', 'S', 'E', 'C'];
            $primary = $isHero ? 'A' : $dims[$learnerIdx % 6];
            foreach ($questions as $q) {
                $dimCode = $q['dimensionCode'];
                $m = [];
                preg_match('/\A([RIASEC])(?::([+-]))?\z/i', $dimCode, $m);
                $dim = strtoupper($m[1] ?? 'R');
                $reversed = ($m[2] ?? '+') === '-';
                $isPrimary = $dim === $primary;
                $base = $isPrimary ? 5 : 2;
                $val = $reversed ? (6 - $base) : $base;
                // Add small variance by position
                if (!$isPrimary && ($q['position'] % 3 === 0)) {
                    $val = $reversed ? (6 - 3) : 3;
                }
                $answers[$q['qid']] = $val;
            }
        } elseif (str_starts_with($code, 'mbti_')) {
            // MBTI: prefer I, N, T, J for hero
            $prefs = $isHero ? ['I', 'N', 'T', 'J'] : [['E', 'I'], ['S', 'N'], ['T', 'F'], ['J', 'P']][$learnerIdx % 4] ?? ['E'];
            // Actually pick one pole per axis deterministically
            $axisPrefs = [];
            if ($isHero) {
                $axisPrefs = ['EI' => 'I', 'SN' => 'N', 'TF' => 'T', 'JP' => 'J'];
            } else {
                $options = [
                    ['EI' => 'E', 'SN' => 'S', 'TF' => 'T', 'JP' => 'J'],
                    ['EI' => 'I', 'SN' => 'N', 'TF' => 'F', 'JP' => 'P'],
                    ['EI' => 'E', 'SN' => 'N', 'TF' => 'T', 'JP' => 'P'],
                    ['EI' => 'I', 'SN' => 'S', 'TF' => 'F', 'JP' => 'J'],
                ];
                $axisPrefs = $options[$learnerIdx % 4];
            }
            foreach ($questions as $q) {
                $code2 = $q['dimensionCode'];
                preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/i', $code2, $m);
                $axis = strtoupper($m[1] ?? 'EI');
                $pole = strtoupper($m[2] ?? 'E');
                $preferred = $axisPrefs[$axis] ?? $pole;
                $val = ($pole === $preferred) ? 5 : 2;
                $answers[$q['qid']] = $val;
            }
        } elseif (str_starts_with($code, 'disc_')) {
            $dims = ['D', 'I', 'S', 'C'];
            $primary = $isHero ? 'C' : $dims[$learnerIdx % 4];
            foreach ($questions as $q) {
                preg_match('/\A([DISC])(?::([+-]))?\z/i', $q['dimensionCode'], $m);
                $dim = strtoupper($m[1] ?? 'D');
                $reversed = ($m[2] ?? '+') === '-';
                $base = ($dim === $primary) ? 5 : 2;
                $val = $reversed ? (6 - $base) : $base;
                $answers[$q['qid']] = $val;
            }
        } else { // multiple_intelligence
            $dims = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
            $primary = $isHero ? 'LOGI' : $dims[$learnerIdx % 8];
            foreach ($questions as $q) {
                preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/i', $q['dimensionCode'], $m);
                $dim = strtoupper($m[1] ?? 'LING');
                $reversed = ($m[2] ?? '+') === '-';
                $base = ($dim === $primary) ? 5 : 2;
                $val = $reversed ? (6 - $base) : $base;
                $answers[$q['qid']] = $val;
            }
        }
        return $answers;
    }

    private function upsertAnswer(PDO $pdo, string $id, string $attemptId, string $questionId, int $value, string $answeredAt, array &$counts): void
    {
        $this->upsertOwned($pdo, 'learner_assessment_answers', $id, [
            'id' => $id,
            'attemptId' => $attemptId,
            'questionId' => $questionId,
            'answerJson' => json_encode($value, JSON_THROW_ON_ERROR),
            'answeredAt' => $answeredAt,
        ], $counts, 'learner_assessment_answers');
    }

    private function upsertOwned(PDO $pdo, string $table, string $id, array $values, array &$counts, string $countKey): void
    {
        $this->assertNaturalKeysOwned($pdo, $table, $id, $values);
        $chk = $pdo->prepare('SELECT id FROM `' . str_replace('`', '``', $table) . '` WHERE id = :id');
        $chk->execute(['id' => $id]);
        $existingId = $chk->fetchColumn();
        if ($existingId !== false
            && !str_starts_with((string) $existingId, '21000000-')
            && !str_starts_with((string) $existingId, '22000000-')
        ) {
            throw new RuntimeException('Refusing to overwrite a non-demo row in ' . $table . '.');
        }
        $cols = array_keys($values);
        $phs = array_map(static fn (string $c): string => ':' . $c, $cols);
        $updates = [];
        foreach ($cols as $col) {
            if ($col !== 'id') {
                $escaped = str_replace('`', '``', $col);
                $updates[] = '`' . $escaped . '` = VALUES(`' . $escaped . '`)';
            }
        }
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (`' . implode('`, `', array_map(static fn (string $c): string => str_replace('`', '``', $c), $cols)) . '`) VALUES (' . implode(', ', $phs) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $pdo->prepare($sql)->execute($values);
        if ($existingId === false) {
            $counts[$countKey] = ($counts[$countKey] ?? 0) + 1;
        }
    }

    /** @param array<string,mixed> $values */
    private function assertNaturalKeysOwned(PDO $pdo, string $table, string $expectedId, array $values): void
    {
        $escapedTable = '`' . str_replace('`', '``', $table) . '`';
        $indexes = $pdo->prepare(
            'SELECT index_name, column_name, seq_in_index FROM information_schema.statistics '
            . 'WHERE table_schema=DATABASE() AND table_name=:table AND non_unique=0 AND index_name<>\'PRIMARY\' '
            . 'ORDER BY index_name, seq_in_index',
        );
        $indexes->execute(['table' => $table]);
        $naturalKeys = self::EXPLICIT_NATURAL_KEYS[$table] ?? [];
        foreach ($indexes->fetchAll(PDO::FETCH_NUM) as $index) {
            $name = (string) $index[0];
            $naturalKeys[$name][] = (string) $index[1];
        }

        foreach ($naturalKeys as $keyName => $columns) {
            if (in_array('id', $columns, true)) {
                continue;
            }
            $params = [];
            $conditions = [];
            foreach ($columns as $position => $column) {
                if (!array_key_exists($column, $values) || $values[$column] === null) {
                    continue 2;
                }
                $parameter = 'natural_' . $position;
                $conditions[] = '`' . str_replace('`', '``', $column) . '` <=> :' . $parameter;
                $params[$parameter] = $values[$column];
            }
            $statement = $pdo->prepare('SELECT id FROM ' . $escapedTable . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id');
            $statement->execute($params);
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $existingId) {
                if ((string) $existingId !== $expectedId) {
                    throw new RuntimeException('Foreign natural-key collision in ' . $table . ' via ' . (is_string($keyName) ? $keyName : 'explicit key') . '.');
                }
            }
        }
    }

    private function roleId(PDO $pdo, string $code): string
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) !== 1) {
            throw new RuntimeException('Role must exist exactly once: ' . $code . '.');
        }
        return $ids[0];
    }

    private function scorers(): ScorerRegistry
    {
        return new ScorerRegistry([
            'holland-riasec-1.0' => new HollandScorer(),
            'mbti-education-1.0' => new MbtiScorer(),
            'disc-education-1.0' => new DiscScorer(),
            'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
        ]);
    }
}
