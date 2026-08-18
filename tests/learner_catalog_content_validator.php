<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Validator;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Support\Uuid;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/src/Support/Uuid.php';

final class CatalogValidationException extends RuntimeException
{
}

final class LearnerCatalogContentValidator
{
    public const FRAMEWORKS = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
    public const BANDS = ['middle', 'high', 'college'];

    public const SCORING_VERSIONS = [
        'holland' => 'holland-riasec-1.0',
        'mbti' => 'mbti-education-1.0',
        'disc' => 'disc-education-1.0',
        'multiple_intelligence' => 'multiple-intelligence-1.0',
    ];

    public const REVIEW_STATES = [
        'draft',
        'content_review',
        'educational_review',
        'bias_review',
        'scoring_review',
        'approved',
        'published',
    ];

    /** Review checkpoints that may be recorded in catalog metadata. */
    public const REVIEW_CHECKPOINTS = [
        'content_review',
        'educational_review',
        'bias_review',
        'scoring_review',
        'product_owner_approval',
        'codex_schema_review',
    ];

    public const MAX_CONTENT_LENGTH = [
        'middle' => 60,
        'high' => 80,
        'college' => 100,
    ];

    public const DISCLAIMERS = [
        'holland' => 'Kết quả Holland chỉ mang tính định hướng nghề nghiệp, không phải chẩn đoán tâm lý hay xác nhận nghề nghiệp.',
        'mbti' => 'Đây là bộ câu hỏi định hướng học tập nội bộ, không phải công cụ MBTI chính thức hay đánh giá tâm lý.',
        'disc' => 'Kết quả DISC chỉ mang tính tham khảo cho giao tiếp và làm việc nhóm, không phải công cụ đánh giá nhân sự.',
        'multiple_intelligence' => 'Định hướng đa trí thông minh giúp chọn trải nghiệm học tập, không phải chỉ số năng lực hay chẩn đoán.',
    ];

    public const LIKERT_OPTIONS = [
        ['value' => 1, 'label' => 'Hoàn toàn không đồng ý'],
        ['value' => 2, 'label' => 'Không đồng ý'],
        ['value' => 3, 'label' => 'Bình thường'],
        ['value' => 4, 'label' => 'Đồng ý'],
        ['value' => 5, 'label' => 'Hoàn toàn đồng ý'],
    ];

    public const FRAMEWORK_DIMENSIONS = [
        'holland' => ['R', 'I', 'A', 'S', 'E', 'C'],
        'disc' => ['D', 'I', 'S', 'C'],
        'multiple_intelligence' => ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'],
        'mbti' => ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'],
    ];

    public const MBTI_AXES = [
        'EI' => ['E', 'I'],
        'SN' => ['S', 'N'],
        'TF' => ['T', 'F'],
        'JP' => ['J', 'P'],
    ];

    /**
     * Banned & sensitive patterns (religion, ethnicity, disability, sexual orientation, politics, wealth/poverty, medical, crime).
     */
    private const BANNED_SENSITIVE_PATTERNS = [
        'religion' => '/\b(tôn giáo|tín ngưỡng|đạo phật|đạo thiên chúa|đạo hồi|phật giáo|kitô giáo|công giáo|nhà thờ|chùa chiền|giáo xứ)\b/ui',
        'ethnicity' => '/\b(dân tộc thiểu số|sắc tộc|chủng tộc|người kinh|h\'mông|hmông|h\s*mông|tày|nùng|dao|khmer|hoa)\b/ui',
        'disability' => '/\b(khuyết tật|tàn tật|dị tật|khiếm thị|khiếm thính|tâm thần|bại liệt|thiểu năng)\b/ui',
        'sexual_orientation' => '/\b(đồng tính|dị tính|song tính|chuyển giới|lgbt|lgbtq|xu hướng tính dục)\b/ui',
        'politics' => '/\b(đảng phái|chính trị|đảng viên|đoàn viên chính trị|tư tưởng chính trị|đối lập chính trị)\b/ui',
        'financial' => '/\b(thu nhập gia đình|nghèo khó|giàu có|gia cảnh nghèo|gia cảnh giàu|hoàn cảnh kinh tế gia đình|sổ hộ nghèo|nợ nần|phá sản|tài sản gia đình|tiền bạc của cha mẹ)\b/ui',
        'family_medical' => '/\b(bệnh di truyền|tiền sử gia đình|bệnh tâm thần gia đình|gen di truyền)\b/ui',
        'criminal' => '/\b(tiền án|tiền sự|phạm tội|vào tù|tù tội|vi phạm pháp luật hình sự)\b/ui',
        'protected_group' => '/\b(giới tính|nam hay nữ|nam\/nữ|tuổi tác|bao nhiêu tuổi|quốc tịch|quê quán|tình trạng hôn nhân|đã kết hôn|mang thai|sắc tộc|nhóm thiểu số|người bản địa|xu hướng tính dục)\b/ui',
    ];

    /**
     * Absolute career conclusions / diagnostic certainty.
     */
    private const DIAGNOSTIC_CERTAINTY_PATTERN = '/(bạn chắc chắn phù hợp với|chắc chắn sẽ trở thành|bạn mắc chứng|chẩn đoán|bệnh lý|tuyệt đối phù hợp|chắc chắn thành công|định mệnh của bạn|chắc chắn bạn là)/ui';

    /**
     * Double negative phrases.
     */
    private const DOUBLE_NEGATIVE_PATTERN = '/(không bao giờ không|không phải là không|không phải không|chẳng phải không|không thể không|không hề không|chẳng thể không)/ui';

