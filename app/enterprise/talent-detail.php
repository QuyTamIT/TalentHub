<?php
/**
 * TalentHub Enterprise - Talent Detail Forwarder
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

$id = $_GET['id'] ?? $_GET['studentId'] ?? '';
$target = app_href('/app/enterprise/talents/detail.php' . (!empty($id) ? ('?id=' . urlencode((string) $id)) : ''));
header('Location: ' . $target);
exit;
