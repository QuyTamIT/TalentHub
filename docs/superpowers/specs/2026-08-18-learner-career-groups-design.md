# Learner Holland Career Groups Design

## Goal

Add deterministic Holland-only career-group recommendations and safe, idempotent
sample activities for four learner groups without requiring a model API key.

## Scope

The first release supports Holland dimension scores only. It maps the six
Holland dimensions to four product groups:

| Group code | Vietnamese label | Holland dimensions |
|---|---|---|
| `technical` | Kỹ thuật | `R`, `I` |
| `business` | Kinh doanh | `E` |
| `arts` | Nghệ thuật | `A` |
| `sports_academic` | Thể thao & Học thuật | `S`, `C` |

MBTI, DISC and Multiple Intelligence mappings are explicitly out of scope until
their business rules are separately approved.

## Architecture

`CareerGroupClassifier` is a pure deterministic component. It receives a
Holland dimension-score map, validates numeric scores in the 0–100 range, and
returns ranked groups using score descending and a stable group-code tie-break.
Each group includes its code, label, score, and contributing dimensions.

`RuleSetV1` consumes the classifier output through the existing
`RuleRecommendationEngine`. For each qualifying group it may produce a
traceable strength recommendation and recommendations for matching published
activities. Existing IoT and presentation rules remain unchanged. Activity
recommendations carry both `career_group` and `activity_source_id` in their
action payload so the learner UI/API can link to registration without exposing
raw personal data.

## Activity seed contract

The schema has an `activities` table, but no separate clubs/projects table.
Sample clubs and projects are therefore published activities with stable
category codes:

- `career_technical`
- `career_business`
- `career_arts`
- `career_sports_academic`

The seeder uses reserved canonical UUIDs, insert-only/idempotent behavior, and a
content conflict check. A rerun with identical content is a no-op; a matching
ID with different content fails closed. It runs only against a disposable
MySQL 8.4 database in this task and never writes `talenthub_local`.

## Error and safety behavior

- Missing or malformed Holland scores produce no speculative group output.
- Consent and existing data-quality gates remain authoritative.
- Inactive, archived, cancelled, or past sample activities are not recommended.
- No Gemini, 9Router, API key, migration, or production seed execution is part
  of this change.

## Verification

Tests will cover:

1. Classifier mapping, ranking, ties, invalid scores, and deterministic output.
2. Rule Engine recommendations and action payloads for all four groups.
3. Existing rule regressions.
4. Seeder first run, second-run no-op, content-conflict rejection, disposable
   schema/foreign-key checks, and unchanged `talenthub_local` status.
