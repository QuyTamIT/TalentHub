<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Support\Uuid;

final class ProfileSharingService
{
    public const ALLOWED_FIELDS = [
        'fullName',
        'headline',
        'bio',
        'location',
        'school',
        'class',
        'skills',
        'experience',
        'certificates',
        'projects',
        'email',
        'phone',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param list<string> $sharedFields
     * @return array{id:string,rawToken:string,shareUrl:string,expiresAt:string,sharedFields:list<string>}
     */
    public function createShare(string $studentId, array $sharedFields, int $expiresInDays = 30): array
    {
        $this->validateFields($sharedFields);
        if ($expiresInDays < 1 || $expiresInDays > 365) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thời hạn chia sẻ phải từ 1 đến 365 ngày.');
        }

        $id = Uuid::v4();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAtObj = $now->modify("+{$expiresInDays} days");
        $expiresAt = $expiresAtObj->format('Y-m-d H:i:s.u');
        $createdAt = $now->format('Y-m-d H:i:s.u');

        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $nowExpr = $isSqlite ? "datetime('now')" : "CURRENT_TIMESTAMP(6)";

        $this->pdo->beginTransaction();
        try {
            // Record consent
            $consentId = Uuid::v4();
            $consentStmt = $this->pdo->prepare(<<<SQL
                INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt)
                VALUES (:id, :studentId, 'profile_share', 1, 'profile-share-1.0', {$nowExpr}, NULL, {$nowExpr})
            SQL
            );
            $consentStmt->execute([
                'id' => $consentId,
                'studentId' => $studentId,
            ]);

            // Record profile share
            $shareStmt = $this->pdo->prepare(<<<SQL
                INSERT INTO student_profile_shares (id, studentId, consentId, tokenHash, sharedFieldsJson, expiresAt, revokedAt, createdAt)
                VALUES (:id, :studentId, :consentId, :tokenHash, :sharedFieldsJson, :expiresAt, NULL, {$nowExpr})
            SQL
            );
            $shareStmt->execute([
                'id' => $id,
                'studentId' => $studentId,
                'consentId' => $consentId,
                'tokenHash' => $tokenHash,
                'sharedFieldsJson' => json_encode(array_values($sharedFields), JSON_UNESCAPED_UNICODE),
                'expiresAt' => $expiresAt,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $sharePath = \function_exists('app_href')
            ? \app_href('/app/learner/shared-profile.php')
            : '/app/learner/shared-profile.php';

        return [
            'id' => $id,
            'rawToken' => $rawToken,
            'shareUrl' => $sharePath . '?token=' . $rawToken,
            'expiresAt' => $expiresAt,
            'sharedFields' => array_values($sharedFields),
        ];
    }

    /** @return list<array{id:string,sharedFields:list<string>,expiresAt:string,revokedAt:?string,isExpired:bool,isRevoked:bool,createdAt:string}> */
    public function listShares(string $studentId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id, sharedFieldsJson, expiresAt, revokedAt, createdAt
            FROM student_profile_shares
            WHERE studentId = :studentId
            ORDER BY createdAt DESC
        SQL
        );
        $stmt->execute(['studentId' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $result = [];
        foreach ($rows as $row) {
            $expiresAt = new DateTimeImmutable((string) $row['expiresAt'], new DateTimeZone('UTC'));
            $isExpired = $expiresAt <= $now;
            $isRevoked = !empty($row['revokedAt']);

            $fields = json_decode((string) $row['sharedFieldsJson'], true);
            $result[] = [
                'id' => (string) $row['id'],
                'sharedFields' => is_array($fields) ? $fields : [],
                'expiresAt' => (string) $row['expiresAt'],
                'revokedAt' => !empty($row['revokedAt']) ? (string) $row['revokedAt'] : null,
                'isExpired' => $isExpired,
                'isRevoked' => $isRevoked,
                'createdAt' => (string) $row['createdAt'],
            ];
        }

        return $result;
    }

    public function revokeShare(string $studentId, string $shareId): void
    {
        if (!Uuid::isValid($shareId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID liên kết chia sẻ không hợp lệ.');
        }

        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $nowExpr = $isSqlite ? "datetime('now')" : "CURRENT_TIMESTAMP(6)";
        $lockSuffix = $isSqlite ? '' : ' FOR UPDATE';

        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare(
                'SELECT id, consentId, revokedAt FROM student_profile_shares '
                . 'WHERE id = :id AND studentId = :studentId LIMIT 1'
                . $lockSuffix
            );
            $checkStmt->execute(['id' => $shareId, 'studentId' => $studentId]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy liên kết chia sẻ.');
            }
            if (!empty($row['revokedAt'])) {
                $this->pdo->commit();
                return;
            }

            if (isset($row['consentId']) && is_string($row['consentId']) && $row['consentId'] !== '') {
                $consent = $this->pdo->prepare(<<<SQL
                    UPDATE privacy_consents
                    SET isGranted = 0, grantedAt = NULL, revokedAt = {$nowExpr}
                    WHERE id = :id AND studentId = :studentId AND scope = 'profile_share'
                SQL
                );
                $consent->execute(['id' => $row['consentId'], 'studentId' => $studentId]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE student_profile_shares SET revokedAt = {$nowExpr} "
                . 'WHERE id = :id AND studentId = :studentId AND revokedAt IS NULL'
            );
            $stmt->execute(['id' => $shareId, 'studentId' => $studentId]);
            if ($stmt->rowCount() !== 1) {
                throw new ApiException(409, 'STALE_SHARE_STATE', 'Trạng thái liên kết chia sẻ đã thay đổi.');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string,mixed>|null */
    public function resolveShare(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || strlen($rawToken) !== 64) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);

        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $expiryCheck = $isSqlite
            ? "s.expiresAt > datetime('now')"
            : "s.expiresAt > CURRENT_TIMESTAMP(6)";

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT s.id, s.studentId, s.sharedFieldsJson, s.expiresAt, s.revokedAt, s.createdAt
            FROM student_profile_shares s
            INNER JOIN privacy_consents c
              ON c.id = s.consentId
             AND c.studentId = s.studentId
             AND c.scope = 'profile_share'
             AND c.isGranted = 1
             AND c.revokedAt IS NULL
            WHERE s.tokenHash = :tokenHash
              AND s.revokedAt IS NULL
              AND {$expiryCheck}
            LIMIT 1
        SQL
        );
        $stmt->execute(['tokenHash' => $tokenHash]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$share) {
            return null;
        }

        $sharedFields = json_decode((string) $share['sharedFieldsJson'], true);
        if (!is_array($sharedFields)) {
            return null;
        }
        $sharedFieldsLookup = array_fill_keys($sharedFields, true);

        $studentId = (string) $share['studentId'];
        $passportRepo = new DatabaseTalentPassportRepository($this->pdo);
        $sharedSections = array_values(array_intersect(['skills', 'experience', 'certificates', 'projects'], $sharedFields));
        $aggregate = $passportRepo->sharedSectionsForStudent($studentId, $sharedSections);

        // Build filtered view strictly matching shared fields
        $studentView = [];
        $rawStudent = $this->profileForShare($studentId);
        if (isset($sharedFieldsLookup['fullName'])) {
            $studentView['fullName'] = $rawStudent['full_name'] ?? $rawStudent['fullName'] ?? '';
        }
        if (isset($sharedFieldsLookup['headline'])) {
            $studentView['headline'] = $rawStudent['headline'] ?? null;
        }
        if (isset($sharedFieldsLookup['bio'])) {
            $studentView['bio'] = $rawStudent['bio'] ?? null;
        }
        if (isset($sharedFieldsLookup['location'])) {
            $studentView['location'] = $rawStudent['location'] ?? null;
        }
        if (isset($sharedFieldsLookup['school'])) {
            $studentView['school'] = $rawStudent['school_name'] ?? $rawStudent['school'] ?? '';
        }
        if (isset($sharedFieldsLookup['class'])) {
            $studentView['class'] = $rawStudent['class_name'] ?? $rawStudent['class'] ?? '';
        }
        if (isset($sharedFieldsLookup['email'])) {
            $studentView['email'] = $rawStudent['email'] ?? null;
        }
        if (isset($sharedFieldsLookup['phone'])) {
            $studentView['phone'] = $rawStudent['phone'] ?? null;
        }
        $studentView['avatarUrl'] = $rawStudent['avatarUrl'] ?? null;

        $result = [
            'student' => $studentView,
            'sharedAt' => (string) $share['createdAt'],
            'expiresAt' => (string) $share['expiresAt'],
        ];

        if (isset($sharedFieldsLookup['skills'])) {
            $result['skills'] = array_values(array_filter(
                is_array($aggregate['skills'] ?? null) ? $aggregate['skills'] : [],
                static function (array $skill): bool {
                    $verification = strtolower((string) ($skill['verification_status'] ?? $skill['verificationStatus'] ?? ''));
                    $status = strtolower((string) ($skill['skill_status'] ?? $skill['skillStatus'] ?? 'active'));
                    return $verification === 'verified' && $status === 'active';
                },
            ));
        }
        if (isset($sharedFieldsLookup['experience'])) {
            $result['experience'] = $aggregate['experience'] ?? [];
        }
        if (isset($sharedFieldsLookup['certificates'])) {
            $result['certificates'] = array_values(array_filter(
                is_array($aggregate['certificates'] ?? null) ? $aggregate['certificates'] : [],
                static function (array $cert): bool {
                    $status = strtolower((string) ($cert['verification_status'] ?? $cert['verificationStatus'] ?? 'verified'));
                    return $status === 'verified';
                },
            ));
        }
        if (isset($sharedFieldsLookup['projects'])) {
            $result['projects'] = $aggregate['projects'] ?? [];
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private function profileForShare(string $studentId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT
              u.fullName,
              u.email,
              sp.phone,
              spd.location,
              spd.bio,
              spd.headline,
              spd.avatarUrl,
              s.name AS school,
              c.name AS class
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            WHERE sp.id = :studentId
            LIMIT 1
        SQL
        );
        $statement->execute(['studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên được chia sẻ.');
        }
        return $row;
    }

    /** @param list<string> $fields */
    private function validateFields(array $fields): void
    {
        if ($fields === []) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn ít nhất một thông tin cần chia sẻ.');
        }
        foreach ($fields as $field) {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', "Trường dữ liệu chia sẻ không hợp lệ: {$field}");
            }
        }
    }
}
