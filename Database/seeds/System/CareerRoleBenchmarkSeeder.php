<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\System;

use PDO;
use RuntimeException;
use Throwable;

final class CareerRoleBenchmarkSeeder
{
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /** @var list<string> */
    public const ASSESSMENT_FAMILIES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

    /**
     * Canonical reference skills that career role benchmarks may reference.
     * Codes match the canonical registry in SchoolAiProjectCatalogDataset
     * plus the deterministic synthetic codes used by learner staging datasets.
     * Names and categories are authoritative; the seeder only inserts when a
     * code is missing, so existing canonical rows are never overwritten.
     *
     * @var array<string, array{name: string, category: string}>
     */
    private const CANONICAL_SKILL_CATALOG = [
        'python' => ['name' => 'Lap trinh Python', 'category' => 'technical'],
        'data_analysis' => ['name' => 'Phan tich du lieu', 'category' => 'technical'],
        'problem_solving' => ['name' => 'Giai quyet van de', 'category' => 'academic'],
        'research' => ['name' => 'Nghien cuu', 'category' => 'academic'],
        'communication' => ['name' => 'Giao tiep', 'category' => 'soft'],
        'teamwork' => ['name' => 'Lam viec nhom', 'category' => 'soft'],
        'leadership' => ['name' => 'Lanh dao', 'category' => 'soft'],
        'entrepreneurship' => ['name' => 'Khoi nghiep', 'category' => 'business'],
        'creative_design' => ['name' => 'Thiet ke sang tao', 'category' => 'creative'],
        'sports_discipline' => ['name' => 'Ren luyen the chat', 'category' => 'sports'],
        'iot' => ['name' => 'IoT Fundamentals', 'category' => 'technology'],
        'prototyping' => ['name' => 'Prototype Practice', 'category' => 'technology'],
        'visual_design' => ['name' => 'Visual Design Practice', 'category' => 'creative'],
        'storytelling' => ['name' => 'Digital Storytelling', 'category' => 'creative'],
        'peer_mentoring' => ['name' => 'Peer Mentoring', 'category' => 'community'],
        'facilitation' => ['name' => 'Group Facilitation', 'category' => 'community'],
        'pitching' => ['name' => 'Idea Pitching', 'category' => 'entrepreneurship'],
        'initiative' => ['name' => 'Project Initiative', 'category' => 'entrepreneurship'],
        'spreadsheet' => ['name' => 'Spreadsheet Accuracy', 'category' => 'operations'],
        'quality_control' => ['name' => 'Quality Control Practice', 'category' => 'operations'],
        'machine_learning' => ['name' => 'Machine Learning', 'category' => 'technical'],
        'pytorch' => ['name' => 'PyTorch', 'category' => 'technical'],
        'algorithms' => ['name' => 'Cấu trúc dữ liệu và giải thuật', 'category' => 'technical'],
        'mlops' => ['name' => 'MLOps', 'category' => 'technical'],
        'generative_ai' => ['name' => 'Generative AI', 'category' => 'technical'],
        'nlp' => ['name' => 'Xử lý ngôn ngữ tự nhiên', 'category' => 'technical'],
        'sql' => ['name' => 'SQL', 'category' => 'technical'],
        'statistical_analysis' => ['name' => 'Phân tích thống kê', 'category' => 'data'],
        'power_bi' => ['name' => 'Power BI', 'category' => 'data'],
        'tableau' => ['name' => 'Tableau', 'category' => 'data'],
        'nodejs' => ['name' => 'Node.js', 'category' => 'technical'],
        'api_development' => ['name' => 'Phát triển API', 'category' => 'technical'],
        'database_design' => ['name' => 'Thiết kế cơ sở dữ liệu', 'category' => 'technical'],
        'git' => ['name' => 'Git', 'category' => 'technical'],
        'software_testing' => ['name' => 'Kiểm thử phần mềm', 'category' => 'technical'],
        'javascript' => ['name' => 'JavaScript', 'category' => 'technical'],
        'react' => ['name' => 'React', 'category' => 'technical'],
        'html_css' => ['name' => 'HTML/CSS', 'category' => 'technical'],
        'typescript' => ['name' => 'TypeScript', 'category' => 'technical'],
        'ui_ux_design' => ['name' => 'Thiết kế UI/UX', 'category' => 'creative'],
        'digital_marketing' => ['name' => 'Digital Marketing', 'category' => 'marketing'],
        'content_marketing' => ['name' => 'Content Marketing', 'category' => 'marketing'],
        'google_analytics' => ['name' => 'Google Analytics', 'category' => 'marketing'],
        'facebook_ads' => ['name' => 'Facebook Ads', 'category' => 'marketing'],
        'roi_analysis' => ['name' => 'Phân tích ROI', 'category' => 'marketing'],
    ];

