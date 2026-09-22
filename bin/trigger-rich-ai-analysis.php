<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Rbac\Service\PermissionService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_STUDENT;

$emails = [
    'sv.tam.ai@btec.local',
    'sv.minh.design@btec.local',
    'sv.ha.mkt@btec.local',
    'sv.quang.biz@btec.local',
    'sv.tuyet.data@btec.local',
    'sv.duyen.log@btec.local',
    'chau.thietke@talenthub.local',
];
if (isset($argv[1]) && $argv[1] !== '') {
    $emails = array_slice($argv, 1);
}

echo "======================================================================\n";
echo " TRIGGER PHÂN TÍCH AI  (đa ngành) — không seed demo roadmap\n";
echo "======================================================================\n\n";

$find = $pdo->prepare(
    'SELECT sp.id AS studentId, u.id AS userId, u.email, u.fullName
     FROM student_profiles sp
     INNER JOIN users u ON u.id = sp.userId
     WHERE u.email = ? LIMIT 1'
);

foreach ($emails as $email) {
    $find->execute([$email]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "[SKIP] not found: {$email}\n";
        continue;
    }
    $studentId = (string) $row['studentId'];
    $userId = (string) $row['userId'];
    $name = (string) $row['fullName'];

    $done = (int) $pdo->query(
        "SELECT COUNT(DISTINCT tt.type)
         FROM test_attempts ta
         INNER JOIN talent_tests tt ON tt.id = ta.testId
         WHERE ta.studentId = " . $pdo->quote($studentId) . " AND ta.status = 'submitted'"
    )->fetchColumn();
    if ($done < 4) {
        echo "[SKIP] {$name} <{$email}> chỉ {$done}/4 bài test\n";
        continue;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $_SESSION = [];
    $_SESSION['user'] = ['id' => $userId, 'email' => $email, 'role' => 'learner'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'learner';
    $_SESSION['logged_in'] = true;

    $context = new LearnerApiContext(
        $pdo,
        new SessionManager($sessionConfig),
        new PermissionService($pdo),
        'rich-ai-' . substr(hash('sha256', $studentId), 0, 12)
    );

    echo "[RUN] {$name} <{$email}> ... ";
    try {
        $result = $context->roadmapService($studentId)->generate(
            $studentId,
            'rich-seed-ai',
            'rich-ai-' . $studentId . '-' . gmdate('YmdHis'),
            true
        );
        $state = (string) ($result['state'] ?? 'unknown');
        if ($state === 'pending') {
            echo "queued (pending)\n";
            continue;
        }
        if (in_array($state, ['ready', 'fresh', 'active', 'completed'], true) || isset($result['executiveSummary']) || isset($result['roadmap'])) {
            echo "OK state={$state}\n";
            continue;
        }
        echo "state={$state} " . json_encode(array_keys($result), JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Throwable $e) {
        echo "FAIL " . $e->getMessage() . "\n";
    }
}

echo "\nNếu state=pending: chạy worker\n";
echo "  php bin/worker-learner-ai-refresh.php\n";
echo "  (cần TALENTHUB_AI_WORKER_BOOTSTRAP=config/learner-ai-worker.php)\n";
