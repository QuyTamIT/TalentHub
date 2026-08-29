<?php

declare(strict_types=1);

/**
 * Task 2 contract: the AI schema is present and aligned with the
 * 2026-08-28 plan.
 *
 * The test is read-only: it only inspects information_schema and
 * SHOW-style metadata through the project's `SchemaInspector`. It
 * never writes data, never invokes the migration runner, and never
 * touches the source database beyond the metadata queries.
 *
 * The test must FAIL on any environment where one of the canonical AI
 * tables, columns or indexes is missing. That includes the current
 * local primary pipeline where the bridge migrations 20260826001000
 * through 20260827001200 are still pending; the test should help
 * prove the schema gap is real before we cut over the staging database.
 *
 * Mapping note (decided in Task 2 before any code is changed):
 *  - The 2026-08-28 plan refers to `source_snapshot_hash` as the
 *    canonical hash of the input snapshot. The current schema stores
 *    that exact value in `learner_recommendation_input_snapshots.contentHash`
 *    (CHAR(64) SHA-256) plus `learner_ai_capability_profiles.snapshotHash`
 *    and `learner_ai_refresh_jobs.snapshot_hash`. We deliberately do
 *    NOT introduce a new `source_snapshot_hash` column; the test
 *    verifies the existing canonical columns and references the
 *    mapping in the assertion message.
 */

use TalentHub\Learner\Data\Database\SchemaInspector;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function schema_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Schema contract violation: {$message}\n");
        exit(1);
    }
}

/**
 * @return array{
 *     pdo: PDO,
 *     schema: string,
 *     inspector: SchemaInspector,
 *     database_name: string,
 *     database_user: string,
 *     host: string
 * }
 */
function schema_contract_connect(): array
{
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new \TalentHub\Database\Connection($config))->connect();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('Schema contract test requires a selected database.');
    }
    return [
        'pdo' => $pdo,
        'schema' => $database,
        'inspector' => new SchemaInspector($pdo, $database),
        'database_name' => $database,
        'database_user' => (string) ($config['username'] ?? 'unknown'),
        'host' => (string) ($config['host'] ?? 'unknown'),
    ];
}

$ctx = schema_contract_connect();
$inspector = $ctx['inspector'];
$pdo = $ctx['pdo'];

$report = [];

/** @param list<string> $columns */
function schema_contract_check_columns(
    SchemaInspector $inspector,
    string $table,
    array $columns,
    array &$report,
): void {
    $missing = [];
    foreach ($columns as $column) {
        if (!$inspector->hasColumn($table, $column)) {
            $missing[] = $column;
        }
    }
    $report[$table]['missing_columns'] = $missing;
}

/** @param list<string> $indexes */
function schema_contract_check_indexes(
    SchemaInspector $inspector,
    string $table,
    array $indexes,
    array &$report,
): void {
    $missing = [];
    foreach ($indexes as $index) {
        if (!$inspector->hasIndex($table, $index)) {
            $missing[] = $index;
        }
    }
    $report[$table]['missing_indexes'] = $missing;
}

$requiredTables = [
    // Learner recommendation store (foundation migrations 002-004)
    'learner_recommendation_input_snapshots',
    'learner_recommendation_runs',
    'learner_recommendation_snapshot_evidence',
    'learner_recommendation_items',
    'learner_recommendation_evidence',
    'learner_recommendation_feedback',
    'learner_recommendation_audit_events',
    // Forward migrations 005-011
    'learner_ai_roadmaps',
    'learner_ai_roadmap_phases',
    'learner_ai_roadmap_tasks',
    'learner_ai_roadmap_task_events',
    'learner_ai_refresh_jobs',
    'learner_ai_data_outbox',
    'learner_ai_capability_profiles',
    'learner_ai_provider_health',
    'learner_ai_catalog_items',
    // School AI tables (forward migrations 012-013)
    'school_ai_insights',
    'school_ai_refresh_jobs',
    // Enterprise AI table (forward migration 014)
    'enterprise_ai_match_rankings',
];

$missingTables = [];
foreach ($requiredTables as $table) {
    if (!$inspector->hasTable($table)) {
        $missingTables[] = $table;
    }
}
$report['missing_tables'] = $missingTables;

