<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

use TalentHub\Learner\Data\Database\SchemaInspector;

interface LearnerMigrationPreflight
{
    public function assertBeforeApply(SchemaInspector $schemaInspector): void;
}
