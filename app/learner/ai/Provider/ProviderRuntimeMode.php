<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

/** Runtime policy for direct provider attempts. */
final class ProviderRuntimeMode
{
    public static function alwaysAttempt(string $appEnvironment): bool
    {
        return strtolower(trim($appEnvironment)) === 'local';
    }
}
