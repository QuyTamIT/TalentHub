# Anti Execution Prompt — Phase 8 Notifications and Preferences

> **Người thực hiện:** Anti / Antigravity
>
> **Mục tiêu:** Hoàn thành production code Phase 8, chạy rehearsal/database gates,
> gửi báo cáo `PHASE_8_GO_FOR_CODEX_REVIEW`, rồi dừng để Codex review. Anti không
> có quyền tự ghi `APPROVED_PHASE_8` và không được bắt đầu Phase 9.

## 1. Trạng thái đầu vào đã được phê duyệt

- Workspace: `D:\TalentHub`
- Branch: `feature/student`
- Baseline HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Phase 0–7: đã hoàn thành; Phase 7 có trạng thái `APPROVED_PHASE_7`.
- Database chính: `talenthub_local`, MySQL 8.4.3.
- Trạng thái bàn giao gần nhất: 56 base tables, 26 migrations applied, 0 pending,
  migration validation OK.
- Bốn bảng Phase 7 đang không có dữ liệu demo; không được tự seed dữ liệu Phase 7.
- `notifications` và `learner_notification_preferences` chưa tồn tại ở runtime.
- Runtime hiện đã có `notification.read_own` và `notification.mark_read_own`.
  Source seeder đã khai báo `notification.manage_preferences_own`, nhưng runtime
  có thể chưa có permission này. Phải audit lại, không được giả định.
- AI vẫn an toàn: `TALENTHUB_AI_VISIBLE_PERCENT=0`; Phase 8 không thay đổi AI.
- Worktree đang rất dirty có chủ đích vì chứa code Phase 2–7 chưa commit. Mọi
  thay đổi hiện hữu đều phải được bảo toàn.

Các executable chuẩn:

```text
PHP:       D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
MySQL:     D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe
mysqldump: D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
Workspace: D:\TalentHub
```

## 2. Tài liệu phải đọc đầy đủ trước khi sửa

Đọc theo thứ tự:

1. `D:\TalentHub\docs\superpowers\plans\2026-08-21-student-portal-four-role-completion-revised.md`
   - đặc biệt Global Constraints, Program Tracker, Phase 8 Task 12, appendices.
2. `D:\TalentHub\docs\superpowers\readiness\2026-08-22-phase-7-enterprise-application-review-report.md`
3. `D:\TalentHub\docs\superpowers\readiness\2026-08-22-phase-7-rehearsal-report.md`
4. `D:\TalentHub\docs\superpowers\database-change-requests\2026-08-22-phase-7-enterprise-application-lifecycle.md`
5. Các instruction file thực sự tồn tại trong workspace như `AGENTS.md`,
   `GEMINI.md`, skill instructions liên quan. Không tự bịa đường dẫn.

Sau đó audit read-only trạng thái thật: branch/HEAD/status/diff, migration
validate/status, schema, table counts, permission mappings, protected-file
hashes, service/repository/API/UI patterns và transaction boundaries.

Tạo task artifact/checklist của Antigravity và cập nhật sau mỗi review unit.
Phản hồi checkpoint đầu đúng mẫu sau, rồi **tiếp tục triển khai ngay**, không dừng
để hỏi có muốn tiếp tục hay không:

```text
PHASE_8_CONTEXT_LOADED
- Branch/HEAD:
- Dirty worktree summary:
- Migration validate/status:
- Runtime notification tables:
- Runtime notification permissions:
- Producer transaction map:
- Protected files verified:
- Contradictions found:
- First implementation unit:
```

## 3. Global constraints bắt buộc

1. Không làm lại Phase 0–7 và không phá hành vi đã được duyệt.
2. Không dùng `git reset`, `checkout`, `clean`, `stash`, không restore file từ
   HEAD, không ghi đè file dirty từ trí nhớ.
