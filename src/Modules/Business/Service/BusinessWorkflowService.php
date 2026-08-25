<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use TalentHub\Http\ApiException;
use TalentHub\Http\CollectionQuery;
use TalentHub\Modules\Business\Repository\BusinessWorkflowRepository;
use TalentHub\Modules\Business\Repository\InternshipRepository;

final class BusinessWorkflowService
{
    public function __construct(
        private readonly BusinessWorkflowRepository $repository,
        private readonly ?InternshipService $internships = null
    ) {}

    public function enterprise(string $userId): string
    {
        return $this->repository->enterpriseId($userId)
            ?? throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy doanh nghiệp hiện tại.');
    }

    public function student(string $userId): string
    {
        return $this->repository->studentId($userId)
            ?? throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
    }

    public function posts(string $userId, CollectionQuery $query): array
    {
        return $this->repository->posts($this->enterprise($userId), $query);
    }

    public function createPost(string $userId, array $input, string $requestId): array
    {
        $enterpriseId = $this->enterprise($userId);
        $id = $this->repository->insertPost($enterpriseId, $this->postData($input));
        $this->repository->audit($userId, 'internship_post.created', 'internship_post', $id, $requestId);
        return $this->repository->post($enterpriseId, $id) ?? [];
    }

    public function updatePost(string $userId, string $id, array $input, string $requestId): array
    {
        $enterpriseId = $this->enterprise($userId);
        $current = $this->repository->post($enterpriseId, $id)
            ?? throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tin thực tập.');
        $this->repository->updatePost($enterpriseId, $id, $this->postData(array_merge($current, $input)));
        $this->repository->audit($userId, 'internship_post.updated', 'internship_post', $id, $requestId);
        return $this->repository->post($enterpriseId, $id) ?? [];
    }

    public function transitionPost(string $userId, string $id, string $action, string $requestId): array
    {
        $enterpriseId = $this->enterprise($userId);
        [$from, $to] = $action === 'publish' ? ['draft', 'active'] : ['active', 'closed'];
        if (!$this->repository->transitionPost($enterpriseId, $id, $from, $to)) {
            throw new ApiException(409, 'INVALID_STATE_TRANSITION', 'Trạng thái tin thực tập không cho phép thao tác.');
        }
        $this->repository->audit($userId, "internship_post.{$action}ed", 'internship_post', $id, $requestId);
        return $this->repository->post($enterpriseId, $id) ?? [];
    }

    public function publicPosts(CollectionQuery $query): array
    {
        return $this->repository->publicPosts($query);
    }

    public function apply(string $userId, string $postId, array $input, string $requestId): array
    {
        if (isset($input['cvUrl'])) {
            $this->text($input['cvUrl'], 'cvUrl', 5, 500);
        }
        $message = isset($input['message']) ? $this->text($input['message'], 'message', 0, 500) : '';
        $application = $this->repository->apply($this->student($userId), $userId, $postId, $message, $requestId);
        $id = (string) ($application['id'] ?? '');
        $this->repository->audit($userId, 'internship_application.created', 'internship_application', $id, $requestId);
        return ['id' => $id, 'status' => (string) ($application['status'] ?? 'submitted')];
    }

    public function studentApplications(string $userId): array
    {
        return $this->repository->studentApplications($this->student($userId));
    }

    public function withdraw(string $userId, string $id, string $requestId): array
    {
        $application = $this->repository->withdraw($this->student($userId), $userId, $id, $requestId, 'Rút hồ sơ');
        $this->repository->audit($userId, 'internship_application.withdrawn', 'internship_application', $id, $requestId);
        return ['id' => $id, 'status' => (string) ($application['status'] ?? 'withdrawn')];
    }

    public function applications(string $userId, string $postId): array
    {
        return $this->repository->applications($this->enterprise($userId), $postId);
    }

    public function review(string $userId, string $id, array $input, string $requestId): array
    {
        $enterpriseId = $this->enterprise($userId);
        $expectedStatus = isset($input['expectedCurrentStatus']) && is_string($input['expectedCurrentStatus']) && trim($input['expectedCurrentStatus']) !== ''
            ? trim($input['expectedCurrentStatus'])
            : null;

        if ($expectedStatus === null) {
            $current = $this->repository->applicationStatus($enterpriseId, $id);
            if ($current === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đơn ứng tuyển.');
            }
            $expectedStatus = $current;
        }

        $canonicalPayload = [
            'expectedCurrentStatus' => $expectedStatus,
            'targetStatus' => (string) ($input['targetStatus'] ?? $input['status'] ?? ''),
            'reviewerNote' => (string) ($input['reviewerNote'] ?? $input['note'] ?? ''),
        ];

        $reviewed = $this->getInternshipService()->review($userId, $id, $canonicalPayload);
        $this->repository->audit($userId, 'internship_application.reviewed', 'internship_application', $id, $requestId);

        return ['id' => $id, 'status' => (string) ($reviewed['status'] ?? $canonicalPayload['targetStatus'])];
    }

