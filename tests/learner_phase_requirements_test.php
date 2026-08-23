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
$phase4 = $requirements->forPhase(4);
phase_requirements_assert(
    in_array('activity_registration_policies', $phase4['tables'], true),
    'phase 4 requires the optional per-activity registration policy table',
);
phase_requirements_assert(
    in_array('cancelledAt', $phase4['columns']['activity_registrations'], true)
        && in_array('cancellationReason', $phase4['columns']['activity_registrations'], true),
    'phase 4 requires canonical cancellation metadata',
);
phase_requirements_assert(
    $phase4['columns']['activity_registration_policies'] === [
        'activityId',
        'registrationOpensAt',
        'registrationClosesAt',
        'cancellationClosesAt',
        'approvalMode',
    ],
    'phase 4 requires exact registration policy columns',
);
phase_requirements_assert(
    in_array('idx_activity_registration_policies_close', $phase4['indexes']['activity_registration_policies'], true),
    'phase 4 requires policy closing-time lookup index',
);
phase_requirements_assert(
    ($phase4['foreign_keys']['activity_registration_policies'][0] ?? null) === [
        'from' => 'activityId',
        'table' => 'activities',
        'to' => 'id',
    ],
    'phase 4 requires policy-to-activity ownership foreign key',
);
$phase3 = $requirements->forPhase(3);
phase_requirements_assert(
    $phase3['columns']['privacy_consents'] === [
        'id',
        'studentId',
        'scope',
        'isGranted',
        'policyVersion',
        'grantedAt',
        'revokedAt',
        'createdAt',
    ],
    'phase 3 uses the canonical privacy consent schema'
);
phase_requirements_assert(
    in_array('consentId', $phase3['columns']['student_profile_shares'], true),
    'phase 3 requires profile shares to link explicit consent',
);
phase_requirements_assert(
    in_array('category', $phase3['columns']['projects'], true)
        && in_array('startAt', $phase3['columns']['projects'], true)
        && in_array('endAt', $phase3['columns']['projects'], true),
    'phase 3 requires the reconciled project aggregate contract',
);
phase_requirements_assert(
    in_array('contribution', $phase3['columns']['project_members'], true),
    'phase 3 requires project member contribution evidence',
);
phase_requirements_assert(
    in_array('idx_student_profile_shares_consent', $phase3['indexes']['student_profile_shares'], true),
    'phase 3 requires the consent lookup index',
);
phase_requirements_assert(
    ($phase3['foreign_keys']['student_profile_shares'][0] ?? null) === [
        'from' => 'consentId',
        'table' => 'privacy_consents',
        'to' => 'id',
    ],
    'phase 3 readiness requires the share-to-consent foreign key',
);

foreach ([10, 11] as $phase) {
    $definition = $requirements->forPhase($phase);
    phase_requirements_assert($definition['tables'] === ['learner_forward_migrations'], "phase {$phase} uses the forward-only migration registry");
    phase_requirements_assert(
        $definition['columns']['learner_forward_migrations'] === ['version', 'name', 'checksum', 'description', 'appliedAt'],
        "phase {$phase} uses the actual registry contract"
    );
}

echo "learner_phase_requirements_test: OK\n";