3. Không commit, push, merge hoặc đổi branch.
4. Không sửa `.env`, `.claude/`, `.qwen/`, migrations learner `001–004`, hoặc
   bất kỳ migration đã applied nào (`00500`, `00510`, `00520` bao gồm).
5. Migration Phase 8 duy nhất được sở hữu là:
   `Database/migrations/20260821000600_create_notifications_and_preferences.php`.
   Nếu version này đã xuất hiện bất ngờ, dừng mutation và phân loại xung đột.
6. Không apply migration/seed vào `talenthub_local` trước khi migration lint,
   contract tests, full relevant regression, backup và disposable rehearsal đều
   xanh.
7. Không chạy toàn bộ `RolePermissionSeeder` lên primary một cách mù quáng.
   Permission delta phải được chứng minh chính xác trên rehearsal và ghi trong
   DCR. Không được xóa mapping hiện hữu ngoài phạm vi.
8. Không tạo notification giả, demo notification, seed notification hoặc số
   unread giả trên primary.
9. Không email worker, queue worker, WebSocket/push, SMS, cron hoặc provider bên
   ngoài. Chỉ lưu preference `emailEnabled`; không gửi email.
10. Không làm badge engine/award rules. Event `badge_awarded` chỉ được định nghĩa
    như type dành cho producer Phase 9; Phase 8 không phát sinh badge notification.
11. Không triển khai School composer hoặc endpoint gửi tùy ý. Permission
    `notification.send_own_school` không mở rộng phạm vi Phase 8.
12. Teacher, School và Enterprise không được đọc inbox của Student. Họ chỉ có
    thể kích hoạt notification như hệ quả của transaction nghiệp vụ đã được phép.
13. Không nhận `userId`, `studentId`, `eventKey`, `deepLink`, `readAt` hoặc
    notification content tùy ý từ JSON của learner API.
14. Không dùng `innerHTML` để render dữ liệu notification không tin cậy.
15. Mọi lỗi solvable phải tự sửa và tiếp tục. Chỉ báo `PHASE_8_NOT_READY` khi có
    blocker thật không thể giải quyết an toàn. Không gửi báo cáo tiến độ kiểu
    “nếu muốn tôi tiếp tục”.

## 4. Phạm vi production bắt buộc

### 4.1 Canonical schema

Tạo migration `20260821000600` theo additive/forward-safe design sau. Audit
runtime trước; vì hai bảng hiện absent, migration phải fail closed nếu xuất hiện
schema partial/khác contract thay vì âm thầm chấp nhận.

`notifications` có contract tối thiểu:

- `id CHAR(36)` primary key;
- `userId CHAR(36)` owner, foreign key tới `users.id`;
- `eventKey VARCHAR(191) NULL`;
- `notificationType VARCHAR(100) NOT NULL`;
- `title VARCHAR(255) NOT NULL`;
- `message TEXT NOT NULL`;
- `deepLink VARCHAR(500) NULL`;
- `readAt DATETIME(6) NULL`;
- `createdAt DATETIME(6) NOT NULL`;
- unique `(userId,eventKey)`; MySQL phải cho phép nhiều legacy `eventKey=NULL`;
- index owner/timeline `(userId,createdAt,id)`;
- index owner/unread `(userId,readAt,createdAt)`;
- không cascade-delete notification history khi user bị xóa; dùng rule bảo toàn
  phù hợp với convention hiện tại và kiểm thử chính xác.

`learner_notification_preferences` có contract tối thiểu:

- `studentId CHAR(36) NOT NULL`, foreign key `student_profiles.id`;
- `notificationType VARCHAR(100) NOT NULL`;
- `inAppEnabled TINYINT(1) NOT NULL DEFAULT 1`;
- `emailEnabled TINYINT(1) NOT NULL DEFAULT 0`;
- `updatedAt DATETIME(6) NOT NULL`;
- primary/unique `(studentId,notificationType)`.

Permission runtime còn thiếu phải được xử lý additive và deterministic:

- `notification.manage_preferences_own` phải tồn tại sau Phase 8;
- mapping phải khớp canonical `RolePermissionSeeder` hiện tại;
- ưu tiên đưa exact permission/mapping delta vào DCR và migration transaction
  hoặc một bước seed riêng có rehearsal/diff chứng minh chỉ thêm đúng permission
  và expected mappings;
- tuyệt đối không để seeder đồng bộ hóa làm mất permission/mapping ngoài phạm vi.

Migration phải có preflight kiểm tra UTC/session, parent tables, absence/partial
state, exact conflicting names; `down()` không được destructive nếu dữ liệu
notification có thể đã phát sinh.

### 4.2 Notification domain

Tạo các thành phần production có trách nhiệm rõ ràng; follow namespace/bootstrap
pattern hiện tại:

- `app/learner/data/Contracts/NotificationRepository.php` nếu cần interface;
- `app/learner/data/Database/DatabaseNotificationRepository.php`;
- `app/learner/data/Service/NotificationService.php`;
- cập nhật `app/learner/data/bootstrap.php` và bootstrap/factory liên quan.

Service/repository phải cung cấp hành vi:

- `publish()` — idempotent theo `(userId,eventKey)`, dùng cùng PDO của transaction
  nghiệp vụ, không tự commit transaction của caller;
- `listForUser()` — owner scoped, newest first, pagination có limit cap;
- `unreadCount()` — owner scoped và đọc từ DB;
- `markRead()` — chỉ owner, idempotent, không đổi notification của user khác;
- `markAllRead()` — chỉ owner, một update có điều kiện;
- `preferencesForStudent()` — trả default thật cho type chưa có row;
- `updatePreference()` — upsert một allow-listed type, chỉ student hiện tại.

`publish()` phải kiểm tra/chuẩn hóa:

- recipient `userId` do domain fact xác định, không do browser gửi;
- `notificationType` thuộc allow-list;
- `eventKey` stable, không chứa secret/raw QR/assessment answer;
- title/message lấy từ server template, không chứa raw provider payload;
- `deepLink` thuộc exact internal allow-list; cấm scheme, host, `//`, traversal,
  backslash, control character và URL bên ngoài;
- preference `inAppEnabled=false` ngăn in-app insert theo design có test;
- `emailEnabled` chỉ được lưu, không trigger side effect;
- duplicate/retry trả fact hiện hữu hoặc no-op ổn định, không tạo dòng thứ hai.

Allow-list v1:

- `activity_registration_created`
- `activity_registration_cancelled`
- `activity_registration_promoted`
- `activity_registration_approved`
- `activity_registration_rejected`
- `activity_checkin_committed`
- `assessment_submitted`
- `internship_application_submitted`
- `internship_application_withdrawn`
- `internship_application_status_changed`
- `badge_awarded` (reserved only; Phase 8 không emit)

Deep links dùng exact learner routes do server sở hữu, ưu tiên các path cố định:

- activity registration → `/app/learner/my-activities.php`
- check-in → `/app/learner/checkin.php`
- assessment → `/app/learner/assessment-result.php`
- internship application → `/app/learner/ecosystem.php`
- badge reserved → `/app/learner/badges.php`

Không cho producer/browser cung cấp arbitrary URL.

### 4.3 Learner API

Tạo `app/learner/api/v1/notifications.php` theo `LearnerApiContext`,
`JsonResponder`, `ApiException` và error envelope hiện tại.

Contract đề xuất, chỉ thay nếu codebase có canonical pattern mạnh hơn và phải ghi
rõ trong design/report:

- `GET ?limit=25&offset=0`: cần `notification.read_own`; trả notifications,
  unread count, pagination và preferences của chính Student.
- `PATCH {"action":"mark-read","notificationId":"<uuid>"}`: CSRF +
  `notification.mark_read_own`.
- `PATCH {"action":"mark-all-read"}`: CSRF +
  `notification.mark_read_own`.
