<?php
/**
 * TalentHub Enterprise - Create Post Redirect Proxy
 *
 * Safely redirects legacy/short links from /app/enterprise/create.php
 * to the canonical path /app/enterprise/internships/create.php.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';

$target = function_exists('app_href') ? app_href('/app/enterprise/internships/create.php') : '/app/enterprise/internships/create.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target, true, 302);
exit;
