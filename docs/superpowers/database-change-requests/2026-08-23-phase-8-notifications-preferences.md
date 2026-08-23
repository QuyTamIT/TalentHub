# Database Change Request: Phase 8 Notifications and Preferences

- **Date:** 2026-08-23
- **Author:** Antigravity
- **Target Schema:** `talenthub_local`
- **Status:** EXECUTED_AND_VERIFIED — APPROVED_PHASE_8
- **Migration IDs:** `20260821000600_create_notifications_and_preferences`, `20260821000610_validate_phase8_notification_contracts`

---

## 1. Current Runtime Facts & Target Delta

### 1.1 Pre-Phase-8 Runtime Facts (`talenthub_local`)
- **Total Tables:** 56 base tables.
- **Migration Status:** 26 applied migrations, 0 pending, validation OK.
- **Existing Relevant Tables:**
  - `users`: 35 rows
  - `student_profiles`: 20 rows
  - `teacher_profiles`: 11 rows
  - `enterprises`: 1 row
  - `schools`: 3 rows
  - `permissions`: 102 rows
  - `role_permissions`: 120 rows
  - `notifications`: **DOES NOT EXIST** (0 tables, 0 rows)
  - `learner_notification_preferences`: **DOES NOT EXIST** (0 tables, 0 rows)
- **Permission Delta Needed:**
  - `notification.manage_preferences_own` ('Quản lý tùy chọn thông báo của bản thân') is absent in runtime `permissions` and will be added and mapped to all 4 system roles (`student`, `teacher`, `school`, `enterprise`).

### 1.2 Target Delta & Justification
- Create table `notifications`: Real database-backed in-app notifications with ownership (`userId`), idempotency barrier `(userId, eventKey)`, safe deep link, timeline index, and unread index.
- Create table `learner_notification_preferences`: Stores per-student in-app and email notification preferences by allow-listed type.
- Add permission `notification.manage_preferences_own` and deterministic role mappings.

---

## 2. Migration Strategy & Invariants

### 2.1 Forward-Only Additive Strategy
- Migration `20260821000600_create_notifications_and_preferences.php` is strictly additive.
- It introduces two new tables and registers 1 missing permission + 4 role mappings.
- Implements `TalentHub\Database\Migration\Migration` and declares `isReversible(): false` to avoid destructive drops when production data accumulates.

### 2.2 Immutability of Applied Migrations
- Migrations `20260814000100` through `20260821000520` remain 100% byte-identical and unmodified.
- `bin/migrate.php validate` verifies all 26 checksums remain unchanged.

### 2.3 Preflight Checks in `preflight()`
Before executing DDL, the migration preflight verifies:
1. Session time zone is `+00:00`.
2. Required parent tables exist: `users`, `student_profiles`, `permissions`, `roles`, `role_permissions`.
3. If target tables already exist (in partial/rehearsal state), verify strict column definitions, indexes, foreign keys, and defaults.

---

## 3. Baseline Manifest & Hashes (56 tables)

The 56 baseline tables in `talenthub_local` prior to Phase 8 migration:

| Table | Baseline Row Count | Stable Data SHA-256 |
|---|---:|---|
| `activities` | 26 | `09a3df3a9319340290f8edb437c9aedad15fd3c056093b931500127b2ca00f0c` |
| `activity_experience_policies` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `activity_qr_sessions` | 8 | `d7ee3dfba9d709fba5729fe57818623218902a4309f4611074d7a9785fc3cb40` |
| `activity_qr_tokens` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `activity_registration_policies` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `activity_registrations` | 40 | `4edc6271ff0884e8795aeb3d3493325723b406e01cbe1609553cb1af44e1b736` |
| `application_profile_snapshots` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `application_status_history` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `assessment_criteria` | 3 | `1497d2f2c94ef2fec7ba456e9c60f764b5c03bd84a48e0ef3fbc047c2017f26b` |
| `assessment_scores` | 60 | `348afe7e6c8747860b04b667581bb2d844e968c5bdee7f8e3eab15703482c1cc` |
| `assessments` | 20 | `030c4816205d7ac5db421d51d2bcdcf553a812c71151cef68a98a13c29bbcb7e` |
| `audit_logs` | 6 | `35b3e35bb8b2388d9e858a7ccdc55935b47a19f593307ef0b8dc617f67fbbee9` |
| `auth_rate_limits` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `certificates` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `checkins` | 20 | `851154a97c603bae024cc4b2bd2fdc3e19c0ac199cd1a1424bc1217fc67bc23f` |
| `classes` | 11 | `ee5ed56b1c0084ef2f7c8729e0837f7fcac42b1215fa9f8f58425d005a1f99e9` |
| `enterprise_members` | 1 | `0a3a0ec23a113902d8c9666742d7362bd7ed522aa5cce8994d223e5aef0bd2a4` |
| `enterprises` | 1 | `9488f3661393678b333e3eccb040bdd7427fdaefa25c19da7f3d8113d3722b77` |
| `experience_logs` | 20 | `044a47518e70ef715a0b1f1240948a43b2ed73910b5f8615b0f04a0eb19c560a` |
| `internship_applications` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `internship_posts` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `learner_ai_consent_events` | 76 | `8b63167e2cd9b07a75262f5aacbe9dd15d298d759ab6ded31c4d9a3c31ab14dd` |
| `learner_assessment_answers` | 1274 | `47496014d57f8c0be69d75267f5bddc90a9890f575ed02e013922391ffedf8d1` |
| `learner_assessment_attempt_metadata` | 42 | `2e9464e9103a39d36e5fca80fda0d1eb7015193c1dc8d26e815bf9c0eb7572af` |
| `learner_assessment_question_versions` | 366 | `ae38a791cbc14f8e2c270e416ae2ecffc2b1d429408bfd3bc5dc1a65c7a044d1` |
| `learner_assessment_versions` | 12 | `7f7f87550c81b56d4592e6b0a6e4d70f4209b7ab96ffb78a811dc17d87ca8a5f` |
| `learner_forward_migrations` | 3 | `01c0485b31a3085940d68df8cbf519f9d0d56a7fd2e8ec99e389df452199e588` |
| `learner_recommendation_audit_events` | 8 | `e4bd1b39bc4240ca2b1d8711e183a9b7a53d577af91a547f1d420ad1c44f6e13` |
| `learner_recommendation_evidence` | 24 | `c5d5b4dad01af856f9a6b0d1b536b7ac2403411b1ea8d1ca8aaf7314ed593f4c` |
| `learner_recommendation_feedback` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `learner_recommendation_input_snapshots` | 2 | `895070cf426ac3b2921ac4f04620a84f8cc351c004206d5bf3d10a78c149d9ae` |
| `learner_recommendation_items` | 13 | `fb38090653e6b1bbc12ada0a510567ee5ac920bc4c1a86bdbf083036ef9eadfa` |
| `learner_recommendation_runs` | 4 | `7e810fb49507f2adc80afc97672cd34814bbaf4ecadcacbd899e4d80c1e93223` |
| `learner_recommendation_snapshot_evidence` | 35 | `3eacf6df04513020ae7fe750d6b3ad4c2565a111933a7973366206def4fd0200` |
| `learner_skill_evidence` | 77 | `2adc2b248dc056721a6f13af0a7a2309766cdaebdd66ca92aa46f09a1045396f` |
| `permissions` | 102 | `58b264d8bca889b14ec21daa8251f53fb2079f489f50392f91eb53afbe1e45ab` |
| `privacy_consents` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `project_members` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `projects` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `reports` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `role_permissions` | 120 | `cdad99d89fa7378544498af248dc908df1b25b0660617a09484e2d7d184d355c` |
| `roles` | 4 | `c784f4055e2eee0c7dad3f41019bba84d9f9ad11cd2bf06dc7b360d2579a0dba` |
| `schema_migrations` | 26 | `7a41dbd731d5667e3df67a5aefeca7af7f15c5377f0403cda88d0723d92489cd` |
| `school_members` | 3 | `5be85468fc2344b64ca32155de8584c11875540e71385849603fb7ed88eee20b` |
| `schools` | 3 | `01feb818060b8b943b2d33febfd1012854d55ab39cfd20042fbe2d14fbea220f` |
| `skills` | 10 | `b824f958d03086760bbe615f8ef3394bbd94e5b2289f6a335085998f6c29679d` |
| `student_profile_details` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `student_profile_shares` | 0 | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `student_profiles` | 20 | `652c5e0c1a1903e24c6f8d5ec0dc53fe1ac1ad71e6e74922d40a1d371571e99c` |
| `student_skills` | 77 | `46f5e2a3546d97f95201403d3ee1e7689354a44e6d6e5d80c5674ec3d01f62e0` |
| `talent_tests` | 12 | `25d35c1ae160f5f497f1fdefaa80c44d7950b4dd4aa29e0031b04211ee5c58e2` |
| `teacher_profiles` | 11 | `2e8232c5f915b07af18999b63cc6cf3937270cf655ac0d9370ab4f71778aa878` |
| `test_attempts` | 42 | `309f1bd893972aa0f878abbb38748a63035bec5aadc39c18cc232843c24f163c` |
| `test_questions` | 366 | `59e661589814d7a92e8b21add129df747e114bc9849ac0e1145296ce217aedee` |
| `test_results` | 42 | `f23cecf93164eae41add90f29c99e95b0b2446ebe9162745bf96bde3db1a4dce` |
| `users` | 35 | `5eef22e78c04c832af1f2739dcb9fb673d95f26c3060f2aaa1e548a17fd1d871` |