- `PATCH {"action":"update-preference","notificationType":"...",
  "inAppEnabled":true,"emailEnabled":false}`: CSRF +
  `notification.manage_preferences_own`.

API phải:

- resolve `studentId/userId` từ authenticated session;
- reject non-Student role, missing exact permission, bad CSRF, bad UUID,
  unknown action/type/field, invalid pagination;
- không leak existence của notification user khác;
- dùng response server-confirmed sau mutation.

### 4.4 Atomic domain producers

Nối producer vào transaction production hiện có, dùng **chính PDO/transaction**
của domain repository. Notification insert phải xảy ra trước domain commit; khi
domain rollback thì notification cũng rollback. Không dùng “insert sau commit”.

Audit và tích hợp tối thiểu tại các owner hiện tại:

- `DatabaseActivityCommandRepository`
  - registration created;
  - registration cancelled;
  - waitlisted registration promoted do cancellation, nếu có.
- `TeacherActivityRepository::transitionRegistration()`
  - approved/rejected và các transition tương ứng đã được canonical contract cho
    phép; recipient phải lấy qua registration → student profile → user.
- `DatabaseCheckinRepository::createConfirmed()`
  - emit `activity_checkin_committed` chỉ ở lần tạo check-in thật; replay không
    tạo notification mới.
- `DatabaseAssessmentWriteRepository::submitAttempt()`
  - emit `assessment_submitted` trong transaction submit thành công; retry không
    duplicate.
- `DatabaseApplicationCommandRepository`
  - submitted và withdrawn.
- `InternshipRepository::review()`
  - emit application status changed cho đúng Student của application, bên trong
    Enterprise-owned review transaction.

Constructor/wiring phải bảo toàn test fixtures và production bootstraps:

- production luôn inject writer dùng cùng PDO;
- nếu giữ optional dependency để tương thích SQLite/unit fixtures, production
  không được silently disable writer;
- không tạo PDO/connection thứ hai trong producer;
- event keys phải deterministic từ domain entity + event/version, không dựa vào
  request timing;
- producer templates không được tuyên bố thông tin không có trong DB.

Không emit `badge_awarded` cho đến khi Phase 9 có badge fact thật.

### 4.5 Learner UI server-truth

Tạo/sửa production UI:

- Create `app/learner/notifications.php`.
- Modify `app/learner/includes/header.php`.
- Modify `app/learner/includes/sidebar.php` nếu cần link điều hướng.
- Có thể create `assets/js/learner-notifications.js` và sửa CSS hiện hữu nếu cần;
  không tạo UI framework mới.

Yêu cầu:

- header bell dẫn tới Notification Center;
- unread badge/count lấy từ DB/API và sống qua refresh; 0 thì ẩn đúng semantics;
- inbox có loading, loaded, empty, error và retry states;
- pagination/load-more dùng server response;
- mark one/read all cập nhật bằng server-confirmed response;
- preference toggles hiển thị trạng thái DB, rollback UI khi API lỗi;
- `emailEnabled` ghi rõ “lưu tùy chọn; chưa gửi email trong v1”, không hứa email;
- deepLink chỉ render nếu thuộc server allow-list; dùng safe relative navigation;
- title/message dùng `textContent` hoặc escaped server-rendering, không `innerHTML`;
- keyboard/focus/ARIA hợp lý và không làm hỏng learner design system.

Không giữ dot notification mock hiện tại. Không hard-code unread count hoặc danh
sách notification.

## 5. Thứ tự thực hiện bắt buộc

### Review Unit A — Audit, design, DCR, failing tests

1. Audit exact schema/permissions/consumers/transactions và current UI.
2. Viết:
   - `docs/superpowers/specs/2026-08-23-phase-8-notifications-preferences-design.md`
   - `docs/superpowers/plans/2026-08-23-phase-8-notifications-preferences-implementation.md`
   - `docs/superpowers/database-change-requests/2026-08-23-phase-8-notifications-preferences.md`
