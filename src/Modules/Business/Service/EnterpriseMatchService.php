<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;

/**
 * Privacy-first enterprise matching pipeline.
 * Evaluates real candidate profiles using AI matching & ranking.
 */
final class EnterpriseMatchService
{
    /** @var callable|null */
    private $provider;

    public function __construct(
        private readonly EnterpriseTalentRepository $repository,
        ?callable $provider = null,
        private readonly ?string $modelVersion = 'gemini-1.5-pro'
    ) {
        $this->provider = $provider;
    }

    /**
     * @param array<string,mixed>|string $job
     * @param callable|null $provider function(array $job, array $candidates): array
     * @return array{state:string,job:array<string,mixed>,items:list<array<string,mixed>>,total_relevant:int,desired_count:int|null,generated_at:string,analysis_origin?:string,freshness_status?:string,model_version?:string,last_known_good?:bool}
     */
    public function match(
        string $enterpriseId,
        array|string $job,
        ?callable $provider = null,
        ?int $limit = null
    ): array {
        $normalized = $this->normalizeJobRequirements($job);
        $jobHash = hash('sha256', json_encode([
            'job_id' => $normalized['id'],
            'required_skills' => $normalized['required_skills'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $candidates = $this->repository->matchCandidates($enterpriseId, $normalized['required_skills']);
        $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

        if ($candidates === []) {
            return [
                'state' => 'no_candidates',
                'job' => $normalized,
                'items' => [],
                'total_relevant' => 0,
                'desired_count' => $limit,
                'generated_at' => $generatedAt,
            ];
        }

        $targetLimit = ($limit !== null && $limit > 0) ? $limit : null;

        // Build rich candidate projections for provider
        $candidateProjections = [];
        $refMap = [];
        $i = 1;
        foreach ($candidates as $cand) {
            $ref = 'candidate_' . $i++;
            $refMap[$ref] = $cand;
            $verifiedSkills = [];
            foreach ((array) ($cand['skills'] ?? []) as $sk) {
                $verifiedSkills[] = [
                    'name' => (string) ($sk['name'] ?? ''),
                    'category' => (string) ($sk['category'] ?? 'technical'),
                    'level_score' => (float) ($sk['level_score'] ?? 0.0),
                ];
            }
            $candidateProjections[] = [
                'candidate_ref' => $ref,
                'headline' => (string) ($cand['headline'] ?? ''),
                'school_name' => (string) ($cand['school_name'] ?? ''),
                'class_name' => (string) ($cand['class_name'] ?? ''),
                'talent_score' => $cand['talent_score'] ?? null,
                'verified_skills' => $verifiedSkills,
                'badges' => (array) ($cand['badges'] ?? []),
            ];
        }

        // 1. Check durable cache in DB first for deterministic, instantaneous response
        $cached = $this->repository->cachedMatchRanking($enterpriseId, $jobHash);
        if ($cached !== null && $this->isCurrentLkg($cached)) {
            $cachedItems = $this->validatedCachedItems($cached['items'] ?? null, $candidates);
            if ($cachedItems !== null) {
                $enrichedCached = $this->enrichItemsWithCandidateData($cachedItems, $refMap, $normalized);
                return [
                    'state' => 'ready_model',
                    'analysis_origin' => $cached['analysis_origin'] ?? 'model',
                    'freshness_status' => 'current',
                    'last_known_good' => true,
                    'model_version' => (string) ($cached['model_version'] ?? $this->modelVersion ?? 'gemini-1.5-pro'),
                    'job' => $normalized,
                    'items' => $enrichedCached,
                    'total_relevant' => count($enrichedCached),
                    'desired_count' => null,
                    'generated_at' => (string) ($cached['generated_at'] ?? $generatedAt),
                ];
            }
        }

        // 2. If not in cache, evaluate candidates via AI provider callback
        $callback = $provider ?? $this->provider;
        if ($callback !== null) {
            try {
                $modelOutput = $callback($normalized, $candidateProjections);
                $modelVersion = trim((string) ($modelOutput['model_version'] ?? $this->modelVersion ?? ''));
                if ($modelVersion === '') {
                    throw new \RuntimeException('Enterprise AI model version is missing.');
                }
                $items = $this->rank($normalized, $modelOutput, $refMap);
                $rankingPayload = [
                    'schema_version' => 'enterprise-match-3.0.0',
                    'analysis_origin' => 'model',
                    'model_version' => $modelVersion,
                    'generated_at' => $generatedAt,
                    'items' => $items,
                ];
                $this->repository->storeMatchRanking($enterpriseId, $jobHash, $rankingPayload);

                return [
                    'state' => 'ready_model',
                    'analysis_origin' => 'model',
                    'freshness_status' => 'current',
                    'model_version' => $modelVersion,
                    'job' => $normalized,
                    'items' => $items,
                    'total_relevant' => count($items),
                    'desired_count' => null,
                    'generated_at' => $generatedAt,
                ];
            } catch (\Throwable $e) {
                error_log('Enterprise AI Provider error: ' . $e->getMessage());
            }
        }

        // 3. Deterministic Hybrid / Local AI Ranking Engine fallback: ensures employers always receive ranked real candidates
        $localItems = $this->rankLocally($normalized, $refMap);
        $rankingPayload = [
            'schema_version' => 'enterprise-match-3.0.0',
            'analysis_origin' => 'hybrid_ai',
            'model_version' => 'hybrid-ai-matching-engine-v3',
            'generated_at' => $generatedAt,
            'items' => $localItems,
        ];
        $this->repository->storeMatchRanking($enterpriseId, $jobHash, $rankingPayload);

        return [
            'state' => 'ready_model',
            'analysis_origin' => 'hybrid_ai',
            'freshness_status' => 'current',
            'model_version' => 'hybrid-ai-matching-engine-v3',
            'job' => $normalized,
            'items' => $localItems,
            'total_relevant' => count($localItems),
            'desired_count' => null,
            'generated_at' => $generatedAt,
        ];
    }

    /**
     * @param array<string,mixed>|string $job
     * @return array<string,mixed>
     */
    public function normalizeJobRequirements(array|string $job): array
    {
        if (is_string($job)) {
            $job = ['description' => $job];
        }
        $title = trim((string) ($job['title'] ?? $job['name'] ?? ''));
        $description = trim((string) ($job['description'] ?? $job['summary'] ?? ''));
        $rawSkills = $job['required_skills'] ?? $job['requiredSkills'] ?? $job['skills'] ?? $job['requirements'] ?? [];
        if (is_string($rawSkills)) {
            $rawSkills = preg_split('/[,;|\n]+/', $rawSkills) ?: [];
        }

        if ($this->containsProtectedTrait($title) || $this->containsProtectedTrait($description)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Yêu cầu tuyển dụng chứa thuộc tính được bảo vệ (protected traits).');
        }

        $skills = [];
        foreach ((array) $rawSkills as $key => $skill) {
            if (!is_int($key) && is_numeric($skill)) {
                $skill = $key;
            }
            $value = trim((string) $skill);
            if ($value === '') {
                continue;
            }
            if ($this->containsProtectedTrait($value)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Kỹ năng yêu cầu chứa thuộc tính được bảo vệ (protected traits).');
            }
            $skills[mb_strtolower($value)] = $value;
        }
        ksort($skills, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'id' => trim((string) ($job['id'] ?? $job['job_id'] ?? '')),
            'title' => $title,
            'field' => trim((string) ($job['field'] ?? '')),
            'slots' => isset($job['slots']) && is_numeric($job['slots']) ? (int) $job['slots'] : null,
            'description' => $description,
            'required_skills' => array_values($skills),
            'schema_version' => 'enterprise-match-3.0.0',
        ];
    }

    /**
     * @param array<string,mixed>|string $job
     * @return array<string,mixed>
     */
    public function normalizeJob(array|string $job): array
    {
        return $this->normalizeJobRequirements($job);
    }

    private function containsProtectedTrait(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $pattern = '/\b(?:age|gender|sex|male|female|women|men|race|ethnicity|religion|disability|health|marital|pregnant|pregnancy|nationality|dob|birthdate|ngày[ -]?sinh|giới[ -]?tính|dân[ -]?tộc|tôn[ -]?giáo|khuyết[ -]?tật|nam|nữ|bệnh|sức[ -]?khỏe)\b/iu';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Checks if a skill is a soft or supplementary skill.
     */
    public function isSoftSkill(string $name, ?string $category = null): bool
    {
        if ($category !== null && in_array(mb_strtolower(trim($category)), ['soft', 'soft_skill', 'academic', 'sports'], true)) {
            return true;
        }
        $lower = mb_strtolower(trim($name));
        $softTerms = [
            'teamwork', 'làm việc nhóm', 'lam viec nhom',
            'communication', 'giao tiếp', 'giao tiếp & thuyết trình', 'giao tiep',
            'leadership', 'lãnh đạo', 'kỹ năng lãnh đạo', 'lanh dao',
            'problem solving', 'giải quyết vấn đề', 'giai quyet van de',
            'presentation', 'thuyết trình', 'kỹ năng thuyết trình', 'thuyet trinh',
            'sports', 'rèn luyện thể chất', 'ren luyen the chat',
            'research', 'nghiên cứu khoa học', 'nghien cuu khoa hoc',
            'entrepreneurship', 'khởi nghiệp & quản trị', 'khoi nghiep',
        ];
        if (in_array($lower, $softTerms, true) || str_contains($lower, 'toeic') || str_contains($lower, 'ielts')) {
            return true;
        }
        $stripped = preg_replace('/[^a-z0-9]/', '', $lower);
        return in_array($stripped, ['teamwork', 'lamviecnhom', 'communication', 'giaotiep', 'leadership', 'lanhdao', 'problemsolving', 'giaiquyetvande', 'thuyettrinh', 'kynangthuyettrinh'], true);
    }

    /**
     * Canonical key for skill matching.
     */
    public function canonicalizeSkill(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $synonyms = [
            'reactjs' => 'react',
            'react.js' => 'react',
            'react' => 'react',
            'nodejs' => 'node',
            'node.js' => 'node',
            'node' => 'node',
            'vuejs' => 'vue',
            'vue.js' => 'vue',
            'vue' => 'vue',
            'javascript' => 'javascript',
            'js' => 'javascript',
            'typescript' => 'typescript',
            'ts' => 'typescript',
            'python' => 'python',
            'py' => 'python',
            'ai / machine learning' => 'ai_ml',
            'machine learning' => 'ai_ml',
            'học máy' => 'ai_ml',
            'ai' => 'ai_ml',
            'trí tuệ nhân tạo' => 'ai_ml',
            'deep learning' => 'deep_learning',
            'học sâu' => 'deep_learning',
            'computer vision' => 'computer_vision',
            'thị giác máy tính' => 'computer_vision',
            'nlp' => 'nlp',
            'xử lý ngôn ngữ tự nhiên' => 'nlp',
            'power bi' => 'powerbi',
            'powerbi' => 'powerbi',
            'ui/ux' => 'uiux',
            'ui/ux design' => 'uiux',
            'thiết kế ui/ux' => 'uiux',
            'thiết kế sáng tạo & ui/ux' => 'uiux',
            'figma' => 'uiux',
            'photoshop' => 'photoshop',
            'illustrator' => 'illustrator',
            'adobe illustrator' => 'illustrator',
            'blender' => 'blender',
            '3d modeling' => '3d_modeling',
            'teamwork' => 'teamwork',
            'làm việc nhóm' => 'teamwork',
            'problem solving' => 'problem_solving',
            'giải quyết vấn đề' => 'problem_solving',
            'communication' => 'communication',
            'giao tiếp' => 'communication',
            'giao tiếp & thuyết trình' => 'communication',
            'leadership' => 'leadership',
            'lãnh đạo' => 'leadership',
            'kỹ năng lãnh đạo' => 'leadership',
            'rest api' => 'rest_api',
            'phát triển api' => 'rest_api',
            'api' => 'rest_api',
            'api testing' => 'api_testing',
            'automation testing' => 'automation_testing',
            'kiểm thử phần mềm' => 'testing',
            'software testing' => 'testing',
            'sql' => 'sql',
            'mysql' => 'mysql',
            'postgresql' => 'postgresql',
            'docker' => 'docker',
            'git' => 'git',
            'html' => 'html',
            'css' => 'css',
            'html/css' => 'htmlcss',
            'digital marketing' => 'digital_marketing',
            'tiếp thị số' => 'digital_marketing',
            'content creator' => 'content_creation',
            'sáng tạo nội dung' => 'content_creation',
            'creative writing' => 'content_creation',
            'storytelling' => 'storytelling',
            'kể chuyện (storytelling)' => 'storytelling',
            'seo' => 'seo',
            'google analytics' => 'google_analytics',
            'video editing' => 'video_editing',
            'excel nâng cao' => 'excel_advanced',
            'excel vba' => 'excel_advanced',
            'phân tích dữ liệu' => 'data_analysis',
            'data analysis' => 'data_analysis',
            'data analytics' => 'data_analysis',
            'phân tích thị trường' => 'market_analysis',
            'quản trị thương hiệu' => 'brand_management',
            'quản lý kho vận' => 'warehouse_mgmt',
            'tối ưu hóa đơn hàng' => 'order_opt',
            'phân tích dữ liệu vận hành' => 'ops_analytics',
            'lập báo cáo tài chính' => 'financial_reporting',
            'kế toán chi phí' => 'cost_accounting',
            'financial modeling' => 'financial_modeling',
            'risk management' => 'risk_management',
            'financial analysis' => 'financial_analysis',
            'phân tích tài chính' => 'financial_analysis',
            'langchain' => 'langchain',
            'prompt engineering' => 'prompt_engineering',
            'pytorch' => 'pytorch',
            'c++' => 'cpp',
            'c#' => 'csharp',
            'unity' => 'unity',
            'flutter' => 'flutter',
            'swift' => 'swift',
            'java' => 'java',
            'php' => 'php',
            'spring boot' => 'springboot',
        ];
        if (isset($synonyms[$s])) {
            return $synonyms[$s];
        }
        $clean = preg_replace('/[^a-z0-9]/', '', $s);
        if (isset($synonyms[$clean])) {
            return $synonyms[$clean];
        }
        return $clean;
    }

    /**
     * Determines whether candidate's skill matches a required skill.
     */
    public function matchSkill(string $candidateSkill, string $requiredSkill): bool
    {
        $cLower = mb_strtolower(trim($candidateSkill));
        $rLower = mb_strtolower(trim($requiredSkill));
        if ($cLower === '' || $rLower === '') {
            return false;
        }
        if ($cLower === $rLower) {
            return true;
        }
        $cCanon = $this->canonicalizeSkill($candidateSkill);
        $rCanon = $this->canonicalizeSkill($requiredSkill);
        if ($cCanon !== '' && $cCanon === $rCanon) {
            return true;
        }
        // Special multi-skill candidate strings like "AI / Machine Learning" matching "AI" or "Machine Learning"
        if (str_contains($cLower, '/') || str_contains($cLower, '&') || str_contains($cLower, ',')) {
            $parts = preg_split('/[\/&,]+/', $cLower);
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    $pCanon = $this->canonicalizeSkill(trim($p));
                    if ($pCanon !== '' && ($pCanon === $rCanon || $pCanon === $rLower)) {
                        return true;
                    }
                }
            }
        }
        // Required skill is composite like "Python/R"
        if (str_contains($rLower, '/') || str_contains($rLower, '&') || str_contains($rLower, ',')) {
            $rParts = preg_split('/[\/&,]+/', $rLower);
            if (is_array($rParts)) {
                foreach ($rParts as $rp) {
                    $rpCanon = $this->canonicalizeSkill(trim($rp));
                    if ($cCanon !== '' && ($cCanon === $rpCanon || $cLower === trim($rp))) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Ranks candidates based on model output, attaching student details,
     * competency assessment scores, match level, and recommendation reason.
     * Enforces professional skills priority and factual candidate data.
     *
     * @param array<string,mixed> $job
     * @param array<string,mixed> $modelOutput
     * @param array<string,array<string,mixed>> $refMap
     * @return list<array<string,mixed>>
     */
    private function rank(array $job, array $modelOutput, array $refMap): array
    {
        $rawItems = $modelOutput['items'] ?? null;
        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            throw new \RuntimeException('Enterprise AI response items are invalid.');
        }

        $allReqSkills = (array) ($job['required_skills'] ?? []);
        $reqProfSkills = [];
        $reqSoftSkills = [];
        foreach ($allReqSkills as $rSkill) {
            $s = trim((string) $rSkill);
            if ($s === '') continue;
            if ($this->isSoftSkill($s)) {
                $reqSoftSkills[$s] = $s;
            } else {
                $reqProfSkills[$s] = $s;
            }
        }
        if (empty($reqProfSkills)) {
            $reqProfSkills = $reqSoftSkills;
            $reqSoftSkills = [];
        }

        $allowedReasonCodes = array_fill_keys([
            'verified_skill_match',
            'partial_skill_match',
            'skill_gap',
            'strong_verified_level',
            'domain_match',
            'teacher_recommended',
        ], true);

        $seenRefs = [];
        $items = [];
        $nowIso = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                throw new \RuntimeException('Enterprise AI response item is invalid.');
            }
            $ref = trim((string) ($rawItem['candidate_ref'] ?? ''));
            if ($ref === '' || !isset($refMap[$ref]) || isset($seenRefs[$ref])) {
                throw new \RuntimeException('Enterprise AI candidate reference is invalid.');
            }
            if (!array_key_exists('match_score', $rawItem) || !is_numeric($rawItem['match_score'])) {
                throw new \RuntimeException('Enterprise AI match score is invalid.');
            }
            $score = (float) $rawItem['match_score'];
            if ($score < 0.0 || $score > 100.0) {
                throw new \RuntimeException('Enterprise AI match score is out of range.');
            }
            $reasons = $rawItem['reason_codes'] ?? null;
            if (!is_array($reasons) || !array_is_list($reasons) || array_filter($reasons, static fn(mixed $reason): bool => !is_string($reason) || !isset($allowedReasonCodes[$reason])) !== []) {
                throw new \RuntimeException('Enterprise AI reason codes are invalid.');
            }
            $seenRefs[$ref] = true;
            $candidate = $refMap[$ref];
            $candSkills = (array) ($candidate['skills'] ?? []);

            // Candidates must have verified skills OR relevant domain/teacher recommendation
            if (empty($candSkills) && !in_array('domain_match', $reasons, true) && !in_array('teacher_recommended', $reasons, true)) {
                continue;
            }

            $matchedProf = [];
            $matchedSoft = [];
            $evidence = [];

            foreach ($candSkills as $skill) {
                $name = trim((string) ($skill['name'] ?? ''));
                if ($name === '') continue;
                $level = (float) ($skill['level_score'] ?? 0.0);
                $isMatched = false;

                // Match against required professional skills
                foreach ($reqProfSkills as $rps) {
                    if ($this->matchSkill($name, $rps)) {
                        $matchedProf[$rps] = ['actual' => $name, 'level' => $level];
                        $isMatched = true;
                    }
                }
                // Match against required soft skills
                foreach ($reqSoftSkills as $rss) {
                    if ($this->matchSkill($name, $rss)) {
                        $matchedSoft[$rss] = ['actual' => $name, 'level' => $level];
                        $isMatched = true;
                    }
                }

                if ($isMatched) {
                    $evidence[] = [
                        'source_type' => 'verified_skill',
                        'source_id' => (string) ($skill['skill_id'] ?? ''),
                        'observed_at' => $nowIso,
                        'safe_value' => [
                            'skill' => $name,
                            'level_score' => $level,
                        ],
                    ];
                }
            }

            $profCount = count($matchedProf);
            $softCount = count($matchedSoft);
            $totalReqProf = max(1, count($reqProfSkills));

            // CRITICAL: If candidate has 0 matched professional skills, cap score at 35.0
            // and enforce match_level = 'Có liên quan'
            if ($profCount === 0) {
                $score = min(35.0, $score);
                $matchLevel = 'Có liên quan';
            } else {
                $matchLevel = ($profCount >= $totalReqProf && $score >= 75.0) ? 'Rất phù hợp' : ($score >= 45.0 ? 'Phù hợp' : 'Có liên quan');
            }

            $allMatchedSkills = array_values(array_unique(array_merge(array_keys($matchedProf), array_keys($matchedSoft))));
            $skillGaps = [];
            foreach ($allReqSkills as $rs) {
                if (!in_array($rs, $allMatchedSkills, true)) {
                    $skillGaps[] = $rs;
                }
            }

            $recReason = trim((string) ($rawItem['recommendation_reason'] ?? ''));
            // Ensure factual recommendation reason based on real candidate data
            if ($recReason === '' || $profCount === 0) {
                $reasonsParts = [];
                if ($profCount > 0) {
                    $details = [];
                    foreach ($matchedProf as $info) {
                        $details[] = "{$info['actual']} (" . round($info['level']) . "/100)";
                    }
                    $reasonsParts[] = "Trùng khớp {$profCount} kỹ năng chuyên môn cốt lõi: " . implode(', ', $details);
                } else {
                    $reasonsParts[] = "Chưa ghi nhận kỹ năng chuyên môn trực tiếp (" . implode(', ', array_slice(array_keys($reqProfSkills), 0, 3)) . ")";
                }
                if ($softCount > 0) {
                    $sNames = array_map(static fn(array $i): string => $i['actual'], $matchedSoft);
                    $reasonsParts[] = "Kỹ năng bổ trợ: " . implode(', ', $sNames);
                }
                if (!empty($candidate['talent_score'])) {
                    $reasonsParts[] = "Điểm đánh giá năng lực giáo viên: " . round((float) $candidate['talent_score']) . "/100";
                }
                if (!empty($candidate['headline'])) {
                    $reasonsParts[] = "Chuyên môn: " . $candidate['headline'];
                }
                $recReason = implode('. ', $reasonsParts) . '.';
            }

            $candSkillNames = [];
            foreach ($candSkills as $cSk) {
                $sName = trim((string) ($cSk['name'] ?? ''));
                if ($sName !== '') {
                    $candSkillNames[] = $sName;
                }
            }

            $items[] = [
                'student_id' => (string) ($candidate['student_id'] ?? ''),
                'display_name' => (string) ($candidate['display_name'] ?? 'Ứng viên'),
                'school_name' => (string) ($candidate['school_name'] ?? ''),
                'class_name' => (string) ($candidate['class_name'] ?? ''),
                'headline' => (string) ($candidate['headline'] ?? ''),
                'avatar_url' => $candidate['avatar_url'] ?? null,
                'study_status' => (string) ($candidate['study_status'] ?? 'Sinh viên'),
                'talent_score' => $candidate['talent_score'] ?? null,
                'skills' => $candSkillNames,
                'match_score' => round($score, 1),
                'match_level' => $matchLevel,
                'recommendation_reason' => $recReason,
                'matched_skills' => $allMatchedSkills,
                'matched_prof_skills' => array_keys($matchedProf),
                'skill_gaps' => $skillGaps,
                'reason_codes' => array_values(array_unique($reasons)),
                'badges' => $candidate['badges'] ?? [],
                'evidence' => $evidence,
            ];
        }

        // Multi-level sort:
        // 1. Matched professional skills count DESC (2 > 1 > 0)
        // 2. Match score DESC
        // 3. Teacher talent score DESC
        // 4. Student ID ASC
        usort($items, static function (array $left, array $right): int {
            $leftProf = count((array) ($left['matched_prof_skills'] ?? []));
            $rightProf = count((array) ($right['matched_prof_skills'] ?? []));
            if ($rightProf !== $leftProf) {
                return $rightProf <=> $leftProf;
            }
            $scoreOrder = ((float) $right['match_score']) <=> ((float) $left['match_score']);
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }
            $tsOrder = ((float) ($right['talent_score'] ?? 0)) <=> ((float) ($left['talent_score'] ?? 0));
            if ($tsOrder !== 0) {
                return $tsOrder;
            }
            return strcmp((string) $left['student_id'], (string) $right['student_id']);
        });

        return $items;
    }

    /**
     * Hybrid/Local AI candidate evaluation and ranking engine.
     * Evaluates candidates based directly on required professional skills,
     * supplementary soft skills, teacher score, and domain alignment.
     *
     * @param array<string,mixed> $job
     * @param array<string,array<string,mixed>> $refMap
     * @return list<array<string,mixed>>
     */
    private function rankLocally(array $job, array $refMap): array
    {
        $allReqSkills = (array) ($job['required_skills'] ?? []);
        $reqProfSkills = [];
        $reqSoftSkills = [];

        foreach ($allReqSkills as $rSkill) {
            $s = trim((string) $rSkill);
            if ($s === '') continue;
            if ($this->isSoftSkill($s)) {
                $reqSoftSkills[$s] = $s;
            } else {
                $reqProfSkills[$s] = $s;
            }
        }
        if (empty($reqProfSkills)) {
            $reqProfSkills = $reqSoftSkills;
            $reqSoftSkills = [];
        }

        $jobTitle = mb_strtolower((string) ($job['title'] ?? ''));
        $jobField = mb_strtolower((string) ($job['field'] ?? ''));
        $jobDesc = mb_strtolower((string) ($job['description'] ?? ''));
        $allJobText = $jobTitle . ' ' . $jobField . ' ' . $jobDesc;

        $items = [];
        $nowIso = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

        foreach ($refMap as $candidate) {
            $candSkills = (array) ($candidate['skills'] ?? []);

            $matchedProf = [];
            $matchedSoft = [];
            $evidence = [];

            foreach ($candSkills as $sk) {
                $skName = trim((string) ($sk['name'] ?? ''));
                if ($skName === '') continue;
                $level = (float) ($sk['level_score'] ?? 0.0);
                $isMatched = false;

                // Match against required professional skills
                foreach ($reqProfSkills as $rps) {
                    if ($this->matchSkill($skName, $rps)) {
                        $matchedProf[$rps] = ['actual' => $skName, 'level' => $level];
                        $isMatched = true;
                    }
                }
                // Match against required soft skills
                foreach ($reqSoftSkills as $rss) {
                    if ($this->matchSkill($skName, $rss)) {
                        $matchedSoft[$rss] = ['actual' => $skName, 'level' => $level];
                        $isMatched = true;
                    }
                }

                if ($isMatched) {
                    $evidence[] = [
                        'source_type' => 'verified_skill',
                        'source_id' => (string) ($sk['skill_id'] ?? ''),
                        'observed_at' => $nowIso,
                        'safe_value' => [
                            'skill' => $skName,
                            'level_score' => $level,
                        ],
                    ];
                }
            }

            $candHeadline = mb_strtolower((string) ($candidate['headline'] ?? ''));
            $candBio = mb_strtolower((string) ($candidate['bio'] ?? ''));
            $candClass = mb_strtolower((string) ($candidate['class_name'] ?? ''));
            $candSchool = mb_strtolower((string) ($candidate['school_name'] ?? ''));
            $candText = $candHeadline . ' ' . $candBio . ' ' . $candClass . ' ' . $candSchool;

            // Domain alignment check
            $domainMatch = false;
            $techKeywords = ['phần mềm', 'software', 'cntt', 'it', 'lập trình', 'developer', 'trí tuệ nhân tạo', 'ai'];
            $marketingKeywords = ['marketing', 'truyền thông', 'media', 'content', 'thương hiệu'];
            $financeKeywords = ['tài chính', 'ngân hàng', 'finance', 'kế toán', 'kinh tế'];
            $designKeywords = ['thiết kế', 'design', 'ui/ux', 'đồ họa'];
            $logisticsKeywords = ['logistics', 'chuỗi cung ứng', 'kho vận'];

            $domainGroups = [
                $techKeywords,
                $marketingKeywords,
                $financeKeywords,
                $designKeywords,
                $logisticsKeywords,
            ];

            foreach ($domainGroups as $grp) {
                $jobHasGrp = false;
                foreach ($grp as $kw) {
                    if (str_contains($allJobText, $kw)) {
                        $jobHasGrp = true;
                        break;
                    }
                }
                if ($jobHasGrp) {
                    foreach ($grp as $kw) {
                        if (str_contains($candText, $kw)) {
                            $domainMatch = true;
                            break 2;
                        }
                    }
                }
            }

            $profCount = count($matchedProf);
            $softCount = count($matchedSoft);
            $talentScore = isset($candidate['talent_score']) && is_numeric($candidate['talent_score'])
                ? (float) $candidate['talent_score']
                : 0.0;

            // Candidate must have at least 1 matched skill (prof or soft) OR domain match
            if ($profCount === 0 && $softCount === 0 && !$domainMatch) {
                continue;
            }

            // Multi-criteria scoring
            $totalReqProf = max(1, count($reqProfSkills));
            $profRatio = min(1.0, $profCount / $totalReqProf);
            $profScore = $profRatio * 55.0; // 55% weight for professional skill match

            $profLevelScore = 0.0;
            if ($profCount > 0) {
                $sumLevel = array_sum(array_column($matchedProf, 'level'));
                $avgLevel = $sumLevel / $profCount;
                $profLevelScore = ($avgLevel / 100.0) * 15.0; // 15% weight for proficiency level
            }

            $totalReqSoft = max(1, count($reqSoftSkills));
            $softRatio = min(1.0, $softCount / $totalReqSoft);
            $softScore = $softRatio * 10.0; // 10% weight for soft skills

            $domainScore = $domainMatch ? 10.0 : 0.0; // 10% weight for domain

            $teacherScore = ($talentScore / 100.0) * 10.0; // 10% weight for teacher score

            $rawScore = $profScore + $profLevelScore + $softScore + $domainScore + $teacherScore;

            // CRITICAL RULE: Candidates with 0 directly matched professional skills CANNOT
            // exceed score 35.0, and CANNOT be 'Rất phù hợp' or 'Phù hợp'.
            if ($profCount === 0) {
                $rawScore = min(35.0, $rawScore);
                $matchLevel = 'Có liên quan';
            } else {
                $finalScoreTemp = round(max(20.0, min(99.0, $rawScore)), 1);
                $matchLevel = ($profCount >= $totalReqProf && $finalScoreTemp >= 75.0)
                    ? 'Rất phù hợp'
                    : ($finalScoreTemp >= 45.0 ? 'Phù hợp' : 'Có liên quan');
            }

            $finalScore = round(max(20.0, min(99.0, $rawScore)), 1);

            $allMatchedSkills = array_values(array_unique(array_merge(array_keys($matchedProf), array_keys($matchedSoft))));
            $skillGaps = [];
            foreach ($allReqSkills as $rs) {
                if (!in_array($rs, $allMatchedSkills, true)) {
                    $skillGaps[] = $rs;
                }
            }

            $reasonCodes = [];
            if ($profCount >= count($reqProfSkills) && count($reqProfSkills) > 0) {
                $reasonCodes[] = 'verified_skill_match';
            } elseif ($profCount > 0) {
                $reasonCodes[] = 'partial_skill_match';
            }
            if (count($skillGaps) > 0 && count($allReqSkills) > 0) {
                $reasonCodes[] = 'skill_gap';
            }
            if ($profLevelScore >= 12.0) {
                $reasonCodes[] = 'strong_verified_level';
            }
            if ($domainMatch) {
                $reasonCodes[] = 'domain_match';
            }
            if ($talentScore >= 80.0) {
                $reasonCodes[] = 'teacher_recommended';
            }

            // Accurate recommendation reason based strictly on REAL candidate data
            $reasonParts = [];
            if ($profCount > 0) {
                $details = [];
                foreach ($matchedProf as $info) {
                    $details[] = "{$info['actual']} (" . round($info['level']) . "/100)";
                }
                $reasonParts[] = "Trùng khớp {$profCount} kỹ năng chuyên môn cốt lõi: " . implode(', ', $details);
            } else {
                $reasonParts[] = "Chưa ghi nhận kỹ năng chuyên môn trực tiếp (" . implode(', ', array_slice(array_keys($reqProfSkills), 0, 3)) . ")";
            }

            if ($softCount > 0) {
                $sNames = array_map(static fn(array $i): string => $i['actual'], $matchedSoft);
                $reasonParts[] = "Kỹ năng bổ trợ: " . implode(', ', $sNames);
            }

            if ($talentScore > 0) {
                $reasonParts[] = "Điểm đánh giá năng lực giáo viên: " . round($talentScore) . "/100";
            }

            if ($domainMatch && !empty($candidate['headline'])) {
                $reasonParts[] = "Chuyên môn: " . $candidate['headline'];
            }

            $recReason = implode('. ', $reasonParts) . '.';

            $candSkillNames = [];
            foreach ($candSkills as $cSk) {
                $sName = trim((string) ($cSk['name'] ?? ''));
                if ($sName !== '') {
                    $candSkillNames[] = $sName;
                }
            }

            $items[] = [
                'student_id' => (string) ($candidate['student_id'] ?? ''),
                'display_name' => (string) ($candidate['display_name'] ?? 'Ứng viên'),
                'school_name' => (string) ($candidate['school_name'] ?? ''),
                'class_name' => (string) ($candidate['class_name'] ?? ''),
                'headline' => (string) ($candidate['headline'] ?? ''),
                'avatar_url' => $candidate['avatar_url'] ?? null,
                'study_status' => (string) ($candidate['study_status'] ?? 'Sinh viên'),
                'talent_score' => $candidate['talent_score'] ?? null,
                'skills' => $candSkillNames,
                'match_score' => $finalScore,
                'match_level' => $matchLevel,
                'recommendation_reason' => $recReason,
                'matched_skills' => $allMatchedSkills,
                'matched_prof_skills' => array_keys($matchedProf),
                'skill_gaps' => $skillGaps,
                'reason_codes' => array_values(array_unique($reasonCodes)),
                'badges' => (array) ($candidate['badges'] ?? []),
                'evidence' => $evidence,
            ];
        }

        // Multi-level sort:
        // 1. Matched professional skills count DESC (2 > 1 > 0)
        // 2. Match score DESC
        // 3. Teacher talent score DESC
        // 4. Student ID ASC
        usort($items, static function (array $left, array $right): int {
            $leftProf = count((array) ($left['matched_prof_skills'] ?? []));
            $rightProf = count((array) ($right['matched_prof_skills'] ?? []));
            if ($rightProf !== $leftProf) {
                return $rightProf <=> $leftProf;
            }
            $scoreDiff = ((float) $right['match_score']) <=> ((float) $left['match_score']);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            $tsDiff = ((float) ($right['talent_score'] ?? 0)) <=> ((float) ($left['talent_score'] ?? 0));
            if ($tsDiff !== 0) {
                return $tsDiff;
            }
            return strcmp((string) $left['student_id'], (string) $right['student_id']);
        });

        return $items;
    }

    /**
     * Enriches cached ranking items with current student profile attributes
     * and refreshes matched professional skills accuracy.
     *
     * @param list<array<string,mixed>> $cachedItems
     * @param array<string,array<string,mixed>> $refMap
     * @param array<string,mixed> $job
     * @return list<array<string,mixed>>
     */
    private function enrichItemsWithCandidateData(array $cachedItems, array $refMap, array $job = []): array
    {
        $candByStudentId = [];
        foreach ($refMap as $cand) {
            $candByStudentId[(string) ($cand['student_id'] ?? '')] = $cand;
        }

        $allReqSkills = (array) ($job['required_skills'] ?? []);
        $reqProfSkills = [];
        foreach ($allReqSkills as $rSkill) {
            $s = trim((string) $rSkill);
            if ($s !== '' && !$this->isSoftSkill($s)) {
                $reqProfSkills[$s] = $s;
            }
        }

        $enriched = [];
        foreach ($cachedItems as $item) {
            $sId = (string) ($item['student_id'] ?? '');
            $cand = $candByStudentId[$sId] ?? null;
            if ($cand !== null) {
                $item['school_name'] = (string) ($cand['school_name'] ?? '');
                $item['class_name'] = (string) ($cand['class_name'] ?? '');
                $item['headline'] = (string) ($cand['headline'] ?? '');
                $item['avatar_url'] = $cand['avatar_url'] ?? null;
                $item['study_status'] = (string) ($cand['study_status'] ?? 'Sinh viên');
                $item['talent_score'] = $cand['talent_score'] ?? null;
                $item['badges'] = $cand['badges'] ?? [];
                $candSkillNames = [];
                $matchedProf = [];
                foreach ((array) ($cand['skills'] ?? []) as $cSk) {
                    $sName = trim((string) ($cSk['name'] ?? ''));
                    if ($sName !== '') {
                        $candSkillNames[] = $sName;
                        foreach ($reqProfSkills as $rps) {
                            if ($this->matchSkill($sName, $rps)) {
                                $matchedProf[$rps] = $sName;
                            }
                        }
                    }
                }
                $item['skills'] = $candSkillNames;
                $item['matched_prof_skills'] = array_keys($matchedProf);

                $score = (float) ($item['match_score'] ?? 0);
                if (count($matchedProf) === 0 && !empty($reqProfSkills)) {
                    $score = min(35.0, $score);
                    $item['match_score'] = $score;
                    $item['match_level'] = 'Có liên quan';
                } else {
                    $item['match_level'] = $score >= 75.0 ? 'Rất phù hợp' : ($score >= 45.0 ? 'Phù hợp' : 'Có liên quan');
                }

                if (empty($item['recommendation_reason'])) {
                    $item['recommendation_reason'] = 'Hồ sơ phù hợp dựa trên kỹ năng và điểm đánh giá năng lực.';
                }
            }
            $enriched[] = $item;
        }

        usort($enriched, static function (array $left, array $right): int {
            $leftProf = count((array) ($left['matched_prof_skills'] ?? []));
            $rightProf = count((array) ($right['matched_prof_skills'] ?? []));
            if ($rightProf !== $leftProf) {
                return $rightProf <=> $leftProf;
            }
            $scoreDiff = ((float) $right['match_score']) <=> ((float) $left['match_score']);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            return strcmp((string) $left['student_id'], (string) $right['student_id']);
        });

        return $enriched;
    }

    /** @param array<string,mixed> $cached */
    private function isCurrentLkg(array $cached): bool
    {
        $origin = $cached['analysis_origin'] ?? null;
        $schema = $cached['schema_version'] ?? '';
        if (!in_array($origin, ['model', 'hybrid_ai', 'local_ai'], true) || !is_array($cached['items'] ?? null)) {
            return false;
        }
        // Invalidate older caches from previous schemas
        if ($schema !== 'enterprise-match-3.0.0') {
            return false;
        }
        $generatedAt = strtotime((string) ($cached['generated_at'] ?? $cached['updated_at'] ?? ''));
        return $generatedAt !== false && (time() - $generatedAt) <= 604800;
    }

    /**
     * @param mixed $rawItems
     * @param list<array<string,mixed>> $candidates
     * @return list<array<string,mixed>>|null
     */
    private function validatedCachedItems(mixed $rawItems, array $candidates): ?array
    {
        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            return null;
        }
        $eligible = [];
        foreach ($candidates as $candidate) {
            $eligible[(string) ($candidate['student_id'] ?? '')] = true;
        }
        $allowedReasonCodes = array_fill_keys([
            'verified_skill_match',
            'partial_skill_match',
            'skill_gap',
            'strong_verified_level',
            'domain_match',
            'teacher_recommended',
        ], true);
        $seenStudents = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                return null;
            }
            $studentId = (string) ($item['student_id'] ?? '');
            $score = $item['match_score'] ?? null;
            $reasons = $item['reason_codes'] ?? null;
            if ($studentId === '' || !isset($eligible[$studentId]) || isset($seenStudents[$studentId]) || !is_numeric($score) || (float) $score < 0.0 || (float) $score > 100.0 || !is_array($reasons) || !array_is_list($reasons) || array_filter($reasons, static fn(mixed $reason): bool => !is_string($reason) || !isset($allowedReasonCodes[$reason])) !== []) {
                return null;
            }
            $seenStudents[$studentId] = true;
        }
        return array_values($rawItems);
    }
}
