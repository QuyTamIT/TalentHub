<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Matching\JobMatchResult;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

final class JobMatchPromptRegistry
{
    public const VERSION = 'learner-job-match-1.4.0';

    /**
     * @param list<OpportunityCandidate> $candidates
     * @param array<string,JobMatchResult> $matches
     * @param array<string,array<string,mixed>> $gaps
     */
    public static function create(LearnerOpportunityProfile $profile, array $candidates, array $matches, array $gaps, RecommendationContext $context): ProviderRequest
    {
        $jobs = [];
        $scores = [];
        $safeGaps = [];
        $evidence = [];
        foreach (array_slice($candidates, 0, 10) as $candidate) {
            $id = $candidate->catalogId();
            if (!isset($matches[$id], $gaps[$id])) {
                continue;
            }
            $job = $candidate->providerPayload();
            $jobs[] = $job;
            $scores[$id] = $matches[$id]->score()->breakdown();
            $safeGaps[$id] = $gaps[$id];
            foreach ($job['evidence_refs'] ?? [] as $ref) {
                if (is_string($ref)) {
                    $evidence[$ref] = true;
                }
            }
        }
        foreach ($profile->evidenceRefs() as $ref) {
            $evidence[$ref] = true;
        }
        if ($jobs === [] || $evidence === []) {
            throw new \InvalidArgumentException('Job match prompt requires grounded jobs and evidence.');
        }
        $evidenceObjects = [];
        foreach (array_keys($evidence) as $ref) {
            [$type, $id] = array_pad(explode(':', $ref, 2), 2, $ref);
            $evidenceObjects[$ref] = new RecommendationEvidence($type, $id, null, 'job-match-' . $ref, ['reference' => $ref]);
        }
        return new ProviderRequest(self::VERSION, [
            'prompt_version' => self::VERSION,
            'system' => [
                'role' => 'Bạn là chuyên gia phân tích mức độ phù hợp vị trí cho học sinh, sinh viên. Chỉ giải thích từ dữ liệu được cung cấp; không tự tạo vị trí, doanh nghiệp, điểm số, URL hoặc bằng chứng.',
            ],
            'instructions' => [
                ...\TalentHub\Learner\Ai\Grounding\GroundedProseGuard::instructions(),
                'Trả về một phân tích riêng cho mỗi catalog_id trong candidate_allow_list.',
                'analysis gồm 5 đến 7 câu tiếng Việt, tối đa 2400 ký tự: kết luận mức phù hợp hiện tại; đối chiếu điểm mạnh, kỹ năng và kinh nghiệm đã xác nhận với yêu cầu vị trí; nêu hạn chế hoặc dữ liệu còn thiếu; bước chuẩn bị trước khi ứng tuyển nếu cần. Có thể dùng hai đoạn văn ngắn.',
                'Mục tiêu là chọn vị trí phù hợp với năng lực hiện có. Khoảng thiếu là hạn chế khi ứng tuyển, không phải lý do đề cử vị trí để rèn kỹ năng yếu. Không khuyên ứng tuyển chỉ vì vị trí giúp bù điểm yếu.',
                'assessment_signals phân biệt từng loại bài test; không gộp các chiều trùng tên giữa DISC, MBTI, Holland và MI, không xem điểm tính cách là điểm thành thạo kỹ năng. confirmed_experience_tags chỉ chứng minh kinh nghiệm đã xác nhận, không tự gán điểm kỹ năng.',
                'current_score hoặc target_score null có nghĩa chưa biết, tuyệt đối không thay bằng 0. target_basis=candidate là yêu cầu của tin; role_benchmark là tiêu chí nghề tham khảo, không phải doanh nghiệp đã công bố. Giải thích sự khác biệt khi cần.',
                'evidence_ref_ids phải gồm bằng chứng của chính vị trí đang giải thích và bằng chứng hồ sơ người học được dùng để đối chiếu; không viện dẫn vị trí khác. Chỉ nói đến kỹ năng trong skill_gaps của vị trí này.',
                'Nếu total_score dưới 40, phải nói rõ vị trí hiện chưa phù hợp hoặc chưa đáp ứng, so sánh năng lực hiện tại với benchmark và giải thích cụ thể nguyên nhân có bằng chứng.',
                'Mỗi item chỉ được trả đúng catalog_id, analysis và evidence_ref_ids; không lặp lại kỹ năng hoặc khoảng cách kỹ năng vì backend sẽ gắn dữ liệu canonical.',
                'Chỉ dùng catalog_id và evidence_ref_ids có trong allow-list.',
                'Không trả về hoặc thay đổi điểm số; điểm 40/35/25 do backend quyết định.',
                'Không hứa hẹn chắc chắn được tuyển dụng.',
            ],
            'input' => [
                'matching_objective' => 'current_strengths_and_requirement_attainment',
                'student_profile' => [
                    'education_band' => $profile->educationBand(),
                    'skills' => $profile->skills(),
                    'assessment_signals' => $profile->assessmentSignals(),
                    'confirmed_experience_tags' => $profile->confirmedExperienceTags(),
                ],
                'candidate_allow_list' => $jobs,
                'deterministic_scores' => $scores,
                'skill_gaps' => $safeGaps,
                'evidence_allow_list' => array_keys($evidence),
                'context' => ['request_id' => $context->requestId(), 'idempotency_key' => $context->idempotencyKey()],
            ],
            'output_schema' => self::schema(
                array_values(array_column($jobs, 'catalog_id')),
                array_values(array_keys($evidence)),
            ),
        ], $evidenceObjects);
    }

    /** @param list<string> $catalogIds @param list<string> $evidenceRefs @return array<string,mixed> */
    private static function schema(array $catalogIds, array $evidenceRefs): array
    {
        $itemCount = count($catalogIds);
        return [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['items'],
            'properties' => ['items' => [
                'type' => 'array', 'minItems' => $itemCount, 'maxItems' => $itemCount,
                'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['catalog_id', 'analysis', 'evidence_ref_ids'],
                    'properties' => [
                        'catalog_id' => ['type' => 'string', 'enum' => $catalogIds],
                        'analysis' => ['type' => 'string', 'minLength' => 120, 'maxLength' => 2400],
                        'evidence_ref_ids' => [
                            'type' => 'array', 'minItems' => 1, 'uniqueItems' => true,
                            'items' => ['type' => 'string', 'enum' => $evidenceRefs],
                        ],
                    ],
                ],
            ]],
        ];
    }
}
