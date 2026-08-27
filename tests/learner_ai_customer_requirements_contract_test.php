<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Contracts\RoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Service\RoadmapService;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

/**
 * Phase 0 customer contract test.
 *
 * This test is intentionally dependency-free: it freezes the response shape
 * before later phases connect the seven capabilities to their implementations.
 */

const LEARNER_AI_CUSTOMER_CAPABILITIES = [
    'profile_analysis',
    'talent_passport',
    'recommendation',
    'roadmap',
    'adaptive_loop',
    'school_insight',
    'enterprise_matching',
];

const LEARNER_AI_CUSTOMER_STATES = [
    'pending',
    'stale_model',
    'ai_unavailable',
    'ready_model',
    'ready_rule',
];

const LEARNER_AI_CUSTOMER_FRESHNESS = [
    'fresh',
    'stale',
    'pending',
    'unavailable',
];

function learner_ai_customer_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function learner_ai_customer_contract_expect_rejection(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }

    throw new RuntimeException('Assertion failed: ' . $message);
}

function learner_ai_customer_contract_is_timestamp(mixed $value): bool
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    try {
        new DateTimeImmutable($value);
        return true;
    } catch (Exception) {
        return false;
    }
}

/** @param array<string,mixed> $output */
function learner_ai_customer_contract_validate(array $output): void
{
    $required = [
        'contract_version',
        'capability',
        'analysis_origin',
        'evidence',
        'generated_at',
        'model_version',
        'rule_version',
        'state',
        'freshness_status',
    ];
    foreach ($required as $field) {
        learner_ai_customer_contract_assert(array_key_exists($field, $output), "required field {$field} is present");
    }

    learner_ai_customer_contract_assert(
        in_array($output['capability'], LEARNER_AI_CUSTOMER_CAPABILITIES, true),
        'capability is in the customer contract matrix',
    );
    learner_ai_customer_contract_assert(
        is_string($output['contract_version']) && $output['contract_version'] !== '',
        'contract_version is present',
    );
    learner_ai_customer_contract_assert(
        in_array($output['state'], LEARNER_AI_CUSTOMER_STATES, true),
        'status is a supported availability state',
    );
    learner_ai_customer_contract_assert(
        in_array($output['freshness_status'], LEARNER_AI_CUSTOMER_FRESHNESS, true),
        'freshness_status is supported',
    );
    learner_ai_customer_contract_assert(is_array($output['evidence']), 'evidence is an array');

    if (in_array($output['state'], ['ready_model', 'stale_model'], true)) {
        learner_ai_customer_contract_assert($output['analysis_origin'] === 'model', 'model state requires model origin');
    }
    if ($output['state'] === 'ready_rule') {
        learner_ai_customer_contract_assert($output['analysis_origin'] === 'rule', 'rule state requires rule origin');
    }

    $expectedFreshness = match ($output['state']) {
        'ready_model', 'ready_rule' => 'fresh',
        'stale_model' => 'stale',
        'pending' => 'pending',
        'ai_unavailable' => 'unavailable',
    };
    learner_ai_customer_contract_assert(
        $output['freshness_status'] === $expectedFreshness,
        'freshness_status agrees with the availability state',
    );

    if (in_array($output['state'], ['ready_model', 'stale_model', 'ready_rule'], true)) {
        learner_ai_customer_contract_assert(
            in_array($output['analysis_origin'], ['model', 'rule'], true),
            'served output identifies model or rule origin',
        );
        learner_ai_customer_contract_assert(count($output['evidence']) > 0, 'served output has evidence');
        learner_ai_customer_contract_assert(
            learner_ai_customer_contract_is_timestamp($output['generated_at']),
            'served output has generated_at timestamp',
        );
        if ($output['analysis_origin'] === 'model') {
            learner_ai_customer_contract_assert(is_string($output['model_version']) && $output['model_version'] !== '', 'model output has model_version');
            learner_ai_customer_contract_assert($output['rule_version'] === null, 'model output does not claim a rule version');
        } else {
            learner_ai_customer_contract_assert(is_string($output['rule_version']) && $output['rule_version'] !== '', 'rule output has rule_version');
            learner_ai_customer_contract_assert($output['model_version'] === null, 'rule output does not claim a model version');
        }
    } else {
        learner_ai_customer_contract_assert(
            $output['analysis_origin'] === null,
            'pending/unavailable output does not claim a generated origin',
        );
        learner_ai_customer_contract_assert($output['generated_at'] === null, 'pending/unavailable is not generated');
        learner_ai_customer_contract_assert($output['model_version'] === null, 'pending/unavailable has no model version');
        learner_ai_customer_contract_assert($output['rule_version'] === null, 'pending/unavailable has no rule version');
    }

    if ($output['state'] === 'stale_model') {
        learner_ai_customer_contract_assert(
            ($output['last_known_good'] ?? false) === true,
            'stale_model explicitly identifies last-known-good data',
        );
        learner_ai_customer_contract_assert(
            learner_ai_customer_contract_is_timestamp($output['stale_since'] ?? null),
            'stale_model has stale_since timestamp',
        );
    }

    $encoded = json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['api_key', 'authorization', 'access_token', 'secret'] as $forbidden) {
        learner_ai_customer_contract_assert(
            !str_contains(strtolower($encoded), $forbidden),
            "output does not contain {$forbidden}",
        );
    }
}

