<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

use RuntimeException;

final class AiRefreshResultPolicy
{
    /** @param array<string,mixed> $result */
    public static function assertSuccessful(array $result): void
    {
        if (!in_array($result['state'] ?? null, ['ready_model', 'ready_rule'], true)) {
            throw new RuntimeException('capability_refresh_unavailable');
        }
    }
}