    /**
     * @param array<string,mixed> $catalog
     * @param array<string,string> $crossCatalogPromptHashes Map of normalizedHash => questionCode
     * @return array{valid:true,schema_hash:string,prompt_hashes:array<string,string>}
     */
    public static function validate(array $catalog, array $crossCatalogPromptHashes = []): array
    {
        // 1. Structure checks
        if (!array_key_exists('metadata', $catalog)) {
            throw new CatalogValidationException("Catalog missing required key 'metadata'.");
        }
        if (!array_key_exists('questions', $catalog)) {
            throw new CatalogValidationException("Catalog missing required key 'questions'.");
        }
        if (!is_array($catalog['metadata'])) {
            throw new CatalogValidationException("'metadata' must be an associative array.");
        }
        if (!is_array($catalog['questions']) || !array_is_list($catalog['questions'])) {
            throw new CatalogValidationException("'questions' must be a list of arrays.");
        }

        $metadata = $catalog['metadata'];
        $questions = $catalog['questions'];

        // 2. Metadata keys check
        $requiredMetaKeys = [
            'framework',
            'education_band',
            'scoring_version',
            'question_count',
            'stable_code_namespace',
            'review_state',
            'review_events',
            'schema_hash',
            'advisory_disclaimer',
        ];
        foreach ($requiredMetaKeys as $key) {
            if (!array_key_exists($key, $metadata)) {
                throw new CatalogValidationException("Metadata missing required key '{$key}'.");
            }
        }

        // Framework check
        if (!is_string($metadata['framework'])) {
            throw new CatalogValidationException("Metadata 'framework' must be a string.");
        }
        $framework = $metadata['framework'];
        if (!in_array($framework, self::FRAMEWORKS, true)) {
            throw new CatalogValidationException("Invalid framework '{$framework}'. Allowed: " . implode(', ', self::FRAMEWORKS));
        }

        // Education band check
        if (!is_string($metadata['education_band'])) {
            throw new CatalogValidationException("Metadata 'education_band' must be a string.");
        }
        $band = $metadata['education_band'];
        if (!in_array($band, self::BANDS, true)) {
            throw new CatalogValidationException("Invalid education band '{$band}'. Allowed: " . implode(', ', self::BANDS));
        }

        // Scoring version check
        $expectedScoringVersion = self::SCORING_VERSIONS[$framework];
        if (!is_string($metadata['scoring_version'])) {
            throw new CatalogValidationException("Metadata 'scoring_version' must be a string.");
        }
        $scoringVersion = $metadata['scoring_version'];
        if ($scoringVersion !== $expectedScoringVersion) {
            throw new CatalogValidationException("Scoring version '{$scoringVersion}' does not match framework '{$framework}' (expected '{$expectedScoringVersion}').");
        }

        // Question count check
        if (!is_int($metadata['question_count']) || $metadata['question_count'] < 1) {
            throw new CatalogValidationException("Metadata 'question_count' must be a positive integer.");
        }
        $questionCount = $metadata['question_count'];
        $actualCount = count($questions);
        if ($questionCount !== $actualCount) {
            throw new CatalogValidationException("Metadata question_count ({$questionCount}) does not match actual question count ({$actualCount}).");
        }

        // Stable code namespace check
        $expectedNamespace = "{$framework}_{$band}_";
        if (!is_string($metadata['stable_code_namespace'])) {
            throw new CatalogValidationException("Metadata 'stable_code_namespace' must be a string.");
        }
        $namespace = $metadata['stable_code_namespace'];
        if ($namespace !== $expectedNamespace) {
            throw new CatalogValidationException("Metadata stable_code_namespace '{$namespace}' does not match expected '{$expectedNamespace}'.");
        }

        // Review state check
        if (!is_string($metadata['review_state'])) {
            throw new CatalogValidationException("Metadata 'review_state' must be a string.");
        }
        $reviewState = $metadata['review_state'];
        if (!in_array($reviewState, self::REVIEW_STATES, true)) {
            throw new CatalogValidationException("Invalid review state '{$reviewState}'. Allowed: " . implode(', ', self::REVIEW_STATES));
        }

        // Review events check
        if (!is_array($metadata['review_events']) || !array_is_list($metadata['review_events'])) {
            throw new CatalogValidationException("Metadata 'review_events' must be a list of event objects.");
        }
        foreach ($metadata['review_events'] as $evtIdx => $event) {
            if (!is_array($event)) {
                throw new CatalogValidationException("Review event at index {$evtIdx} must be an associative array.");
            }
            foreach (['checkpoint', 'reviewer', 'approved_at_utc'] as $evtKey) {
                if (!array_key_exists($evtKey, $event)) {
                    throw new CatalogValidationException("Review event at index {$evtIdx} missing required key '{$evtKey}'.");
                }
            }
            if (!is_string($event['checkpoint'])) {
                throw new CatalogValidationException("Review event checkpoint must be a string at index {$evtIdx}.");
            }
            $checkpoint = trim($event['checkpoint']);
            if ($checkpoint === '') {
                throw new CatalogValidationException("Review event checkpoint cannot be empty at index {$evtIdx}.");
            }
            if (!in_array($checkpoint, self::REVIEW_CHECKPOINTS, true)) {
                throw new CatalogValidationException("Review event checkpoint '{$checkpoint}' is not recognized at index {$evtIdx}.");
            }
            if (!is_string($event['reviewer'])) {
                throw new CatalogValidationException("Review event reviewer must be a string at index {$evtIdx}.");
            }
            $reviewer = trim($event['reviewer']);
            if ($reviewer === '' || preg_match('/\A(TODO|TBD|placeholder|xxx|none|N\/A|null)\z/i', $reviewer) === 1) {
                throw new CatalogValidationException("Review event reviewer cannot be a placeholder ('{$reviewer}') at index {$evtIdx}.");
            }
            if (!is_string($event['approved_at_utc'])) {
                throw new CatalogValidationException("Review event approved_at_utc must be a string at index {$evtIdx}.");
            }
            $timestamp = trim($event['approved_at_utc']);
            if ($timestamp === '' || preg_match('/\A(TODO|TBD|placeholder|0000-00-00)\z/i', $timestamp) === 1) {
                throw new CatalogValidationException("Invalid approved_at_utc timestamp in review event at index {$evtIdx}.");
            }
            try {
                $dt = new DateTimeImmutable($timestamp);
                $tzOffset = $dt->getTimezone()->getOffset($dt);
                $hasExplicitUtc = str_ends_with(strtoupper($timestamp), 'Z')
                    || str_ends_with($timestamp, '+00:00')
                    || str_ends_with($timestamp, '+0000');
                if ($tzOffset !== 0 || !$hasExplicitUtc) {
                    throw new CatalogValidationException("Review event timestamp '{$timestamp}' must be in UTC.");
                }
            } catch (\Exception $e) {
                throw new CatalogValidationException("Invalid approved_at_utc timestamp '{$timestamp}' at index {$evtIdx}: " . $e->getMessage());
            }
        }

        if ($reviewState === 'published') {
            $checkpoints = array_values(array_unique(array_map(
                static fn (array $event): string => trim((string) $event['checkpoint']),
                $metadata['review_events']
            )));
            $missingCheckpoints = array_values(array_diff(self::REVIEW_CHECKPOINTS, $checkpoints));
            if ($missingCheckpoints !== []) {
                throw new CatalogValidationException(
                    'Published catalog is missing required review checkpoints: ' . implode(', ', $missingCheckpoints) . '.'
                );
            }
        }

        // Advisory disclaimer check
        if (!is_string($metadata['advisory_disclaimer'])) {
            throw new CatalogValidationException("Metadata 'advisory_disclaimer' must be a string.");
        }
        $disclaimer = $metadata['advisory_disclaimer'];
        if (trim($disclaimer) === '') {
            throw new CatalogValidationException("Metadata advisory_disclaimer cannot be empty.");
        }
        $expectedDisclaimer = self::DISCLAIMERS[$framework];
        if ($disclaimer !== $expectedDisclaimer) {
            throw new CatalogValidationException("Metadata advisory_disclaimer does not match required disclaimer for framework '{$framework}'.");
        }

        // 3. Questions validation
        if ($actualCount === 0) {
            throw new CatalogValidationException("Catalog must have at least one question.");
        }

        $seenIds = [];
        $seenCodes = [];
        $seenPositions = [];
        $catalogPromptHashes = [];

        // Dimension tracking
        $dimensionItemCounts = [];
        $dimensionReverseCounts = [];
        $mbtiPoleCounts = array_fill_keys(self::FRAMEWORK_DIMENSIONS['mbti'], 0);

        foreach ($questions as $qIdx => $question) {
            if (!is_array($question)) {
                throw new CatalogValidationException("Question at index {$qIdx} must be an associative array.");
            }

            $requiredQKeys = ['id', 'code', 'position', 'dimension_code', 'required', 'content', 'options'];
            foreach ($requiredQKeys as $qKey) {
                if (!array_key_exists($qKey, $question)) {
                    throw new CatalogValidationException("Question at index {$qIdx} missing required key '{$qKey}'.");
                }
            }

            // UUID check
            if (!is_string($question['id'])) {
                throw new CatalogValidationException("Question at index {$qIdx} id must be a string.");
            }
            $id = $question['id'];
            if (!Uuid::isValid($id)) {
                throw new CatalogValidationException("Question id '{$id}' is not a valid canonical UUID.");
            }
            if (isset($seenIds[$id])) {
                throw new CatalogValidationException("Duplicate question id '{$id}' detected at index {$qIdx}.");
            }
            $seenIds[$id] = true;

            // Code check
            if (!is_string($question['code'])) {
                throw new CatalogValidationException("Question at index {$qIdx} code must be a string.");
            }
            $code = $question['code'];
            if (!str_starts_with($code, $namespace)) {
                throw new CatalogValidationException("Question code '{$code}' does not match namespace '{$namespace}'.");
            }
            if (isset($seenCodes[$code])) {
                throw new CatalogValidationException("Duplicate question code '{$code}' detected at index {$qIdx}.");
            }
            $seenCodes[$code] = true;

            // Position check
            $position = $question['position'];
            if (!is_int($position)) {
                throw new CatalogValidationException("Question '{$code}' position must be an integer.");
            }
            $seenPositions[] = $position;

            // Required check
            if ($question['required'] !== true) {
                throw new CatalogValidationException("Question '{$code}' required flag must be boolean true.");
            }

            // Dimension Code check
            if (!is_string($question['dimension_code'])) {
                throw new CatalogValidationException("Question '{$code}' dimension_code must be a string.");
            }
            $rawDimCode = $question['dimension_code'];
            self::validateDimensionCode(
                $framework,
                $rawDimCode,
                $code,
                $dimensionItemCounts,
                $dimensionReverseCounts,
                $mbtiPoleCounts
            );

            // Content check
            if (!is_string($question['content'])) {
                throw new CatalogValidationException("Question '{$code}' content must be a string.");
            }
            $content = $question['content'];
            if (!mb_check_encoding($content, 'UTF-8')) {
                throw new CatalogValidationException("Question '{$code}' content has invalid UTF-8 encoding.");
            }
            $trimmedContent = trim($content);
            if ($trimmedContent === '') {
                throw new CatalogValidationException("Question '{$code}' content cannot be empty.");
            }

            $charLength = mb_strlen($content, 'UTF-8');
            $maxLen = self::MAX_CONTENT_LENGTH[$band];
            if ($charLength > $maxLen) {
                throw new CatalogValidationException("Question '{$code}' content exceeds max length of {$maxLen} characters for band '{$band}' (length: {$charLength}).");
            }

            // Safety / Banned terms check
            foreach (self::BANNED_SENSITIVE_PATTERNS as $category => $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    throw new CatalogValidationException("Question '{$code}' contains prohibited or sensitive content ({$category}).");
                }
            }

            // Diagnostic certainty check
            if (preg_match(self::DIAGNOSTIC_CERTAINTY_PATTERN, $content) === 1) {
                throw new CatalogValidationException("Question '{$code}' contains absolute career conclusion or diagnostic certainty.");
            }

            // Double negative check
            if (preg_match(self::DOUBLE_NEGATIVE_PATTERN, $content) === 1) {
                throw new CatalogValidationException("Question '{$code}' contains double negative.");
            }

            // Likert options check
            $options = $question['options'];
            if (!is_array($options) || !array_is_list($options) || count($options) !== 5) {
                throw new CatalogValidationException("Question '{$code}' has invalid Likert options: must contain exactly 5 options.");
            }
            foreach ($options as $optIdx => $opt) {
                if (!is_array($opt) || !array_key_exists('value', $opt) || !array_key_exists('label', $opt)) {
                    throw new CatalogValidationException("Question '{$code}' has invalid Likert options: option missing 'value' or 'label'.");
                }
                $expectedVal = $optIdx + 1;
                if (!is_int($opt['value']) || $opt['value'] !== $expectedVal) {
                    throw new CatalogValidationException("Question '{$code}' has invalid Likert options: option value at index {$optIdx} must be {$expectedVal}.");
                }
                $expectedLabel = self::LIKERT_OPTIONS[$optIdx]['label'];
                if (!is_string($opt['label']) || $opt['label'] !== $expectedLabel) {
                    throw new CatalogValidationException("Question '{$code}' has invalid Likert options: option label at index {$optIdx} must be '{$expectedLabel}'.");
                }
            }

            // Prompt uniqueness check
            $normalizedPrompt = self::normalizePrompt($content);
            $promptHash = hash('sha256', $normalizedPrompt);
            if (isset($catalogPromptHashes[$promptHash])) {
                $prevCode = $catalogPromptHashes[$promptHash];
                throw new CatalogValidationException("Duplicate prompt detected in question '{$code}' (matches '{$prevCode}').");
            }
            $catalogPromptHashes[$promptHash] = $code;

            // Cross-catalog prompt uniqueness check
            if (isset($crossCatalogPromptHashes[$promptHash])) {
                $otherCode = $crossCatalogPromptHashes[$promptHash];
                throw new CatalogValidationException("Cross-catalog duplicate prompt detected in question '{$code}' (matches question '{$otherCode}' from another catalog).");
            }
        }

