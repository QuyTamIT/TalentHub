<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

/**
 * Built-in learner AI uses server-managed scopes in the local environment.
 * These scopes describe application service access, not a user consent event.
 */
final class ConsentMode
{
    /** @return list<string> */
    public static function serviceScopes(string $appEnvironment = ''): array
    {
        return strtolower(trim($appEnvironment)) === 'local'
            ? ConsentDecision::REQUIRED_SCOPES
            : [];
    }
}
