# Prompt cho Codex CLI — Thực thi phần phức tạp Phase 7

Bạn đang làm việc trong `D:\TalentHub` trên branch `feature/student`.

Nhiệm vụ: hoàn thành phần triển khai phức tạp của **Phase 7 — Enterprise
Opportunity and Application Lifecycle**, kiểm thử trên disposable database,
tạo backup, chỉ apply primary khi mọi gate đều xanh, sau đó viết báo cáo
`PHASE_7_GO_FOR_REVIEW` và dừng trước Phase 8.

## 1. Context bắt buộc phải đọc

Đọc toàn bộ theo đúng thứ tự:

1. `D:\TalentHub\docs\superpowers\plans\2026-08-21-student-portal-four-role-completion-revised.md`
2. `D:\TalentHub\docs\superpowers\specs\2026-08-22-phase-7-enterprise-application-lifecycle-design.md`
3. `D:\TalentHub\docs\superpowers\database-change-requests\2026-08-22-phase-7-enterprise-application-lifecycle.md`
4. `D:\TalentHub\docs\superpowers\readiness\2026-08-22-phase-7-basic-preflight-report.md`
5. `D:\TalentHub\docs\superpowers\handoffs\2026-08-22-phase-7-codex-cli-complex-work-handoff.md`
6. `D:\TalentHub\docs\superpowers\readiness\2026-08-22-phase-6-assessment-review-report.md`
7. `D:\TalentHub\tests\application_profile_snapshot_migration_test.php`

Các correction của Codex reviewer trong design/DCR/handoff/test là binding:

- Submission không được tự cấp consent ngầm.
- Missing/revoked consent phải tạo zero application writes.
- Nếu chưa có consent grant flow, phải là command riêng do learner xác nhận rõ,
  dùng `privacy_consent.manage_own`, hoàn tất trước khi submit được retry.
- Phase 7 không tạo notification interface, producer, no-op adapter, table, API,
  placeholder hoặc UI.
- History và snapshot dùng FK `ON DELETE RESTRICT` để chặn hard delete.
- Data preservation áp dụng cho 51 non-registry baseline tables;
  `schema_migrations` chỉ được append đúng một Phase 7 row.

Không tin mù báo cáo cũ. Audit trạng thái thật trước khi sửa.

## 2. Baseline đã được reviewer xác minh

- Branch: `feature/student`
- HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Phase 6: `APPROVED_PHASE_6`
- Primary DB: `talenthub_local`, MySQL 8.4.3
- Runtime: 52 base tables, 23 applied migrations, 0 pending
- Bốn bảng Phase 7 hiện chưa tồn tại:
  - `internship_posts`
  - `internship_applications`
  - `application_status_history`
  - `application_profile_snapshots`
- Parent facts: 1 enterprise, 1 enterprise member, 20 student profiles,
  0 privacy consents.
- Runtime RBAC đã có đủ Student/Enterprise permissions Phase 7.
- `@@session.time_zone = '+00:00'`.
- `tests/application_profile_snapshot_migration_test.php` đang expected RED vì
  migration Phase 7 chưa tồn tại; protected Phase 3–5 migration hashes đã được
  khóa trong test.
- 13 targeted baseline suites đã pass độc lập.
- Worktree rất dirty theo thiết kế vì chứa toàn bộ Phase 2–6 chưa commit.

Executable:

- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`

## 3. Quy tắc tuyệt đối

- Không làm lại Phase 0–6.
- Không revert/overwrite thay đổi hiện có ngoài Phase 7.
- Không chạy `git reset`, `git clean`, `git checkout`, `git stash`, merge, push.
- Không commit trong lượt này.
- Không sửa `.env`, `.claude/`, `.qwen/`.
- Không sửa learner migrations `001`–`004` hoặc bất kỳ applied migration nào.
- Giữ `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Không seed/reseed/xóa dữ liệu demo hiện có.
- Không in secret, raw private snapshot/CV, assessment answer hoặc provider payload.
- Dùng prepared statements cho mọi runtime value.
- Actor, `studentId`, `enterpriseId`, role ownership phải resolve từ session;
  không nhận các ID đó từ request để authorize.
