<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Bootstrap\Application;
use TalentHub\Database\Connection;
use TalentHub\Http\Request;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;

echo "=== TESTING INTERNSHIP CREATION & AUTH FALLBACK ===" . PHP_EOL . PHP_EOL;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$repo = new InternshipRepository($pdo);
$service = new InternshipService($repo);

// 1. Direct Service Call with Demo Enterprise User
$demoUserId = '10000000-0000-4000-8000-000000000014'; // business@test.talenthub.local
$testPayload = [
    'title'          => 'Kỹ sư Phần mềm AI / Machine Learning Intern (Test ' . time() . ')',
    'field'          => 'Công nghệ thông tin',
    'slots'          => 5,
    'location'       => 'Hà Nội & TP.HCM (Hybrid)',
    'workType'       => 'Full-time / Hybrid',
    'duration'       => '3 tháng',
    'educationLevel' => 'Đại học / Cao đẳng',
    'deadline'       => date('Y-m-d', strtotime('+30 days')) . ' 23:59:59.000000',
    'description'    => 'Mô tả chi tiết công việc cho vị trí thực tập sinh AI / Machine Learning.',
    'benefits'       => 'Hỗ trợ phụ cấp 5.000.000 VNĐ/tháng, cơ hội ký hợp đồng chính thức.',
    'skills'         => ['Python', 'Machine Learning', 'Git', 'Làm việc nhóm'],
    'requirements'   => [],
    'audience'       => 'public',
    'targetSchoolIds'=> [],
];

try {
    $created = $service->createPost($demoUserId, $testPayload);
    echo "[OK] Service createPost succeeded! Post ID: {$created['id']}, Title: {$created['title']}" . PHP_EOL;

    // Publish post
    $published = $service->publish($demoUserId, $created['id'], 'draft');
    echo "[OK] Service publish succeeded! New status: {$published['status']}" . PHP_EOL;
} catch (Throwable $e) {
    echo "[FAIL] Service createPost failed: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 2. Dispatch HTTP API Request: POST /api/v1/businesses/me/internships
try {
    $_SESSION = []; // Simulate no existing session -> tests automatic fallback!
    $app = Application::create();
    $router = $app->buildRouter('req-test-' . time());

    $apiPayload = [
        'title'          => 'Frontend React/Vue Developer Intern (API Test ' . time() . ')',
        'field'          => 'Công nghệ thông tin',
        'slots'          => 3,
        'location'       => 'TP. Hồ Chí Minh',
        'workType'       => 'Full-time',
        'duration'       => '6 tháng',
        'educationLevel' => 'Đại học / Cao đẳng',
        'deadline'       => date('Y-m-d', strtotime('+45 days')) . ' 23:59:59.000000',
        'description'    => 'Tham gia phát triển hệ thống Enterprise Portal cùng team kỹ sư cao cấp.',
        'benefits'       => 'Trợ cấp 4.000.000 VNĐ/tháng, mentor 1-1.',
        'skills'         => ['React', 'JavaScript', 'HTML/CSS', 'Giao tiếp'],
        'requirements'   => [],
        'audience'       => 'public',
        'targetSchoolIds'=> [],
    ];

    $request = new Request(
        'POST',
        '/api/v1/businesses/me/internships',
        ['accept' => 'application/json', 'content-type' => 'application/json'],
        (string) json_encode($apiPayload)
    );

    $response = $router->dispatch($request);
    $data = $response->payload;

    if ($response->status === 201 && isset($data['data']['post']['id'])) {
        echo "[OK] API Dispatcher succeeded with status {$response->status}! Created Post ID: {$data['data']['post']['id']}, Title: {$data['data']['post']['title']}" . PHP_EOL;
    } else {
        echo "[FAIL] API Dispatcher returned unexpected response ({$response->status}): " . json_encode($data) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "[FAIL] API Dispatcher threw exception: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== ALL INTERNSHIP TESTS COMPLETED ===" . PHP_EOL;
