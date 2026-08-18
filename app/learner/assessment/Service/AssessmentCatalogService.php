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

    public function assessmentDetail(string $studentId, string $assessmentCode, ?string $confirmedBand): array
    {
        $band = $this->bands->resolve($studentId, $confirmedBand);
        $assessment = $this->repository->publishedAssessment($assessmentCode, $band);
        if ($assessment === null) {
            throw new \RuntimeException('Assessment definition was not found or is not published.');
        }
        $assessment['code'] = $assessmentCode;
        $assessment['education_band'] = $band;
        $questions = $this->repository->questionsForVersion($assessment['version_id']);
        $history = $this->repository->history($studentId, $assessmentCode);

        return [
            'assessment' => $assessment,
            'questions' => $questions,
            'history' => $history,
        ];
    }
}
