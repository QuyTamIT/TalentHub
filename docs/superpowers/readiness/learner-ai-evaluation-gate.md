# Learner AI shadow-evaluation gate

## Required outcomes before visible model output

| Metric | Required value |
|---|---:|
| Schema validity | 100% |
| Evidence coverage | 100% |
| Unsupported-claim rate | 0% |
| Unsafe-output rate | 0% |
| Simulated provider failures using rule fallback | 100% |

Latency p50/p95 and cost-per-run are recorded by the evaluator, but have no release threshold until the product owner approves exact values in this document. Small demographic or cohort samples must report `insufficient_sample`, not a bias score.

Shadow runs may persist a validated `engineType=model` run for audit and comparison. The visible learner response remains the completed rule result. `eligible_for_visible_rollout` stays `false` until this gate, cost/latency values, and the separate model-visible rollout approval are all recorded.
