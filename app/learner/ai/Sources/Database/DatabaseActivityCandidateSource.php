<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Sources\Database;

use PDO;
use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Data\Database\DatabaseActivityRepository;
use TalentHub\Learner\Ai\Matching\ActivityCandidate;
use TalentHub\Learner\Ai\Matching\JobSkillNormalizer;

final class DatabaseActivityCandidateSource
{
    public function __construct(private readonly PDO $pdo) {}
    /** Reuse approval, school, visibility, registration window, capacity and own-registration rules. */
    public function candidates(string $studentId): array
    {
        $schoolQuery = $this->pdo->prepare('SELECT classroom.schoolId FROM student_profiles student INNER JOIN classes classroom ON classroom.id = student.classId WHERE student.id = ?');
        $schoolQuery->execute([$studentId]);
        $schoolId = $schoolQuery->fetchColumn();
        if (!is_string($schoolId) || $schoolId === '') return [];
        $codes = $this->pdo->query("SELECT code FROM skills WHERE status='active' ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
        $normalizer = new JobSkillNormalizer($codes);
        $rows = (new DatabaseActivityRepository($this->pdo))->discoverForStudent($studentId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $candidates = [];
        foreach ($rows as $row) {
            // Discovery also permits public cross-school events; this module is strictly same-school.
            if (strtolower((string)($row['school_id'] ?? '')) !== strtolower($schoolId)) continue;
            $tags = $normalizer->normalize($row['skills'] ?? [])->codes();
            sort($tags, SORT_STRING);
            $candidates[] = new ActivityCandidate((string)$row['id'], (string)$row['title'], $tags, (string)($row['start_at'] ?? ''));
        }
        usort($candidates, static fn ($a, $b) => strcmp($a->id, $b->id));
        return $candidates;
    }
}