// 1. learner_recommendation_input_snapshots: source_snapshot_hash canonical
schema_contract_check_columns($inspector, 'learner_recommendation_input_snapshots', [
    'id', 'studentId', 'schemaVersion', 'contentHash', 'consentScopesJson',
    'qualityFlagsJson', 'payloadJson', 'sourceUpdatedAt', 'createdAt',
], $report);
$type = $inspector->columnType('learner_recommendation_input_snapshots', 'contentHash');
$report['learner_recommendation_input_snapshots']['contentHash_type'] = $type;
schema_contract_check_indexes($inspector, 'learner_recommendation_input_snapshots', [
    'uq_learner_recommendation_input_snapshots_student_hash',
    'uq_learner_recommendation_input_snapshots_id_student',
    'idx_learner_recommendation_input_snapshots_student_created',
], $report);

// 2. learner_recommendation_runs: analysis_origin + modelVersion + provider + opportunity matching capability
schema_contract_check_columns($inspector, 'learner_recommendation_runs', [
    'id', 'studentId', 'snapshotId', 'idempotencyKey', 'engineType', 'status',
    'ruleVersion', 'provider', 'modelVersion', 'promptVersion', 'fallbackReason',
    'safeErrorCode', 'startedAt', 'completedAt', 'createdAt',
    'capability',
], $report);
schema_contract_check_indexes($inspector, 'learner_recommendation_runs', [
    'uq_learner_recommendation_runs_student_idempotency',
    'idx_learner_recommendation_runs_student_created',
    'idx_learner_recommendation_runs_snapshot_student',
    'idx_learner_recommendation_runs_student_capability_created',
], $report);

// 3. learner_ai_refresh_jobs: queue + retry + lease + attempt + dead-letter
schema_contract_check_columns($inspector, 'learner_ai_refresh_jobs', [
    'id', 'job_key', 'student_id', 'capability', 'snapshot_hash', 'status',
    'attempts', 'next_retry_at', 'lease_until', 'lease_owner', 'lease_token',
    'error_code', 'dead_lettered_at', 'created_at', 'updated_at',
], $report);
schema_contract_check_indexes($inspector, 'learner_ai_refresh_jobs', [
    'idx_ai_refresh_jobs_claim',
], $report);
$report['learner_ai_refresh_jobs']['status_type'] = $inspector->columnType('learner_ai_refresh_jobs', 'status');

// 4. learner_ai_data_outbox: transactional outbox
schema_contract_check_columns($inspector, 'learner_ai_data_outbox', [
    'id', 'aggregate_type', 'aggregate_id', 'tenant_id', 'event_type',
    'aggregate_version', 'payload_hash', 'affected_student_ids',
    'delivery_status', 'occurred_at', 'delivered_at',
], $report);
schema_contract_check_indexes($inspector, 'learner_ai_data_outbox', [
    'idx_ai_outbox_delivery',
], $report);

// 5. learner_ai_roadmaps: after migration 005 + 008 it carries freshness + model_version
schema_contract_check_columns($inspector, 'learner_ai_roadmaps', [
    'id', 'studentId', 'runId', 'versionNumber', 'contractVersion', 'status',
    'executiveSummary', 'confidenceBand', 'responseHash', 'generatedAt',
    'supersededAt', 'createdAt',
    // migration 008 columns
    'freshness_status', 'stale_since', 'last_refresh_error', 'next_retry_at',
    'model_version', 'snapshot_hash', 'refresh_job_id',
], $report);
schema_contract_check_indexes($inspector, 'learner_ai_roadmaps', [
    'uq_learner_ai_roadmaps_student_version',
    'uq_learner_ai_roadmaps_run',
    'idx_learner_ai_roadmaps_student_status_generated',
], $report);

// 6. learner_ai_capability_profiles: source_snapshot_hash canonical
schema_contract_check_columns($inspector, 'learner_ai_capability_profiles', [
    'id', 'student_id', 'version_number', 'status', 'talent_map_json',
    'strengths_json', 'improvements_json', 'potential_paths_json',
    'trend_signals_json', 'evidence_json', 'snapshot_hash',
    'pending_snapshot_hash', 'model_version', 'generated_at', 'stale_since',
    'last_refresh_error', 'next_retry_at', 'refresh_job_id', 'superseded_at',
    'created_at',
], $report);

