# Learner AI provider configuration

## Default-safe behavior

`TALENTHUB_AI_ENABLED=false` is the default. When disabled, the model engine selects the versioned rule fallback and does not call a provider.

## Required configuration when enabled

| Variable | Constraint |
|---|---|
| `TALENTHUB_AI_PROVIDER` | Non-empty provider identifier |
| `TALENTHUB_AI_MODEL` | Non-empty model identifier |
| `TALENTHUB_AI_API_URL` | HTTPS URL whose host is in `TALENTHUB_AI_ALLOWED_HOSTS` |
| `TALENTHUB_AI_ALLOWED_HOSTS` | Comma-separated approved HTTPS hostnames |
| `TALENTHUB_AI_API_KEY` | Secret, never logged or exposed in diagnostics |
| `TALENTHUB_AI_TIMEOUT_SECONDS` | Integer from 1 through 10; default 2 |
| `TALENTHUB_AI_MAX_ATTEMPTS` | Integer 1 or 2; retry is only for 502/503 |

The provider returns an `items` JSON array. Each item must cite one or more opaque `evidence_ref_ids` from the supplied request. The request excludes learner IDs, source IDs, email, tokens, and raw provider/persistence payloads.

No real key, provider configuration, network call, or visible model rollout is authorized by this document. Keep the feature disabled until shadow-evaluation and release gates are separately approved.
