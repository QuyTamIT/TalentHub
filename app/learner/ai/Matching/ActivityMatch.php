<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Matching;

final class ActivityMatch
{
    public function __construct(private readonly ActivityCandidate $candidate, private readonly int $score, private readonly array $reasons, private readonly array $develop) {}
    public function toArray(): array
    {
        return ['activity_id'=>$this->candidate->id, 'title'=>$this->candidate->title, 'score'=>$this->score,
            'why_fit'=>implode(' ', $this->reasons), 'fit_reasons'=>$this->reasons, 'skills_to_develop'=>$this->develop,
            'url'=>'activity-detail.php?id='.rawurlencode($this->candidate->id)];
    }
}
