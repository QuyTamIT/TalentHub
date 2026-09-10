<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Http\CollectionQuery;
use TalentHub\Http\Request;
use TalentHub\Modules\Notification\Repository\NotificationRepository;
use TalentHub\Modules\Notification\Service\NotificationService;
use TalentHub\Rbac\Service\PermissionService;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$repository = new NotificationRepository($pdo);
$service = new NotificationService($repository);
$permissionService = new PermissionService($pdo);

$users = [];
$statement = $pdo->query(<<<'SQL'
    SELECT u.id, u.email, r.code AS role
    FROM users u
    INNER JOIN roles r ON r.id = u.roleId
    WHERE u.email IN (
        'teacher@talenthub.local',
        'school@talenthub.local',
        'enterprise@talenthub.local'
    )
    ORDER BY u.email
SQL);
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $users[(string) $row['role']] = $row;
}

foreach (['teacher', 'school', 'enterprise'] as $role) {
    if (!isset($users[$role])) {
        throw new RuntimeException("Missing {$role} fixture user.");
    }
    $permissionService->require((string) $users[$role]['id'], 'notification.read_own');
    $permissionService->require((string) $users[$role]['id'], 'notification.mark_read_own');
}

$query = CollectionQuery::fromRequest(
    new Request('GET', '/api/v1/notifications.php', [], '', [], ['limit' => '25', 'offset' => '0']),
    ['createdAt'],
    ['read' => ['true', 'false']],
    25,
    100
);

$pdo->beginTransaction();
try {
    $teacherId = (string) $users['teacher']['id'];
    $schoolId = (string) $users['school']['id'];
    $notificationId = \TalentHub\Support\Uuid::v4();
    $eventKey = 'smoke:portal-notifications:' . \TalentHub\Support\Uuid::v4();
    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt)
        VALUES (:id, :userId, :eventKey, 'assessment_submitted', 'Smoke notification',
                'Transactional notification test', '/app/teacher/grading.php', UTC_TIMESTAMP(6))
    SQL);
    $insert->execute(['id' => $notificationId, 'userId' => $teacherId, 'eventKey' => $eventKey]);

    $teacherInbox = $service->inbox($teacherId, $query);
    $schoolInbox = $service->inbox($schoolId, $query);
    if (!in_array($notificationId, array_column($teacherInbox['items'], 'id'), true)) {
        throw new RuntimeException('Teacher could not read own notification.');
    }
    if (in_array($notificationId, array_column($schoolInbox['items'], 'id'), true)) {
        throw new RuntimeException('Tenant isolation failed: school received teacher notification.');
    }

    $first = $service->markRead($teacherId, $notificationId);
    $second = $service->markRead($teacherId, $notificationId);
    if (empty($first['isRead']) || empty($second['isRead'])) {
        throw new RuntimeException('markRead is not idempotent.');
    }

    try {
        $service->markRead($schoolId, $notificationId);
        throw new RuntimeException('Cross-user markRead unexpectedly succeeded.');
    } catch (\TalentHub\Http\ApiException $exception) {
        if ($exception->status !== 404) {
            throw $exception;
        }
    }
} finally {
    $pdo->rollBack();
}

echo "[OK] Portal notification repository, RBAC, isolation and idempotent mark-read checks passed.\n";