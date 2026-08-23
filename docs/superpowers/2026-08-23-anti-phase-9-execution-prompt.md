# Anti Execution Prompt — Phase 9 Badges, Levels, and Personal Statistics

> **Người thực hiện:** Anti / Antigravity
>
> **Mục tiêu:** Triển khai đầy đủ production code Phase 9 theo design và
> implementation plan đã khóa; kiểm thử trên database disposable; gửi báo cáo
> `PHASE_9_GO_FOR_CODEX_REVIEW`; sau đó dừng để Codex review. Anti không được tự
> ghi `APPROVED_PHASE_9`, không apply Phase 9 vào database chính và không bắt đầu
> Phase 10.

## 1. Trạng thái đầu vào đã khóa

- Workspace: `D:\TalentHub`
- Branch: `feature/student`
- Baseline HEAD: `8875310dbb919f04a5769c7c65f60b98bd16e399`
- Commit baseline: `feat(student): complete portal phases 2-8`
- Phase 0–8 đã được tích hợp vào commit trên; Phase 9 chưa triển khai.
- Database chính: `talenthub_local`, MySQL 8.4.3.
- Baseline dự kiến phải audit lại: 58 tables, 28 migrations applied, 0 pending,
  migration validation OK.
- Các bảng `badges`, `student_badges`, `badge_rule_definitions` chưa tồn tại.
- Dữ liệu baseline tham khảo, không được tin nếu chưa query lại:
  - 20 student profiles;
  - 20 confirmed experience rows / 88.00 confirmed hours;
  - 20 attended registrations / 20 confirmed check-ins;
  - 42 submitted attempts / 4 distinct assessment types;
  - 20 published Teacher evaluations;
  - 0 notifications / 0 notification preferences.
- AI vẫn ở chế độ an toàn: `TALENTHUB_AI_VISIBLE_PERCENT=0`. Phase 9 không thay
  đổi visibility, provider, recommendation hoặc shadow-mode contract.
- `.claude/` và `.qwen/` là untracked pre-existing; không sửa, xóa hoặc đưa vào
  bất kỳ phạm vi Phase 9 nào.

Executable chuẩn:

```text
PHP:       D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
MySQL:     D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe
mysqldump: D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
Workspace: D:\TalentHub
```

## 2. Tài liệu bắt buộc đọc toàn bộ theo đúng thứ tự

1. `D:\TalentHub\docs\superpowers\readiness\2026-08-23-phase-8-notifications-review-report.md`
2. `D:\TalentHub\docs\superpowers\specs\2026-08-23-phase-9-badges-levels-statistics-design.md`
3. `D:\TalentHub\docs\superpowers\plans\2026-08-23-phase-9-badges-levels-statistics-implementation.md`
4. `D:\TalentHub\docs\superpowers\plans\2026-08-21-student-portal-four-role-completion-revised.md`
5. Các instruction file thực sự tồn tại trong workspace, như `AGENTS.md`,
   `GEMINI.md` và skill instructions liên quan. Không tự bịa đường dẫn.

Tài liệu số 2 là product/data contract đã khóa. Tài liệu số 3 là checklist thực
thi chính thức. Nếu roadmap cũ mâu thuẫn với hai tài liệu Phase 9 mới, ưu tiên:

1. invariant/safety hiện tại;
2. Phase 9 design;
3. Phase 9 implementation plan;
4. roadmap cũ.

Không được rút gọn plan thành một cách triển khai khác. Thực hiện lần lượt Task
1 đến Task 11, đánh dấu checkbox chỉ sau khi có bằng chứng thật. Có thể điều chỉnh
tên method nhỏ để khớp convention hiện hữu, nhưng không được thay đổi semantics,
schema, security boundary hoặc database gate đã khóa. Mọi divergence bắt buộc ghi
trong review report với lý do và test chứng minh.

## 3. Audit mở đầu và checkpoint bắt buộc

Trước mutation source, audit read-only:

- branch, HEAD, status và diff;
- migration validate/status;
- schema/table/index/FK/permission/runtime counts;
- exact transaction boundaries của check-in, assessment submit, Teacher publish
  evaluation và Phase 8 notification publish;
- current database-mode behavior của `badges.php`, `statistics.php`, dashboard,
  Talent Passport và School consumer;
- hashes của protected/applied migrations;
- những file Phase 9 có thể đã xuất hiện do phiên chạy trước hoặc người dùng.

Phản hồi đúng mẫu sau rồi **tiếp tục triển khai ngay trong cùng lượt**, không
dừng để hỏi người dùng có muốn tiếp tục hay không:

```text
PHASE_9_CONTEXT_LOADED
- Branch/HEAD:
- Dirty worktree summary:
- Migration validate/status:
- Primary tables/migrations:
- Badge tables present/absent:
- Confirmed fact counts:
- Phase 8 notification state:
- Producer transaction map:
- Protected files verified:
- Contradictions/unexpected files:
- First plan task:
```

Nếu baseline khác dự kiến, phân loại bằng bằng chứng. Chỉ dừng khi có xung đột
ngoài phạm vi không thể xử lý an toàn; không tự sửa/xóa dữ liệu để ép baseline
khớp báo cáo.

## 4. Global constraints không được vi phạm

1. Không làm lại Phase 0–8 và không phá luồng Student, Teacher, School,
   Enterprise đã duyệt.
2. Không dùng `git reset`, `checkout`, `clean`, `stash`; không restore file từ
   HEAD; không ghi đè file dirty từ trí nhớ.
3. Không commit, push, merge, rebase, đổi branch hoặc sửa lịch sử Git.
4. Không sửa `.env`, `.claude/`, `.qwen/`, learner migrations `001–004`, hoặc
   bất kỳ migration đã applied nào.
5. Chỉ được tạo migration Phase 9 mới:
   `Database/migrations/20260821000700_create_badges_and_award_rules.php`.
   Nếu version này đã có trước khi Anti tạo, audit nội dung và báo conflict; không
   thay thế mù quáng.
6. **Không apply `00700`, không insert catalog/rules, không chạy backfill và
   không mutate `talenthub_local`.** Codex sẽ review trước khi quyết định backup,
   apply và backfill primary.
7. Database mutation chỉ được phép trên schema disposable có tên khớp chính xác
   `^talenthub_phase9_(?:rehearsal|test)_[0-9]{14}$`, không bao giờ bằng
   `talenthub_local`. Mọi schema/user/grant disposable phải cleanup trong
   `finally`, kể cả khi test fail.
8. Không tạo badge/demo award/statistics giả. Database mode không fallback về
   mock/static khi schema/query lỗi; trả lỗi kiểm soát hoặc truthful empty state
   theo design.
9. Không cho browser đánh giá rule, tạo award, chọn `studentId`, đặt level, gửi
   notification content/event key hoặc khai báo số liệu tùy ý.
10. Read API, page render và Talent Passport read không được phát sinh award hay
    mutation.
11. Không tạo ranking, percentile, so sánh giữa học sinh, leaderboard hoặc suy
    diễn competency khi database không có fact tương ứng.
12. Không dùng AI cho badge/rule/statistics. Phase 9 hoàn toàn deterministic.
13. Không dùng arbitrary SQL/config expression. Rule language v1 chỉ có
    `{fact, operator, value}` và operator allow-list `gte`.
14. Award insert và notification `badge_awarded` phải atomic trong cùng PDO
    transaction. Duplicate/replay chỉ nuốt exact unique-constraint conflict;
    mọi lỗi FK/schema/rule/recipient phải propagate và rollback.
15. Không sửa UI bằng `innerHTML` cho dữ liệu không tin cậy. Giữ accessibility,
    empty/error/loading state và database-server truth.
16. Không bắt đầu Phase 10. Không tự ghi `APPROVED_PHASE_9`.
17. Mọi lỗi solvable trong phạm vi phải tự debug, sửa và chạy lại. Không gửi
    báo cáo tiến độ kiểu “nếu muốn tôi tiếp tục”. Chỉ dùng `PHASE_9_NOT_READY`
    cho blocker ngoại vi thật sự, sau khi đã ghi rõ bằng chứng và phương án resume.

## 5. Phạm vi production bắt buộc

Anti phải bám sát file map và từng bước trong implementation plan. Tóm tắt phạm
vi không thay thế checklist chi tiết:

### 5.1 Canonical schema và catalog

Migration `00700` tạo additive, fail-closed:

- `badges` — catalog badge hệ thống;
- `badge_rule_definitions` — versioned deterministic rule config;
- `student_badges` — materialized award, unique `(studentId,badgeId)`.

Tạo đúng năm catalog/rule v1 trong design:

- `first_experience`: confirmed experience hours `>= 1`;
- `experience_10h`: confirmed experience hours `>= 10`;
- `active_participant`: attended activity count `>= 3`;
- `assessment_explorer`: submitted distinct assessment-type count `>= 2`;
- `teacher_recognition`: published Teacher evaluation count `>= 1`.

