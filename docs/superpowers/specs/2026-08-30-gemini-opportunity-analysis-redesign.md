# Gemini Opportunity Analysis Redesign

## Goal

Replace the current no-fit summary made of short chips and generic phrases with evidence-backed Gemini analysis that is substantial, project-specific, and visibly tied to real TalentHub opportunities.

## User experience

The result area contains a short overall Gemini assessment followed by zero to three recommended opportunity cards. An opportunity is shown only when Gemini returns its canonical catalog ID and the backend validates that ID against the active database catalog. The interface must never claim that opportunities are available without rendering the same opportunities.

Each rendered card contains:

- canonical project or opportunity title and provider data from the database;
- a prominent integer score out of 100;
- a colored horizontal score bar and a textual score band;
- a Gemini-written rationale of three to four meaningful sentences;
- detailed reasons the opportunity fits the learner;
- detailed reasons the opportunity does not yet fit the learner;
- skills the learner can learn or practise through the opportunity;
- a canonical internal link to view the opportunity.

The interface does not display the 70/30 scoring formula or its component calculations.

## Gemini contract

For recommendation and low-fit modes, each model item contains the canonical `catalog_id`, `gemini_score`, a three-to-four-sentence `rationale`, `fit_reasons`, `gap_reasons`, `skills_to_develop`, validated skill/outcome codes, and evidence references. The prompt explicitly prohibits templates, repeated wording, generic labels presented as analysis, and invented catalog information.

The validator requires substantial prose and rejects responses that are too short, contain fewer than three sentences, omit any analysis section, cite unsupported evidence, or reuse substantially identical rationales across opportunities. A malformed model response follows the existing one-retry path.

## Backend behavior

The service continues to calculate the final score internally but exposes only the final integer. It no longer discards ranked items merely because the highest final score is below 40. Such items remain visible as detailed `no_fit` cards when Gemini has selected and analyzed them.

If no eligible candidate can be sent to Gemini, the service returns a prose-only no-fit assessment and no opportunity cards. Its copy must not claim that hidden opportunities were analyzed. Canonical titles, summaries, providers, URLs, deadlines, and capacity remain database-owned and cannot be supplied by Gemini.

## Frontend behavior

Ready, low-fit, and analyzed no-fit results share the same card renderer. The no-fit summary panel becomes a concise prose introduction rather than a grid of chips and scoring-formula metrics. Project analysis sections use readable paragraphs and lists. The score is rendered as a number, semantic label, and accessible progress bar.

Missing optional canonical metadata is omitted. Missing required Gemini prose is treated as an invalid response instead of being replaced with prepared analysis phrases.

## Error handling and safety

- Model IDs and evidence references remain allow-listed.
- Model-created canonical project facts remain prohibited.
- Unsafe guarantees about admission, awards, hiring, or outcomes remain rejected.
- Malformed model output is retried once and then returns the existing safe unavailable state.
- All dynamic text is inserted with `textContent`.
- Canonical URLs continue through the existing internal URL validator.

## Tests

Automated tests cover:

- prompt and schema requirements for substantial rationale, fit reasons, gap reasons, and skills to develop;
- validator rejection of short or incomplete analysis;
- service preservation of below-40 ranked opportunities;
- API mapping of the new fields and score;
- frontend rendering of the score bar, three-to-four-line rationale, detailed fit/gap lists, skills-to-develop list, and canonical opportunity link;
- prose-only rendering when no opportunity is actually recommended;
- absence of the visible 70/30 formula and old analysis-chip layout.
