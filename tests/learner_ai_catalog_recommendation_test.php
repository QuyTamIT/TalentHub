<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogRepository;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Ai\Queue\AiDataOutboxConsumer;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\DatabaseAiDataOutboxRepository;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function catalog_assert(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "Assertion failed: {$message}\n"); exit(1); }
}

catalog_assert(class_exists(DatabaseCatalogSource::class), 'canonical catalog source exists');
catalog_assert(class_exists(DatabaseCatalogRepository::class), 'canonical catalog repository exists');

$catalogItem = new RecommendationItem('activity', 'Eligible workshop', 'Matches the eligible catalog.', 10, 'medium', ['type' => 'explore_career_group', 'career_group' => 'technical'], [new RecommendationEvidence('catalog', 'catalog-1', '2029-01-01T00:00:00.000000+00:00', 'catalog_match', ['catalog_id' => 'catalog-1'])], 'career_technical', 'catalog-1', 'Eligible opportunity', ['eligible_catalog', 'career_match']);
catalog_assert($catalogItem->reasonCodes() === ['career_match', 'eligible_catalog'], 'catalog recommendations persist an allowlisted, deterministic reason code set');
$catalogActionItem = new RecommendationItem('activity', 'Catalog action', 'Catalog-backed registration.', 11, 'medium', ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => 'workshop-a'], [new RecommendationEvidence('catalog', 'workshop-a', '2029-01-01T00:00:00.000000+00:00', 'catalog_match', ['catalog_id' => 'workshop-a'])], 'career_technical', 'workshop-a', 'Eligible workshop.', ['eligible_catalog']);
(new RecommendationResultValidator(['workshop-a']))->validate(new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, null, [$catalogActionItem]));
catalog_assert(true, 'stable non-UUID catalog IDs are accepted only when allowlisted and evidenced');
$mismatched = new RecommendationItem('activity', 'Wrong evidence', 'Must be rejected.', 11, 'medium', ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => 'workshop-a'], [new RecommendationEvidence('catalog', 'workshop-b', '2029-01-01T00:00:00.000000+00:00', 'catalog_match', ['catalog_id' => 'workshop-b'])], 'career_technical', 'workshop-a', 'Wrong evidence.', ['eligible_catalog']);
try { (new RecommendationResultValidator(['workshop-a', 'workshop-b']))->validate(new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, null, [$mismatched])); catalog_assert(false, 'catalog id must match evidence on the same item'); } catch (RuntimeException) {}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, gradeLevel TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT)');
$pdo->exec("CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE learner_ai_data_outbox (id TEXT PRIMARY KEY, aggregate_type TEXT, aggregate_id TEXT, tenant_id TEXT, event_type TEXT, aggregate_version INTEGER, payload_hash TEXT, affected_student_ids TEXT, delivery_status TEXT, occurred_at TEXT, delivered_at TEXT)");
$pdo->exec("INSERT INTO classes VALUES ('class-a','school-a'),('class-b','school-b')");
$pdo->exec("INSERT INTO student_profiles VALUES ('student-a','class-a','10'),('student-b','class-b','10')");
$insert = $pdo->prepare('INSERT INTO learner_ai_catalog_items VALUES (:id,:type,:category,:title,:summary,:status,:deadline,:eligibility,:capacity,:enrolled,:url,:action,:school,:tenant,:updated)');
$add = static function (string $id, array $overrides = []) use ($insert): void {
    $row = array_replace([
        'id' => $id, 'type' => 'workshop', 'category' => 'career_technical', 'title' => $id, 'summary' => 'Catalog item',
        'status' => 'published', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
        'capacity' => 20, 'enrolled' => 1, 'url' => '/catalog/' . $id, 'action' => '{"type":"register"}',
        'school' => 'school-a', 'tenant' => 'school-a', 'updated' => '2029-01-01 00:00:00',
    ], $overrides);
    $insert->execute($row);
};
$add('workshop-b'); $add('workshop-a');
$add('expired', ['deadline' => '2020-01-01 00:00:00']);
$add('unpublished', ['status' => 'draft']);
$add('full', ['capacity' => 1, 'enrolled' => 1]);
$add('wrong-school', ['school' => 'school-b']);
$add('wrong-tenant', ['tenant' => 'school-b']);
$add('wrong-grade', ['eligibility' => '{"grade_levels":["11"]}']);

$source = new DatabaseCatalogSource($pdo, new DateTimeImmutable('2029-06-01T00:00:00+00:00'));
$records = $source->readForStudent('student-a');
catalog_assert(array_column($records, 'source_id') === ['workshop-a', 'workshop-b'], 'expired, unpublished, full, inaccessible, cross-tenant, and ineligible catalog items are removed before ranking in stable order');
foreach ($records as $record) {
    catalog_assert(isset($record['catalog_id'], $record['item_type'], $record['publish_status'], $record['eligibility'], $record['availability'], $record['url'], $record['updated_at']), 'catalog evidence has the canonical contract fields');
}

