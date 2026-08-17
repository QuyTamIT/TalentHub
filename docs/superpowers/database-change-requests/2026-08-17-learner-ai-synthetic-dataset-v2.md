# Database Change Request: Synthetic Learner AI Dataset V2

**Status:** PROPOSED — DISPOSABLE SCHEMA ONLY

## Scope, safety boundary, and ownership

This Database Change Request (DCR) requests authorization to seed the deterministic, insert-only Synthetic Learner AI Dataset V2 into the approved disposable verification database:

- **Authorized Target Schema:** `talenthub_ai_backup_verify_004_20260816`
- **Shared / Production Schemas:** Strictly forbidden. `talenthub_local` is never approved and the seeder must immediately reject any connection to it or to any non-matching schema name.
- **Dataset Fingerprint (SHA-256):** `c6e417b69a06b9bf93a5762b03850b90c79fd88b716a1b3de48cb5097cf75b6f`
- **Total Declared V2 Rows:** `1116`

Every V2 record uses the reserved UUID prefix `00000000-0000-4000-8000-`, `.example` email domains, non-login placeholder password hashes (`!synthetic-disabled-login-v2!`), and fictional names and phone numbers. No real personal data, production records, live credentials, raw QR tokens, or real AI provider calls are involved.

The writes are strictly insert-only and transactional. The seeder contains no cleanup logic and must never execute `UPDATE`, `DELETE`, `REPLACE`, `DROP`, `TRUNCATE`, `ALTER`, or `ON DUPLICATE KEY UPDATE`.

## Prerequisite Canonical Migrations

The target disposable database must have recorded and verified the following canonical learner forward migrations:

| Version | Canonical Checksum (SHA-256) | Purpose |
|---|---|---|
| `002_create_ai_input_foundation` | `f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc` | skills, assessment foundation, checkins, experience logs |
| `003_create_ai_input_extensions` | `6b2c5674e4da5d98bc7540881f90ce5fab421d2cf52e41b7899f51a87d563c38` | versioned assessments, answers, evidence, append-only consent |
| `004_create_recommendation_store` | `48d7eaf7122cae13d5dbcb1dbaa2e157c34f2f4cea8f0c430914f193be48f0be` | immutable recommendation snapshots, runs, items, evidence |

## V1 Provenance Preservation & Historical Backfill Policy

The V1 pilot dataset (61 rows declared by `LearnerAiPilotSeeder`) is preserved unchanged in the disposable schema. V2 builds upon V1 parent entities (roles, school, class, teacher user/profile, Holland test, R1/I1/A1 questions, IoT/Python skills, activity `...000030`, presentation criterion) and adds version 2.0.0 assessment, 22 new learners (103–124), 21 new questions, 10 new skills, 11 new activities, and new versioned evidence for all 24 learners.

### Learner 112: Historical Synthetic Backfill
Learner 112 models an assessment submitted over 365 days ago to exercise the DataQualityGate stale assessment rule.
- `test_attempts.startedAt`: `2024-01-15 08:30:00.000000`
- `test_attempts.submittedAt`: `2024-01-15 09:00:00.000000`
- `learner_assessment_answers.answeredAt`: 24 timestamps from `2024-01-15 08:31:30.000000` to `2024-01-15 08:43:00.000000` (all satisfying `startedAt <= answeredAt <= submittedAt`)
- `createdAt` values are set to `2026-08-16 00:00:00.000000` to represent historical data backfilled during the 2026 dataset setup.

## Deterministic Timestamp Rules

All timestamps are generated using deterministic UTC `DateTimeImmutable` base objects:
- Timestamps follow strict MySQL `DATETIME(6)` formatting `YYYY-MM-DD HH:MM:SS.uuuuuu`.
- Seconds are strictly in the range `00..59` with zero second-overflow.
- Consent event timestamps are generated from a UTC base `2026-08-08 09:00:00.000000` with second offsets (`modify('+N seconds')`).
- Learner 120's evaluation revoke event occurs at `2026-08-08 10:00:00.000000` (+3600s), strictly after its evaluation grant.
- Assessment answers adhere to chronological ordering `attempt.startedAt <= answer.answeredAt <= attempt.submittedAt`.

## Participant Matrix (24 Learners)

Participant UUIDs use the reserved prefix `00000000-0000-4000-8000-` and sequence suffixes `000000000101` through `000000000124`.

