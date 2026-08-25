<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use TalentHub\Auth\Service\OrganizationRegistrationService;
use TalentHub\Database\Connection;
$service=new OrganizationRegistrationService((new Connection(require dirname(__DIR__).'/config/database.php'))->connect());
$deleted=$service->purgeExpired();
fwrite(STDOUT,"[OK] Deleted {$deleted} expired organization registration request(s).".PHP_EOL);
