# Database Change Request: Phase 3 Passport Evidence and Sharing

**Date:** 2026-08-22
**Target:** `talenthub_local`
**Status:** APPLIED_WITH_FORWARD_REPAIR
**Authorization:** User approved Phase 3 completion and safe migration apply.

## Scope

Phase 3 uses six shared migrations:

1. `20260821000100_create_student_passport_sharing.php` created profile details and hashed profile shares and expanded the consent scope CHECK.
2. `20260821000200_create_student_certificates_and_projects.php` created certificate/project evidence and added `certificate.manage_own` for Student.
3. `20260821000204_validate_phase_3_canonical_contracts.php` is the strict validation-only precursor. It verifies every canonical column's type, length, nullability, default, datetime precision, CHECK tokens, FK actions, and consent owner/scope consistency.
4. `20260821000205_preflight_phase_3_reconciliation.php` is the structural validation-only precursor. On a fresh database it sorts before the repair and rejects incompatible ordered indexes, FK actions, table options, statuses, and orphan links before repair DDL.
5. `20260821000206_validate_phase_3_exact_metadata.php` is the exact validation-only precursor. It preserves meaningful CHECK parentheses during comparison and requires exact `EXTRA`/`ON UPDATE` metadata for every canonical column.
6. `20260821000210_reconcile_phase_3_contracts.php` is the forward-only repair. It adds `projects.category`, `project_members.contribution`, and `student_profile_shares.consentId`; widens project dates to `DATETIME(6)`; and links shares to `privacy_consents`.

The first two applied migration files remain byte-identical. The repair does not create badge tables, touch learner migrations `001`–`004`, or modify Teacher, School, Enterprise, activity, assessment, or AI-owned tables.

## Risk and controls

| Risk | Control |
|---|---|
| Applied-migration drift | Never edited migrations `000100` or `000200`; `bin/migrate.php validate` remains green. |
| Existing project rows | `category` is added nullable, empty rows are backfilled with `general`, then the column becomes `NOT NULL`; dates are widened from `DATE` to `DATETIME(6)`. |
| Partial/incompatible schema | Combined validation precursors `00204`, `00205`, and `00206` run before `00210` on fresh installs and check full column metadata, exact CHECK grouping, exact `EXTRA`/`ON UPDATE`, index, FK, table-option, status, timezone, and orphan semantics without DDL. |
| Consent/share inconsistency | New shares store `consentId`; create and revoke update share and consent in one transaction. |
| Data loss | No table drop, truncate, delete, or destructive `down()` exists in the repair migration. The original `000100` replaced the named consent scope CHECK; this is recorded rather than described as “zero DROP”. |
| Recovery | Fresh backup was restore-tested before apply. Recovery is forward-only; no automatic restore is run over the primary database. |

## Backup and invariant evidence

- Backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase3_repair_20260822103522.sql`
- Exact size: `798698` bytes
- SHA-256: `7AA072774FA7653D83CA7C180E96AD3D993B1A6AB47AE5D0BF4BC44FBB981C05`
- Restore clone contained all 17 pre-repair migration rows and matching exact table counts.
- Restore clone was deleted after its validated name matched `talenthub_phase3_restore_[0-9]{14}`.
- Normalized data dump SHA-256 for all non-owned tables before and after repair: `1166108800A455CC3072218545622A2732DE873211945E8B795A60804F1F3E0E`.
- Migration registry: `17 -> 18`; every other table row count stayed unchanged. The three altered Phase 3 tables also retained their row counts.

Post-review validation-only apply evidence:

- Backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase3_preflight_registry_20260822_110808.sql`
- Exact size: `799248` bytes
- SHA-256: `9EE2217B8C52EB4F125FAFC4AEF73971B1E1C11C491B7BD537A6A8D4C222F567`
- Restore clone contained all 18 pre-precursor migration rows and was removed after verification.
- Registry: `18 -> 19`; only `schema_migrations` changed.

| Application table | Before rows | After rows | Stable before/after SHA-256 |
|---|---:|---:|---|
| `certificates` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `projects` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `project_members` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `student_profile_details` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `student_profile_shares` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `privacy_consents` | 0 | 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |

Strict canonical validation apply evidence:

- Backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase3_strict_validation_20260822_112335.sql`
- Exact size: `799402` bytes
- SHA-256: `BA0F1159433DB68551A3934399B2665F90865E7D0EB9208F2E8BFAAF18FBB574`
- Restore clone contained all 19 pre-strict-validation registry rows and 50 base tables; restore cleanup succeeded.
- Registry: `19 -> 20`; only `schema_migrations` changed.
- Every one of the other 49 pre-existing tables retained both its exact row count and stable data-only SHA-256. The full manifest is recorded below; the hash shown is identical before and after.

Exact CHECK/column-EXTRA validation apply evidence:

- Backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase3_exact_validation_20260822_113423.sql`
- Exact size: `768220` bytes
- SHA-256: `E93542528C7377F729507CA6B31D1D529FD3961CF6FBC2663E5DB0A51E576D57`
- Restore clone contained all 20 pre-exact-validation registry rows and 50 base tables; restore cleanup succeeded.
- Registry: `20 -> 21`; `00206` is validation-only, so its `up()` performs no DDL or application DML and only `schema_migrations` gains one row.

| Table | Rows before/after | Stable SHA-256 |
|---|---:|---|
| `activities` | 26 / 26 | `DE346223DCE84847488D0C422895DC4117DE79B8A9C0244A74EAC71206FCB3B1` |
| `activity_qr_sessions` | 8 / 8 | `FF28F004FDA2F7F53809A0FE8051509FA5219E4E461115BF386D0F04902D01B4` |
| `activity_qr_tokens` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `activity_registrations` | 40 / 40 | `F968BEDAF7BDAD53C822442568A15374DAB9D798F345622564E63922B76561C5` |
| `assessment_criteria` | 3 / 3 | `70234D14E2D16F06F99C5E3A81E50A3A0953BF7CE03EFAEA0FC442B48CFF91D4` |
| `assessment_scores` | 60 / 60 | `88C5E4E884B651BA16D524E9F8CD744F62394B336A9B0205FE9848F483DD8F7B` |
| `assessments` | 20 / 20 | `B363FC09C4527F25D8034E25A1B8A396819ED096B724086F9428FD79C7344AEB` |
| `audit_logs` | 6 / 6 | `D2E95DCF28F710310E40841728E710DFFE980B0400A0B8D175ABE70DB5255B7C` |
| `auth_rate_limits` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `certificates` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `checkins` | 20 / 20 | `2CACBBCD80F3BEE154F369E266034CCD05DC5A41068C18C22799C283D0FCA494` |
| `classes` | 11 / 11 | `5995BF07FC044F11C381843759D8B881E07DEA1FF3740B082F45A64A07592453` |
| `enterprise_members` | 1 / 1 | `7C916357646EED8DE2EF836F08D2E1DC9F40E8528A5378DD706A4CD13F950EAA` |
| `enterprises` | 1 / 1 | `C07214B2F4598BB8E0A5E6F6AF3B3C8D8FE4EEF959B3E73B16B494E7E5E4198E` |
| `experience_logs` | 20 / 20 | `24F1E40E519B43987021108E2654581A4C9D4DC2B4C0F58FD38F72C72765D27C` |
| `learner_ai_consent_events` | 76 / 76 | `A625695E3CD45FAB8A108A67F60A81982DE2295808E65D851BFBD0531DC457A9` |
| `learner_assessment_answers` | 1274 / 1274 | `3D50D7E586D910034F2E4746848B9061D79882D0BEC762A6AB4E79F4DC8A503E` |
| `learner_assessment_attempt_metadata` | 42 / 42 | `7E3323CAF3000CD5BCEBED2EFBC6C219292C4B016D691BF8A58720017FCDEF77` |
| `learner_assessment_question_versions` | 366 / 366 | `5EA458647BA9B5D00E54229C95B79F6290DFE501E32FC2D4B9E156BA5690DE16` |
| `learner_assessment_versions` | 12 / 12 | `896F7C344E5A14A9BCE54DCD2AAA7534E0100654AC778759E697FC235AFF708D` |
| `learner_forward_migrations` | 3 / 3 | `EDAA6EDA1A5852CB27E8E9D7D4E12354777725EF0417CBC298880F2D6A548A13` |
| `learner_recommendation_audit_events` | 8 / 8 | `6F786C74AA9671F9972D09B78AF81B7AF1E5359F145F36C90445CAA0920DF0E9` |
| `learner_recommendation_evidence` | 24 / 24 | `3F2E6D1D891794F7BC15162FF63C385FB948DF18996573B0F2B5248A4AAFFF5D` |
| `learner_recommendation_feedback` | 0 / 0 | `C41819F4BAF92A6BAFD4BC44AE1E00EEFFEE05AF62E60DA6FA2E8390C56A50E9` |
| `learner_recommendation_input_snapshots` | 2 / 2 | `85ACA201E006AB7ADBE5048D694CACEB9275B810560BDBAE298260B270F75BBA` |
| `learner_recommendation_items` | 13 / 13 | `1D065C0926760A9924FE3445BCDF5EED7381EAC13A86B30D1FC79775BDCD353B` |
| `learner_recommendation_runs` | 4 / 4 | `1BBAEEF6741C50848C432DD9F3831D6AAA5F11C5BBC3BABBEDC119D4CFA7FDFB` |
| `learner_recommendation_snapshot_evidence` | 35 / 35 | `378FD97694C8636FAD34674B65FC886A0770E379278EE38835C23A554DDF95FE` |
| `learner_skill_evidence` | 77 / 77 | `5EDED2F722EB3364E5FBD3D0183A8732BACD71F882A1D6EAFEB0ED9EB91E3C77` |
| `permissions` | 101 / 101 | `A371BB32B43AC86905F7D0703ADBB82D746F8A4C1A441B177A41FD344212FC7E` |
| `privacy_consents` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `project_members` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `projects` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `reports` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `role_permissions` | 119 / 119 | `87D50486A6DDA5513383B50BDC8613C9E47D4B067B34EC046F103D5CAB523959` |
| `roles` | 4 / 4 | `C09EFEFBF68D760E9EAD64B32CDDA3ED1BEBB4A0BFB9CF3E5CA051FE77766627` |
| `school_members` | 3 / 3 | `CA2AD5A05BA57E1E83423DE62269BF7D9B41A5F9EB92EEB7C00AC221A5057C59` |
| `schools` | 3 / 3 | `5B487FCAB1572DCBF8D76D2D0C76F6CF05D27FBAC8AE516BC67817BC5E5820DA` |
| `skills` | 10 / 10 | `2D8B803F83152068F20454F43D33670D3BBC6FB2D024EE84B599E9C5766761A4` |
| `student_profile_details` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `student_profile_shares` | 0 / 0 | `E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855` |
| `student_profiles` | 20 / 20 | `C367C061E241EB18A3CCF5158A692D26EC3AF3781B813E8DCA34B237BE6CA91D` |
| `student_skills` | 77 / 77 | `03CF89B3C7EB4D5C4345EA652B2B384F5E59953D01A754A62DD789DF9A3B0AF4` |
| `talent_tests` | 12 / 12 | `209B68C07C8AAB0C1AC6220894C5588790064406DF82A6BB3BF1C7F4762A79F7` |
| `teacher_profiles` | 11 / 11 | `254B6A1083039065F2EFA42DBAFF7A11F804A506D82AEB9FF566E8E63005D00E` |
| `test_attempts` | 42 / 42 | `E3E838F0355A8C849104EB9B4741C01ECE08660DE12B6A8B0B75DA8F7278B5A4` |
| `test_questions` | 366 / 366 | `EBB016B777ABE4E7B6AA821CDD7B4B5717E190728D53362D238B138FF888A95E` |
| `test_results` | 42 / 42 | `C96F8F439F11F44B2D6FF4D58150CE3974C6A3C10D492B55A7F4030C4277CC51` |
| `users` | 35 / 35 | `28A367CE813A5E9BA4E5B9767BE581D89E308806D9C16C45CACB8FF244568641` |

