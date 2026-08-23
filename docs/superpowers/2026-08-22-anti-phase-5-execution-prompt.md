# Prompt thực thi Phase 5 cho Anti — Learner QR Check-in and Confirmed Experience

Bạn là Anti, tiếp tục dự án TalentHub từ checkpoint chính thức sau Phase 4. Người dùng giao bạn **triển khai trọn Phase 5**, kiểm thử, rehearsal migration, apply migration an toàn vào `talenthub_local` nếu và chỉ nếu toàn bộ gate bên dưới xanh, rồi dừng để Codex reviewer kiểm tra. Không tự chuyển sang Phase 6.

## 1. Ngữ cảnh bắt buộc

Trước khi làm bất kỳ thay đổi nào, đọc toàn bộ:

1. `docs/superpowers/handoffs/2026-08-22-anti-project-context-after-phase-4.md`
2. `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`, đặc biệt Phase 5/Task 8
3. `docs/superpowers/readiness/2026-08-22-phase-4-activity-registration-review-report.md`
4. `docs/superpowers/readiness/2026-08-22-phase-4-rehearsal-report.md`
5. `docs/superpowers/database-change-requests/2026-08-22-phase-4-activity-registration.md`
6. `docs/superpowers/specs/2026-08-14-talenthub-four-role-database-blueprint.md`, phần QR/check-in/experience
7. Code hiện tại của Phase 4, `TeacherQrSessionService`, `TeacherQrSessionRepository`, trang Teacher QR, learner API context và trang learner check-in.

Checkpoint đã được audit:

- Workspace: `D:\TalentHub`
- Branch: `feature/student`
- Baseline HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Worktree dirty có chủ đích vì chứa thay đổi Phase 2–4 chưa commit.
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- Primary database: `talenthub_local`, MySQL `8.4.3`
- Baseline: `51` tables, `22` migrations applied, `0` pending, validation OK.
- Runtime facts: `26` activities, `40` registrations, `8` QR sessions, `20` check-ins, `20` experience logs.
- AI: `TALENTHUB_AI_VISIBLE_PERCENT=0`; Rule visible, model Shadow-only.

## 2. Quy tắc tuyệt đối

- Không commit, push, merge, reset, clean, checkout hoặc stash.
- Không sửa/xóa thay đổi Phase 2–4 đang có trong worktree.
- Không sửa `.env`, `.claude/`, `.qwen/`.
- Không sửa learner migrations `Database/migrations/learner/001`–`004`.
- Không sửa bất kỳ migration đã apply nào, gồm toàn bộ migrations đến `20260821000300`.
- Không seed, truncate, delete, rewrite hoặc backfill dữ liệu demo/primary.
- Không tạo notification, badge, opportunity/application table hay tính năng thuộc Phase 6+.
- Không đổi AI rollout; giữ visible percent bằng `0`.
- Không log, persist, đưa vào URL, analytics, audit metadata hoặc error response raw QR token.
- Không nhận `studentId`, Teacher ID, School ID, registration owner hay organization scope từ client.
- Không dùng `localStorage` làm nguồn sự thật production.
- Không đánh dấu Phase 5 hoàn thành chỉ vì unit test hoặc UI chạy; phải có MySQL integration, rollback và concurrency evidence.

## 3. Phạm vi Phase 5 đã duyệt

### 3.1 Luồng bốn vai trò

**Teacher**

- Tiếp tục sở hữu lifecycle phiên QR: create, list, revoke.
- Khi tạo QR cho activity đang `ongoing`, Teacher cấu hình số giờ trải nghiệm áp dụng cho các lượt check-in tương lai của activity.
- Action này dùng session-derived Teacher identity, CSRF và quyền `qr_session.create_managed`; chỉ activity do Teacher hiện tại quản lý.
- Teacher xem danh sách check-in thuộc activity mình quản lý, không thấy dữ liệu ngoài scope.
- Raw token chỉ hiển thị đúng một lần như contract hiện tại.

**Student**

