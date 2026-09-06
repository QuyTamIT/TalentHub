<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use PDO;
use Throwable;

/**
 * Reads career role benchmarks from the three benchmark tables.
 *
 * Guarantees:
 * - only active roles with a non-empty code/title/category are returned;
 * - skill requirements are joined against the canonical skills registry and
 *   rows referencing unknown or inactive skills are dropped, never repaired;
 * - weights must be positive; per role both weight groups are normalized to
 *   sum 100 so a drifted database cannot skew the 40/35/25 composition;
 * - assessment families and dimensions are validated against the same
 *   allow-list the benchmark seed uses;
 * - missing tables or columns yield an explicit insufficient_data state —
 *   the repository never fabricates roles, skills, targets or evidence.
 */
final class CareerRoleBenchmarkRepository
{
    private const ASSESSMENT_FAMILIES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

    private const FAMILY_DIMENSIONS = [
        'holland' => ['R', 'I', 'A', 'S', 'E', 'C'],
        'mbti' => ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'],
        'disc' => ['D', 'I', 'S', 'C'],
        'multiple_intelligence' => ['LOGI', 'LING', 'SPAT', 'MUSIC', 'BODY', 'INTER', 'INTRA', 'NAT'],
    ];

    private const REQUIRED_COLUMNS = [
        'career_role_benchmarks' => ['id', 'code', 'title', 'category', 'isActive'],
        'career_role_skill_requirements' => ['roleId', 'skillId', 'minimumScore', 'weight', 'isRequired'],
        'career_role_assessment_signals' => ['roleId', 'assessmentFamily', 'dimensionCode', 'targetScore', 'weight'],
        'skills' => ['id', 'code', 'name', 'status'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @var array<string,list<string>> */
    private array $columnCache = [];

    /** @return array{status:string, roles:list<CareerRoleBenchmark>} */
    public function activeRoles(): array
    {
        if (!$this->hasContract()) {
            return ['status' => 'insufficient_data', 'roles' => []];
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT id, code, title, category FROM career_role_benchmarks WHERE isActive = 1 ORDER BY code ASC',
            );
            $executed = $statement !== false && $statement->execute();
            if (!$executed) {
                return ['status' => 'insufficient_data', 'roles' => []];
            }
            $roleRows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return ['status' => 'insufficient_data', 'roles' => []];
        }

        $roles = [];
        foreach ($roleRows as $roleRow) {
            $role = $this->roleFromRow($roleRow);
            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return ['status' => 'ok', 'roles' => $roles];
    }

    /** @return array{status:string, role:?CareerRoleBenchmark} */
    public function activeRole(string $code): array
    {
        $code = trim($code);
        if ($code === '' || preg_match('/\A[a-z][a-z0-9_]{1,99}\z/', $code) !== 1) {
            return ['status' => 'unresolved_role', 'role' => null];
        }
        $roles = $this->activeRoles();
        if (($roles['status'] ?? '') !== 'ok') {
            return ['status' => 'insufficient_data', 'role' => null];
        }
        foreach ($roles['roles'] as $role) {
            if ($role->code() === $code) {
                return ['status' => 'ok', 'role' => $role];
            }
        }
        return ['status' => 'unresolved_role', 'role' => null];
    }

    private function roleFromRow(array $row): ?CareerRoleBenchmark
    {
        $roleId = trim((string) ($row['id'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));
        $category = trim((string) ($row['category'] ?? ''));
        if ($roleId === '' || $title === '' || $category === '' || preg_match('/\A[a-z][a-z0-9_]{1,99}\z/', $code) !== 1) {
            return null;
        }

        $skillWeightSum = 0.0;
        $skillRequirements = [];
        try {
            $statement = $this->pdo->prepare(
                'SELECT r.minimumScore, r.weight, r.isRequired, s.code, s.name, s.status '
                . 'FROM career_role_skill_requirements r INNER JOIN skills s ON s.id = r.skillId '
                . 'WHERE r.roleId = :role ORDER BY s.code ASC',
            );
            $executed = $statement !== false && $statement->execute(['role' => $roleId]);
            $rows = $executed ? $statement->fetchAll(PDO::FETCH_ASSOC) ?: [] : [];
        } catch (Throwable) {
            return null;
        }
        foreach ($rows as $requirementRow) {
            $requirement = $this->skillRequirementFromRow($requirementRow);
            if ($requirement === null) {
                continue;
            }
            $skillWeightSum += $requirement['weight'];
            $skillRequirements[] = $requirement;
        }

        $signalWeightSum = 0.0;
        $signals = [];
        try {
            $statement = $this->pdo->prepare(
                'SELECT assessmentFamily, dimensionCode, targetScore, weight '
                . 'FROM career_role_assessment_signals WHERE roleId = :role '
                . 'ORDER BY assessmentFamily ASC, dimensionCode ASC',
            );
            $executed = $statement !== false && $statement->execute(['role' => $roleId]);
            $rows = $executed ? $statement->fetchAll(PDO::FETCH_ASSOC) ?: [] : [];
        } catch (Throwable) {
            return null;
        }
        foreach ($rows as $signalRow) {
            $signal = $this->signalFromRow($signalRow);
            if ($signal === null) {
                continue;
            }
            $signalWeightSum += $signal['weight'];
            $signals[] = $signal;
        }

        // Normalize drifted weight groups back to 100 (scale preserves ratios).
        $skillScale = $skillWeightSum > 0.0 ? 100.0 / $skillWeightSum : 0.0;
        foreach ($skillRequirements as $index => $requirement) {
            $skillRequirements[$index]['weight'] = round($requirement['weight'] * $skillScale, 4);
        }
        $signalScale = $signalWeightSum > 0.0 ? 100.0 / $signalWeightSum : 0.0;
        foreach ($signals as $index => $signal) {
            $signals[$index]['weight'] = round($signal['weight'] * $signalScale, 4);
        }

        try {
            return new CareerRoleBenchmark($code, $title, $category, $skillRequirements, $signals);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{code:string,label:string,minimum_score:int,weight:float,required:bool}|null */
    private function skillRequirementFromRow(array $row): ?array
    {
        $code = trim((string) ($row['code'] ?? ''));
        if ($code === '' || strtolower((string) ($row['status'] ?? '')) !== 'active') {
            return null;
        }
        $minimum = (float) ($row['minimumScore'] ?? -1.0);
        $weight = (float) ($row['weight'] ?? -1.0);
        if ($minimum < 0.0 || $minimum > 100.0 || $weight <= 0.0 || $weight > 100.0) {
            return null;
        }
        return [
            'code' => $code,
            'label' => trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : $code,
            'minimum_score' => (int) round($minimum),
            'weight' => $weight,
            'required' => (int) ($row['isRequired'] ?? 0) === 1,
        ];
    }

    /** @return array{family:string,dimension:string,target:float,weight:float}|null */
    private function signalFromRow(array $row): ?array
    {
        $family = strtolower(trim((string) ($row['assessmentFamily'] ?? '')));
        $dimension = strtoupper(trim((string) ($row['dimensionCode'] ?? '')));
        if (!in_array($family, self::ASSESSMENT_FAMILIES, true)) {
            return null;
        }
        if (!in_array($dimension, self::FAMILY_DIMENSIONS[$family], true)) {
            return null;
        }
        $target = (float) ($row['targetScore'] ?? -1.0);
        $weight = (float) ($row['weight'] ?? -1.0);
        if ($target < 0.0 || $target > 100.0 || $weight <= 0.0 || $weight > 100.0) {
            return null;
        }
        return ['family' => $family, 'dimension' => $dimension, 'target' => $target, 'weight' => $weight];
    }

    private function hasContract(): bool
    {
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (array_diff($columns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }
        return true;
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
}
