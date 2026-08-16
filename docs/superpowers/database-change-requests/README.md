# Learner database change requests

Database changes are additive and forward-only. They require review before any learner migration or seed file is created or run.

## Required request contents

Every request must include the exact DDL; an ownership matrix for every table, column, index, and constraint; and compatibility analysis for existing applications and clients. Include backup proof, baseline row counts and checksums, repeated-run evidence, and post-change checks.

The request must also document an operational rollback plan. Rollback must preserve data: no destructive reversal, DROP, DELETE, TRUNCATE, destructive rename, type conversion, or backfill is permitted. New foreign keys must use `RESTRICT` or `NO ACTION`.

## Approval gate

**APPROVAL REQUIRED:** A reviewer must approve each exact path under `Database/migrations/learner/` or `Database/seeds/learner/` before it is introduced. The approval must identify the requested file path and the reviewed DDL exactly.

The scope audit reports unapproved database paths and destructive SQL tokens. It never runs migrations, connects to a database, or modifies the workspace.
