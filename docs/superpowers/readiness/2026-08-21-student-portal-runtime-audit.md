# Student Portal Runtime Audit - 2026-08-21

## Executive status
- Phase 0 gate: PASS for review after Phase 1 resolved the deterministic readiness baseline blocker.
- Runtime: PHP 8.3.30, PDO and pdo_mysql available, MySQL 8.4.3, database connection OK.
- Migration state: 15 applied, 0 pending, validation OK, no drift.
- Database mutation: none. No migration, seed, INSERT, UPDATE, DELETE, TRUNCATE, DROP, commit, push, or merge was performed.

## Accepted checkpoint reused
- Branch feature/student and HEAD bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4 were already verified.
- Runtime and migration validation were already accepted and reused per the resume override.
- Initial information_schema and status inventory were already accepted and reused.

## Runtime schema summary
- Runtime table count: 45.
- Relevant row counts: activities=26, activity_registrations=40, activity_qr_sessions=8, checkins=20, experience_logs=20, test_attempts=42, assessments=20.
- Collation for runtime tables sampled through information_schema: utf8mb4_unicode_ci.
- Planned migration IDs 20260821000100 through 20260821000700 are unclaimed and reserved in the revised plan.
- Continuation Session (2026-08-21): Resolved 5 blockers from the Codex CLI review, including deterministic readiness tests without production bypass, canonical status normalization across repository/mock layers, RBAC regression contract (103 permissions / 124 mappings), and full missing schema specifications.

## Canonical statuses observed
| Table | Runtime statuses |
|---|---|
| activities | completed=7, ongoing=5, published=14 |
| activity_registrations | approved=8, attended=20, cancelled=6, pending=6 |
| activity_qr_sessions | active=2, expired=4, revoked=2 |
| checkins | confirmed=20 |
| experience_logs | confirmed=20 |
| test_attempts | submitted=42 |
| assessments | published=20 |

## Key schema details captured
- activities: PK id; indexes idx_activities_school_start(schoolId,startAt), idx_activities_teacher_status(createdByTeacherId,status); columns id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, createdAt, updatedAt.
- activity_registrations: PK id; unique uq_activity_registrations_activity_student(activityId,studentId); index idx_activity_registrations_student_status(studentId,status); columns id, activityId, studentId, status, registeredAt, updatedAt.
- activity_qr_sessions: PK id; unique uq_activity_qr_sessions_token_hash(tokenHash); indexes activity, teacher, status/expiry; tokenHash only, no raw token column.
- checkins: PK id; unique uq_checkins_registration(registrationId); FK-style link uses qrSessionId, not legacy qrTokenId; columns id, registrationId, qrSessionId, status, checkedInAt, confirmedAt, createdAt.
- experience_logs: PK id; unique uq_experience_logs_checkin(checkinId); indexes student/status and activity.
- privacy_consents and learner_ai_consent_events exist; general profile/application consent expansion remains later-phase migration work.
- learner_recommendation_* tables exist and remain shadow/rule infrastructure only.

## Missing or semantic-equivalent tables
| Entity | Runtime | Dump/source | Classification | Resolution |
|---|---|---|---|---|
| internship_posts | absent | Database/Talenthub.sql and code consumers | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE / LEGACY_DUMP_ONLY | Planned future shared migration; do not create in Phase 0-2 |
| internship_applications | absent | Database/Talenthub.sql and learner repositories | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE / LEGACY_DUMP_ONLY | Planned future shared migration; do not create in Phase 0-2 |
| application_status_history | absent | Database/Talenthub.sql | LEGACY_DUMP_ONLY | Planned future shared migration; no duplicate |
| notifications | absent | Database/Talenthub.sql, teacher dashboard read | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE | Planned future shared migration; no fake rows |
| badges | absent | Database/Talenthub.sql, learner UI, PhaseRequirements | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE | Blocks Phase 2 completion as written |
| student_badges | absent | Database/Talenthub.sql, School analytics, PhaseRequirements | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE | Blocks Phase 2 completion as written |
| certificates/projects/project_members | absent | PhaseRequirements and UI expectations | CODE_CONSUMER_WITHOUT_RUNTIME_TABLE | Blocks Phase 2 Talent Passport completion as written |

