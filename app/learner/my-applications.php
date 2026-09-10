<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/student-data.php';

// Redirect to ecosystem.php with enterprises tab and applications tracker view
header('Location: ecosystem.php?tab=enterprises&view=applications', true, 302);
exit;
