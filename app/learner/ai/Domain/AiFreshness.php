<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Domain;

final class AiFreshness
{
    public const READY_MODEL='ready_model'; public const STALE_MODEL='stale_model'; public const PENDING='pending'; public const AI_UNAVAILABLE='ai_unavailable'; public const READY_RULE='ready_rule';
    public function __construct(public readonly string $status, public readonly ?string $staleSince=null, public readonly ?string $lastRefreshError=null, public readonly ?string $nextRetryAt=null, public readonly ?string $modelVersion=null, public readonly ?string $snapshotHash=null) { if (!in_array($status,[self::READY_MODEL,self::STALE_MODEL,self::PENDING,self::AI_UNAVAILABLE,self::READY_RULE],true)) throw new \InvalidArgumentException('Invalid AI freshness status.'); if ($status===self::STALE_MODEL && $staleSince===null) throw new \InvalidArgumentException('Stale model requires stale_since.'); }
    public function isUsable(): bool { return in_array($this->status,[self::READY_MODEL,self::STALE_MODEL,self::READY_RULE],true); }
}
