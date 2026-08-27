# Learner AI Phase 8 operations runbook

This runbook is executable without putting a credential, learner identifier, prompt, or provider response in the repository or logs. The on-call owner is the AI Platform on-call; Security/Privacy owns consent incidents; Database Operations owns migrations and rollback; Product Owner approves rollout stages.

## Safe telemetry and alert thresholds

Emit `ai-observability-v1` events through `AiMetricsCollector`. The in-process collector is intentionally bounded; the deployment exporter must persist only aggregate metrics and bounded error categories for 30 days in the approved telemetry backend. Never emit raw payloads. Page the AI Platform on-call when queue depth is above 100 or oldest age above 300 seconds for 10 minutes, stale ratio exceeds 5% for 15 minutes, provider error rate exceeds 10% for 10 minutes, p95 latency exceeds 2,000 ms, quota remaining is zero, or the circuit is `open`. Review recommendation click/feedback and token cost weekly; these are product analytics, not learner profiling.

## Quota exhausted / 429

1. Confirm `provider_error=quota_exhausted` or `rate_limited` in aggregate telemetry and pause the pilot (`TALENTHUB_AI_PILOT_PAUSED=true`) through the approved deployment mechanism.
2. Keep learner-visible output on the last-known-good model or transparent rule/pending state; do not retry in a tight loop.
3. Verify quota in the provider console with an authorized operator, request an increase or wait for reset, then run the synthetic outage and validator checks.
4. Record the approval reference and resume only through the staged rollout gate.

## Expired/invalid key

1. Pause the pilot and verify `invalid_credentials` without printing response bodies or headers.
2. Rotate the secret in the deployment secret manager (never in `.env`, source, or this runbook), then restart the worker and application using the normal service manager:

```powershell
# The value is entered into the secret manager prompt; it is never echoed or committed.
& .\bin\rotate-ai-key.ps1 -SecretName 'TALENTHUB_AI_API_KEY'
& $php bin\learner-migrate.php status
```

3. Execute a local synthetic provider health check, confirm no key appears in structured events, and leave visibility at its current approved stage until Product Owner sign-off.

## Google/provider outage

Pause the pilot, verify the circuit transitions `closed -> open`, and drain/retry through the bounded queue worker. Serve the last-known-good result with `stale_model` and `stale_since`, or `pending`/`ai_unavailable` when none exists. Resume only after provider health is stable for the documented error budget and a rollback drill passes.

## Queue backlog

Page AI Platform when either queue threshold is exceeded. Inspect depth/oldest age, stop duplicate workers, and process dead-letter items after validating idempotency keys. Do not delete jobs as a first response. If backlog cannot be reduced within the freshness SLA, pause visibility and serve a transparent stale/pending state.

## Bad model output

Stop visible model output, preserve the last-known-good version, and classify the validator failure (`malformed_output`, `unsafe_output`, or `unsupported_claim`). Re-run the deterministic safety suite and rollback to the prior model/version. Do not expose raw output while investigating.

## Migration rollback

Database Operations records the migration version, backup location/hash, and operator. Run status and validation first, then use the reviewed rollback command only on the disposable rehearsal or an approved production change window:

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php bin\learner-migrate.php status
& $php bin\learner-migrate.php validate
# Learner migrations are forward-only. Rollback is an approved backup/restore
# operation; the command fails closed rather than pretending to undo schema.
& $php bin\learner-migrate.php rollback
& $php bin\learner-migrate.php status
```

Verify migration metadata, representative reads, queue state, and learner-safe response contracts before resuming any stage.

## Consent/privacy incident

Immediately pause the pilot, revoke the affected source scope, and notify Security/Privacy. Preserve only event hashes and timestamps needed for audit; do not copy prompts, responses, or identifiers into tickets. Confirm subsequent snapshots omit revoked data, run the privacy and tenant-boundary tests, and obtain a written privacy review before re-entering the pilot gate.

## End-to-end recovery markers

The recovery evidence must cover: four assessments -> complete consented snapshot -> queue/outbox -> provider attempt -> validator -> Talent Passport -> recommendation/catalog -> roadmap -> click/feedback event -> refresh. Attach migration `status`/`validate` output, validator pass rate, freshness, error budget, privacy review, and rollback drill references to the staged rollout decision record.
