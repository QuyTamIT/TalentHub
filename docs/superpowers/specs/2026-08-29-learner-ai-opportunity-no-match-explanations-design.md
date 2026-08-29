# Learner AI Opportunity No-Match Explanations Design

**Date:** 2026-08-29  
**Status:** Approved for planning  
**Scope:** Learner portal, inline `Hệ sinh thái & Cơ hội` AI block only

## Goal

Replace the current generic `catalog_insufficient` message with a grounded Gemini explanation that tells a learner whether suitable opportunities exist, why current opportunities are or are not suitable, which requirements are missing, and what the learner can do next.

The feature must never invent an opportunity or imply that a learner is ranked against other people. It evaluates personal fit only.

## User Experience Decision

The server classifies results by the final personal-fit score:

- **60–100 — Suitable:** Show up to three opportunity cards as recommendations. Each card includes a score, a project-specific Gemini explanation, matched skills, missing skills, expected outcomes, evidence sources, and the canonical project link. The block may contain one, two, or three cards; three is no longer required for a useful result.
- **40–59 — Low fit:** Do not label these opportunities as recommendations. Show up to three compact diagnostic cards under `Cơ hội hiện còn ít phù hợp`, explaining why each opportunity is not yet suitable, its missing skills or conditions, and concrete improvement steps. The action copy is `Xem yêu cầu dự án`, not a participation recommendation.
- **0–39 — No fit:** Do not render project cards. Show a grounded Gemini summary describing the dominant requirements in the current opportunity catalog, the learner's current strengths or direction, the main mismatches, and a reassuring next step such as waiting for new opportunities or developing specific skills.
- **No canonical candidates:** Show a separate grounded explanation that the current catalog has no available opportunity after hard eligibility gates. Gemini may explain server-provided aggregate exclusion reasons but may not invent catalog characteristics.

The existing full Top 3 presentation remains the ideal state when three suitable opportunities exist.

## Scoring and Classification

The deterministic scorer continues to calculate the structured component. Gemini supplies its bounded `gemini_score` and evidence-backed analysis. The server remains the only component allowed to calculate:

```text
final_match_score = round(0.70 * structured_score + 0.30 * gemini_score)
```

The final score, not Gemini prose, determines the tier. Gemini cannot promote or demote an item by changing a label.

To avoid hiding potentially suitable items before Gemini is called, the server sends at most ten eligible canonical candidates to Gemini. The provider response may analyze one to three selected candidates. The server composes final scores, classifies the items, and returns only the tier permitted by the rules above.

When all eligible candidates have a structured score below 40, the server requests only a catalog/profile gap analysis. It does not ask Gemini to recommend a project.

## Gemini Analysis Modes

The dedicated opportunity prompt supports three strict modes:

### `recommendation`

Gemini analyzes one to three allow-listed candidates that may reach the suitable tier. Every item must return:

- `catalog_id`
- `gemini_score` between 0 and 100
- `why_fit`
- `matched_skill_codes`
- `missing_skill_codes`
- `expected_outcome_codes`
- `evidence_ref_ids`

### `low_fit`

Gemini analyzes up to three allow-listed candidates in the 40–59 final-score tier. Every item must return:

- `catalog_id`
- `gemini_score`
- `why_not_fit_yet`
- `matched_skill_codes`
- `missing_skill_codes`
- `missing_conditions`
- `improvement_steps`
- `evidence_ref_ids`

### `no_fit`

Gemini receives a consent-safe learner profile, server-generated catalog aggregates, and server-generated exclusion counts. It returns:

- `headline`
- `explanation`
- `learner_strengths`
- `catalog_demands`
- `main_gaps`
- `next_steps`
- `evidence_ref_ids`

If there are no canonical candidates, `catalog_demands` must be empty unless the server supplies a safe aggregate derived from canonical records. Gemini must state that the catalog is currently insufficient instead of guessing.

## Grounding and Safety

- Gemini receives only consent-allowed snapshot fields and at most ten canonical candidate records.
- Email, phone, precise address, health data, protected traits, free-form private notes, secrets, and raw prompts are never persisted or sent.
- All candidate IDs, skill codes, outcome codes, condition codes, and evidence references are validated against server allow-lists.
- Titles, providers, URLs, deadlines, availability, scores, and tier labels remain server-owned.
- Exclusion reasons are server-generated codes such as `education_band_mismatch`, `expired`, `full`, `already_applied`, `tenant_mismatch`, `protected_eligibility`, or `score_below_threshold`.
- Gemini prose must cite at least one supplied evidence reference. Unsupported promises about admission, hiring, awards, or guaranteed results are rejected.
- Provider failure never produces fake analysis. The UI shows a safe temporary-unavailable state or a still-valid persisted explanation marked as stale.

