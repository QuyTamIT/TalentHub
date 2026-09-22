<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

TalentHub\Bootstrap\Application::create()->run();
