# Local 9Router Loopback Design

## Goal

Allow TalentHub's learner recommendation AI to call a developer-owned 9Router instance at `http://localhost:20128/v1/chat/completions` without weakening the HTTPS requirement for staging or production. Keep AI output invisible while the integration is being verified.

## Scope

- Update `RecommendationConfig` URL validation only.
- Configure the ignored local `.env` for the 9Router endpoint and selected combo model.
- Add focused configuration tests and run the existing AI, learner, and role regression suites.
- Do not change Teacher, School, or Enterprise behavior.
- Do not commit, log, print, or persist an API key outside `.env` or deployment secret storage.

## URL Security Rules

An AI endpoint is accepted when either condition is true:

1. It uses HTTPS and its normalized hostname is present in `TALENTHUB_AI_ALLOWED_HOSTS`.
2. It uses HTTP only when all of these conditions hold:
   - `APP_ENV` is `local` or `test`;
   - the hostname is exactly `localhost`, `127.0.0.1`, or `::1`;
   - the port is exactly `20128`;
   - the normalized hostname is present in `TALENTHUB_AI_ALLOWED_HOSTS`;
   - the URL contains no username or password component.

HTTP endpoints outside loopback, HTTP on another port, and every HTTP endpoint in staging or production remain invalid. Existing HTTPS behavior remains unchanged.

## Local Configuration

The ignored `.env` will use:

```dotenv
TALENTHUB_AI_ENABLED=true
TALENTHUB_AI_PROVIDER=9router_gemini
TALENTHUB_AI_MODEL=ag/gemini-3.7-flash-high
TALENTHUB_AI_API_URL=http://localhost:20128/v1/chat/completions
TALENTHUB_AI_ALLOWED_HOSTS=localhost
TALENTHUB_AI_SHADOW=true
TALENTHUB_AI_SHADOW_GATE_APPROVED=false
TALENTHUB_AI_VISIBLE_PERCENT=0
```

`TALENTHUB_AI_API_KEY` remains local and is intentionally absent from this document and Git history.

## Future Deployment

The API key is not coupled to local HTTP handling. Replacing the local key with another real key only changes the environment secret. A staging or production deployment must provide an HTTPS chat-completions URL and its hostname allowlist; it must not depend on the local loopback exception.

Before AI results become user-visible, deployment must separately approve the shadow gate and rollout percentage. Initial verification keeps `TALENTHUB_AI_SHADOW=true`, `TALENTHUB_AI_SHADOW_GATE_APPROVED=false`, and `TALENTHUB_AI_VISIBLE_PERCENT=0`.

## Data Flow

1. TalentHub loads AI settings from environment variables.
2. `RecommendationConfig` validates scheme, host, port, environment, and allowlist.
3. `HttpRecommendationProvider` sends an OpenAI-compatible request with the API key in the Bearer authorization header.
4. 9Router routes the request to the configured combo model.
5. TalentHub validates the structured response and records only recommendation evidence/audit data allowed by the existing learner AI flow.
6. Shadow mode prevents AI output from replacing the visible rule-based result.

## Error Handling

- Missing values fail configuration validation when AI is enabled.
- Invalid URL, host, environment, or port fails closed before a network request.
- Authentication, timeout, malformed response, and provider errors use the existing safe provider failure states.
- API keys are excluded from diagnostics and test output.

## Verification

- Add a failing test proving local HTTP loopback is currently rejected.
- Add acceptance tests for `local` and `test` loopback port `20128`.
- Retain rejection tests for production HTTP, non-loopback hosts, wrong ports, credentials in URLs, and hosts absent from the allowlist.
- Run the AI provider/configuration tests, learner regression tests, PHP lint, database validation, and isolated multi-role integration suite.
- Perform one minimal live request to the local 9Router endpoint without printing the key or response content.

## Success Criteria

- The local 9Router endpoint is accepted only under the documented loopback rules.
- A live shadow request authenticates successfully with the configured combo model.
- `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`.
- `.env` remains ignored and no API key appears in tracked files or Git diffs.
- Existing Teacher, School, Student, and Enterprise integration tests continue to pass.