        // 4. Strict Contiguous Positions Check: 1..N
        sort($seenPositions);
        $expectedPositions = range(1, $actualCount);
        if ($seenPositions !== $expectedPositions) {
            throw new CatalogValidationException("Question positions must be contiguous 1..N without gaps or duplicates (expected 1..{$actualCount}).");
        }

        // 5. Dimension Coverage & Balance Verification
        self::validateDimensionBalance(
            $framework,
            $dimensionItemCounts,
            $dimensionReverseCounts,
            $mbtiPoleCounts
        );

        // 6. Canonical Schema Hash Verification
        $computedSchemaHash = self::computeCanonicalSchemaHash($questions);
        $declaredHash = $metadata['schema_hash'];
        if ($reviewState === 'published' && $declaredHash === null) {
            throw new CatalogValidationException("Published catalog must have a non-null schema_hash.");
        }
        if ($declaredHash !== null) {
            if (!is_string($declaredHash) || preg_match('/\A[0-9a-f]{64}\z/', $declaredHash) !== 1) {
                throw new CatalogValidationException("Metadata schema_hash must be a valid 64-character SHA-256 hex string.");
            }
            if ($declaredHash !== $computedSchemaHash) {
                throw new CatalogValidationException("Metadata schema_hash mismatch: expected '{$computedSchemaHash}', got '{$declaredHash}'.");
            }
        }