Migration phải có preflight exact parent schema, UTC/session rules, indexes/FKs,
collision/partial-state detection, deterministic insert và idempotent apply.
`down()` không được âm thầm xóa learner awards. Không thêm permission write cho
Student; `badge.read_own` phải giữ owner-scoped.

### 5.2 Confirmed facts, levels và statistics

Chỉ dùng bốn nguồn fact được khóa:

- tổng `experience_logs.hours` với status `confirmed`;
- số registration `attended`/canonical confirmed participation;
- số loại assessment khác nhau có attempt `submitted`;
- số Teacher evaluation `published` có `publishedAt`.

`DatabaseStatisticsRepository` là nguồn query duy nhất cho facts. `LevelProgression`
dùng cấu hình `experience-hours-v1`:

- Explorer: `0` giờ;
- Innovator: `10` giờ;
- Expert: `100` giờ;
- Master: `200` giờ.

Statistics cá nhân phải có all-time, ISO week Monday-to-Monday UTC và calendar
month first-to-first UTC. Trả dữ liệu thật, source label rõ ràng, progress hiện
tại/next threshold; không ranking và không fabricated zeros nếu query/schema lỗi.

### 5.3 Pure rule engine và transactional awards

- `BadgeRuleEngine` là hàm thuần: validate exact JSON contract, fact allow-list,
  `gte`, numeric finite threshold; malformed/unknown config fail closed.
- `BadgeAwardService` đọc active rule version, query fact server-side, insert
  award idempotently và emit Phase 8 `badge_awarded` notification trong cùng
  transaction.
- Event key exact:
  `badge_award:<studentId>:<badgeId>:v<ruleVersion>`.
- Notification dùng server template và deep link `/app/learner/badges.php`.
- Khi preference in-app disabled, award vẫn đúng nhưng không có insert inbox,
  theo Phase 8 contract; transaction vẫn nhất quán.
- Concurrent/replay evaluation tạo tối đa một award và một notification.

Tạo `bin/run-badge-awards.php` cho operator backfill với dry-run mặc định,
explicit execute flag, student/badge filter, summary JSON, nonzero exit khi có
lỗi. Trong phiên Anti chỉ chạy CLI trên disposable DB; không chạy execute trên
primary.

### 5.4 Domain producer integration

Nối award evaluation vào transaction hiện có, sau khi canonical fact mutation
thành công và trước commit:

- confirmed check-in/experience;
- submitted assessment;
- published Teacher evaluation.

Producer phải dùng cùng PDO/transaction; không mở transaction rời và không
award sau commit. Cancellation, rejected/pending/draft facts không tạo award.
Replay domain event không duplicate. Bảo toàn mọi constructor/fixture/bootstrap
hiện có bằng dependency injection rõ ràng.

### 5.5 Owner-scoped reads và UI server truth

Tạo API theo plan:

- `GET app/learner/api/v1/badges.php`;
- `GET app/learner/api/v1/statistics.php`.

Identity lấy từ authenticated learner session; reject non-Student/missing
permission; limit/pagination/time-window dùng allow-list/cap; không nhận arbitrary
student identifier. Hai endpoint là read-only và không trigger award/backfill.

Thay database-mode empty/static content tại:

- `app/learner/badges.php`;
- `app/learner/statistics.php`;
- dashboard badge/level/statistics sections nếu có;
- Talent Passport optional badge collection;
- School scoped consumer theo đúng aggregate đã được phép.

Mock mode có thể tiếp tục dùng demo fixture hiện hữu, nhưng database mode phải
dùng DB truth. School chỉ đọc scoped aggregate cần thiết; Teacher/Enterprise
không được award cho Student hoặc đọc owner-only statistics. Reconcile cả hai
legacy uniqueness expectations thành canonical `uq_student_badges_student_badge`
và xóa assumption `student_badges.sourceEvent` khỏi production consumer.

## 6. TDD, test và rehearsal bắt buộc

Thực hiện đúng Task 1–11 trong plan. Mỗi review unit:

1. viết/điều chỉnh test hành vi để RED vì functionality thiếu;
2. triển khai thay đổi nhỏ nhất đúng design;
3. chạy focused suite cho tới GREEN;
4. chạy regression liên quan Phase 2–8/bốn vai trò;
5. cập nhật checkbox và artifact evidence.

Không tạo test chỉ để tìm chuỗi source rồi gọi là production verified. Test phải
bao phủ hành vi thật, query/owner scope/transaction/rollback/concurrency/UI output.
Contract test chỉ bổ sung, không thay behavioral/integration test.

Disposable rehearsal phải tối thiểu chứng minh:

