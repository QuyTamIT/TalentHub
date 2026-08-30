<?php
declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';
require __DIR__ . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry;

$studentId = '22000000-53d8-4897-8d68-ab3f78db0ce9';
$config = require __DIR__ . '/config/database.php';
$pdo = (new Connection($config))->connect();
$sessionConfig = require __DIR__ . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_STUDENT;
$context = new LearnerApiContext(
    $pdo,
    new SessionManager($sessionConfig),
    new PermissionService($pdo),
    'probe-opportunity-runtime-00000000000000000000000000000000',
);
$service = $context->opportunityMatchService($studentId);
$property = static function (object $object, string $name): mixed {
    $reflection = new ReflectionProperty($object, $name);
    return $reflection->getValue($object);
};
$method = static function (object $object, string $name, array $arguments): mixed {
    $reflection = new ReflectionMethod($object, $name);
    return $reflection->invoke($object, ...$arguments);
};
$inputBuilder = $property($service, 'inputBuilder');
$candidateSupplier = $property($service, 'candidateEvidenceSupplier');
$scorer = $property($service, 'scorer');
$decisionResolver = $property($service, 'decisionResolver');
$input = $inputBuilder($studentId);
$profile = LearnerOpportunityProfile::fromInput($input);
$rawCandidates = $candidateSupplier($studentId);
$candidates = $method($service, 'eligibleCandidates', [$profile, $rawCandidates]);
$scored = [];
$scoredCandidates = [];
foreach ($candidates as $candidate) {
    try {
        $scored[$candidate->catalogId()] = $scorer($profile, $candidate);
        $scoredCandidates[] = $candidate;
    } catch (Throwable $exception) {
        echo "score_error=" . $exception->getMessage() . PHP_EOL;
    }
}
$allowList = $method($service, 'sortAndSlice', [$scoredCandidates, $scored]);
$analysisContext = $method($service, 'analysisContext', [$profile, $candidates, $scoredCandidates, $scored]);
$maxStructured = 0;
foreach ($scored as $score) $maxStructured = max($maxStructured, $score->structuredScore());
$mode = $allowList === [] ? 'no_fit' : (count($allowList) < 3 ? ($maxStructured < 60 ? 'low_fit' : 'recommendation') : 'top3');
$decision = $decisionResolver($studentId);
$requestContext = new RecommendationContext(
    $decision->allowedScopes(),
    'probe-request-000000000000000000000000000000',
    'probe-idempotency-00000000000000000000000000',
    $studentId,
    $decision->decisionHash(),
    $decision->policyVersion(),
);
$engine = $property($service, 'engine');
$engineProperty = new ReflectionProperty($engine, 'provider');
$provider = $engineProperty->getValue($engine);
$authorizerProperty = new ReflectionProperty($engine, 'authorizer');
$authorizer = $authorizerProperty->getValue($engine);
$request = OpportunityMatchPromptRegistry::create($profile, $allowList, $scored, $requestContext, $mode, $analysisContext);
echo json_encode([
    'mode' => $mode,
    'candidate_count' => count($candidates),
    'allow_count' => count($allowList),
    'structured_scores' => array_map(static fn ($score): int => $score->structuredScore(), $scored),
    'prompt_version' => $request->promptVersion(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
$response = $provider->generate($request, $authorizer);
echo json_encode([
    'provider_success' => $response->isSuccess(),
    'provider_error' => $response->errorCode(),
    'items' => array_map(static function (mixed $item): array {
        if (!is_array($item)) return ['type' => gettype($item)];
        $result = ['keys' => array_keys($item)];
        foreach (['catalog_id', 'gemini_score', 'matched_skill_codes', 'missing_skill_codes', 'missing_conditions', 'evidence_ref_ids'] as $key) {
            if (array_key_exists($key, $item)) $result[$key] = $item[$key];
        }
        foreach (['why_not_fit_yet', 'why_fit', 'improvement_steps'] as $key) {
            if (array_key_exists($key, $item)) $result[$key] = is_array($item[$key]) ? $item[$key] : mb_substr((string) $item[$key], 0, 180);
        }
        return $result;
    }, $response->items()),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
try {
    $engine->generate($profile, $allowList, $scored, $requestContext, $mode, $analysisContext);
    echo "validation=ok" . PHP_EOL;
} catch (Throwable $exception) {
    echo 'validation_error=' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL;
}
