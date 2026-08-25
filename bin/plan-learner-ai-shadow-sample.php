<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/ai/bootstrap.php';

$options = getopt('', ['format:', 'dry-run', 'manifest:', 'verify-only']);
$format = strtolower((string)($options['format'] ?? 'text'));
$payload = [
    'status' => 'MODEL_VISIBLE_BLOCKED',
    'mode' => isset($options['verify-only']) ? 'verify-only' : 'dry-run',
    'provider_calls' => 0,
    'direct_identifiers_emitted' => 0,
    'sample' => ['high'=>['status'=>'insufficient_sample','sample_size'=>0], 'college'=>['status'=>'insufficient_sample','sample_size'=>0]],
    'reason' => 'approved_manifest_not_supplied',
];
if (isset($options['manifest']) && is_string($options['manifest']) && is_file($options['manifest'])) {
    $json=(string)file_get_contents($options['manifest']);
    $payload['manifest_sha256']=hash('sha256',$json);
    $expected=getenv('TALENTHUB_AI_EVALUATION_MANIFEST_SHA256');
    $payload['manifest_verified']=is_string($expected)&&preg_match('/\A[0-9a-f]{64}\z/',$expected)===1&&hash_equals($expected,$payload['manifest_sha256']);
    $payload['reason']=$payload['manifest_verified']?'execution_not_authorized':'manifest_hash_mismatch';
}
if ($format === 'json') echo json_encode($payload, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . PHP_EOL;
else echo "Phase 12 sample planner: {$payload['status']} ({$payload['reason']}); provider calls=0" . PHP_EOL;
exit(0);
