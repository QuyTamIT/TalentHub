<?php
declare(strict_types=1);

/**
 * TalentHub Enterprise - Candidate Detail Forwarder
 * Aliases /app/enterprise/candidate-detail.php to /app/enterprise/talents/detail.php
 */
require dirname(__DIR__, 2) . '/bin/bootstrap.php';

$id = $_GET['id'] ?? $_GET['studentId'] ?? $_GET['candidateId'] ?? '';
$postId = $_GET['postId'] ?? $_GET['jobId'] ?? '';
$params = [];
if (!empty($id)) $params['id'] = (string) $id;
if (!empty($postId)) $params['postId'] = (string) $postId;
$qs = $params !== [] ? ('?' . http_build_query($params)) : '';
$target = app_href('/app/enterprise/talents/detail.php' . $qs);
header('Location: ' . $target);
exit;
