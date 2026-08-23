# Database Change Request: Phase 7 Enterprise Application Lifecycle

- **Date:** 2026-08-22
- **Author:** Antigravity (Phase 7 Basic Preflight)
- **Target Schema:** `talenthub_local`
- **Status:** APPROVED_FOR_CODEX_EXECUTION_WITH_GATES (primary apply remains conditional on disposable rehearsal, fresh backup, and all green gates)
- **Migration ID:** `20260821000500_create_internships_and_application_lifecycle`

---

## 1. Current Runtime Facts & Target Delta

### 1.1 Current Runtime Facts (`talenthub_local`)
- **Total Tables:** 52 base tables.
- **Migration Status:** 23 applied migrations, 0 pending, validation OK.
- **Existing Relevant Tables:**
  - `enterprises`: 1 row (`10000000-0000-4000-8000-000000000003`)
  - `enterprise_members`: 1 row (`10000000-0000-4000-8000-000000000024`)
  - `student_profiles`: 20 rows
  - `privacy_consents`: 0 rows (Consent scope `application_profile_share` already valid in CHECK constraint)
  - `student_profile_details`: 0 rows
  - `student_profile_shares`: 0 rows
- **Phase 7 Tables Currently Present in Runtime:**
  - `internship_posts`: **DOES NOT EXIST** (0 tables, 0 rows)
  - `internship_applications`: **DOES NOT EXIST** (0 tables, 0 rows)
  - `application_status_history`: **DOES NOT EXIST** (0 tables, 0 rows)
  - `application_profile_snapshots`: **DOES NOT EXIST** (0 tables, 0 rows)
- **Primary Data Manifest SHA-256:** `910fbfc4f134bfd79227141b2ee30d16c468fed97bdb087884003376785ebf00`

### 1.2 Target Delta & Justification
- Create table `internship_posts`: Enables Enterprise recruitment posting with structured title, field, deadline, skills, slots, and status.
- Create table `internship_applications`: Manages student applications with unique post/student barrier and status lifecycle.
- Create table `application_status_history`: Provides an immutable audit trail of every status transition (`submitted` &rarr; `reviewing` &rarr; `interview` &rarr; `accepted`/`declined`/`withdrawn`).
- Create table `application_profile_snapshots`: Stores an immutable, allow-listed JSON talent passport snapshot linked to canonical `privacy_consents`.

---

## 2. Migration Strategy & Invariants

### 2.1 Forward-Only Additive Strategy
- Migration `20260821000500_create_internships_and_application_lifecycle.php` is strictly additive.
- It introduces four new tables without altering, truncating, or dropping any existing tables or rows.
- Migration implements `TalentHub\Database\Migration\Migration` and declares `isReversible(): false`.

### 2.2 Immutability of Applied Migrations
- Migrations `20260814000100` through `20260821000400` remain 100% byte-identical and unmodified.
- `bin/migrate.php validate` will verify that all 23 checksums remain unchanged.

### 2.3 Preflight Checks in `preflight()`
Before executing DDL, the migration preflight will verify:
1. Session time zone is `+00:00`.
2. Required parent tables exist: `enterprises`, `student_profiles`, `users`, `privacy_consents`.
3. If any target table already exists (in partial/rehearsal state), verify strict column definitions, indexes, foreign keys, and CHECK constraints.

---

## 3. Preservation Manifest & Baseline Hashes

The 52 baseline tables in `talenthub_local` prior to Phase 7 migration:

