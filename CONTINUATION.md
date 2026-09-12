## Continuation Note

**Goal**: Refine seed data enrichment for learner recommendation and AI roadmap stores, aligning demo seeders with migrations 004/005.

### Current State
- **Done**: All migrations 004/005 constraints satisfied; `EnrichmentDemoSeeder.php` fully aligned; `setup-local.php` executes successfully with verified row counts.
- **In Progress**: None.
- **Blocked**: None.

### Recent Changes
- Fixed `seedRoadmaps()` in `EnrichmentDemoSeeder.php` to satisfy all CHECK constraints:
  - Changed `engineType` from 'rules_plus_llm' to 'model' (valid enum value)
  - Changed `status` from 'succeeded' to 'completed' (valid enum value)
  - Set `ruleVersion` to null when `engineType='model'` (required by constraint)
  - Wrapped `sourceUpdatedAt` in `json_encode()` to satisfy JSON validation constraint
  - Changed phase 1 `startDay` from 1 to 0 (required by position/day range constraint)
  - Changed task `actionType` from 'practice' to 'self_task' (valid enum value)
- Verified data integrity:
  - 2 snapshots, 2 runs, 2 roadmaps (one per student)
  - 6 phases (3 per roadmap with correct position/day ranges)
  - 12 tasks (6 per roadmap)
  - All foreign key relationships intact (studentId, runId, snapshotId, roadmapId, phaseId)

### Next Steps
1. **Commit** the updated `EnrichmentDemoSeeder.php` with migration/seeder annotations.
2. **Push** changes and update team documentation on the new seed alignment.
3. **Optional**: Add integration tests to verify the seeder works with different student configurations.