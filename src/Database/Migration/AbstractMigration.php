<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

abstract class AbstractMigration implements Migration
{
    public function preflight(MigrationContext $context): void {}
    public function isReversible(): bool { return true; }
}
