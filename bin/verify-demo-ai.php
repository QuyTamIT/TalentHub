<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoVerifier.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoVerifier;
use TalentHub\Learner\Ai\Config\RecommendationConfig;

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();
    $verification = CompleteAiDemoVerifier::verify($pdo);
    $diagnostics = RecommendationConfig::fromEnvironment([])->diagnostics();

    foreach ($verification['counts'] as $name => $count) {
        fwrite(STDOUT, 'count=' . $name . ':' . $count . PHP_EOL);
    }
    foreach ($verification['heroes'] as $hero => $result) {
        $sources = $result['source_counts'] ?? [];
        fwrite(
            STDOUT,
            'hero=' . $hero
            . ' state=' . self_value($result['state'] ?? 'unknown')
            . ' engine_type=' . self_value($result['engine_type'] ?? 'none')
            . ' sources=skills:' . (int) ($sources['skills'] ?? 0)
            . ',assessments:' . (int) ($sources['assessments'] ?? 0)
            . ',activities:' . (int) ($sources['activities'] ?? 0)
            . ',evaluations:' . (int) ($sources['evaluations'] ?? 0)
            . ',opportunities:' . (int) ($sources['opportunities'] ?? 0)
            . PHP_EOL,
        );
    }
    fwrite(STDOUT, 'diagnostics=enabled:' . ($diagnostics['enabled'] ? 'true' : 'false') . ',provider:' . self_value($diagnostics['provider'] ?? null) . ',model:' . self_value($diagnostics['model'] ?? null) . ',timeout_seconds:' . (int) $diagnostics['timeout_seconds'] . PHP_EOL);
    foreach ($verification['violations'] as $violation) {
        fwrite(STDOUT, 'violation=' . self_value($violation) . PHP_EOL);
    }
    exit($verification['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "violation=verification_failed\n");
    exit(1);
}

function self_value(mixed $value): string
{
    $value = is_string($value) ? $value : 'none';
    $value = preg_replace('/[^A-Za-z0-9._:-]/', '_', $value);
    return $value === '' ? 'none' : $value;
}
