<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Matching;

final class ActivityCandidate
{
    public function __construct(public readonly string $id, public readonly string $title, public readonly array $skills, public readonly string $startsAt) {}
    public function toArray(): array { return ['activity_id'=>$this->id, 'title'=>$this->title, 'skills'=>$this->skills, 'starts_at'=>$this->startsAt]; }
}
