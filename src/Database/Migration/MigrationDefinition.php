<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

final readonly class MigrationDefinition
{
    public function __construct(
        public string $version,
        public string $name,
        public string $path,
        public string $checksum,
        public Migration $migration,
    ) {}
}
