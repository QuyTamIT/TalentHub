<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Snapshot;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\SkillSource;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

final class RecommendationSnapshotBuilder
{
    private readonly AiSourceRegistry $registry;

    public function __construct(
        AiSourceRegistry|StudentProfileSource $registryOrProfile,
        ?SkillSource $skillSource = null,
        ?AssessmentSource $assessmentSource = null,
        ?ActivityExperienceSource $activityExperienceSource = null,
        ?PublishedEvaluationSource $publishedEvaluationSource = null,
        ?OpportunitySource $opportunitySource = null,
    ) {
        if ($registryOrProfile instanceof AiSourceRegistry) {
            $this->registry = $registryOrProfile;
            return;
        }
        if ($skillSource === null || $assessmentSource === null || $activityExperienceSource === null
            || $publishedEvaluationSource === null || $opportunitySource === null) {
            throw new \InvalidArgumentException('Legacy snapshot sources are incomplete.');
        }
        $this->registry = AiSourceRegistry::fromLegacySources([
            $registryOrProfile,
            $skillSource,
            $assessmentSource,
            $activityExperienceSource,
            $publishedEvaluationSource,
            $opportunitySource,
        ]);
    }

    /** @param list<string> $allowedScopes */
    public function build(string $studentId, array $allowedScopes): RecommendationInput
    {
        return $this->registry->buildInput($studentId, $allowedScopes);
    }

    /** @param list<string> $allowedScopes */
    public function buildForRoadmap(string $studentId, array $allowedScopes): RecommendationInput
    {
        return $this->registry->buildInput($studentId, $allowedScopes, true);
    }
}
