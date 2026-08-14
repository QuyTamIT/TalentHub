<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use JsonException;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Exceptions\LearnerDataMappingException;
use TalentHub\Learner\Data\Exceptions\LearnerDataQueryException;
use TalentHub\Learner\Data\Support\KeyMapper;
use Throwable;

abstract class AbstractDatabaseRepository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    protected function fetchAll(string $operation, string $sql, array $parameters = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            if ($statement === false) {
                throw new RuntimeException('PDO could not prepare the learner read query.');
            }
            if (!$statement->execute($parameters)) {
                throw new RuntimeException('PDO could not execute the learner read query.');
            }

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn (array $row): array => KeyMapper::toSnake($row), $rows);
        } catch (Throwable $exception) {
            if ($exception instanceof LearnerDataMappingException) {
                throw $exception;
            }

            throw new LearnerDataQueryException(
                static::classShortName() . ".{$operation} failed: " . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    protected function fetchOne(string $operation, string $sql, array $parameters = []): ?array
    {
        $rows = $this->fetchAll($operation, $sql, $parameters);
        return $rows[0] ?? null;
    }

    protected function decodeJson(mixed $value, string $field): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LearnerDataMappingException("Invalid JSON in {$field}: {$exception->getMessage()}", 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new LearnerDataMappingException("Expected a JSON array or object in {$field}.");
        }

        return $decoded;
    }

    private static function classShortName(): string
    {
        $parts = explode('\\', static::class);
        return (string) end($parts);
    }
}