        return [
            'valid' => true,
            'schema_hash' => $computedSchemaHash,
            'prompt_hashes' => $catalogPromptHashes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $catalogs
     * @return array{total_catalogs:int,total_questions:int}
     */
    public static function validateCatalogs(array $catalogs): array
    {
        $allPromptHashes = [];
        $allQuestionCodes = [];
        $allQuestionIds = [];
        $totalQuestions = 0;

        foreach ($catalogs as $catIdx => $catalog) {
            $res = self::validate($catalog, $allPromptHashes);
            foreach ($res['prompt_hashes'] as $hash => $code) {
                $allPromptHashes[$hash] = $code;
            }
            foreach ($catalog['questions'] as $q) {
                $code = (string) $q['code'];
                $id = (string) $q['id'];
                if (isset($allQuestionCodes[$code])) {
                    throw new CatalogValidationException("Global duplicate question code '{$code}' detected across catalogs.");
                }
                $allQuestionCodes[$code] = true;

                if (isset($allQuestionIds[$id])) {
                    throw new CatalogValidationException("Global duplicate question id '{$id}' detected across catalogs.");
                }
                $allQuestionIds[$id] = true;
                $totalQuestions++;
            }
        }

        return [
            'total_catalogs' => count($catalogs),
            'total_questions' => $totalQuestions,
        ];
    }

    /**
     * Load catalog file and validate.
     * @return array<string,mixed>
     */
    public static function loadCatalogFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new CatalogValidationException("Catalog file '{$filePath}' does not exist.");
        }
        $catalog = require $filePath;
        if (!is_array($catalog)) {
            throw new CatalogValidationException("Catalog file '{$filePath}' must return an array.");
        }
        self::validate($catalog);
        return $catalog;
    }

    /**
     * Deterministic Prompt Normalization:
     * 1. Trim Unicode whitespace
     * 2. Collapse Unicode whitespace to single space
     * 3. Lowercase UTF-8
     */
    public static function normalizePrompt(string $content): string
    {
        $trimmed = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $content) ?? '';
        $collapsed = preg_replace('/\p{Z}+/u', ' ', $trimmed) ?? '';
        return mb_strtolower($collapsed, 'UTF-8');
    }

    /**
     * Canonical Schema Hash Serializer:
     * - Order questions by position ascending
     * - Fixed object-key order: code, content, options, dimension_code, required, position
     * - Fixed option-key order: value, label
     * - JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
     * - SHA-256
     * @param list<array<string,mixed>> $questions
     */
    public static function computeCanonicalSchemaHash(array $questions): string
    {
        $sorted = $questions;
        usort($sorted, static fn (array $a, array $b) => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));

        $canonical = [];
        foreach ($sorted as $q) {
            $options = [];
            foreach ($q['options'] ?? [] as $opt) {
                $options[] = [
                    'value' => (int) ($opt['value'] ?? 0),
                    'label' => (string) ($opt['label'] ?? ''),
                ];
            }

            $canonical[] = [
                'code' => (string) ($q['code'] ?? ''),
                'content' => (string) ($q['content'] ?? ''),
                'options' => $options,
                'dimension_code' => (string) ($q['dimension_code'] ?? ''),
                'required' => (bool) ($q['required'] ?? false),
                'position' => (int) ($q['position'] ?? 0),
            ];
        }

        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new CatalogValidationException('Failed to encode canonical question payload to JSON.');
        }

        return hash('sha256', $json);
    }

    /**
     * Validate dimension code syntax and track coverage.
     */
    private static function validateDimensionCode(
        string $framework,
        string $rawCode,
        string $questionCode,
        array &$dimCounts,
        array &$dimRevCounts,
        array &$mbtiPoleCounts
    ): void {
        $code = strtoupper(trim($rawCode));

        switch ($framework) {
            case 'holland':
                if (preg_match('/\A([RIASEC])(?::([+-]))?\z/', $code, $match) !== 1) {
                    throw new CatalogValidationException("Unsupported Holland dimension code '{$rawCode}' in question '{$questionCode}'.");
                }
                $dim = $match[1];
                $isRev = ($match[2] ?? '+') === '-';
                $dimCounts[$dim] = ($dimCounts[$dim] ?? 0) + 1;
                if ($isRev) {
                    $dimRevCounts[$dim] = ($dimRevCounts[$dim] ?? 0) + 1;
                }
                break;

            case 'disc':
                if (preg_match('/\A([DISC])(?::([+-]))?\z/', $code, $match) !== 1) {
                    throw new CatalogValidationException("Unsupported DISC dimension code '{$rawCode}' in question '{$questionCode}'.");
                }
                $dim = $match[1];
                $isRev = ($match[2] ?? '+') === '-';
                $dimCounts[$dim] = ($dimCounts[$dim] ?? 0) + 1;
                if ($isRev) {
                    $dimRevCounts[$dim] = ($dimRevCounts[$dim] ?? 0) + 1;
                }
                break;

            case 'multiple_intelligence':
                if (preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/', $code, $match) !== 1) {
                    throw new CatalogValidationException("Unsupported Multiple Intelligence dimension code '{$rawCode}' in question '{$questionCode}'.");
                }
                $dim = $match[1];
                $isRev = ($match[2] ?? '+') === '-';
                $dimCounts[$dim] = ($dimCounts[$dim] ?? 0) + 1;
                if ($isRev) {
                    $dimRevCounts[$dim] = ($dimRevCounts[$dim] ?? 0) + 1;
                }
                break;

            case 'mbti':
                if (str_contains($code, ':+') || str_contains($code, ':-') || str_contains($code, '+') || str_contains($code, '-')) {
                    throw new CatalogValidationException("MBTI dimension code '{$rawCode}' cannot contain reverse suffix :+ or :- in question '{$questionCode}'.");
                }
                if (preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/', $code, $match) !== 1) {
                    throw new CatalogValidationException("Unsupported MBTI dimension code '{$rawCode}' in question '{$questionCode}'.");
                }
                $axis = $match[1];
                $pole = $match[2];
                $allowedPoles = self::MBTI_AXES[$axis];
                if (!in_array($pole, $allowedPoles, true)) {
                    throw new CatalogValidationException("Pole '{$pole}' does not belong to axis '{$axis}' in question '{$questionCode}'.");
                }
                $mbtiPoleCounts[$pole] = ($mbtiPoleCounts[$pole] ?? 0) + 1;
                break;
        }
    }

    /**
     * Validate dimension coverage and balance rules.
     */
    private static function validateDimensionBalance(
        string $framework,
        array $dimCounts,
        array $dimRevCounts,
        array $mbtiPoleCounts
    ): void {
        if ($framework === 'mbti') {
            foreach (self::FRAMEWORK_DIMENSIONS['mbti'] as $pole) {
                $count = $mbtiPoleCounts[$pole] ?? 0;
                if ($count === 0) {
                    throw new CatalogValidationException("Missing coverage for pole '{$pole}' in MBTI catalog.");
                }
                if ($count !== 4) {
                    throw new CatalogValidationException("MBTI pole '{$pole}' must have exactly 4 stated items (got {$count}).");
                }
            }
            return;
        }

        $expectedDimensions = self::FRAMEWORK_DIMENSIONS[$framework];
        foreach ($expectedDimensions as $dim) {
            $count = $dimCounts[$dim] ?? 0;
            if ($count === 0) {
                throw new CatalogValidationException("Missing coverage for dimension '{$dim}' in {$framework} catalog.");
            }
            if ($count < 2) {
                throw new CatalogValidationException("Dimension '{$dim}' must have at least 2 questions in {$framework} catalog (got {$count}).");
            }

            $revCount = $dimRevCounts[$dim] ?? 0;
            $ratio = $revCount / $count;
            if ($ratio < 0.40 || $ratio > 0.60) {
                throw new CatalogValidationException(sprintf(
                    "Dimension '%s' reverse ratio %s (%d/%d) is out of acceptable bounds (40%%–60%%) in %s catalog.",
                    $dim,
                    (string) round($ratio, 4),
                    $revCount,
                    $count,
                    $framework
                ));
            }
        }
    }
}

// -----------------------------------------------------------------------------
// TEST SUITE & SYNTHETIC FIXTURES
// -----------------------------------------------------------------------------

function validator_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function validator_expect_exception(callable $callback, string $expectedMessageSubstring): void
{
    try {
        $callback();
    } catch (CatalogValidationException $e) {
        if (!str_contains($e->getMessage(), $expectedMessageSubstring)) {
            fwrite(STDERR, "Failed asserting that exception message '{$e->getMessage()}' contains '{$expectedMessageSubstring}'.\n");
            exit(1);
        }
        return;
    } catch (\Throwable $e) {
        fwrite(STDERR, "Expected CatalogValidationException with '{$expectedMessageSubstring}', but got " . get_class($e) . ": {$e->getMessage()}\n");
        exit(1);
    }

    fwrite(STDERR, "Failed asserting that exception was thrown (expected '{$expectedMessageSubstring}').\n");
    exit(1);
}

