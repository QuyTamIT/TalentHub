<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationResult;

final class UnsupportedClaimDetector
{
    /** @return list<array{category:string,item_index:int}> */
    public function detect(RecommendationResult $result, RecommendationInput $input): array
    {
        $findings = [];
        foreach ($result->items() as $index => $item) {
            $text = mb_strtolower($item->title() . ' ' . $item->summary(), 'UTF-8');
            $patterns = [
                'guaranteed_outcome' => '/\b(chắc chắn|đảm bảo|guaranteed|will definitely)\b/u',
                'diagnosis' => '/\b(chẩn đoán|diagnosis|rối loạn|disorder)\b/u',
                'protected_trait' => '/\b(tôn giáo|dân tộc|religion|ethnicity|giới tính sinh học)\b/u',
            ];
            foreach ($patterns as $category => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $findings[] = ['category' => $category, 'item_index' => $index];
                    break;
                }
            }
        }
        return $findings;
    }
}
