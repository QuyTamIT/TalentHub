# Prompt bàn giao ngữ cảnh TalentHub cho Anti — sau Phase 4

> Sao chép toàn bộ nội dung file này làm ngữ cảnh đầu vào cho Anti. Đây là checkpoint chính thức sau khi Phase 4 được triển khai, migration, kiểm thử và review độc lập.

## Vai trò của bạn

Bạn là Anti, tiếp nhận dự án TalentHub tại checkpoint sau Phase 4. Trước khi đề xuất hoặc sửa bất kỳ thứ gì, hãy đọc đầy đủ file này và các báo cáo được dẫn chiếu. Không làm lại Phase 0–4, không suy đoán trạng thái chỉ từ checklist cũ, và không bắt đầu phase mới nếu người dùng chưa yêu cầu rõ ràng.

Mục tiêu của bạn là tiếp tục công việc từ trạng thái thật hiện tại, giữ nguyên dữ liệu và luồng tương tác giữa bốn vai trò: Student, Teacher, School và Enterprise.

## Workspace và runtime bắt buộc

- Working directory: `D:\TalentHub`
- Branch: `feature/student`
- Baseline HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Worktree đang dirty vì chứa toàn bộ thay đổi Phase 2–4 chưa commit. Không xóa, reset, clean, checkout hoặc ghi đè các thay đổi này.
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- mysqldump: cùng thư mục với MySQL executable.
- Database chính: `talenthub_local`, MySQL `8.4.3`.
- Trạng thái migration hiện tại: `22 applied`, `0 pending`, validation OK.
- Runtime schema hiện có `51` bảng.

## Những điều tuyệt đối không được làm

- Không commit, push, merge, reset, clean hoặc stash nếu người dùng chưa cho phép riêng.
- Không sửa `.env`, `.claude/`, `.qwen/`.
- Không sửa `Database/migrations/learner/001`–`004`.
- Không sửa bất kỳ migration chính nào đã apply, đặc biệt:
  - `20260821000100_create_student_passport_sharing.php`
  - `20260821000200_create_student_certificates_and_projects.php`
  - `20260821000204_validate_phase_3_canonical_contracts.php`
  - `20260821000205_preflight_phase_3_reconciliation.php`
  - `20260821000206_validate_phase_3_exact_metadata.php`
  - `20260821000210_reconcile_phase_3_contracts.php`
  - `20260821000300_extend_activity_registration_lifecycle.php`
- Không chạy seed, migrate, INSERT/UPDATE/DELETE hoặc tạo/xóa database nếu task hiện tại chưa cho phép database mutation.
- Không đưa AI model ra hiển thị trực tiếp cho người học. `TALENTHUB_AI_VISIBLE_PERCENT` phải giữ bằng `0`.
- Không tạo notification/badge/opportunity table sớm hơn roadmap.
- Không dùng trạng thái registration để giả lập check-in hoặc giờ trải nghiệm.

## Nguồn sự thật và thứ tự ưu tiên

Khi tài liệu cũ hoặc tracker cũ mâu thuẫn với báo cáo review mới hơn, dùng thứ tự sau:

1. Runtime schema và kết quả `bin/migrate.php validate/status` hiện tại.
2. Báo cáo review gần nhất của từng phase.
3. Design/spec và implementation plan của phase.
4. Program Tracker cũ trong roadmap; một số nhãn Phase 0–3 trong bảng tổng quan có thể chưa được cập nhật đồng bộ.

Đọc các file sau trước khi làm việc:

- `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`
- `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`
- `docs/superpowers/readiness/2026-08-21-phase-0-1-conditional-2-review-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-2-talent-passport-review-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-3-rehearsal-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-4-activity-registration-review-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-4-rehearsal-report.md`
- `docs/superpowers/database-change-requests/2026-08-22-phase-4-activity-registration.md`
- `docs/superpowers/specs/2026-08-22-phase-4-activity-registration-design.md`
- `docs/superpowers/plans/2026-08-22-phase-4-activity-registration-implementation.md`

## Tiến độ chính thức

### Phase 0 — Runtime/schema/consumer audit: hoàn thành