/**
 * Deterministic UUID generator for synthetic test fixtures.
 */
function synthetic_uuid(int $catalogIndex, int $questionIndex): string
{
    return sprintf(
        'c%03x0000-%04x-4000-8000-%012x',
        $catalogIndex,
        $questionIndex,
        ($catalogIndex * 1000) + $questionIndex
    );
}

/**
 * Generate a complete, valid synthetic catalog fixture.
 * @return array{metadata:array<string,mixed>,questions:list<array<string,mixed>>}
 */
function create_valid_synthetic_catalog(
    string $framework,
    string $band,
    int $catalogIndex,
    string $reviewState = 'draft',
    array $reviewEvents = []
): array {
    $namespace = "{$framework}_{$band}_";
    $questions = [];

    $baseOptions = LearnerCatalogContentValidator::LIKERT_OPTIONS;

    if ($framework === 'holland') {
        // 6 dimensions × 2 items (1 pos + 1 rev) = 12 items
        $dims = ['R', 'I', 'A', 'S', 'E', 'C'];
        $pos = 1;
        foreach ($dims as $dim) {
            $q1Id = synthetic_uuid($catalogIndex, $pos);
            $q1Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q1Content = match ($band) {
                'middle' => "Thích sửa đồ và làm thủ công ({$dim}-{$band}-1)",
                'high' => "Thường xuyên tìm hiểu máy móc thực tế ({$dim}-{$band}-1)",
                'college' => "Thực hành kỹ thuật và nghiên cứu ứng dụng ({$dim}-{$band}-1)",
            };
            $questions[] = [
                'id' => $q1Id,
                'code' => $q1Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:+",
                'required' => true,
                'content' => $q1Content,
                'options' => $baseOptions,
            ];
            $pos++;

            $q2Id = synthetic_uuid($catalogIndex, $pos);
            $q2Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q2Content = match ($band) {
                'middle' => "Ít quan tâm đến việc chế tạo đồ ({$dim}-{$band}-2)",
                'high' => "Ít hào hứng với hoạt động kỹ thuật ({$dim}-{$band}-2)",
                'college' => "Ít tham gia dự án thực nghiệm nghề nghiệp ({$dim}-{$band}-2)",
            };
            $questions[] = [
                'id' => $q2Id,
                'code' => $q2Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:-",
                'required' => true,
                'content' => $q2Content,
                'options' => $baseOptions,
            ];
            $pos++;
        }
    } elseif ($framework === 'disc') {
        // 4 dimensions × 2 items (1 pos + 1 rev) = 8 items
        $dims = ['D', 'I', 'S', 'C'];
        $pos = 1;
        foreach ($dims as $dim) {
            $q1Id = synthetic_uuid($catalogIndex, $pos);
            $q1Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q1Content = match ($band) {
                'middle' => "Thích dẫn đầu hoạt động nhóm ({$dim}-{$band}-1)",
                'high' => "Tự tin đưa ra quyết định khi thảo luận ({$dim}-{$band}-1)",
                'college' => "Chủ động nhận trách nhiệm điều phối mục tiêu ({$dim}-{$band}-1)",
            };
            $questions[] = [
                'id' => $q1Id,
                'code' => $q1Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:+",
                'required' => true,
                'content' => $q1Content,
                'options' => $baseOptions,
            ];
            $pos++;

            $q2Id = synthetic_uuid($catalogIndex, $pos);
            $q2Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q2Content = match ($band) {
                'middle' => "Thường ngần ngại nhận vai trò chính ({$dim}-{$band}-2)",
                'high' => "Thích để bạn khác quyết định hướng đi ({$dim}-{$band}-2)",
                'college' => "Ít khi xung phong làm người lãnh đạo dự án ({$dim}-{$band}-2)",
            };
            $questions[] = [
                'id' => $q2Id,
                'code' => $q2Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:-",
                'required' => true,
                'content' => $q2Content,
                'options' => $baseOptions,
            ];
            $pos++;
        }
    } elseif ($framework === 'multiple_intelligence') {
        // 8 dimensions × 2 items (1 pos + 1 rev) = 16 items
        $dims = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
        $pos = 1;
        foreach ($dims as $dim) {
            $q1Id = synthetic_uuid($catalogIndex, $pos);
            $q1Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q1Content = match ($band) {
                'middle' => "Yêu thích chủ đề học tập này ({$dim}-{$band}-1)",
                'high' => "Hào hứng với phương pháp rèn luyện lĩnh vực này ({$dim}-{$band}-1)",
                'college' => "Thường áp dụng kỹ năng này vào học tập chuyên sâu ({$dim}-{$band}-1)",
            };
            $questions[] = [
                'id' => $q1Id,
                'code' => $q1Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:+",
                'required' => true,
                'content' => $q1Content,
                'options' => $baseOptions,
            ];
            $pos++;

            $q2Id = synthetic_uuid($catalogIndex, $pos);
            $q2Code = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
            $q2Content = match ($band) {
                'middle' => "Ít thấy hứng thú với lĩnh vực này ({$dim}-{$band}-2)",
                'high' => "Cảm thấy khó tập trung khi làm bài tập dạng này ({$dim}-{$band}-2)",
                'college' => "Ít khi lựa chọn học phần liên quan lĩnh vực này ({$dim}-{$band}-2)",
            };
            $questions[] = [
                'id' => $q2Id,
                'code' => $q2Code,
                'position' => $pos,
                'dimension_code' => "{$dim}:-",
                'required' => true,
                'content' => $q2Content,
                'options' => $baseOptions,
            ];
            $pos++;
        }
    } elseif ($framework === 'mbti') {
        // 8 poles × exactly 4 items = 32 items
        $poleDefinitions = [
            'EI' => ['E', 'I'],
            'SN' => ['S', 'N'],
            'TF' => ['T', 'F'],
            'JP' => ['J', 'P'],
        ];
        $pos = 1;
        foreach ($poleDefinitions as $axis => $poles) {
            foreach ($poles as $pole) {
                for ($itemIdx = 1; $itemIdx <= 4; $itemIdx++) {
                    $qId = synthetic_uuid($catalogIndex, $pos);
                    $qCode = sprintf('%s%s_%03d', $namespace, strtolower($pole), $pos);
                    $qContent = match ($band) {
                        'middle' => "Thói quen học tập theo hướng {$pole} câu {$itemIdx} ({$band})",
                        'high' => "Xu hướng tư duy và hành vi theo định hướng {$pole} mục {$itemIdx} ({$band})",
                        'college' => "Phong cách làm việc độc lập hoặc phối hợp theo chiều {$pole} phần {$itemIdx} ({$band})",
                    };
                    $questions[] = [
                        'id' => $qId,
                        'code' => $qCode,
                        'position' => $pos,
                        'dimension_code' => "{$axis}:{$pole}",
                        'required' => true,
                        'content' => $qContent,
                        'options' => $baseOptions,
                    ];
                    $pos++;
                }
            }
        }
    }

    $computedHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($questions);
    $schemaHash = ($reviewState === 'draft') ? $computedHash : $computedHash;

    $metadata = [
        'framework' => $framework,
        'education_band' => $band,
        'scoring_version' => LearnerCatalogContentValidator::SCORING_VERSIONS[$framework],
        'question_count' => count($questions),
        'stable_code_namespace' => $namespace,
        'review_state' => $reviewState,
        'review_events' => $reviewEvents,
        'schema_hash' => $schemaHash,
        'advisory_disclaimer' => LearnerCatalogContentValidator::DISCLAIMERS[$framework],
    ];

    return [
        'metadata' => $metadata,
        'questions' => $questions,
    ];
}

