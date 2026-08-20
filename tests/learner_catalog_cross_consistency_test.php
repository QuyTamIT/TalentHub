<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;
use TalentHub\Learner\Assessment\Validator\CatalogValidationException;
use TalentHub\Learner\Assessment\Validator\LearnerCatalogContentValidator;
use TalentHub\Support\Uuid;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/src/Support/Uuid.php';
require_once __DIR__ . '/learner_catalog_content_validator.php';

$crossAssertionsCount = 0;

function cross_assert(bool $condition, string $message): void
{
    global $crossAssertionsCount;
    if (!$condition) {
        fwrite(STDERR, "CROSS-CONSISTENCY ASSERTION FAILED: {$message}\n");
        exit(1);
    }
    $crossAssertionsCount++;
}

function cross_expect_exception(callable $callback, string $expectedClass, string $message): void
{
    global $crossAssertionsCount;
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $expectedClass) {
            $crossAssertionsCount++;
            return;
        }
        $actual = get_class($e);
        fwrite(STDERR, "CROSS-CONSISTENCY FAILED (expected {$expectedClass}, got {$actual} '{$e->getMessage()}'): {$message}\n");
        exit(1);
    }
    fwrite(STDERR, "CROSS-CONSISTENCY FAILED (no exception thrown, expected {$expectedClass}): {$message}\n");
    exit(1);
}

echo "=== STARTING CROSS-CATALOG CONSISTENCY VALIDATOR TEST SUITE ===\n\n";

$registry = new ScorerRegistry([
    'holland-riasec-1.0' => new HollandScorer(),
    'mbti-education-1.0' => new MbtiScorer(),
    'disc-education-1.0' => new DiscScorer(),
    'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
]);

$frameworks = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
$bands = ['middle', 'high', 'college'];

$catalogFileMap = [
    'holland' => [
        'middle' => 'Database/seeds/learner/Assessment/HollandCatalogMiddle.php',
        'high' => 'Database/seeds/learner/Assessment/HollandCatalogHigh.php',
        'college' => 'Database/seeds/learner/Assessment/HollandCatalogCollege.php',
    ],
    'mbti' => [
        'middle' => 'Database/seeds/learner/Assessment/MbtiCatalogMiddle.php',
        'high' => 'Database/seeds/learner/Assessment/MbtiCatalogHigh.php',
        'college' => 'Database/seeds/learner/Assessment/MbtiCatalogCollege.php',
    ],
    'disc' => [
        'middle' => 'Database/seeds/learner/Assessment/DiscCatalogMiddle.php',
        'high' => 'Database/seeds/learner/Assessment/DiscCatalogHigh.php',
        'college' => 'Database/seeds/learner/Assessment/DiscCatalogCollege.php',
    ],
    'multiple_intelligence' => [
        'middle' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogMiddle.php',
        'high' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogHigh.php',
        'college' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogCollege.php',
    ],
];

$expectedQuestionCounts = [
    'holland' => 30,
    'mbti' => 32,
    'disc' => 28,
    'multiple_intelligence' => 32,
];

$expectedScoringVersions = [
    'holland' => 'holland-riasec-1.0',
    'mbti' => 'mbti-education-1.0',
    'disc' => 'disc-education-1.0',
    'multiple_intelligence' => 'multiple-intelligence-1.0',
];

$expectedDisclaimers = [
    'holland' => 'Kết quả Holland chỉ mang tính định hướng nghề nghiệp, không phải chẩn đoán tâm lý hay xác nhận nghề nghiệp.',
    'mbti' => 'Đây là bộ câu hỏi định hướng học tập nội bộ, không phải công cụ MBTI chính thức hay đánh giá tâm lý.',
    'disc' => 'Kết quả DISC chỉ mang tính tham khảo cho giao tiếp và làm việc nhóm, không phải công cụ đánh giá nhân sự.',
    'multiple_intelligence' => 'Định hướng đa trí thông minh giúp chọn trải nghiệm học tập, không phải chỉ số năng lực hay chẩn đoán.',
];

