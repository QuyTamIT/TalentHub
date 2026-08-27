<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$runbook = file_get_contents($root . '/docs/superpowers/runbooks/learner-ai-phase8-operations.md');
$readiness = file_get_contents($root . '/docs/superpowers/readiness/learner-ai-evaluation-gate.md');
$roadmap = file_get_contents($root . '/docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md');
foreach ([$runbook, $readiness, $roadmap] as $document) if (!is_string($document)) throw new RuntimeException('Phase 8 readiness document is unreadable.');
foreach (['quota_exhausted', 'invalid_credentials', 'Google/provider outage', 'Migration rollback', 'Queue backlog', 'Bad model output', 'Consent/privacy incident', 'rotate-ai-key.ps1', 'learner-migrate.php status', 'learner-migrate.php validate', 'learner-migrate.php rollback'] as $marker) {
    if (stripos($runbook, $marker) === false) throw new RuntimeException("Runbook marker missing: {$marker}");
}
foreach (['owner', 'threshold', 'retention', 'privacy', '0% shadow', '10%', '25%', '50%', '100%', 'rollback'] as $marker) {
    if (stripos($readiness . $roadmap, $marker) === false) throw new RuntimeException("Readiness marker missing: {$marker}");
}
foreach (['four assessments', 'snapshot', 'queue', 'validator', 'Talent Passport', 'recommendation', 'roadmap', 'feedback', 'refresh'] as $marker) {
    if (stripos($runbook, $marker) === false) throw new RuntimeException("E2E contract marker missing: {$marker}");
}
$migration = file_get_contents($root . '/bin/learner-migrate.php');
if (!is_string($migration) || !str_contains($migration, "'status'") || !str_contains($migration, "'validate'") || !str_contains($migration, "'rollback'")) {
    throw new RuntimeException('Migration status/validate/rollback contract is missing.');
}
if (!is_dir($root . '/Database/migrations/learner') || count(glob($root . '/Database/migrations/learner/*.php') ?: []) < 1) {
    throw new RuntimeException('Learner migration chain is missing.');
}
echo "learner_ai_phase8_readiness_contract_test: OK\n";