$registry = new AiSourceRegistry([$source]);
$withoutConsent = $registry->buildInput('student-a', []);
catalog_assert($withoutConsent->evidenceReferences() === [], 'catalog data respects the activity consent boundary');
$withConsent = $registry->buildInput('student-a', ['activity']);
catalog_assert(array_column($withConsent->evidenceReferences(), 'source_id') === ['workshop-a', 'workshop-b'], 'consented snapshot contains only eligible catalog evidence');
$add('newly-published', ['type' => 'contest', 'updated' => '2029-06-01 01:00:00']);
$refreshed = $registry->buildInput('student-a', ['activity']);
catalog_assert(in_array('newly-published', array_column($refreshed->evidenceReferences(), 'source_id'), true), 'newly published catalog records appear on the next snapshot without prompt or code edits');

$repository = new DatabaseCatalogRepository($pdo);
foreach (['community', 'contest', 'group', 'project', 'skill_resource', 'workshop'] as $supportedType) {
    catalog_assert(in_array($supportedType, (new ReflectionClass($repository))->getConstant('ITEM_TYPES'), true), "{$supportedType} is writable through the canonical catalog boundary");
}
$repository->create([
    'catalog_id' => 'created-group', 'item_type' => 'group', 'category' => 'career_technical', 'title' => 'Created group',
    'summary' => 'Created through the mutation boundary.', 'publish_status' => 'published', 'deadline_at' => '2030-01-01 00:00:00',
    'eligibility_json' => '{"grade_levels":["10"]}', 'capacity' => 10, 'enrolled_count' => 0,
    'url' => '/catalog/created-group', 'action_json' => '{"type":"join_group"}', 'school_id' => 'school-a',
]);
catalog_assert(in_array('created-group', array_column($source->readForStudent('student-a'), 'source_id'), true), 'new catalog item is persisted and immediately readable');
catalog_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_data_outbox WHERE aggregate_id='created-group' AND event_type='catalog.created'")->fetchColumn() === 1, 'catalog create and outbox event are committed together');
catalog_assert($pdo->query("SELECT tenant_id FROM learner_ai_data_outbox WHERE aggregate_id='created-group'")->fetchColumn() === 'school-a', 'catalog writer derives the production tenant from school when no separate learner tenant column exists');
$mapped = (new RecommendationResponseMapper())->run(['status' => 'completed', 'engineType' => 'rule', 'items' => [[
    'itemId' => 'persisted-item', 'itemType' => 'activity', 'title' => 'Persisted catalog', 'summary' => 'Persisted', 'priority' => 1, 'confidenceBand' => 'medium', 'actionJson' => '{}',
    'evidence' => [['sourceType' => 'catalog', 'sourceId' => 'created-group', 'observedAt' => '2029-01-01', 'contributionLabel' => 'catalog_match', 'safeValueJson' => '{"url":"/catalog/created-group"}']],
]]]);
catalog_assert(($mapped['items'][0]['evidence'][0]['safe_value']['url'] ?? null) === '/catalog/created-group', 'persisted camelCase/JSON evidence is normalized for the live catalog CTA');
$jobs = new InMemoryAiRefreshJobRepository();
$consumer = new AiDataOutboxConsumer(new DatabaseAiDataOutboxRepository($pdo), new AiRefreshDispatcher($jobs), static fn (string $studentId, string $capability): string => hash('sha256', $studentId . ':' . $capability));
catalog_assert($consumer->consume() >= 1 && count($jobs->all()) >= 3, 'catalog create outbox is consumed into learner refresh jobs');
$repository->update('workshop-b', ['title' => 'Updated workshop'], ['student-a']);
catalog_assert($pdo->query("SELECT title FROM learner_ai_catalog_items WHERE catalog_id='workshop-b'")->fetchColumn() === 'Updated workshop', 'catalog updates use the canonical repository');
catalog_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_data_outbox WHERE aggregate_id='workshop-b' AND event_type='catalog.updated'")->fetchColumn() === 1, 'catalog update writes one transactional outbox refresh event');
$repository->archive('workshop-a', ['student-a']);
catalog_assert(!in_array('workshop-a', array_column($source->readForStudent('student-a'), 'source_id'), true), 'archive is immediately excluded from catalog reads');
catalog_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_data_outbox WHERE aggregate_type='learner_ai_catalog_item' AND aggregate_id='workshop-a' AND event_type='catalog.archived'")->fetchColumn() === 1, 'catalog archive writes one transactional outbox refresh event');
$repository->update('workshop-b', ['summary' => 'Scoped update'], ['student-a', 'student-b']);
$scopedEvent = $pdo->query("SELECT affected_student_ids, tenant_id FROM learner_ai_data_outbox WHERE aggregate_id='workshop-b' AND event_type='catalog.updated' ORDER BY occurred_at DESC, id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
catalog_assert(json_decode((string) $scopedEvent['affected_student_ids'], true) === ['student-a'] && $scopedEvent['tenant_id'] === 'school-a', 'caller-supplied students are intersected with the production school tenant audience and tenant is persisted');

echo "learner_ai_catalog_recommendation_test: OK\n";