// --- SECTION 1: LOAD & DISCOVER ALL 12 REAL CATALOG FILES ---
echo "--- Section 1: Catalog Registry & File Integrity Discovery ---\n";
$loadedCatalogs = [];
$totalCatalogsCount = 0;

foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $relPath = $catalogFileMap[$fw][$band];
        $fullPath = dirname(__DIR__) . '/' . $relPath;
        cross_assert(file_exists($fullPath), "Catalog file must exist: {$relPath}");

        $catalog = LearnerCatalogContentValidator::loadCatalogFile($fullPath);
        cross_assert(is_array($catalog), "Catalog must return array: {$relPath}");
        cross_assert(isset($catalog['metadata']) && is_array($catalog['metadata']), "Catalog metadata must be array: {$relPath}");
        cross_assert(isset($catalog['questions']) && is_array($catalog['questions']), "Catalog questions must be array: {$relPath}");

        $loadedCatalogs[$fw][$band] = $catalog;
        $totalCatalogsCount++;
    }
}
cross_assert($totalCatalogsCount === 12, "Exactly 12 catalog files must be loaded (got {$totalCatalogsCount})");
echo "Section 1 PASS: All 12 real catalog files discovered and loaded.\n\n";

// --- SECTION 2: QUESTION COUNTS & EDUCATION BAND ALLOCATIONS ---
echo "--- Section 2: Question Counts & Education Band Allocations ---\n";
$totalQuestionsAll = 0;
$bandQuestionsCount = array_fill_keys($bands, 0);
$frameworkQuestionsCount = array_fill_keys($frameworks, 0);

foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $catalog = $loadedCatalogs[$fw][$band];
        $qCount = count($catalog['questions']);
        $expected = $expectedQuestionCounts[$fw];
        cross_assert($qCount === $expected, "Catalog {$fw}_{$band} must have {$expected} questions (got {$qCount})");
        cross_assert($catalog['metadata']['question_count'] === $expected, "Metadata question_count for {$fw}_{$band} must be {$expected}");

        $totalQuestionsAll += $qCount;
        $bandQuestionsCount[$band] += $qCount;
        $frameworkQuestionsCount[$fw] += $qCount;
    }
}

cross_assert($totalQuestionsAll === 366, "Grand total across all 12 catalogs must be exactly 366 questions (got {$totalQuestionsAll})");
cross_assert($frameworkQuestionsCount['holland'] === 90, "Holland total must be 90 questions");
cross_assert($frameworkQuestionsCount['mbti'] === 96, "MBTI total must be 96 questions");
cross_assert($frameworkQuestionsCount['disc'] === 84, "DISC total must be 84 questions");
cross_assert($frameworkQuestionsCount['multiple_intelligence'] === 96, "MI total must be 96 questions");

cross_assert($bandQuestionsCount['middle'] === 122, "Middle band total must be 122 questions");
cross_assert($bandQuestionsCount['high'] === 122, "High band total must be 122 questions");
cross_assert($bandQuestionsCount['college'] === 122, "College band total must be 122 questions");

echo "Section 2 PASS: All question counts (366 total, 122 per band, 90/96/84/96 per framework) verified.\n\n";

// --- SECTION 3: METADATA SCHEMA & CONTRACT COMPLIANCE ---
echo "--- Section 3: Metadata Schema & Contract Compliance ---\n";
foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $meta = $loadedCatalogs[$fw][$band]['metadata'];
        $ctx = "{$fw}_{$band}";

        cross_assert($meta['framework'] === $fw, "Metadata framework must match {$fw} in {$ctx}");
        cross_assert($meta['education_band'] === $band, "Metadata education_band must match {$band} in {$ctx}");
        cross_assert($meta['scoring_version'] === $expectedScoringVersions[$fw], "Metadata scoring_version must match {$expectedScoringVersions[$fw]} in {$ctx}");
        cross_assert($meta['stable_code_namespace'] === "{$fw}_{$band}_", "Metadata namespace must match {$fw}_{$band}_ in {$ctx}");
        cross_assert($meta['review_state'] === 'published', "Metadata review_state must be published in {$ctx}");
        cross_assert(is_array($meta['review_events']) && count($meta['review_events']) >= 6, "Metadata review_events must contain all approval checkpoints in {$ctx}");
        $checkpoints = array_map(static fn (array $event): string => (string) ($event['checkpoint'] ?? ''), $meta['review_events']);
        foreach (['content_review', 'educational_review', 'bias_review', 'scoring_review', 'product_owner_approval', 'codex_schema_review'] as $checkpoint) {
            cross_assert(in_array($checkpoint, $checkpoints, true), "Missing {$checkpoint} in {$ctx}");
        }
        cross_assert(is_string($meta['schema_hash']) && preg_match('/\A[0-9a-f]{64}\z/', $meta['schema_hash']) === 1, "Metadata schema_hash must be 64 hex chars in {$ctx}");
        cross_assert($meta['advisory_disclaimer'] === $expectedDisclaimers[$fw], "Metadata advisory_disclaimer must match exact contract in {$ctx}");
    }
}
echo "Section 3 PASS: Metadata schema & contracts verified for all 12 catalogs.\n\n";