- Mọi mutation có CSRF và exact permission.
- Mọi multi-table command có một transaction boundary và rollback toàn bộ.
- Không dùng `localStorage` làm production truth.
- Không bắt đầu Phase 8.
- Không đánh dấu `APPROVED_PHASE_7`; chỉ reviewer được duyệt.

Sử dụng TDD cho từng review unit: test phải fail đúng lý do, triển khai tối thiểu,
chạy lại xanh, sau đó mới chuyển unit. Không tạo test/file trùng lặp hoặc test chỉ
so khớp placeholder.

## 4. Phản hồi context ban đầu

Sau audit read-only, phản hồi ngắn:

```text
PHASE_7_COMPLEX_CONTEXT_LOADED
- Branch/HEAD:
- Dirty worktree preserved:
- Migration validate/status:
- Target tables absent/present:
- Consent/runtime permission facts:
- Expected RED contract test:
- Primary DB mutation: none
- Contradictions/blockers:
```

Sau đó tiếp tục thực hiện toàn bộ Phase 7. Không dừng ở báo cáo context hoặc một
progress checkpoint nếu không có blocker an toàn thật sự.

## 5. Review Unit 1 — Migration và behavioral rehearsal gate

### Files

- Create:
  `Database/migrations/20260821000500_create_internships_and_application_lifecycle.php`
- Strengthen nếu cần:
  `tests/application_profile_snapshot_migration_test.php`
- Create:
  `tests/phase7_rehearsal_integrity_test.php`
- Create:
  `docs/superpowers/readiness/2026-08-22-phase-7-rehearsal-report.md`

### Migration contract

Tạo đúng bốn canonical tables theo approved design:

1. `internship_posts`
2. `internship_applications`
3. `application_status_history`
4. `application_profile_snapshots`

Migration phải:

- dùng main migration framework;
- forward-only, `isReversible() === false`;
- preflight `+00:00`, required parents, target-table partial state và metadata;
- fail rõ nếu partial target schema không đúng contract, không silently accept;
- giữ UUID `CHAR(36)` conventions hiện tại;
- khóa post status: `draft`, `active`, `closed`, `cancelled`;
- khóa application status:
  `submitted`, `reviewing`, `interview`, `accepted`, `declined`, `withdrawn`;
- unique `(postId, studentId)`;
- snapshot unique theo `applicationId`;
- valid JSON, `schemaVersion`, `createdAt`, canonical consent FK;
- history/snapshot application FK `ON DELETE RESTRICT`;
- không thêm notification schema;
- không sửa/drop/update/delete dữ liệu ở 52 baseline tables.

Contract test nguồn chỉ là gate đầu. `phase7_rehearsal_integrity_test.php` phải
kiểm tra behavior thật trên MySQL disposable:

- exact columns/types/defaults/nullability/index/FK/CHECK;
- invalid statuses bị DB từ chối;
- invalid JSON bị từ chối;
- duplicate post/student bị từ chối;
- duplicate snapshot/application bị từ chối;
- application hard delete bị FK history/snapshot chặn;
- apply lần đầu thành công;
- apply lần hai là clean no-op;
- 51 non-registry baseline table counts/hashes không đổi;
- `schema_migrations` giữ nguyên 23 prior rows/checksums và append đúng Phase 7 row.

### Disposable safety

- Tạo schema duy nhất có prefix chính xác `talenthub_phase7_rehearsal_`.
- Resolve và kiểm tra absolute target/schema name trước create/drop.
- Assert target khác `talenthub_local` trước mọi mutation.
- Không nội suy tên schema chưa validate vào raw SQL.
- Helper/test sở hữu toàn bộ create/restore/apply/cleanup.
- Backup/dump dùng cho rehearsal phải ở temp path rõ ràng và có SHA-256.
- Nếu cleanup thất bại, báo exact schema; không chạy wildcard drop.

Không apply primary ở Unit 1.

## 6. Review Unit 2 — Student consent, application command và API

### Files

- Create:
  `app/learner/data/Contracts/ApplicationCommandRepository.php`