    /**
     * Career role benchmark records. Codes are stable identifiers (unique key);
     * the seeder matches by code, so renames here will be reflected on re-run.
     *
     * @var array<string, array{title: string, category: string, isActive: bool}>
     */
    private const ROLE_BENCHMARKS = [
        'ai_engineer' => ['title' => 'AI Engineer', 'category' => 'technology', 'isActive' => true],
        'data_analyst' => ['title' => 'Data Analyst', 'category' => 'data', 'isActive' => true],
        'backend_developer' => ['title' => 'Backend Developer', 'category' => 'technology', 'isActive' => true],
        'frontend_developer' => ['title' => 'Frontend Developer', 'category' => 'technology', 'isActive' => true],
        'fullstack_developer' => ['title' => 'Fullstack Developer', 'category' => 'technology', 'isActive' => true],
        'digital_marketing' => ['title' => 'Digital Marketing Specialist', 'category' => 'marketing', 'isActive' => true],
    ];

    /**
     * Skill requirements per role. Each row references a canonical skill code,
     * a minimum acceptable student score, a relative weight (positive decimal
     * where weights per role sum to 100), and whether the skill is required.
     *
     * @var array<string, list<array{skill: string, minimumScore: float, weight: float, isRequired: bool}>>
     */
    private const SKILL_REQUIREMENTS = [
        'ai_engineer' => [
            ['skill' => 'python', 'minimumScore' => 70.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'machine_learning', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'pytorch', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'algorithms', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'mlops', 'minimumScore' => 55.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'generative_ai', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'nlp', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'data_analysis', 'minimumScore' => 65.0, 'weight' => 5.0, 'isRequired' => true],
            ['skill' => 'problem_solving', 'minimumScore' => 70.0, 'weight' => 5.0, 'isRequired' => false],
        ],
        'data_analyst' => [
            ['skill' => 'sql', 'minimumScore' => 70.0, 'weight' => 25.0, 'isRequired' => true],
            ['skill' => 'data_analysis', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'statistical_analysis', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'spreadsheet', 'minimumScore' => 65.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'power_bi', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'tableau', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'python', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'storytelling', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
        ],
        'backend_developer' => [
            ['skill' => 'nodejs', 'minimumScore' => 65.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'python', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => false],
            ['skill' => 'sql', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'api_development', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'database_design', 'minimumScore' => 65.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'git', 'minimumScore' => 60.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'algorithms', 'minimumScore' => 60.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'software_testing', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
        ],
        'frontend_developer' => [
            ['skill' => 'javascript', 'minimumScore' => 70.0, 'weight' => 25.0, 'isRequired' => true],
            ['skill' => 'react', 'minimumScore' => 65.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'html_css', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'typescript', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'ui_ux_design', 'minimumScore' => 55.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'git', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'problem_solving', 'minimumScore' => 60.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'teamwork', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
        ],
        'fullstack_developer' => [
            ['skill' => 'javascript', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'react', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'nodejs', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'sql', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'api_development', 'minimumScore' => 65.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'html_css', 'minimumScore' => 65.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'python', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'git', 'minimumScore' => 60.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'problem_solving', 'minimumScore' => 65.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'teamwork', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
        ],
        'digital_marketing' => [
            ['skill' => 'digital_marketing', 'minimumScore' => 70.0, 'weight' => 20.0, 'isRequired' => true],
            ['skill' => 'content_marketing', 'minimumScore' => 65.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'google_analytics', 'minimumScore' => 60.0, 'weight' => 15.0, 'isRequired' => true],
            ['skill' => 'facebook_ads', 'minimumScore' => 55.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'roi_analysis', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => true],
            ['skill' => 'data_analysis', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'storytelling', 'minimumScore' => 60.0, 'weight' => 10.0, 'isRequired' => false],
            ['skill' => 'communication', 'minimumScore' => 65.0, 'weight' => 5.0, 'isRequired' => false],
            ['skill' => 'creative_design', 'minimumScore' => 55.0, 'weight' => 5.0, 'isRequired' => false],
        ],
    ];

    /**
     * Assessment signal targets per role. Each entry pairs a normalized
     * assessment family with a scorer-compatible dimension code (Holland
     * R/I/A/S/E/C; MBTI E/I/S/N/T/F/J/P poles; DISC D/I/S/C; Multiple Intelligence LOGI/LING/SPAT/
     * MUSIC/BODY/INTER/INTRA/NAT), a target score, and a relative weight.
     * Weights per role sum to 100 across families.
     *
     * @var array<string, list<array{family: string, dimension: string, target: float, weight: float}>>
     */
    private const ASSESSMENT_SIGNALS = [
        'ai_engineer' => [
            ['family' => 'holland', 'dimension' => 'I', 'target' => 80.0, 'weight' => 35.0],
            ['family' => 'holland', 'dimension' => 'R', 'target' => 75.0, 'weight' => 20.0],
            ['family' => 'holland', 'dimension' => 'C', 'target' => 60.0, 'weight' => 15.0],
            ['family' => 'mbti', 'dimension' => 'I', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'N', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'T', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'J', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'C', 'target' => 75.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'D', 'target' => 70.0, 'weight' => 5.0],
        ],
        'data_analyst' => [
            ['family' => 'holland', 'dimension' => 'I', 'target' => 80.0, 'weight' => 30.0],
            ['family' => 'holland', 'dimension' => 'C', 'target' => 75.0, 'weight' => 25.0],
            ['family' => 'holland', 'dimension' => 'R', 'target' => 65.0, 'weight' => 15.0],
            ['family' => 'mbti', 'dimension' => 'I', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'S', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'T', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'J', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'C', 'target' => 80.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'S', 'target' => 70.0, 'weight' => 5.0],
        ],
        'backend_developer' => [
            ['family' => 'holland', 'dimension' => 'I', 'target' => 75.0, 'weight' => 35.0],
            ['family' => 'holland', 'dimension' => 'R', 'target' => 70.0, 'weight' => 20.0],
            ['family' => 'holland', 'dimension' => 'C', 'target' => 65.0, 'weight' => 15.0],
            ['family' => 'mbti', 'dimension' => 'I', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'S', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'T', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'J', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'C', 'target' => 75.0, 'weight' => 10.0],
        ],
        'frontend_developer' => [
            ['family' => 'holland', 'dimension' => 'A', 'target' => 80.0, 'weight' => 35.0],
            ['family' => 'holland', 'dimension' => 'I', 'target' => 65.0, 'weight' => 20.0],
            ['family' => 'holland', 'dimension' => 'E', 'target' => 60.0, 'weight' => 15.0],
            ['family' => 'mbti', 'dimension' => 'E', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'N', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'F', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'P', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'I', 'target' => 75.0, 'weight' => 10.0],
        ],
        'fullstack_developer' => [
            ['family' => 'holland', 'dimension' => 'I', 'target' => 75.0, 'weight' => 30.0],
            ['family' => 'holland', 'dimension' => 'R', 'target' => 70.0, 'weight' => 20.0],
            ['family' => 'holland', 'dimension' => 'A', 'target' => 65.0, 'weight' => 20.0],
            ['family' => 'mbti', 'dimension' => 'I', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'N', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'T', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'J', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'D', 'target' => 75.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'I', 'target' => 70.0, 'weight' => 5.0],
        ],
        'digital_marketing' => [
            ['family' => 'holland', 'dimension' => 'E', 'target' => 80.0, 'weight' => 35.0],
            ['family' => 'holland', 'dimension' => 'A', 'target' => 75.0, 'weight' => 20.0],
            ['family' => 'holland', 'dimension' => 'S', 'target' => 65.0, 'weight' => 15.0],
            ['family' => 'mbti', 'dimension' => 'E', 'target' => 70.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'N', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'F', 'target' => 65.0, 'weight' => 5.0],
            ['family' => 'mbti', 'dimension' => 'P', 'target' => 60.0, 'weight' => 5.0],
            ['family' => 'disc', 'dimension' => 'I', 'target' => 75.0, 'weight' => 10.0],
        ],
    ];

    public function run(PDO $pdo): void
    {
        $this->assertRequiredTables($pdo);
        $this->assertDatasetConsistency();
        $pdo->beginTransaction();

        try {
            $this->upsertBenchmarks($pdo);
            $this->pruneObsoleteBenchmarks($pdo);
            $this->upsertSkillRequirements($pdo);
            $this->upsertAssessmentSignals($pdo);
            $this->pruneObsoleteSkillRequirements($pdo);
            $this->pruneObsoleteAssessmentSignals($pdo);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function runWithinTransaction(PDO $pdo): void
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('runWithinTransaction requires an active transaction.');
        }
        $this->assertRequiredTables($pdo);
        $this->assertDatasetConsistency();
        $this->upsertBenchmarks($pdo);
        $this->pruneObsoleteBenchmarks($pdo);
        $this->upsertSkillRequirements($pdo);
        $this->upsertAssessmentSignals($pdo);
        $this->pruneObsoleteSkillRequirements($pdo);
        $this->pruneObsoleteAssessmentSignals($pdo);
    }

    /** @return array{roles: int, skill_requirements: int, assessment_signals: int, canonical_skills: int} */
    public function expectedCounts(): array
    {
        $skillRequirementCount = 0;
        foreach (self::SKILL_REQUIREMENTS as $entries) {
            $skillRequirementCount += count($entries);
        }
        $signalCount = 0;
        foreach (self::ASSESSMENT_SIGNALS as $entries) {
            $signalCount += count($entries);
        }
        return [
            'roles' => count(self::ROLE_BENCHMARKS),
            'skill_requirements' => $skillRequirementCount,
            'assessment_signals' => $signalCount,
            'canonical_skills' => count(self::CANONICAL_SKILL_CATALOG),
        ];
    }

    /**
     * Validate the static dataset without touching a database. Throws when the
     * dataset would violate the schema or produce duplicate rows.
     */
    public function assertDatasetConsistency(): void
    {
        $roleCodes = array_keys(self::ROLE_BENCHMARKS);
        if ($roleCodes !== array_values(array_unique($roleCodes))) {
            throw new RuntimeException('Career role benchmark dataset contains duplicate role codes.');
        }
        foreach (self::ROLE_BENCHMARKS as $code => $benchmark) {
            $this->assertCodeFormat($code, 'role');
            if ($benchmark['title'] === '' || $benchmark['category'] === '') {
                throw new RuntimeException("Role benchmark {$code} must declare a non-empty title and category.");
            }
        }

        $skillPairs = [];
        foreach (self::SKILL_REQUIREMENTS as $roleCode => $entries) {
            $weightSum = 0.0;
            foreach ($entries as $entry) {
                if (!isset(self::CANONICAL_SKILL_CATALOG[$entry['skill']])) {
                    throw new RuntimeException("Role {$roleCode} references undeclared canonical skill: {$entry['skill']}.");
                }
                $this->assertScoreRange($entry['minimumScore'], "Role {$roleCode} skill {$entry['skill']} minimumScore");
                $this->assertWeightRange($entry['weight'], "Role {$roleCode} skill {$entry['skill']} weight");
                $weightSum += $entry['weight'];
                $pair = $roleCode . '|' . $entry['skill'];
                if (isset($skillPairs[$pair])) {
                    throw new RuntimeException("Role {$roleCode} lists skill {$entry['skill']} more than once.");
                }
                $skillPairs[$pair] = true;
            }
            if (round($weightSum, 2) !== 100.0) {
                throw new RuntimeException("Role {$roleCode} skill weights must sum to 100 (got {$weightSum}).");
            }
        }

        $signalPairs = [];
        foreach (self::ASSESSMENT_SIGNALS as $roleCode => $entries) {
            $weightSum = 0.0;
            foreach ($entries as $entry) {
                if (!in_array($entry['family'], self::ASSESSMENT_FAMILIES, true)) {
                    throw new RuntimeException("Role {$roleCode} signal uses invalid family: {$entry['family']}.");
                }
                $this->assertDimensionFormat($entry['dimension'], "Role {$roleCode} {$entry['family']} dimension");
                $this->assertAssessmentDimension($entry['family'], $entry['dimension'], $roleCode);
                $this->assertScoreRange($entry['target'], "Role {$roleCode} {$entry['family']} target");
                $this->assertWeightRange($entry['weight'], "Role {$roleCode} {$entry['family']} weight");
                $weightSum += $entry['weight'];
                $pair = $roleCode . '|' . $entry['family'] . '|' . $entry['dimension'];
                if (isset($signalPairs[$pair])) {
                    throw new RuntimeException("Role {$roleCode} lists {$entry['family']} dimension {$entry['dimension']} more than once.");
                }
                $signalPairs[$pair] = true;
            }
            if (round($weightSum, 2) !== 100.0) {
                throw new RuntimeException("Role {$roleCode} assessment weights must sum to 100 (got {$weightSum}).");
            }
        }

        // Confirm every signal/requirement role has a matching benchmark.
        foreach (self::SKILL_REQUIREMENTS as $roleCode => $_) {
            if (!isset(self::ROLE_BENCHMARKS[$roleCode])) {
                throw new RuntimeException("Skill requirement references missing role benchmark: {$roleCode}.");
            }
        }
        foreach (self::ASSESSMENT_SIGNALS as $roleCode => $_) {
            if (!isset(self::ROLE_BENCHMARKS[$roleCode])) {
                throw new RuntimeException("Assessment signal references missing role benchmark: {$roleCode}.");
            }
        }
    }

    private function assertRequiredTables(PDO $pdo): void
    {
        foreach (['career_role_benchmarks', 'career_role_skill_requirements', 'career_role_assessment_signals', 'skills'] as $table) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => $table]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException("Career role benchmark seed requires migrated table: {$table}.");
            }
        }
    }

    private function assertCodeFormat(string $code, string $context): void
    {
        if (preg_match('/\A[a-z][a-z0-9_]{1,99}\z/', $code) !== 1) {
            throw new RuntimeException("{$context} code {$code} must be lowercase snake_case (max 100 chars).");
        }
    }

    private function assertDimensionFormat(string $dimension, string $context): void
    {
        if (preg_match('/\A[A-Z][A-Z0-9_]{0,29}\z/', $dimension) !== 1) {
            throw new RuntimeException("{$context} {$dimension} must be uppercase canonical code (max 30 chars).");
        }
    }

    private function assertAssessmentDimension(string $family, string $dimension, string $roleCode): void
    {
        $allowed = match ($family) {
            'holland' => ['R', 'I', 'A', 'S', 'E', 'C'],
            'mbti' => ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'],
            'disc' => ['D', 'I', 'S', 'C'],
            'multiple_intelligence' => ['LOGI', 'LING', 'SPAT', 'MUSIC', 'BODY', 'INTER', 'INTRA', 'NAT'],
            default => [],
        };
        if (!in_array($dimension, $allowed, true)) {
            throw new RuntimeException(
                "Role {$roleCode} signal uses unsupported {$family} dimension: {$dimension}."
            );
        }
    }

    private function assertScoreRange(float $score, string $context): void
    {
        if ($score < 0.0 || $score > 100.0) {
            throw new RuntimeException("{$context} must be between 0 and 100 (got {$score}).");
        }
    }

    private function assertWeightRange(float $weight, string $context): void
    {
        if ($weight <= 0.0 || $weight > 100.0) {
            throw new RuntimeException("{$context} must be between 0 (exclusive) and 100 inclusive (got {$weight}).");
        }
    }

    private function upsertBenchmarks(PDO $pdo): void
    {
        $select = $pdo->prepare('SELECT id FROM career_role_benchmarks WHERE code = :code');
        $insert = $pdo->prepare(
            'INSERT INTO career_role_benchmarks (id, code, title, category, isActive) VALUES (:id, :code, :title, :category, :isActive)'
        );
        $update = $pdo->prepare(
            'UPDATE career_role_benchmarks SET title = :title, category = :category, isActive = :isActive WHERE id = :id AND code = :code'
        );

        foreach (self::ROLE_BENCHMARKS as $code => $benchmark) {
            $id = self::roleId($code);
            $select->execute(['code' => $code]);
            $existingId = $select->fetchColumn();
            $params = [
                'id' => $id,
                'code' => $code,
                'title' => $benchmark['title'],
                'category' => $benchmark['category'],
                'isActive' => $benchmark['isActive'] ? 1 : 0,
            ];
            if ($existingId === false) {
                $insert->execute($params);
            } else {
                $params['id'] = (string) $existingId;
                $update->execute($params);
            }
        }
    }

    private function pruneObsoleteBenchmarks(PDO $pdo): void
    {
        $codes = array_keys(self::ROLE_BENCHMARKS);
        $placeholders = implode(', ', array_fill(0, count($codes), '?'));
        $delete = $pdo->prepare(
            "DELETE FROM career_role_benchmarks WHERE code NOT IN ({$placeholders})"
        );
        $delete->execute($codes);
    }

    private function upsertSkillRequirements(PDO $pdo): void
    {
        $select = $pdo->prepare(
            'SELECT id FROM career_role_skill_requirements WHERE roleId = :roleId AND skillId = :skillId'
        );
        $insert = $pdo->prepare(
            'INSERT INTO career_role_skill_requirements (id, roleId, skillId, minimumScore, weight, isRequired)'
            . ' VALUES (:id, :roleId, :skillId, :minimumScore, :weight, :isRequired)'
        );
        $update = $pdo->prepare(
            'UPDATE career_role_skill_requirements'
            . ' SET minimumScore = :minimumScore, weight = :weight, isRequired = :isRequired'
            . ' WHERE id = :id AND roleId = :roleId AND skillId = :skillId'
        );

        foreach (self::SKILL_REQUIREMENTS as $roleCode => $entries) {
            $roleId = $this->requireRoleId($pdo, $roleCode);
            foreach ($entries as $entry) {
                $skillId = $this->ensureCanonicalSkill($pdo, $entry['skill']);
                $select->execute(['roleId' => $roleId, 'skillId' => $skillId]);
                $existingId = $select->fetchColumn();
                $params = [
                    'id' => self::skillRequirementId($roleId, $skillId),
                    'roleId' => $roleId,
                    'skillId' => $skillId,
                    'minimumScore' => $entry['minimumScore'],
                    'weight' => $entry['weight'],
                    'isRequired' => $entry['isRequired'] ? 1 : 0,
                ];
                if ($existingId === false) {
                    $insert->execute($params);
                } else {
                    $params['id'] = (string) $existingId;
                    $update->execute($params);
                }
            }
        }
    }

    private function upsertAssessmentSignals(PDO $pdo): void
    {
        $select = $pdo->prepare(
            'SELECT id FROM career_role_assessment_signals'
            . ' WHERE roleId = :roleId AND assessmentFamily = :family AND dimensionCode = :dimension'
        );
        $insert = $pdo->prepare(
            'INSERT INTO career_role_assessment_signals (id, roleId, assessmentFamily, dimensionCode, targetScore, weight)'
            . ' VALUES (:id, :roleId, :family, :dimension, :target, :weight)'
        );
        $update = $pdo->prepare(
            'UPDATE career_role_assessment_signals SET targetScore = :target, weight = :weight'
            . ' WHERE id = :id AND roleId = :roleId AND assessmentFamily = :family AND dimensionCode = :dimension'
        );

        foreach (self::ASSESSMENT_SIGNALS as $roleCode => $entries) {
            $roleId = $this->requireRoleId($pdo, $roleCode);
            foreach ($entries as $entry) {
                $select->execute([
                    'roleId' => $roleId,
                    'family' => $entry['family'],
                    'dimension' => $entry['dimension'],
                ]);
                $existingId = $select->fetchColumn();
                $params = [
                    'id' => self::assessmentSignalId($roleId, $entry['family'], $entry['dimension']),
                    'roleId' => $roleId,
                    'family' => $entry['family'],
                    'dimension' => $entry['dimension'],
                    'target' => $entry['target'],
                    'weight' => $entry['weight'],
                ];
                if ($existingId === false) {
                    $insert->execute($params);
                } else {
                    $params['id'] = (string) $existingId;
                    $update->execute($params);
                }
            }
        }
    }

    private function pruneObsoleteSkillRequirements(PDO $pdo): void
    {
        $select = $pdo->prepare(
            'SELECT id, skillId FROM career_role_skill_requirements WHERE roleId = :roleId'
        );
        $delete = $pdo->prepare('DELETE FROM career_role_skill_requirements WHERE id = :id AND roleId = :roleId');

        foreach (self::SKILL_REQUIREMENTS as $roleCode => $entries) {
            $roleId = $this->requireRoleId($pdo, $roleCode);
            $desiredSkillIds = [];
            foreach ($entries as $entry) {
                $desiredSkillIds[$this->ensureCanonicalSkill($pdo, $entry['skill'])] = true;
            }

            $select->execute(['roleId' => $roleId]);
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if (!isset($desiredSkillIds[(string) $row['skillId']])) {
                    $delete->execute(['id' => (string) $row['id'], 'roleId' => $roleId]);
                }
            }
        }
    }

