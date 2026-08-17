<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Seeds\Staging\LearnerAiPilotSeeder;

function pilot_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function pilot_schema(): string
{
    $schema = (string) getenv('LEARNER_MYSQL_TEST_SCHEMA');
    pilot_assert(preg_match('/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/', $schema) === 1, 'pilot seed requires an explicitly named disposable verification schema');
    return $schema;
}

function pilot_pdo(string $schema): PDO
{
    $configRoot = (string) getenv('TALENTHUB_DB_CONFIG_ROOT');
    pilot_assert($configRoot !== '' && is_file($configRoot . '/bin/bootstrap.php') && is_file($configRoot . '/config/database.php'), 'pilot seed requires an external local configuration root');
    require_once $configRoot . '/bin/bootstrap.php';
    $config = require $configRoot . '/config/database.php';
    $config['database'] = $schema;
    $pdo = (new TalentHub\Database\Connection($config))->connect();
    pilot_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema, 'pilot seed connection is pinned to the approved disposable schema');
    return $pdo;
}

/** @param list<string> $tables @return array<string,int> */
function pilot_counts_outside_reserved_prefix(PDO $pdo, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE id NOT LIKE :prefix');
        $statement->execute(['prefix' => LearnerAiPilotSeeder::reservedPrefix() . '%']);
        $counts[$table] = (int) $statement->fetchColumn();
    }
    ksort($counts, SORT_STRING);
    return $counts;
}

/** @return list<string> */
function pilot_evidence_source_ids(TalentHub\Learner\Ai\Domain\RecommendationInput $input): array
{
    $ids = array_map(static fn (array $reference): string => (string) $reference['source_id'], $input->evidenceReferences());
    sort($ids, SORT_STRING);
    return $ids;
}

function pilot_rule_signature(TalentHub\Learner\Ai\Domain\RecommendationResult $result): string
{
    $items = [];
    foreach ($result->items() as $item) {
        $items[] = [
            $item->itemType(),
            $item->title(),
            $item->priority(),
            array_map(static fn ($evidence): array => [$evidence->sourceType(), $evidence->sourceId()], $item->evidence()),
        ];
    }
    return json_encode([$result->engineType(), $result->fallbackReason(), $items], JSON_THROW_ON_ERROR);
}

$repositoryRoot = dirname(__DIR__);
$seedFile = $repositoryRoot . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
pilot_assert(is_file($seedFile), 'Task 14 insert-only pilot seeder exists');
require_once $seedFile;
require_once $repositoryRoot . '/app/learner/ai/bootstrap.php';

$schema = pilot_schema();
$pdo = pilot_pdo($schema);
$seeder = new LearnerAiPilotSeeder($pdo, $schema);
$baselineOutsideReserved = pilot_counts_outside_reserved_prefix($pdo, $seeder->touchedTables());

$first = $seeder->seed();
pilot_assert($first['declared'] === 61, 'seed declares exactly the DCR fixture rows');
pilot_assert($first['inserted'] + $first['existing'] === $first['declared'], 'seed either inserts or verifies every declared row');
pilot_assert(pilot_counts_outside_reserved_prefix($pdo, $seeder->touchedTables()) === $baselineOutsideReserved, 'first seed leaves all non-reserved row counts unchanged');

$second = $seeder->seed();
pilot_assert($second['inserted'] === 0 && $second['existing'] === $second['declared'], 'second seed is an insert-only idempotent no-op');
pilot_assert(pilot_counts_outside_reserved_prefix($pdo, $seeder->touchedTables()) === $baselineOutsideReserved, 'second seed leaves all non-reserved row counts unchanged');

$consent = new ConsentPolicy(new DatabaseConsentSource($pdo));
$snapshotBuilder = new RecommendationSnapshotBuilder(
    new DatabaseStudentProfileSource($pdo),
    new DatabaseSkillSource($pdo),
    new DatabaseAssessmentSource($pdo),
    new DatabaseActivityExperienceSource($pdo),
    new DatabasePublishedEvaluationSource($pdo),
    new DatabaseOpportunitySource($pdo),
);
$engine = new RuleRecommendationEngine();
[$studentA, $studentB] = LearnerAiPilotSeeder::studentIds();
$inputA = $snapshotBuilder->build($studentA, $consent->allowedScopes($studentA));
$inputB = $snapshotBuilder->build($studentB, $consent->allowedScopes($studentB));
$resultA = $engine->generate($inputA, new RecommendationContext($consent->allowedScopes($studentA), 'pilot-rule-request-a', 'pilot-rule-a', $studentA));
$repeatA = $engine->generate($inputA, new RecommendationContext($consent->allowedScopes($studentA), 'pilot-rule-request-a', 'pilot-rule-a', $studentA));
$resultB = $engine->generate($inputB, new RecommendationContext($consent->allowedScopes($studentB), 'pilot-rule-request-b', 'pilot-rule-b', $studentB));

pilot_assert($inputA->qualityFlags()['allowed_scopes'] === ['activity', 'assessment', 'evaluation', 'skills'], 'learner A has explicit four-scope consent');
pilot_assert($inputB->qualityFlags()['allowed_scopes'] === ['activity', 'assessment', 'evaluation', 'skills'], 'learner B has explicit four-scope consent');
pilot_assert($resultA->items() !== [] && $resultB->items() !== [], 'each synthetic learner produces an evidence-backed rule result');
pilot_assert(pilot_rule_signature($resultA) === pilot_rule_signature($repeatA), 'rule output is deterministic for an unchanged canonical snapshot');
foreach (array_merge($resultA->items(), $resultB->items()) as $item) {
    pilot_assert($item->evidence() !== [], 'every persisted-rule candidate has provenance evidence');
}
pilot_assert(
    pilot_evidence_source_ids($inputA) === [
        '00000000-0000-4000-8000-000000000151',
        '00000000-0000-4000-8000-000000000161',
        '00000000-0000-4000-8000-000000000201',
        '00000000-0000-4000-8000-000000000202',
        '00000000-0000-4000-8000-000000000251',
    ],
    'learner A snapshot contains only learner A source identifiers',
);
pilot_assert(
    pilot_evidence_source_ids($inputB) === [
        '00000000-0000-4000-8000-000000000152',
        '00000000-0000-4000-8000-000000000162',
        '00000000-0000-4000-8000-000000000203',
        '00000000-0000-4000-8000-000000000204',
        '00000000-0000-4000-8000-000000000252',
    ],
    'learner B snapshot contains only learner B source identifiers',
);

echo 'learner_ai_pilot_seed_test: OK' . PHP_EOL;