## Rehearsal and apply result

- Rehearsal schema: `talenthub_phase3_fix_20260822102944`
- Repair apply: `[OK] 20260821000210`
- Second run: `[OK] no changes`
- Rehearsal validation: `[OK] validation: OK`
- Rehearsal non-owned normalized data hash: `15FC40F5B94C718CE2DB05F59DAAF0EF055E1EFD663FDDD9B1B5D013167EF756`
- Primary apply: `[OK] 20260821000210`
- Post-review precursor apply: `[OK] 20260821000205`
- Strict canonical precursor apply: `[OK] 20260821000204`
- Exact metadata precursor apply: `[OK] 20260821000206`
- Fresh integration ordering: `[OK] 00204 -> 00205 -> 00206 -> 00210`; `phase_3_mysql_integration_test: OK`
- Primary post-state: 21 applied, 0 pending, validation OK
- Live project capability: `available`

## Forward recovery

If a later defect is discovered:

1. Stop Phase 3 writes and preserve the current primary database.
2. Restore the verified backup into a newly named disposable schema, never directly over `talenthub_local`.
3. Reproduce and diagnose the defect on the clone.
4. Add a new forward corrective migration with a new version.
5. Rehearse, compare counts/hashes, back up primary again, and apply through the canonical migration runner.

No destructive automatic rollback is authorized.