// -----------------------------------------------------------------------------
// EXECUTION & VALIDATION TESTS
// -----------------------------------------------------------------------------

echo "=== STARTING CONTENT SCHEMA VALIDATOR TEST SUITE ===\n";

$assertionsCount = 0;

// 1. Generate all 12 Valid Synthetic Fixtures (4 frameworks × 3 bands)
$frameworks = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
$bands = ['middle', 'high', 'college'];
$validCatalogs = [];
$catIndex = 1;

foreach ($frameworks as $fw) {
    foreach ($bands as $b) {
        $validCatalogs["{$fw}_{$b}"] = create_valid_synthetic_catalog($fw, $b, $catIndex++);
    }
}

validator_assert(count($validCatalogs) === 12, 'Must generate exactly 12 synthetic valid catalogs');
$assertionsCount++;

// 2. Validate all 12 valid fixtures individually
foreach ($validCatalogs as $key => $catalog) {
    $result = LearnerCatalogContentValidator::validate($catalog);
    validator_assert($result['valid'] === true, "Valid catalog '{$key}' must pass validation");
    validator_assert($result['schema_hash'] === $catalog['metadata']['schema_hash'], "Catalog '{$key}' computed hash matches metadata");
    $assertionsCount += 2;
}

// 3. Batch Validate all 12 valid fixtures together (cross-catalog prompt/code/UUID uniqueness)
$batchResult = LearnerCatalogContentValidator::validateCatalogs(array_values($validCatalogs));
validator_assert($batchResult['total_catalogs'] === 12, 'Batch validation passed for 12 catalogs');
// Total questions: 36 (Holland) + 24 (DISC) + 48 (MI) + 96 (MBTI) = 204
validator_assert($batchResult['total_questions'] === 204, "Total questions across 12 synthetic catalogs must be 204 (got {$batchResult['total_questions']})");
$assertionsCount += 2;

// 4. Cross-check dimension behavior with real Scorer implementations
$hollandScorer = new HollandScorer();
$discScorer = new DiscScorer();
$miScorer = new MultipleIntelligenceScorer();
$mbtiScorer = new MbtiScorer();

// Test Holland synthetic catalog scoring
$hCat = $validCatalogs['holland_middle'];
$hAnswers = [];
foreach ($hCat['questions'] as $q) {
    $hAnswers[$q['id']] = 4;
}
$hRes = $hollandScorer->score($hCat['questions'], $hAnswers)->toArray();
validator_assert(is_string($hRes['result_code']) && strlen($hRes['result_code']) === 3, 'Holland scorer successfully scored synthetic catalog');
$assertionsCount++;

// Test DISC synthetic catalog scoring
$dCat = $validCatalogs['disc_high'];
$dAnswers = [];
foreach ($dCat['questions'] as $q) {
    $dAnswers[$q['id']] = 4;
}
$dRes = $discScorer->score($dCat['questions'], $dAnswers)->toArray();
validator_assert(is_string($dRes['result_code']) && strlen($dRes['result_code']) === 4, 'DISC scorer successfully scored synthetic catalog');
$assertionsCount++;

// Test MI synthetic catalog scoring
$miCat = $validCatalogs['multiple_intelligence_college'];
$miAnswers = [];
foreach ($miCat['questions'] as $q) {
    $miAnswers[$q['id']] = 4;
}
$miRes = $miScorer->score($miCat['questions'], $miAnswers)->toArray();
validator_assert(str_contains($miRes['result_code'], '-'), 'MI scorer successfully scored synthetic catalog');
$assertionsCount++;

// Test MBTI synthetic catalog scoring
$mCat = $validCatalogs['mbti_middle'];
$mAnswers = [];
foreach ($mCat['questions'] as $q) {
    $mAnswers[$q['id']] = 4;
}
$mRes = $mbtiScorer->score($mCat['questions'], $mAnswers)->toArray();
validator_assert(strlen($mRes['result_code']) === 4, 'MBTI scorer successfully scored synthetic catalog');
$assertionsCount++;

// -----------------------------------------------------------------------------
// 5. INTENTIONALLY INVALID FIXTURES TESTS
// -----------------------------------------------------------------------------

echo "--- Testing Intentionally Invalid Fixtures ---\n";

// [Invalid Group 1: Missing Top-level & Metadata Keys]
$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Catalog missing required key 'metadata'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['questions']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Catalog missing required key 'questions'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['framework']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'framework'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['education_band']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'education_band'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['scoring_version']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'scoring_version'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['question_count']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'question_count'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['stable_code_namespace']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'stable_code_namespace'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['review_state']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'review_state'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['review_events']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'review_events'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['metadata']['advisory_disclaimer']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata missing required key 'advisory_disclaimer'");
$assertionsCount++;

// [Invalid Group 2: Invalid Metadata Enums & Values]
$cat = $validCatalogs['holland_middle'];
$cat['metadata']['framework'] = 'bigfive';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Invalid framework 'bigfive'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['education_band'] = 'primary';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Invalid education band 'primary'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_state'] = 'unknown_state';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Invalid review state 'unknown_state'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['scoring_version'] = 'holland-riasec-2.0';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Scoring version 'holland-riasec-2.0' does not match framework 'holland'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['question_count'] = 99;
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata question_count (99) does not match actual question count");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['stable_code_namespace'] = 'holland_high_';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata stable_code_namespace 'holland_high_' does not match expected 'holland_middle_'");
$assertionsCount++;

// [Invalid Group 3: Review Events & Published State]
$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_events'] = [['checkpoint' => 'content_review']]; // missing reviewer, approved_at_utc
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Review event at index 0 missing required key 'reviewer'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_events'] = [['checkpoint' => 'content_review', 'reviewer' => 'TODO', 'approved_at_utc' => '2026-08-18T10:00:00Z']];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Review event reviewer cannot be a placeholder ('TODO')");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_events'] = [['checkpoint' => 'content_review', 'reviewer' => 'Codex', 'approved_at_utc' => 'invalid-date']];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Invalid approved_at_utc timestamp 'invalid-date'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_events'] = [['checkpoint' => 'content_review', 'reviewer' => 'Codex', 'approved_at_utc' => '2026-08-18 10:00:00+07:00']];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "must be in UTC");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_events'] = [['checkpoint' => 'unknown_checkpoint', 'reviewer' => 'Codex', 'approved_at_utc' => '2026-08-18T10:00:00Z']];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "is not recognized");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_state'] = 'published';
$cat['metadata']['review_events'] = [['checkpoint' => 'content_review', 'reviewer' => 'Codex', 'approved_at_utc' => '2026-08-18T10:00:00Z']];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Published catalog is missing required review checkpoints");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['review_state'] = 'published';
$cat['metadata']['schema_hash'] = null;
$cat['metadata']['review_events'] = array_map(
    static fn (string $checkpoint): array => [
        'checkpoint' => $checkpoint,
        'reviewer' => 'Codex',
        'approved_at_utc' => '2026-08-18T10:00:00Z',
    ],
    LearnerCatalogContentValidator::REVIEW_CHECKPOINTS
);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Published catalog must have a non-null schema_hash");
$assertionsCount++;

