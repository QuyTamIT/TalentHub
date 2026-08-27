<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\LearnerAiExtendedSource;
use Throwable;

/** Reads the canonical, publishable opportunity catalog on every snapshot. */
final class DatabaseCatalogSource implements LearnerAiExtendedSource
{
    private const TYPES = ['group', 'community', 'workshop', 'project', 'contest', 'skill_resource'];
    private readonly DateTimeImmutable $clock;

    public function __construct(private readonly PDO $pdo, ?DateTimeImmutable $clock = null)
    {
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    public function sourceType(): string { return 'catalog'; }
    public function schemaVersion(): string { return 'catalog-1.0.0'; }
    public function consentScope(): string { return 'activity'; }
    public function refreshTrigger(): string { return 'catalog_changed'; }
    public function allowedFields(): array
    {
        return ['action', 'availability', 'catalog_id', 'category', 'deadline_at', 'eligibility', 'item_type', 'publish_status', 'summary', 'title', 'updated_at', 'url'];
    }

    /** @return list<array<string,mixed>> */
    public function readForStudent(string $studentId): array
    {
        $student = $this->student(trim($studentId));
        if ($student === null) return [];
        $items = [];
        if ($this->tableExists('learner_ai_catalog_items')) {
            try {
                $rows = $this->pdo->query('SELECT catalog_id, item_type, category, title, summary, publish_status, deadline_at, eligibility_json, capacity, enrolled_count, url, action_json, school_id, tenant_id, updated_at FROM learner_ai_catalog_items')?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) { $rows = []; }
            foreach ($rows as $row) {
                if (!$this->eligible($row, $student)) continue;
                $id = trim((string) ($row['catalog_id'] ?? ''));
                $updatedAt = $this->timestamp($row['updated_at'] ?? null);
                if ($id === '' || $updatedAt === null) continue;
                $deadline = $this->timestamp($row['deadline_at'] ?? null);
                $eligibility = $this->json($row['eligibility_json'] ?? null);
                $action = $this->json($row['action_json'] ?? null);
                $items[] = [
                    'source_id' => $id, 'catalog_id' => $id, 'item_type' => (string) $row['item_type'],
                    'category' => (string) ($row['category'] ?? ''), 'title' => (string) ($row['title'] ?? ''),
                    'summary' => (string) ($row['summary'] ?? ''), 'publish_status' => 'published', 'deadline_at' => $deadline,
                    'eligibility' => $eligibility, 'availability' => ['capacity' => (int) $row['capacity'], 'enrolled' => (int) $row['enrolled_count'], 'remaining' => max(0, (int) $row['capacity'] - (int) $row['enrolled_count'])],
                    'url' => (string) ($row['url'] ?? ''), 'action' => $action, 'updated_at' => $updatedAt, 'observed_at' => $updatedAt,
                ];
            }
        }
        array_push($items, ...$this->schoolProjects($student));
        usort($items, static fn (array $a, array $b): int => [$a['deadline_at'] ?? '9999', $a['catalog_id']] <=> [$b['deadline_at'] ?? '9999', $b['catalog_id']]);
        return $items;
    }

    /** @param array<string,string> $student @return list<array<string,mixed>> */
    private function schoolProjects(array $student): array
    {
        if ($student['school_id'] === '' || !$this->tableExists('projects')) return [];
        $columns = $this->columnsFor('projects');
        foreach (['id', 'schoolId', 'title', 'status', 'updatedAt'] as $required) if (!in_array($required, $columns, true)) return [];
        $select = static fn (string $column, string $fallback): string => in_array($column, $columns, true) ? $column : $fallback . ' AS ' . $column;
        try {
            $statement = $this->pdo->prepare('SELECT id, schoolId, title, status, updatedAt, '
                . $select('category', "'project'") . ', ' . $select('description', "''") . ', '
                . $select('projectUrl', "''") . ', ' . $select('endAt', 'NULL')
                . " FROM projects WHERE schoolId = :schoolId AND status = 'in_progress' ORDER BY updatedAt, id");
            $statement->execute(['schoolId' => $student['school_id']]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $items = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $updatedAt = $this->timestamp($row['updatedAt'] ?? null);
            $deadline = $this->timestamp($row['endAt'] ?? null);
            if ($id === '' || $updatedAt === null || ($deadline !== null && $deadline <= $this->clock->format('Y-m-d\\TH:i:s.uP'))) continue;
            $url = trim((string) ($row['projectUrl'] ?? ''));
            if ($url === '') $url = '/app/learner/projects.php?id=' . rawurlencode($id);
            $items[] = [
                'source_id' => $id, 'catalog_id' => $id, 'item_type' => 'project',
                'category' => (string) ($row['category'] ?? 'project'), 'title' => (string) $row['title'],
                'summary' => (string) ($row['description'] ?? ''), 'publish_status' => 'published',
                'deadline_at' => $deadline, 'eligibility' => ['school_ids' => [$student['school_id']]],
                'availability' => ['capacity' => null, 'enrolled' => null, 'remaining' => null],
                'url' => $url, 'action' => ['type' => 'view_project', 'project_id' => $id],
                'updated_at' => $updatedAt, 'observed_at' => $updatedAt,
            ];
        }
        return $items;
    }

    public function changedSince(string $studentId, ?string $versionOrTimestamp): bool
    {
        foreach ($this->readForStudent($studentId) as $item) if ($versionOrTimestamp === null || ($item['updated_at'] ?? '') > $versionOrTimestamp) return true;
        return false;
    }

    /** @return array<string,string>|null */
    private function student(string $studentId): ?array
    {
        if ($studentId === '' || !$this->tableExists('student_profiles')) return null;
        try {
            $columns = $this->columnsFor('student_profiles');
            $class = in_array('classId', $columns, true) ? 'classId' : 'NULL';
            $tenant = in_array('tenantId', $columns, true) ? 'tenantId' : 'NULL';
            $grade = in_array('gradeLevel', $columns, true) ? 'gradeLevel' : 'NULL';
            $student = $this->pdo->prepare("SELECT id, {$class} AS classId, {$tenant} AS tenantId, {$grade} AS gradeLevel FROM student_profiles WHERE id = :id");
            $student->execute(['id' => $studentId]); $row = $student->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            $schoolId = null;
            $classGrade = null;
            if (($row['classId'] ?? '') !== '' && $this->tableExists('classes')) { $classColumns=$this->columnsFor('classes'); $gradeField=in_array('gradeLevel',$classColumns,true)?'gradeLevel':(in_array('grade',$classColumns,true)?'grade':'NULL'); $q = $this->pdo->prepare("SELECT schoolId, {$gradeField} AS gradeLevel FROM classes WHERE id = :id"); $q->execute(['id' => $row['classId']]); $classRow=$q->fetch(PDO::FETCH_ASSOC) ?: []; $schoolId = $classRow['schoolId'] ?? null; $classGrade=$classRow['gradeLevel'] ?? null; }
            $effectiveTenant = trim((string) ($row['tenantId'] ?? ''));
            if ($effectiveTenant === '') $effectiveTenant = trim((string) $schoolId);
            return ['id' => (string) $row['id'], 'class_id' => (string) ($row['classId'] ?? ''), 'tenant_id' => $effectiveTenant, 'grade_level' => (string) ($classGrade ?? $row['gradeLevel'] ?? ''), 'school_id' => (string) $schoolId];
        } catch (Throwable) { return null; }
    }

    /** @param array<string,mixed> $item @param array<string,string> $student */
    private function eligible(array $item, array $student): bool
    {
        if (($item['publish_status'] ?? null) !== 'published' || !in_array((string) ($item['item_type'] ?? ''), self::TYPES, true)) return false;
        $deadline = $this->timestamp($item['deadline_at'] ?? null);
        if ($deadline !== null && $deadline <= $this->clock->format('Y-m-d\\TH:i:s.uP')) return false;
        if ((int) ($item['capacity'] ?? 0) <= (int) ($item['enrolled_count'] ?? 0)) return false;
        if (($item['school_id'] ?? null) !== null && (string) $item['school_id'] !== '' && !hash_equals((string) $item['school_id'], $student['school_id'])) return false;
        if (($item['tenant_id'] ?? null) !== null && (string) $item['tenant_id'] !== '' && !hash_equals((string) $item['tenant_id'], $student['tenant_id'])) return false;
        $rawRules = $item['eligibility_json'] ?? null;
        $rules = $this->json($rawRules);
        if (!is_string($rawRules) || trim($rawRules) === '' || $rules === null) return false;
        foreach (array_keys($rules) as $rule) if (!in_array($rule, ['student_ids', 'class_ids', 'grade_levels'], true)) return false;
        foreach (['student_ids' => 'id', 'class_ids' => 'class_id', 'grade_levels' => 'grade_level'] as $field => $studentField) {
            if (isset($rules[$field]) && is_array($rules[$field]) && !in_array($student[$studentField] ?? '', array_map('strval', $rules[$field]), true)) return false;
        }
        return true;
    }

    /** @return array<string,mixed> */
    /** @return array<string,mixed>|null */
    private function json(mixed $value): ?array { try { $decoded = is_string($value) ? json_decode($value, true, 64, JSON_THROW_ON_ERROR) : null; return is_array($decoded) ? $decoded : null; } catch (Throwable) { return null; } }
    private function timestamp(mixed $value): ?string { if (!is_string($value) || trim($value) === '') return null; try { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.uP'); } catch (Throwable) { return null; } }
    private function tableExists(string $table): bool { try { if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $q = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name"); $q->execute(['name' => $table]); return $q->fetchColumn() !== false; } $q = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:name'); $q->execute(['name' => $table]); return $q->fetchColumn() !== false; } catch (Throwable) { return false; } }
    /** @return list<string> */
    private function columnsFor(string $table): array { try { $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "PRAGMA table_info({$table})" : "SHOW COLUMNS FROM `{$table}`"; $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: []; return array_values(array_filter(array_map(static fn (array $row): mixed => $row['name'] ?? $row['Field'] ?? null, $rows), 'is_string')); } catch (Throwable) { return []; } }
}