// --- SECTION 4: CANONICAL SCHEMA HASH INVARIANCE & SENSITIVITY ---
echo "--- Section 4: Canonical Schema Hash Invariance & Sensitivity ---\n";
foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $questions = $loadedCatalogs[$fw][$band]['questions'];
        $declared = $loadedCatalogs[$fw][$band]['metadata']['schema_hash'];
        $ctx = "{$fw}_{$band}";

        $computed1 = LearnerCatalogContentValidator::computeCanonicalSchemaHash($questions);
        $computed2 = LearnerCatalogContentValidator::computeCanonicalSchemaHash($questions);

        cross_assert($computed1 === $declared, "Computed schema hash must match declared in {$ctx}");
        cross_assert($computed1 === $computed2, "Schema hash computation must be 100% deterministic in {$ctx}");

        // Hash sensitivity check: mutating one character in content changes hash
        $mutated = $questions;
        $mutated[0]['content'] .= '.';
        $mutatedHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($mutated);
        cross_assert($mutatedHash !== $computed1, "Schema hash must be sensitive to content changes in {$ctx}");
    }
}
echo "Section 4 PASS: Canonical schema hash deterministic & invariant for all 12 catalogs.\n\n";

// --- SECTION 5: UUID, STABLE CODE & PROMPT GLOBAL UNIQUENESS ---
echo "--- Section 5: UUID, Stable Code & Prompt Global Uniqueness (Cross-Catalog) ---\n";
$allGlobalUuids = [];
$allGlobalCodes = [];
$allGlobalPromptHashes = [];

foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $catalog = $loadedCatalogs[$fw][$band];
        $namespace = "{$fw}_{$band}_";
        $maxLen = LearnerCatalogContentValidator::MAX_CONTENT_LENGTH[$band];
        $seenPositionsInCat = [];

        foreach ($catalog['questions'] as $qIdx => $q) {
            $qId = $q['id'];
            $qCode = $q['code'];
            $pos = $q['position'];
            $req = $q['required'];
            $content = $q['content'];
            $options = $q['options'];
            $dimCode = $q['dimension_code'];

            // 1. UUID check
            cross_assert(Uuid::isValid($qId), "UUID '{$qId}' must be valid canonical hex UUID in {$qCode}");
            $prevUuidCode = $allGlobalUuids[$qId] ?? '';
            cross_assert(!isset($allGlobalUuids[$qId]), "Global duplicate UUID '{$qId}' detected in {$qCode} (previously seen in {$prevUuidCode})");
            $allGlobalUuids[$qId] = $qCode;

            // 2. Stable Code check
            cross_assert(str_starts_with($qCode, $namespace), "Question code '{$qCode}' must start with namespace '{$namespace}'");
            cross_assert(!isset($allGlobalCodes[$qCode]), "Global duplicate question code '{$qCode}' detected");
            $allGlobalCodes[$qCode] = true;

            // 3. Position check
            cross_assert(is_int($pos) && $pos >= 1, "Position must be positive int in {$qCode}");
            cross_assert(!in_array($pos, $seenPositionsInCat, true), "Position {$pos} must be unique in {$fw}_{$band}");
            $seenPositionsInCat[] = $pos;

            // 4. Required flag
            cross_assert($req === true, "Required flag must be boolean true in {$qCode}");

            // 5. Options check
            cross_assert(is_array($options) && count($options) === 5, "Options must contain exactly 5 elements in {$qCode}");
            for ($optI = 0; $optI < 5; $optI++) {
                cross_assert($options[$optI]['value'] === ($optI + 1), "Option value must be " . ($optI + 1) . " in {$qCode}");
                cross_assert($options[$optI]['label'] === LearnerCatalogContentValidator::LIKERT_OPTIONS[$optI]['label'], "Option label must match Likert standard in {$qCode}");
            }

            // 6. Content check
            cross_assert(trim($content) !== '', "Content cannot be empty in {$qCode}");
            $len = mb_strlen($content, 'UTF-8');
            cross_assert($len <= $maxLen, "Content length ({$len}) exceeds max {$maxLen} for {$band} in {$qCode}");

            // 7. Prompt uniqueness (normalized)
            $norm = LearnerCatalogContentValidator::normalizePrompt($content);
            $pHash = hash('sha256', $norm);
            $prevPromptCode = $allGlobalPromptHashes[$pHash] ?? '';
            cross_assert(!isset($allGlobalPromptHashes[$pHash]), "Duplicate normalized prompt detected in '{$qCode}' (matches '{$prevPromptCode}')");
            $allGlobalPromptHashes[$pHash] = $qCode;
        }

        // Contiguous 1..N positions check
        sort($seenPositionsInCat);
        $expectedPositions = range(1, count($catalog['questions']));
        cross_assert($seenPositionsInCat === $expectedPositions, "Positions must be contiguous 1..N without gaps in {$fw}_{$band}");
    }
}

cross_assert(count($allGlobalUuids) === 366, "Exactly 366 unique UUIDs across all catalogs");
cross_assert(count($allGlobalCodes) === 366, "Exactly 366 unique question codes across all catalogs");
cross_assert(count($allGlobalPromptHashes) === 366, "Exactly 366 unique prompts across all catalogs");
echo "Section 5 PASS: All 366 UUIDs, codes, and prompts globally unique and valid.\n\n";

