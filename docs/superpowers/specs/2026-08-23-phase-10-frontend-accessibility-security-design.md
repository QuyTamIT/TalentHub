# Phase 10 Frontend, Accessibility, Error, and Security Hardening Design

## Decision

Use progressive hardening over the existing server-authoritative flows. Do not create a parallel frontend framework, new product domain, or new database table. Production-visible mutations must use canonical endpoints and wait for a validated response; explicit mock behavior remains isolated to mock mode and cannot run when the learner source is `database`.

## Scope

- Extend `assets/js/learner-api.js` with caller abort signals, bounded timeouts, safe GET retry, normalized `429` metadata, and no retry for mutations.
- Move notification/statistics requests to the shared client so error behavior is consistent.
- Replace the remaining server-data `innerHTML` activity renderer with DOM creation and `textContent`.
- Remove or disable UI-only success paths that have no server endpoint. In particular, the saved-opportunity control must not claim persistence.
- Keep application, profile, sharing, certificate, activity, assessment, check-in, notification, badge, statistics, and recommendation mutations server-confirmed in database mode.
- Complete keyboard dialog behavior, focus restoration, visible focus, live status/error announcements, explicit labels, reduced motion, and responsive behavior at 360, 768, 1024, and desktop widths.
- Preserve login rate limiting and add persistent, namespaced request limits for learner check-in, application submission, and recommendation generation without changing the schema.
- Emit `Retry-After` from safe API errors and expose it through the learner API client.

## Architecture

### Request lifecycle

`learner-api.js` remains the only browser transport contract. A request owns a timeout controller, optionally follows a caller signal, normalizes JSON/error envelopes, and retries only idempotent GET requests for network/temporary service failures. A mutation is submitted once, disables its initiating control, and changes visible state only after the response payload passes a domain-specific check.

### Rate limiting

Add a focused persistent action limiter backed by the existing `auth_rate_limits` table. Bucket hashes are namespaced by action and identity/IP while the existing checked `scope` values remain `identity` or `ip`; therefore no migration or shared-table metadata change is needed. Limits run before the domain transaction and return `ApiException(429, RATE_LIMIT_EXCEEDED)` with `Retry-After`.

### Accessibility

Dialogs retain native buttons and the existing focus trap, add Escape close where safe, return focus to a valid trigger, and announce success/error through persistent live regions. Form errors remain inline and are focusable/announced. Dynamic collections use semantic elements and DOM text APIs. CSS keeps focus unobscured, uses visible `:focus-visible`, meets 44px pointer target guidance, and honors reduced motion.

## Error policy

- `401`: normalized error and existing sign-in redirect.
- `403/404/409/422`: no optimistic state; retain user input and expose a recoverable message.
- `429`: display the safe server message and retry delay; never auto-retry a mutation.
- Network/timeout/`503`: GET may retry once with a bounded delay; mutations remain manual retry only.
- Abort/stale response: no success/error announcement from an obsolete request.

## Data and database safety

- No Phase 10 migration, seed, backfill, or primary data rewrite.
- No edits to any applied migration, `.env`, `.claude/`, `.qwen/`, or learner migrations `001`–`004`.
- Rate-limit tests use disposable/in-memory fixtures or transaction rollback and do not consume production limits.
- `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`; Phase 10 does not enable model-visible recommendations.

## Verification

- Extend `tests/learner_api_client_test.js` for abort, timeout, retry, mutation no-retry, and `429` metadata.
- Extend affected learner UI suites for no optimistic success, stale responses, shared client usage, and safe DOM rendering.
- Create `tests/learner_accessibility_render_test.php` and `tests/learner_security_contract_test.php`.
- Add focused rate-limit tests and endpoint contract tests.
- Run all learner Node suites, affected PHP/API/render/security/four-role tests, PHP lint, migration validate/status, `git diff --check`, secret/protected-scope checks, and Phase 10 readiness.

## Exit gate

Phase 10 is ready for review only when every database-mode mutation is server-confirmed, no untrusted server string is inserted with HTML parsing, keyboard/focus/live-region contracts pass, rate-limited endpoints return safe `429` responses, and all affected regressions are green. Phase 11 remains unstarted.
