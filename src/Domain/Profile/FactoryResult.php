<?php
declare(strict_types=1);

namespace TalentHub\Domain\Profile;

/**
 * Result returned by the profile factories. The caller decides whether to
 * insert (factory insert) or just read (factory returned null).
 */
final class FactoryResult
{
    /**
     * @param array<string, mixed> $row Normalised profile row (mirrors the table columns).
     * @param bool $created True if the factory inserted a new row.
     */
    public function __construct(
        public readonly array $row,
        public readonly bool $created = false,
    ) {
    }

    public function getRow(): array
    {
        return $this->row;
    }

    public function wasCreated(): bool
    {
        return $this->created;
    }
}