// --- SECTION 6: FRAMEWORK DIMENSION & REVERSE BALANCE RULES ---
echo "--- Section 6: Framework Dimension & Reverse Balance Rules ---\n";
foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $catalog = $loadedCatalogs[$fw][$band];
        $ctx = "{$fw}_{$band}";

        if ($fw === 'holland') {
            $dimCounts = array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], 0);
            $dimRevCounts = array_fill_keys(['R', 'I', 'A', 'S', 'E', 'C'], 0);
            foreach ($catalog['questions'] as $q) {
                cross_assert(preg_match('/\A([RIASEC])(?::([+-]))?\z/', $q['dimension_code'], $m) === 1, "Holland dimension_code valid in {$q['code']}");
                $dim = $m[1];
                $dimCounts[$dim]++;
                if (($m[2] ?? '+') === '-') {
                    $dimRevCounts[$dim]++;
                }
            }
            foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $dim) {
                cross_assert($dimCounts[$dim] === 5, "Holland dimension {$dim} has 5 questions in {$ctx}");
                $rev = $dimRevCounts[$dim];
                cross_assert($rev === 2 || $rev === 3, "Holland dimension {$dim} has 2 or 3 reverse items in {$ctx} (got {$rev})");
            }
        } elseif ($fw === 'mbti') {
            $poleCounts = array_fill_keys(['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'], 0);
            foreach ($catalog['questions'] as $q) {
                cross_assert(preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/', $q['dimension_code'], $m) === 1, "MBTI dimension_code valid in {$q['code']}");
                $axis = $m[1];
                $pole = $m[2];
                cross_assert(in_array($pole, LearnerCatalogContentValidator::MBTI_AXES[$axis], true), "MBTI pole {$pole} belongs to {$axis} in {$q['code']}");
                $poleCounts[$pole]++;
            }
            foreach (['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'] as $pole) {
                cross_assert($poleCounts[$pole] === 4, "MBTI pole {$pole} has exactly 4 questions in {$ctx}");
            }
        } elseif ($fw === 'disc') {
            $dimCounts = array_fill_keys(['D', 'I', 'S', 'C'], 0);
            $dimRevCounts = array_fill_keys(['D', 'I', 'S', 'C'], 0);
            foreach ($catalog['questions'] as $q) {
                cross_assert(preg_match('/\A([DISC])(?::([+-]))?\z/', $q['dimension_code'], $m) === 1, "DISC dimension_code valid in {$q['code']}");
                $dim = $m[1];
                $dimCounts[$dim]++;
                if (($m[2] ?? '+') === '-') {
                    $dimRevCounts[$dim]++;
                }
            }
            foreach (['D', 'I', 'S', 'C'] as $dim) {
                cross_assert($dimCounts[$dim] === 7, "DISC dimension {$dim} has 7 questions in {$ctx}");
                $rev = $dimRevCounts[$dim];
                cross_assert($rev === 3 || $rev === 4, "DISC dimension {$dim} has 3 or 4 reverse items in {$ctx} (got {$rev})");
            }
        } elseif ($fw === 'multiple_intelligence') {
            $miDims = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
            $dimCounts = array_fill_keys($miDims, 0);
            $dimRevCounts = array_fill_keys($miDims, 0);
            foreach ($catalog['questions'] as $q) {
                cross_assert(preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/', $q['dimension_code'], $m) === 1, "MI dimension_code valid in {$q['code']}");
                $dim = $m[1];
                $dimCounts[$dim]++;
                if (($m[2] ?? '+') === '-') {
                    $dimRevCounts[$dim]++;
                }
            }
            foreach ($miDims as $dim) {
                cross_assert($dimCounts[$dim] === 4, "MI dimension {$dim} has 4 questions in {$ctx}");
                $rev = $dimRevCounts[$dim];
                cross_assert($rev === 2, "MI dimension {$dim} has exactly 2 reverse items in {$ctx} (got {$rev})");
            }
        }
    }
}
echo "Section 6 PASS: Framework dimensions & reverse balances verified.\n\n";

