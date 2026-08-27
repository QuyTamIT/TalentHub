<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;
interface AiDataOutboxRepository { public function append(AiDataOutbox $event,?string $tenantId=null):void; public function pending(int $limit=100):array; public function delivered(string $eventId):void; }
