<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

final class AiDataOutbox
{
    public function __construct(public readonly string $eventId, public readonly string $aggregateType, public readonly string $aggregateId, public readonly int $aggregateVersion, public readonly array $studentIds, public readonly string $eventType, public readonly string $payloadHash) {}
    public static function create(string $aggregateType,string $aggregateId,int $aggregateVersion,array $studentIds,string $eventType,array $payload): self { return new self(bin2hex(random_bytes(16)),$aggregateType,$aggregateId,$aggregateVersion,array_values($studentIds),$eventType,hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))); }
}
