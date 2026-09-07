<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Snapshot;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\{OpportunityCandidate,CareerRoleBenchmark};

/** Persist everything that can change matching, including unselected offers. */
final class MatchingInputSnapshot
{
    public static function build(RecommendationInput $input, array $candidates, array $roles = []): RecommendationInput
    {
        $catalog = [];
        foreach ($candidates as $candidate) {
            if ($candidate instanceof OpportunityCandidate) $catalog[$candidate->catalogId()] = $candidate->providerPayload();
        }
        ksort($catalog, SORT_STRING);
        $benchmarks = [];
        foreach ($roles as $role) {
            if ($role instanceof CareerRoleBenchmark) $benchmarks[$role->code()] = [
                'title'=>$role->title(), 'category'=>$role->category(),
                'skills'=>$role->skillRequirements(), 'assessment_signals'=>$role->assessmentSignals(),
            ];
        }
        ksort($benchmarks, SORT_STRING);
        $payload = $input->payload();
        // RecommendationInput deliberately strips private-looking keys such
        // as provider_name. A digest of the complete public candidate input
        // still detects edits to these facts without weakening that filter.
        $payload['matching_context'] = ['version'=>'matching-input-1',
            'catalog_digest'=>self::digest($catalog), 'benchmark_digest'=>self::digest($benchmarks),
            'catalog'=>$catalog, 'benchmarks'=>$benchmarks];
        return new RecommendationInput($payload, $input->sourceUpdatedAt(), $input->qualityFlags(), $input->evidenceReferences());
    }

    private static function digest(array $value): string
    {
        return hash('sha256', self::encode(self::canonical($value)));
    }

    private static function canonical(array $value): array
    {
        foreach ($value as $key=>$child) {
            if (is_array($child)) $value[$key]=self::canonical($child);
        }
        if (array_is_list($value)) usort($value, static fn($a,$b):int=>strcmp(self::encode($a),self::encode($b)));
        else ksort($value, SORT_STRING);
        return $value;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function canReuse(array $run, RecommendationInput $input): bool
    {
        return ($run['status'] ?? null) === 'completed'
            && ($run['generationCurrent'] ?? false) === true
            && is_string($run['inputHash'] ?? null)
            && hash_equals($input->contentHash(), $run['inputHash']);
    }

    public static function reused(array $response): array
    {
        return array_merge($response, ['reused'=>true, 'data_changed'=>false,
            'reuse_reason'=>'inputs_unchanged',
            'message'=>'Dữ liệu không thay đổi. Đang hiển thị kết quả phân tích trước đó.']);
    }
}