// 7. learner_ai_provider_health: circuit breaker persistence
schema_contract_check_columns($inspector, 'learner_ai_provider_health', [
    'provider_key', 'state', 'failure_count', 'opened_at', 'updated_at',
], $report);

// 8. learner_ai_catalog_items (migration 015 adds opportunity matching fields)
schema_contract_check_columns($inspector, 'learner_ai_catalog_items', [
    'catalog_id', 'item_type', 'category', 'title', 'summary', 'publish_status',
    'deadline_at', 'eligibility_json', 'capacity', 'enrolled_count', 'url',
    'action_json', 'school_id', 'tenant_id', 'updated_at',
    'provider_name', 'location', 'difficulty',
    'required_skills_json', 'learning_outcomes_json', 'education_bands_json',
], $report);

// 9. school_ai_insights
schema_contract_check_columns($inspector, 'school_ai_insights', [
    'id', 'school_id', 'aggregate_hash', 'payload_json', 'model_version',
    'generated_at', 'stale_since',
], $report);

// 10. school_ai_refresh_jobs
schema_contract_check_columns($inspector, 'school_ai_refresh_jobs', [
    'id', 'school_id', 'aggregate_hash', 'status', 'attempts', 'next_retry_at',
    'created_at', 'updated_at',
], $report);
schema_contract_check_indexes($inspector, 'school_ai_refresh_jobs', [
    'idx_school_ai_refresh_claim',
], $report);

// 11. enterprise_ai_match_rankings
schema_contract_check_columns($inspector, 'enterprise_ai_match_rankings', [
    'id', 'enterprise_id', 'job_hash', 'ranking_json', 'updated_at',
], $report);
schema_contract_check_indexes($inspector, 'enterprise_ai_match_rankings', [
    'idx_enterprise_ai_match_rankings_updated',
], $report);

// 12. learner_recommendation_items (migration 015 opportunity matching columns)
schema_contract_check_columns($inspector, 'learner_recommendation_items', [
    'catalogId', 'rankPosition', 'structuredScore', 'geminiScore',
    'matchScore', 'analysisJson',
], $report);

// ---------------------------------------------------------------------
// Print structured report; exit non-zero if any violation is detected.
// ---------------------------------------------------------------------
$hasViolations = false;
fwrite(STDOUT, "schema_contract_report=database:{$ctx['database_name']}\n");
fwrite(STDOUT, "schema_contract_report=host:" . preg_replace('/[^A-Za-z0-9._-]/', '_', $ctx['host']) . "\n");

if ($missingTables !== []) {
    $hasViolations = true;
    fwrite(STDOUT, 'schema_contract_violation=missing_tables=' . implode(',', $missingTables) . "\n");
}

foreach ($report as $key => $entry) {
    if (!is_array($entry)) {
        continue;
    }
    if (($entry['missing_columns'] ?? []) !== []) {
        $hasViolations = true;
        fwrite(STDOUT, "schema_contract_violation={$key}_missing_columns=" . implode(',', $entry['missing_columns']) . "\n");
    }
    if (($entry['missing_indexes'] ?? []) !== []) {
        $hasViolations = true;
        fwrite(STDOUT, "schema_contract_violation={$key}_missing_indexes=" . implode(',', $entry['missing_indexes']) . "\n");
    }
}

// contentHash on learner_recommendation_input_snapshots is the canonical
// `source_snapshot_hash` referenced in the 2026-08-28 plan; require CHAR(64).
$expected = 'CHAR(64)';
if (($report['learner_recommendation_input_snapshots']['contentHash_type'] ?? null) !== $expected) {
    $hasViolations = true;
    fwrite(STDOUT, "schema_contract_violation=learner_recommendation_input_snapshots.contentHash_type_expected={$expected}\n");
}

if ($hasViolations) {
    fwrite(STDOUT, "schema_contract_status=violations_detected\n");
    exit(1);
}

fwrite(STDOUT, "schema_contract_status=ok\n");
echo "learner_ai_database_schema_test: OK\n";