    private function pruneObsoleteAssessmentSignals(PDO $pdo): void
    {
        $select = $pdo->prepare(
            'SELECT id, assessmentFamily, dimensionCode FROM career_role_assessment_signals WHERE roleId = :roleId'
        );
        $delete = $pdo->prepare('DELETE FROM career_role_assessment_signals WHERE id = :id AND roleId = :roleId');

        foreach (self::ASSESSMENT_SIGNALS as $roleCode => $entries) {
            $roleId = $this->requireRoleId($pdo, $roleCode);
            $desiredSignals = [];
            foreach ($entries as $entry) {
                $desiredSignals[$entry['family'] . '|' . $entry['dimension']] = true;
            }

            $select->execute(['roleId' => $roleId]);
            foreach ($select->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = (string) $row['assessmentFamily'] . '|' . (string) $row['dimensionCode'];
                if (!isset($desiredSignals[$key])) {
                    $delete->execute(['id' => (string) $row['id'], 'roleId' => $roleId]);
                }
            }
        }
    }

    private function requireRoleId(PDO $pdo, string $roleCode): string
    {
        $statement = $pdo->prepare('SELECT id FROM career_role_benchmarks WHERE code = :code');
        $statement->execute(['code' => $roleCode]);
        $id = $statement->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException("Missing career role benchmark row for code {$roleCode}.");
        }
        return $id;
    }

    /**
     * Return the canonical skill id for $code. When the code is missing from
     * the skills table the canonical reference row is inserted deterministically
     * (stable UUID, code, canonical Vietnamese name, canonical category,
     * status=active). Existing rows are never overwritten.
     */
    private function ensureCanonicalSkill(PDO $pdo, string $code): string
    {
        if (!isset(self::CANONICAL_SKILL_CATALOG[$code])) {
            throw new RuntimeException("Canonical skill catalog is missing code: {$code}.");
        }
        $definition = self::CANONICAL_SKILL_CATALOG[$code];
        $select = $pdo->prepare('SELECT id FROM skills WHERE code = :code');
        $select->execute(['code' => $code]);
        $existingId = $select->fetchColumn();
        if (is_string($existingId) && $existingId !== '') {
            return $existingId;
        }
        $insert = $pdo->prepare(
            'INSERT INTO skills (id, code, name, category, status) VALUES (:id, :code, :name, :category, \'active\')'
        );
        $insert->execute([
            'id' => self::canonicalSkillId($code),
            'code' => $code,
            'name' => $definition['name'],
            'category' => $definition['category'],
        ]);
        $select->execute(['code' => $code]);
        $newId = $select->fetchColumn();
        if (!is_string($newId) || $newId === '') {
            throw new RuntimeException("Failed to ensure canonical skill row for code {$code}.");
        }
        return $newId;
    }

    public static function roleId(string $code): string
    {
        return self::stableId('career-role:' . $code);
    }

    public static function canonicalSkillId(string $code): string
    {
        return self::stableId('career-skill:' . $code);
    }

    public static function skillRequirementId(string $roleId, string $skillId): string
    {
        return self::stableId('career-requirement:' . $roleId . ':' . $skillId);
    }

    public static function assessmentSignalId(string $roleId, string $family, string $dimension): string
    {
        return self::stableId('career-signal:' . $roleId . ':' . $family . ':' . $dimension);
    }

    private static function stableId(string $name): string
    {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) {
            throw new RuntimeException('Invalid UUID namespace.');
        }
        $hash = sha1($namespace . $name);
        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }
}
