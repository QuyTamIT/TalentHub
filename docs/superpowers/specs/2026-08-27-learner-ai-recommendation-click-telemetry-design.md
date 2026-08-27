# Learner AI recommendation click telemetry design

## Scope

Record a product-analytics event only when an authenticated learner activates a real recommendation CTA, such as viewing an activity, opening an opportunity, or starting registration. Card expansion, refresh, feedback, and unrelated page links are not recommendation clicks.

## Client flow

Each rendered recommendation CTA carries only safe identifiers already returned by the recommendation API: `itemId`, optional `catalogId`, and an allow-listed `actionType`. The delegated click handler sends a same-origin `POST` to the learner API with the existing session CSRF token and `keepalive: true`. It does not await the response and never prevents default navigation, so an expired session, rejected CSRF token, network failure, or analytics outage cannot block the CTA.

The browser does not send recommendation title, summary, evidence, learner identifier, destination URL, prompt, or model output.

## Server flow

The endpoint uses the existing learner API bootstrap, authentication, role/permission checks, JSON validation, and CSRF validation. It accepts exactly:

- `itemId`: bounded opaque identifier;
- `catalogId`: optional bounded opaque identifier;
- `actionType`: one of `view_activity`, `view_opportunity`, `register_activity`, or `open_catalog_item`.

Before recording the metric, the server verifies that the referenced recommendation item belongs to the authenticated learner's current or retained recommendation data and that the supplied catalog identifier matches that item when present. Invalid, stale, cross-owner, or fabricated identifiers return a safe validation/not-found response and produce no metric.

The telemetry event contains only `recommendation_click=true` plus the allow-listed action category. It contains no student ID or recommendation ID because the metric is aggregate operational/product telemetry.

## Failure behavior

Telemetry is best-effort. Server failures never change recommendation data, enqueue refresh, or affect the destination page. The client preserves normal browser navigation in all cases. CSRF remains mandatory at the endpoint; reliability comes from non-blocking client behavior, not from weakening CSRF protection.

## Testing

- Endpoint rejects missing/invalid CSRF and unauthenticated requests without recording metrics.
- Endpoint rejects unknown/cross-owner item IDs and invalid action categories.
- A valid owned CTA records exactly one sanitized click event.
- Client sends same-origin JSON with CSRF and `keepalive`, does not await, and never calls `preventDefault`.
- Existing recommendation feedback remains feedback-only and is not counted as a click.
- Existing AI PHP and Node regression suites remain green.

## Non-goals

No third-party analytics SDK, clickstream database, URL tracking, learner profiling, deduplication across browser retries, or change to AI rollout visibility is included.