| Table | Baseline Row Count | Stable Data SHA-256 |
|---|---:|---|
| `activities` | 26 | `a9f34f4c5fab0b87bbb4a944551a8cf45b770797059906b4922fb534089af43f` |
| `activity_experience_policies` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `activity_qr_sessions` | 8 | `10452a121f9d0f417cc71d3948d1442c294c778a3c57b6bec425d0caa036afcc` |
| `activity_qr_tokens` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `activity_registration_policies` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `activity_registrations` | 40 | `07a6e6c93dde0e4fe6fcad713036806cd6b85498f2ee7a67440c094ff6f2168e` |
| `assessment_criteria` | 3 | `7d736b1aa9f7a703ef36b75d0c12e49286354aa0cbb79fba69d1655f13daa41c` |
| `assessment_scores` | 60 | `4266060487d76522001331643854987a042e1f11d3e6babe3dccdd463ab7493c` |
| `assessments` | 20 | `a38ed47e202ddcb7c4bc5616b3d8dd94582cbdaa7b03f748b1b5a3120d76d941` |
| `audit_logs` | 6 | `03ad1ebcbed45e3a53b194c2da60b555d3e2b0a98187fbd07111fa4789e2eff9` |
| `auth_rate_limits` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `certificates` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `checkins` | 20 | `e6079b854d5cf1ccfd81064c04c1dfe3c9c933d622c673d50dd972f559e97903` |
| `classes` | 11 | `6fa4b0d974506ffe2e82c8d92e418f43d0e599ce197ef05d988ff28b3e913ff4` |
| `enterprise_members` | 1 | `45d3023b9cd00929bd53568ac0030d8ed28c09fd540a410bfaea39c0e546b85e` |
| `enterprises` | 1 | `3231b828bdfcbd5b4ffd86ad3095f686c3a43e8796c4b4f540e4447be6695cca` |
| `experience_logs` | 20 | `f643423b4d637c7ecced69b848835694bdb990145ace37a516c3db9299c32406` |
| `learner_ai_consent_events` | 76 | `dc1fced26c7e8928b97d316b1f3768b00f89913946e22808aa63c92ce04bbb49` |
| `learner_assessment_answers` | 1274 | `c016bfcdaa516091db3e79ff6128534e1f0fa6d934cf47f894e135923e3da9b4` |
| `learner_assessment_attempt_metadata` | 42 | `d0e15f6fc44d198107b5956c171d16538c827241f34239d1743fc7c3ff4fb445` |
| `learner_assessment_question_versions` | 366 | `2e370e5665b2d81ee49436ef2a69fe60738106773268a91a4eccd93e8f98167d` |
| `learner_assessment_versions` | 12 | `93212329d4a260d178fd69360ebd1eb761d55df1e11ab9ad8928d8ca2f4bcc07` |
| `learner_forward_migrations` | 3 | `0b33fc4beef1357e742fa05656620fffc02fcb61c81c90d4f3af8c447ca1ece8` |
| `learner_recommendation_audit_events` | 8 | `152a072312c51133c98cc38512caaa33da094c4fa83d3e53a6ed590dd8952f08` |
| `learner_recommendation_evidence` | 24 | `6845ca5313d0765dcd3d50f8c836ecb1a4cfd5d4e9973085a40de0e66eea2d55` |
| `learner_recommendation_feedback` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `learner_recommendation_input_snapshots` | 2 | `bb070ddf9bb34687482f52f92f992ba04b3e91d3f03852fe455a21e14f6fd171` |
| `learner_recommendation_items` | 13 | `c40ffdba407d66f21df0aae0dd9ed26ddaaebcfa7009046900918234686affd1` |
| `learner_recommendation_runs` | 4 | `2cc1806d23de713ccb06c74a057db0c840446c81f615c74cd6c6883b606fbaa9` |
| `learner_recommendation_snapshot_evidence` | 35 | `43596fee2f4fd49765f18f5c81fc17f86192686eb7954fa736e285b4b44991f6` |
| `learner_skill_evidence` | 77 | `b5423d8aef38a2b568529b65a583aac631507909249ef5b015d1c7dd9720927b` |
| `permissions` | 102 | `97158c5414c30d3487fdcf39bfbe4c90df18bd45031d8dc3faf8d85c2ddc6a46` |
| `privacy_consents` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `project_members` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `projects` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `reports` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `role_permissions` | 120 | `a1ff3e2da7190f10e6893c9142e2eb5d0501c5deaccea474b8e974fab34982d7` |
| `roles` | 4 | `559601acb9fc0c5a26c2d0560055a37e9702531855a481bbc4fb282db894108f` |
| `schema_migrations` | 23 | `5e76f0ee4caafad1ef46e38baf6c383415ce8c808aa7c059ba0ae531e8ba0c5e` |
| `school_members` | 3 | `e3ecb0a284b7253be2b7d4bd372d664689698f3ef2d98b79d2bc19b79f38f874` |
| `schools` | 3 | `b91f48649594de9879ab1772825a29a573344676307204695d02dc562e5bfb1e` |
| `skills` | 10 | `8e074c1f60bf23abcf7ef43bbff85c4a8b140bdded0f493c1ed2e659c472e5ab` |
| `student_profile_details` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `student_profile_shares` | 0 | `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` |
| `student_profiles` | 20 | `3b23da366118b4f17af6566dc878b85329cd6ee4bbedf24df9a0390d11fc5adc` |
| `student_skills` | 77 | `a0baf73ed4ba8c3979784a2d69deb09323f8314da93c857a4620b74065d74922` |
| `talent_tests` | 12 | `13cd53d7d3af3b5534cb39931f3d3ccdf773a3b362a8edb2271a3e2a06757a37` |
| `teacher_profiles` | 11 | `868f3d729fd9d18ef86f8ea06267def02a59ce1e588b1e3312ddc0778cf7926f` |
| `test_attempts` | 42 | `12542aaaf40b50931f45395aff760c3079bfe5c608c8f09cb216e70f70708c3e` |
| `test_questions` | 366 | `538cee1f210921b59e075ead31abbd124b86074e4ca9d55aeca85ce93e0ab913` |
| `test_results` | 42 | `99ef126f5d3424437ed1f8ecf1ef342931b860d83a6d9f8265b4bb1a5ce4f96f` |
| `users` | 35 | `2fe3ca85185b9369529f0526bdd046dd02f30979fb6840d15d77831413ea7f4c` |

