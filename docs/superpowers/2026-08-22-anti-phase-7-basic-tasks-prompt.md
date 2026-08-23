# Prompt cho Anti — Phase 7 Basic Work Package

Bạn đang làm việc trong workspace `D:\TalentHub` bằng model **3.7 Flash**.
Mục tiêu của lượt này là hoàn thành **gói công việc cơ bản/preflight của Phase 7**
nhanh, chính xác và tạo handoff đủ tốt để Codex reviewer kiểm tra trước khi giao
phần triển khai phức tạp cho Codex CLI.

## 1. Trạng thái được phép tin cậy

- Workspace: `D:\TalentHub`
- Branch bắt buộc: `feature/student`
- Baseline HEAD hiện tại: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Phase 0–5: đã duyệt.
- Phase 6: `APPROVED_PHASE_6`.
- Phase 7: đủ điều kiện bắt đầu nhưng **chưa triển khai**.
- Primary database: `talenthub_local`, MySQL 8.4.3.
- Migration hiện tại: 23 applied, 0 pending, validation OK.
- Worktree dirty theo thiết kế vì chứa thay đổi Phase 2–6 chưa commit.
- AI visibility phải giữ `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL CLI: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`

## 2. Đọc context bắt buộc

Đọc đầy đủ theo đúng thứ tự trước khi hành động:

1. `D:\TalentHub\docs\superpowers\plans\2026-08-21-student-portal-four-role-completion-revised.md`
2. `D:\TalentHub\docs\superpowers\readiness\2026-08-22-phase-6-assessment-review-report.md`
3. Các design/DCR/readiness hiện có liên quan Phase 3, consent, Enterprise và application.

Chỉ đọc file liên quan. Không dump toàn bộ file lớn ra chat. Dùng tìm kiếm theo
pattern và đọc đoạn cần thiết để tiết kiệm context.

Sau audit ban đầu, phản hồi ngắn đúng mẫu:

```text
PHASE_7_BASIC_CONTEXT_LOADED
- Branch/HEAD:
- Worktree preservation:
- Migration validate/status:
- Runtime opportunity/application tables:
- Existing canonical consumers:
- Primary DB mutation: none
- Contradictions/blockers:
```

Sau đó **tiếp tục làm việc**, không dừng chỉ để chờ xác nhận nếu không có blocker
nguy hiểm thật sự.

## 3. Ranh giới gói công việc của Anti

Anti chỉ thực hiện các việc cơ bản sau:

1. Audit read-only source code và runtime schema.
2. Lập consumer/ownership/status/permission map của Phase 7.
3. Viết design Phase 7 và Database Change Request ở trạng thái draft-for-review.
4. Viết số lượng tối thiểu test contract/preflight có giá trị để khóa yêu cầu.
5. Viết handoff chi tiết cho Codex CLI thực hiện phần phức tạp sau khi Codex
   reviewer duyệt.
6. Chạy các baseline verification không mutation và báo cáo trung thực.

Anti **không được** làm trong lượt này:

- Không tạo hoặc triển khai production migration
  `20260821000500_create_internships_and_application_lifecycle.php`.
- Không apply/revert migration trên `talenthub_local`.
- Không tạo/xóa/sửa dữ liệu thật, không seed hoặc rehearsal database.
- Không triển khai transaction create/withdraw/review application.
- Không sửa production API, repository, service hoặc UI Phase 7.
- Không tạo notification table/service/API; notification thuộc Phase 8.
- Không bắt đầu Phase 8.
- Không commit, push, merge, reset, clean, checkout hoặc stash.
- Không recreate file từ trí nhớ và không ghi đè file dirty ngoài phạm vi.

Nếu phát hiện production source hiện tại đã có implementation Phase 7, chỉ audit,
ghi provenance và compatibility; không tự ý thay thế.

## 4. Task artifact bắt buộc

Ngay khi bắt đầu, tạo một task artifact/checklist của Antigravity và cập nhật sau
mỗi bước. Checklist tối thiểu:

- [ ] Verify branch/HEAD/protected paths
- [ ] Validate migration baseline
- [ ] Audit runtime schema read-only
- [ ] Audit source consumers and role ownership
- [ ] Lock status machine, consent and permission contracts
- [ ] Write Phase 7 design
- [ ] Write Phase 7 DCR draft
- [ ] Add minimal contract/preflight tests
- [ ] Run non-mutating baseline verification
- [ ] Write Codex CLI complex-work handoff
- [ ] Write Anti basic-work review report

## 5. Task A — Read-only runtime/schema preflight

Không giả định bảng tồn tại hoặc không tồn tại. Xác minh bằng
`information_schema` và SELECT read-only:

- `internship_posts`
- `internship_applications`
- `application_status_history`
- `application_profile_snapshots`
- `enterprise_members`
- `student_profiles`
- `privacy_consents`
- bảng consent event/canonical share hiện có nếu có

Với mỗi bảng liên quan, ghi:

- table tồn tại hay không;
- columns, types, nullability, defaults;
- primary/unique/index/FK/CHECK constraints;
- status values đang tồn tại;
- số row;
- duplicate `(postId, studentId)`;
- orphan post/application/student/enterprise;
- deadline/status incompatibility;
- liệu đã có status history hoặc snapshot tương đương hay chưa.

