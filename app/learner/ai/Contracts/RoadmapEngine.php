<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Contracts;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;

interface RoadmapEngine
{
    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis;
}
