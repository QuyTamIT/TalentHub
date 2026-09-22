<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = app_href('/app/school/projects/') . ($qs !== '' ? '?' . $qs : '');
header('Location: ' . $target, true, 302);
exit;
