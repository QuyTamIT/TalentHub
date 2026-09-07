<?php

declare(strict_types=1);

/** School notification inbox API (shares the platform `notifications` table). */
require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\PortalNotificationApi;
use TalentHub\Rbac\RoleCodes;

PortalNotificationApi::handle(RoleCodes::SCHOOL, SessionManager::SESSION_SCHOOL);