// [Invalid Group 3b: Strict metadata and field types]
$cat = $validCatalogs['holland_middle'];
$cat['metadata']['question_count'] = '12';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "question_count' must be a positive integer");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['id'] = 123;
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "id must be a string");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['options'][0]['value'] = '1';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "option value at index 0 must be 1");
$assertionsCount++;

// [Invalid Group 4: Disclaimers]
$cat = $validCatalogs['holland_middle'];
$cat['metadata']['advisory_disclaimer'] = '   ';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata advisory_disclaimer cannot be empty");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['metadata']['advisory_disclaimer'] = LearnerCatalogContentValidator::DISCLAIMERS['mbti'];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata advisory_disclaimer does not match required disclaimer for framework 'holland'");
$assertionsCount++;

// [Invalid Group 5: Schema Hash Mismatch]
$cat = $validCatalogs['holland_middle'];
$cat['metadata']['schema_hash'] = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Metadata schema_hash mismatch");
$assertionsCount++;

// [Invalid Group 6: Question Missing Keys & Invalid UUIDs]
$cat = $validCatalogs['holland_middle'];
unset($cat['questions'][0]['dimension_code']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Question at index 0 missing required key 'dimension_code'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['id'] = 'haaaaa-0001-4000-8000-000000000001'; // non-hex prefix
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "not a valid canonical UUID");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['id'] = 'q-h-01'; // non-UUID
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "not a valid canonical UUID");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['id'] = $cat['questions'][0]['id']; // duplicate UUID
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Duplicate question id");
$assertionsCount++;

// [Invalid Group 7: Question Code Namespace Mismatch & Duplicate Code]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['code'] = 'disc_middle_d_001';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Question code 'disc_middle_d_001' does not match namespace 'holland_middle_'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['code'] = $cat['questions'][0]['code'];
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Duplicate question code");
$assertionsCount++;

// [Invalid Group 8: Position Errors (Non-int, start at 0, duplicate, gapped)]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['position'] = '1';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "position must be an integer");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['position'] = 0;
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Question positions must be contiguous 1..N without gaps or duplicates");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['position'] = 1; // duplicate position 1
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Question positions must be contiguous 1..N without gaps or duplicates");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['position'] = 3; // gap: 1, 3, 3, 4, ...
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Question positions must be contiguous 1..N without gaps or duplicates");
$assertionsCount++;

// [Invalid Group 9: Required Flag]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['required'] = false;
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "required flag must be boolean true");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['required'] = 1; // int instead of bool true
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "required flag must be boolean true");
$assertionsCount++;

// [Invalid Group 10: Likert Options]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['options'] = array_slice($cat['questions'][0]['options'], 0, 4); // 4 options
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "must contain exactly 5 options");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['options'][0]['value'] = 0; // values 0..4
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "option value at index 0 must be 1");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['options'][0]['label'] = 'Rất không đồng ý';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "option label at index 0 must be 'Hoàn toàn không đồng ý'");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
unset($cat['questions'][0]['options'][0]['label']);
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "option missing 'value' or 'label'");
$assertionsCount++;

// [Invalid Group 11: Content Length Limits per Band]
$catMiddle = $validCatalogs['holland_middle'];
$catMiddle['questions'][0]['content'] = str_repeat('a', 61); // > 60
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catMiddle), "exceeds max length of 60 characters for band 'middle' (length: 61)");
$assertionsCount++;

$catHigh = $validCatalogs['holland_high'];
$catHigh['questions'][0]['content'] = str_repeat('b', 81); // > 80
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catHigh), "exceeds max length of 80 characters for band 'high' (length: 81)");
$assertionsCount++;

$catCollege = $validCatalogs['holland_college'];
$catCollege['questions'][0]['content'] = str_repeat('c', 101); // > 100
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catCollege), "exceeds max length of 100 characters for band 'college' (length: 101)");
$assertionsCount++;

// [Invalid Group 12: Content Encoding & Banned Terms]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = "Invalid UTF8 \xC3\x28 text";
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "invalid UTF-8 encoding");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Bạn có thường đi nhà thờ vào cuối tuần không?';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains prohibited or sensitive content (religion)");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Bạn thuộc dân tộc thiểu số nào?';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains prohibited or sensitive content (ethnicity)");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Bạn có người thân bị khuyết tật không?';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains prohibited or sensitive content (disability)");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Gia đình bạn có sổ hộ nghèo không?';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains prohibited or sensitive content (financial)");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Bạn chắc chắn phù hợp với nghề bác sĩ.';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains absolute career conclusion or diagnostic certainty");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][0]['content'] = 'Tôi không bao giờ không hoàn thành bài tập.';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "contains double negative");
$assertionsCount++;

// [Invalid Group 13: Duplicate Prompts (within catalog and cross-catalog)]
$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['content'] = $cat['questions'][0]['content']; // exact duplicate
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Duplicate prompt detected in question");
$assertionsCount++;

$cat = $validCatalogs['holland_middle'];
$cat['questions'][1]['content'] = '   ' . mb_strtoupper($cat['questions'][0]['content']) . '   '; // normalized duplicate
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($cat), "Duplicate prompt detected in question");
$assertionsCount++;

$cat1 = $validCatalogs['holland_middle'];
$cat2 = $validCatalogs['holland_high'];
$cat2['questions'][0]['content'] = $cat1['questions'][0]['content']; // cross catalog duplicate
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validateCatalogs([$cat1, $cat2]), "Cross-catalog duplicate prompt detected in question");
$assertionsCount++;

// [Invalid Group 14: Dimension Code Format Errors per Framework]
$catH = $validCatalogs['holland_middle'];
$catH['questions'][0]['dimension_code'] = 'X';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catH), "Unsupported Holland dimension code 'X'");
$assertionsCount++;

$catD = $validCatalogs['disc_middle'];
$catD['questions'][0]['dimension_code'] = 'X';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catD), "Unsupported DISC dimension code 'X'");
$assertionsCount++;

$catMI = $validCatalogs['multiple_intelligence_middle'];
$catMI['questions'][0]['dimension_code'] = 'UNKNOWN';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catMI), "Unsupported Multiple Intelligence dimension code 'UNKNOWN'");
$assertionsCount++;

$catM = $validCatalogs['mbti_middle'];
$catM['questions'][0]['dimension_code'] = 'EI:E:+';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catM), "MBTI dimension code 'EI:E:+' cannot contain reverse suffix");
$assertionsCount++;

$catM = $validCatalogs['mbti_middle'];
$catM['questions'][0]['dimension_code'] = 'SN:N:-';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catM), "MBTI dimension code 'SN:N:-' cannot contain reverse suffix");
$assertionsCount++;

$catM = $validCatalogs['mbti_middle'];
$catM['questions'][0]['dimension_code'] = 'EI:S'; // pole S on axis EI
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catM), "Pole 'S' does not belong to axis 'EI'");
$assertionsCount++;

$catM = $validCatalogs['mbti_middle'];
$catM['questions'][0]['dimension_code'] = 'EI:X';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catM), "Unsupported MBTI dimension code 'EI:X'");
$assertionsCount++;

// [Invalid Group 15: Dimension Coverage & Reverse Balance Rules]
// Holland missing dimension 'C' (replace C with R)
$catH = $validCatalogs['holland_middle'];
$catH['questions'][10]['dimension_code'] = 'R:+';
$catH['questions'][11]['dimension_code'] = 'R:-';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catH), "Missing coverage for dimension 'C' in holland catalog");
$assertionsCount++;

