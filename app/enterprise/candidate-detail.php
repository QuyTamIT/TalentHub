<?php
declare(strict_types=1);

/**
 * TalentHub Enterprise - Candidate Detail Forwarder
 * Aliases /app/enterprise/candidate-detail.php to /app/enterprise/talents/detail.php
 */
require dirname(__DIR__, 2) . '/bin/bootstrap.php';

$id = $_GET['id'] ?? $_GET['studentId'] ?? $_GET['candidateId'] ?? '';
$target = app_href('/app/enterprise/talents/detail.php' . (!empty($id) ? ('?id=' . urlencode((string) $id)) : ''));
header('Location: ' . $target);
exit;
