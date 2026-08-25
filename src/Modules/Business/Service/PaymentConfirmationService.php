<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class PaymentConfirmationService
{
    private ?NotificationService $notifications = null;

    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Atomically confirms a payment order and its associated sponsorship.
     *
     * @param string $enterpriseId The enterprise ID claiming the payment.
     * @param string $orderId The payment order ID (UUID).
     * @param array{providerReference?: string} $input
     * @param string $requestId Trace request ID.
     * @return array<string, mixed>
     */
    public function confirmPayment(string $enterpriseId, string $orderId, array $input, string $requestId): array
    {
        if (!Uuid::isValid($orderId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'orderId không đúng định dạng UUID.');
        }

        $providerReference = isset($input['providerReference']) && is_string($input['providerReference'])
            ? trim($input['providerReference'])
            : 'VNPAY_' . bin2hex(random_bytes(6));

        // Lock payment order row for update
        $lockSuffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT po.*, ps.projectId, ps.status AS sponsorshipStatus, ps.amount AS sponsorshipAmount
                 FROM payment_orders po
                 INNER JOIN project_sponsorships ps ON ps.id = po.sponsorshipId
                 WHERE po.id = :orderId AND po.enterpriseId = :enterpriseId" . $lockSuffix
            );
            $stmt->execute(['orderId' => $orderId, 'enterpriseId' => $enterpriseId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($order)) {
                $this->pdo->rollBack();
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đơn thanh toán.');
            }

            // Idempotency check: if already paid, return existing state without duplicate side-effects
            if (($order['paymentStatus'] ?? '') === 'paid') {
                $this->pdo->commit();
                return [
                    'id' => $orderId,
                    'sponsorshipId' => (string) $order['sponsorshipId'],
                    'projectId' => (string) $order['projectId'],
                    'amount' => (string) $order['amount'],
                    'currency' => (string) $order['currency'],
                    'paymentStatus' => 'paid',
                    'provider' => (string) $order['provider'],
                    'providerReference' => (string) ($order['providerReference'] ?? $providerReference),
                    'paidAt' => (string) ($order['paidAt'] ?? $this->now()),
                    'isIdempotent' => true,
                ];
            }

            if (($order['paymentStatus'] ?? '') !== 'pending') {
                $this->pdo->rollBack();
                throw new ApiException(409, 'INVALID_STATE_TRANSITION', 'Đơn thanh toán không ở trạng thái chờ thanh toán.');
            }

            $now = $this->now();
            $sponsorshipId = (string) $order['sponsorshipId'];
            $projectId = (string) $order['projectId'];

            // 1. Update payment_orders
            $updateOrder = $this->pdo->prepare(
                "UPDATE payment_orders
                 SET paymentStatus = 'paid', providerReference = :providerReference, paidAt = :paidAt, updatedAt = :updatedAt
                 WHERE id = :id AND paymentStatus = 'pending'"
            );
            $updateOrder->execute([
                'providerReference' => $providerReference,
                'paidAt' => $now,
                'updatedAt' => $now,
                'id' => $orderId,
            ]);

            if ($updateOrder->rowCount() !== 1) {
                $this->pdo->rollBack();
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái đơn thanh toán đã bị thay đổi.');
            }

            // 2. Update project_sponsorships
            $updateSpon = $this->pdo->prepare(
                "UPDATE project_sponsorships
                 SET status = 'paid', updatedAt = :updatedAt
                 WHERE id = :id AND status IN ('pending_payment', 'pledged')"
            );
            $updateSpon->execute([
                'updatedAt' => $now,
                'id' => $sponsorshipId,
            ]);

            // 3. Write Audit Log
            if ($this->tableExists('audit_logs')) {
                $audit = $this->pdo->prepare(
                    "INSERT INTO audit_logs(id, userId, action, entityType, entityId, requestId, metadata, createdAt)
                     VALUES (?, ?, 'payment.confirmed', 'payment_order', ?, ?, ?, ?)"
                );
                $audit->execute([
                    Uuid::v4(),
                    $enterpriseId,
                    $orderId,
                    $requestId,
                    json_encode([
                        'sponsorshipId' => $sponsorshipId,
                        'projectId' => $projectId,
                        'amount' => $order['amount'],
                        'providerReference' => $providerReference,
                    ], JSON_THROW_ON_ERROR),
                    $now,
                ]);
            }

            $this->pdo->commit();

            // 4. Notify School / Project stakeholders
            $this->notifyStakeholders($projectId, $enterpriseId, (string) $order['amount'], (string) $order['currency']);

            return [
                'id' => $orderId,
                'sponsorshipId' => $sponsorshipId,
                'projectId' => $projectId,
                'amount' => (string) $order['amount'],
                'currency' => (string) $order['currency'],
                'paymentStatus' => 'paid',
                'provider' => (string) $order['provider'],
                'providerReference' => $providerReference,
                'paidAt' => $now,
                'isIdempotent' => false,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function notifyStakeholders(string $projectId, string $enterpriseId, string $amount, string $currency): void
    {
        try {
            if (!$this->tableExists('projects') || !$this->tableExists('notifications')) {
                return;
            }

            // Find project and enterprise name
            $stmtProj = $this->pdo->prepare("SELECT title, schoolId, mentorTeacherId FROM projects WHERE id = ? LIMIT 1");
            $stmtProj->execute([$projectId]);
            $proj = $stmtProj->fetch(PDO::FETCH_ASSOC);
            if (!is_array($proj)) {
                return;
            }

            $stmtEnt = $this->pdo->prepare("SELECT name FROM enterprises WHERE id = ? LIMIT 1");
            $stmtEnt->execute([$enterpriseId]);
            $entName = $stmtEnt->fetchColumn() ?: 'Doanh nghiệp đối tác';

            $projectTitle = (string) ($proj['title'] ?? 'Dự án');
            $formattedAmount = number_format((float) $amount, 0, ',', '.') . ' ' . $currency;

            // Notify mentor teacher if assigned
            $mentorTeacherId = $proj['mentorTeacherId'] ?? null;
            if ($mentorTeacherId !== null && $this->tableExists('teacher_profiles')) {
                $stmtTeach = $this->pdo->prepare("SELECT userId FROM teacher_profiles WHERE id = ? LIMIT 1");
                $stmtTeach->execute([$mentorTeacherId]);
                $teacherUserId = $stmtTeach->fetchColumn();
                if (is_string($teacherUserId) && $teacherUserId !== '') {
                    $this->getNotificationService()->publish(
                        $teacherUserId,
                        'project_sponsored',
                        'Dự án nhận được tài trợ mới!',
                        "{$entName} đã tài trợ thành công {$formattedAmount} cho dự án \"{$projectTitle}\".",
                        "/app/teacher/projects/index.php?id={$projectId}",
                        "project_sponsored:{$projectId}:{$enterpriseId}"
                    );
                }
            }

            // Notify student members
            if ($this->tableExists('project_members') && $this->tableExists('student_profiles')) {
                $stmtMembers = $this->pdo->prepare(
                    "SELECT sp.userId
                     FROM project_members pm
                     INNER JOIN student_profiles sp ON sp.id = pm.studentId
                     WHERE pm.projectId = ? AND pm.status = 'active'"
                );
                $stmtMembers->execute([$projectId]);
                $studentUserIds = $stmtMembers->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($studentUserIds as $studentUserId) {
                    if (is_string($studentUserId) && $studentUserId !== '') {
                        $this->getNotificationService()->publish(
                            $studentUserId,
                            'project_sponsored',
                            'Dự án của bạn vừa nhận tài trợ!',
                            "Dự án \"{$projectTitle}\" vừa nhận được gói tài trợ {$formattedAmount} từ {$entName}.",
                            "/app/learner/talent-passport.php",
                            "project_sponsored:{$projectId}:{$enterpriseId}"
                        );
                    }
                }
            }
        } catch (Throwable) {
            // Ignore background notification exceptions to avoid failing payment transaction
        }
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__, 4) . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Service/NotificationService.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }
        if ($this->notifications === null) {
            $this->notifications = new NotificationService(new DatabaseNotificationRepository($this->pdo));
        }
        return $this->notifications;
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
