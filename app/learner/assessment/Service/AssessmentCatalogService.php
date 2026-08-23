<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Service;

use TalentHub\Learner\Data\Contracts\AssessmentRepository;

final class AssessmentCatalogService
{
    /** Automated engine results and Teacher judgement are never merged; each keeps its own label. */
    public const SOURCE_ASSESSMENT_ENGINE = 'assessment_engine';
    public const SOURCE_TEACHER_PUBLISHED_EVALUATION = 'teacher_published_evaluation';

    public function __construct(
        private readonly AssessmentRepository $repository,
        private readonly EducationBandResolver $bands
    ) {
    }

    /**
     * Read-only combined history view. Two sibling sections, two distinct source labels,
     * no submit-path behaviour and no write.
     */
    public function historyView(string $studentId): array
    {
        $history = $this->repository->completeHistory($studentId);
        $evaluations = $this->repository->publishedEvaluationsForStudent($studentId);

        return [
            'student_id' => $studentId,
            'assessment_history' => [
                'source' => self::SOURCE_ASSESSMENT_ENGINE,
                'count' => count($history),
                'items' => $history,
            ],
            'teacher_evaluations' => [
                'source' => self::SOURCE_TEACHER_PUBLISHED_EVALUATION,
                'count' => count($evaluations),
                'items' => $evaluations,
            ],
        ];
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
