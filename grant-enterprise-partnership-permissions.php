<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$key = (string) ($_GET['key'] ?? '');
if ($key !== 'grant-partnership-20260922') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN'], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

try {
    $pdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
    $codes = ['partnership.read_own_business', 'partnership.create_own_business'];
    $granted = [];

    foreach ($codes as $code) {
        $exists = $pdo->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
        $exists->execute([$code]);
        $permissionId = $exists->fetchColumn();
        if ($permissionId === false) {
            $permissionId = Uuid::v4();
            $insert = $pdo->prepare('INSERT INTO permissions (id, code, description) VALUES (?, ?, ?)');
            $insert->execute([$permissionId, $code, 'Enterprise partnership permission: ' . $code]);
        }

        $grant = $pdo->prepare(
            "INSERT IGNORE INTO role_permissions (roleId, permissionId)
             SELECT r.id, ? FROM roles r WHERE r.code IN ('enterprise', 'business')"
        );
        $grant->execute([$permissionId]);
        $granted[$code] = [
            'permissionId' => (string) $permissionId,
            'rows' => $grant->rowCount(),
        ];
    }

    $check = $pdo->query(
        "SELECT r.code AS role, p.code AS permission
         FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.roleId
         INNER JOIN permissions p ON p.id = rp.permissionId
         WHERE p.code IN ('partnership.read_own_business', 'partnership.create_own_business')
           AND r.code IN ('enterprise', 'business')
         ORDER BY r.code, p.code"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'granted' => $granted,
        'mappings' => $check,
        'next' => 'Mo lai /app/enterprise/partnerships roi xoa file grant-enterprise-partnership-permissions.php',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