3. Tạo test tối thiểu, có giá trị hành vi; test không được thay production code:
   - `tests/learner_notifications_api_test.php`
   - `tests/notification_domain_producer_test.php`
   - một self-orchestrating MySQL rehearsal/integrity test nếu hai file trên
     không đủ chứng minh DB/runtime/concurrency.
   - chỉ tạo một JS UI test nếu không thể mở rộng suite hiện có.
4. Tránh test source-string dư thừa. Mỗi test mới phải bảo vệ một contract thật.
5. Chạy test để chứng minh RED đúng nguyên nhân trước implementation.

### Review Unit B — Migration and repository/service

1. Viết migration `00600`, contract/repository/service/bootstrap.
2. PHP lint từng file và `bin/migrate.php validate`; primary phải vẫn 26 applied,
   `00600 pending`.
3. Chạy tests Unit A đến GREEN trên fixture/disposable phù hợp.
4. Không apply primary.

### Review Unit C — API and owner isolation

1. Viết endpoint và exact permission/CSRF validation.
2. Chạy behavioral API tests: owner/cross-owner, pagination boundaries, unread,
   mark-one/all, preferences, missing permission, invalid deep link/type.
3. Chứng minh không request field nào chọn recipient.

### Review Unit D — Producers and rollback

1. Nối từng producer một; sau mỗi producer chạy test liên quan Phase 4–7.
2. Test duplicate/retry idempotency và preference suppression.
3. Inject failure sau notification insert nhưng trước commit để chứng minh cả
   domain fact lẫn notification rollback.
4. Test Enterprise/Teacher ownership và Student recipient resolution.

### Review Unit E — UI

1. Thay mock header dot và tạo Notification Center.
2. Nối API, states, pagination, read actions, preferences.
3. Chạy render/UI/accessibility/security tests và kiểm tra không `innerHTML`.
4. Chạy learner page regressions.

### Review Unit F — Full disposable rehearsal

1. Tạo fresh primary backup trước rehearsal và ghi path/bytes/SHA-256.
2. Rehearsal phải dùng exact schema allow-list:
   `talenthub_phase8_rehearsal_<14 digits>`.
3. Pin và xác minh SHA-256 dump **trước CREATE/restore**.
4. Restore clone từ current pre-Phase-8 primary backup.
5. Chạy migration validate, apply lần 1, apply lần 2 phải `no changes`.
6. Chứng minh exact columns/indexes/FKs/defaults/permission delta.
7. So sánh row counts + deterministic hashes của toàn bộ 56 baseline tables;
   chỉ `schema_migrations` và exact approved permission delta được khác trước khi
   test fixtures chạy.
8. Chạy runtime HTTP auth/RBAC/CSRF, cross-owner, concurrency/idempotency,
   producer rollback và UI/render gates trên disposable.
9. Cleanup trong `finally`: nếu đã GRANT quyền schema cho app user thì REVOKE
   exact grant trước, sau đó DROP exact schema.
10. Xác minh cuối: 0 rehearsal schemas và 0 orphan `mysql.db` grant rows.
11. Viết:
    `docs/superpowers/readiness/2026-08-23-phase-8-rehearsal-report.md`.

### Review Unit G — Conditional primary apply

Chỉ làm khi A–F đều xanh:

1. Tạo **fresh backup thứ hai ngay trước primary apply**, ghi path/size/SHA-256.
2. Recheck primary row counts, 26 applied/0 pending baseline và migration hashes.
3. Apply đúng `00600`/expected permission delta; không apply Phase 9.
4. `bin/migrate.php validate` phải OK; status phải 27 applied/0 pending nếu chỉ
   có một migration mới.
5. Chứng minh pre-existing data không đổi; notification/preferences tables không
   có fake rows.
6. Chạy full relevant regression và rehearsal thêm một lần ở final state.
7. Không tự restore primary nếu lỗi; dừng, giữ backup và báo exact failure.