- Quét QR thật bằng camera khi browser hỗ trợ, hoặc nhập opaque token thủ công.
- POST chỉ gửi token opaque và metadata giao thức thật sự cần thiết; không gửi `studentId`, Teacher ID hoặc School ID.
- Backend tự resolve Student từ session, hash token bằng SHA-256 và tự tìm registration của Student cho activity.
- Student đọc lịch sử check-in/experience của chính mình từ DB thật.

**School**

- Chỉ đọc aggregate đã scope theo school hiện tại: số check-in hợp lệ và tổng **confirmed hours**.
- Không nhận raw token, không đọc lịch sử cá nhân ngoài permission/scope hiện hữu, không có quyền tạo hoặc xác nhận check-in trong Phase 5.

**Enterprise**

- Không được cấp quyền đọc/ghi QR, check-in hoặc experience.
- Regression tests phải chứng minh không có permission mapping/route mới vô tình mở cho Enterprise.

### 3.2 Confirmation policy được duyệt cho Phase 5

Phase 5 này triển khai **automatic confirmation** end-to-end:

- Teacher cấu hình `confirmedHours` cho activity khi tạo QR hoặc bằng thao tác quản lý policy gắn với activity do mình sở hữu.
- Một lượt check-in hợp lệ tạo `checkins.status='confirmed'`, đặt `checkedInAt` và `confirmedAt` cùng transaction.
- Tạo đúng một `experience_logs` row với `status='confirmed'`, `hours` lấy từ policy đã lock và `confirmedAt` được đặt.
- Registration chuyển từ `approved` sang canonical `attended`; tuyệt đối không ghi alias `checked_in` hoặc `completed` vào registration.
- Thay đổi policy chỉ ảnh hưởng check-in tương lai; không rewrite experience lịch sử.

Không triển khai `teacher_review` hoặc `school_review` trong Phase 5 vì RBAC hiện không có exact managed-confirm permission đã được duyệt. Nếu audit phát hiện yêu cầu bắt buộc phải có một trong hai mode này, hãy dừng tại design amendment, nêu permission/schema/API cần thêm và xin duyệt; không dùng tạm `checkin.read_managed`, `assessment.update_managed` hoặc quyền School để ghi dữ liệu.

## 4. Schema contract và migration

Migration dự kiến duy nhất:

`Database/migrations/20260821000400_create_activity_experience_policies.php`

Trước khi tạo file:

- Kiểm tra registry, filesystem và `information_schema` để chắc chắn ID `20260821000400` và semantic equivalent chưa tồn tại.
- Nếu ID đã bị chiếm, không tự chọn ID khác và tiếp tục; dừng báo conflict.
- Audit exact metadata của `activities`, `activity_qr_sessions`, `activity_registrations`, `checkins`, `experience_logs`, `audit_logs` và mọi FK/CHECK/index liên quan.

Contract tối thiểu của `activity_experience_policies`:

- `activityId CHAR(36)` là PK và FK tới `activities.id`.
- `confirmedHours DECIMAL(7,2) NOT NULL`, CHECK `confirmedHours >= 0` và giới hạn hợp lý chống dữ liệu bất thường.
- `locationPolicy` nullable JSON chỉ thêm nếu code thực sự sử dụng và kiểm thử trong Phase 5; không tạo field trang trí.
- `createdAt`, `updatedAt` dùng `DATETIME(6)` và UTC, metadata phải nhất quán với project.
- Không thêm `confirmationMode` khi chỉ hỗ trợ automatic confirmation.
- Không sửa shape/history của `checkins` và `experience_logs` nếu audit xác nhận canonical constraints đã đủ.
- Phải giữ `uq_checkins_registration` và `uq_experience_logs_checkin` làm replay barriers cuối cùng.
- Migration forward-only, additive, idempotent theo migration framework, có strict preflight và không xóa/backfill 20 row lịch sử.

Tạo các tài liệu:

- `docs/superpowers/specs/2026-08-22-phase-5-learner-checkin-experience-design.md`
- `docs/superpowers/plans/2026-08-22-phase-5-learner-checkin-experience-implementation.md`
- `docs/superpowers/database-change-requests/2026-08-22-phase-5-learner-checkin-experience.md`
- `docs/superpowers/readiness/2026-08-22-phase-5-rehearsal-report.md`
- `docs/superpowers/readiness/2026-08-22-phase-5-learner-checkin-review-report.md`

Design/plan phải ghi exact file paths, state transitions, lock order, API envelopes, error codes, browser states và test matrix; không để mục chưa xác định, chỗ trống cần điền sau hoặc yêu cầu kiểm thử chung chung.

## 5. Transaction và concurrency contract

Thiết kế lock order phải tương thích Phase 4, không tạo deadlock với register/cancel/Teacher transition. Tối thiểu:

1. Validate request shape và hash raw token ngoài transaction; bỏ raw token khỏi mọi diagnostics ngay sau khi hash.
2. Resolve Student identity từ authenticated session.
3. Có thể pre-read token hash để lấy candidate IDs nhưng kết quả pre-read không phải authoritative.
4. Bắt đầu một transaction.
5. Lock theo một thứ tự duy nhất đã chứng minh tương thích với Phase 4, ưu tiên Student → Activity → Registration → QR session → Experience policy; re-read và revalidate tất cả facts sau lock.
6. Registration phải thuộc Student hiện tại, cùng activity với QR và đang `approved`.
7. Activity phải ở canonical state cho phép check-in (`ongoing`).
8. QR phải `active`, chưa revoke, `expiresAt > UTC_TIMESTAMP(6)` và `usedScans < maxScans`.
9. Policy phải tồn tại và hợp lệ. Thiếu policy trả lỗi ổn định, không tính giờ từ dữ liệu giả.
10. Insert một check-in; increment `usedScans` bằng conditional update hoặc dưới lock; update registration thành `attended`; insert một confirmed experience; insert audit log; commit.
11. Bất kỳ lỗi nào rollback toàn bộ: không check-in mồ côi, không tăng scan count lẻ, không attended không có check-in, không experience thiếu check-in/audit.

Hai concurrent Student tranh scan cuối chỉ một người thành công. Hai request đồng thời của cùng Student/registration chỉ tạo tối đa một check-in và một experience. Teacher revoke đồng thời với Student scan phải serialize; kết quả cuối chỉ được là scan hoàn tất trước revoke hoặc revoke thắng và scan bị từ chối, không có trạng thái nửa chừng.

Duplicate request phải replay-safe. Nếu chưa có persistent idempotency-response store phù hợp, đừng tuyên bố response-idempotent; dùng unique constraints và stable `409` duplicate/replay response. Không tạo bảng idempotency ngoài migration đã duyệt.

## 6. API và error contract

Tạo endpoint canonical:

`app/learner/api/v1/checkins.php`

- `POST`: permission `checkin.create_own`, CSRF bắt buộc, JSON allow-list nghiêm ngặt, Student identity từ session.
- `GET`: permission `experience_log.read_own`; trả own history, pagination/limit hữu hạn nếu pattern hiện tại yêu cầu.
- Dùng `TalentHub\Http\Request`, `LearnerApiContext`, `JsonResponder` và response envelope hiện có.
- POST body ưu tiên chỉ `{ "token": "<opaque>" }`; không yêu cầu `registrationId` nếu server có thể resolve duy nhất từ Student + QR activity.
- Không echo raw token trong success/error.

Ổn định hóa và test các error code tương đương:

- `VALIDATION_FAILED`
- `CSRF_INVALID`
- `PERMISSION_DENIED`
- `QR_TOKEN_INVALID`
- `QR_SESSION_EXPIRED`
- `QR_SESSION_REVOKED`
- `QR_SESSION_EXHAUSTED`
- `ACTIVITY_NOT_CHECKIN_ELIGIBLE`
- `REGISTRATION_NOT_ELIGIBLE`
- `CHECKIN_ALREADY_EXISTS`
- `EXPERIENCE_POLICY_MISSING`
- `CHECKIN_STATE_CONFLICT`