- Create:
  `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
- Create:
  `app/learner/data/Service/ApplicationCommandService.php`
- Create:
  `app/learner/api/v1/applications.php`
- Modify only if composition requires:
  `app/learner/api/LearnerApiContext.php`
- Test:
  `tests/learner_application_api_test.php`
- Add guarded MySQL/concurrency test if separate process connections are needed.

### Consent flow

Application submit never grants consent implicitly.

- Reuse an existing active consent if present.
- Active means matching learner, scope `application_profile_share`,
  `isGranted=1`, `grantedAt IS NOT NULL`, `revokedAt IS NULL`.
- If no active consent exists, submit returns a stable validation/consent error and
  inserts no application/snapshot/history.
- Because runtime currently has zero consents, provide a separate explicit
  learner-confirmed grant/renew command through the approved learner boundary.
- Consent command requires `privacy_consent.manage_own`, never accepts
  `studentId`, records canonical policy version and audit-safe timestamps.
- UI submit flow may call consent grant first only after an explicit checked user
  action; submission remains a separate request/transaction.

### Submit transaction

1. Resolve authenticated learner and permission
   `internship_application.create_own`.
2. Validate request allow-list; reject unknown fields.
3. Lock requested post; require `active` and unexpired deadline.
4. Validate truthfully supported eligibility only; do not invent eligibility data.
5. Lock/verify active consent.
6. Enforce duplicate barrier `(postId, studentId)`.
7. Build minimized snapshot from canonical passport sources using an explicit
   allow-list; no raw private/internal fields.
8. Insert `submitted` application.
9. Insert immutable one-to-one snapshot.
10. Insert initial history `NULL -> submitted`.
11. Commit; any failure rolls back all three rows.

### Withdraw/read contracts

- GET list/detail only for authenticated learner's own applications.
- Include canonical post, immutable snapshot and ordered history without leaking
  other learners.
- Withdraw locks owned application.
- Allowed only from `submitted`, `reviewing`, `interview`.
- Transition to `withdrawn`, append history, never DELETE.
- `accepted`, `declined`, `withdrawn` are terminal.

Tests must cover auth, permission, CSRF, unknown fields, expired/closed post,
duplicate, missing consent, revoked consent, explicit grant, cross-student read,
snapshot allow-list, profile edit after submit, rollback injection, illegal
withdraw and duplicate/concurrent submit.

## 7. Review Unit 3 — Enterprise post ownership và application review

### Files

- Create:
  `src/Modules/Business/Repository/InternshipRepository.php`
- Create:
  `src/Modules/Business/Service/InternshipService.php`
- Modify composition/routes:
  `src/Bootstrap/Application.php`
- Modify only related Enterprise route/controllers as required.
- Test:
  `tests/Integration/EnterpriseApplicationLifecycleTest.php`

### Required commands

Implement server-confirmed Enterprise operations already represented by existing
permissions and pages:

- list/read own posts;
- create own draft post;
- update own draft/allowed post fields;
- publish own draft to `active`;
- close own active post;
- list/read applications to own posts;
- read CV/snapshot only through own post/application ownership;
- review application with `expectedCurrentStatus`.

Enterprise service must resolve exactly one membership from authenticated
`userId -> enterprise_members.enterpriseId`. Never accept client enterprise
identity for authorization.

Review transition matrix:

- `submitted -> reviewing|declined`
- `reviewing -> interview|accepted|declined`
- `interview -> accepted|declined`
- terminal: `accepted|declined|withdrawn`

Transaction locks application joined to owned post, verifies
`expectedCurrentStatus`, updates application/reviewer metadata and appends history
atomically. Cross-enterprise resource access returns the same not-found behavior as
a nonexistent resource. No existence leak.

Tests must include cross-enterprise read/write/CV denial, illegal transition,
lost update, rollback after update before history, post ownership, publish/close
rules and malformed request fields.

## 8. Review Unit 4 — Student và Enterprise UI dùng server truth

### Student

- Modify: `app/learner/opportunity.php`
- Modify: `app/learner/ecosystem.php`
- Reuse current database read adapters and `assets/js/learner-api.js`.
- Modify shared API client only when a failing contract test proves a missing
  capability; do not create a second client.

Requirements:

- active/unexpired database posts only;
- real UUID routes;
- explicit consent checkbox/action;
- submit and withdraw show only server-confirmed state;
- application history and immutable snapshot are server-backed;
- independent loading/ready/empty/error states;
- no numeric mock ID or localStorage production state.

### Enterprise

- Modify only corresponding files under `app/enterprise/internships/` and their
  existing JS/data adapters.
- Replace mock-only post/applicant mutations with server responses one screen at
  a time.
- Applicant snapshot view uses captured snapshot, not the learner's current live
  profile.
- No arbitrary URL from browser is trusted for CV/snapshot access.

Add the smallest behavioral UI/render tests that prove these interactions. Reuse
existing render tests when practical; do not create redundant test files.

## 9. Review Unit 5 — Cross-role, concurrency và regression

Run fresh:

- Phase 7 migration contract and disposable rehearsal tests;
- learner application API/unit/MySQL/concurrency tests;
- Enterprise lifecycle integration tests;
- Student ecosystem/opportunity render/UI tests;
- Enterprise internship/applicant render/profile regressions;
- `student_portal_cross_role_contract_test.php`;
- Phase 2–6 regression suites affected by shared Application/bootstrap/UI code;
- every JavaScript test;
- every PHP file through `php -l`, failing aggregate on any non-zero exit;
- `bin/migrate.php validate` and `status`;
- `git diff --check`.

Do not count a gated MySQL/AI suite as PASS unless it actually ran against the
required validated disposable target. Report PASS, FAIL and NOT RUN separately.

## 10. Review Unit 6 — Backup và conditional primary apply

Primary apply is conditionally authorized only if all preceding gates are green
and no Critical/Important blocker remains.

Before apply:

1. Re-run primary read-only table/count/hash manifest.
2. Create a fresh full backup of `talenthub_local` in a timestamped temp path.
3. Verify backup exists, non-zero size, and record SHA-256.
4. Confirm current target is exactly `talenthub_local`.
5. Confirm migration validate is clean and only `20260821000500` is pending.
6. Confirm disposable apply-twice, behavior, concurrency and preservation gates
   are green.

Then apply only `20260821000500` through the normal migration runner.

After apply verify:

- 56 base tables;
- 24 applied migrations, 0 pending;
- migration validate OK;
- four Phase 7 tables exact metadata;
- all 51 non-registry baseline counts/hashes unchanged;
- `schema_migrations` retained all 23 prior checksums and appended one correct row;
- Phase 7 tables initially contain no fabricated production rows unless created by
  an explicitly authorized real UI interaction; tests must use disposable DB.

Run the relevant regressions again after primary apply. Do not seed primary.

If any gate fails, do not apply primary. Report `PHASE_7_NOT_READY` with exact
blocker and preserve the backup/rehearsal evidence.

## 11. Documentation và final review gate

Create:

- `docs/superpowers/readiness/2026-08-22-phase-7-rehearsal-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-7-enterprise-application-review-report.md`

Update Phase 7 section of:

- `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`

Only check items backed by fresh evidence. Leave commit checkbox open because this
run is no-commit. Do not mark Phase 8 started.

Final response must be exactly one decision:

- `PHASE_7_GO_FOR_REVIEW`, or
- `PHASE_7_NOT_READY`.

Report:

1. Files created/modified by review unit.
2. Student create/read/withdraw behavior.
3. Explicit consent behavior.
4. Enterprise post/review/ownership behavior.
5. Snapshot immutability and data minimization evidence.
6. Cross-student/cross-enterprise/concurrency/rollback evidence.
7. Tests with PASS/FAIL/NOT RUN counts.
8. Disposable rehearsal and apply-twice result.
9. Backup absolute path, size and SHA-256.
10. Primary DB before/after tables, migrations and preservation result.
11. Protected-file and AI visibility invariants.
12. Risks or unresolved blockers.
13. Explicit confirmation: no Phase 8, no commit/push/merge/reset/clean/checkout/stash.

Stop after this report so Codex reviewer can inspect the real workspace and decide
`APPROVED_PHASE_7`. Do not begin Phase 8.