- Branch, HEAD, scope, PHP/MySQL runtime và database connection đã được xác minh.
- Schema inventory, migration registry, status inventory và cross-role consumer map đã được lập.
- Không còn blocker runtime ban đầu.

### Phase 1 — Architecture contracts/RBAC/readiness: hoàn thành

- Readiness và contract test cho Student Portal/bốn vai trò đã được khóa.
- Permission compatibility và canonical status contracts đã được kiểm tra.
- Các blocker review Phase 0/1 đã được sửa trước khi tiếp tục.

### Phase 2 — Talent Passport truthful read model: hoàn thành

- Dashboard/Profile database mode đọc dữ liệu thật.
- Talent Passport aggregate dùng dữ liệu canonical hiện có.
- Không bịa certificate/project/badge khi schema chưa sẵn sàng; phần chưa có trả empty state trung thực.
- Các blocker review Phase 2 đã được xử lý.

### Phase 3 — Profile ownership, evidence, consent và sharing: hoàn thành

- Profile ownership và input allow-list được thực thi phía server.
- Certificates, projects, project members và profile sharing/consent đã có schema và luồng thật.
- Sharing có consent, expiry/revocation và không lộ token thô.
- Các migration Phase 3 đã rehearsal và apply; không được chỉnh sửa lại.
- Báo cáo Phase 3 đã được review và duyệt.

### Phase 4 — Activity registration, approval và waitlist: hoàn thành

Decision chính thức: `APPROVED_PHASE_4`.

Đã có:

- Student đăng ký/hủy hoạt động bằng API và DB thật.
- Session-derived Student ownership; client không được gửi `studentId`.
- CSRF, exact permission, allow-list, transaction và audit log atomic.
- Trạng thái canonical: `pending`, `approved`, `rejected`, `cancelled`, `attended`, `waitlisted`.
- Capacity chỉ đếm `approved|attended`.
- Automatic approval, Teacher review và waitlist hoạt động thật.
- Hủy đăng ký kiểm tra deadline và chỉ promote waitlist khi capacity hiện tại thật sự còn chỗ.
- FIFO promotion theo `registeredAt,id`.
- Student commands dùng lock order Student → Activity → Registration để serialize schedule/capacity races.
- Teacher chỉ approve/reject registration `pending` thuộc activity mình sở hữu; dùng expected status và capacity recount.
- Student database-mode UI dùng server response làm nguồn sự thật; localStorage chỉ còn cho explicit mock mode.
- Teacher page có form approve/reject với CSRF, permission và ownership.
- School và Enterprise không bị mở rộng quyền ghi ngoài phạm vi; các consumer hiện hữu vẫn tương thích.

Review độc lập ban đầu phát hiện bốn Important issues về concurrency/test safety. Tất cả đã được sửa và re-review cuối cùng trả `READY`, không còn Critical hoặc Important. Không được vô tình loại bỏ các lock/recount/schema guards này.

## Database hiện tại sau Phase 4

- Tables: `51`
- Migrations: `22 applied`, `0 pending`
- Activities: `26`
- Activity registrations: `40`
  - approved: `8`
  - attended: `20`
  - cancelled: `6`
  - pending: `6`
  - waitlisted runtime fixture: `0`
- Activity QR sessions: `8`
- Check-ins: `20`
- Experience logs: `20`
- Test attempts: `42`
- Assessments: `20`
- Registration orphans: `0`
- Invalid cancellation metadata: `0`
- `activity_registration_policies` rows: `0` theo thiết kế; khi không có row, hệ thống dùng default policy từ activity.
- Teacher mapping cho `activity_registration.update_managed`: đúng `1`; non-Teacher mapping: `0`.
- Không có database rehearsal `talenthub_phase4_%` còn sót lại.

Backup trước Phase 4:

- `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase4_20260822.sql`
- Size: `766576` bytes
- SHA-256: `b9789e5c5b42b53dda2ceb86abd7a2a5b50a2105e8bf76866e7342f35b4096ae`

## Bằng chứng kiểm thử gần nhất

