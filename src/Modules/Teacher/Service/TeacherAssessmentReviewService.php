<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use JsonException;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherAssessmentReviewRepository;
use TalentHub\Support\Uuid;

/**
 * Orchestrates teacher review of the 4 aptitude-test results (AI graded) scoped to the
 * teacher's school, producing the official TEACHER_VERIFIED score consumed by School analytics.
 */
final class TeacherAssessmentReviewService
{
    public function __construct(private readonly TeacherAssessmentReviewRepository $repository)
    {
    }

    /** @return array<string,mixed> */
    public function pageData(string $userId, string $search = ''): array
    {
        $teacher = $this->requireTeacher($userId);
        $search = $this->search($search);
        $results = $this->repository->submittedResults((string) $teacher['schoolId'], $search);

        foreach ($results as &$result) {
            $result['dimensionScores'] = $this->decodeScores((string) ($result['dimensionScoresJson'] ?? ''));
            $result['aiFeedback'] = $this->decodeAiSummary((string) ($result['aiSummary'] ?? ''));
            $result['teacherOverrides'] = $this->decodeScores((string) ($result['teacherScoreOverrideJson'] ?? ''));
            unset($result['dimensionScoresJson'], $result['aiSummary'], $result['teacherScoreOverrideJson']);
        }
        unset($result);

        return [
            'teacher' => $teacher,
            'results' => $results,
            'search' => $search,
            'counts' => [
                'total' => count($results),
                'ai_graded' => count(array_filter($results, static fn (array $r): bool => $r['gradingStatus'] === 'ai_graded')),
                'teacher_verified' => count(array_filter($results, static fn (array $r): bool => $r['gradingStatus'] === 'teacher_verified')),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function detail(string $userId, string $resultId): array
    {
        if (!Uuid::isValid($resultId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã kết quả bài làm không hợp lệ.');
        }
        $teacher = $this->requireTeacher($userId);
        $result = $this->repository->submittedResultDetail((string) $teacher['schoolId'], $resultId);
        if ($result === null) {
            throw new ApiException(404, 'NOT_FOUND', 'Không tìm thấy kết quả bài làm trong trường của bạn.');
        }
        $result['dimensionScores'] = $this->decodeScores((string) ($result['dimensionScoresJson'] ?? ''));
        $result['aiFeedback'] = $this->decodeAiSummary((string) ($result['aiSummary'] ?? ''));
        $result['teacherOverrides'] = $this->decodeScores((string) ($result['teacherScoreOverrideJson'] ?? ''));
        unset($result['dimensionScoresJson'], $result['aiSummary'], $result['teacherScoreOverrideJson']);

        return $result;
    }

    /**
     * @param array<string,int|float> $overrides
     */
    public function verify(string $userId, string $resultId, array $overrides, ?string $summary, ?string $comment): void
    {
        $teacher = $this->requireTeacher($userId);
        $this->repository->verify(
            (string) $teacher['schoolId'],
            $resultId,
            $overrides,
            $this->nullableText($summary, 4000, 'Nhận xét tổng'),
            $this->nullableText($comment, 4000, 'Ghi chú giáo viên'),
            $userId
        );
    }

    /** @return array<string,mixed> */
    private function requireTeacher(string $userId): array
    {
        $teacher = $this->repository->teacherProfileByUserId($userId);
        if ($teacher === null) {
            throw new ApiException(403, 'FORBIDDEN', 'Hồ sơ giáo viên không tồn tại cho tài khoản này.');
        }

        return $teacher;
    }

    /** @return array<string,int|float> */
    private function decodeScores(string $json): array
    {
        if ($json === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array{summary?:string, feedback?:array<string,string>} */
    private function decodeAiSummary(string $json): array
    {
        if ($json === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableText(mixed $value, int $max, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', $label . ' không hợp lệ.');
        }
        $text = trim($value);
        if (mb_strlen($text) > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', $label . ' không được vượt quá ' . $max . ' ký tự.');
        }

        return $text === '' ? null : $text;
    }

    private function search(string $value): string
    {
        return mb_substr(trim($value), 0, 100);
    }
}