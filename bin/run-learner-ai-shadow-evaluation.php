<?php

declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';

$options=getopt('', ['manifest:','approval:','simulated']);
$manifest=$options['manifest']??null; $approval=$options['approval']??null;
$deny=static function(string $reason): never { echo json_encode(['status'=>'MODEL_VISIBLE_BLOCKED','reason'=>$reason,'provider_calls'=>0],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL; exit(2); };
if(!is_string($manifest)||trim($manifest)===''||!is_string($approval)||trim($approval)==='')$deny('manifest_and_approval_required');
if(!is_file($manifest))$deny('manifest_missing');
$expected=getenv('TALENTHUB_AI_EVALUATION_MANIFEST_SHA256');
if(!is_string($expected)||preg_match('/\A[0-9a-f]{64}\z/',$expected)!==1||!hash_equals($expected,hash_file('sha256',$manifest)))$deny('manifest_hash_mismatch');
$approved=getenv('TALENTHUB_AI_EVALUATION_APPROVAL_REFERENCE');
if(!is_string($approved)||!hash_equals($approved,$approval))$deny('approval_reference_mismatch');
$visible=getenv('TALENTHUB_AI_VISIBLE_PERCENT');
if($visible!==false&&trim((string)$visible)!=='0')$deny('visibility_must_be_zero');
if(!isset($options['simulated']))$deny('primary_shadow_execution_not_authorized');
echo json_encode(['status'=>'SIMULATED_ONLY','decision'=>'MODEL_VISIBLE_BLOCKED','provider_calls'=>0],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(0);