| Archetype | Learners | State | Scenario Description |
|---|---|---|---|
| **R — Realistic** | 101, 102, 103 | `ready` | Complete evidence chain (IoT, Prototyping, Python; activity attended & confirmed) |
| **R — Realistic** | 104 | `insufficient_data` | Exactly 1 active skill (IoT); fails data quality gate (`['skills']`) |
| **I — Investigative** | 105, 106, 107 | `ready` | Complete evidence chain (IoT, Python, Data Analysis; activity attended & confirmed) |
| **I — Investigative** | 108 | `insufficient_data` | Registered for activity but lacks check-in and experience log (`['experience']`) |
| **A — Artistic** | 109, 110, 111 | `ready` | Complete evidence chain (IoT, Visual Design, Storytelling; activity attended & confirmed) |
| **A — Artistic** | 112 | `insufficient_data` | Stale assessment from `2024-01-15` (> 365 days old); fails quality gate (`['assessment']`) |
| **S — Social** | 113, 114, 115 | `ready` | Complete evidence chain (IoT, Peer Mentoring, Facilitation; activity attended & confirmed) |
| **S — Social** | 116 | `insufficient_data` | Teacher evaluation is draft and unpublished; fails quality gate (`['evaluations']`) |
| **E — Enterprising** | 117, 118, 119 | `ready` | Complete evidence chain (IoT, Pitching, Initiative; activity attended & confirmed) |
| **E — Enterprising** | 120 | `consent_required` | Evaluation consent granted then revoked; requires consent (`['evaluation']`) |
| **C — Conventional** | 121, 122, 123 | `ready` | Complete evidence chain (IoT, Spreadsheet, Quality Control; activity attended & confirmed) |
| **C — Conventional** | 124 | `consent_required` | Activity consent omitted from grants; requires consent (`['activity']`) |

**Summary:** 18 `ready`, 4 `insufficient_data`, 2 `consent_required`. Exactly 4 learners per RIASEC archetype.

## Question Bank (Verbatim 24 Questions)

Assessment version `2.0.0` (`...000000001130`) under test `holland` (`...000000000060`) with scoring version `pilot-riasec-2`:

| Code | ID | Dimension | Verbatim Content |
|---|---|---|---|
| `R1` | `...000061` (V1) | R | Synthetic realistic-interest question. |
| `R2` | `...001101` | R | Tôi thích lắp ráp một mô hình từ các bộ phận có sẵn. |
| `R3` | `...001102` | R | Tôi hứng thú khi thử dụng cụ để tạo ra một sản phẩm nhỏ. |
| `R4` | `...001103` | R | Tôi muốn thực hành quy trình an toàn trong một xưởng mô phỏng. |
| `I1` | `...000062` (V1) | I | Synthetic investigative-interest question. |
| `I2` | `...001104` | I | Tôi thích đặt giả thuyết rồi kiểm tra bằng dữ liệu giả lập. |
| `I3` | `...001105` | I | Tôi muốn phân tích nguyên nhân của một kết quả bất thường. |
| `I4` | `...001106` | I | Tôi thấy hứng thú khi so sánh nhiều cách giải một vấn đề. |
| `A1` | `...000063` (V1) | A | Synthetic artistic-interest question. |
| `A2` | `...001107` | A | Tôi thích tạo bố cục hình ảnh cho một câu chuyện giả tưởng. |
| `A3` | `...001108` | A | Tôi muốn thử nhiều cách diễn đạt cho cùng một ý tưởng. |
| `A4` | `...001109` | A | Tôi hứng thú khi biến một chủ đề thành sản phẩm sáng tạo. |
| `S1` | `...001110` | S | Tôi thích hướng dẫn bạn khác hoàn thành một nhiệm vụ mới. |
| `S2` | `...001111` | S | Tôi muốn lắng nghe và giúp một nhóm thống nhất cách làm. |
| `S3` | `...001112` | S | Tôi thấy có động lực khi hỗ trợ người khác tiến bộ. |
| `S4` | `...001113` | S | Tôi hứng thú với vai trò điều phối một buổi học nhóm. |
| `E1` | `...001114` | E | Tôi thích trình bày một ý tưởng để thuyết phục nhóm thử nghiệm. |
| `E2` | `...001115` | E | Tôi muốn chủ động tổ chức nguồn lực cho một dự án nhỏ. |
| `E3` | `...001116` | E | Tôi hứng thú khi đặt mục tiêu và theo dõi tiến độ của nhóm. |
| `E4` | `...001117` | E | Tôi thích đề xuất một hướng đi khi nhóm cần quyết định. |
| `C1` | `...001118` | C | Tôi thích sắp xếp dữ liệu theo một cấu trúc rõ ràng. |
| `C2` | `...001119` | C | Tôi muốn kiểm tra chi tiết để phát hiện sai lệch trong bảng số liệu. |
| `C3` | `...001120` | C | Tôi hứng thú với việc chuẩn hóa các bước của một quy trình. |
| `C4` | `...001121` | C | Tôi thích hoàn thành công việc theo tiêu chí và thứ tự xác định. |

## Synthetic Skills Catalog (12 Skills)