    private function getInternshipService(): InternshipService
    {
        return $this->internships ?? new InternshipService(new InternshipRepository($this->repository->pdo()));
    }

    public function projects(CollectionQuery $query): array
    {
        return $this->repository->projects($query);
    }

    public function sponsor(string $userId, array $input, string $requestId): array
    {
        $amount = (string) ($input['amount'] ?? '');
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount) || (float) $amount <= 0) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Số tiền tài trợ không hợp lệ.');
        }
        $currency = strtoupper((string) ($input['currency'] ?? 'VND'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Currency không hợp lệ.');
        }
        $id = $this->repository->sponsor(
            $this->enterprise($userId),
            (string) ($input['projectId'] ?? ''),
            $amount,
            $currency,
            isset($input['note']) ? $this->text($input['note'], 'note', 0, 1000) : null
        );
        if ($id === '') {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Dự án không khả dụng.');
        }
        $this->repository->audit($userId, 'sponsorship.created', 'sponsorship', $id, $requestId);
        return ['id' => $id, 'status' => 'pledged'];
    }

    public function sponsorships(string $userId): array
    {
        return $this->repository->sponsorships($this->enterprise($userId));
    }

    public function cancelSponsorship(string $userId, string $id, string $requestId): array
    {
        if (!$this->repository->cancelSponsorship($this->enterprise($userId), $id)) {
            throw new ApiException(409, 'INVALID_STATE_TRANSITION', 'Không thể hủy tài trợ.');
        }
        $this->repository->audit($userId, 'sponsorship.cancelled', 'sponsorship', $id, $requestId);
        return ['id' => $id, 'status' => 'cancelled'];
    }

    public function createPayment(string $userId, array $input, string $requestId): array
    {
        $provider = $this->text($input['provider'] ?? 'manual', 'provider', 2, 100);
        $id = $this->repository->createPayment($this->enterprise($userId), (string) ($input['sponsorshipId'] ?? ''), $provider);
        if ($id === '') {
            throw new ApiException(409, 'INVALID_STATE_TRANSITION', 'Không thể tạo payment order.');
        }
        $this->repository->audit($userId, 'payment.created', 'payment_order', $id, $requestId);
        return ['id' => $id, 'status' => 'pending'];
    }

    public function confirmPayment(string $userId, string $orderId, array $input, string $requestId): array
    {
        $enterpriseId = $this->enterprise($userId);
        $confirmationService = new PaymentConfirmationService($this->repository->pdo());
        return $confirmationService->confirmPayment($enterpriseId, $orderId, $input, $requestId);
    }

    public function payments(string $userId): array
    {
        return $this->repository->payments($this->enterprise($userId));
    }

    public function analytics(string $userId, array $params = []): array
    {
        $enterpriseId = $this->enterprise($userId);
        return $this->repository->analytics($enterpriseId, $params);
    }

    public function getEnterpriseMetrics(string $enterpriseId): array
    {
        return $this->repository->analytics($enterpriseId)['summary'];
    }

    /** @return array<string,mixed> */
    private function postData(array $input): array
    {
        $deadline = (string) ($input['deadline'] ?? '');
        try {
            $date = new DateTimeImmutable($deadline);
        } catch (\Throwable) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Deadline không hợp lệ.');
        }
        $rawWorkType = trim((string) ($input['workType'] ?? $input['workMode'] ?? 'onsite'));
        $workType = $rawWorkType !== '' ? $rawWorkType : 'onsite';
        $slots = filter_var($input['slots'] ?? $input['openings'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($slots === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Slots không hợp lệ.');
        }
        return [
            'title' => $this->text($input['title'] ?? null, 'title', 2, 255),
            'field' => $this->text($input['field'] ?? 'general', 'field', 2, 150),
            'location' => $this->text($input['location'] ?? null, 'location', 2, 255),
            'workType' => $workType,
            'duration' => $this->text($input['duration'] ?? 'Not specified', 'duration', 2, 100),
            'educationLevel' => $this->text($input['educationLevel'] ?? 'Not specified', 'educationLevel', 2, 150),
            'description' => $this->text($input['description'] ?? '', 'description', 0, 10000),
            'benefits' => isset($input['benefits']) ? $this->text($input['benefits'], 'benefits', 0, 10000) : null,
            'skillsJson' => $this->jsonList($input['skillsJson'] ?? $input['skills'] ?? []),
            'requirementsJson' => isset($input['requirementsJson']) || isset($input['requirements'])
                ? $this->jsonList($input['requirementsJson'] ?? $input['requirements'])
                : null,
            'slots' => $slots,
            'deadline' => $date->format('Y-m-d H:i:s.u'),
        ];
    }

    private function jsonList(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Danh sách JSON không hợp lệ.');
            }
            $value = $decoded;
        }
        if (!is_array($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Danh sách không hợp lệ.');
        }
        return json_encode(array_values($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function text(mixed $value, string $field, int $min, int $max): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        return $value;
    }
}
