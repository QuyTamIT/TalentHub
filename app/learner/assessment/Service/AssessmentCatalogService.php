<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Service;

use TalentHub\Learner\Data\Contracts\AssessmentRepository;

final class AssessmentCatalogService
{
    public function __construct(
        private readonly AssessmentRepository $repository,
        private readonly EducationBandResolver $bands
    ) {
    }

    public function catalog(string $studentId, ?string $confirmedBand): array
    {
        $band = $this->bands->resolve($studentId, $confirmedBand);
        $assessments = $this->repository->publishedCatalog($studentId, $band);

        return [
            'student_id' => $studentId,
            'education_band' => $band,
            'assessments' => $assessments,
        ];
    }
}
