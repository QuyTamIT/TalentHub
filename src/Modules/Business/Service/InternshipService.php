<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Support\Uuid;

final class InternshipService
{
    private const POST_FIELDS = [
        'title', 'field', 'location', 'workType', 'duration', 'educationLevel',
        'description', 'benefits', 'skills', 'requirements', 'slots', 'deadline',
        'audience', 'targetSchoolIds'
    ];

    public function __construct(private readonly InternshipRepository $repository) {}

    public function listPosts(string $userId): array { return $this->repository->posts($this->repository->enterpriseIdForUser($userId)); }
    public function post(string $userId, string $postId): array { return $this->repository->post($this->repository->enterpriseIdForUser($userId), $this->uuid($postId, 'postId')); }
    public function listApplications(string $userId): array { return $this->repository->applications($this->repository->enterpriseIdForUser($userId)); }
    public function application(string $userId, string $applicationId): array { return $this->repository->application($this->repository->enterpriseIdForUser($userId), $this->uuid($applicationId, 'applicationId')); }

    public function createPost(string $userId, array $input): array
    {
        $this->assertAllowed($input, self::POST_FIELDS);
        return $this->repository->createPost($this->repository->enterpriseIdForUser($userId), $this->postFields($input, true));
    }

    public function updatePost(string $userId, string $postId, array $input): array
    {
        $this->assertAllowed($input, self::POST_FIELDS);
        return $this->repository->updatePost($this->repository->enterpriseIdForUser($userId), $this->uuid($postId, 'postId'), $this->postFields($input, false));
    }

    public function publish(string $userId, string $postId, string $expectedStatus): array
    {
        return $this->repository->transitionPost($this->repository->enterpriseIdForUser($userId), $this->uuid($postId, 'postId'), $expectedStatus, 'active');
    }

    public function close(string $userId, string $postId, string $expectedStatus): array
    {
        return $this->repository->transitionPost($this->repository->enterpriseIdForUser($userId), $this->uuid($postId, 'postId'), $expectedStatus, 'closed');
    }

    public function review(string $userId, string $applicationId, array $input): array
    {
        $this->assertAllowed($input, ['expectedCurrentStatus', 'targetStatus', 'status', 'reviewerNote', 'note']);
        $expected = trim((string) ($input['expectedCurrentStatus'] ?? ''));
        $target = trim((string) ($input['targetStatus'] ?? $input['status'] ?? ''));
        $note = trim((string) ($input['reviewerNote'] ?? $input['note'] ?? ''));
        if ($expected === '' || $target === '' || mb_strlen($note) > 2000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu duyệt hồ sơ không hợp lệ.');
        }
        return $this->repository->review($this->repository->enterpriseIdForUser($userId), $userId, $this->uuid($applicationId, 'applicationId'), $expected, $target, $note);
    }

    private function postFields(array $input, bool $requireAll): array
    {
        $result = [];
        $map = ['title', 'field', 'location', 'workType', 'duration', 'educationLevel', 'description', 'benefits', 'slots', 'deadline'];
        foreach ($map as $field) {
            if (array_key_exists($field, $input)) {
                $result[$field] = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
            } elseif ($requireAll && !in_array($field, ['benefits', 'requirements'], true)) {
                if ($field === 'workType') {
                    $result[$field] = 'Full-time / Hybrid';
                } elseif ($field === 'duration') {
                    $result[$field] = '3 tháng';
                } elseif ($field === 'educationLevel') {
                    $result[$field] = 'Đại học / Cao đẳng';
                } else {
                    throw new ApiException(422, 'VALIDATION_FAILED', "Thiếu field {$field}.");
                }
            }
        }

        if (array_key_exists('audience', $input)) {
            $audience = is_string($input['audience']) ? trim($input['audience']) : '';
            if (!in_array($audience, ['public', 'partner_schools'], true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'audience không hợp lệ.');
            }
            $result['audience'] = $audience;
        } elseif ($requireAll) {
            $result['audience'] = 'public';
        }

        if (array_key_exists('targetSchoolIds', $input)) {
            if (!is_array($input['targetSchoolIds'])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'targetSchoolIds phải là mảng.');
            }
            $schoolIds = [];
            foreach ($input['targetSchoolIds'] as $schoolId) {
                if (!is_string($schoolId) || !Uuid::isValid(trim($schoolId))) {
                    throw new ApiException(422, 'VALIDATION_FAILED', 'targetSchoolIds chứa mã trường không hợp lệ.');
                }
                $schoolIds[] = trim($schoolId);
            }
            $result['targetSchoolIds'] = array_values(array_unique($schoolIds));
        }

        foreach (['skills' => 'skillsJson', 'requirements' => 'requirementsJson'] as $inputField => $databaseField) {
            if (array_key_exists($inputField, $input)) {
                if (!is_array($input[$inputField])) { throw new ApiException(422, 'VALIDATION_FAILED', "{$inputField} phải là mảng."); }
                $values = [];
                foreach ($input[$inputField] as $value) {
                    if (!is_string($value) || trim($value) === '') { throw new ApiException(422, 'VALIDATION_FAILED', "{$inputField} chỉ chứa chuỗi không rỗng."); }
                    $values[] = trim($value);
                }
                $result[$databaseField] = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } elseif ($requireAll && $inputField === 'skills') { throw new ApiException(422, 'VALIDATION_FAILED', 'Thiếu field skills.'); }
        }
        if (isset($result['slots'])) { $result['slots'] = filter_var($result['slots'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0; }
        foreach (['title', 'field', 'location', 'workType', 'duration', 'educationLevel', 'description', 'deadline'] as $required) {
            if (array_key_exists($required, $result) && $result[$required] === '') { throw new ApiException(422, 'VALIDATION_FAILED', "{$required} không được để trống."); }
        }
        if (isset($result['slots']) && $result['slots'] < 1) { throw new ApiException(422, 'VALIDATION_FAILED', 'slots phải lớn hơn 0.'); }
        if (isset($result['deadline'])) {
            try {
                $deadline = new DateTimeImmutable((string) $result['deadline'], new DateTimeZone('UTC'));
            } catch (\Throwable) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'deadline không hợp lệ.');
            }
            $result['deadline'] = $deadline->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        }
        if ($requireAll) {
            $result['benefits'] ??= null;
            $result['requirementsJson'] ??= null;
        }
        return $result;
    }

    private function assertAllowed(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) { throw new ApiException(422, 'VALIDATION_FAILED', 'Request chứa field không được phép.'); }
        }
    }

    private function uuid(string $value, string $field): string
    {
        $value = strtolower(trim($value));
        if (!Uuid::isValid($value)) { throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ."); }
        return $value;
    }
}
