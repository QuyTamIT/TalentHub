<?php

declare(strict_types=1);

use TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2;

function v2_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$root = dirname(__DIR__);
$datasetFile = $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php';
v2_contract_assert(is_file($datasetFile), 'V2 dataset class exists');
require_once $datasetFile;

LearnerAiSyntheticDatasetV2::validate();
$participants = LearnerAiSyntheticDatasetV2::participants();
$questions = LearnerAiSyntheticDatasetV2::questions();
$rows = LearnerAiSyntheticDatasetV2::rows();

v2_contract_assert(count($participants) === 24, 'exactly 24 participants');
v2_contract_assert(count(array_unique(array_column($participants, 'student_id'))) === 24, 'participant IDs are distinct');
v2_contract_assert(array_count_values(array_column($participants, 'primary')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'RIASEC is balanced');
v2_contract_assert(array_count_values(array_column($participants, 'expected_state')) === ['ready' => 18, 'insufficient_data' => 4, 'consent_required' => 2], 'state matrix is exact');
v2_contract_assert(count($questions) === 24, 'exactly 24 questions');
v2_contract_assert(array_count_values(array_column($questions, 'dimension')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'four questions per dimension');
v2_contract_assert(count($rows) === 1116, 'V2 declares the fixed 1116-row contract');
v2_contract_assert(preg_match('/^[a-f0-9]{64}$/', LearnerAiSyntheticDatasetV2::contentHash()) === 1, 'content fingerprint is SHA-256');

$rowKeys = [];
foreach ($rows as $row) {
    $key = $row['table'] . "\0" . $row['id'];
    v2_contract_assert(!isset($rowKeys[$key]), 'table/id pairs are unique');
    $rowKeys[$key] = true;
    v2_contract_assert(($row['values']['id'] ?? null) === $row['id'], 'row id is declared in values');
    foreach ($row['values'] as $value) {
        if (is_string($value) && str_contains($value, '@')) {
            v2_contract_assert(preg_match('/@(?:[A-Za-z0-9-]+\.)*example$/', $value) === 1, 'email-like values use .example only');
        }
    }
}

$source = file_get_contents($datasetFile);
v2_contract_assert(is_string($source), 'dataset source is readable');
foreach (['UPDATE ', 'DELETE ', 'REPLACE ', 'DROP ', 'TRUNCATE ', 'ALTER '] as $forbidden) {
    v2_contract_assert(stripos($source, $forbidden) === false, 'dataset contains no destructive or mutable SQL token: ' . trim($forbidden));
}

$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md';
v2_contract_assert(is_file($dcrPath), 'V2 DCR exists');
$dcr = file_get_contents($dcrPath);
v2_contract_assert(is_string($dcr), 'V2 DCR is readable');
v2_contract_assert(str_contains($dcr, '`talenthub_ai_backup_verify_004_20260816`'), 'DCR pins the approved disposable schema');
v2_contract_assert(str_contains($dcr, '`' . LearnerAiSyntheticDatasetV2::contentHash() . '`'), 'DCR records the exact dataset fingerprint');
v2_contract_assert(str_contains($dcr, '1116'), 'DCR records the exact V2 row count');
v2_contract_assert(!str_contains($dcr, 'talenthub_local` is approved'), 'DCR never approves the shared schema');

echo 'learner_ai_synthetic_dataset_v2_contract_test: OK' . PHP_EOL;