- pinned primary dump/hash được restore vào disposable schema;
- migration validation/apply `00700` xanh;
- apply lần hai là idempotent;
- exact tables/columns/index/FK/rule/catalog rows;
- không đổi hashes/counts của 58 baseline tables ngoài delta đã cho phép;
- backfill dry-run không mutate;
- execute backfill trên disposable tạo đúng awards/notifications;
- execute lần hai không duplicate;
- concurrent award chỉ tạo một award/notification;
- injected notification failure rollback award;
- malformed rule/unknown fact fail closed;
- API/UI owner scope và no-read-mutation;
- Phase 2–8 regression cùng Student/Teacher/School/Enterprise contract xanh;
- schema/user/grant disposable được cleanup sau cùng.

Chạy PHP lint cho toàn bộ PHP changed/new, JavaScript syntax/tests, migration
validate/status, `git diff --check`, secret scan và protected-file hash check.
Không tuyên bố pass từ output cũ. Ghi exact command, exit code, suite/assertion
count và database target vào reports.

## 7. Tài liệu đầu ra bắt buộc

Tạo/cập nhật đúng các tài liệu trong plan:

- DCR:
  `docs/superpowers/database-change-requests/2026-08-23-phase-9-badges-levels-statistics.md`
- rehearsal report:
  `docs/superpowers/readiness/2026-08-23-phase-9-rehearsal-report.md`
- final review report:
  `docs/superpowers/readiness/2026-08-23-phase-9-review-report.md`
- cập nhật implementation plan checkbox theo bằng chứng;
- chỉ khi mọi gate xanh, cập nhật Program Tracker thành
  `GO_FOR_CODEX_REVIEW — PRIMARY_APPLY_PENDING`, không phải
  `APPROVED_PHASE_9`.

Review report cuối bắt buộc có:

1. branch/HEAD và dirty-worktree manifest;
2. toàn bộ file created/modified;
3. schema/catalog/rule/level contracts đã triển khai;
4. luồng award từ ba producer và backfill CLI;
5. API/UI/Talent Passport/School/cross-role behavior;
6. exact test commands, exit codes, suite/assertion counts;
7. disposable schema name, dump/hash, migration twice, backfill/replay,
   concurrency/rollback và cleanup evidence;
8. primary proof: `talenthub_local` vẫn 58 tables, 28 applied migrations,
   `00700` pending, counts/hashes baseline không đổi;
9. protected files, AI visibility và secret-scan evidence;
10. risks/deviations còn lại;
11. **chỉ đề xuất**, không thực thi, exact post-review sequence cho Codex:
    primary backup → apply `00700` → validate/status → operator backfill →
    idempotency/reconciliation → smoke/regression;
12. xác nhận không commit/push/merge/reset/clean/checkout/stash và không bắt đầu
    Phase 10.

## 8. Quy tắc khi gần hết context hoặc gặp lỗi công cụ

- Không recreate file từ trí nhớ và không ghi đè file bằng shell redirection.
- Dùng patch an toàn, đọc lại file, chạy lint và `git diff --check` ngay sau mỗi
  repair có rủi ro encoding/namespace/quoting.
- Nếu context gần hết, tạo handoff file:
  `docs/superpowers/handoffs/2026-08-23-phase-9-resume.md` gồm exact completed
  tasks, incomplete tasks, files/diffs, commands/results, DB state và first next
  action. Sau đó đưa một resume prompt ngắn yêu cầu phiên mới đọc design, plan và
  handoff; không được tự báo Phase 9 hoàn thành.
- Nếu server/tool lỗi, giữ workspace, không rollback/clean. Phiên mới tiếp tục từ
  disk và audit trước khi sửa.

## 9. Điều kiện kết thúc

Chỉ báo:

```text
PHASE_9_GO_FOR_CODEX_REVIEW
```

khi tất cả Task 1–11 và gate trong plan đã xanh, reports đã đầy đủ, disposable
cleanup hoàn tất, và primary vẫn chưa mutate.

Nếu còn lỗi có thể sửa, tiếp tục sửa. Nếu có blocker ngoại vi thật sự không thể
giải quyết, báo:

```text
PHASE_9_NOT_READY
- Completed plan tasks:
- Exact blocker and evidence:
- Files changed:
- Tests/rehearsal completed:
- Primary database proof:
- Safe next action:
```

Khi đã `PHASE_9_GO_FOR_CODEX_REVIEW`, dừng. Chờ Codex kiểm tra độc lập và người
dùng duyệt trước mọi primary apply/backfill, commit hoặc Phase 10.
