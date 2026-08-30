<?php
declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';
require __DIR__ . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Rbac\Service\PermissionService;

$studentId = '22000000-53d8-4897-8d68-ab3f78db0ce9';
$config = require __DIR__ . '/config/database.php';
$pdo = (new Connection($config))->connect();
$sessionConfig = require __DIR__ . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_STUDENT;
$requestId = 'live-ui-' . bin2hex(random_bytes(12));
$idempotencyKey = 'live-ui-' . bin2hex(random_bytes(16));
$context = new LearnerApiContext(
    $pdo,
    new SessionManager($sessionConfig),
    new PermissionService($pdo),
    $requestId,
);
$result = $context->opportunityMatchService($studentId)->generate($studentId, $requestId, $idempotencyKey);

$safe = [
    'state' => $result['state'] ?? null,
    'item_count' => is_array($result['items'] ?? null) ? count($result['items']) : 0,
    'items' => [],
    'analysis_keys' => is_array($result['analysis'] ?? null) ? array_keys($result['analysis']) : [],
];
foreach (is_array($result['items'] ?? null) ? $result['items'] : [] as $item) {
    if (!is_array($item)) continue;
    $safe['items'][] = [
        'catalog_id' => $item['catalog_id'] ?? null,
        'match_score' => $item['match_score'] ?? null,
        'analysis_kind' => $item['analysis_kind'] ?? null,
    ];
}
echo json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