- **Realistic:** `iot` (`...000050`, V1), `prototyping` (`...001001`)
- **Investigative:** `python` (`...000051`, V1), `data_analysis` (`...001002`)
- **Artistic:** `visual_design` (`...001003`), `storytelling` (`...001004`)
- **Social:** `peer_mentoring` (`...001005`), `facilitation` (`...001006`)
- **Enterprising:** `pitching` (`...001007`), `initiative` (`...001008`)
- **Conventional:** `spreadsheet` (`...001009`), `quality_control` (`...001010`)

## Synthetic Activities Catalog (12 Activities)

- **Realistic:** `Synthetic Technical Workshop` (`...000030`, V1), `Synthetic Prototype Lab` (`...001021`)
- **Investigative:** `Synthetic Data Investigation Lab` (`...001022`), `Synthetic Python Data Challenge` (`...001023`)
- **Artistic:** `Synthetic Visual Design Studio` (`...001024`), `Synthetic Digital Storytelling Studio` (`...001025`)
- **Social:** `Synthetic Peer Mentoring Circle` (`...001026`), `Synthetic Facilitation Practice` (`...001027`)
- **Enterprising:** `Synthetic Student Pitch Lab` (`...001028`), `Synthetic Initiative Sprint` (`...001029`)
- **Conventional:** `Synthetic Spreadsheet Accuracy Lab` (`...001030`), `Synthetic Quality Control Simulation` (`...001031`)

## Row Family Counts Totaling 1116

| Table | V2 Row Count | ID Range / Description |
|---|---:|---|
| `users` | 22 | `...000103` through `...000124` |
| `student_profiles` | 22 | `...000103` through `...000124` |
| `skills` | 10 | `...001001` through `...001010` |
| `student_skills` | 66 | `...200001` through `...200066` |
| `learner_skill_evidence` | 66 | `...300001` through `...300066` |
| `test_questions` | 21 | `...001101` through `...001121` |
| `learner_assessment_versions` | 1 | `...001130` (version 2.0.0) |
| `learner_assessment_question_versions` | 24 | `...001131` through `...001154` |
| `test_attempts` | 24 | `...400101` through `...400124` |
| `learner_assessment_attempt_metadata` | 24 | `...401101` through `...401124` |
| `learner_assessment_answers` | 576 | `...500001` through `...500576` (24 answers × 24 attempts) |
| `test_results` | 24 | `...600101` through `...600124` |
| `activities` | 11 | `...001021` through `...001031` |
| `activity_qr_tokens` | 11 | `...001041` through `...001051` |
| `activity_registrations` | 24 | `...700101` through `...700124` |
| `checkins` | 23 | `...701101` through `...701124` (excluding 108) |
| `experience_logs` | 23 | `...702101` through `...702124` (excluding 108) |
| `assessments` | 24 | `...800101` through `...800124` |
| `assessment_scores` | 24 | `...801101` through `...801124` |
| `learner_ai_consent_events` | 96 | `...900001` through `...900096` |
| **Total** | **1116** | |

## Data Diversity & Value Distributions

To avoid test blind spots and validate varied scoring paths, V2 incorporates diverse deterministic distributions:

- **Experience Hours:** 9 distinct values spanning `2.50`, `3.00`, `3.50`, `4.00`, `4.50`, `5.00`, `5.50`, `6.00`, `6.50`.
- **Teacher Overall Scores:** 9 distinct published values spanning `72.00` to `94.00` (Learner 116 remains `null` in draft).
- **Presentation Scores:** 6 distinct values (`55.00`, `58.00`, `62.00`, `68.00`, `75.00`, `82.00`), containing scores both below and above `60.00`. Learner 101's score is fixed at `55.00` to combine with V1 for the roadmap rule.
- All values strictly comply with database check constraints (`levelScore` 0..100, `hours` 0..24, `score` 0..100, `overallScore` 0..100).

## Execution Form & Safety Invariants

Persistence uses the following strictly parameterized insert-only form:

```sql
INSERT INTO table_name (col1, col2, ...)
SELECT :val1, :val2, ...
WHERE NOT EXISTS (
    SELECT 1 FROM table_name WHERE id = :present_id
);
```

- **Idempotency:** A second execution must report `inserted=0, existing=1116`.
- **Non-reserved row isolation:** Counts outside `00000000-0000-4000-8000-` must remain identical before and after seeding.
- **Rollback Policy:** In case of failure or constraint violation, the transaction rolls back cleanly. No row deletion or rollback data script is executed. Any future correction must be issued as a forward V3 dataset with new distinct IDs.
- **Contract Verification:** The pure contract test proves exact DATETIME(6) round-trip, foreign key/parent closure, chronology, data diversity, and security properties without database connectivity.

## Approval & Execution Log

- **Approval Status:** PROPOSED — DISPOSABLE SCHEMA ONLY
- **Approved By:** Pending user explicit approval gate
- **Approved At:** Pending
- **Execution Status:** NOT EXECUTED
