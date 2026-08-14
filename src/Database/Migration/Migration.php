<?php
declare(strict_types=1);
namespace TalentHub\Database\Migration;

interface Migration
{
    public function description(): string;
    public function preflight(MigrationContext $context): void;
    public function up(MigrationContext $context): void;
    public function down(MigrationContext $context): void;
    public function isReversible(): bool;
}
