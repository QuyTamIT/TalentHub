<?php
/**
 * TalentHub Enterprise - Talent Pool Forwarder
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

header('Location: ' . app_href('/app/enterprise/talents.php'));
exit;
