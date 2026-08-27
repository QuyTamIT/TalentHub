<?php

declare(strict_types=1);

namespace TalentHub\Modules\Student\Service;

use TalentHub\Http\ApiException;

final class LearnerOnboardingGate
{
    /** @var list<string> */
    private const ACCEPTED_PAGE_BASENAMES = ['discover.php', 'assessment.php', 'assessment-result.php'];

    /** @var list<string> */
    private const ACCEPTED_API_BASENAMES = [
        'assessments.php',
        'assessment-attempts.php',
        'assessment-answers.php',
        'assessment-submit.php',
    ];

    /** @param array<string,mixed> $progress */
    public function pageDestination(array $progress, string $path): ?string
    {
        if (($progress['required'] ?? false) !== true || ($progress['status'] ?? null) === 'completed') {
            return null;
        }

        $page = basename((string) (parse_url($path, PHP_URL_PATH) ?: ''));
        if (($progress['status'] ?? null) === 'pending') {
            return $page === 'index.php' ? null : '/app/learner/index.php';
        }

        if (($progress['status'] ?? null) !== 'accepted' || in_array($page, self::ACCEPTED_PAGE_BASENAMES, true)) {
            return null;
        }

        return $this->safeNextUrl($progress['next_url'] ?? null);
    }

    /** @param array<string,mixed> $progress */
    public function assertApiAllowed(array $progress, string $endpoint): void
    {
        if (($progress['required'] ?? false) !== true || ($progress['status'] ?? null) === 'completed') {
            return;
        }

        if (
            ($progress['status'] ?? null) === 'accepted'
            && in_array(basename($endpoint), self::ACCEPTED_API_BASENAMES, true)
        ) {
            return;
        }

        throw new ApiException(
            403,
            'ONBOARDING_REQUIRED',
            'Bạn cần hoàn thành quy trình đánh giá bắt buộc trước khi sử dụng chức năng này.',
        );
    }

    private function safeNextUrl(mixed $candidate): ?string
    {
        if (!is_string($candidate) || $candidate === '' || str_starts_with($candidate, '//')) {
            return null;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        if (($parts['path'] ?? null) !== '/app/learner/assessment.php') {
            return null;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (array_keys($query) !== ['code'] || !in_array($query['code'], ['holland', 'mbti', 'disc', 'multiple_intelligence'], true)) {
            return null;
        }

        return '/app/learner/assessment.php?code=' . $query['code'];
    }
}
