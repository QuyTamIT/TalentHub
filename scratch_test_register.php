<?php
$env = parse_ini_file('.env');
$pdo = new PDO("mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find a student
$stmt = $pdo->prepare("SELECT sp.id, u.id as userId FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE u.email = 'tamlangtu2005@gmail.com' LIMIT 1");
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'src/Support/Uuid.php';
require_once 'src/Http/ApiException.php';
require_once 'app/learner/data/Contracts/ActivityCommandRepository.php';
require_once 'app/learner/data/Database/DatabaseActivityCommandRepository.php';
require_once 'app/learner/data/Service/ActivityRegistrationService.php';

$repo = new \TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository($pdo);
$service = new \TalentHub\Learner\Data\Service\ActivityRegistrationService($repo);

try {
    $result = $service->register(
        $student['id'],
        $student['userId'],
        'test-req',
        ['activityId' => 'a07f36bc-5ba9-4030-82de-c851dff0db47']
    );
    print_r($result);
} catch (\TalentHub\Http\ApiException $e) {
    echo "ApiException: " . $e->getMessage() . "\n";
    echo "Code: " . $e->errorCode . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
