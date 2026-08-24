<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

interface LearnerForwardMigration
{
    public function version(): string;

    public function description(): string;

    /** @return list<string> */
    public function statements(string $driver): array;

    /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
    public function expectedSchema(): array;
}