### Review Unit H — Final verification and report

1. PHP lint toàn bộ PHP production/test liên quan và tốt nhất toàn repo.
2. Chạy tất cả JS suites.
3. Chạy Phase 2–8 learner, Teacher activity, School scope, Enterprise application
   regressions liên quan.
4. `git diff --check` không có whitespace error.
5. Verify protected files unchanged, no Phase 9 migration/artifact, AI visibility
   unchanged, no leftover rehearsal schema/grant.
6. Cập nhật Program Tracker Phase 8 thành `GO_FOR_CODEX_REVIEW`, không được ghi
   `APPROVED_PHASE_8`.
7. Viết report:
   `docs/superpowers/readiness/2026-08-23-phase-8-notifications-review-report.md`.
8. Dừng để Codex kiểm tra; không bắt đầu Phase 9.

## 6. Acceptance matrix bắt buộc

Anti chỉ được báo review-ready khi chứng minh đủ:

| Contract | Bằng chứng tối thiểu |
|---|---|
| Owner isolation | Student A không đọc/mark notification Student B |
| Exact RBAC | authenticated Student thiếu permission bị 403 ổn định |
| CSRF | mutation sai/thiếu token bị từ chối |
| Pagination | limit cap, offset hợp lệ, deterministic newest-first |
| Unread | count đúng trước/sau mark one/all và sau refresh |
| Preferences | allow-listed upsert; in-app disabled suppresses producer; email không gửi |
| Idempotency | cùng `(userId,eventKey)` chỉ một row dưới retry/concurrency |
| Deep link | internal allow-list pass; external/traversal/protocol-relative fail |
| Atomicity | domain rollback để lại 0 notification |
| Registration | create/cancel/promote/approve/reject emit đúng recipient/event |
| Check-in | first commit emit một; replay không duplicate |
| Assessment | submit thật emit một; retry không duplicate |
| Application | submit/withdraw/review status emit đúng owner |
| Four roles | Teacher/Enterprise trigger scoped; không đọc inbox; School không được mở composer |
| UI truth | không mock, no `innerHTML`, DB survives refresh, đầy đủ states |
| DB safety | apply twice no-op; hashes preserved; fresh backups; cleanup 0 schema/grant |

## 7. Mẫu báo cáo cuối bắt buộc

```text
PHASE_8_GO_FOR_CODEX_REVIEW

1. Branch / HEAD / worktree invariants
2. Production files created
3. Production files modified
4. Canonical schema and exact permission delta
5. Notification service/API contract
6. Producer integrations by domain event
7. Four-role ownership/interaction proof
8. Learner UI server-truth behavior
9. Tests executed: command, pass/fail/assertion counts
10. Disposable rehearsal: schema, dump hash, apply twice, data-hash result
11. Primary backup(s): full path, byte size, SHA-256
12. Primary DB final state: tables, applied, pending, validation, row counts
13. Rehearsal cleanup: remaining schemas/grants
14. Protected-file and AI invariants
15. Known risks or tests not run (không được che giấu)
16. Reports created/updated
17. Explicit statement: no commit/push/merge/reset/clean/checkout/stash
18. Explicit statement: Phase 9 not started
19. Decision requested from Codex reviewer
```

Không được báo `GO_FOR_CODEX_REVIEW` nếu còn test Phase 8 đỏ, migration pending
trên primary sau khi đã quyết định apply, missing producer rollback evidence,
mock notification UI, orphan rehearsal schema/grant, hoặc report không khớp
trạng thái thật.

## 8. Lệnh kiểm tra nền tảng

Chạy từ `D:\TalentHub`, dùng absolute PHP path:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\connect-check.php --json --quick
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php validate
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php status
git diff --check
git status --short
```

Không ghép destructive commands. Không in secrets. Khi đọc output dài, tóm tắt
nhưng phải giữ exact command/evidence trong report.
