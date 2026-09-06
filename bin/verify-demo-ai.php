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
    $diagnostics = RecommendationConfig::fromEnvironment([])->diagnostics();
    $verification = CompleteAiDemoVerifier::verify(
        $pdo,
        null,
        ($diagnostics['strict_mode'] ?? false) === true,
    );

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
            . ' sources=skills:' . (int) ($sources['skill'] ?? 0)
            . ',assessments:' . (int) ($sources['assessment'] ?? 0)
            . ',activities:' . (int) ($sources['activity_experience'] ?? 0)
            . ',evaluations:' . (int) ($sources['evaluation'] ?? 0)
            . ',opportunities:' . (int) ($sources['opportunity'] ?? 0)
            . PHP_EOL,
        );
    }
    fwrite(STDOUT, 'diagnostics=enabled:' . ($diagnostics['enabled'] ? 'true' : 'false') . ',provider:' . self_value($diagnostics['provider'] ?? null) . ',model:' . self_value($diagnostics['model'] ?? null) . ',strict_mode:' . (($diagnostics['strict_mode'] ?? false) ? 'true' : 'false') . ',timeout_seconds:' . (int) $diagnostics['timeout_seconds'] . PHP_EOL);
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