Không phân biệt token “không tồn tại” với token hash có thật nhưng thuộc dữ liệu mà actor không được biết nếu sự khác biệt tạo enumeration leak. Chỉ trả chi tiết expiry/revocation sau khi threat model xác nhận an toàn.

## 7. Service/repository và UI dự kiến

Tạo hoặc sửa theo kiến trúc hiện tại, sau khi audit chứng minh đường dẫn chính xác:

- Create `app/learner/data/Contracts/CheckinRepository.php` nếu project convention cần interface.
- Create `app/learner/data/Database/DatabaseCheckinRepository.php`.
- Create `app/learner/data/Service/LearnerCheckinService.php`.
- Create `app/learner/api/v1/checkins.php`.
- Modify `app/learner/data/RepositoryFactory.php` và bootstrap/autoload tối thiểu nếu cần.
- Modify `app/learner/checkin.php` để bỏ QR demo sai chiều và render DB history thật.
- Modify learner JS theo separation hiện có; tách scanner thành file riêng nếu `learner.js` đã quá lớn.
- Modify `TeacherQrSessionRepository`, `TeacherQrSessionService`, `app/teacher/checkins/index.php` chỉ cho policy configuration và managed check-in read cần thiết.
- Modify School repository/service/page tối thiểu để đưa scoped aggregates thật nếu chưa có consumer phù hợp.

Camera/browser contract:

- Dùng `navigator.mediaDevices.getUserMedia` và decoder được browser hỗ trợ (ví dụ `BarcodeDetector`) mà không thêm dependency mạng tùy tiện.
- Nếu camera/decoder không hỗ trợ hoặc permission bị từ chối, hiển thị trạng thái rõ ràng và form nhập token thủ công vẫn hoạt động.
- Stop tất cả media tracks khi scan thành công, modal đóng, page hidden/unload hoặc có lỗi.
- Chống double submit; UI lấy server response làm nguồn sự thật rồi refresh history.
- Có loading, success, validation error, permission denied, expired/revoked/exhausted, duplicate và retry states có thể truy cập bằng bàn phím/screen reader.
- Không persist token trong DOM lâu hơn cần thiết, URL, clipboard tự động, session/local storage hoặc console.

## 8. TDD và test matrix bắt buộc

Viết failing tests trước implementation. Tối thiểu gồm:

### Contract/unit/API

- Session-derived Student ownership; spoofed/unknown field bị từ chối.
- CSRF và exact permission cho POST; own-read permission cho GET.
- Raw token được hash đúng và không xuất hiện trong response, exception, audit metadata hoặc logs.
- Invalid, expired, revoked, exhausted, wrong activity và missing policy.
- Registration pending/waitlisted/rejected/cancelled/attended không được scan như một approved registration mới.
- Cross-student scan không đọc hoặc mutate registration của người khác.
- Duplicate/replay trả stable error và không tăng `usedScans`.
- History chỉ trả Student hiện tại; confirmed hours chỉ lấy từ `experience_logs.status='confirmed'`.
- Teacher chỉ thấy check-in của managed activity.
- School aggregate chỉ gồm school scope; không lộ token/PII.
- Enterprise không có permission/route truy cập.

### Transaction failure injection

Inject failure sau từng mốc quan trọng:

- Sau insert check-in.
- Sau increment scan count.
- Sau registration transition.
- Sau insert experience.
- Trước/sau audit insert.

Mỗi case phải chứng minh rollback đưa DB về hash/count/state trước transaction.

### MySQL integration/concurrency

- Một registration chỉ có một check-in.
- Một check-in chỉ có một experience.
- Hai request cùng registration chạy đồng thời.
- Hai Student tranh lượt scan cuối.
- Student scan đồng thời Teacher revoke.
- Policy update đồng thời scan: scan phải dùng một policy snapshot đã lock, không tạo giờ lai.
- Integration runner bắt buộc từ chối chạy nếu database name là `talenthub_local` hoặc không khớp disposable prefix.
- Cleanup chỉ xóa đúng disposable schema đã resolve/verify; không dùng target rộng hoặc glob nguy hiểm.

### Browser/JS