- `446` file PHP lint thành công.
- `34` PHP regression runners thành công.
- `59` JavaScript tests thành công.
- Hai MySQL Phase 4 integration suites chạy thành công trên disposable schema.
- Đã kiểm thử ba race quan trọng:
  - Hai Student tranh một chỗ cuối.
  - Một Student đăng ký đồng thời hai activity trùng lịch.
  - Cancel và register diễn ra đồng thời.
- Hai MySQL test entry points từ chối `talenthub_local` với exit code `2`.
- `git diff --check` sạch.
- Phase 4 readiness: `READY`.
- High-confidence secret scan: `0`.

## AI hiện tại

- Dataset demo AI và 9Router đã hoạt động trước các phase hiện tại.
- Hai hero profile có dữ liệu ready.
- AI model chạy Shadow thành công; learner-visible result vẫn dùng Rule an toàn.
- `TALENTHUB_AI_VISIBLE_PERCENT=0` và phải giữ nguyên cho đến release/pilot gate riêng ở Phase 12.
- Không được gọi Phase 4 là bước mở AI cho người học.

## Phase tiếp theo hợp lệ

Phase tiếp theo là Phase 5 — Learner QR Check-in and Confirmed Experience. Chỉ bắt đầu nếu người dùng yêu cầu hoặc duyệt rõ.

Phạm vi Phase 5 theo roadmap:

- Người học quét/nhập opaque QR token và check-in thời gian thực.
- Validate QR session `active/expired/revoked`, activity/registration ownership và replay.
- Check-in phải dùng transaction, CSRF, RBAC, idempotency/replay protection và audit.
- Experience log chỉ được tạo/xác nhận theo policy canonical; không suy ra giả từ registration status.
- Teacher tiếp tục sở hữu QR session lifecycle và confirmation policy.
- School chỉ đọc aggregate theo scope; Enterprise không nhận quyền check-in.
- Không triển khai notification center của Phase 8 hoặc badge award của Phase 9 trong Phase 5.
- Migration dự kiến tiếp theo là `20260821000400_create_activity_experience_policies.php`, nhưng phải audit lại ID/schema trước khi tạo.

## Quy trình bắt buộc khi nhận task tiếp theo

1. Đọc các tài liệu nguồn sự thật nêu trên.
2. Chạy audit read-only:
   - `git status --short`
   - `git branch --show-current`
   - `git rev-parse HEAD`
   - PHP `bin/migrate.php validate`
   - PHP `bin/migrate.php status`
3. Xác nhận không có migration ID hoặc semantic equivalent mới xuất hiện.
4. Phân biệt rõ việc người dùng đang yêu cầu lập kế hoạch, review hay triển khai.
5. Nếu triển khai phase mới, viết design/plan chi tiết trước; dùng TDD và review gates.
6. Mọi migration mới phải có preflight exact metadata, backup, disposable rehearsal, idempotency, behavior/concurrency tests và user authorization trước khi apply primary.
7. Sau mỗi phase, dừng tại review gate và gửi báo cáo gồm:
   - Files changed.
   - Tests/checks và số lượng pass/fail.
   - Database before/after và dữ liệu bị tác động.
   - Bốn vai trò bị ảnh hưởng thế nào.
   - Invariants, risks và unresolved blockers.
   - Migration/seed/backup/rehearsal evidence.
   - `GO_FOR_REVIEW`, `READY`, `NOT_READY` hoặc decision tương đương.
8. Không tự chuyển sang phase tiếp theo trước khi người dùng/Codex reviewer duyệt.

## Yêu cầu phản hồi đầu tiên của Anti

Sau khi đọc file này, chưa sửa code hoặc database. Hãy phản hồi ngắn theo mẫu:

```text
CONTEXT_LOADED
- Workspace/branch/HEAD: ...
- Phase 0–4: ...
- Database: ...
- AI visibility: ...
- Dirty worktree/protected files: ...
- Next eligible phase: ...
- Actions performed: read-only only
- Blockers or contradictions found: ...
```

Nếu không có mâu thuẫn, chờ yêu cầu tiếp theo. Nếu người dùng giao Phase 5, trước tiên lập kế hoạch/review scope; không tự apply migration hoặc mutate database chỉ vì file này nhắc đến Phase 5.
