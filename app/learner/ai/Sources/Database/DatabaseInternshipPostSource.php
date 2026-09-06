<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Matching\JobRequirementsSanitizer;
use TalentHub\Learner\Ai\Matching\JobSkillNormalizer;
use TalentHub\Learner\Ai\Matching\JobSkillNormalization;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use Throwable;

/**
 * Phase 2 AI Job Matching data source.
 *
 * Reads real, currently active and publicly visible internship posts and
 * their canonical enterprise records, then normalizes every post into the
 * shared opportunity candidate evidence contract used by the existing
 * learner AI pipeline:
 *
 * - only posts with status active/published and a future deadline qualify;
 *   closed, cancelled, draft, expired, full or already-applied posts are
 *   dropped, never fabricated;
 * - enterprises must be active and verified/approved; the join is the
 *   canonical source for the enterprise id and display name;
 * - tenant/school visibility reuses the canonical audience mechanism:
 *   public posts are visible to every learner, partner_schools posts only
 *   through an approved school-enterprise partnership that targets the
 *   learner's school;
 * - skillsJson display names are normalized deterministically to canonical
 *   codes loaded from the `skills` registry; unresolvable names are tracked
 *   as unmapped labels and never become synthetic codes;
 * - requirementsJson is sanitized into a structured list (no markup, bounded
 *   length); broken JSON is marked invalid and fails safe;
 * - the canonical detail URL always points at the real internship post
 *   record on the learner ecosystem route;
 * - no personal data is read or emitted: the query touches posts,
 *   enterprises, skills registry and anonymous application counters only.
 */
final class DatabaseInternshipPostSource implements OpportunitySource
{
    private const REQUIRED_INTERNSHIP_COLUMNS = [
        'student_profiles' => ['id', 'classId'],
        'classes' => ['id', 'schoolId'],
        'internship_posts' => [
            'id', 'enterpriseId', 'title', 'field', 'status', 'audience', 'location',
            'workType', 'duration', 'educationLevel', 'description', 'benefits',
            'skillsJson', 'requirementsJson', 'slots', 'deadline', 'createdAt',
        ],
        'enterprises' => ['id', 'name', 'status', 'verificationStatus'],
        'internship_post_target_schools' => ['postId', 'schoolId'],
        'school_enterprise_partnerships' => ['schoolId', 'enterpriseId', 'status'],
        'internship_applications' => ['postId', 'studentId', 'status'],
        'skills' => ['code', 'status'],
    ];

    private const POST_SQL = <<<'SQL'
SELECT
    post.id AS post_id,
    enterprise.id AS enterprise_id,
    enterprise.name AS enterprise_name,
    post.title,
    post.location,
    post.deadline,
    post.slots,
    post.audience,
    post.description,
    post.benefits,
    post.field,
    post.workType AS work_type,
    post.duration,
    post.educationLevel AS education_level,
    post.skillsJson AS skills_json,
    post.requirementsJson AS requirements_json
FROM internship_posts post
INNER JOIN enterprises enterprise ON enterprise.id = post.enterpriseId
WHERE EXISTS (SELECT 1 FROM student_profiles student WHERE student.id = :student_id)
  AND post.status IN ('active', 'published')
  AND enterprise.status = 'active'
  AND enterprise.verificationStatus IN ('verified', 'approved')
  AND (
      post.audience = 'public'
      OR post.audience IS NULL
      OR (
          post.audience = 'partner_schools'
          AND EXISTS (
              SELECT 1
              FROM student_profiles sp
              INNER JOIN classes c ON c.id = sp.classId
              INNER JOIN internship_post_target_schools ipts ON ipts.schoolId = c.schoolId AND ipts.postId = post.id
              INNER JOIN school_enterprise_partnerships sep ON sep.schoolId = c.schoolId AND sep.enterpriseId = enterprise.id
              WHERE sp.id = :student_id_target AND sep.status = 'approved'
          )
      )
  )
ORDER BY post.createdAt DESC, post.id DESC
SQL;

    private readonly DateTimeImmutable $clock;

    private readonly JobRequirementsSanitizer $sanitizer;

    /** @var array<string,list<string>> */
    private array $columnCache = [];

    public function __construct(
        private readonly PDO $pdo,
        ?DateTimeImmutable $clock = null,
    ) {
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $this->sanitizer = new JobRequirementsSanitizer();
    }

