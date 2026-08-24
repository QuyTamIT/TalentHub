<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

final class RoadmapPromptRegistry
{
    public const VERSION = 'learner-roadmap-prompt-1.0.0';

    private const SAFE_FIELDS = [
        'assessment' => ['test_type', 'result_code', 'dimension_scores', 'submitted_at'],
        'skill' => ['code', 'category', 'level_score', 'source_type', 'verification_status', 'verified_at', 'source_updated_at'],
        'activity_experience' => ['activity_category', 'hours', 'confirmed_at'],
        'evaluation' => ['overall_score', 'presentation_score', 'published_at'],
        'opportunity' => ['title', 'location', 'deadline_at', 'category', 'opportunity_type'],
    ];

    public function create(RecommendationInput $input, RecommendationContext $context): ProviderRequest
    {
        $evidence = [];
        $byReference = [];
        $allowedActivityIds = [];
        foreach ($input->evidenceReferences() as $index => $reference) {
            $referenceId = sprintf('evidence-%03d', $index + 1);
            $sourceType = (string) ($reference['source_type'] ?? '');
            $sourceId = (string) ($reference['source_id'] ?? '');
            $safeValue = $this->safeRecord(
                is_array($reference['safe_value'] ?? null) ? $reference['safe_value'] : [],
                self::SAFE_FIELDS[$sourceType] ?? [],
            );
            if ($sourceType === 'opportunity' && ($safeValue['opportunity_type'] ?? null) === 'activity' && $this->isUuid($sourceId)) {
                $safeValue['activity_source_id'] = $sourceId;
                $allowedActivityIds[] = $sourceId;
            }
            $record = new RecommendationEvidence(
                $sourceType,
                $sourceId,
                is_string($reference['observed_at'] ?? null) ? $reference['observed_at'] : null,
                'provider_source',
                $safeValue,
            );
            $byReference[$referenceId] = $record;
            $evidence[] = [
                'reference_id' => $referenceId,
                'source_type' => $record->sourceType(),
                'observed_at' => $record->observedAt(),
                'safe_value' => $record->safeValue(),
            ];
        }
        sort($allowedActivityIds, SORT_STRING);

        return new ProviderRequest(self::VERSION, [
            'prompt_version' => self::VERSION,
            'contract_version' => RoadmapAnalysis::CONTRACT_VERSION,
            'instructions' => [
                'Trả về duy nhất một JSON object hợp lệ theo learner-roadmap-1.0.0.',
                'Viết toàn bộ nội dung dành cho học viên bằng tiếng Việt tự nhiên.',
                'Tạo đúng ba giai đoạn 0–30, 31–60 và 61–90 ngày; mỗi giai đoạn có từ 3 đến 5 task cụ thể.',
                'Không nhắc lại mã MBTI, điểm Holland, biểu đồ DISC hoặc điểm Multiple Intelligence.',
                'Mỗi insight, phase và task phải trích dẫn evidence_ref_ids được cung cấp.',
                'Không chẩn đoán, không khẳng định chắc chắn nghề nghiệp, tuyển sinh hoặc việc làm.',
                'Chỉ dùng activity_source_id có trong allowed_activity_ids; nếu danh sách rỗng thì chỉ tạo self_task.',
                'Không đưa tên, thông tin liên hệ, mã học viên, mã nguồn dữ liệu hoặc nội dung ngoài JSON vào kết quả.',
            ],
            'allowed_scopes' => $this->allowedScopes($input, $context),
            'allowed_activity_ids' => $allowedActivityIds,
            'input' => $this->safeInput($input->payload()),
            'evidence' => $evidence,
        ], $byReference);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function safeInput(array $payload): array
    {
        $profile = is_array($payload['profile'] ?? null)
            ? $this->safeRecord($payload['profile'], ['study_status'])
            : [];

        return [
            'profile' => $profile,
            'assessments' => $this->safeRecords($payload['assessments'] ?? [], self::SAFE_FIELDS['assessment']),
            'skills' => $this->safeRecords($payload['skills'] ?? [], self::SAFE_FIELDS['skill']),
            'activities' => $this->safeRecords($payload['activities'] ?? [], self::SAFE_FIELDS['activity_experience']),
            'evaluations' => $this->safeRecords($payload['evaluations'] ?? [], self::SAFE_FIELDS['evaluation']),
            'opportunities' => $this->safeRecords($payload['opportunities'] ?? [], self::SAFE_FIELDS['opportunity']),
        ];
    }

    /** @param mixed $records @param list<string> $allowedFields @return list<array<string,mixed>> */
    private function safeRecords(mixed $records, array $allowedFields): array
    {
        if (!is_array($records)) {
            return [];
        }
        $safe = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $safe[] = $this->safeRecord($record, $allowedFields);
            }
        }
        return $safe;
    }

    /** @param array<string,mixed> $record @param list<string> $allowedFields @return array<string,mixed> */
    private function safeRecord(array $record, array $allowedFields): array
    {
        $safe = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $record)) {
                $safe[$field] = $record[$field];
            }
        }
        return $safe;
    }

    /** @return list<string> */
    private function allowedScopes(RecommendationInput $input, RecommendationContext $context): array
    {
        $inputScopes = $input->qualityFlags()['allowed_scopes'] ?? [];
        if (!is_array($inputScopes)) {
            return [];
        }
        $allowed = array_values(array_intersect($context->allowedScopes(), $inputScopes));
        sort($allowed, SORT_STRING);
        return $allowed;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
