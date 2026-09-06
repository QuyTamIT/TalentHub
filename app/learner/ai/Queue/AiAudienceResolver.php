<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

use PDO;
use Throwable;

/** Resolves learner IDs at a mutation boundary without loading learner PII. */
final class AiAudienceResolver
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<string> */
    public function schoolStudents(string $schoolId): array
    {
        $schoolId = trim($schoolId);
        if ($schoolId === '' || !$this->tableExists('student_profiles') || !$this->tableExists('classes')) return [];
        try {
            $statement = $this->pdo->prepare(
                'SELECT sp.id FROM student_profiles sp INNER JOIN classes c ON c.id = sp.classId WHERE c.schoolId = :schoolId ORDER BY sp.id'
            );
            $statement->execute(['schoolId' => $schoolId]);
            return $this->ids($statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    public function allStudents(): array
    {
        if (!$this->tableExists('student_profiles')) return [];
        try {
            $rows = $this->pdo->query('SELECT id FROM student_profiles ORDER BY id')?->fetchAll(PDO::FETCH_COLUMN) ?: [];
            return $this->ids($rows);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    public function internshipStudents(string $postId): array
    {
        $postId = trim($postId);
        if ($postId === '' || !$this->tableExists('internship_posts')) return [];
        try {
            $columns = $this->columnsFor('internship_posts');
            $audienceColumn = in_array('audience', $columns, true) ? 'audience' : "'public' AS audience";
            $statement = $this->pdo->prepare("SELECT enterpriseId, {$audienceColumn} FROM internship_posts WHERE id = :id LIMIT 1");
            $statement->execute(['id' => $postId]);
            $post = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($post)) return [];
            $audience = strtolower(trim((string) ($post['audience'] ?? 'public')));
            if ($audience === '' || $audience === 'public') return $this->allStudents();
            if ($audience !== 'partner_schools'
                || !$this->tableExists('internship_post_target_schools')
                || !$this->tableExists('school_enterprise_partnerships')) return [];
            $query = $this->pdo->prepare(
                "SELECT DISTINCT sp.id
                 FROM student_profiles sp
                 INNER JOIN classes c ON c.id = sp.classId
                 INNER JOIN internship_post_target_schools target ON target.schoolId = c.schoolId AND target.postId = :postId
                 INNER JOIN school_enterprise_partnerships partnership ON partnership.schoolId = c.schoolId
                    AND partnership.enterpriseId = :enterpriseId AND partnership.status = 'approved'
                 ORDER BY sp.id"
            );
            $query->execute(['postId' => $postId, 'enterpriseId' => (string) $post['enterpriseId']]);
            return $this->ids($query->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<mixed> $values @return list<string> */
    private function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') continue;
            $ids[trim($value)] = true;
        }
        $result = array_keys($ids);
        sort($result, SORT_STRING);
        return $result;
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $statement = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
                $statement->execute(['name' => $table]);
                return $statement->fetchColumn() !== false;
            }
            $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:name');
            $statement->execute(['name' => $table]);
            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        try {
            $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "PRAGMA table_info({$table})"
                : "SHOW COLUMNS FROM `{$table}`";
            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_values(array_filter(array_map(
                static fn (array $row): mixed => $row['name'] ?? $row['Field'] ?? null,
                $rows,
            ), 'is_string'));
        } catch (Throwable) {
            return [];
        }
    }
}
