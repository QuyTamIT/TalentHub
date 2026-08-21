<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoAiRunner.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoAiRunner;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoDataset;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

try {
    $failureCode = 'environment_forbidden';
    $environment = Environment::appEnvironment();
    if (!in_array($environment, ['local', 'test'], true)) {
        throw new RuntimeException('Complete AI demo runner is allowed only in local/test.');
    }

    $failureCode = 'configuration_invalid';
    $recommendationConfig = RecommendationConfig::fromEnvironment($_ENV);
    if (!$recommendationConfig->enabled()
        || !$recommendationConfig->shadowEnabled()
        || $recommendationConfig->visiblePercent() !== 0) {
        throw new RuntimeException('Complete AI demo runner requires enabled shadow AI with visible percentage zero.');
    }

    $failureCode = 'provider_unavailable';
    $databaseConfig = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($databaseConfig))->connect();
    $provider = new HttpRecommendationProvider($recommendationConfig);
    $modelEngine = new ModelRecommendationEngine(
        $provider,
        new RuleRecommendationEngine(),
        new PromptRegistry(),
        new RecommendationRateLimiter(
            $recommendationConfig->perStudentLimit(),
            $recommendationConfig->globalLimit(),
            60,
            static fn (): int => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp(),
        ),
        $recommendationConfig,
        new RecommendationResultValidator(),
    );

    $heroes = CompleteAiDemoDataset::heroStudentIds();
    $report = CompleteAiDemoAiRunner::run($pdo, $modelEngine, array_values($heroes));
    $failed = false;
    foreach ($heroes as $hero => $studentId) {
        $row = is_array($report[$studentId] ?? null) ? $report[$studentId] : [];
        $status = match (true) {
            ($row['quality_state'] ?? null) !== 'ready',
            ($row['visible_engine'] ?? null) !== 'rule' => 'shadow_invalid',
            ($row['shadow_engine'] ?? null) !== 'model' => 'provider_fallback',
            ($row['shadow_valid'] ?? null) !== true => 'shadow_invalid',
            default => 'ok',
        };
        $failed = $failed || $status !== 'ok';
        $violationCodes = is_array($row['shadow_violation_codes'] ?? null)
            ? array_values(array_filter(
                array_map('demo_ai_safe_scalar', $row['shadow_violation_codes']),
                static fn (string $code): bool => $code !== 'none',
            ))
            : [];
        fwrite(
            STDOUT,
            'hero=' . demo_ai_safe_scalar($hero)
            . ' quality_state=' . demo_ai_safe_scalar($row['quality_state'] ?? null)
            . ' visible_engine=' . demo_ai_safe_scalar($row['visible_engine'] ?? null)
            . ' visible_item_count=' . (int) ($row['visible_item_count'] ?? 0)
            . ' shadow_engine=' . demo_ai_safe_scalar($row['shadow_engine'] ?? null)
            . ' shadow_valid=' . (($row['shadow_valid'] ?? false) === true ? 'true' : 'false')
            . ' shadow_violation_codes=' . ($violationCodes === [] ? 'none' : implode(',', $violationCodes))
            . ' status=' . $status
            . PHP_EOL,
        );
    }
    exit($failed ? 1 : 0);
} catch (Throwable) {
    fwrite(STDERR, 'status=' . ($failureCode ?? 'provider_unavailable') . PHP_EOL);
    exit(1);
}

function demo_ai_safe_scalar(mixed $value): string
{
    if (!is_string($value) || trim($value) === '') {
        return 'none';
    }
    $safe = preg_replace('/[^A-Za-z0-9._:-]/', '_', trim($value));
    return is_string($safe) && $safe !== '' ? $safe : 'none';
}