---

## 4. Disposable Rehearsal & Verification Plan

Before any primary execution, Codex CLI must perform the following rehearsal protocol on a disposable database:

1. **Backup Primary:** Generate full `mysqldump` of `talenthub_local` into temp directory and compute its SHA-256 checksum.
2. **Create Disposable Clone:** Create `talenthub_phase7_rehearsal_<timestamp>` and restore the backup.
3. **Run Pre-Migration Contract Tests:** Execute `tests/application_profile_snapshot_migration_test.php`.
4. **Apply Migration (1st Pass):** Run `bin/migrate.php up` against the rehearsal schema. Verify exit code 0 and table count increases from 52 to 56.
5. **Apply Migration (2nd Pass - Idempotency):** Run `bin/migrate.php up` again on the rehearsal database. Verify 0 pending migrations and no error (clean no-op).
6. **Verify Data Preservation on Rehearsal:** Prove all 51 pre-existing non-registry tables retain identical row counts and data hashes. `schema_migrations` is the sole expected exception: it must append exactly the `20260821000500` row and preserve all 23 prior rows/checksums byte-for-byte.
7. **Run Functional / Concurrency Integration Tests:** Execute application submission, snapshot creation, and status transitions against the rehearsal database.
8. **Drop Rehearsal Clone:** Clean up disposable schema after full verification.

---

## 5. Rollback and Recovery Procedure

- **Migration Irreversibility:** Migration `20260821000500` is declared non-reversible (`isReversible() === false`) to prevent accidental schema drop.
- **Recovery Strategy:** Forward-only repair or full restore from pre-migration backup.
- **Automatic Restore Prohibition:** No script may automatically drop `talenthub_local`. Any primary database restore requires explicit human operator confirmation.

---

## 6. Authorization Gate

- **Anti Turn:** PRIMARY APPLY IS **NOT AUTHORIZED**. No migration file is created or executed on `talenthub_local`.
- **Codex CLI Turn:** Primary apply will only be performed after:
  1. Reviewer approves DCR and Design.
  2. All automated tests pass in disposable rehearsal.
  3. Fresh primary backup SHA-256 is recorded.

---

## 7. Forward Repair Amendment — 2026-08-22

Independent review found four exact-metadata deviations after
`20260821000500` had already been applied. The applied file remains immutable.
The forward-only migration
`20260821000510_reconcile_phase7_exact_metadata.php`:

- sets `internship_posts.workType` default to `full_time`;
- narrows `educationLevel` to `VARCHAR(100)` only after rejecting values longer
  than 100 characters;
- removes obsolete `internship_applications.cvUrl` only when it has no non-empty
  value;
- sets `application_profile_snapshots.schemaVersion` default to `1.0.0`.

The metadata repair was rehearsed from the pre-Phase-7 backup under the exact
schema prefix `talenthub_phase7_rehearsal_`, applied twice, and passed the
integrity, lifecycle and HTTP/concurrency gates. A new primary backup was
created immediately before applying only that repair migration. This produced
the reviewed intermediate state of 56 tables / 25 migrations / 0 pending.

## 8. Exact Index Repair Amendment — 2026-08-22

Independent re-review found that the applied schema still used broader indexes
than the approved contract and relied on one implicit foreign-key index. The
already-applied `00500` and `00510` files remain immutable. Forward-only
migration `20260821000520_reconcile_phase7_exact_indexes.php`:

- replaces the combined post index with exact enterprise and status/deadline
  indexes;
- narrows the Student and post/status application indexes to their exact
  approved sequences;
- creates the explicit changed-by history index and removes the redundant
  implicit index without changing its foreign key.

The direct rehearsal test now self-orchestrates restore, apply twice, exhaustive
metadata/data checks, lifecycle and HTTP gates, and allow-listed grant/schema
cleanup. It
passed before and after primary apply: 88 integrity assertions, 34 lifecycle
assertions and 32 runtime endpoint assertions. A fresh backup was created at
`C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase7_index_repair_20260822_233010.sql`
(812,556 bytes, SHA-256
`5d82d734f688540f9cf0ac11677c795c4fa229b75c7741fe39372869efa94726`).
Primary finished at 56 tables / 26 migrations / 0 pending; all four Phase 7
tables remain empty.

The restore baseline is pinned to
`talenthub_local_pre_phase7_20260822_225511.sql` (804,165 bytes), SHA-256
`c7435080598d68e495fe4ed514868bbd0644a900c06341020df5c4f7692e4c8c`.
The rehearsal verifies that digest before creating the disposable schema.
Two final consecutive runs left 0 matching disposable schemas and 0 matching
`mysql.db` grant rows.
