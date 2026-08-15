<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bin/bootstrap.php';

(new TalentHub\Bootstrap\Application())->run();
