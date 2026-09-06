<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class SchoolAiProjectCatalogSeeder
{
    private const REQUIRED_COLUMNS = [
        'schools' => ['id', 'name', 'status'],
        'teacher_profiles' => ['id', 'schoolId'],
        'student_profiles' => ['id'],
        'skills' => ['id', 'code', 'status'],
        'student_skills' => ['id', 'studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt', 'createdAt', 'updatedAt'],
        'projects' => ['id', 'schoolId', 'mentorTeacherId', 'title', 'category', 'description', 'fundingGoal', 'projectUrl', 'startAt', 'endAt', 'status', 'createdAt', 'updatedAt'],
        'learner_ai_catalog_items' => ['catalog_id', 'item_type', 'category', 'title', 'summary', 'publish_status', 'deadline_at', 'eligibility_json', 'capacity', 'enrolled_count', 'url', 'action_json', 'school_id', 'tenant_id', 'updated_at', 'provider_name', 'location', 'difficulty', 'required_skills_json', 'learning_outcomes_json', 'education_bands_json'],
    ];

    public function run(PDO $pdo, string $environment, ?DateTimeImmutable $clock = null): void
    {
        if (!in_array($environment, ['local', 'test'], true)) {
            throw new RuntimeException('School AI project catalog seed is allowed only in local/test.');
        }
        $clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $this->assertSchema($pdo);
            $skillIds = $this->assertSchoolsMentorsAndSkills($pdo);
            foreach (SchoolAiProjectCatalogDataset::projects() as $project) {
                $this->upsertProject($pdo, $project, $clock);
                $this->upsertCatalogItem($pdo, $project, $clock);
            }
            $this->seedBtecHeroSkills($pdo, $skillIds, $clock);
            $this->verifyManagedCounts($pdo);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function assertSchema(PDO $pdo): void
    {
        foreach (self::REQUIRED_COLUMNS as $table => $required) {
            $actual = $this->tableColumns($pdo, $table);
            if ($actual === []) {
                throw new RuntimeException("Required table {$table} is missing.");
            }
            foreach ($required as $column) {
                if (!in_array(strtolower($column), $actual, true)) {
                    throw new RuntimeException("Required column {$table}.{$column} is missing.");
                }
            }
        }
    }

    /** @return list<string> */
    private function tableColumns(PDO $pdo, string $table): array
    {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn (array $row): string => strtolower((string) $row['name']), $rows);
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException("Unsupported database driver {$driver}.");
        }
        $statement = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION');
        $statement->execute(['table' => $table]);
        return array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string,string> */
    private function assertSchoolsMentorsAndSkills(PDO $pdo): array
    {
        $schoolQuery = $pdo->prepare('SELECT name, status FROM schools WHERE id = :id');
        $mentorQuery = $pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = :id');
        foreach (SchoolAiProjectCatalogDataset::schools() as $schoolKey => $school) {
            $schoolQuery->execute(['id' => $school['id']]);
            $storedSchool = $schoolQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($storedSchool) || $storedSchool['name'] !== $school['name'] || $storedSchool['status'] !== 'active') {
                throw new RuntimeException("Target school {$schoolKey} is missing, inactive, or has an unexpected name.");
            }
            $mentorQuery->execute(['id' => $school['mentor_id']]);
            if ($mentorQuery->fetchColumn() !== $school['id']) {
                throw new RuntimeException("Mentor {$school['mentor_id']} does not belong to target school {$schoolKey}.");
            }
        }

        $heroQuery = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE id = :id');
        $heroQuery->execute(['id' => SchoolAiProjectCatalogDataset::HERO_STUDENTS['btec']]);
        if ((int) $heroQuery->fetchColumn() !== 1) {
            throw new RuntimeException('BTEC hero student profile is missing.');
        }

        $skillQuery = $pdo->prepare("SELECT id, status FROM skills WHERE code = :code");
        $skillIds = [];
        foreach (SchoolAiProjectCatalogDataset::CANONICAL_SKILL_CODES as $code) {
            $skillQuery->execute(['code' => $code]);
            $row = $skillQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || $row['status'] !== 'active') {
                throw new RuntimeException("Required canonical skill {$code} is missing or inactive.");
            }
            $skillIds[$code] = (string) $row['id'];
        }
        return $skillIds;
    }

    /** @param array<string,mixed> $project */
    private function upsertProject(PDO $pdo, array $project, DateTimeImmutable $clock): void
    {
        $columns = 'id, schoolId, mentorTeacherId, title, category, description, fundingGoal, projectUrl, startAt, endAt, status, createdAt, updatedAt';
        $values = ':id, :school_id, :mentor_id, :title, :category, :description, :funding_goal, :project_url, :start_at, :end_at, :status, :created_at, :updated_at';
        $updates = $this->isSqlite($pdo)
            ? 'schoolId=excluded.schoolId, mentorTeacherId=excluded.mentorTeacherId, title=excluded.title, category=excluded.category, description=excluded.description, fundingGoal=excluded.fundingGoal, projectUrl=excluded.projectUrl, startAt=excluded.startAt, endAt=excluded.endAt, status=excluded.status, updatedAt=excluded.updatedAt'
            : 'schoolId=VALUES(schoolId), mentorTeacherId=VALUES(mentorTeacherId), title=VALUES(title), category=VALUES(category), description=VALUES(description), fundingGoal=VALUES(fundingGoal), projectUrl=VALUES(projectUrl), startAt=VALUES(startAt), endAt=VALUES(endAt), status=VALUES(status), updatedAt=VALUES(updatedAt)';
        $conflict = $this->isSqlite($pdo) ? "ON CONFLICT(id) DO UPDATE SET {$updates}" : "ON DUPLICATE KEY UPDATE {$updates}";
        $statement = $pdo->prepare("INSERT INTO projects ({$columns}) VALUES ({$values}) {$conflict}");
        $timestamp = $clock->format('Y-m-d H:i:s');
        $statement->execute([
            'id' => $project['id'],
            'school_id' => $project['school_id'],
            'mentor_id' => $project['mentor_id'],
            'title' => $project['title'],
            'category' => $project['category'],
            'description' => $project['description'],
            'funding_goal' => $project['funding_goal'],
            'project_url' => $project['project_url'],
            'start_at' => $project['start_at'],
            'end_at' => $project['deadline_at'],
            'status' => $project['status'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /** @param array<string,mixed> $project */
    private function upsertCatalogItem(PDO $pdo, array $project, DateTimeImmutable $clock): void
    {
        $columns = 'catalog_id, item_type, category, title, summary, publish_status, deadline_at, eligibility_json, capacity, enrolled_count, url, action_json, school_id, tenant_id, updated_at, provider_name, location, difficulty, required_skills_json, learning_outcomes_json, education_bands_json';
        $parameters = array_map(static fn (string $column): string => ':' . $column, explode(', ', $columns));
        $mutable = array_values(array_filter(explode(', ', $columns), static fn (string $column): bool => $column !== 'catalog_id'));
        $updates = $this->isSqlite($pdo)
            ? implode(', ', array_map(static fn (string $column): string => "{$column}=excluded.{$column}", $mutable))
            : implode(', ', array_map(static fn (string $column): string => "{$column}=VALUES({$column})", $mutable));
        $conflict = $this->isSqlite($pdo) ? "ON CONFLICT(catalog_id) DO UPDATE SET {$updates}" : "ON DUPLICATE KEY UPDATE {$updates}";
        $statement = $pdo->prepare("INSERT INTO learner_ai_catalog_items ({$columns}) VALUES (" . implode(', ', $parameters) . ") {$conflict}");
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        $statement->execute([
            'catalog_id' => $project['id'],
            'item_type' => 'project',
            'category' => $project['ai_category'],
            'title' => $project['title'],
            'summary' => $project['summary'],
            'publish_status' => $project['publish_status'],
            'deadline_at' => $project['deadline_at'],
            'eligibility_json' => '{}',
            'capacity' => $project['capacity'],
            'enrolled_count' => $project['enrolled_count'],
            'url' => $project['canonical_url'],
            'action_json' => json_encode(['type' => 'view_project', 'project_id' => $project['id']], $jsonFlags),
            'school_id' => $project['school_id'],
            'tenant_id' => $project['school_id'],
            'updated_at' => $clock->format('Y-m-d H:i:s'),
            'provider_name' => $project['provider_name'],
            'location' => $project['location'],
            'difficulty' => $project['difficulty'],
            'required_skills_json' => json_encode($project['required_skills'], $jsonFlags),
            'learning_outcomes_json' => json_encode($project['learning_outcomes'], $jsonFlags),
            'education_bands_json' => json_encode($project['education_bands'], $jsonFlags),
        ]);
    }

    /** @param array<string,string> $skillIds */
    private function seedBtecHeroSkills(PDO $pdo, array $skillIds, DateTimeImmutable $clock): void
    {
        $existing = $pdo->prepare('SELECT COUNT(*) FROM student_skills WHERE studentId = :student_id AND skillId = :skill_id');
        $sqliteSql = 'INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt) VALUES (:id, :student_id, :skill_id, :score, :source_type, :verification, :verified_at, :created_at, :updated_at) ON CONFLICT DO NOTHING';
        $mysqlSql = 'INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt) VALUES (:id, :student_id, :skill_id, :score, :source_type, :verification, :verified_at, :created_at, :updated_at) ON DUPLICATE KEY UPDATE id=id';
        $insert = $pdo->prepare($this->isSqlite($pdo) ? $sqliteSql : $mysqlSql);
        $timestamp = $clock->format('Y-m-d H:i:s');
        $index = 1;
        foreach (SchoolAiProjectCatalogDataset::btecHeroSkills() as $code => $score) {
            $existing->execute(['student_id' => SchoolAiProjectCatalogDataset::HERO_STUDENTS['btec'], 'skill_id' => $skillIds[$code]]);
            if ((int) $existing->fetchColumn() === 0) {
                $insert->execute([
                    'id' => sprintf('54000000-0000-4000-8000-%012d', $index),
                    'student_id' => SchoolAiProjectCatalogDataset::HERO_STUDENTS['btec'],
                    'skill_id' => $skillIds[$code],
                    'score' => $score,
                    'source_type' => 'assessment',
                    'verification' => 'verified',
                    'verified_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
            $index++;
        }
    }

    private function verifyManagedCounts(PDO $pdo): void
    {
        $projectsBySchool = [];
        foreach (SchoolAiProjectCatalogDataset::projects() as $project) {
            $projectsBySchool[$project['school_key']][] = $project['id'];
        }
        foreach ($projectsBySchool as $schoolKey => $ids) {
            $schoolId = SchoolAiProjectCatalogDataset::schools()[$schoolKey]['id'];
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $projectQuery = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id IN ({$placeholders}) AND schoolId = ? AND status = 'in_progress'");
            $projectQuery->execute([...$ids, $schoolId]);
            if ((int) $projectQuery->fetchColumn() !== 8) {
                throw new RuntimeException("Managed project verification failed for {$schoolKey}.");
            }
            $catalogQuery = $pdo->prepare("SELECT COUNT(*) FROM learner_ai_catalog_items WHERE catalog_id IN ({$placeholders}) AND school_id = ? AND tenant_id = ? AND publish_status = 'published'");
            $catalogQuery->execute([...$ids, $schoolId, $schoolId]);
            if ((int) $catalogQuery->fetchColumn() !== 8) {
                throw new RuntimeException("Managed AI catalog verification failed for {$schoolKey}.");
            }
        }
    }

    private function isSqlite(PDO $pdo): bool
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }
}