/** @param array<string,mixed> $latest @return array<string,mixed> */
function learner_ai_customer_contract_runtime_roadmap(array $latest): array
{
    $repository = new class($latest) implements RoadmapRepository {
        /** @param array<string,mixed> $latest */
        public function __construct(private readonly array $latest) {}
        public function saveCompleted(string $studentId, string $runId, RoadmapAnalysis $analysis, array $providerAudit): array { return $this->latest; }
        public function latestForStudent(string $studentId): ?array { return $this->latest; }
        public function latestPendingForStudent(string $studentId): ?array { return null; }
        public function historyForStudent(string $studentId): array { return []; }
        public function versionForStudent(string $studentId, int $version): ?array { return null; }
        public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array { return []; }
        public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array { return []; }
        public function feedbackSignalsForStudent(string $studentId): array { return []; }
    };
    $engine = new class implements RoadmapEngine {
        public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis
        {
            throw new LogicException('The latest-roadmap contract probe must not generate a roadmap.');
        }
    };
    $service = new RoadmapService(
        $repository,
        $engine,
        static fn (string $studentId): bool => true,
        static fn (string $studentId): array => [],
        static fn (string $studentId, array $scopes): never => throw new LogicException('Snapshot builder must not run.'),
        static fn (RecommendationInput $input): never => throw new LogicException('Quality gate must not run.'),
        static fn (): never => throw new LogicException('Pending creator must not run.'),
        static fn (): never => throw new LogicException('Run completer must not run.'),
        static fn (): never => throw new LogicException('Run failer must not run.'),
    );

    return $service->latest('phase-0-contract-student') ?? [];
}

$evidence = [[
    'source_type' => 'assessment',
    'source_id' => 'evidence-phase0-001',
    'observed_at' => '2026-08-26T00:00:00+00:00',
]];

/** @var list<array<string,mixed>> $outputs */
$outputs = [
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'profile_analysis',
        'analysis_origin' => 'model',
        'evidence' => $evidence,
        'generated_at' => '2026-08-26T00:00:00+00:00',
        'model_version' => 'gemini-contract-v1',
        'rule_version' => null,
        'state' => 'ready_model',
        'freshness_status' => 'fresh',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'talent_passport',
        'analysis_origin' => 'rule',
        'evidence' => $evidence,
        'generated_at' => '2026-08-26T00:00:00+00:00',
        'model_version' => null,
        'rule_version' => 'rule-contract-v1',
        'state' => 'ready_rule',
        'freshness_status' => 'fresh',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'recommendation',
        'analysis_origin' => 'model',
        'evidence' => $evidence,
        'generated_at' => '2026-08-25T00:00:00+00:00',
        'model_version' => 'gemini-contract-v1',
        'rule_version' => null,
        'state' => 'stale_model',
        'freshness_status' => 'stale',
        'last_known_good' => true,
        'stale_since' => '2026-08-26T00:00:00+00:00',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'roadmap',
        'analysis_origin' => null,
        'evidence' => [],
        'generated_at' => null,
        'model_version' => null,
        'rule_version' => null,
        'state' => 'pending',
        'freshness_status' => 'pending',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'adaptive_loop',
        'analysis_origin' => null,
        'evidence' => [],
        'generated_at' => null,
        'model_version' => null,
        'rule_version' => null,
        'state' => 'ai_unavailable',
        'freshness_status' => 'unavailable',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'school_insight',
        'analysis_origin' => 'model',
        'evidence' => $evidence,
        'generated_at' => '2026-08-26T00:00:00+00:00',
        'model_version' => 'gemini-contract-v1',
        'rule_version' => null,
        'state' => 'ready_model',
        'freshness_status' => 'fresh',
    ],
    [
        'contract_version' => 'learner-ai-customer-1.0.0',
        'capability' => 'enterprise_matching',
        'analysis_origin' => 'rule',
        'evidence' => $evidence,
        'generated_at' => '2026-08-26T00:00:00+00:00',
        'model_version' => null,
        'rule_version' => 'rule-contract-v1',
        'state' => 'ready_rule',
        'freshness_status' => 'fresh',
    ],
];

