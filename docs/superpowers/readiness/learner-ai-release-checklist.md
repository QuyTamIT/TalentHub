# Learner AI model-visible release checklist

## Current decision

**NOT READY — visible model percentage is fixed at `0`.** Rule baseline remains the only learner-visible engine.

## Required evidence before any value above zero

- [ ] Shadow-evaluation gate records 100% schema validity and evidence coverage, with 0% unsupported claims and unsafe output.
- [ ] Product owner records approved p50/p95 latency and cost-per-run thresholds in the evaluation gate.
- [ ] Provider failure simulation proves a completed rule result is returned for every tested failure.
- [ ] Consent revoke test proves both shadow and visible model calls are disabled.
- [ ] Disposable two-learner isolation verification passes.
- [ ] Security/bias review is approved with a sample size large enough for each reported group.
- [ ] Explicit model-visible approval records a deterministic pilot percentage above zero.

The feature flags are `TALENTHUB_AI_ENABLED`, `TALENTHUB_AI_SHADOW`, `TALENTHUB_AI_SHADOW_GATE_APPROVED`, `TALENTHUB_AI_VISIBLE_PERCENT`, and `TALENTHUB_AI_PROVIDER`. Turning any flag off requires no database change. Never use an environment or database edit to bypass this checklist.
