<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\PhaseRequirements;

require_once dirname(__DIR__) . '/app/learner/data/Readiness/PhaseRequirements.php';

function phase_requirements_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$requirements = new PhaseRequirements();
foreach ([10, 11] as $phase) {
    $definition = $requirements->forPhase($phase);
    phase_requirements_assert($definition['tables'] === ['learner_forward_migrations'], "phase {$phase} uses the forward-only migration registry");
    phase_requirements_assert(
        $definition['columns']['learner_forward_migrations'] === ['version', 'name', 'checksum', 'description', 'appliedAt'],
        "phase {$phase} uses the actual registry contract"
    );
}

echo "learner_phase_requirements_test: OK\n";
