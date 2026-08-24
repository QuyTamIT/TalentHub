# Assessment Band Inference and Runner UI Design

Date: 2026-08-24
Status: Proposed for implementation

## Objective

Remove repeated education-band prompts when a learner's school and class already identify the band, repair the broken answer-label layout, and polish the assessment runner without changing scoring, autosave, submission order, or onboarding security.

## Confirmed root causes

1. `EducationBandResolver` reads only `classes.gradeLevel`. University cohorts use values 1–4 for study years, so “Đại học FPT – Năm 1” is treated as ambiguous even though `schools.level` is “Đại học”. The confirmed band is request-scoped, which makes the chooser appear again on the next assessment.
2. The answer CSS defines two columns for a numeric badge and answer text, but JavaScript renders both values as one text node. That node occupies the narrow badge column and wraps almost every word onto a separate line.

## Education-band inference

No registration field and no database column will be added in this iteration. School and class remain the authoritative source.

`EducationBandResolver` will query both `classes.gradeLevel` and `schools.level` and resolve in this order:

1. A recognized college/university level (`Đại học`, `Cao đẳng`, `university`, or `college`) resolves to `college`. This intentionally supports study-year values 1–4.
2. Grades 6–9 resolve to `middle`.
3. Grades 10–12 resolve to `high`.
4. A recognized middle-school or high-school level is a fallback when its grade is outside the canonical range.
5. An explicit, server-validated confirmation remains the fallback only when neither source can resolve a band.

This is evaluated on every assessment request, so existing learners benefit immediately and the inferred band remains consistent after logout. A college school takes precedence over its 1–4 study-year value. Unknown schools with grades 1–5 are not silently classified because the product has no primary-school assessment catalog; they retain the existing confirmation path.

The existing server-provided `next_url` continues to control the ordered onboarding transition. Once inference succeeds, completing Holland goes directly to MBTI, then DISC, then Multiple Intelligence, with no intermediate band chooser.

## Answer rendering and visual treatment

Each option will keep its accessible `<label><input type="radio">…</label>` relationship but render three visual parts:

- a fixed numeric badge;
- a flexible text label with `min-width: 0` and normal multi-line wrapping;
- a selected-state indicator.

The runner will retain the current page structure while receiving focused visual improvements:

- clearer question hierarchy and more breathing room;
- larger rounded answer cards with stable horizontal alignment;
- distinct hover, keyboard-focus, saving, and selected states;
- a cleaner progress header and question navigator;
- responsive spacing and full-width answer text on mobile;
- no horizontal overflow and no one-word-per-line wrapping.

Question content and option labels continue to use `textContent`; no assessment text may be inserted through `innerHTML`.

## Compatibility and hidden logic

- No schema migration or catalog reseed is required.
- Existing in-progress attempts keep their pinned assessment version and saved answers.
- Scoring stays server-side and is unchanged.
- Onboarding sequence enforcement stays server-side and is unchanged.
- Ambiguous or classless learners still receive the education-band chooser.
- Conflicting explicit input cannot override a band inferred from an authoritative school/class record.
- The registration form remains school + class only, avoiding a duplicate field that can disagree with institutional data.

## Verification

Implementation follows red-green TDD:

1. Add a resolver fixture for a university school with a year-1 class and prove it currently requires confirmation.
2. Add a UI regression proving numeric badge and answer text are separate DOM nodes and long labels keep the flexible text column.
3. Run assessment catalog, API, UI, onboarding, lint, and migration validation suites.
4. Run a visible Edge E2E with a new FPT university learner and verify all four assessments transition without another band chooser, while autosave, history, logout/login resume, and final dashboard unlock still work.