---

## 4. Rehearsal & Verification Protocol

1. **Backup Primary:** Generate full `mysqldump` of `talenthub_local` before rehearsal and calculate SHA-256.
2. **Disposable Clone:** Create `talenthub_phase8_rehearsal_<timestamp>` and restore the verified backup.
3. **Migration Apply Twice:** Run migration `20260821000600` once (expect 27 applied, 58 tables), then run again (expect clean no-op).
4. **Verify Data Preservation:** Prove all 56 baseline non-registry tables retain identical row counts and data hashes.
5. **Functional & Concurrency Tests:** Run API, producer atomicity, rollback injection, and RBAC on disposable schema.
6. **Cleanup:** Drop disposable schema and revoke grants.

---

## 5. Rollback & Recovery Procedure

- Migration `20260821000600` declares `isReversible(): false` to protect notification history.
- Recovery in case of failure: restore from pre-migration backup with operator confirmation.

---

## 6. Final Execution Evidence

- The original additive migration `00600` was preserved byte-for-byte after it had been applied. Final review introduced `00610` as a validation-only forward migration for exact columns, defaults, indexes, foreign keys, engine/collation, permission metadata, and all four canonical role mappings.
- Final pinned rehearsal input: `talenthub_local_pre_phase8_20260823_083516.sql`, 812,710 bytes, SHA-256 `599d170f5559344b672f3baf10444e2639daa5604121537aae593565384e05e7`.
- Final rehearsal schema: `talenthub_phase8_rehearsal_20260823021809`; both migrations applied, the second migration run was a no-op, 74 integrity assertions, 27 endpoint assertions, 18 MySQL concurrency/rollback assertions, and the deliberate forward-contract conflict test passed. The grant was revoked and the schema was dropped.
- Fresh backup immediately before primary `00610` apply: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase8_00610_20260823_091851.sql`, 816,185 bytes, SHA-256 `da6a559853a043235a7222365fac6480499c9958d66178677b297e94124978a7`.
- Post-apply primary state: 58 base tables, 28 applied migrations, 0 pending, validation OK, 0 notification rows, and 0 preference rows.
- Business/RBAC invariance across `00610`: permissions remained 103 rows with SHA-256 `4d495c3c4881c3626cc99e6730cc94108bf3976102a6a444cad6d9a37fa2fd15`; role mappings remained 124 rows with SHA-256 `73ccc81a1ba6aaad4e828ae9173c5c25af1f8fff9d5329ac2dc8e17fa62dbfef`; the exact Phase 8 permission remains mapped to the four canonical roles.