// --- SECTION 7: REAL-CATALOG SCORER DETERMINISM & CONTRACTS ---
echo "--- Section 7: Real-Catalog Scorer Determinism & Contracts ---\n";
foreach ($frameworks as $fw) {
    $version = $expectedScoringVersions[$fw];
    $scorer = $registry->forVersion($version);

    foreach ($bands as $band) {
        $catalog = $loadedCatalogs[$fw][$band];
        $ctx = "{$fw}_{$band}";

        $scorerQuestions = [];
        $answersPositive = [];
        $answersTied = [];

        foreach ($catalog['questions'] as $q) {
            $scorerQuestions[] = [
                'question_id' => $q['id'],
                'dimension_code' => $q['dimension_code'],
                'required' => 1,
            ];
            $answersPositive[$q['id']] = 4;
            $answersTied[$q['id']] = 3;
        }

        // 1. Determinism test
        $res1 = $scorer->score($scorerQuestions, $answersPositive);
        $res2 = $scorer->score($scorerQuestions, $answersPositive);
        cross_assert($res1 instanceof ScoringResult, "score() must return ScoringResult for {$ctx}");
        cross_assert($res2 instanceof ScoringResult, "score() must return ScoringResult for {$ctx}");
        cross_assert($res1->toArray() === $res2->toArray(), "Scoring must be 100% deterministic for {$ctx}");

        $data1 = $res1->toArray();
        cross_assert(is_string($data1['result_code']) && trim($data1['result_code']) !== '', "Result code must be non-empty string in {$ctx}");
        cross_assert(is_string($data1['summary']) && trim($data1['summary']) !== '', "Summary must be non-empty string in {$ctx}");
        cross_assert(is_array($data1['dimension_scores']), "dimension_scores must be array in {$ctx}");

        foreach ($data1['dimension_scores'] as $dKey => $dScore) {
            cross_assert(is_int($dScore), "Dimension score {$dKey} must be int in {$ctx}");
            cross_assert($dScore >= 0 && $dScore <= 100, "Dimension score {$dKey} ({$dScore}) must be between 0 and 100 in {$ctx}");
        }

        // 2. Result code format test
        if ($fw === 'holland') {
            cross_assert(preg_match('/\A[RIASEC]{3}\z/', $data1['result_code']) === 1, "Holland result_code must be 3 RIASEC chars in {$ctx}, got '{$data1['result_code']}'");
            $chars = str_split($data1['result_code']);
            cross_assert(count($chars) === 3 && count(array_unique($chars)) === 3, "Holland result_code must have 3 distinct chars in {$ctx}");
        } elseif ($fw === 'mbti') {
            cross_assert(preg_match('/\A[EI][SN][TF][JP]\z/', $data1['result_code']) === 1, "MBTI result_code must match 4-letter type in {$ctx}, got '{$data1['result_code']}'");
        } elseif ($fw === 'disc') {
            cross_assert(preg_match('/\A[DISC]{4}\z/', $data1['result_code']) === 1, "DISC result_code must match 4 DISC chars in {$ctx}, got '{$data1['result_code']}'");
            $chars = str_split($data1['result_code']);
            cross_assert(count($chars) === 4 && count(array_unique($chars)) === 4, "DISC result_code must be permutation of DISC in {$ctx}");
        } elseif ($fw === 'multiple_intelligence') {
            cross_assert(preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)-(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)-(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)\z/', $data1['result_code']) === 1, "MI result_code must match top-3 dash format in {$ctx}, got '{$data1['result_code']}'");
            $dims = explode('-', $data1['result_code']);
            cross_assert(count($dims) === 3 && count(array_unique($dims)) === 3, "MI result_code must have 3 distinct dims in {$ctx}");
        }

        // 3. Stable tie-break test
        $resTied = $scorer->score($scorerQuestions, $answersTied)->toArray();
        if ($fw === 'holland') {
            cross_assert($resTied['result_code'] === 'RIA', "Holland tie-break must be RIA in {$ctx}, got '{$resTied['result_code']}'");
        } elseif ($fw === 'mbti') {
            cross_assert($resTied['result_code'] === 'ESTJ', "MBTI tie-break must be ESTJ in {$ctx}, got '{$resTied['result_code']}'");
        } elseif ($fw === 'disc') {
            cross_assert($resTied['result_code'] === 'DISC', "DISC tie-break must be DISC in {$ctx}, got '{$resTied['result_code']}'");
        } elseif ($fw === 'multiple_intelligence') {
            cross_assert($resTied['result_code'] === 'LING-LOGI-SPAT', "MI tie-break must be LING-LOGI-SPAT in {$ctx}, got '{$resTied['result_code']}'");
        }

        // 4. Missing required answer test
        $partialAnswers = $answersPositive;
        unset($partialAnswers[$catalog['questions'][0]['id']]);
        cross_expect_exception(
            static fn () => $scorer->score($scorerQuestions, $partialAnswers),
            \RuntimeException::class,
            "Missing required answer must throw RuntimeException in {$ctx}"
        );
    }
}
echo "Section 7 PASS: Real-catalog scorer determinism & contract compliance verified.\n\n";

// --- SECTION 8: FULL CROSS-CATALOG AGGREGATE VALIDATION ---
echo "--- Section 8: Full Cross-Catalog Aggregate Validation ---\n";
$all12Flat = [];
foreach ($frameworks as $fw) {
    foreach ($bands as $band) {
        $all12Flat[] = $loadedCatalogs[$fw][$band];
    }
}

$summary = LearnerCatalogContentValidator::validateCatalogs($all12Flat);
cross_assert($summary['total_catalogs'] === 12, "validateCatalogs must validate 12 catalogs");
cross_assert($summary['total_questions'] === 366, "validateCatalogs must validate 366 total questions");
echo "Section 8 PASS: validateCatalogs passed for all 12 catalogs (366 questions).\n\n";

echo "=== ALL CHECKS PASSED: {$crossAssertionsCount} ASSERTIONS COMPLETED SUCCESSFULLY ===\n";
echo "learner_catalog_cross_consistency_test: OK\n";
