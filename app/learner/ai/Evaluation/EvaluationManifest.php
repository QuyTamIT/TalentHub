<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationManifest
{
    /** @var list<EvaluationSubject> */
    private array $subjects;
    private string $canonicalJson;

    /** @param list<EvaluationSubject> $subjects */
    private function __construct(private readonly string $version, private readonly string $approvalReference, array $subjects, private readonly int $minimumPerBand)
    {
        if ($version === '' || $approvalReference === '' || $minimumPerBand < 1) throw new \InvalidArgumentException('Manifest metadata is invalid.');
        $seen = [];
        foreach ($subjects as $subject) {
            if (!$subject instanceof EvaluationSubject || isset($seen[$subject->subjectRef()])) throw new \InvalidArgumentException('Manifest subjects must be unique.');
            $seen[$subject->subjectRef()] = true;
        }
        usort($subjects, static fn(EvaluationSubject $a, EvaluationSubject $b): int => $a->subjectRef() <=> $b->subjectRef());
        $this->subjects = array_values($subjects);
        $this->canonicalJson = json_encode([
            'version' => $version, 'approval_reference' => $approvalReference,
            'minimum_per_band' => $minimumPerBand,
            'subjects' => array_map(static fn(EvaluationSubject $s): array => $s->publicRow(), $this->subjects),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<EvaluationSubject> $subjects */
    public static function create(string $version, string $approvalReference, array $subjects, int $minimumPerBand): self
    { return new self($version, $approvalReference, $subjects, $minimumPerBand); }
    public function publicJson(): string { return $this->canonicalJson; }
    public function sha256(): string { return hash('sha256', $this->canonicalJson); }
    /** @return list<EvaluationSubject> */ public function subjects(): array { return $this->subjects; }
    /** @return array{high:string,college:string} */
    public function sampleStatus(): array
    {
        $counts = ['high' => 0, 'college' => 0];
        foreach ($this->subjects as $subject) $counts[$subject->educationBand()]++;
        return [
            'high' => $counts['high'] < $this->minimumPerBand ? 'insufficient_sample' : 'sufficient',
            'college' => $counts['college'] < $this->minimumPerBand ? 'insufficient_sample' : 'sufficient',
        ];
    }
}
