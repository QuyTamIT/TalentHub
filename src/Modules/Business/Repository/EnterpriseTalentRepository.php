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
use TalentHub\Support\Uuid;
use Throwable;

final class EnterpriseTalentRepository
{
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
     * @return array{id:string,title:string,description:string,required_skills:list<string>}
     */
    public function matchingJob(string $enterpriseId, string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, enterpriseId, title, description, skillsJson, requirementsJson, status, deadline FROM internship_posts WHERE id = ? AND enterpriseId = ? LIMIT 1');
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
        if (!empty($row['skillsJson'])) {
            try {
                $decoded = json_decode((string) $row['skillsJson'], true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $s) {
                        if (is_string($s) && trim($s) !== '') {
                            $skills[] = trim($s);
                        }
                    }
                }
            } catch (\Throwable) {}
        }
        if (!empty($row['requirementsJson'])) {
            try {
                $decoded = json_decode((string) $row['requirementsJson'], true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $s) {
                        if (is_string($s) && trim($s) !== '') {
                            $skills[] = trim($s);
                        }
                    }
                }
            } catch (\Throwable) {}
        }
        return [
            'id' => (string) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'required_skills' => array_values(array_unique($skills)),
        ];
    }

    /**
     * Return the minimum, consented candidate projection used by enterprise matching.
     * The query intentionally exposes no protected traits or hidden profile fields.
     *
     * @param list<string> $requiredSkills
     * @return list<array{student_id:string,display_name:string,skills:list<array<string,mixed>>}>
     */
    public function matchCandidates(string $enterpriseId, array $requiredSkills = []): array
    {
        $now = $this->now();
        $sql = <<<'SQL'
            SELECT sp.id AS student_id, u.fullName AS display_name,
                   sk.id AS skill_id, sk.name AS skill_name, ss.levelScore AS level_score
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId AND u.status = 'active'
            INNER JOIN enterprise_talent_access_grants grant_row
              ON grant_row.studentId = sp.id
             AND grant_row.enterpriseId = :enterpriseId
             AND grant_row.scope = 'enterprise_talent_discovery'
             AND grant_row.revokedAt IS NULL
             AND grant_row.expiresAt > :now
            INNER JOIN privacy_consents consent
              ON consent.id = grant_row.consentId
             AND consent.studentId = sp.id
             AND consent.scope = 'enterprise_talent_discovery'
             AND consent.isGranted = 1
             AND consent.revokedAt IS NULL
            LEFT JOIN student_skills ss
              ON ss.studentId = sp.id AND ss.verificationStatus = 'verified'
            LEFT JOIN skills sk ON sk.id = ss.skillId AND sk.status = 'active'
            ORDER BY sp.id ASC, sk.name ASC, sk.id ASC
        SQL;
        // A partnership is an additional tenant boundary when schools are present.
        if ($this->tableExists('school_enterprise_partnerships') && $this->tableExists('classes')) {
            $sql = str_replace(
                '            ORDER BY sp.id ASC, sk.name ASC, sk.id ASC',
                "            WHERE sp.classId IS NULL OR EXISTS (SELECT 1 FROM classes cl INNER JOIN school_enterprise_partnerships sep ON sep.schoolId = cl.schoolId WHERE cl.id = sp.classId AND sep.enterpriseId = :partnershipEnterprise AND sep.status = 'approved')\n            ORDER BY sp.id ASC, sk.name ASC, sk.id ASC",
                $sql
            );
        }
        $stmt = $this->pdo->prepare($sql);
        $params = ['enterpriseId' => $enterpriseId, 'now' => $now];
        if (str_contains($sql, ':partnershipEnterprise')) {
            $params['partnershipEnterprise'] = $enterpriseId;
        }
        $stmt->execute($params);

        $candidates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (string) ($row['student_id'] ?? '');
            if ($studentId === '') {
                continue;
            }
            if (!isset($candidates[$studentId])) {
                $candidates[$studentId] = [
                    'student_id' => $studentId,
                    'display_name' => (string) ($row['display_name'] ?? 'Ứng viên'),
                    'skills' => [],
                ];
            }
            if (!empty($row['skill_id']) && !empty($row['skill_name'])) {
                $name = (string) $row['skill_name'];
                $candidates[$studentId]['skills'][] = [
                    'skill_id' => (string) $row['skill_id'],
                    'name' => $name,
                    'level_score' => (float) ($row['level_score'] ?? 0),
                ];
            }
        }

        if ($requiredSkills !== []) {
            $required = [];
            foreach ($requiredSkills as $skill) {
                $normalized = mb_strtolower(trim((string) $skill));
                if ($normalized !== '') {
                    $required[$normalized] = true;
                }
            }
            if ($required !== []) {
                $filtered = [];
                foreach ($candidates as $candidate) {
                    foreach ($candidate['skills'] as $skill) {
                        $name = mb_strtolower(trim((string) ($skill['name'] ?? '')));
                        if (isset($required[$name])) {
                            $filtered[] = $candidate;
                            continue 2;
                        }
                    }
                }
                return $filtered;
            }
        }

        return array_values($candidates);
    }

    /** Backwards-compatible descriptive alias for matching callers. */
    public function findMatchCandidates(string $enterpriseId, array $requiredSkills = []): array
    {
        return $this->matchCandidates($enterpriseId, $requiredSkills);
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
            WHERE em.userId = :userId
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

    /**
     * @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listTalents(string $enterpriseId, array $filters = []): array
    {
        $now = $this->now();
        $hasPartnership = $this->tableExists('school_enterprise_partnerships');

        $where = [
            "u.status = 'active'",
        ];

        $params = [];

        if ($hasPartnership) {
            $where[] = '(s.id IS NULL OR EXISTS (SELECT 1 FROM school_enterprise_partnerships sep WHERE sep.schoolId = s.id AND sep.enterpriseId = :enterpriseIdPartnership AND sep.status = \'approved\'))';
            $params['enterpriseIdPartnership'] = $enterpriseId;
        }

        if (isset($filters['school']) && is_string($filters['school']) && trim($filters['school']) !== '') {
            $schoolFilter = trim($filters['school']);
            if (Uuid::isValid($schoolFilter)) {
                $where[] = 's.id = :schoolIdFilter';
                $params['schoolIdFilter'] = $schoolFilter;
            } else {
                $where[] = 's.name LIKE :schoolNameFilter';
                $params['schoolNameFilter'] = '%' . $schoolFilter . '%';
            }
        }

        if (isset($filters['search']) && is_string($filters['search']) && trim($filters['search']) !== '') {
            $search = '%' . trim($filters['search']) . '%';
            $where[] = '(u.fullName LIKE :search1 OR spd.headline LIKE :search2 OR spd.bio LIKE :search3 OR s.name LIKE :search4 OR c.name LIKE :search5)';
            $params['search1'] = $search;
            $params['search2'] = $search;
            $params['search3'] = $search;
            $params['search4'] = $search;
            $params['search5'] = $search;
        }

        $whereClause = implode(' AND ', $where);

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
                COALESCE(
                    student.talentScore,
                    (SELECT ROUND(AVG(sa.overallScore) * 10, 0) FROM assessments sa WHERE sa.studentId = student.id AND sa.overallScore IS NOT NULL),
                    (SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = student.id AND ss.levelScore > 0),
                    85
                ) AS talentScore,
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
                ) AS hasPendingContactRequest
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
            default => 'ORDER BY talentScore DESC, verifiedSkillCount DESC, student.createdAt DESC',
        };

        $limitClause = '';
        if (isset($filters['limit']) && is_numeric($filters['limit']) && (int) $filters['limit'] > 0) {
            $limit = (int) $filters['limit'];
            $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
            $limitClause = " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare("{$sql} {$orderClause}{$limitClause}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Post-process skills filter and populate verifiedSkills list
        $filterSkills = [];
        if (isset($filters['skills'])) {
            $rawSkills = is_array($filters['skills']) ? $filters['skills'] : explode(',', (string) $filters['skills']);
            $filterSkills = array_values(array_filter(array_map('trim', $rawSkills)));
        }

        $items = [];
        foreach ($rows as $row) {
            $studentId = (string) $row['studentId'];
            $skills = $this->allSkillsForStudent($studentId);

            if ($filterSkills !== []) {
                $hasAllSkills = true;
                $lowerSkills = array_map('mb_strtolower', $skills);
                foreach ($filterSkills as $requiredSkill) {
                    if (!in_array(mb_strtolower($requiredSkill), $lowerSkills, true)) {
                        $hasAllSkills = false;
                        break;
                    }
                }
                if (!$hasAllSkills) {
                    continue;
                }
            }

            $score = (int) $row['talentScore'];
            $items[] = [
                'studentId' => $studentId,
                'userId' => (string) ($row['userId'] ?? ''),
                'displayName' => (string) ($row['displayName'] ?? 'Ứng viên'),
                'schoolName' => (string) ($row['schoolName'] ?? ''),
                'className' => (string) ($row['className'] ?? ''),
                'studyStatus' => (string) ($row['studyStatus'] ?? ''),
                'location' => (string) ($row['location'] ?? ''),
                'headline' => (string) ($row['headline'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'avatarUrl' => $row['avatarUrl'] !== null ? (string) $row['avatarUrl'] : null,
                'talentScore' => min(100, max(60, $score)),
                'match_score' => min(100, max(60, $score)),
                'skillCount' => (int) $row['skillCount'],
                'verifiedSkillCount' => (int) $row['verifiedSkillCount'],
                'verifiedSkills' => $skills,
                'skills' => $skills,
                'contactAllowed' => (bool) ((int) ($row['contactAllowed'] ?? 0) === 1),
                'hasPendingContactRequest' => (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1),
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    public function getTalentDetail(string $enterpriseId, string $studentId): ?array
    {
        $now = $this->now();
        $hasPartnership = $this->tableExists('school_enterprise_partnerships');

        $where = [
            '(student.id = :studentId OR u.id = :studentIdAlt)',
            "u.status = 'active'",
        ];

        $params = [
            'studentId' => $studentId,
            'studentIdAlt' => $studentId,
        ];

        if ($hasPartnership) {
            $where[] = '(s.id IS NULL OR EXISTS (SELECT 1 FROM school_enterprise_partnerships sep WHERE sep.schoolId = s.id AND sep.enterpriseId = :enterpriseIdPartnership AND sep.status = \'approved\'))';
            $params['enterpriseIdPartnership'] = $enterpriseId;
        }

        $whereClause = implode(' AND ', $where);

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
                COALESCE(
                    student.talentScore,
                    (SELECT ROUND(AVG(sa.overallScore) * 10, 0) FROM assessments sa WHERE sa.studentId = student.id AND sa.overallScore IS NOT NULL),
                    (SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = student.id AND ss.levelScore > 0),
                    85
                ) AS talentScore,
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
        $contactAllowed = (bool) ((int) ($row['contactAllowed'] ?? 0) === 1);
        $hasPendingContact = (bool) ((int) ($row['hasPendingContactRequest'] ?? 0) === 1);

        // Load aggregate details via DatabaseTalentPassportRepository or robust fallback queries
        $passportRepo = $this->getTalentPassportRepository();
        $aggregate = $passportRepo !== null
            ? $passportRepo->sharedSectionsForStudent($realStudentId, ['skills', 'experience', 'certificates', 'projects'])
            : [];

        $skills = !empty($aggregate['skills']) ? $aggregate['skills'] : $this->skillsWithDetailsForStudent($realStudentId);
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
            'talent_score' => (int) ($row['talentScore'] ?? 85),
            'contactAllowed' => $contactAllowed,
            'hasPendingContactRequest' => $hasPendingContact,
            'skills' => $skills,
            'experience' => $experience,
            'certificates' => $certificates,
            'projects' => $projects,
        ];

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
    public function allSkillsForStudent(string $studentId): array
    {
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

    /** @return list<string> */
    private function verifiedSkillsForStudent(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT s.name
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
              AND ss.verificationStatus = 'verified'
            ORDER BY ss.levelScore DESC, ss.createdAt ASC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter($names, static fn ($n) => is_string($n) && trim($n) !== ''));
    }

    /** @return list<array<string,mixed>> */
    private function skillsWithDetailsForStudent(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT ss.id, s.name AS skillName, s.name AS name, ss.levelScore,
                   ss.verificationStatus, ss.verifiedAt, ss.createdAt,
                   CASE 
                       WHEN ss.levelScore >= 85 THEN 'Nâng cao'
                       WHEN ss.levelScore >= 65 THEN 'Trung bình'
                       ELSE 'Cơ bản'
                   END AS level
            FROM student_skills ss
            INNER JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :studentId
            ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC, ss.createdAt ASC
        SQL);
        $stmt->execute(['studentId' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        if (!$this->tableExists('projects') || !$this->tableExists('project_members')) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT p.id, p.title, p.category, p.description, p.projectUrl, p.startAt, p.endAt, p.status, p.createdAt, p.updatedAt, pm.role, pm.contribution FROM projects p INNER JOIN project_members pm ON pm.projectId = p.id WHERE pm.studentId = ? ORDER BY p.createdAt DESC");
        $stmt->execute([$studentId]);
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