## Service and API Contract

The service replaces the generic insufficient result with these states:

- `ready_model` — three suitable recommendations.
- `partial_model` — one or two suitable recommendations.
- `low_fit_model` — no suitable recommendation, but one to three diagnostic items in the 40–59 tier.
- `no_fit_model` — no item reaches 40, or no canonical candidate remains after hard gates; includes a Gemini summary.
- Existing `consent_required`, `insufficient_data`, `provider_unavailable`, and `stale_model` states remain.

The safe response shape is:

```json
{
  "state": "partial_model | low_fit_model | no_fit_model",
  "tier": "suitable | low_fit | no_fit",
  "items": [],
  "analysis": {
    "headline": "",
    "explanation": "",
    "learner_strengths": [],
    "catalog_demands": [],
    "main_gaps": [],
    "next_steps": [],
    "evidence": []
  }
}
```

Suitable and low-fit item responses include `analysis_kind` and the existing canonical server fields. The UI never exposes `structured_score` or `gemini_score`; it shows only `match_score`.

## Persistence

Migration 016 adds a nullable run-level diagnostic analysis JSON field to `learner_recommendation_runs`. It stores the validated no-fit or tier summary for `capability = 'opportunity_match'` so GET can reload the same explanation without calling Gemini.

Opportunity-match runs may persist zero to three items:

- Three suitable items for `ready_model`.
- One or two suitable items for `partial_model`.
- One to three diagnostic items for `low_fit_model`.
- Zero items plus run-level analysis for `no_fit_model`.

Repository reads and writes continue to require `capability = 'opportunity_match'`. Completion remains transactional, idempotent, and last-known-good aware.

## UI Design

The new states stay inside the approved AI block above the ordinary opportunity list.

### Suitable

Render the existing detailed card style with `Cơ hội phù hợp với bạn`. For one or two cards, do not show empty placeholders and do not claim `Top 3`.

### Low fit

Render a warning-toned summary followed by compact diagnostic cards. Each card shows:

- `Điểm phù hợp hiện tại`
- `Vì sao chưa phù hợp`
- `Bạn đang có`
- `Bạn còn thiếu`
- `Nên cải thiện`
- `Nguồn phân tích`
- `Xem yêu cầu dự án`

The card must not use success colors or recommendation language.

### No fit

Render one explanatory panel without project cards:

- What current catalog opportunities mainly require.
- What the learner profile currently leans toward.
- Why those two sides do not match yet.
- Which skills or profile evidence would improve future matching.
- A calm message that TalentHub will continue updating opportunities.

All Gemini strings are rendered with `textContent`. The panel uses the approved Be Vietnam Pro typography and existing warning/secondary design tokens.

## Data Flow

```text
authorize + consent
→ build consent-safe snapshot
→ load canonical catalog/opportunity records
→ hard-gate and record exclusion reason codes
→ structured-score eligible candidates
→ select Gemini mode and at most 10 candidates/aggregates
→ validate grounded Gemini output
→ calculate 70/30 final scores on the server
→ classify 60+/40–59/<40
→ persist run, items and diagnostic analysis transactionally
→ return suitable, low-fit or no-fit UI state
```

## Testing

Tests must prove:

- Three final scores of at least 60 return `ready_model`.
- One or two final scores of at least 60 return `partial_model` with no empty cards.
- No suitable result plus scores from 40–59 returns `low_fit_model` with project-specific Gemini diagnostics.
- All scores below 40 return `no_fit_model` and no project cards.
- Zero canonical candidates returns a grounded catalog-insufficient explanation without invented demands.
- Every Gemini analysis cites supplied evidence and uses allow-listed IDs/codes.
- Provider failure does not masquerade as a Gemini analysis.
- Persisted partial, low-fit, and no-fit states survive GET reload and exclude candidates that later close or expire.
- UI copy, safe DOM rendering, accessibility states, and the ordinary 21-item opportunity list remain intact.

## Out of Scope

- Dashboard recommendations.
- A separate AI tab, modal, drawer, or page.
- Changing ordinary ecosystem search/filter behavior.
- Learner ranking, employment prediction, admission prediction, or guaranteed outcomes.
- Sending personal contact data or protected traits to Gemini.
