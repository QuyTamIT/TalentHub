<?php

declare(strict_types=1);

namespace TalentHub\Modules\Student\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\LearnerOnboardingRepository;

final class LearnerOnboardingService
{
    /** @var list<string> */
    private const REQUIRED_CODES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

    public function __construct(private readonly LearnerOnboardingRepository $repository)
    {
    }

    public static function normalizeCode(string $code): ?string
    {
        $code = strtolower(trim($code));
        foreach (self::REQUIRED_CODES as $required) {
            if ($code === $required || str_starts_with($code, $required . '_')) {
                return $required;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function progress(string $studentId): array
    {
        $state = $this->repository->find($studentId);
        if ($state === null) {
            return $this->readModel(false, 'not_required', []);
        }

        if ($state['status'] === 'pending') {
            return $this->readModel(true, 'pending', []);
        }
        if ($state['status'] === 'completed') {
            return $this->readModel(true, 'completed', self::REQUIRED_CODES);
        }

        return $this->readModel(true, $state['status'], $this->completedCodes($studentId));
    }

    /** @return array<string,mixed> */
    public function accept(string $studentId, string $userId, string $requestId, ?string $ip): array
    {
        $before = $this->progress($studentId);
        if ($before['required'] === true && $before['status'] === 'pending' && $this->repository->accept($studentId)) {
            $this->repository->audit(
                $studentId,
                $userId,
                'learner.onboarding_accepted',
                $requestId,
                $ip,
                [
                    'from' => 'pending',
                    'to' => 'accepted',
                    'completedCodes' => $before['completed_codes'],
                ],
            );
        }

        return $this->progress($studentId);
    }

    public function decline(string $studentId, string $userId, string $requestId, ?string $ip): void
    {
        $progress = $this->progress($studentId);
        if ($progress['required'] !== true || $progress['status'] !== 'pending') {
            return;
        }

        $this->repository->audit(
            $studentId,
            $userId,
            'learner.onboarding_declined',
            $requestId,
            $ip,
            [
                'from' => 'pending',
                'to' => 'logged_out',
                'completedCodes' => $progress['completed_codes'],
            ],
        );
    }

    /** @return array<string,mixed> */
    public function reconcile(string $studentId, string $userId, string $requestId, ?string $ip): array
    {
        $progress = $this->progress($studentId);
        if (
            $progress['required'] === true
            && $progress['status'] === 'accepted'
            && $progress['completed_count'] === count(self::REQUIRED_CODES)
            && $this->repository->complete($studentId)
        ) {
            $this->repository->audit(
                $studentId,
                $userId,
                'learner.onboarding_completed',
                $requestId,
                $ip,
                [
                    'from' => 'accepted',
                    'to' => 'completed',
                    'completedCodes' => $progress['completed_codes'],
                ],
            );
        }

        return $this->progress($studentId);
    }

    public function assertAssessmentAccessible(string $studentId, string $assessmentCode): void
    {
        $progress = $this->progress($studentId);
        if ($progress['required'] !== true || $progress['status'] === 'completed') {
            return;
        }

        if ($progress['status'] === 'pending') {
            throw new ApiException(
                409,
                'ONBOARDING_ACCEPTANCE_REQUIRED',
                'Bạn cần đồng ý hoàn thành bài đánh giá trước khi tiếp tục.',
            );
        }

        $normalized = self::normalizeCode($assessmentCode);
        if (
            $normalized !== null
            && ($normalized === $progress['next_code'] || in_array($normalized, $progress['completed_codes'], true))
        ) {
            return;
        }

        throw new ApiException(
            409,
            'ONBOARDING_SEQUENCE_REQUIRED',
            'Vui lòng hoàn thành bài đánh giá tiếp theo theo đúng thứ tự.',
        );
    }

    /** @return list<string> */
    private function completedCodes(string $studentId): array
    {
        $submitted = [];
        foreach ($this->repository->submittedCodes($studentId) as $code) {
            $normalized = self::normalizeCode($code);
            if ($normalized !== null) {
                $submitted[$normalized] = true;
            }
        }

        return array_values(array_filter(
            self::REQUIRED_CODES,
            static fn (string $code): bool => isset($submitted[$code]),
        ));
    }

    /**
     * @param list<string> $completedCodes
     * @return array<string,mixed>
     */
    private function readModel(bool $required, string $status, array $completedCodes): array
    {
        $completedCount = count($completedCodes);
        $nextCode = null;
        if ($required && $status === 'accepted') {
            foreach (self::REQUIRED_CODES as $code) {
                if (!in_array($code, $completedCodes, true)) {
                    $nextCode = $code;
                    break;
                }
            }
        }

        $items = [];
        foreach (self::REQUIRED_CODES as $code) {
            $itemState = in_array($code, $completedCodes, true) ? 'completed' : 'locked';
            if ($code === $nextCode) {
                $itemState = 'next';
            }
            $items[] = ['code' => $code, 'state' => $itemState];
        }

        return [
            'required' => $required,
            'status' => $status,
            'completed_count' => $completedCount,
            'required_count' => count(self::REQUIRED_CODES),
            'completed_codes' => $completedCodes,
            'next_code' => $nextCode,
            'next_url' => $nextCode === null ? null : '/app/learner/assessment.php?code=' . $nextCode,
            'items' => $items,
        ];
    }
}
