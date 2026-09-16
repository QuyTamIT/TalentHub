<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$partnershipId = trim((string) ($argv[1] ?? ''));
if (!Uuid::isValid($partnershipId)) {
    fwrite(STDERR, "Usage: php bin/repair-school-partnership.php <partnership-id>\n");
    exit(1);
}

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->beginTransaction();
try {
    // Repair only an explicitly selected pending record created by its school.
    $statement = $pdo->prepare(<<<'SQL'
        SELECT p.id, p.schoolId, p.enterpriseId, p.requestedByUserId
        FROM school_enterprise_partnerships p
        INNER JOIN schools s ON s.id = p.schoolId AND s.status = 'active'
        INNER JOIN enterprises e ON e.id = p.enterpriseId
            AND e.status = 'active' AND e.verificationStatus IN ('verified', 'approved')
        INNER JOIN users u ON u.id = p.requestedByUserId
        INNER JOIN roles r ON r.id = u.roleId AND r.code = 'school'
        WHERE p.id = :id AND p.status = 'pending'
          AND EXISTS (SELECT 1 FROM school_members sm
                      WHERE sm.schoolId = p.schoolId AND sm.userId = p.requestedByUserId)
        FOR UPDATE
    SQL);
    $statement->execute(['id' => $partnershipId]);
    $partnership = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($partnership)) {
        $pdo->rollBack();
        fwrite(STDOUT, "No eligible pending school partnership; no data changed.\n");
        exit;
    }

    $update = $pdo->prepare(<<<'SQL'
        UPDATE school_enterprise_partnerships
        SET status = 'approved', reviewedByUserId = :requesterId,
            reviewedAt = UTC_TIMESTAMP(6), updatedAt = UTC_TIMESTAMP(6)
        WHERE id = :id AND status = 'pending'
    SQL);
    $update->execute(['requesterId' => $partnership['requestedByUserId'], 'id' => $partnershipId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Partnership changed concurrently.');
    }
    $audit = $pdo->prepare(<<<'SQL'
        INSERT INTO audit_logs (id, userId, action, entityType, entityId, metadata)
        VALUES (:id, :userId, 'SCHOOL_PARTNERSHIP_APPROVED', 'school_enterprise_partnership', :entityId, :metadata)
    SQL);
    $audit->execute([
        'id' => Uuid::v4(),
        'userId' => $partnership['requestedByUserId'],
        'entityId' => $partnershipId,
        'metadata' => json_encode([
            'schoolId' => $partnership['schoolId'],
            'enterpriseId' => $partnership['enterpriseId'],
            'fromStatus' => 'pending', 'toStatus' => 'approved',
            'reason' => 'Repair legacy school-initiated partnership; school consent recorded by original request.',
            'source' => 'bin/repair-school-partnership.php',
        ], JSON_THROW_ON_ERROR),
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Updated partnership {$partnershipId}: pending -> approved. Original ID and requester preserved.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