// Holland dimension 'R' reverse ratio 0% (2 positive, 0 reverse)
$catH = $validCatalogs['holland_middle'];
$catH['questions'][1]['dimension_code'] = 'R:+';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catH), "reverse ratio 0 (0/2) is out of acceptable bounds (40%–60%) in holland catalog");
$assertionsCount++;

// Holland dimension 'R' reverse ratio 100% (0 positive, 2 reverse)
$catH = $validCatalogs['holland_middle'];
$catH['questions'][0]['dimension_code'] = 'R:-';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catH), "reverse ratio 1 (2/2) is out of acceptable bounds (40%–60%) in holland catalog");
$assertionsCount++;

// DISC missing dimension 'D'
$catD = $validCatalogs['disc_middle'];
$catD['questions'][0]['dimension_code'] = 'I:+';
$catD['questions'][1]['dimension_code'] = 'I:-';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catD), "Missing coverage for dimension 'D' in disc catalog");
$assertionsCount++;

// MI missing dimension 'NAT'
$catMI = $validCatalogs['multiple_intelligence_middle'];
$catMI['questions'][14]['dimension_code'] = 'LING:+';
$catMI['questions'][15]['dimension_code'] = 'LING:-';
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catMI), "Missing coverage for dimension 'NAT' in multiple_intelligence catalog");
$assertionsCount++;

// MBTI pole count != 4 (e.g. pole E has 3 items, pole I has 5 items)
$catM = $validCatalogs['mbti_middle'];
$catM['questions'][0]['dimension_code'] = 'EI:I'; // change one E to I -> E has 3, I has 5
validator_expect_exception(static fn () => LearnerCatalogContentValidator::validate($catM), "MBTI pole 'E' must have exactly 4 stated items (got 3)");
$assertionsCount++;

// -----------------------------------------------------------------------------
// 6. CANONICAL SCHEMA HASH INVARIANCE & SENSITIVITY TESTS
// -----------------------------------------------------------------------------

echo "--- Testing Canonical Schema Hash Invariance & Sensitivity ---\n";

$baseQuestions = $validCatalogs['holland_middle']['questions'];
$baseHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($baseQuestions);

// Test Invariance 1: Deterministic
$repeatHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($baseQuestions);
validator_assert($baseHash === $repeatHash, 'Canonical hash must be deterministic');
$assertionsCount++;

// Test Invariance 2: Associative question array keys order
$shuffledKeyQuestions = array_map(static function (array $q): array {
    return [
        'position' => $q['position'],
        'content' => $q['content'],
        'required' => $q['required'],
        'code' => $q['code'],
        'dimension_code' => $q['dimension_code'],
        'options' => $q['options'],
        'id' => $q['id'],
    ];
}, $baseQuestions);
$shuffledHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($shuffledKeyQuestions);
validator_assert($baseHash === $shuffledHash, 'Canonical hash must be invariant to question array key ordering');
$assertionsCount++;

// Test Invariance 3: Option array keys order
$shuffledOptQuestions = array_map(static function (array $q): array {
    $opts = array_map(static fn (array $opt) => ['label' => $opt['label'], 'value' => $opt['value']], $q['options']);
    $q['options'] = $opts;
    return $q;
}, $baseQuestions);
$optShuffledHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($shuffledOptQuestions);
validator_assert($baseHash === $optShuffledHash, 'Canonical hash must be invariant to option key ordering');
$assertionsCount++;

// Test Invariance 4: UUID exclusion (changing question id does NOT change schema hash)
$uuidChangedQuestions = $baseQuestions;
$uuidChangedQuestions[0]['id'] = synthetic_uuid(99, 1);
$uuidChangedHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($uuidChangedQuestions);
validator_assert($baseHash === $uuidChangedHash, 'Canonical schema hash MUST NOT depend on opaque question UUID');
$assertionsCount++;

// Test Sensitivity 1: Content change
$modContentQuestions = $baseQuestions;
$modContentQuestions[0]['content'] .= ' (modified)';
$modContentHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modContentQuestions);
validator_assert($baseHash !== $modContentHash, 'Canonical hash must change when content changes');
$assertionsCount++;

// Test Sensitivity 2: Options change
$modOptQuestions = $baseQuestions;
$modOptQuestions[0]['options'][0]['label'] = 'Hoàn toàn không đồng ý (edit)';
$modOptHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modOptQuestions);
validator_assert($baseHash !== $modOptHash, 'Canonical hash must change when options change');
$assertionsCount++;

// Test Sensitivity 3: Position change
$modPosQuestions = $baseQuestions;
$modPosQuestions[0]['position'] = 2;
$modPosQuestions[1]['position'] = 1;
$modPosHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modPosQuestions);
validator_assert($baseHash !== $modPosHash, 'Canonical hash must change when position changes');
$assertionsCount++;

// Test Sensitivity 4: Dimension code change
$modDimQuestions = $baseQuestions;
$modDimQuestions[0]['dimension_code'] = 'R:-';
$modDimHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modDimQuestions);
validator_assert($baseHash !== $modDimHash, 'Canonical hash must change when dimension_code changes');
$assertionsCount++;

// Test Sensitivity 5: Required flag change
$modReqQuestions = $baseQuestions;
$modReqQuestions[0]['required'] = false;
$modReqHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modReqQuestions);
validator_assert($baseHash !== $modReqHash, 'Canonical hash must change when required flag changes');
$assertionsCount++;

// Test Sensitivity 6: Code change
$modCodeQuestions = $baseQuestions;
$modCodeQuestions[0]['code'] = 'holland_middle_r_999';
$modCodeHash = LearnerCatalogContentValidator::computeCanonicalSchemaHash($modCodeQuestions);
validator_assert($baseHash !== $modCodeHash, 'Canonical hash must change when code changes');
$assertionsCount++;

// -----------------------------------------------------------------------------
// 7. FILE LOADER TESTS
// -----------------------------------------------------------------------------

echo "--- Testing File Loader Helper ---\n";

// Test loadCatalogFile with non-existent file
validator_expect_exception(
    static fn () => LearnerCatalogContentValidator::loadCatalogFile(__DIR__ . '/non_existent_catalog.php'),
    'does not exist'
);
$assertionsCount++;

// Test loadCatalogFile with non-array returning file
$tempNonArrayFile = tempnam(sys_get_temp_dir(), 'cat_non_array_') . '.php';
file_put_contents($tempNonArrayFile, "<?php return 'invalid_string';\n");
try {
    validator_expect_exception(
        static fn () => LearnerCatalogContentValidator::loadCatalogFile($tempNonArrayFile),
        'must return an array'
    );
    $assertionsCount++;
} finally {
    if (file_exists($tempNonArrayFile)) {
        unlink($tempNonArrayFile);
    }
}

// Test loadCatalogFile with valid synthetic catalog file
$tempValidFile = tempnam(sys_get_temp_dir(), 'cat_valid_') . '.php';
$exportedCatalog = var_export($validCatalogs['holland_middle'], true);
file_put_contents($tempValidFile, "<?php return {$exportedCatalog};\n");
try {
    $loadedCat = LearnerCatalogContentValidator::loadCatalogFile($tempValidFile);
    validator_assert(is_array($loadedCat) && isset($loadedCat['metadata']), 'loadCatalogFile successfully loads and validates valid catalog file');
    $assertionsCount++;
} finally {
    if (file_exists($tempValidFile)) {
        unlink($tempValidFile);
    }
}

echo "=== ALL CHECKS PASSED: {$assertionsCount} ASSERTIONS COMPLETED SUCCESSFULLY ===\n";
echo "learner_catalog_content_validator: OK\n";
