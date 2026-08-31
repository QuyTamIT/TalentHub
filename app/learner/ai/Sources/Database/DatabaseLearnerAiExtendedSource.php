<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use Closure;
use TalentHub\Learner\Ai\Sources\LearnerAiExtendedSource;

final class DatabaseLearnerAiExtendedSource implements LearnerAiExtendedSource
{
    /** @var list<string> */
    private readonly array $fields;
    /** @var Closure(string):array */
    private readonly Closure $reader;
    /** @var Closure(string,?string):bool|null */
    private readonly ?Closure $changeDetector;

    /**
     * @param list<string> $allowedFields
     * @param callable(string):array $reader
     * @param (callable(string,?string):bool)|null $changeDetector
     */
    public function __construct(
        private readonly string $type,
        private readonly string $version,
        private readonly string $scope,
        array $allowedFields,
        private readonly string $trigger,
        callable $reader,
        ?callable $changeDetector = null,
    ) {
        foreach ([$type, $version, $scope, $trigger] as $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException('AI source metadata is required.');
            }
        }
        $fields = [];
        foreach ($allowedFields as $field) {
            if (!is_string($field) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $field) !== 1) {
                throw new \InvalidArgumentException('AI source allowed fields are invalid.');
            }
            $fields[$field] = true;
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('AI source requires allowed fields.');
        }
        $normalizedFields = array_keys($fields);
        sort($normalizedFields, SORT_STRING);
        $this->fields = $normalizedFields;
        $this->reader = Closure::fromCallable($reader);
        $this->changeDetector = $changeDetector === null ? null : Closure::fromCallable($changeDetector);
    }

    public function sourceType(): string { return $this->type; }
    public function schemaVersion(): string { return $this->version; }
    public function consentScope(): string { return $this->scope; }
    public function allowedFields(): array { return $this->fields; }
    public function refreshTrigger(): string { return $this->trigger; }

    public function readForStudent(string $studentId): array
    {
        $records = ($this->reader)(trim($studentId));
        if (!is_array($records)) {
            return [];
        }
        if (!array_is_list($records)) {
            $records = [$records];
        }
        return array_values(array_filter($records, static fn (mixed $record): bool => is_array($record)));
    }

    public function changedSince(string $studentId, ?string $versionOrTimestamp): bool
    {
        if ($this->changeDetector !== null) {
            return ($this->changeDetector)(trim($studentId), $versionOrTimestamp);
        }
        if ($versionOrTimestamp === null || trim($versionOrTimestamp) === '') {
            return $this->readForStudent($studentId) !== [];
        }
        foreach ($this->readForStudent($studentId) as $record) {
            $timestamp = $record['updated_at'] ?? $record['observed_at'] ?? null;
            if (is_string($timestamp) && strcmp($timestamp, $versionOrTimestamp) > 0) {
                return true;
            }
        }
        return false;
    }
}