Chỉ SELECT. Không tạo temporary table trong primary database. Không ghi raw CV,
snapshot JSON, consent payload hoặc dữ liệu riêng tư vào report.

Tính và ghi hash/row-count baseline cần thiết để Codex CLI có thể chứng minh
rehearsal/apply sau này không làm đổi application hiện có.

## 6. Task B — Source consumer và ownership map

Audit có mục tiêu các vùng sau:

- `app/learner/opportunity.php`
- `app/learner/ecosystem.php`
- `app/learner/data/Contracts/ApplicationRepository.php`
- `app/learner/data/Database/DatabaseApplicationRepository.php`
- `app/learner/data/Mock/MockApplicationRepository.php`
- `app/learner/data/ReadModel/ApplicationReadModel.php`
- `app/enterprise/includes/internships-data.php`
- `app/enterprise/internships/`
- `src/Modules/Business/`
- `src/Bootstrap/Application.php`
- `Database/seeds/System/RolePermissionSeeder.php`

Lập map rõ ràng:

- Student đọc post nào và bằng permission nào.
- Student create/read/withdraw application bằng exact permission nào.
- Enterprise identity phải resolve từ session → `enterprise_members`, tuyệt đối
  không nhận `enterpriseId` từ browser.
- Enterprise chỉ đọc/review application thuộc post của chính enterprise.
- CV/snapshot read phải đi qua application/post ownership; không nhận arbitrary
  URL từ request.
- School và Teacher không có application mutation trong Phase 7.
- Một canonical row/state/history phải được cả Student và Enterprise cùng nhìn.

Reuse permission hiện có, không tạo permission mới nếu chưa có bằng chứng bắt buộc:

- Student: `internship_post.read_available`,
  `internship_application.create_own`,
  `internship_application.read_own`,
  `internship_application.withdraw_own`.
- Enterprise: `internship_post.read_own_business`,
  `internship_post.create_own_business`,
  `internship_post.update_own_business`,
  `internship_post.publish_own_business`,
  `internship_post.close_own_business`,
  `internship_application.read_own_business`,
  `internship_application.review_own_business`,
  `internship_application.read_cv_own_business`, `talent.read_consented`.

## 7. Task C — Khóa contract Phase 7

Thiết kế phải khóa tối thiểu các contract sau để Codex CLI không tự suy diễn:

### 7.1 Canonical statuses

- Post: dùng runtime values nếu consumer hiện tại chưa tương thích với target.
- Target chỉ khi preflight chứng minh tương thích:
  `draft`, `active`, `closed`, `cancelled`.
- Application target:
  `submitted`, `reviewing`, `interview`, `accepted`, `declined`, `withdrawn`.
- Ghi state-transition matrix cụ thể, terminal states và status nào Student được
  withdraw. Không silently normalize legacy statuses.

### 7.2 Student create transaction

Thiết kế transaction phải yêu cầu:

1. Resolve learner từ authenticated session.
2. Lock post hợp lệ/active.
3. Kiểm tra deadline và eligibility có nguồn thật.
4. Kiểm tra active application-profile consent.
5. Chặn duplicate `(postId, studentId)` bằng cả service check và unique barrier.
6. Tạo minimized, allow-listed passport snapshot.
7. Insert application ở `submitted`.
8. Insert immutable one-to-one snapshot.
9. Insert initial status history.
10. Commit hoặc rollback toàn bộ.

Không đưa notification vào transaction Phase 7 vì Phase 8 chưa tồn tại. Chỉ định
nghĩa domain event/producer contract cần nối trong Phase 8; không tạo placeholder
production.

### 7.3 Student withdraw transaction

- Lock owned application.
- Chỉ cho withdraw từ pre-terminal states đã khóa trong matrix.
- Transition sang `withdrawn`.
- Append status history.
- Không DELETE application hoặc snapshot.
- Rollback toàn bộ khi history insert thất bại.

### 7.4 Enterprise review transaction

- Resolve enterprise membership từ session.
- Lock application joined với post do enterprise đó sở hữu.
- Enforce expected-current-status để chống lost update.
- Chỉ cho transition nằm trong matrix.
- Update application và append history trong một transaction.
- Cross-enterprise luôn 403/not found theo pattern hiện có, không leak existence.

### 7.5 Snapshot schema

Thiết kế target `application_profile_snapshots`:

- one-to-one theo `applicationId`;
- liên kết consent canonical đã xác minh ở preflight;
- minimized JSON allow-list, JSON hợp lệ;
- `schemaVersion` và `createdAt`;
- immutable sau insert;
- profile edit/revoke consent sau khi nộp không sửa snapshot cũ;
- không lưu secret, assessment answer, raw provider payload hoặc field không cần
  cho tuyển dụng.

## 8. Task D — Tài liệu phải tạo

Tạo đúng các file sau:

1. `docs/superpowers/specs/2026-08-22-phase-7-enterprise-application-lifecycle-design.md`
2. `docs/superpowers/database-change-requests/2026-08-22-phase-7-enterprise-application-lifecycle.md`
3. `docs/superpowers/readiness/2026-08-22-phase-7-basic-preflight-report.md`
4. `docs/superpowers/handoffs/2026-08-22-phase-7-codex-cli-complex-work-handoff.md`

DCR phải ghi rõ:

- current runtime facts, target delta và lý do;
- migration additive/reconciliation strategy;
- no-edit applied migrations;
- disposable rehearsal plan;
- apply-twice/idempotency plan;
- backup path/hash verification plan;
- preservation queries/hashes;
- rollback/recovery procedure;
- primary apply vẫn `NOT_AUTHORIZED` trong lượt Anti.

Handoff Codex CLI phải chia phần phức tạp thành review units:

1. Migration + disposable rehearsal + DCR gate.
2. Student application command transaction/API.
3. Enterprise ownership/review transaction/routes.
4. Student + Enterprise server-confirmed UI integration.
5. Full cross-role/concurrency/regression verification.
6. Backup, approved primary apply nếu và chỉ nếu reviewer cấp quyền.
7. Phase 7 final review report.

Mỗi unit phải ghi exact files, interfaces, invariants, tests và lệnh verify; không
dùng `TBD`, `TODO`, “test appropriately” hoặc mô tả mơ hồ.

## 9. Task E — Test tối thiểu, không tạo file thừa

Chỉ được tạo/sửa test nếu nó khóa trực tiếp contract Phase 7 và có thể tái sử dụng
cho Codex CLI. Không nhân bản các regression đã có.

File test được phép tạo trong gói Anti:

- `tests/application_profile_snapshot_migration_test.php`
- một file source/contract preflight duy nhất nếu thật sự cần; phải giải thích vì
  sao test hiện có không bao phủ được.

Test migration phải khóa tối thiểu:

- migration ID/path đúng;
- không sửa migration đã applied;
- snapshot unique/one-to-one với application;
- consent reference đúng canonical source;
- JSON validity/schema version/timestamp;
- additive behavior khi bảng legacy đã tồn tại;
- không duplicate `application_status_history`;
- không làm thay đổi hash của application hiện có.

Nếu production migration chưa được tạo thì test mới có thể ở trạng thái expected
RED. Báo rõ expected RED vì implementation chưa được giao, không sửa test cho xanh
bằng placeholder hoặc fake assertion.

Chạy lại các baseline tests liên quan Enterprise render/profile/applicant và
Student ecosystem/application reads để phát hiện baseline blocker. Không chạy suite
có khả năng tạo database nếu chưa xác minh disposable guard.

## 10. Cách dùng model Flash

- Batch các read-only query và source search độc lập.
- Không đọc/dump toàn bộ `tests/` hoặc file lớn nếu chỉ cần vài pattern.
- Tóm tắt evidence theo bảng ngắn thay vì chép raw source/output.
- Nếu dùng subagent, chỉ dùng cho research read-only độc lập; không cho nhiều agent
  sửa cùng file.
- Giữ task artifact cập nhật để context overflow không làm mất tiến độ.
- Không hỏi từng câu. Nếu có product decision thật sự chặn thiết kế, gom toàn bộ
  câu hỏi còn lại thành **một** mục `BLOCKED_PRODUCT_DECISIONS` trong báo cáo.

## 11. Verification bắt buộc trước khi bàn giao

Chạy lại tối thiểu:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php validate
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php status
git diff --check
git branch --show-current
git rev-parse HEAD
```

Ngoài ra:

- PHP lint mọi file Anti tạo/sửa.
- Chạy targeted baseline tests đã xác định trong audit.
- Xác nhận protected paths không có diff mới.
- Xác nhận primary row counts/hashes không đổi.
- Phân biệt rõ PASS, expected RED và NOT RUN; không gom tất cả thành PASS.

## 12. Review gate và mẫu báo cáo cuối

Khi hoàn tất, dừng và trả đúng một trong hai decision:

- `PHASE_7_BASIC_READY_FOR_CODEX_REVIEW`
- `PHASE_7_BASIC_NOT_READY`

Báo cáo cuối phải ngắn nhưng đủ bằng chứng:

```text
PHASE_7_BASIC_READY_FOR_CODEX_REVIEW

1. Branch/HEAD/worktree invariants
2. Runtime schema facts
3. Existing source/consumer/permission map
4. Locked state-machine and transaction contracts
5. Files created/modified
6. Tests/checks: PASS / expected RED / NOT RUN
7. Primary database state and preservation evidence
8. Risks and BLOCKED_PRODUCT_DECISIONS, nếu có
9. Exact Codex CLI complex-work handoff path
10. Explicit confirmation:
   - no production migration implemented/applied
   - no production API/service/UI implemented
   - no primary DB mutation
   - no Phase 8 work
   - no commit/push/merge/reset/clean/checkout/stash
```

Không được ghi `APPROVED_PHASE_7`, `PHASE_7_COMPLETE` hoặc đề nghị apply primary.
Chỉ Codex reviewer sau khi kiểm tra báo cáo và workspace mới quyết định có giao gói
phức tạp cho Codex CLI hay không.
