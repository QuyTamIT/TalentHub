<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

$authenticatedLearner = PortalGuard::requireRole(RoleCodes::STUDENT, '/app/learner/index.php');
