<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

final class ForwardMigrationDefinition
{
    public function __construct(
        public readonly string $version,
        public readonly string $name,
        public readonly string $path,
        public readonly string $checksum,
        public readonly LearnerForwardMigration $migration,
    ) {
    }
}
