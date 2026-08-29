<?php
declare(strict_types=1);

/**
 * TalentHub - Employer Candidates Search
 * Entry point for /app/employer/candidates.php
 */
require dirname(__DIR__, 2) . '/bin/bootstrap.php';

// Include the unified enterprise talents search controller/view
require dirname(__DIR__) . '/enterprise/talents.php';