## Reader/writer consumer map
| Table | Consumers and operations | Owner / boundary |
|---|---|---|
| activities | src/Modules/Teacher/Repository/TeacherActivityRepository.php SELECT/INSERT/UPDATE; src/Modules/Teacher/Repository/TeacherQrSessionRepository.php SELECT; src/Modules/Teacher/Repository/TeacherGradingRepository.php SELECT; app/learner/data/Database/DatabaseActivityRepository.php SELECT | TeacherActivityService owns lifecycle; Student reads catalog; School scoped reads |
| activity_registrations | TeacherActivityRepository SELECT; TeacherGradingRepository SELECT; learner activity read repository SELECT; demo seed/verifier tests read/write in disposable/demo contexts | Student registration lifecycle planned; Teacher managed transition must share repository primitive |
| activity_qr_sessions | TeacherQrSessionRepository SELECT/INSERT/UPDATE; QR tests and demo verifier SELECT; future learner check-in validates opaque token | TeacherQrSessionService owns QR session lifecycle |
| checkins | Teacher dashboard includes SELECT; demo verifier/tests SELECT; AI ActivityExperienceSource SELECT | LearnerCheckinService planned owner for Student check-in transaction |
| experience_logs | Teacher dashboard includes SELECT; SchoolDashboardService aggregate SELECT; AI ActivityExperienceSource SELECT; demo verifier/tests SELECT | Confirmed experience facts must come from check-in transaction/policy |
| assessments/assessment_scores | TeacherGradingRepository SELECT/INSERT/UPDATE; learner assessment repository SELECT published results | TeacherGradingService owns Teacher evaluations; separate from automated results |
| test_attempts/test_results/learner_assessment_* | app/learner assessment APIs/services SELECT/INSERT/UPDATE under existing tests | LearnerAssessmentService owns automated assessment lifecycle |
| student_profiles | StudentRepository SELECT/UPDATE; SchoolRepository SELECT/INSERT/UPDATE scoped; TeacherStudentRepository reads | StudentProfileService owns own profile; School owns scoped admin updates |
| internship_* | app/learner DatabaseApplicationRepository and DatabaseEcosystemRepository SELECT but runtime tables absent; enterprise mock includes document intended shape | Planned Business/Student application services later |
| notifications | Teacher dashboard SELECT but runtime absent; learner header/UI placeholder | Planned NotificationService later |
| badges/student_badges | SchoolDashboardService SELECT and learner UI static data but runtime absent | Planned BadgeAwardService later |
| learner_recommendation_* | app/learner/ai Persistence/Service SELECT/INSERT; recommendation APIs read/generate; tests cover isolation/fallback | Existing recommendation service; AI visible percent remains 0 |

## Four-source reconciliation
- Runtime plus applied migrations are authoritative.
- Database/Talenthub.sql is legacy/reference for absent internship, notification, badge, and student_badge tables.
- PhaseRequirements.php currently expects Phase 2 tables that runtime lacks; this makes Phase 2 SKIP unless the plan is later amended to define honest empty states without requiring those tables or migrations are approved later.
- Revised plan was amended with runtime evidence; no migration file was created.

## Baseline tests
| Command | Exit | Result |
|---|---:|---|
| tests/learner_readiness_test.php | 0 | PASS (deterministic fixture, Windows clean deletion, 0 temp leaks) |
| tests/learner_phase_requirements_test.php | 0 | PASS |
| tests/learner_shared_readiness_test.php | 0 | PASS |
| tests/permission_service_driver_compatibility_test.php | 0 | PASS |
| tests/learner_data_foundation_test.php | 0 | PASS |
| tests/student_portal_cross_role_contract_test.php | 0 | PASS (103 permissions, 7 single-purpose planned IDs) |
| node --test tests/learner_activities_ui_test.js | 0 | PASS (8 UI/DOM tests with canonical boot data) |
| node --test tests/learner_api_client_test.js | 0 | PASS (12 tests) |
| tests/qr_session_migration_contract_test.php | 0 | PASS (103 permissions / 124 mappings, createdAt restored) |
| tests/learner_assessment_api_test.php | 0 | PASS |
| tests/learner_recommendation_api_test.php | 0 | PASS |
| tests/learner_activities_data_test.php | 0 | PASS |
| tests/learner_database_render_test.php | 0 | PASS |

## Clone/rehearsal feasibility
- MySQL client and server paths are known and connectivity is available.
- Runtime can be cloned later, but Phase 0-2 did not clone or rehearse to avoid unauthorized primary mutation.
- Future migrations require DCR, backup, disposable-schema rehearsal, apply-twice validation, and postflight verification.

## Protected migration hashes
- 001_migration_registry.sql: 6382F12F89BEC03D232957C3914FD8EC736332381BEB82614F728843A7C417EE
- 002_create_ai_input_foundation.php: F218EC8E2A2A730197DD07F4F00F4EC5B14B5651F5B41B6E2307C4A87619B970
- 003_create_ai_input_extensions.php: 07A5AE89B21433E893C54E168A06B464CB8E6092108DD31CBB18A3999A7EC75B
- 004_create_recommendation_store.php: 83F684C68827CA8C1623668552DF5B44663421F5714165FCB077F2496C56B287
