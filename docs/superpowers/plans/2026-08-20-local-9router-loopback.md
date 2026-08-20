# Local 9Router Loopback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect TalentHub locally to 9Router at `http://localhost:20128/v1/chat/completions` while preserving HTTPS-only behavior outside local/test environments and keeping the API key out of Git.

**Architecture:** Extend `RecommendationConfig` with a fail-closed URL policy: approved HTTPS hosts remain valid everywhere, while HTTP is accepted only for an allowlisted loopback host on port `20128` in `local` or `test`. Keep the existing HTTP provider and shadow rollout unchanged; configuration remains environment-driven so a future real key or HTTPS endpoint requires no code change.

**Tech Stack:** PHP 8.3, Laragon, 9Router OpenAI-compatible API, PowerShell, MySQL 8.4, Git.

## Global Constraints

- HTTP is allowed only in `APP_ENV=local|test`, on `localhost`, `127.0.0.1`, or `::1`, with explicit port `20128` and an approved hostname.
- Staging and production continue to require HTTPS.
- URL credentials are rejected for loopback HTTP.
- `TALENTHUB_AI_SHADOW=true`, `TALENTHUB_AI_SHADOW_GATE_APPROVED=false`, and `TALENTHUB_AI_VISIBLE_PERCENT=0` remain in force.
- The API key must never be added to a tracked file, commit, diagnostic, command output, or test fixture.

---

### Task 1: Fail-closed local loopback URL policy

**Files:**
- Modify: `tests/learner_ai_provider_test.php`
- Modify: `app/learner/ai/Config/RecommendationConfig.php`

**Interfaces:**
- Consumes: `RecommendationConfig::fromEnvironment(array<string,string>): RecommendationConfig`
- Produces: the same public interface with a stricter internal URL-policy helper; no consumer changes.

- [ ] **Step 1: Write the failing local-loopback test**

Add a complete local environment fixture and assertion after the existing incomplete-configuration assertion:

```php
$localEnvironment = [
    'APP_ENV' => 'local',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => '9router_gemini',
    'TALENTHUB_AI_MODEL' => 'ag/gemini-3.7-flash-high',
    'TALENTHUB_AI_API_URL' => 'http://localhost:20128/v1/chat/completions',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'localhost',
    'TALENTHUB_AI_API_KEY' => 'local-test-key',
];
$localConfig = RecommendationConfig::fromEnvironment($localEnvironment);
provider_assert($localConfig->enabled(), 'local 9Router loopback is accepted');
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_provider_test.php
```

Expected: FAIL with `AI provider URL must use an approved HTTPS host.` because HTTP loopback support does not exist yet.

- [ ] **Step 3: Add rejection coverage before implementation**

Add `provider_expect(...)` cases derived from `$localEnvironment` for:

```php
['APP_ENV' => 'production']
['TALENTHUB_AI_API_URL' => 'http://192.168.1.20:20128/v1/chat/completions', 'TALENTHUB_AI_ALLOWED_HOSTS' => '192.168.1.20']
['TALENTHUB_AI_API_URL' => 'http://localhost:8080/v1/chat/completions']
['TALENTHUB_AI_API_URL' => 'http://user:pass@localhost:20128/v1/chat/completions']
['TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1']
```

Each case must throw `InvalidArgumentException`. Add positive assertions for `APP_ENV=test` with `127.0.0.1:20128` and for existing HTTPS behavior.

- [ ] **Step 4: Implement the minimal URL policy**

Normalize allowlisted and parsed hosts by lowercasing and trimming IPv6 brackets. Replace the inline HTTPS condition with:

```php
if (!is_array($parts) || !self::isApprovedApiUrl($parts, $allowedHosts, $environment)) {
    throw new \InvalidArgumentException('AI provider URL must use an approved HTTPS host or an approved local loopback endpoint.');
}
```

Add:

```php
/** @param array<string,mixed> $parts @param list<string> $allowedHosts @param array<string,string> $environment */
private static function isApprovedApiUrl(array $parts, array $allowedHosts, array $environment): bool
{
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
    if ($host === '' || !in_array($host, $allowedHosts, true)) {
        return false;
    }
    if ($scheme === 'https') {
        return true;
    }
    $appEnv = strtolower(self::value($environment, 'APP_ENV', 'production'));
    return $scheme === 'http'
        && in_array($appEnv, ['local', 'test'], true)
        && in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        && ($parts['port'] ?? null) === 20128
        && !isset($parts['user'])
        && !isset($parts['pass']);
}
```

- [ ] **Step 5: Verify GREEN and regressions**

Run the provider test, 9Router shadow integration test, and PHP lint for both changed files. Expected: all exit `0` and report `OK`/no syntax errors.

- [ ] **Step 6: Commit the policy**

```powershell
git add -- app/learner/ai/Config/RecommendationConfig.php tests/learner_ai_provider_test.php
git commit -m "feat(ai): allow secure local 9router loopback"
```

### Task 2: Local secret configuration and live verification

**Files:**
- Modify, ignored local file: `.env`
- Verify only: `.gitignore`, `.env.example`, existing AI and integration tests

**Interfaces:**
- Consumes: the Task 1 URL policy and `HttpRecommendationProvider` Bearer authentication.
- Produces: a working local shadow connection to the selected 9Router combo without tracked secrets.

- [ ] **Step 1: Update the ignored local environment**

Keep the supplied key value only in `.env`, and set:

```dotenv
TALENTHUB_AI_ENABLED=true
TALENTHUB_AI_PROVIDER=9router_gemini
TALENTHUB_AI_MODEL=ag/gemini-3.7-flash-high
TALENTHUB_AI_API_URL=http://localhost:20128/v1/chat/completions
TALENTHUB_AI_ALLOWED_HOSTS=localhost
TALENTHUB_AI_TIMEOUT_SECONDS=10
TALENTHUB_AI_MAX_ATTEMPTS=2
TALENTHUB_AI_PER_STUDENT_LIMIT=2
TALENTHUB_AI_GLOBAL_LIMIT=20
TALENTHUB_AI_SHADOW=true
TALENTHUB_AI_SHADOW_GATE_APPROVED=false
TALENTHUB_AI_VISIBLE_PERCENT=0
```

- [ ] **Step 2: Verify configuration without printing the key**

Load `bin/bootstrap.php` and `app/learner/ai/bootstrap.php`, construct `RecommendationConfig` from `$_ENV`, and print only `diagnostics()`, `shadowEnabled()`, and `visiblePercent()`. Expected: enabled, correct provider/model, shadow `true`, visible percentage `0`.

- [ ] **Step 3: Perform a minimal live 9Router request**

Read the key from `.env` into memory, send one non-streaming POST to `http://localhost:20128/v1/chat/completions` with model `ag/gemini-3.7-flash-high`, and report only HTTP/auth/envelope status. Do not print headers, request body containing secrets, or response content.

- [ ] **Step 4: Run full verification**

Run:

- the 33-file safe PHP learner/AI suite;
- all seven JavaScript test files;
- PHP lint for all tracked PHP files;
- `php bin/migrate.php validate` on `talenthub_local`;
- `php bin/test-school-suite.php` on a disposable database whose name contains `test`, followed by cleanup;
- `git diff --check`, `git status --short`, and `git check-ignore -v .env`.

Expected: all tests and validation exit `0`; `.env` is ignored; only `.claude/` and `.qwen/` remain unrelated untracked files.

- [ ] **Step 5: Confirm secret hygiene and final commit state**

Search tracked files and the pending diff for the literal key prefix/value without printing matches. Expected count: `0`. Do not commit `.env`; commit only tracked test/production changes from Task 1.