$capabilitiesSeen = array_column($outputs, 'capability');
learner_ai_customer_contract_assert(
    $capabilitiesSeen === LEARNER_AI_CUSTOMER_CAPABILITIES,
    'all seven customer capabilities are represented exactly once',
);

$statesSeen = [];
foreach ($outputs as $output) {
    learner_ai_customer_contract_validate($output);
    $statesSeen[$output['state']] = true;
}
$statesSeen = array_keys($statesSeen);
sort($statesSeen);
$expectedStates = LEARNER_AI_CUSTOMER_STATES;
sort($expectedStates);
learner_ai_customer_contract_assert(
    $statesSeen === $expectedStates,
    'all five availability states are covered',
);

$missingEvidence = $outputs[0];
$missingEvidence['evidence'] = [];
learner_ai_customer_contract_expect_rejection(
    static fn () => learner_ai_customer_contract_validate($missingEvidence),
    'missing evidence is rejected for served output',
);

$invalidState = $outputs[0];
$invalidState['state'] = 'fallback_silently';
learner_ai_customer_contract_expect_rejection(
    static fn () => learner_ai_customer_contract_validate($invalidState),
    'unknown or silent fallback state is rejected',
);

$modelStateWithRuleOrigin = $outputs[0];
$modelStateWithRuleOrigin['analysis_origin'] = 'rule';
$modelStateWithRuleOrigin['model_version'] = null;
$modelStateWithRuleOrigin['rule_version'] = 'rule-contract-v1';
learner_ai_customer_contract_expect_rejection(
    static fn () => learner_ai_customer_contract_validate($modelStateWithRuleOrigin),
    'ready_model with rule origin is rejected',
);

$ruleStateWithModelOrigin = $outputs[1];
$ruleStateWithModelOrigin['analysis_origin'] = 'model';
$ruleStateWithModelOrigin['model_version'] = 'gemini-contract-v1';
$ruleStateWithModelOrigin['rule_version'] = null;
learner_ai_customer_contract_expect_rejection(
    static fn () => learner_ai_customer_contract_validate($ruleStateWithModelOrigin),
    'ready_rule with model origin is rejected',
);

$staleWithoutLkg = $outputs[2];
$staleWithoutLkg['last_known_good'] = false;
learner_ai_customer_contract_expect_rejection(
    static fn () => learner_ai_customer_contract_validate($staleWithoutLkg),
    'stale model without last-known-good marker is rejected',
);

$runtimeRuleRoadmap = learner_ai_customer_contract_runtime_roadmap([
    'roadmap_id' => 'phase-0-runtime-roadmap',
    'version' => 1,
    'contract_version' => 'learner-roadmap-1.0.0',
    'status' => 'active',
    'analysis_origin' => 'rule_fallback',
    'executive_summary' => 'Runtime compatibility probe.',
    'confidence_band' => 'medium',
    'primary_direction' => [],
    'alternative_directions' => [],
    'insights' => [],
    'evidence_summary' => [],
    'generated_at' => '2026-08-26T00:00:00+00:00',
    'phases' => [],
    'progress' => [],
    'engine' => ['rule_version' => 'learner-roadmap-rules-1', 'fallback_reason' => 'rule_only'],
]);
learner_ai_customer_contract_assert(
    ($runtimeRuleRoadmap['analysis_origin'] ?? null) === 'rule'
        && ($runtimeRuleRoadmap['state'] ?? null) === 'ready_rule'
        && ($runtimeRuleRoadmap['freshness_status'] ?? null) === 'fresh'
        && array_key_exists('model_version', $runtimeRuleRoadmap)
        && $runtimeRuleRoadmap['model_version'] === null
        && ($runtimeRuleRoadmap['rule_version'] ?? null) === 'learner-roadmap-rules-1',
    'runtime roadmap follows the canonical Phase 0 public contract',
);

$checklist = file_get_contents(dirname(__DIR__) . '/docs/superpowers/readiness/learner-ai-roadmap-release-checklist.md');
$gate = file_get_contents(dirname(__DIR__) . '/docs/superpowers/readiness/learner-ai-evaluation-gate.md');
learner_ai_customer_contract_assert(is_string($checklist) && is_string($gate), 'Phase 0 gate documents are readable');
learner_ai_customer_contract_assert(
    !str_contains($checklist, '- [x] Roadmap model execution is fail-closed'),
    'release checklist does not mark the unimplemented unified roadmap rollout gate as complete',
);
learner_ai_customer_contract_assert(
    str_contains($gate, '`rule_fallback` → `rule`')
        && str_contains($gate, '`fallback_rule` → `ready_rule`')
        && str_contains($gate, 'target contract'),
    'evaluation gate records the canonical target contract and legacy roadmap mapping',
);

echo "learner_ai_customer_requirements_contract_test: OK\n";
