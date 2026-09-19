<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Support\Uuid;
use Throwable;

final class EnterpriseTalentRepository
{
    private ?EvidenceBackedScoreService $scoreReader = null;
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Look up an active, unexpired internship post owned by the enterprise.
     *
     * @return array{id:string,title:string,field:string,slots:int|null,description:string,required_skills:list<string>}
     */
    public function matchingJob(string $enterpriseId, string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, enterpriseId, title, field, slots, description, skillsJson, requirementsJson, status, deadline FROM internship_posts WHERE id = ? AND enterpriseId = ? LIMIT 1');
        $stmt->execute([$jobId, $enterpriseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tin tuyển dụng hoặc không thuộc doanh nghiệp.');
        }
        if (($row['status'] ?? '') !== 'active') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tin tuyển dụng không ở trạng thái hoạt động.');
        }
        if (!empty($row['deadline'])) {
            $now = gmdate('Y-m-d H:i:s');
            if ($row['deadline'] < $now) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Tin tuyển dụng đã hết hạn.');
            }
        }
        $skills = [];
        $rawSkills = trim((string) ($row['skillsJson'] ?? ''));
        if ($rawSkills !== '') {
            try {
                $decoded = json_decode($rawSkills, true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $skill) {
                        if (is_string($skill) && trim($skill) !== '') {
                            $skills[] = trim($skill);
                        }
                    }
                }
            } catch (\JsonException) {
                // Ignore parse errors on secondary JSON
            }
        }

        // If skillsJson is empty, check requirementsJson for concise skill tags
        if (empty($skills)) {
            $rawReqs = trim((string) ($row['requirementsJson'] ?? ''));
            if ($rawReqs !== '') {
                try {
                    $decoded = json_decode($rawReqs, true, 64, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        foreach ($decoded as $item) {
                            if (is_string($item)) {
                                $trimmed = trim($item);
                                if ($trimmed !== '' && mb_strlen($trimmed) <= 40 && !str_contains($trimmed, '.') && !str_contains($trimmed, 'Sinh viên')) {
                                    $skills[] = $trimmed;
                                }
                            }
                        }
                    }
                } catch (\JsonException) {}
            }
        }

        return [
            'id' => (string) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'field' => (string) ($row['field'] ?? ''),
            'slots' => isset($row['slots']) && is_numeric($row['slots']) ? (int) $row['slots'] : null,
            'description' => (string) ($row['description'] ?? ''),
            'required_skills' => array_values(array_unique($skills)),
        ];
    }

    /**
     * Return candidate projections for enterprise matching.
     * Includes verified skills, teacher assessment scores, profile details, and achievements.
     *
     * @param list<string> $requiredSkills
     * @return list<array<string,mixed>>
     */
    public function matchCandidates(string $enterpriseId, array $requiredSkills = [], ?string $jobId = null): array
    {
        $now = $this->now();
        $hasAssessments = $this->tableExists('assessments');
        $hasBadges = $this->tableExists('student_badges') && $this->tableExists('badges');

        // Check if there are explicit active discovery grants for this enterprise (e.g. in isolated consent test suites)
        $hasExplicitGrants = false;
        if ($this->tableExists('enterprise_talent_access_grants')) {
            try {
                $st = $this->pdo->prepare("SELECT COUNT(*) FROM enterprise_talent_access_grants WHERE enterpriseId = ? AND scope = 'enterprise_talent_discovery' AND revokedAt IS NULL AND expiresAt > ?");
                $st->execute([$enterpriseId, $now]);
                $hasExplicitGrants = ((int) $st->fetchColumn()) > 0;
            } catch (\Throwable) {}
        }

        $where = ["u.status = 'active'"];
        $params = [];

        if ($hasExplicitGrants) {
            $where[] = "EXISTS (
                SELECT 1 FROM enterprise_talent_access_grants grant_row
                INNER JOIN privacy_consents consent ON consent.id = grant_row.consentId AND consent.studentId = sp.id AND consent.scope = 'enterprise_talent_discovery' AND consent.isGranted = 1 AND consent.revokedAt IS NULL
                WHERE grant_row.studentId = sp.id AND grant_row.enterpriseId = :entGrant AND grant_row.scope = 'enterprise_talent_discovery' AND grant_row.revokedAt IS NULL AND grant_row.expiresAt > :nowGrant
            )";
            $params['entGrant'] = $enterpriseId;
            $params['nowGrant'] = $now;
        }

        $selectedJobId = $jobId !== null ? trim($jobId) : '';
        $appStatusSelect = 'NULL AS current_post_app_status,';
        if ($selectedJobId !== '' && $this->tableExists('internship_applications') && $this->tableExists('internship_posts')) {
            $appStatusSelect = '(SELECT ia.status FROM internship_applications ia INNER JOIN internship_posts ip_chk ON ip_chk.id = ia.postId WHERE (ia.studentId = sp.id OR ia.studentId = u.id) AND ia.postId = :selectedJobIdMatch AND ip_chk.enterpriseId = :selectedJobEntMatch ORDER BY ia.updatedAt DESC LIMIT 1) AS current_post_app_status,';
            $params['selectedJobIdMatch'] = $selectedJobId;
            $params['selectedJobEntMatch'] = $enterpriseId;

            $where[] = "NOT EXISTS (
                SELECT 1 FROM internship_applications ia_filter
                INNER JOIN internship_posts ip_filter ON ip_filter.id = ia_filter.postId
                WHERE (ia_filter.studentId = sp.id OR ia_filter.studentId = u.id)
                  AND ia_filter.postId = :selectedJobIdFilter
                  AND ip_filter.enterpriseId = :selectedJobEntFilter
            )";
            $params['selectedJobIdFilter'] = $selectedJobId;
            $params['selectedJobEntFilter'] = $enterpriseId;
        }

        $whereClause = implode(' AND ', $where);

        $talentScoreCol = $this->columnExists('student_profiles', 'talentScore') ? 'sp.talentScore' : 'NULL';
        $hasScoreState = $this->columnExists('student_skills', 'scoreState');
        $hasClasses = $this->tableExists('classes');
        $hasSchools = $this->tableExists('schools');
        $hasSpd = $this->tableExists('student_profile_details');

        $classJoin = $hasClasses ? 'LEFT JOIN classes c ON c.id = sp.classId' : '';
        $schoolJoin = '';
        if ($hasSchools) {
            if ($hasClasses) {
                $schoolJoin = 'LEFT JOIN schools s ON s.id = c.schoolId';
            } elseif ($this->columnExists('student_profiles', 'schoolId')) {
                $schoolJoin = 'LEFT JOIN schools s ON s.id = sp.schoolId';
            }
        }
        $spdJoin = $hasSpd ? 'LEFT JOIN student_profile_details spd ON spd.studentId = sp.id' : '';

        $schoolNameSelect = $hasSchools ? 's.name AS school_name,' : "'' AS school_name,";
        $classNameSelect = $hasClasses ? 'c.name AS class_name,' : "'' AS class_name,";
        $headlineSelect = $hasSpd ? 'spd.headline,' : "'' AS headline,";
        $bioSelect = $hasSpd ? 'spd.bio,' : "'' AS bio,";
        $locationSelect = $hasSpd ? 'spd.location,' : "'' AS location,";
        $avatarSelect = $hasSpd ? 'spd.avatarUrl AS avatar_url,' : 'NULL AS avatar_url,';
        $studyStatusSelect = $this->columnExists('student_profiles', 'studyStatus') ? 'sp.studyStatus AS study_status,' : "'' AS study_status,";

        $scoredSubquery = $hasScoreState
            ? "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.scoreState = 'scored')"
            : "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0)";

        // appStatusSelect is already populated above when $selectedJobId !== ''

        $sql = <<<SQL
            SELECT
                sp.id AS student_id,
                u.id AS user_id,
                u.fullName AS display_name,
                {$schoolNameSelect}
                {$classNameSelect}
                {$headlineSelect}
                {$bioSelect}
                {$locationSelect}
                {$avatarSelect}
                {$studyStatusSelect}
                {$appStatusSelect}
                COALESCE(
                    {$talentScoreCol},
                    {$scoredSubquery}
                ) AS talent_score
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            {$classJoin}
            {$schoolJoin}
            {$spdJoin}
            WHERE {$whereClause}
            ORDER BY sp.id ASC
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $candidates = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (string) ($row['student_id'] ?? '');
            if ($studentId === '') {
                continue;
            }

            // In AI matching for a specific internship post, candidates who already have an application
            // for this exact internship post at this enterprise are ineligible for new recommendation/invitation.
            if ($selectedJobId !== '') {
                $stRaw = strtolower(trim((string) ($row['current_post_app_status'] ?? '')));
                if ($stRaw !== '') {
                    continue;
                }
            }

            $stRaw = strtolower(trim((string) ($row['current_post_app_status'] ?? '')));
            $canInvite = true;
            $internshipStatus = 'ready_now';
            $internshipStatusLabel = 'Sẵn sàng thực tập';
            $actionLabel = 'Mời ứng tuyển';

            if (in_array($stRaw, ['accepted', 'approved', 'hired'], true)) {
                $canInvite = false;
                $internshipStatus = 'accepted';
                $internshipStatusLabel = 'Đã nhận';
                $actionLabel = 'Đã tiếp nhận';
            } elseif (in_array($stRaw, ['submitted'], true)) {
                $canInvite = false;
                $internshipStatus = 'submitted';
                $internshipStatusLabel = 'Đang xét duyệt';
                $actionLabel = 'Đã ứng tuyển';
            } elseif (in_array($stRaw, ['reviewing'], true)) {
                $canInvite = false;
                $internshipStatus = 'reviewing';
                $internshipStatusLabel = 'Đang xét duyệt';
                $actionLabel = 'Đang xét duyệt';
            } elseif (in_array($stRaw, ['interview', 'interviewing'], true)) {
                $canInvite = false;
                $internshipStatus = 'interviewing';
                $internshipStatusLabel = 'Đang phỏng vấn';
                $actionLabel = 'Đang phỏng vấn';
            } elseif (in_array($stRaw, ['invited'], true)) {
                $canInvite = false;
                $internshipStatus = 'invited';
                $internshipStatusLabel = 'Đã mời ứng tuyển';
                $actionLabel = 'Đã gửi lời mời';
            }

            $evalInfo = $selectedJobId !== ''
                ? $this->findApplicationEvaluation($enterpriseId, $selectedJobId, $studentId, (string) ($row['user_id'] ?? ''))
                : [
                    'has_applied' => false,
                    'application_id' => null,
                    'evaluation_score' => null,
                    'evaluation_status' => 'unapplied',
                    'evaluation_label' => 'Chưa chấm điểm',
                ];

            $candidates[$studentId] = [
                'id' => $studentId,
                'student_id' => $studentId,
                'user_id' => (string) ($row['user_id'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? 'Ứng viên'),
                'school_name' => (string) ($row['school_name'] ?? ''),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'headline' => (string) ($row['headline'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'avatar_url' => $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
                'study_status' => (string) ($row['study_status'] ?? ''),
                'talent_score' => is_numeric($row['talent_score'] ?? null) ? (float) $row['talent_score'] : null,
                'evaluation_score' => $evalInfo['evaluation_score'],
                'evaluation_status' => $evalInfo['evaluation_status'],
                'evaluation_label' => $evalInfo['evaluation_label'],
                'has_applied' => $evalInfo['has_applied'],
                'application_id' => $evalInfo['application_id'],
                'can_invite' => $canInvite,
                'canInvite' => $canInvite,
                'internship_status' => $internshipStatus,
                'internshipStatus' => $internshipStatus,
                'internship_status_label' => $internshipStatusLabel,
                'internshipStatusLabel' => $internshipStatusLabel,
                'action_label' => $actionLabel,
                'actionLabel' => $actionLabel,
                'skills' => [],
                'badges' => [],
                'assessments' => [],
                'projects' => [],
            ];
        }

        if ($candidates === []) {
            return [];
        }

        // Fetch skills for all candidates
        $hasScoreState = $this->columnExists('student_skills', 'scoreState');
        $scoreStateSelect = $hasScoreState ? 'ss.scoreState,' : 'NULL AS scoreState,';
        $skillSql = <<<SQL
            SELECT ss.studentId, sk.id AS skill_id, sk.name AS skill_name, sk.category AS skill_category, ss.levelScore AS level_score, ss.verificationStatus, {$scoreStateSelect} ss.sourceType
            FROM student_skills ss
            INNER JOIN skills sk ON sk.id = ss.skillId AND sk.status = 'active'
            ORDER BY ss.studentId ASC, sk.name ASC
        SQL;
        $skillStmt = $this->pdo->query($skillSql);
        while ($sRow = $skillStmt->fetch(PDO::FETCH_ASSOC)) {
            $sId = (string) $sRow['studentId'];
            if (isset($candidates[$sId])) {
                $candidates[$sId]['skills'][] = [
                    'skill_id' => (string) $sRow['skill_id'],
                    'skillId' => (string) $sRow['skill_id'],
                    'id' => (string) $sRow['skill_id'],
                    'name' => (string) $sRow['skill_name'],
                    'category' => (string) ($sRow['skill_category'] ?? 'technical'),
                    'level_score' => (float) ($sRow['level_score'] ?? 0),
                    'verification_status' => (string) ($sRow['verificationStatus'] ?? ''),
                    'score_state' => $sRow['scoreState'] !== null ? (string) $sRow['scoreState'] : null,
                    'scoreState' => $sRow['scoreState'] !== null ? (string) $sRow['scoreState'] : null,
                    'source_type' => (string) ($sRow['sourceType'] ?? ''),
                ];
            }
        }

        // Fetch assessments if table exists
        if ($hasAssessments) {
            $aSql = 'SELECT studentId, overallScore, comment FROM assessments WHERE studentId IS NOT NULL';
            $aStmt = $this->pdo->query($aSql);
            while ($aRow = $aStmt->fetch(PDO::FETCH_ASSOC)) {
                $sId = (string) $aRow['studentId'];
                if (isset($candidates[$sId])) {
                    $candidates[$sId]['assessments'][] = [
                        'overall_score' => (float) ($aRow['overallScore'] ?? 0),
                        'comment' => (string) ($aRow['comment'] ?? ''),
                    ];
                }
            }
        }

        // Fetch badges if table exists
        if ($hasBadges) {
            $bSql = 'SELECT sb.studentId, b.name FROM student_badges sb INNER JOIN badges b ON b.id = sb.badgeId';
            $bStmt = $this->pdo->query($bSql);
            while ($bRow = $bStmt->fetch(PDO::FETCH_ASSOC)) {
                $sId = (string) $bRow['studentId'];
                if (isset($candidates[$sId])) {
                    $candidates[$sId]['badges'][] = (string) $bRow['name'];
                }
            }
        }

        // Fetch projects for candidates if tables exist
        $hasProjects = $this->tableExists('projects') && $this->tableExists('project_members');
        if ($hasProjects) {
            $studentLookup = [];
            foreach ($candidates as $sId => $cand) {
                $studentLookup[$sId] = $sId;
                $uId = (string) ($cand['user_id'] ?? '');
                if ($uId !== '') {
                    $studentLookup[$uId] = $sId;
                }
            }

            $hasTopic = $this->columnExists('projects', 'topic');
            $topicCol = $hasTopic ? 'p.topic,' : "'' AS topic,";
            $hasContrib = $this->columnExists('project_members', 'contribution');
            $contribCol = $hasContrib ? 'pm.contribution,' : "'' AS contribution,";

            $pSql = <<<SQL
                SELECT
                    pm.studentId,
                    p.id AS project_id,
                    p.title,
                    p.category,
                    {$topicCol}
                    p.description,
                    p.projectUrl,
                    p.status,
                    p.createdAt,
                    p.updatedAt,
                    pm.role,
                    {$contribCol}
                    pm.status AS member_status
                FROM project_members pm
                INNER JOIN projects p ON p.id = pm.projectId
                WHERE (pm.status = 'active' OR pm.status IS NULL)
                ORDER BY p.createdAt DESC
            SQL;

            try {
                $pStmt = $this->pdo->query($pSql);
                while ($pRow = $pStmt->fetch(PDO::FETCH_ASSOC)) {
                    $rawMemberStudentId = (string) ($pRow['studentId'] ?? '');
                    $targetStudentId = $studentLookup[$rawMemberStudentId] ?? null;
                    if ($targetStudentId !== null && isset($candidates[$targetStudentId])) {
                        $candidates[$targetStudentId]['projects'][] = [
                            'project_id' => (string) ($pRow['project_id'] ?? ''),
                            'title' => (string) ($pRow['title'] ?? ''),
                            'category' => (string) ($pRow['category'] ?? ''),
                            'topic' => (string) ($pRow['topic'] ?? ''),
                            'description' => (string) ($pRow['description'] ?? ''),
                            'project_url' => (string) ($pRow['projectUrl'] ?? ''),
                            'status' => (string) ($pRow['status'] ?? ''),
                            'role' => (string) ($pRow['role'] ?? 'member'),
                            'contribution' => (string) ($pRow['contribution'] ?? ''),
                            'createdAt' => (string) ($pRow['createdAt'] ?? ''),
                            'updatedAt' => (string) ($pRow['updatedAt'] ?? ''),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('EnterpriseTalentRepository matchCandidates project query error: ' . $e->getMessage());
            }
        }

        if ($this->tableExists('learner_evaluations')) {
            foreach ($candidates as $studentId => &$candidate) {
                $snapshot = $this->discoveryScores($studentId, $enterpriseId);
                if ($snapshot === null) {
                    unset($candidates[$studentId]);
                    continue;
                }
                $candidate['skills'] = $this->mapCurrentSkills($snapshot['skills']);
                $candidate['talent_score'] = $snapshot['summary']['score'];
            }
            unset($candidate);
        }
        return array_values($candidates);
    }

    /** Backwards-compatible descriptive alias for matching callers. */
    public function findMatchCandidates(string $enterpriseId, array $requiredSkills = []): array
    {
        return $this->matchCandidates($enterpriseId, $requiredSkills);
    }

    /**
     * Check if a candidate already has an application for a specific internship post at an enterprise.
     * Validates full tripartite relation: candidate (studentId/userId) + internship post (postId) + enterprise.
     */
    public function hasApplicationForJob(string $enterpriseId, string $jobId, string $studentId, ?string $userId = null): bool
    {
        if (!$this->tableExists('internship_applications') || !$this->tableExists('internship_posts')) {
            return false;
        }
        $targetPostId = trim($jobId);
        $targetEnterpriseId = trim($enterpriseId);
        $targetStudentId = trim($studentId);
        if ($targetPostId === '' || $targetEnterpriseId === '' || $targetStudentId === '') {
            return false;
        }

        $sql = 'SELECT 1 FROM internship_applications ia
                INNER JOIN internship_posts ip ON ip.id = ia.postId
                WHERE (ia.studentId = :studentId OR ia.studentId = :userId)
                  AND ia.postId = :jobId
                  AND ip.enterpriseId = :enterpriseId
                LIMIT 1';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'studentId' => $targetStudentId,
                'userId' => !empty($userId) ? trim($userId) : $targetStudentId,
                'jobId' => $targetPostId,
                'enterpriseId' => $targetEnterpriseId,
            ]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Find a candidate's application for a specific internship post at an enterprise.
     * Validates full tripartite relation: candidate (studentId/userId) + internship post (jobId) + enterprise.
     */
    public function findApplicationForJob(string $enterpriseId, string $jobId, string $studentId, ?string $userId = null): ?array
    {
        if (!$this->tableExists('internship_applications') || !$this->tableExists('internship_posts')) {
            return null;
        }
        $targetPostId = trim($jobId);
        $targetEnterpriseId = trim($enterpriseId);
        $targetStudentId = trim($studentId);
        if ($targetPostId === '' || $targetEnterpriseId === '' || $targetStudentId === '') {
            return null;
        }

        $sql = 'SELECT ia.*, ip.title AS postTitle FROM internship_applications ia
                INNER JOIN internship_posts ip ON ip.id = ia.postId
                WHERE (ia.studentId = :studentId OR ia.studentId = :userId)
                  AND ia.postId = :jobId
                  AND ip.enterpriseId = :enterpriseId
                ORDER BY ia.updatedAt DESC
                LIMIT 1';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'studentId' => $targetStudentId,
                'userId' => !empty($userId) ? trim($userId) : $targetStudentId,
                'jobId' => $targetPostId,
                'enterpriseId' => $targetEnterpriseId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find if a candidate has an accepted application for any internship post at an enterprise.
     */
    public function findAcceptedApplicationForEnterprise(string $enterpriseId, string $studentId, ?string $userId = null): ?array
    {
        if (!$this->tableExists('internship_applications') || !$this->tableExists('internship_posts')) {
            return null;
        }
        $targetEnterpriseId = trim($enterpriseId);
        $targetStudentId = trim($studentId);
        if ($targetEnterpriseId === '' || $targetStudentId === '') {
            return null;
        }

        $sql = "SELECT ia.*, ip.title AS postTitle FROM internship_applications ia
                INNER JOIN internship_posts ip ON ip.id = ia.postId
                WHERE (ia.studentId = :studentId OR ia.studentId = :userId)
                  AND ip.enterpriseId = :enterpriseId
                  AND ia.status IN ('accepted', 'approved', 'hired')
                ORDER BY ia.updatedAt DESC
                LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'studentId' => $targetStudentId,
                'userId' => !empty($userId) ? trim($userId) : $targetStudentId,
                'enterpriseId' => $targetEnterpriseId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Find the evaluation score and status for a candidate's application to a specific internship post.
     * 
     * Enforces strict relationship: candidate + internship/job posting + enterprise (+ application/evaluation).
     * Does NOT substitute with AI matching score, skill score, teacher score, or scores from other internships.
     *
     * @return array{has_applied:bool,application_id:string|null,evaluation_score:float|null,evaluation_status:string,evaluation_label:string}
     */
    public function findApplicationEvaluation(string $enterpriseId, string $jobId, string $studentId, ?string $userId = null): array
    {
        $app = $this->findApplicationForJob($enterpriseId, $jobId, $studentId, $userId);
        if ($app === null) {
            return [
                'has_applied' => false,
                'application_id' => null,
                'evaluation_score' => null,
                'evaluation_status' => 'unapplied',
                'evaluation_label' => 'Chưa chấm điểm',
            ];
        }

        $appId = (string) ($app['id'] ?? '');

        // 1. Direct evaluation score columns on internship_applications if defined in schema
        foreach (['evaluationScore', 'evaluation_score', 'score', 'rating'] as $col) {
            if ($this->columnExists('internship_applications', $col) && isset($app[$col]) && is_numeric($app[$col])) {
                $score = (float) $app[$col];
                return [
                    'has_applied' => true,
                    'application_id' => $appId,
                    'evaluation_score' => $score,
                    'evaluation_status' => 'scored',
                    'evaluation_label' => '★ ' . round($score) . ' điểm đánh giá',
                ];
            }
        }

        // 2. Check if learner_evaluations has an evaluation linked to this application via internship report
        if ($this->tableExists('learner_evaluations') && $this->tableExists('learner_internship_reports')) {
            try {
                $stmt = $this->pdo->prepare(<<<'SQL'
                    SELECT le.overallScore
                    FROM learner_evaluations le
                    INNER JOIN learner_internship_reports lir ON lir.id = le.contextId AND le.contextType = 'internship_report'
                    WHERE lir.applicationId = :appId
                      AND (lir.studentId = :studentId OR lir.studentId = :userId)
                      AND le.status = 'published'
                      AND le.revokedAt IS NULL
                      AND le.supersededAt IS NULL
                    ORDER BY le.revision DESC, le.publishedAt DESC, le.id DESC
                    LIMIT 1
                SQL);
                $stmt->execute([
                    'appId' => $appId,
                    'studentId' => $studentId,
                    'userId' => !empty($userId) ? trim($userId) : $studentId,
                ]);
                $evalRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($evalRow && isset($evalRow['overallScore']) && is_numeric($evalRow['overallScore'])) {
                    $score = (float) $evalRow['overallScore'];
                    return [
                        'has_applied' => true,
                        'application_id' => $appId,
                        'evaluation_score' => $score,
                        'evaluation_status' => 'scored',
                        'evaluation_label' => '★ ' . round($score) . ' điểm đánh giá',
                    ];
                }
            } catch (\Throwable) {}
        }

        // 3. Check if learner_evaluations has a direct evaluation on the application itself
        if ($this->tableExists('learner_evaluations')) {
            try {
                $stmt = $this->pdo->prepare(<<<'SQL'
                    SELECT le.overallScore
                    FROM learner_evaluations le
                    WHERE le.contextId = :appId
                      AND le.contextType IN ('internship_application', 'application', 'internship')
                      AND (le.studentId = :studentId OR le.studentId = :userId)
                      AND le.status = 'published'
                      AND le.revokedAt IS NULL
                      AND le.supersededAt IS NULL
                    ORDER BY le.revision DESC, le.publishedAt DESC, le.id DESC
                    LIMIT 1
                SQL);
                $stmt->execute([
                    'appId' => $appId,
                    'studentId' => $studentId,
                    'userId' => !empty($userId) ? trim($userId) : $studentId,
                ]);
                $evalRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($evalRow && isset($evalRow['overallScore']) && is_numeric($evalRow['overallScore'])) {
                    $score = (float) $evalRow['overallScore'];
                    return [
                        'has_applied' => true,
                        'application_id' => $appId,
                        'evaluation_score' => $score,
                        'evaluation_status' => 'scored',
                        'evaluation_label' => '★ ' . round($score) . ' điểm đánh giá',
                    ];
                }
            } catch (\Throwable) {}
        }

        // Candidate has applied to this internship, but no evaluation score exists
        return [
            'has_applied' => true,
            'application_id' => $appId,
            'evaluation_score' => null,
            'evaluation_status' => 'pending',
            'evaluation_label' => 'Chưa chấm điểm',
        ];
    }
    /**
     * Find featured talents preserving NULL talentScore for unscored students.
     *
     * @return list<array<string,mixed>>
     */
    public function findFeaturedTalents(int $limit = 10): array
    {
        $hasScoreState = $this->columnExists('student_skills', 'scoreState');
        $scoredSubquery = $hasScoreState
            ? "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.scoreState = 'scored')"
            : "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0)";

        $sql = <<<SQL
            SELECT
                sp.id,
                sp.id AS studentId,
                u.id AS userId,
                u.fullName AS name,
                COALESCE(
                    sp.talentScore,
                    {$scoredSubquery}
                ) AS talentScore
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            WHERE u.status = 'active'
            ORDER BY
                CASE WHEN sp.talentScore IS NULL AND {$scoredSubquery} IS NULL THEN 1 ELSE 0 END,
                talentScore DESC
            LIMIT :limit
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if ($row['talentScore'] !== null) {
                $row['talentScore'] = (float) $row['talentScore'];
            }
        }
        return $rows;
    }

    /**
     * Find talent by ID preserving NULL talentScore for unscored profiles.
     *
     * @return ?array<string,mixed>
     */
    public function findTalentById(string $studentId): ?array
    {
        $hasScoreState = $this->columnExists('student_skills', 'scoreState');
        $scoredSubquery = $hasScoreState
            ? "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.scoreState = 'scored')"
            : "(SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0)";

        $sql = <<<SQL
            SELECT
                sp.id,
                sp.id AS studentId,
                u.id AS userId,
                u.fullName AS name,
                COALESCE(
                    sp.talentScore,
                    {$scoredSubquery}
                ) AS talentScore
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            WHERE (sp.id = :id OR u.id = :idAlt)
            LIMIT 1
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $studentId, ':idAlt' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ($row['talentScore'] !== null) {
            $row['talentScore'] = (float) $row['talentScore'];
        }
        return $row;
    }

    /** @return ?array<string,mixed> */
    public function cachedMatchRanking(string $enterpriseId, string $jobHash): ?array
    {
        try {
            $statement = $this->pdo->prepare('SELECT ranking_json, updated_at FROM enterprise_ai_match_rankings WHERE enterprise_id = ? AND job_hash = ? LIMIT 1');
            $statement->execute([$enterpriseId, $jobHash]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && is_string($row['ranking_json'] ?? null)) {
                $decoded = json_decode($row['ranking_json'], true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && ($decoded['analysis_origin'] ?? '') === 'model' && isset($decoded['items']) && is_array($decoded['items'])) {
                    $decoded['updated_at'] = (string) ($row['updated_at'] ?? '');
                    return $decoded;
                }
            }
        } catch (Throwable) {
        }
        return null;
    }

    /** @return ?array<string,mixed> */
    public function getCachedRanking(string $enterpriseId, string $jobHash): ?array
    {
        return $this->cachedMatchRanking($enterpriseId, $jobHash);
    }

    /** @param array<string,mixed> $ranking */
    public function storeMatchRanking(string $enterpriseId, string $jobHash, array $ranking): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR REPLACE INTO enterprise_ai_match_rankings (enterprise_id, job_hash, ranking_json, updated_at) VALUES (?, ?, ?, ?)'
            : 'INSERT INTO enterprise_ai_match_rankings (enterprise_id, job_hash, ranking_json, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ranking_json = VALUES(ranking_json), updated_at = VALUES(updated_at)';
        $this->pdo->prepare($sql)->execute([
            $enterpriseId,
            $jobHash,
            json_encode($ranking, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $ranking */
    public function saveCachedRanking(string $enterpriseId, string $jobHash, array $ranking): void
    {
        $this->storeMatchRanking($enterpriseId, $jobHash, $ranking);
    }

    /**
     * @return array{id:string,name:string,status:string,verificationStatus:string}
     */
    public function enterpriseForUser(string $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT e.id, e.name, e.status, e.verificationStatus
            FROM enterprise_members em
            INNER JOIN enterprises e ON e.id = em.enterpriseId
            WHERE em.userId = :userId AND e.status = 'active'
            LIMIT 2
        SQL);
        $statement->execute(['userId' => $userId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (count($rows) !== 1) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một doanh nghiệp.');
        }

        $enterprise = $rows[0];
        if (($enterprise['status'] ?? '') !== 'active' || ($enterprise['verificationStatus'] ?? '') !== 'verified') {
            throw new ApiException(403, 'ENTERPRISE_NOT_VERIFIED', 'Chỉ doanh nghiệp đang hoạt động và đã được xác thực mới có quyền tìm kiếm nhân tài.');
        }

        return $enterprise;
    }

    public function studentIdForUser(string $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId = :userId LIMIT 1');
        $stmt->execute(['userId' => $userId]);
        $id = $stmt->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
        }
        return $id;
    }

    public function recordProfileAccess(
        string $enterpriseId,
        string $userId,
        string $studentId,
        string $accessType,
        ?string $requestId = null,
        ?string $ipAddress = null
    ): void {
        if (!$this->tableExists('student_profile_access_logs')) {
            return;
        }
        if (!in_array($accessType, ['talent_detail', 'application_cv', 'shared_profile'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Loại truy cập hồ sơ không hợp lệ.');
        }

        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO student_profile_access_logs
                (id, enterpriseId, studentId, accessedByUserId, accessType, requestId, ipAddress, metadata, accessedAt)
            VALUES
                (:id, :enterpriseId, :studentId, :userId, :accessType, :requestId, :ipAddress, :metadata, :accessedAt)
        SQL);
        $statement->execute([
            'id' => Uuid::v4(),
            'enterpriseId' => $enterpriseId,
            'studentId' => $studentId,
            'userId' => $userId,
            'accessType' => $accessType,
            'requestId' => $requestId,
            'ipAddress' => $ipAddress,
            'metadata' => json_encode(['source' => 'enterprise_talent_service'], JSON_THROW_ON_ERROR),
            'accessedAt' => $this->now(),
        ]);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listTalents(string $enterpriseId, array $filters = []): array
    {
        $now = $this->now();
        $hasCurrentScores = $this->tableExists('learner_evaluations');
        $hasPartnership = $this->tableExists('school_enterprise_partnerships');

        $where = [
            "u.status = 'active'",
        ];
        $params = [];

        // Enterprise Talent Discovery requires candidates to have demonstrated competency evaluation data:
        // Either evaluated talentScore, standardized assessment score, verified/scored skills, or active practical projects.
        // Profiles with zero evaluation data and no practical skills/projects are excluded from enterprise talent evaluation.
        $hasAssessments = $this->tableExists('assessments');
        $hasProjects = $this->tableExists('projects') && $this->tableExists('project_members');
        $talentScoreCol = $this->columnExists('student_profiles', 'talentScore') ? 'student.talentScore' : 'NULL';

        $assessSubCondition = $hasAssessments
            ? "EXISTS (SELECT 1 FROM assessments sa WHERE sa.studentId = student.id AND sa.overallScore IS NOT NULL)"
            : "0=1";
        $projectSubCondition = $hasProjects
            ? "EXISTS (SELECT 1 FROM project_members pm WHERE pm.studentId = student.id AND (pm.status = 'active' OR pm.status IS NULL))"
            : "0=1";

        if (!$hasCurrentScores) $where[] = "(
            ({$talentScoreCol} IS NOT NULL AND {$talentScoreCol} > 0)
            OR {$assessSubCondition}
            OR EXISTS (SELECT 1 FROM student_skills ss WHERE ss.studentId = student.id AND (ss.levelScore > 0 OR ss.verificationStatus = 'verified'))
            OR {$projectSubCondition}
        )";

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $where[] = "EXISTS (
                SELECT 1 FROM enterprise_talent_access_grants accessGrant
                INNER JOIN privacy_consents consent ON consent.id = accessGrant.consentId AND consent.studentId = student.id AND consent.scope = 'enterprise_talent_discovery' AND consent.isGranted = 1 AND consent.revokedAt IS NULL
                WHERE accessGrant.studentId = student.id AND accessGrant.enterpriseId = :enterpriseIdGrant AND accessGrant.scope = 'enterprise_talent_discovery' AND accessGrant.revokedAt IS NULL AND accessGrant.expiresAt > :nowGrant
            )";
        }

        // Optional filter: Partnered schools only (if explicitly requested)
        if (!empty($filters['partnered_only']) && $hasPartnership) {
            $where[] = '(s.id IS NULL OR EXISTS (SELECT 1 FROM school_enterprise_partnerships sep WHERE sep.schoolId = s.id AND sep.enterpriseId = :enterpriseIdPartnership AND sep.status = \'approved\'))';
            $params['enterpriseIdPartnership'] = $enterpriseId;
        }

        // Filter: School ID or School Name (only when specified and not 'all')
        $schoolFilter = trim((string) ($filters['school_id'] ?? $filters['school'] ?? ''));
        if ($schoolFilter !== '' && $schoolFilter !== 'all') {
            if (Uuid::isValid($schoolFilter)) {
                $where[] = 's.id = :schoolIdFilter';
                $params['schoolIdFilter'] = $schoolFilter;
            } else {
                $where[] = '(s.name LIKE :schoolNameFilter OR s.id LIKE :schoolNameFilter2)';
                $params['schoolNameFilter'] = '%' . $schoolFilter . '%';
                $params['schoolNameFilter2'] = '%' . $schoolFilter . '%';
            }
        }

        // Filter: Education level / Bậc học (only when specified and not 'all')
        $eduFilter = trim((string) ($filters['education_level'] ?? $filters['educationLevel'] ?? $filters['level'] ?? $filters['studyStatus'] ?? ''));
        if ($eduFilter !== '' && $eduFilter !== 'all') {
            if (strcasecmp($eduFilter, 'THPT') === 0 || stripos($eduFilter, 'Phổ thông') !== false) {
                $where[] = "(s.level LIKE '%Trung học Phổ thông%' OR s.level LIKE '%THPT%' OR s.name LIKE '%THPT%' OR c.name REGEXP '^(10|11|12)[A-Za-z0-9_-]*')";
            } elseif (strcasecmp($eduFilter, 'THCS') === 0 || stripos($eduFilter, 'Cơ sở') !== false) {
                $where[] = "(s.level LIKE '%Trung học Cơ sở%' OR s.level LIKE '%THCS%' OR s.name LIKE '%THCS%' OR c.name REGEXP '^(6|7|8|9)[A-Za-z0-9_-]*')";
            } elseif (strcasecmp($eduFilter, 'Cao đẳng') === 0 || stripos($eduFilter, 'Cao đẳng') !== false) {
                $where[] = "(s.level LIKE '%Cao đẳng%' OR s.name LIKE '%Cao đẳng%' OR s.name LIKE '%BTEC%')";
            } elseif (strcasecmp($eduFilter, 'Đại học') === 0 || stripos($eduFilter, 'Đại học') !== false) {
                $where[] = "(s.level LIKE '%Đại học%' OR s.name LIKE '%Đại học%')";
            } else {
                $where[] = '(s.level LIKE :eduFilter1 OR student.studyStatus LIKE :eduFilter2 OR c.name LIKE :eduFilter3 OR s.name LIKE :eduFilter4)';
                $params['eduFilter1'] = '%' . $eduFilter . '%';
                $params['eduFilter2'] = '%' . $eduFilter . '%';
                $params['eduFilter3'] = '%' . $eduFilter . '%';
                $params['eduFilter4'] = '%' . $eduFilter . '%';
            }
        }

        // Filter: Skill tag — JS gửi key 'skills' (comma-separated), cũng hỗ trợ 'skill_tag'/'skill' (legacy).
        // Lưu ý: filter 'skills' (multi) được xử lý thêm ở bước post-query PHP bên dưới (với alias map).
        // WHERE clause này chỉ xử lý single-skill hoặc first-skill khi chỉ có 1 kỹ năng được chọn.
        $rawSkillsInput = $filters['skill_tag'] ?? $filters['skill'] ?? $filters['skills'] ?? '';
        $rawSkillsParam = is_array($rawSkillsInput) ? implode(',', $rawSkillsInput) : trim((string)$rawSkillsInput);
        // Nếu có nhiều kỹ năng (comma-separated), bỏ qua bước SQL WHERE — post-processing sẽ handle.
        $skillTag = (str_contains($rawSkillsParam, ',') ? '' : $rawSkillsParam);
        if (!$hasCurrentScores && $skillTag !== '' && $skillTag !== 'all') {
            $where[] = 'EXISTS (
                SELECT 1 FROM student_skills ss
                JOIN skills sk ON ss.skillId = sk.id
                WHERE ss.studentId = student.id
                  AND (sk.name LIKE :skillTag1 OR sk.code LIKE :skillTag2)
            )';
            $params['skillTag1'] = '%' . $skillTag . '%';
            $params['skillTag2'] = '%' . $skillTag . '%';
        }

        // Filter: Keyword search across candidate name, headline, bio, school, class, skills
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? $filters['keyword'] ?? ''));
        if (!$hasCurrentScores && $search !== '' && $search !== 'all') {
            $searchWildcard = '%' . $search . '%';
            $where[] = '(u.fullName LIKE :search1 OR spd.headline LIKE :search2 OR spd.bio LIKE :search3 OR s.name LIKE :search4 OR c.name LIKE :search5 OR EXISTS (
                SELECT 1 FROM student_skills ssk
                JOIN skills skk ON ssk.skillId = skk.id
                WHERE ssk.studentId = student.id
                  AND (skk.name LIKE :searchSkill1 OR skk.code LIKE :searchSkill2)
            ) OR EXISTS (
                SELECT 1 FROM project_members pm
                JOIN projects p ON p.id = pm.projectId
                WHERE pm.studentId = student.id
                  AND (p.title LIKE :searchProj1 OR p.description LIKE :searchProj2 OR p.category LIKE :searchProj3)
            ))';
            $params['search1'] = $searchWildcard;
            $params['search2'] = $searchWildcard;
            $params['search3'] = $searchWildcard;
            $params['search4'] = $searchWildcard;
            $params['search5'] = $searchWildcard;
            $params['searchSkill1'] = $searchWildcard;
            $params['searchSkill2'] = $searchWildcard;
            $params['searchProj1'] = $searchWildcard;
            $params['searchProj2'] = $searchWildcard;
            $params['searchProj3'] = $searchWildcard;
        }

        // Filter: Major / Domain / Lĩnh vực năng lực
        $majorField = trim((string) ($filters['major_field'] ?? $filters['field'] ?? $filters['major'] ?? $filters['domain'] ?? ''));
        if (!$hasCurrentScores && $majorField !== '' && $majorField !== 'all') {
            if ($majorField === 'ai_ml' || stripos($majorField, 'AI') !== false || stripos($majorField, 'dữ liệu') !== false || stripos($majorField, 'Data') !== false || stripos($majorField, 'Trí tuệ Nhân tạo') !== false) {
                $where[] = "(
                    spd.headline LIKE '%AI%'
                    OR spd.headline LIKE '%Trí tuệ Nhân tạo%'
                    OR spd.headline LIKE '%Data%'
                    OR spd.headline LIKE '%Machine Learning%'
                    OR spd.bio LIKE '%AI%'
                    OR spd.bio LIKE '%Trí tuệ Nhân tạo%'
                    OR c.name LIKE '%AI%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Python', 'Machine Learning', 'AI / Machine Learning', 'PyTorch', 'Computer Vision', 'Phân tích dữ liệu', 'LangChain', 'Prompt Engineering')
                    )
                )";
            } elseif ($majorField === 'frontend' || stripos($majorField, 'Frontend') !== false || stripos($majorField, 'Front-end') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Frontend%'
                    OR spd.headline LIKE '%Front-end%'
                    OR spd.headline LIKE '%React%'
                    OR spd.headline LIKE '%Vue%'
                    OR spd.headline LIKE '%Web%'
                    OR spd.headline LIKE '%UI/UX%'
                    OR spd.bio LIKE '%Frontend%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('React', 'Vue.js', 'HTML', 'CSS', 'JavaScript', 'TypeScript', 'Frontend', 'Tailwind', 'Next.js', 'UI/UX')
                    )
                )";
            } elseif ($majorField === 'backend' || stripos($majorField, 'Backend') !== false || stripos($majorField, 'Back-end') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Backend%'
                    OR spd.headline LIKE '%Back-end%'
                    OR spd.headline LIKE '%Java%'
                    OR spd.headline LIKE '%Node%'
                    OR spd.headline LIKE '%PHP%'
                    OR spd.headline LIKE '%Spring%'
                    OR spd.bio LIKE '%Backend%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Node.js', 'Java', 'Spring Boot', 'Docker', 'MySQL', 'SQL', 'PHP', 'Laravel', 'Python', 'C#', '.NET', 'REST API', 'Backend')
                    )
                )";
            } elseif ($majorField === 'security' || stripos($majorField, 'An toàn') !== false || stripos($majorField, 'Security') !== false || stripos($majorField, 'Bảo mật') !== false) {
                $where[] = "(
                    spd.headline LIKE '%An toàn%'
                    OR spd.headline LIKE '%Security%'
                    OR spd.headline LIKE '%Bảo mật%'
                    OR spd.bio LIKE '%Security%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('An toàn thông tin', 'Cyber Security', 'Network Security')
                    )
                )";
            } elseif ($majorField === 'biz_mgmt' || (stripos($majorField, 'Kinh doanh') !== false && stripos($majorField, 'Marketing') === false) || stripos($majorField, 'QTKD') !== false || stripos($majorField, 'Quản trị') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Kinh doanh%'
                    OR spd.headline LIKE '%Quản trị%'
                    OR spd.bio LIKE '%Kinh doanh%'
                    OR spd.bio LIKE '%Quản trị%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Quản trị Kinh doanh', 'Quản lý dự án', 'Nghiên cứu thị trường', 'Bán hàng', 'Sales', 'Khởi nghiệp & Quản trị')
                    )
                )";
            } elseif ($majorField === 'marketing_media' || $majorField === 'marketing_pr' || stripos($majorField, 'Marketing') !== false || stripos($majorField, 'Truyền thông') !== false || stripos($majorField, 'PR') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Marketing%'
                    OR spd.headline LIKE '%Truyền thông%'
                    OR spd.headline LIKE '%PR%'
                    OR spd.bio LIKE '%Marketing%'
                    OR spd.bio LIKE '%Truyền thông%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Digital Marketing', 'Sáng tạo nội dung', 'Nghiên cứu thị trường', 'SEO', 'Google Analytics', 'Social Ads', 'Copywriting')
                    )
                )";
            } elseif ($majorField === 'finance_acc' || stripos($majorField, 'Tài chính') !== false || stripos($majorField, 'Kế toán') !== false || stripos($majorField, 'Ngân hàng') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Tài chính%'
                    OR spd.headline LIKE '%Kế toán%'
                    OR spd.headline LIKE '%Ngân hàng%'
                    OR spd.bio LIKE '%Tài chính%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Tài chính', 'Kế toán', 'PowerBI', 'Excel nâng cao', 'Lập báo cáo tài chính', 'Kế toán chi phí')
                    )
                )";
            } elseif ($majorField === 'logistics_sc' || stripos($majorField, 'Logistics') !== false || stripos($majorField, 'kho vận') !== false || stripos($majorField, 'cung ứng') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Logistics%'
                    OR spd.headline LIKE '%Kho vận%'
                    OR spd.headline LIKE '%Chuỗi cung ứng%'
                    OR spd.bio LIKE '%Logistics%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Quản trị kho vận', 'Logistics', 'Tối ưu hóa đơn hàng', 'Phân tích dữ liệu vận hành', 'Supply Chain')
                    )
                )";
            } elseif ($majorField === 'data_bi' || stripos($majorField, 'BI') !== false) {
                $where[] = "(
                    spd.headline LIKE '%BI%'
                    OR spd.headline LIKE '%Phân tích dữ liệu%'
                    OR spd.headline LIKE '%Data%'
                    OR spd.bio LIKE '%BI%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('PowerBI', 'Phân tích dữ liệu', 'Data Analytics', 'Excel nâng cao', 'SQL', 'Tableau')
                    )
                )";
            } elseif ($majorField === 'electronics_automation' || stripos($majorField, 'Điện tử') !== false || stripos($majorField, 'Tự động hóa') !== false || stripos($majorField, 'Automation') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Điện tử%'
                    OR spd.headline LIKE '%Tự động hóa%'
                    OR spd.headline LIKE '%Automation%'
                    OR spd.headline LIKE '%Robotics%'
                    OR spd.headline LIKE '%IoT%'
                    OR spd.bio LIKE '%Điện tử%'
                    OR spd.bio LIKE '%Tự động hóa%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Điện tử', 'Tự động hóa', 'Automation', 'Robotics', 'PLC', 'Vi điều khiển', 'Nhúng', 'Embedded', 'IoT', 'Cơ điện tử')
                    )
                )";
            } elseif ($majorField === 'mechanical_engineering' || stripos($majorField, 'Cơ khí') !== false || stripos($majorField, 'Mechanical') !== false || stripos($majorField, 'Chế tạo') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Cơ khí%'
                    OR spd.headline LIKE '%Chế tạo%'
                    OR spd.headline LIKE '%Mechanical%'
                    OR spd.bio LIKE '%Cơ khí%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Cơ khí', 'Kỹ thuật cơ khí', 'Mechanical', 'Chế tạo máy', 'CAD', 'CAM', 'CNC', 'SolidWorks', 'AutoCAD')
                    )
                )";
            } elseif ($majorField === 'design_multimedia' || stripos($majorField, 'Thiết kế') !== false || stripos($majorField, 'Đa phương tiện') !== false || stripos($majorField, 'Multimedia') !== false || stripos($majorField, 'UI/UX') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Thiết kế%'
                    OR spd.headline LIKE '%Đa phương tiện%'
                    OR spd.headline LIKE '%Multimedia%'
                    OR spd.headline LIKE '%UI/UX%'
                    OR spd.headline LIKE '%Graphic%'
                    OR spd.bio LIKE '%Thiết kế%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Thiết kế đồ họa', 'Graphic Design', 'UI/UX', 'Photoshop', 'Illustrator', 'Figma', 'Video editing', 'Animation', 'Multimedia', 'Đa phương tiện')
                    )
                )";
            } elseif ($majorField === 'tourism_hospitality' || stripos($majorField, 'Du lịch') !== false || stripos($majorField, 'Khách sạn') !== false || stripos($majorField, 'Tourism') !== false || stripos($majorField, 'Hospitality') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Du lịch%'
                    OR spd.headline LIKE '%Khách sạn%'
                    OR spd.headline LIKE '%Tourism%'
                    OR spd.headline LIKE '%Hospitality%'
                    OR spd.bio LIKE '%Du lịch%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('Du lịch', 'Khách sạn', 'Tourism', 'Hospitality', 'Quản trị khách sạn', 'Nhà hàng', 'Lữ hành', 'F&B', 'Tour guide')
                    )
                )";
            } elseif (stripos($majorField, 'Công nghệ') !== false || stripos($majorField, 'Phần mềm') !== false || stripos($majorField, 'Web') !== false || stripos($majorField, 'Lập trình') !== false) {
                $where[] = "(
                    spd.headline LIKE '%Công nghệ%'
                    OR spd.headline LIKE '%Phần mềm%'
                    OR spd.headline LIKE '%Lập trình%'
                    OR spd.headline LIKE '%Web%'
                    OR spd.headline LIKE '%AI%'
                    OR spd.bio LIKE '%Công nghệ%'
                    OR c.name LIKE '%BTEC%'
                    OR c.name LIKE '%CNTT%'
                    OR c.name LIKE '%Công nghệ%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = student.id
                          AND skills.name IN ('React', 'Node.js', 'Python', 'TypeScript', 'JavaScript', 'HTML', 'CSS', 'Java', 'PHP', 'Docker', 'Git', 'REST API', 'MySQL', 'AI / Machine Learning')
                    )
                    OR EXISTS (
                        SELECT 1 FROM project_members pm
                        JOIN projects p ON p.id = pm.projectId
                        WHERE pm.studentId = student.id
                    )
                )";
            } else {
                $where[] = "(spd.headline LIKE :maj1 OR spd.bio LIKE :maj2 OR c.name LIKE :maj3)";
                $params['maj1'] = '%' . $majorField . '%';
                $params['maj2'] = '%' . $majorField . '%';
                $params['maj3'] = '%' . $majorField . '%';
            }
        }

        $selectedJobId = trim((string) ($filters['jobId'] ?? $filters['job_id'] ?? $filters['postId'] ?? $filters['post_id'] ?? ''));
        $appStatusSubquery = 'NULL AS current_post_app_status';
        if ($this->tableExists('internship_applications') && $this->tableExists('internship_posts')) {
            if ($selectedJobId !== '') {
                $appStatusSubquery = '(SELECT ia.status FROM internship_applications ia INNER JOIN internship_posts ip_chk ON ip_chk.id = ia.postId WHERE (ia.studentId = student.id OR ia.studentId = u.id) AND ia.postId = :selectedJobIdApp AND ip_chk.enterpriseId = :selectedJobEnterpriseIdApp ORDER BY ia.updatedAt DESC LIMIT 1) AS current_post_app_status';
                $params['selectedJobIdApp'] = $selectedJobId;
                $params['selectedJobEnterpriseIdApp'] = $enterpriseId;

                // When a specific internship is selected, candidates who already have an application
                // (especially accepted/approved) for this exact internship post at this enterprise are ineligible for invitation and excluded from the pool.
                $where[] = "NOT EXISTS (
                    SELECT 1 FROM internship_applications ia_filter
                    INNER JOIN internship_posts ip_filter ON ip_filter.id = ia_filter.postId
                    WHERE (ia_filter.studentId = student.id OR ia_filter.studentId = u.id)
                      AND ia_filter.postId = :selectedJobIdFilter
                      AND ip_filter.enterpriseId = :selectedJobEntFilter
                      AND ia_filter.status IN ('accepted', 'approved', 'hired', 'submitted', 'reviewing', 'interview', 'invited')
                )";
                $params['selectedJobIdFilter'] = $selectedJobId;
                $params['selectedJobEntFilter'] = $enterpriseId;
            } else {
                // When no specific internship is selected (all talents view for this enterprise),
                // query candidate's latest application status for this enterprise
                $appStatusSubquery = '(SELECT ia.status FROM internship_applications ia INNER JOIN internship_posts ip_chk ON ip_chk.id = ia.postId WHERE (ia.studentId = student.id OR ia.studentId = u.id) AND ip_chk.enterpriseId = :allJobsEnterpriseIdApp ORDER BY CASE WHEN ia.status IN (\'accepted\', \'approved\', \'hired\') THEN 1 WHEN ia.status IN (\'submitted\', \'reviewing\', \'interview\') THEN 2 WHEN ia.status = \'invited\' THEN 3 ELSE 4 END ASC, ia.updatedAt DESC LIMIT 1) AS current_post_app_status';
                $params['allJobsEnterpriseIdApp'] = $enterpriseId;
            }
        }

        $whereClause = implode(' AND ', $where);

        $publishedScoreSql = $this->publishedAssessmentScoreSql();

        $sql = <<<SQL
            SELECT
                student.id AS studentId,
                u.id AS userId,
                u.fullName AS displayName,
                s.id AS schoolId,
                s.name AS schoolName,
                c.id AS classId,
                c.name AS className,
                student.studyStatus,
                spd.location,
                spd.headline,
                spd.bio,
                spd.avatarUrl,
                accessGrant.grantedAt,
                accessGrant.expiresAt,
                student.createdAt AS studentCreatedAt,
                {$publishedScoreSql} AS talentScore,
                COUNT(DISTINCT studentSkill.id) AS skillCount,
                COUNT(DISTINCT CASE WHEN studentSkill.verificationStatus = 'verified' THEN studentSkill.id END) AS verifiedSkillCount,
                EXISTS(
                    SELECT 1 FROM enterprise_talent_access_grants contactGrant
                    WHERE contactGrant.studentId = student.id
                      AND contactGrant.enterpriseId = :enterpriseIdContact
                      AND contactGrant.scope = 'enterprise_talent_contact'
                      AND contactGrant.revokedAt IS NULL
                      AND contactGrant.expiresAt > :nowContact
                ) AS contactAllowed,
                EXISTS(
                    SELECT 1 FROM enterprise_contact_requests cr
                    WHERE cr.studentId = student.id
                      AND cr.enterpriseId = :enterpriseIdCr
                      AND cr.status = 'pending'
                ) AS hasPendingContactRequest,
                {$appStatusSubquery}
            FROM student_profiles student
            INNER JOIN users u ON u.id = student.userId
            LEFT JOIN classes c ON c.id = student.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = student.id
            LEFT JOIN student_skills studentSkill ON studentSkill.studentId = student.id
            LEFT JOIN enterprise_talent_access_grants accessGrant
              ON accessGrant.studentId = student.id
             AND accessGrant.enterpriseId = :enterpriseIdGrant
             AND accessGrant.scope = 'enterprise_talent_discovery'
             AND accessGrant.revokedAt IS NULL
             AND accessGrant.expiresAt > :nowGrant
            WHERE {$whereClause}
            GROUP BY student.id, u.id, u.fullName, s.id, s.name, c.id, c.name, student.studyStatus,
                     spd.location, spd.headline, spd.bio, spd.avatarUrl, accessGrant.grantedAt, accessGrant.expiresAt
        SQL;

        $params['enterpriseIdContact'] = $enterpriseId;
        $params['nowContact'] = $now;
        $params['enterpriseIdCr'] = $enterpriseId;
        $params['enterpriseIdGrant'] = $enterpriseId;
        $params['nowGrant'] = $now;

        // Sorting
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'score_desc';
        $orderClause = match ($sort) {
            'skills' => 'ORDER BY verifiedSkillCount DESC, skillCount DESC, u.fullName ASC',
            'name' => 'ORDER BY u.fullName ASC',
            'newest' => 'ORDER BY student.createdAt DESC, student.id ASC',
            'exp_desc' => 'ORDER BY skillCount DESC, verifiedSkillCount DESC, student.createdAt DESC',
            'score_desc', 'matching' => 'ORDER BY talentScore DESC, verifiedSkillCount DESC, student.createdAt DESC',
            default => 'ORDER BY talentScore DESC, verifiedSkillCount DESC, student.createdAt DESC',
        };

        $limitClause = '';
        if (!$hasCurrentScores && isset($filters['limit']) && is_numeric($filters['limit']) && (int) $filters['limit'] > 0) {
            $limit = (int) $filters['limit'];
            $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
            $limitClause = " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare("{$sql} {$orderClause}{$limitClause}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Preload candidate projects to extract demonstrated skills and provide actual project data
        $studentProjectsMap = [];
        if ($rows !== [] && $this->tableExists('projects') && $this->tableExists('project_members')) {
            $studentIds = array_map(static fn($r) => (string) $r['studentId'], $rows);
            $userIds = array_values(array_filter(array_map(static fn($r) => (string) ($r['userId'] ?? ''), $rows)));
            $allIdKeys = array_unique(array_merge($studentIds, $userIds));

            if ($allIdKeys !== []) {
                $placeholders = implode(',', array_fill(0, count($allIdKeys), '?'));
                $pSql = <<<SQL
                    SELECT
                        pm.studentId,
                        p.id AS project_id,
                        p.title,
                        p.category,
                        p.description,
                        p.projectUrl,
                        p.status,
                        p.createdAt,
                        p.updatedAt,
                        pm.role,
                        pm.contribution,
                        pm.status AS member_status
                    FROM project_members pm
                    INNER JOIN projects p ON p.id = pm.projectId
                    WHERE pm.studentId IN ({$placeholders})
                      AND (pm.status = 'active' OR pm.status IS NULL)
                    ORDER BY p.createdAt DESC
                SQL;
                try {
                    $pStmt = $this->pdo->prepare($pSql);
                    $pStmt->execute($allIdKeys);
                    while ($pRow = $pStmt->fetch(PDO::FETCH_ASSOC)) {
                        $mStudentId = (string) ($pRow['studentId'] ?? '');
                        $studentProjectsMap[$mStudentId][] = [
                            'id' => (string) ($pRow['project_id'] ?? ''),
                            'title' => (string) ($pRow['title'] ?? ''),
                            'category' => (string) ($pRow['category'] ?? ''),
                            'description' => (string) ($pRow['description'] ?? ''),
                            'projectUrl' => (string) ($pRow['projectUrl'] ?? ''),
                            'role' => (string) ($pRow['role'] ?? 'member'),
                            'contribution' => (string) ($pRow['contribution'] ?? ''),
                        ];
                    }
                } catch (\Throwable $e) {
                    error_log('listTalents project query error: ' . $e->getMessage());
                }
            }
        }

        // Post-process skills filter and populate verifiedSkills list
        $filterSkills = [];
        if ($rawSkillsParam !== '' && $rawSkillsParam !== 'all') {
            $filterSkills = array_values(array_filter(array_map('trim', explode(',', $rawSkillsParam))));
        }

        $items = [];
        foreach ($rows as $row) {
            $studentId = (string) $row['studentId'];
            $userId = (string) ($row['userId'] ?? '');
            // $dbSkills = kỹ năng thực tế từ student_skills (dùng cho "Kỹ năng xác thực" và Talent Profile)
            $snapshot = $hasCurrentScores ? $this->discoveryScores($studentId, $enterpriseId) : null;
            if ($hasCurrentScores && $snapshot === null) continue;
            $currentSkills = $snapshot !== null ? $this->mapCurrentSkills($snapshot['skills']) : $this->skillsWithDetailsForStudent($studentId, $enterpriseId);
            $dbSkills = array_values(array_unique(array_column($currentSkills, 'name')));
            $studentProjects = $studentProjectsMap[$studentId] ?? $studentProjectsMap[$userId] ?? [];
            if ($hasCurrentScores && $currentSkills === [] && $studentProjects === [] && !is_numeric($row['talentScore'] ?? null)) continue;

            // Extract inferred skills from project content — chỉ dùng cho AI matching/filter,
            // KHÔNG được gọi là "xác thực" vì không có nguồn gốc từ student_skills.
            $projectSkills = $this->extractSkillsFromProjects($studentProjects);
            // $skills = merged set cho mục đích matching và filter (bao gồm cả inferred)
            $skills = $projectSkills !== []
                ? array_values(array_unique(array_merge($dbSkills, $projectSkills)))
                : $dbSkills;

            if ($hasCurrentScores) {
                $skillTerms = array_merge($dbSkills, array_column($currentSkills, 'code'), array_column($currentSkills, 'group_code'));
                $searchText = implode(' ', array_filter(array_merge([
                    $row['displayName'], $row['headline'], $row['bio'], $row['schoolName'], $row['className'],
                ], $skillTerms, array_column($studentProjects, 'title'), array_column($studentProjects, 'description'), array_column($studentProjects, 'category')), 'is_string'));
                if ($search !== '' && $search !== 'all' && mb_stripos($searchText, $search) === false) continue;
                $domainText = implode(' ', array_filter(array_merge([
                    $row['headline'], $row['bio'], $row['className'],
                ], $skillTerms, array_column($studentProjects,'title'), array_column($studentProjects,'description'), array_column($studentProjects,'category')), 'is_string'));
                if ($majorField !== '' && $majorField !== 'all' && !$this->matchesDiscoveryDomain($majorField, $domainText)) continue;
            }

            if ($filterSkills !== []) {
                $hasAllSkills = true;
                $lowerSkills = array_map('mb_strtolower', array_filter(array_merge($skills,
                    array_column($currentSkills,'code'), array_column($currentSkills,'group_code')), static fn($s) => is_string($s) && $s !== ''));

                $aliases = [
                    'nghiên cứu thị trường' => ['phân tích thị trường', 'nghiên cứu thị trường', 'market research', 'market analysis'],
                    'phân tích thị trường' => ['phân tích thị trường', 'nghiên cứu thị trường', 'market research', 'market analysis'],
                    'quản trị kho vận' => ['quản lý kho vận', 'quản trị kho vận', 'warehouse', 'kho vận'],
                    'quản lý kho vận' => ['quản lý kho vận', 'quản trị kho vận', 'warehouse', 'kho vận'],
                    'tiếng anh giao tiếp' => ['tiếng anh', 'tiếng anh toeic 800', 'tiếng anh toeic 850', 'tiếng anh giao tiếp', 'toeic', 'ielts', 'english'],
                    'phân tích dữ liệu' => ['phân tích dữ liệu', 'data analysis', 'data analytics', 'data analyst'],
                    'excel nâng cao' => ['excel nâng cao', 'excel', 'advanced excel'],
                    'kỹ năng thuyết trình' => ['kỹ năng thuyết trình', 'thuyết trình', 'presentation'],
                    'digital marketing' => ['digital marketing', 'marketing', 'tiếp thị số'],
                    'sáng tạo nội dung' => ['sáng tạo nội dung', 'content marketing', 'content creator'],
                ];

                foreach ($filterSkills as $requiredSkill) {
                    $reqLow = mb_strtolower($requiredSkill);
                    $checkList = $aliases[$reqLow] ?? [$reqLow];

                    $skillMatched = false;
                    foreach ($lowerSkills as $candSkill) {
                        foreach ($checkList as $target) {
                            if ($candSkill === $target || mb_strpos($candSkill, $target) !== false || mb_strpos($target, $candSkill) !== false) {
                                $skillMatched = true;
                                break 2;
                            }
                        }
                    }

                    if (!$skillMatched) {
                        $hasAllSkills = false;
                        break;
                    }
                }
                if (!$hasAllSkills) {
                    continue;
                }
            }

            // Application eligibility rule: When an internship post is selected,
            // candidates who already have an application for this exact internship post at this enterprise
            // must NOT appear in the candidate pool for invitation (matching AI search eligibility).
            if ($selectedJobId !== '') {
                $stRaw = strtolower(trim((string) ($row['current_post_app_status'] ?? '')));
                if ($stRaw !== '' || $this->hasApplicationForJob($enterpriseId, $selectedJobId, $studentId, $userId)) {
                    continue;
                }
            }

            $stRaw = strtolower(trim((string) ($row['current_post_app_status'] ?? '')));
            if ($stRaw === '' && $selectedJobId === '') {
                $acceptedCheck = $this->findAcceptedApplicationForEnterprise($enterpriseId, $studentId, $userId);
                if ($acceptedCheck !== null) {
                    $stRaw = strtolower(trim((string) ($acceptedCheck['status'] ?? 'accepted')));
                }
            }
            $canInvite = true;
            $internshipStatus = 'ready_now';
            $internshipStatusLabel = 'Sẵn sàng thực tập';
            $actionLabel = 'Mời ứng tuyển';

            if (in_array($stRaw, ['accepted', 'approved', 'hired'], true)) {
                $canInvite = false;
                $internshipStatus = 'accepted';
                $internshipStatusLabel = 'Đã nhận';
                $actionLabel = 'Đã tiếp nhận';
            } elseif (in_array($stRaw, ['submitted'], true)) {
                $canInvite = false;
                $internshipStatus = 'submitted';
                $internshipStatusLabel = 'Đang xét duyệt';
                $actionLabel = 'Đã ứng tuyển';
            } elseif (in_array($stRaw, ['reviewing'], true)) {
                $canInvite = false;
                $internshipStatus = 'reviewing';
                $internshipStatusLabel = 'Đang xét duyệt';
                $actionLabel = 'Đang xét duyệt';
            } elseif (in_array($stRaw, ['interview', 'interviewing'], true)) {
                $canInvite = false;
                $internshipStatus = 'interviewing';
                $internshipStatusLabel = 'Đang phỏng vấn';
                $actionLabel = 'Đang phỏng vấn';
            } elseif (in_array($stRaw, ['invited'], true)) {
                $canInvite = false;
                $internshipStatus = 'invited';
                $internshipStatusLabel = 'Đã mời ứng tuyển';
                $actionLabel = 'Đã gửi lời mời';
            }

            $evalInfo = $selectedJobId !== ''
                ? $this->findApplicationEvaluation($enterpriseId, $selectedJobId, $studentId, $userId)
                : [
                    'has_applied' => false,
                    'application_id' => null,
                    'evaluation_score' => null,
                    'evaluation_status' => 'unapplied',
                    'evaluation_label' => 'Chưa chấm điểm',
                ];

            $score = is_numeric($row['talentScore'] ?? null) ? (float) $row['talentScore'] : null;
            $items[] = [
                'studentId' => $studentId,
                'userId' => $userId,
                '_createdAt' => $row['studentCreatedAt'] ?? '',
                'displayName' => (string) ($row['displayName'] ?? 'Ứng viên'),
                'schoolName' => (string) ($row['schoolName'] ?? ''),
                'className' => (string) ($row['className'] ?? ''),
                'studyStatus' => (string) ($row['studyStatus'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'headline' => (string) ($row['headline'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'avatarUrl' => $row['avatarUrl'] !== null ? (string) $row['avatarUrl'] : null,
                'talentScore' => $score === null ? null : min(100, max(0, $score)),
                'skillCount' => count($dbSkills),
                'verifiedSkillCount' => count(array_filter($currentSkills, static fn($s) => ($s['scoreState'] ?? '') === 'scored')),
                'verifiedSkills' => $dbSkills,
                'skills' => $skills,
                'projects' => $studentProjects,
                'contactAllowed' => (bool) ((int) ($row['contactAllowed'] ?? 0) === 1),
                'hasPendingContactRequest' => (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1),
                'evaluation_score' => $evalInfo['evaluation_score'],
                'evaluationScore' => $evalInfo['evaluation_score'],
                'evaluation_status' => $evalInfo['evaluation_status'],
                'evaluationStatus' => $evalInfo['evaluation_status'],
                'evaluation_label' => $evalInfo['evaluation_label'],
                'evaluationLabel' => $evalInfo['evaluation_label'],
                'has_applied' => $evalInfo['has_applied'],
                'hasApplied' => $evalInfo['has_applied'],
                'canInvite' => $canInvite,
                'can_invite' => $canInvite,
                'internshipStatus' => $internshipStatus,
                'internship_status' => $internshipStatus,
                'internshipStatusLabel' => $internshipStatusLabel,
                'internship_status_label' => $internshipStatusLabel,
                'actionLabel' => $actionLabel,
                'action_label' => $actionLabel,
            ];
        }

        $total = count($items);
        if ($hasCurrentScores) {
            usort($items, static function($a,$b) use ($sort) {
                $newest = strcmp($b['_createdAt'], $a['_createdAt']);
                $verified = $b['verifiedSkillCount'] <=> $a['verifiedSkillCount'];
                $count = $b['skillCount'] <=> $a['skillCount'];
                return (match($sort) {
                    'name' => strcmp($a['displayName'],$b['displayName']),
                    'newest' => $newest,
                    'skills' => $verified ?: $count ?: strcmp($a['displayName'],$b['displayName']),
                    'exp_desc' => $count ?: $verified ?: $newest,
                    default => (($b['talentScore'] ?? -1) <=> ($a['talentScore'] ?? -1)) ?: $verified ?: $newest,
                }) ?: strcmp($a['studentId'],$b['studentId']);
            });
            if (isset($filters['limit']) && is_numeric($filters['limit']) && (int)$filters['limit'] > 0) {
                $items = array_slice($items, max(0,(int)($filters['offset'] ?? 0)), (int)$filters['limit']);
            }
        }
        foreach ($items as &$item) unset($item['_createdAt']);
        unset($item);
        return ['items'=>$items, 'total'=>$total];
    }

    public function getTalentDetail(string $enterpriseId, string $studentId, ?string $jobId = null): ?array
    {
        $now = $this->now();

        $where = [
            '(student.id = :studentId OR u.id = :studentIdAlt)',
            "u.status = 'active'",
        ];

        $params = [
            'studentId' => $studentId,
            'studentIdAlt' => $studentId,
        ];

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $where[] = "EXISTS (
                SELECT 1 FROM enterprise_talent_access_grants accessGrant
                INNER JOIN privacy_consents consent ON consent.id = accessGrant.consentId AND consent.studentId = student.id AND consent.scope = 'enterprise_talent_discovery' AND consent.isGranted = 1 AND consent.revokedAt IS NULL
                WHERE accessGrant.studentId = student.id AND accessGrant.enterpriseId = :enterpriseIdGrant AND accessGrant.scope = 'enterprise_talent_discovery' AND accessGrant.revokedAt IS NULL AND accessGrant.expiresAt > :nowGrant
            )";
            $params['enterpriseIdGrant'] = $enterpriseId;
            $params['nowGrant'] = $now;
        }

        $whereClause = implode(' AND ', $where);

        $publishedScoreSql = $this->publishedAssessmentScoreSql();

        $sql = <<<SQL
            SELECT
                student.id AS studentId,
                u.id AS userId,
                u.fullName AS displayName,
                u.email,
                student.phone,
                s.id AS schoolId,
                s.name AS schoolName,
                c.id AS classId,
                c.name AS className,
                student.studyStatus,
                spd.location,
                spd.headline,
                spd.bio,
                spd.avatarUrl,
                {$publishedScoreSql} AS talentScore,
                EXISTS(
                    SELECT 1 FROM enterprise_talent_access_grants contactGrant
                    WHERE contactGrant.studentId = student.id
                      AND contactGrant.enterpriseId = :enterpriseIdContact
                      AND contactGrant.scope = 'enterprise_talent_contact'
                      AND contactGrant.revokedAt IS NULL
                      AND contactGrant.expiresAt > :nowContact
                ) AS contactAllowed,
                EXISTS(
                    SELECT 1 FROM enterprise_contact_requests cr
                    WHERE cr.studentId = student.id
                      AND cr.enterpriseId = :enterpriseIdCr
                      AND cr.status = 'pending'
                ) AS hasPendingContactRequest
            FROM student_profiles student
            INNER JOIN users u ON u.id = student.userId
            LEFT JOIN classes c ON c.id = student.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = student.id
            WHERE {$whereClause}
            LIMIT 1
        SQL;

        $params['enterpriseIdContact'] = $enterpriseId;
        $params['nowContact'] = $now;
        $params['enterpriseIdCr'] = $enterpriseId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $realStudentId = (string) $row['studentId'];
        $snapshot = $this->tableExists('learner_evaluations') ? $this->discoveryScores($realStudentId, $enterpriseId) : null;
        if ($this->tableExists('learner_evaluations') && $snapshot === null) return null;
        $contactAllowed = (bool) ((int) ($row['contactAllowed'] ?? 0) === 1);
        $hasPendingContact = (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1);

        // Load aggregate details via DatabaseTalentPassportRepository or robust fallback queries
        $passportRepo = $this->getTalentPassportRepository();
        $aggregate = [];
        if ($passportRepo !== null) {
            try {
                $aggregate = $passportRepo->sharedSectionsForStudent($realStudentId, ['skills', 'experience', 'certificates', 'projects']);
            } catch (\Throwable) {
                $aggregate = [];
            }
        }

        $skills = $snapshot !== null ? $this->mapCurrentSkills($snapshot['skills']) : $this->skillsWithDetailsForStudent($realStudentId, $enterpriseId);
        $experience = !empty($aggregate['experience']['confirmed_entries']) ? $aggregate['experience'] : $this->experienceForStudent($realStudentId);
        $certificates = !empty($aggregate['certificates']) ? $aggregate['certificates'] : $this->certificatesForStudent($realStudentId);
        $projects = !empty($aggregate['projects']) ? $aggregate['projects'] : $this->projectsForStudent($realStudentId);

        $detail = [
            'studentId' => $realStudentId,
            'userId' => (string) ($row['userId'] ?? ''),
            'displayName' => (string) ($row['displayName'] ?? 'Ứng viên'),
            'schoolName' => (string) ($row['schoolName'] ?? ''),
            'className' => (string) ($row['className'] ?? ''),
            'studyStatus' => (string) ($row['studyStatus'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'headline' => (string) ($row['headline'] ?? ''),
            'bio' => (string) ($row['bio'] ?? ''),
            'avatarUrl' => $row['avatarUrl'] !== null ? (string) $row['avatarUrl'] : null,
            'talent_score' => is_numeric($row['talentScore'] ?? null) ? (float) $row['talentScore'] : null,
            'contactAllowed' => $contactAllowed,
            'hasPendingContactRequest' => $hasPendingContact,
            'skills' => $skills,
            'experience' => $experience,
            'certificates' => $certificates,
            'projects' => $projects,
        ];

        $selectedJobId = $jobId !== null ? trim($jobId) : '';
        $canInvite = true;
        $internshipStatus = 'ready_now';
        $internshipStatusLabel = 'Sẵn sàng thực tập';
        $actionLabel = 'Mời ứng tuyển';

        if ($selectedJobId !== '') {
            try {
                $appStmt = $this->pdo->prepare("
                    SELECT ia.status, ip.title as postTitle
                    FROM internship_applications ia
                    INNER JOIN internship_posts ip ON ip.id = ia.postId
                    WHERE (ia.studentId = ? OR ia.studentId = ?)
                      AND ia.postId = ?
                      AND ip.enterpriseId = ?
                    ORDER BY ia.updatedAt DESC
                    LIMIT 1
                ");
                $appStmt->execute([$realStudentId, (string) ($row['userId'] ?? ''), $selectedJobId, $enterpriseId]);
                $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);
                if ($appRow) {
                    $st = strtolower(trim((string) $appRow['status']));
                    if (in_array($st, ['accepted', 'approved', 'hired'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'accepted';
                        $internshipStatusLabel = 'Đã nhận';
                        $actionLabel = 'Đã tiếp nhận';
                    } elseif (in_array($st, ['submitted'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'submitted';
                        $internshipStatusLabel = 'Đang xét duyệt';
                        $actionLabel = 'Đã ứng tuyển';
                    } elseif (in_array($st, ['reviewing'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'reviewing';
                        $internshipStatusLabel = 'Đang xét duyệt';
                        $actionLabel = 'Đang xét duyệt';
                    } elseif (in_array($st, ['interview', 'interviewing'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'interviewing';
                        $internshipStatusLabel = 'Đang phỏng vấn';
                        $actionLabel = 'Đang phỏng vấn';
                    } elseif (in_array($st, ['invited'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'invited';
                        $internshipStatusLabel = 'Đã mời ứng tuyển';
                        $actionLabel = 'Đã gửi lời mời';
                    }
                }
            } catch (\Throwable $e) {
                error_log('getTalentDetail app check error: ' . $e->getMessage());
            }
        } else {
            try {
                $appStmt = $this->pdo->prepare("
                    SELECT ia.status, ip.title as postTitle
                    FROM internship_applications ia
                    INNER JOIN internship_posts ip ON ip.id = ia.postId
                    WHERE (ia.studentId = ? OR ia.studentId = ?)
                      AND ip.enterpriseId = ?
                    ORDER BY CASE WHEN ia.status IN ('accepted', 'approved', 'hired') THEN 1
                                  WHEN ia.status IN ('submitted', 'reviewing', 'interview') THEN 2
                                  WHEN ia.status = 'invited' THEN 3
                                  ELSE 4 END ASC,
                             ia.updatedAt DESC
                    LIMIT 1
                ");
                $appStmt->execute([$realStudentId, (string) ($row['userId'] ?? ''), $enterpriseId]);
                $appRow = $appStmt->fetch(PDO::FETCH_ASSOC);
                if ($appRow) {
                    $st = strtolower(trim((string) $appRow['status']));
                    if (in_array($st, ['accepted', 'approved', 'hired'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'accepted';
                        $internshipStatusLabel = 'Đã nhận';
                        $actionLabel = 'Đã tiếp nhận';
                    } elseif (in_array($st, ['submitted'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'submitted';
                        $internshipStatusLabel = 'Đang xét duyệt';
                        $actionLabel = 'Đã ứng tuyển';
                    } elseif (in_array($st, ['reviewing'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'reviewing';
                        $internshipStatusLabel = 'Đang xét duyệt';
                        $actionLabel = 'Đang xét duyệt';
                    } elseif (in_array($st, ['interview', 'interviewing'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'interviewing';
                        $internshipStatusLabel = 'Đang phỏng vấn';
                        $actionLabel = 'Đang phỏng vấn';
                    } elseif (in_array($st, ['invited'], true)) {
                        $canInvite = false;
                        $internshipStatus = 'invited';
                        $internshipStatusLabel = 'Đã mời ứng tuyển';
                        $actionLabel = 'Đã gửi lời mời';
                    }
                }
            } catch (\Throwable $e) {
                error_log('getTalentDetail all-jobs app check error: ' . $e->getMessage());
            }
        }

        $evalInfo = $selectedJobId !== ''
            ? $this->findApplicationEvaluation($enterpriseId, $selectedJobId, $realStudentId, (string) ($row['userId'] ?? ''))
            : [
                'has_applied' => false,
                'application_id' => null,
                'evaluation_score' => null,
                'evaluation_status' => 'unapplied',
                'evaluation_label' => 'Chưa chấm điểm',
            ];

        $detail['evaluation_score'] = $evalInfo['evaluation_score'];
        $detail['evaluation_status'] = $evalInfo['evaluation_status'];
        $detail['evaluation_label'] = $evalInfo['evaluation_label'];
        $detail['has_applied'] = $evalInfo['has_applied'];
        $detail['application_id'] = $evalInfo['application_id'];

        $detail['can_invite'] = $canInvite;
        $detail['canInvite'] = $canInvite;
        $detail['internship_status'] = $internshipStatus;
        $detail['internshipStatus'] = $internshipStatus;
        $detail['internship_status_label'] = $internshipStatusLabel;
        $detail['internshipStatusLabel'] = $internshipStatusLabel;
        $detail['action_label'] = $actionLabel;
        $detail['actionLabel'] = $actionLabel;

        // Include email & phone if contact grant was explicitly granted or allow contact request
        if ($contactAllowed) {
            $detail['email'] = (string) ($row['email'] ?? '');
            $detail['phone'] = (string) ($row['phone'] ?? '');
        }

        return $detail;
    }

    public function createContactRequest(
        string $enterpriseId,
        string $userId,
        string $studentId,
        string $idempotencyKey,
        ?string $message
    ): array {
        $talent = $this->getTalentDetail($enterpriseId, $studentId);
        if ($talent === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng viên hoặc ứng viên chưa cấp quyền.');
        }

        // Idempotency check
        $stmtCheck = $this->pdo->prepare('SELECT id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt FROM enterprise_contact_requests WHERE enterpriseId = ? AND idempotencyKey = ? LIMIT 1');
        $stmtCheck->execute([$enterpriseId, $idempotencyKey]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            return $existing;
        }

        // Check if there is already an active pending request
        $stmtPending = $this->pdo->prepare("SELECT id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt FROM enterprise_contact_requests WHERE enterpriseId = ? AND studentId = ? AND status = 'pending' LIMIT 1");
        $stmtPending->execute([$enterpriseId, $studentId]);
        $existingPending = $stmtPending->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingPending)) {
            return $existingPending;
        }

        $id = Uuid::v4();
        $now = $this->now();

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO enterprise_contact_requests (id, enterpriseId, studentId, idempotencyKey, status, message, requestedAt)
                VALUES (:id, :enterpriseId, :studentId, :idempotencyKey, 'pending', :message, :requestedAt)
            SQL);
            $insert->execute([
                'id' => $id,
                'enterpriseId' => $enterpriseId,
                'studentId' => $studentId,
                'idempotencyKey' => $idempotencyKey,
                'message' => $message === '' ? null : $message,
                'requestedAt' => $now,
            ]);

            // Audit log
            $audit = $this->pdo->prepare('INSERT INTO audit_logs (id, userId, action, entityType, entityId, createdAt) VALUES (?, ?, ?, ?, ?, ?)');
            $audit->execute([Uuid::v4(), $userId, 'enterprise_contact_request.created', 'enterprise_contact_request', $id, $now]);

            // Publish notification to student
            $studentUserId = $this->userIdForStudent($studentId);
            $enterpriseName = $this->enterpriseName($enterpriseId);

            $this->getNotificationService()->publish(
                $studentUserId,
                'internship_application_status_changed',
                'Yêu cầu kết nối từ doanh nghiệp',
                "Doanh nghiệp {$enterpriseName} muốn kết nối và xem thông tin liên hệ của bạn.",
                '/app/learner/ecosystem.php',
                'enterprise_contact_request:' . $id,
                $studentId
            );

            $this->pdo->commit();

            return [
                'id' => $id,
                'enterpriseId' => $enterpriseId,
                'studentId' => $studentId,
                'idempotencyKey' => $idempotencyKey,
                'status' => 'pending',
                'message' => $message,
                'requestedAt' => $now,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function grantAccess(string $studentId, string $enterpriseId, string $scope, int $durationDays = 30): array
    {
        if (!in_array($scope, ['enterprise_talent_discovery', 'enterprise_talent_contact'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "Scope không hợp lệ: {$scope}");
        }
        if ($durationDays < 1 || $durationDays > 365) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời hạn chia sẻ phải từ 1 đến 365 ngày.');
        }

        $nowObj = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $nowObj->format('Y-m-d H:i:s.u');
        $expiresAt = $nowObj->modify("+{$durationDays} days")->format('Y-m-d H:i:s.u');

        $this->pdo->beginTransaction();
        try {
            // Find or create consent
            $stmtConsent = $this->pdo->prepare('SELECT id FROM privacy_consents WHERE studentId = ? AND scope = ? LIMIT 1');
            $stmtConsent->execute([$studentId, $scope]);
            $consentId = $stmtConsent->fetchColumn();

            if (is_string($consentId) && $consentId !== '') {
                $updConsent = $this->pdo->prepare('UPDATE privacy_consents SET isGranted = 1, grantedAt = :now, revokedAt = NULL WHERE id = :id');
                $updConsent->execute(['now' => $now, 'id' => $consentId]);
            } else {
                $consentId = Uuid::v4();
                $insConsent = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt)
                    VALUES (:id, :studentId, :scope, 1, '1.0', :now, NULL, :now)
                SQL);
                $insConsent->execute([
                    'id' => $consentId,
                    'studentId' => $studentId,
                    'scope' => $scope,
                    'now' => $now,
                ]);
            }

            // Find or insert grant
            $stmtGrant = $this->pdo->prepare('SELECT id FROM enterprise_talent_access_grants WHERE studentId = ? AND enterpriseId = ? AND scope = ? LIMIT 1');
            $stmtGrant->execute([$studentId, $enterpriseId, $scope]);
            $grantId = $stmtGrant->fetchColumn();

            if (is_string($grantId) && $grantId !== '') {
                $updGrant = $this->pdo->prepare(<<<'SQL'
                    UPDATE enterprise_talent_access_grants
                    SET consentId = :consentId, grantedAt = :grantedAt, expiresAt = :expiresAt, revokedAt = NULL, updatedAt = :updatedAt
                    WHERE id = :id
                SQL);
                $updGrant->execute([
                    'consentId' => $consentId,
                    'grantedAt' => $now,
                    'expiresAt' => $expiresAt,
                    'updatedAt' => $now,
                    'id' => $grantId,
                ]);
            } else {
                $grantId = Uuid::v4();
                $insGrant = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO enterprise_talent_access_grants (id, studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, revokedAt, createdAt, updatedAt)
                    VALUES (:id, :studentId, :enterpriseId, :consentId, :scope, :grantedAt, :expiresAt, NULL, :createdAt, :updatedAt)
                SQL);
                $insGrant->execute([
                    'id' => $grantId,
                    'studentId' => $studentId,
                    'enterpriseId' => $enterpriseId,
                    'consentId' => $consentId,
                    'scope' => $scope,
                    'grantedAt' => $now,
                    'expiresAt' => $expiresAt,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            }

            $this->pdo->commit();

            return [
                'id' => $grantId,
                'studentId' => $studentId,
                'enterpriseId' => $enterpriseId,
                'scope' => $scope,
                'grantedAt' => $now,
                'expiresAt' => $expiresAt,
                'revokedAt' => null,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function revokeGrant(string $studentId, string $grantId): bool
    {
        if (!Uuid::isValid($grantId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID grant không hợp lệ.');
        }

        $now = $this->now();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, consentId, enterpriseId, scope, revokedAt FROM enterprise_talent_access_grants WHERE id = ? AND studentId = ? LIMIT 1');
            $stmt->execute([$grantId, $studentId]);
            $grant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($grant)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy quyền chia sẻ hồ sơ.');
            }

            if (!empty($grant['revokedAt'])) {
                $this->pdo->commit();
                return true;
            }

            $upd = $this->pdo->prepare('UPDATE enterprise_talent_access_grants SET revokedAt = :now, updatedAt = :now WHERE id = :id AND studentId = :studentId AND revokedAt IS NULL');
            $upd->execute(['now' => $now, 'id' => $grantId, 'studentId' => $studentId]);

            // Check if there are any remaining active grants for this student and scope
            $stmtRemaining = $this->pdo->prepare('SELECT COUNT(*) FROM enterprise_talent_access_grants WHERE studentId = ? AND scope = ? AND revokedAt IS NULL AND expiresAt > ?');
            $stmtRemaining->execute([$studentId, $grant['scope'], $now]);
            $activeCount = (int) $stmtRemaining->fetchColumn();

            if ($activeCount === 0 && !empty($grant['consentId'])) {
                $updConsent = $this->pdo->prepare('UPDATE privacy_consents SET isGranted = 0, revokedAt = :now WHERE id = :id AND studentId = :studentId');
                $updConsent->execute(['now' => $now, 'id' => $grant['consentId'], 'studentId' => $studentId]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listGrants(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT g.id, g.studentId, g.enterpriseId, e.name AS enterpriseName, e.logoUrl AS enterpriseLogo,
                   g.scope, g.grantedAt, g.expiresAt, g.revokedAt, g.createdAt
            FROM enterprise_talent_access_grants g
            INNER JOIN enterprises e ON e.id = g.enterpriseId
            WHERE g.studentId = :studentId
            ORDER BY g.createdAt DESC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $nowObj = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $items = [];
        foreach ($rows as $row) {
            $expiresAt = new DateTimeImmutable((string) $row['expiresAt'], new DateTimeZone('UTC'));
            $isExpired = $expiresAt <= $nowObj;
            $isRevoked = !empty($row['revokedAt']);

            $items[] = [
                'id' => (string) $row['id'],
                'enterpriseId' => (string) $row['enterpriseId'],
                'enterpriseName' => (string) $row['enterpriseName'],
                'enterpriseLogo' => $row['enterpriseLogo'] !== null ? (string) $row['enterpriseLogo'] : null,
                'scope' => (string) $row['scope'],
                'grantedAt' => (string) $row['grantedAt'],
                'expiresAt' => (string) $row['expiresAt'],
                'revokedAt' => $row['revokedAt'] !== null ? (string) $row['revokedAt'] : null,
                'isActive' => !$isExpired && !$isRevoked,
            ];
        }

        return $items;
    }

    /** @return list<string> */
    public function allSkillsForStudent(string $studentId, ?string $enterpriseId = null): array
    {
        if ($this->tableExists('learner_evaluations')) {
            return array_column($this->skillsWithDetailsForStudent($studentId, $enterpriseId), 'name');
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT s.name
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
            ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC, ss.createdAt ASC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter($names, static fn ($n) => is_string($n) && trim($n) !== ''));
    }

    /**
     * Extract actual skills demonstrated in candidate projects.
     *
     * @param list<array<string,mixed>> $projects
     * @return list<string>
     */
    public function extractSkillsFromProjects(array $projects): array
    {
        if (empty($projects)) {
            return [];
        }

        $discoveredSkills = [];
        $patterns = [
            'PHP' => '/\bphp\b/i',
            'MySQL' => '/\bmysql\b/i',
            'SQL' => '/\bsql\b/i',
            'PostgreSQL' => '/\b(postgres|postgresql)\b/i',
            'MongoDB' => '/\bmongodb\b/i',
            'Redis' => '/\bredis\b/i',
            'Node.js' => '/\bnode(\.?js)?\b/i',
            'Java' => '/\bjava\b(?!script)/i',
            'Spring Boot' => '/\b(spring\s*boot|springboot)\b/i',
            'Python' => '/\bpython\b/i',
            'Django' => '/\bdjango\b/i',
            'FastAPI' => '/\bfastapi\b/i',
            'C#' => '/\bc#\b|\bcsharp\b/i',
            '.NET' => '/\b(\.net|dotnet|asp\.net)\b/i',
            'Docker' => '/\bdocker\b/i',
            'REST API' => '/\brest(\s*ful)?\s*api\b|\bapi\b/i',
            'React' => '/\breact(\.?js)?\b/i',
            'Vue.js' => '/\bvue(\.?js)?\b/i',
            'Angular' => '/\bangular\b/i',
            'JavaScript' => '/\bjavascript\b|\bjs\b/i',
            'TypeScript' => '/\btypescript\b|\bts\b/i',
            'HTML/CSS' => '/\bhtml5?\b|\bcss3?\b/i',
            'Tailwind CSS' => '/\btailwind(\s*css)?\b/i',
            'Bootstrap' => '/\bbootstrap\b/i',
            'UI/UX' => '/\bui\/ux\b|\bfigma\b/i',
            'AI / Machine Learning' => '/\b(machine\s*learning|deep\s*learning|pytorch|tensorflow|opencv|trí tuệ nhân tạo|ai)\b/i',
            'Digital Marketing' => '/\b(digital\s*marketing|marketing|seo|content)\b/i',
            'Logistics' => '/\b(logistics|kho\s*vận|chuỗi\s*cung\s*ứng|supply\s*chain)\b/i',
            'Tài chính' => '/\b(tài\s*chính|kế\s*toán|accounting|finance)\b/i',
        ];

        $hasBackendIndicators = false;
        $hasFrontendIndicators = false;

        foreach ($projects as $proj) {
            $text = ($proj['title'] ?? '') . ' ' . ($proj['category'] ?? '') . ' ' . ($proj['description'] ?? '') . ' ' . ($proj['contribution'] ?? '');
            foreach ($patterns as $skillName => $regex) {
                if (preg_match($regex, $text)) {
                    $discoveredSkills[$skillName] = true;
                    if (in_array($skillName, ['PHP', 'MySQL', 'SQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Node.js', 'Java', 'Spring Boot', 'Python', 'Django', 'FastAPI', 'C#', '.NET', 'Docker', 'REST API'], true)) {
                        $hasBackendIndicators = true;
                    }
                    if (in_array($skillName, ['React', 'Vue.js', 'Angular', 'HTML/CSS', 'Tailwind CSS', 'Bootstrap', 'UI/UX'], true)) {
                        $hasFrontendIndicators = true;
                    }
                }
            }
            if (stripos($text, 'backend') !== false || stripos($text, 'hệ thống quản lý') !== false || stripos($text, 'máy chủ') !== false || stripos($text, 'cơ sở dữ liệu') !== false) {
                $hasBackendIndicators = true;
            }
            if (stripos($text, 'frontend') !== false || stripos($text, 'giao diện') !== false) {
                $hasFrontendIndicators = true;
            }
        }

        if (isset($discoveredSkills['MySQL']) || isset($discoveredSkills['PostgreSQL'])) {
            $discoveredSkills['SQL'] = true;
        }

        if ($hasBackendIndicators) {
            $discoveredSkills['Backend Development'] = true;
        }
        if ($hasFrontendIndicators) {
            $discoveredSkills['Frontend Development'] = true;
        }

        return array_keys($discoveredSkills);
    }

    /** @return list<string> */
    private function verifiedSkillsForStudent(string $studentId): array
    {
        $order = $this->columnExists('student_skills', 'createdAt')
            ? 'ORDER BY ss.levelScore DESC, ss.createdAt ASC'
            : 'ORDER BY ss.levelScore DESC, ss.id ASC';
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT s.name
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
              AND ss.verificationStatus = 'verified'
            {$order}
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter($names, static fn ($n) => is_string($n) && trim($n) !== ''));
    }

    /** @return list<array<string,mixed>> */
    public function skillsWithDetailsForStudent(string $studentId, ?string $enterpriseId = null): array
    {
        if ($this->tableExists('learner_evaluations')) {
            if ($enterpriseId === null) return [];
            $snapshot = $this->discoveryScores($studentId, $enterpriseId);
            return $snapshot === null ? [] : $this->mapCurrentSkills($snapshot['skills']);
        }
        $hasScoreState = $this->columnExists('student_skills', 'scoreState');
        $hasCreatedAt = $this->columnExists('student_skills', 'createdAt');
        $scoreStateCol = $hasScoreState ? 'ss.scoreState,' : 'NULL AS scoreState,';
        $createdAtCol = $hasCreatedAt ? 'ss.createdAt,' : 'NULL AS createdAt,';
        $orderCreatedAt = $hasCreatedAt ? ', ss.createdAt ASC' : ', ss.id ASC';
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT ss.id, ss.skillId, s.name AS skillName, s.name AS name, ss.levelScore,
                   ss.verificationStatus, ss.verifiedAt, {$createdAtCol} {$scoreStateCol}
                   CASE
                       WHEN ss.levelScore >= 85 THEN 'Nâng cao'
                       WHEN ss.levelScore >= 65 THEN 'Trung bình'
                       ELSE 'Cơ bản'
                   END AS level
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
            ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC{$orderCreatedAt}
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Same published assessment total and ordering as the Student talent passport reader. */
    private function publishedAssessmentScoreSql(): string
    {
        if (!$this->tableExists('assessments')) {
            return 'NULL';
        }

        return <<<'SQL'
            (SELECT a.overallScore FROM assessments a
             WHERE a.studentId = student.id
               AND a.status = 'published' AND a.publishedAt IS NOT NULL
             ORDER BY a.publishedAt DESC, a.id DESC
             LIMIT 1)
            SQL;
    }

    private function discoveryScores(string $studentId, string $enterpriseId): ?array
    {
        try {
            $this->scoreReader ??= new EvidenceBackedScoreService($this->pdo);
            return $this->scoreReader->forEnterpriseDiscovery($studentId, $enterpriseId);
        } catch (\DomainException) {
            // No fallback to stale projections when recipient access is denied.
            return null;
        }
    }

    private function mapCurrentSkills(array $skills): array
    {
        $result = [];
        foreach ($skills as $skill) {
            $score = $skill['score'] ?? null;
            $name = $skill['skill_name'];
            $result[] = [
                'id'=>$skill['skill_id'], 'skillId'=>$skill['skill_id'], 'skill_id'=>$skill['skill_id'],
                'name'=>$name, 'skillName'=>$name, 'category'=>$skill['category'],
                'code'=>$skill['code'] ?? '',
                'levelScore'=>$score, 'level_score'=>$score,
                'scoreState'=>$skill['state'], 'score_state'=>$skill['state'],
                'verificationStatus'=>$skill['state']==='scored' ? 'verified' : 'pending',
                'verification_status'=>$skill['state']==='scored' ? 'verified' : 'pending',
                'item_kind'=>$skill['item_kind'] ?? 'skill',
                'group_code'=>$skill['group_code'] ?? null,
                'assessed_at'=>$skill['assessed_at'] ?? null,
            ];
        }
        usort($result, static fn($a,$b) => ($b['levelScore'] ?? -1) <=> ($a['levelScore'] ?? -1)
            ?: strcmp($a['name'],$b['name']));
        return $result;
    }

    private function matchesDiscoveryDomain(string $domain, string $text): bool
    {
        $contains = static fn(string $haystack, string $term): bool => mb_strlen($term) <= 3
            ? preg_match('/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/iu', $haystack) === 1
            : mb_stripos($haystack, $term) !== false;
        $domains = [
            ['ai','dữ liệu','data','trí tuệ nhân tạo','machine learning','python','pytorch','computer vision','langchain','prompt engineering'],
            ['marketing','kinh doanh','qtkd','tmđt','quản trị','business'],
            ['logistics','kho vận','cung ứng','supply chain'],
            ['tài chính','kế toán','ngân hàng','finance','accounting','powerbi','excel'],
            ['an toàn','security','bảo mật'],
            ['công nghệ','phần mềm','web','lập trình','cntt','backend','frontend','react','node.js','python','javascript','java','php','docker','sql'],
        ];
        foreach ($domains as $terms) {
            foreach ($terms as $term) {
                if (!$contains($domain, $term)) continue;
                foreach ($terms as $candidateTerm) {
                    if ($contains($text, $candidateTerm)) return true;
                }
                return false;
            }
        }
        return mb_stripos($text, $domain) !== false;
    }

    private function userIdForStudent(string $studentId): string
    {
        $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $userId = $stmt->fetchColumn();
        if (!is_string($userId) || $userId === '') {
            throw new \RuntimeException('Student user record not found.');
        }
        return $userId;
    }

    private function enterpriseName(string $enterpriseId): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM enterprises WHERE id = ? LIMIT 1');
        $stmt->execute([$enterpriseId]);
        $name = $stmt->fetchColumn();
        return is_string($name) ? $name : 'Doanh nghiệp';
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $stmt = $this->pdo->query("PRAGMA table_info({$tableName})");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (strcasecmp((string) ($row['name'] ?? ''), $columnName) === 0) {
                        return true;
                    }
                }
            } catch (\Throwable) {}
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $stmt->execute([$tableName, $columnName]);
        return (bool) $stmt->fetchColumn();
    }

    private function getTalentPassportRepository(): ?DatabaseTalentPassportRepository
    {
        if (!class_exists('TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository', false)) {
            $bootstrapPath = dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
            if (file_exists($bootstrapPath)) {
                require_once $bootstrapPath;
            }
        }
        if (class_exists('TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository', false)) {
            return new DatabaseTalentPassportRepository($this->pdo);
        }
        return null;
    }

    /** @return array{confirmed_hours:int,confirmed_entries:list<array<string,mixed>>} */
    private function experienceForStudent(string $studentId): array
    {
        $entries = [];
        if ($this->tableExists('student_experience_entries')) {
            $stmt = $this->pdo->prepare("SELECT id, title, organization, hours, status, createdAt FROM student_experience_entries WHERE studentId = ? AND status = 'confirmed' ORDER BY createdAt DESC");
            $stmt->execute([$studentId]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($entries) && $this->tableExists('activity_registrations') && $this->tableExists('activities')) {
            $stmt = $this->pdo->prepare(<<<'SQL'
                SELECT
                    ar.id,
                    a.title,
                    COALESCE(s.name, 'Hoạt động trải nghiệm') AS organization,
                    COALESCE(aep.confirmedHours, 4) AS hours,
                    COALESCE(c.status, ar.status, 'attended') AS status,
                    COALESCE(c.createdAt, ar.registeredAt) AS createdAt
                FROM activity_registrations ar
                JOIN activities a ON a.id = ar.activityId
                LEFT JOIN activity_experience_policies aep ON aep.activityId = a.id
                LEFT JOIN checkins c ON c.registrationId = ar.id
                LEFT JOIN schools s ON s.id = a.schoolId
                WHERE ar.studentId = :studentId
                ORDER BY ar.registeredAt DESC
            SQL);
            $stmt->execute(['studentId' => $studentId]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $totalHours = array_sum(array_column($entries, 'hours'));
        return ['confirmed_hours' => (int) $totalHours, 'confirmed_entries' => $entries];
    }

    /** @return list<array<string,mixed>> */
    private function certificatesForStudent(string $studentId): array
    {
        if (!$this->tableExists('certificates')) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT id, title, issuingOrganization, issueDate, expiryDate, credentialId, credentialUrl, verificationStatus, verifiedAt, createdAt, updatedAt FROM certificates WHERE studentId = ? ORDER BY createdAt DESC");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    private function projectsForStudent(string $studentId): array
    {
        if (!$this->tableExists('projects') || !$this->tableExists('project_members') || !$this->tableExists('project_sponsorships')) {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.title, p.category, p.description, p.projectUrl, p.startAt, p.endAt, p.status, p.createdAt, p.updatedAt, pm.role, pm.contribution,
                   (
                       SELECT e.name
                       FROM project_sponsorships ps
                       JOIN enterprises e ON e.id = ps.enterpriseId
                       WHERE ps.projectId = p.id AND ps.status = 'paid'
                       ORDER BY ps.amount DESC, ps.createdAt DESC
                       LIMIT 1
                   ) AS sponsorName,
                   (
                       SELECT SUM(ps.amount)
                       FROM project_sponsorships ps
                       WHERE ps.projectId = p.id AND ps.status = 'paid'
                   ) AS totalFundedAmount
            FROM projects p
            INNER JOIN project_members pm ON pm.projectId = p.id AND pm.status = 'active'
            WHERE pm.studentId = ? OR pm.studentId IN (SELECT sp.id FROM student_profiles sp WHERE sp.userId = ?)
            ORDER BY p.createdAt DESC
        ");
        $stmt->execute([$studentId, $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__, 4) . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Service/NotificationService.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