    /** @return list<array<string,mixed>> */
    public function forStudent(string $studentId): array
    {
        $studentId = trim($studentId);
        if ($studentId === '' || !$this->hasInternshipContract()) {
            return [];
        }

        try {
            $statement = $this->pdo->prepare(self::POST_SQL);
            $parameters = ['student_id' => $studentId, 'student_id_target' => $studentId];
            $executed = $statement !== false && $statement->execute($parameters);
            if (!$executed) {
                return [];
            }
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }

        $normalizer = $this->skillNormalizer();
        if ($normalizer === null) {
            return [];
        }

        $candidates = [];
        foreach ($rows as $row) {
            $candidate = $this->candidateFromRow($row, $normalizer, $studentId);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /** @return array<string,mixed>|null */
    private function candidateFromRow(array $row, JobSkillNormalizer $normalizer, string $studentId): ?array
    {
        $postId = trim((string) ($row['post_id'] ?? ''));
        $enterpriseId = trim((string) ($row['enterprise_id'] ?? ''));
        $enterpriseName = trim((string) ($row['enterprise_name'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));
        if ($postId === '' || $enterpriseId === '' || $enterpriseName === '' || $title === '') {
            return null;
        }

        $deadline = $this->deadline($row['deadline'] ?? null);
        if ($deadline === null || $deadline <= $this->clock) {
            return null;
        }

        $capacity = (int) ($row['slots'] ?? 0);
        if ($capacity < 1) {
            return null;
        }
        $applicationState = $this->applicationState($postId, $studentId);
        if ($applicationState['already_applied'] || $applicationState['accepted'] >= $capacity) {
            return null;
        }

        $skillsRaw = $row['skills_json'] ?? null;
        $skillsStatus = $this->skillsStatus($skillsRaw);
        $skills = $skillsStatus === 'ok'
            ? $normalizer->normalize($this->decodeSkills($skillsRaw))
            : new JobSkillNormalization([], []);

        $requirementsRaw = $row['requirements_json'] ?? null;
        $requirementsStatus = $this->sanitizer->sourceStatus($requirementsRaw);

        return [
            'opportunity_id' => $postId,
            'catalog_id' => $postId,
            'item_type' => 'internship',
            'enterprise_id' => $enterpriseId,
            'provider_name' => $enterpriseName,
            'title' => $title,
            'location' => trim((string) ($row['location'] ?? '')),
            'deadline_at' => $deadline->format('Y-m-d\\TH:i:s.uP'),
            'status' => 'active',
            'audience' => (string) ($row['audience'] ?? ''),
            'url' => '/app/learner/opportunity.php?type=internship&id=' . rawurlencode($postId),
            'action' => ['type' => 'view_opportunity', 'opportunity_id' => $postId],
            'summary' => $this->sanitizer->sanitizeText($row['description'] ?? null),
            'benefits' => $this->sanitizer->sanitizeText($row['benefits'] ?? null),
            'field' => trim((string) ($row['field'] ?? '')),
            'work_type' => trim((string) ($row['work_type'] ?? '')),
            'duration' => trim((string) ($row['duration'] ?? '')),
            'education_bands' => self::educationBands($row['education_level'] ?? null),
            'required_skills' => array_map(
                static fn (array $entry): array => ['code' => $entry['code'], 'minimum_score' => 0, 'label' => $entry['label']],
                $skills->mapped(),
            ),
            'unmapped_skills' => $skills->unmapped(),
            'requirements' => $this->sanitizer->sanitize($requirementsRaw),
            'requirements_status' => $requirementsStatus,
            'skills_status' => $skillsStatus,
            'availability' => [
                'capacity' => $capacity,
                'enrolled' => $applicationState['accepted'],
                'remaining' => max(0, $capacity - $applicationState['accepted']),
            ],
        ];
    }

    private function hasInternshipContract(): bool
    {
        foreach (self::REQUIRED_INTERNSHIP_COLUMNS as $table => $requiredColumns) {
            if (array_diff($requiredColumns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }
        return true;
    }

    private function skillNormalizer(): ?JobSkillNormalizer
    {
        try {
            $statement = $this->pdo->prepare("SELECT code FROM skills WHERE status = 'active'");
            $executed = $statement !== false && $statement->execute();
            if (!$executed) {
                return null;
            }
            return new JobSkillNormalizer(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{accepted:int,already_applied:bool} */
    private function applicationState(string $postId, string $studentId): array
    {
        $columns = $this->columnsFor('internship_applications');
        if (array_diff(['postId', 'studentId', 'status'], $columns) !== []) {
            return ['accepted' => PHP_INT_MAX, 'already_applied' => true];
        }
        try {
            $statement = $this->pdo->prepare(
                "SELECT SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted, "
                . 'MAX(CASE WHEN studentId = :student THEN 1 ELSE 0 END) AS already_applied '
                . 'FROM internship_applications WHERE postId = :post',
            );
            $statement->execute(['student' => $studentId, 'post' => $postId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'accepted' => (int) ($row['accepted'] ?? 0),
                'already_applied' => (int) ($row['already_applied'] ?? 0) === 1,
            ];
        } catch (Throwable) {
            return ['accepted' => PHP_INT_MAX, 'already_applied' => true];
        }
    }

    private function deadline(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable(trim($raw), new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private function decodeSkills(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            try {
                $decoded = json_decode(trim($raw), true, 64);
            } catch (Throwable) {
                return [];
            }
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function skillsStatus(mixed $raw): string
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return 'missing';
        }
        if (is_array($raw)) {
            return 'ok';
        }
        if (is_string($raw)) {
            try {
                $decoded = json_decode(trim($raw), true, 64);
            } catch (Throwable) {
                return 'invalid';
            }
            return is_array($decoded) ? 'ok' : 'invalid';
        }
        return 'invalid';
    }

    /** @return list<string> */
    private static function educationBands(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $ascii = strtolower(strtr(trim($raw), self::DIACRITIC_ASCII));
        if (str_contains($ascii, 'tat ca bac') || str_contains($ascii, 'moi bac')) {
            return [];
        }
        $bands = [];
        if (str_contains($ascii, 'thcs') || str_contains($ascii, 'cap 2') || str_contains($ascii, 'middle')) {
            $bands[] = 'middle';
        }
        if (str_contains($ascii, 'thpt') || str_contains($ascii, 'cap 3') || str_contains($ascii, 'pho thong') || str_contains($ascii, 'high')) {
            $bands[] = 'high';
        }
        if (str_contains($ascii, 'dai hoc') || str_contains($ascii, 'cao dang') || str_contains($ascii, 'college') || str_contains($ascii, 'university')) {
            $bands[] = 'college';
        }
        return $bands;
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            return [];
        }
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = match ($driver) {
                'sqlite' => "PRAGMA table_info({$table})",
                'mysql' => "SHOW COLUMNS FROM `{$table}`",
                default => null,
            };
            if ($sql === null) {
                return [];
            }
            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }
        return $this->columnCache[$table] = $columns;
    }

    private const DIACRITIC_ASCII = [
        'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];
}