- Camera supported và decode thành công.
- Permission denied.
- Không có `mediaDevices`.
- Không có decoder.
- Manual fallback.
- Stop tracks ở mọi exit path.
- Double-submit/retry/server-error/history refresh.

### Regression

- Toàn bộ Phase 0–4 suites vẫn xanh.
- Teacher QR create/list/revoke vẫn xanh và one-time token contract không đổi.
- Registration capacity/waitlist/concurrency của Phase 4 vẫn xanh.
- Talent Passport/AI sources chỉ tính confirmed experience đúng.
- AI visible percent vẫn `0`.
- PHP lint toàn repo, JS tests, migration validate/status, `git diff --check` và secret scan đều xanh.

## 9. Migration rehearsal và quyền apply primary

Người dùng cho phép apply **duy nhất migration additive Phase 5 `20260821000400`** vào `talenthub_local` khi tất cả điều kiện sau đều đạt:

1. Design, implementation plan, DCR và migration contract tests hoàn thành.
2. `bin/migrate.php validate` xanh trước apply.
3. Backup mới của `talenthub_local` được tạo bằng absolute `mysqldump` path; ghi path, byte size và SHA-256.
4. Restore/rehearsal trên disposable database thành công.
5. Apply lần một thành công; apply/status lần hai chứng minh idempotency và `0 pending`.
6. Before/after row counts và hashes chứng minh không đổi dữ liệu hiện hữu ngoài migration registry/schema additive.
7. MySQL behavioral và concurrency tests chạy trên disposable database, không phải primary.
8. Không có blocker Critical/Important chưa xử lý.

Nếu preflight, backup, rehearsal, metadata hoặc regression không xanh: **không apply primary**, dừng và báo `NOT_READY` cùng bằng chứng. Không tự sửa dữ liệu để ép migration chạy.

Sau apply không seed và không chạy test mutation trên `talenthub_local`. Chỉ smoke/read-only verification: connection, migration status/validate, schema metadata, counts/orphans/invariants.

## 10. Review gate và báo cáo bắt buộc

Khi hoàn tất, cập nhật row Phase 5 trong master plan thành `GO_FOR_REVIEW` hoặc trạng thái tương đương; chưa ghi `APPROVED_PHASE_5`. Dừng để Codex reviewer duyệt, không bắt đầu Phase 6.

Báo cáo cuối phải có:

1. Decision: `GO_FOR_REVIEW` hoặc `NOT_READY`.
2. Files created/modified, phân loại code/test/docs/migration.
3. Phạm vi behavior đã triển khai cho Student, Teacher, School, Enterprise.
4. API/error/state-machine/lock-order contract cuối cùng.
5. Tests: từng command/suite, số pass/fail, failure ban đầu và cách sửa.
6. Concurrency evidence cho ba race bắt buộc và policy-update race.
7. Database before/after: tables, applied/pending, row counts, orphan/duplicate/invalid-state checks.
8. Backup path, bytes, SHA-256; disposable DB names; rehearsal/apply/idempotency evidence; xác nhận cleanup.
9. Security/privacy: CSRF, RBAC, ownership, token redaction, secret scan.
10. Invariants: branch/HEAD, dirty worktree được bảo toàn, protected files/migrations untouched, AI visibility `0`, no commit/push/merge.
11. Risks, exclusions và blockers còn lại.
12. Đường dẫn tới design, plan, DCR, rehearsal report và review report.
13. Proposed commit groups chỉ để tham khảo; không tạo commit.

Chỉ được kết luận `GO_FOR_REVIEW` khi luồng thật sau hoạt động end-to-end:

`Teacher tạo QR + cấu hình giờ → Student camera/manual submit → server validate/lock/transaction → check-in confirmed + registration attended + experience confirmed + scan count + audit atomic → Student thấy history/hours → Teacher thấy managed event → School thấy scoped aggregate → Enterprise không có quyền`.

Nếu bất kỳ mắt xích nào vẫn mock/placeholder hoặc chỉ đổi DOM/localStorage, Phase 5 là `NOT_READY`.
