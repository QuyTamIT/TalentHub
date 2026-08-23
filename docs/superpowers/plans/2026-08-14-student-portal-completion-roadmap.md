# Student Portal Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thành toàn bộ vai trò Học sinh/Sinh viên của TalentHub từ giao diện hiện có thành một module frontend, backend API và database vận hành bằng dữ liệu thật; AI chỉ được lập kế hoạch và triển khai sau khi tất cả phase core trong tài liệu này hoàn thành.

**Architecture:** Giữ kiến trúc repository/read-model hiện có của `app/learner`, bổ sung learner runtime, authentication context, command services và JSON API theo từng vertical slice. Mọi dữ liệu ghi đều đi qua service có transaction, authorization, CSRF và audit; frontend không dùng `localStorage` làm nguồn dữ liệu chính. Code của Teacher, School và Enterprise chỉ được đọc để đối chiếu contract/status, không được chỉnh sửa.

**Tech Stack:** PHP 8.3, PDO MySQL/MariaDB 10.4, HTML/CSS/JavaScript thuần, PHP session, JSON API, PHPUnit-free PHP test scripts hiện có, Node.js built-in test runner.

## Global Constraints

- Chỉ triển khai trên nhánh `feature/student`.
- Chỉ sửa `app/learner/**`, learner-owned assets `assets/css/learner.css`, `assets/js/learner*.js`, learner tests, learner documentation và learner-owned migration/seed files.
- `app/teacher/**`, `app/school/**`, `app/enterprise/**` là READ ONLY. Không sửa, format, đổi mock data hoặc thay đổi hành vi của các module này.
- Không sao chép dữ liệu mock của vai trò khác thành dữ liệu ghi chính thức. Chỉ được đọc để ánh xạ contract ở learner adapter.
- Thay đổi schema phải nằm trong `Database/migrations/learner/` và chỉ phục vụ Student Portal. Không sửa trực tiếp dữ liệu của vai trò khác.
- Mọi SQL production phải dùng prepared statements; tên bảng/cột là constant nội bộ, không nhận từ request.
- Mọi nghiệp vụ ghi nhiều bảng phải dùng transaction và rollback khi có lỗi.
- Mọi API ghi phải kiểm tra session, role learner, ownership theo `student_id`, CSRF, HTTP method và content type.
- Database mode không được tự động fallback sang mock khi kết nối hoặc query thất bại.
- Mock vẫn được giữ cho demo/test, nhưng production phải bật database mode rõ ràng.
- Tất cả phase dùng TDD: test thất bại trước, implementation tối thiểu, test xanh, rồi refactor.
- Không triển khai AI trong các Phase 0-11. Phase AI bị khóa cho đến khi release gate Student Portal core đạt.
- Không commit file bí mật, password, DSN hoặc dữ liệu cá nhân thật.

---

## 1. Current Readiness Assessment — 2026-08-14

### 1.1 Trạng thái tổng quan

| Khu vực | Trạng thái hiện tại | Bằng chứng | Kết luận |
|---|---|---|---|
| Giao diện Student Portal | `PARTIALLY_READY` | Các trang dashboard, profile, assessment, activities, check-in, evaluation, AI mock, ecosystem, badges và statistics đã tồn tại | Không cần viết lại UI từ đầu |
| Data contracts/read models | `READY_FOR_EXTENSION` | Có 5 repository contracts, mock/database implementations và read models | Có thể mở rộng theo domain |
| Automated tests | `READY` | 9 learner PHP test scripts và 28 Node test cases đang pass | Có baseline chống regression |
| Learner runtime database | `NOT_READY` | `app/learner/data/config.php` vẫn dùng `source=mock`, `pdo=null`, `student_id=null` | Chưa thể chạy dữ liệu thật |
| MySQL local | `BLOCKED_BY_ENVIRONMENT` | Kiểm tra hiện tại trả `MYSQL_CONNECTION=unavailable` | Không thể xác nhận schema/data runtime thật |
| Authenticated student context | `NOT_READY` | `learner_current_student_id()` fallback `student-demo-001` | API production chưa có identity đáng tin cậy |
| Write API/service | `NOT_READY` | Repository contracts chỉ có phương thức đọc | Các thao tác hiện chỉ đổi UI/localStorage |
| Activity registration | `PARTIALLY_READY` | Có UI, domain rules, DB tables và tests; persistence nằm trong localStorage | Sẵn sàng làm vertical slice đầu tiên sau Phase 1 |
| QR check-in/experience | `PARTIALLY_READY` | Schema có token, registration, checkin, experience log; UI mới là mẫu | Thiếu API, transaction và anti-replay |
| Talent Passport | `PARTIALLY_READY` | UI có; schema có profile/skills/certificates/projects; DB repository mới đọc hồ sơ cơ bản | Thiếu aggregate repository và field ownership |
| Assessment | `PARTIALLY_READY` | Holland UI/scoring/tests tốt; localStorage là nguồn ghi | Schema thiếu question metadata, answers và lifecycle đầy đủ |
| Applications | `PARTIALLY_READY` | UI/schema/read repository có; submit/withdraw chỉ đổi UI | Thiếu command API, consent snapshot và timeline thật |
| Notifications | `NOT_READY` | Schema có bảng; chưa có repository/API/page trung tâm | Chưa thể hoàn thành luồng thông báo |
| Badges/statistics | `NOT_READY` | UI dùng mảng tĩnh; schema có bảng badge | Thiếu rule engine và aggregate queries |
| AI recommendations | `LOCKED` | UI và dữ liệu hiện là mock; schema AI tối thiểu | Không được triển khai trước Phase 11 |

### 1.2 Kết luận readiness

- Codebase **đủ để bắt đầu Phase 0 và xây dựng Phase 1**.
- Codebase **chưa đủ để đóng Phase 1** nếu chưa có MySQL chạy được, schema đã migrate và learner identity hợp lệ.
- Phase 2 trở đi không được bắt đầu nếu Phase 1 gate chưa đạt.
- Không phase nào được coi là hoàn thành chỉ vì UI render được; phải có database integration test và negative authorization tests.

---

## 2. Mandatory Readiness Gate Before Every Phase

Trước khi bắt đầu bất kỳ phase nào, người triển khai phải thực hiện đủ các bước sau. Nếu một điều kiện bắt buộc không đạt, đánh dấu phase `NOT_READY`, ghi blocker cụ thể và dừng implementation của phase đó.

### 2.1 Gate commands

- [ ] Xác nhận đúng nhánh và không có thay đổi ngoài scope.

```powershell
git branch --show-current
git status --short --branch
git diff --name-only
```

Expected: branch là `feature/student`; không có modified path thuộc `app/teacher`, `app/school` hoặc `app/enterprise`.

- [ ] Chạy toàn bộ regression tests learner.

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$node = 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
Get-ChildItem tests\learner_*_test.php | Sort-Object Name | ForEach-Object { & $php $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }
Get-ChildItem tests\learner_*_test.js | Sort-Object Name | ForEach-Object { & $node --test $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }
```

Expected: exit code `0`, không có failed test.

- [ ] Chạy learner readiness checker sau khi Phase 0 tạo công cụ này.

```powershell
& $php app\learner\tools\readiness-check.php --phase=<phase-number> --format=text
```

Expected:

- Exit `0`: `READY` — được triển khai phase.
- Exit `2`: `NOT_READY` — thiếu code/schema/config có thể hoàn thiện trong learner scope.
- Exit `3`: `BLOCKED` — phụ thuộc môi trường, quyền hoặc quyết định ngoài learner scope.

- [ ] Xác nhận migration đã áp dụng đúng version.

```powershell
& $php app\learner\tools\readiness-check.php --phase=<phase-number> --format=json
```

Expected JSON phải có `database.connected=true`, `schema.missing_tables=[]`, `schema.missing_columns=[]`, `schema.missing_indexes=[]` cho phase yêu cầu database.

- [ ] Review read-only dependencies từ module khác.

Chỉ được ghi lại tên table, status hoặc view shape mà learner phải consume. Nếu contract khác nhau, thêm mapping trong learner adapter; không sửa module nguồn.

### 2.2 Universal stop conditions

Dừng phase ngay khi gặp một trong các điều kiện sau:

- MySQL không kết nối được nhưng phase cần integration test MySQL.
- Không xác định được `student_id` từ session/user đã đăng nhập.
- Migration cần sửa hành vi của Teacher/School/Enterprise thay vì chỉ bổ sung learner contract.
- Có write query không scope bằng current `student_id`.
- Test mới chỉ chạy với mock/SQLite nhưng chưa chạy với MariaDB cho phần SQL đặc thù.
- Status từ module khác chưa có mapping an toàn và đang bị chuyển thành trạng thái sai.
- Endpoint ghi thiếu CSRF, authorization, transaction hoặc idempotency.

---

## 3. Target File/Component Map

### Existing files retained and extended

- `app/learner/data/bootstrap.php`: bootstrap contracts và learner data configuration.
- `app/learner/data/RepositoryFactory.php`: chọn mock/database repository đọc.
- `app/learner/data/Contracts/*.php`: query contracts theo domain.
- `app/learner/data/Database/*.php`: prepared SELECT repositories.
- `app/learner/data/Mock/*.php`: deterministic mock repositories.
- `app/learner/data/ReadModel/*.php`: page-facing shapes và safe defaults.
- `app/learner/includes/*-data.php`: compatibility boundary cho page hiện có.
- `assets/js/learner*.js`: frontend interaction; localStorage chỉ còn cache/draft.

### New learner-only components

- `app/learner/runtime/LearnerRuntime.php`: composition root cho request production.
- `app/learner/auth/LearnerAuth.php`: login/session/current learner identity.
- `app/learner/auth/Csrf.php`: issue và verify CSRF token.
- `app/learner/http/JsonResponse.php`: JSON response/error envelope.
- `app/learner/http/Request.php`: JSON body, method và content-type validation.
- `app/learner/api/v1/**`: learner-only API endpoints.
- `app/learner/domain/**`: command services và transaction boundaries.
- `app/learner/tools/readiness-check.php`: phase gate CLI.
- `app/learner/data/Database/SchemaInspector.php`: kiểm tra table/column/index.
- `Database/migrations/learner/*.sql`: migrations phục vụ Student Portal.
- `Database/seeds/learner/*.php`: dev/integration fixtures không chứa secret.
- `tests/learner_*_api_test.php`: API/service integration tests.
- `tests/learner_*_mysql_test.php`: MariaDB integration tests.

---

## Phase 0 — Baseline, Scope Guard and Automated Readiness Checker

**Current status:** `READY_TO_START`

**Purpose:** Biến việc “kiểm tra codebase + database đã đủ chưa” thành gate có thể chạy tự động trước mọi phase.

**Files:**

- Create: `app/learner/tools/readiness-check.php`
- Create: `app/learner/data/Database/SchemaInspector.php`
- Create: `app/learner/data/Readiness/ReadinessResult.php`
- Create: `app/learner/data/Readiness/PhaseRequirements.php`
- Create: `tests/learner_readiness_test.php`
- Modify: `app/learner/data/bootstrap.php`

**Interfaces:**

```php
final class ReadinessResult
{
    public function addPass(string $check, string $message): void;
    public function addFailure(string $check, string $message, bool $blocked = false): void;
    public function status(): string; // READY | NOT_READY | BLOCKED
    public function exitCode(): int;  // 0 | 2 | 3
    public function toArray(): array;
}

final class SchemaInspector
{
    public function hasTable(string $table): bool;
    public function hasColumn(string $table, string $column): bool;
    public function hasIndex(string $table, string $index): bool;
}
```

### Tasks

- [ ] Viết failing test cho exit codes, source mode, PDO availability, table/column/index checks và forbidden-path scope scan.
- [ ] Chạy `learner_readiness_test.php` và xác nhận RED vì classes chưa tồn tại.
- [ ] Implement `ReadinessResult`, `SchemaInspector` bằng `information_schema` prepared queries và `PhaseRequirements` cho Phase 1-11.
- [ ] Implement CLI options `--phase=0..11` và `--format=text|json`.
- [ ] Thêm scope guard đọc `git diff --name-only` và reject `app/teacher`, `app/school`, `app/enterprise`.
- [ ] Chạy test để xác nhận GREEN.
- [ ] Chạy Phase 0 gate và ghi current result.

**Acceptance criteria:**

- Gate chạy được khi DB unavailable và trả `BLOCKED` cho phase cần DB thay vì fatal error.
- Output nêu chính xác table/column/index/config bị thiếu.
- Không sửa bất kỳ file vai trò khác.

**Commit:**

```powershell
git add app/learner/tools app/learner/data/Readiness app/learner/data/Database/SchemaInspector.php app/learner/data/bootstrap.php tests/learner_readiness_test.php
git commit -m "test(learner): add automated phase readiness gates"
```

---

## Phase 1 — Learner Database Runtime, Authentication and Core Schema Safety

**Current status:** `NOT_READY`

**Current blockers:**

- Learner config mặc định `mock`, PDO null.
- MySQL hiện không kết nối được trong môi trường kiểm tra.
- Không có learner login/session contract.
- Current student fallback là `student-demo-001`.
- Chưa có migration runner/version table cho learner migrations.

**Entry gate requirements:**

- Phase 0 `READY`.
- PHP có `pdo_mysql`.
- Có env `TALENTHUB_DB_HOST`, `TALENTHUB_DB_PORT`, `TALENTHUB_DB_NAME`, `TALENTHUB_DB_USER`, `TALENTHUB_DB_PASS`.
- MariaDB chứa `users`, `student_profiles`, `classes`, `schools`.

**Files:**

- Create: `app/learner/runtime/LearnerRuntime.php`
- Create: `app/learner/data/Database/LearnerPdoFactory.php`
- Create: `app/learner/auth/LearnerAuth.php`
- Create: `app/learner/auth/Csrf.php`
- Create: `app/learner/http/JsonResponse.php`
- Create: `app/learner/http/Request.php`
- Create: `app/learner/login.php`
- Create: `app/learner/logout.php`
- Create: `app/learner/api/v1/session.php`
- Create: `Database/migrations/learner/001_migration_registry.sql`
- Create: `Database/migrations/learner/002_core_idempotency_indexes.sql`
- Create: `app/learner/tools/migrate.php`
- Create: `tests/learner_runtime_test.php`
- Create: `tests/learner_auth_test.php`
- Modify: `app/learner/data/config.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/includes/header.php`

**Interfaces:**

```php
final class LearnerPdoFactory
{
    public static function fromEnvironment(): PDO;
}

final class LearnerAuth
{
    public function login(string $email, string $password): array;
    public function logout(): void;
    public function currentStudent(): array;
    public function requireStudent(): array;
}

final class Csrf
{
    public function issue(): string;
    public function verify(?string $token): void;
}
```

**Required migration SQL:**

```sql
CREATE TABLE IF NOT EXISTS learner_schema_migrations (
  version VARCHAR(100) NOT NULL,
  appliedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE activity_registrations
  ADD UNIQUE KEY uq_activity_registrations_activity_student (activityId, studentId);

ALTER TABLE checkins
  ADD UNIQUE KEY uq_checkins_registration (registrationId);

ALTER TABLE experience_logs
  ADD UNIQUE KEY uq_experience_logs_checkin (checkinId);

ALTER TABLE student_badges
  ADD UNIQUE KEY uq_student_badges_student_badge (studentId, badgeId);
```

Migration runner phải kiểm tra `information_schema.statistics` trước khi chạy mỗi `ALTER`, vì MariaDB 10.4 không đảm bảo cú pháp `ADD INDEX IF NOT EXISTS` hoạt động nhất quán.

### Tasks

- [ ] Viết failing tests cho env validation, PDO options, invalid credentials, inactive user, non-student role, missing student profile, session regeneration và CSRF mismatch.
- [ ] Chạy focused tests để xác nhận RED.
- [ ] Implement `LearnerPdoFactory` với `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.
- [ ] Implement session login dùng `password_verify()`, `users.status=active`, learner role và join `student_profiles`.
- [ ] Loại bỏ production fallback sang `student-demo-001`; chỉ cho phép demo ID khi `source=mock`.
- [ ] Implement CSRF token rotation khi login/logout.
- [ ] Implement JSON response envelope `{ok,data,error,request_id}`.
- [ ] Implement migration registry và idempotent migration runner.
- [ ] Chạy migration trên MariaDB development.
- [ ] Chạy Phase 1 readiness gate và MariaDB integration tests.

**Acceptance criteria:**

- User learner đăng nhập nhận đúng `user_id` và `student_id` từ DB.
- User không thuộc learner role bị HTTP `403`.
- Request chưa đăng nhập bị HTTP `401`.
- Database lỗi không fallback mock và không lộ DSN/SQL/password.
- Migration chạy lần hai không tạo index trùng.
- Phase 1 gate trả `READY` trên database development.

**Commit:**

```powershell
git add app/learner/runtime app/learner/auth app/learner/http app/learner/api/v1/session.php app/learner/login.php app/learner/logout.php app/learner/data Database/migrations/learner tests/learner_runtime_test.php tests/learner_auth_test.php
git commit -m "feat(learner): add authenticated database runtime"
```

---

## Phase 2 — Dashboard and Talent Passport Read Model from Real Data

**Current status:** `COMPLETE — APPROVED_PHASE_10`

**Current blockers:**

- `DatabaseStudentRepository` chỉ đọc profile cơ bản.
- Dashboard KPI, skills, certificates, projects, evaluation, experience và badges vẫn là arrays trong `student-data.php`.
- Chưa có aggregate repository cho Talent Passport.
- AI insight trên dashboard chưa có dữ liệu thật; trong Phase 2 phải render trạng thái `insufficient` và CTA hoàn thiện dữ liệu, không hiển thị phân tích AI mock.

**Entry gate requirements:** Phase 1 `READY`; DB có student fixture liên kết hợp lệ với school/class/user.

**Files:**

- Create: `app/learner/data/Contracts/TalentPassportRepository.php`
- Create: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Create: `app/learner/data/Mock/MockTalentPassportRepository.php`
- Create: `app/learner/data/ReadModel/TalentPassportReadModel.php`
- Create: `tests/learner_talent_passport_data_test.php`
- Create: `tests/learner_talent_passport_render_test.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/index.php`
- Modify: `app/learner/profile.php`
- Modify: `app/learner/evaluation.php`

**Interfaces:**

```php
interface TalentPassportRepository
{
    public function profile(string $studentId): ?array;
    public function skills(string $studentId): array;
    public function certificates(string $studentId): array;
    public function projects(string $studentId): array;
    public function experience(string $studentId): array;
    public function evaluations(string $studentId): array;
    public function badges(string $studentId): array;
}
```

### Tasks

- [ ] Viết failing tests cho student scoping, verified state, empty states và aggregate totals.
- [ ] Chạy tests và xác nhận RED.
- [ ] Implement SELECT joins cho `student_profiles/users/classes/schools`, `student_skills/skills`, `certificates`, `project_members/projects`, `experience_logs`, `assessments/assessment_scores`, `student_badges/badges`.
- [ ] Thêm `TalentPassportReadModel` không invent dữ liệu đã xác minh.
- [ ] Chuyển dashboard KPI sang dữ liệu aggregate theo current student.
- [ ] Chuyển profile/evaluation sang repository; giữ mock parity.
- [ ] Thêm loading, database-error và empty-state copy; không hiển thị KPI của toàn trường.
- [ ] Chạy render tests ở mock mode và database mode.

**Acceptance criteria:**

- Không còn hard-coded KPI/profile data khi source là database.
- Mọi query scope theo authenticated `student_id`.
- `verified_status` được hiển thị đúng; dữ liệu self-declared không mang nhãn verified.
- Empty database render không warning/notice.

**Commit:**

```powershell
git add app/learner/data app/learner/includes/student-data.php app/learner/index.php app/learner/profile.php app/learner/evaluation.php tests/learner_talent_passport_*
git commit -m "feat(learner): read dashboard and talent passport from database"
```

---

## Phase 3 — Profile Editing, Privacy Consent and Controlled Sharing

**Current status:** `NOT_READY`

**Current blockers:** profile form chỉ cập nhật DOM; schema chưa phân biệt đầy đủ self-declared fields và verified fields; share URL đang hard-code.

**Entry gate requirements:** Phase 2 `READY`; field ownership matrix được chốt như sau:

- Student editable: phone, location, bio, avatar URL, headline.
- Read-only verified: school, class, skills verification, activity hours, assessments, badges.
- Account-managed outside profile form: email, password, roles, account status.

**Files:**

- Create: `Database/migrations/learner/003_profile_privacy_sharing.sql`
- Create: `app/learner/domain/ProfileCommandService.php`
- Create: `app/learner/domain/CertificateCommandService.php`
- Create: `app/learner/domain/SkillEvidenceCommandService.php`
- Create: `app/learner/data/Contracts/ProfileCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseProfileCommandRepository.php`
- Create: `app/learner/api/v1/profile/update.php`
- Create: `app/learner/api/v1/certificates/create.php`
- Create: `app/learner/api/v1/certificates/update.php`
- Create: `app/learner/api/v1/certificates/delete.php`
- Create: `app/learner/api/v1/skill-evidence/upsert.php`
- Create: `app/learner/api/v1/consents/grant.php`
- Create: `app/learner/api/v1/consents/revoke.php`
- Create: `app/learner/api/v1/profile-shares/create.php`
- Create: `app/learner/api/v1/profile-shares/revoke.php`
- Create: `app/learner/shared-profile.php`
- Create: `tests/learner_profile_api_test.php`
- Modify: `app/learner/profile.php`
- Modify: `assets/js/learner.js`

**Required schema:**

```sql
CREATE TABLE student_profile_details (
  studentId CHAR(36) NOT NULL,
  location VARCHAR(255) DEFAULT NULL,
  bio VARCHAR(1000) DEFAULT NULL,
  avatarUrl VARCHAR(500) DEFAULT NULL,
  headline VARCHAR(255) DEFAULT NULL,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (studentId),
  CONSTRAINT fk_student_profile_details_student
    FOREIGN KEY (studentId) REFERENCES student_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE certificates
  ADD COLUMN evidenceUrl VARCHAR(500) DEFAULT NULL,
  ADD COLUMN verifiedStatus VARCHAR(50) NOT NULL DEFAULT 'pending',
  ADD COLUMN verifiedAt TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN rejectionReason VARCHAR(500) DEFAULT NULL,
  ADD COLUMN createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE student_skills
  ADD COLUMN evidenceUrl VARCHAR(500) DEFAULT NULL,
  ADD COLUMN declaredAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE student_profile_shares (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  tokenHash CHAR(64) NOT NULL,
  sharedFields LONGTEXT NOT NULL CHECK (JSON_VALID(sharedFields)),
  expiresAt TIMESTAMP NOT NULL,
  revokedAt TIMESTAMP NULL DEFAULT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_profile_shares_token (tokenHash),
  KEY idx_student_profile_shares_student (studentId),
  CONSTRAINT fk_student_profile_shares_student
    FOREIGN KEY (studentId) REFERENCES student_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tasks

- [ ] Viết failing tests cho allowed-field update, forbidden verified-field update, ownership, consent grant/revoke, expired share và revoked share.
- [ ] Viết failing tests cho certificate ownership, evidence URL allow-list, pending certificate edit/delete và verified certificate immutability.
- [ ] Viết failing tests cho skill evidence ownership; student không được tự đổi `verifiedStatus`, `verifiedAt` hoặc verified level.
- [ ] Implement profile command transaction và audit log entries.
- [ ] Implement certificate create/update/delete cho record của current student; verified/rejected record chỉ được đọc, không xóa bằng learner API.
- [ ] Implement skill evidence upsert; chỉ cập nhật `evidenceUrl`, không thay đổi kết quả xác minh do nguồn khác ghi.
- [ ] Implement consent endpoints scope theo current student.
- [ ] Tạo random share token bằng `random_bytes(32)`; chỉ lưu SHA-256 hash trong DB.
- [ ] Render shared profile chỉ với `sharedFields` đã consent; không lộ email/phone mặc định.
- [ ] Thay DOM-only submit và hard-coded URL bằng API response.
- [ ] Chạy security/negative tests và Phase 3 gate.

**Acceptance criteria:** refresh trang vẫn giữ thay đổi; verified fields/certificates/skills không thể sửa trái quyền; share có expiry/revocation; audit log ghi actor/action/entity.

---

## Phase 4 — Activities Catalog and Registration Lifecycle

**Current status:** `PARTIALLY_READY`

**Current blockers:** registration lưu localStorage; contract chỉ đọc; schema registration thiếu lifecycle timestamps; database activity thiếu learner presentation/policy fields.

**Entry gate requirements:** Phase 1 và Phase 2 `READY`; có ít nhất một activity `published|active` trong DB.

**Files:**

- Create: `Database/migrations/learner/004_activity_registration_lifecycle.sql`
- Create: `app/learner/domain/ActivityRegistrationService.php`
- Create: `app/learner/data/Contracts/ActivityCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseActivityCommandRepository.php`
- Create: `app/learner/api/v1/activities/index.php`
- Create: `app/learner/api/v1/activities/detail.php`
- Create: `app/learner/api/v1/activity-registrations/create.php`
- Create: `app/learner/api/v1/activity-registrations/cancel.php`
- Create: `tests/learner_activity_api_test.php`
- Modify: `app/learner/data/Database/DatabaseActivityRepository.php`
- Modify: `app/learner/data/ReadModel/ActivityReadModel.php`
- Modify: `app/learner/activities.php`
- Modify: `app/learner/activity-detail.php`
- Modify: `app/learner/my-activities.php`
- Modify: `assets/js/learner-activities.js`

**Required schema:**

```sql
ALTER TABLE activity_registrations
  ADD COLUMN createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD COLUMN cancelledAt TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN cancellationReason VARCHAR(500) DEFAULT NULL;

CREATE TABLE learner_activity_details (
  activityId CHAR(36) NOT NULL,
  summary VARCHAR(500) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  location VARCHAR(500) DEFAULT NULL,
  format VARCHAR(50) NOT NULL DEFAULT 'onsite',
  registrationOpensAt TIMESTAMP NULL DEFAULT NULL,
  registrationClosesAt TIMESTAMP NULL DEFAULT NULL,
  cancellationClosesAt TIMESTAMP NULL DEFAULT NULL,
  approvalMode VARCHAR(50) NOT NULL DEFAULT 'automatic',
  costLabel VARCHAR(100) DEFAULT NULL,
  skills LONGTEXT DEFAULT NULL CHECK (skills IS NULL OR JSON_VALID(skills)),
  requirements LONGTEXT DEFAULT NULL CHECK (requirements IS NULL OR JSON_VALID(requirements)),
  benefits LONGTEXT DEFAULT NULL CHECK (benefits IS NULL OR JSON_VALID(benefits)),
  PRIMARY KEY (activityId),
  CONSTRAINT fk_learner_activity_details_activity
    FOREIGN KEY (activityId) REFERENCES activities(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Command interface:**

```php
final class ActivityRegistrationService
{
    public function register(string $studentId, string $activityId): array;
    public function cancel(string $studentId, string $registrationId, string $reason): array;
}
```

### Transaction rules

1. `SELECT activity ... FOR UPDATE`.
2. Kiểm tra visible status và registration window.
3. Kiểm tra existing `(activityId, studentId)`.
4. Kiểm tra lịch trùng với registrations active của current student.
5. Đếm active registrations; quyết định `registered`, `pending` hoặc `waitlisted`.
6. Insert/update registration và audit log.
7. Commit; duplicate key chuyển thành HTTP `409` idempotent response.

### Tasks

- [ ] Viết failing tests cho capacity race, duplicate registration, waitlist, approval mode, schedule conflict, cancellation deadline và cross-student cancellation.
- [ ] Implement migration và repository transaction primitives.
- [ ] Implement create/cancel API với CSRF và ownership.
- [ ] Chuyển frontend từ localStorage-authoritative sang API-authoritative; localStorage chỉ cache unsent UI state.
- [ ] Render My Activities từ DB registrations.
- [ ] Chạy concurrent registration test trên MariaDB.
- [ ] Chạy Phase 4 gate.

**Acceptance criteria:** registration tồn tại sau refresh/device change; không vượt capacity; không trùng student/activity; không hủy hồ sơ người khác.

---

## Phase 5 — QR Check-in and Confirmed Experience Hours

**Current status:** `PARTIALLY_READY`

**Current blockers:** check-in page là QR mẫu; chưa mở camera/submit token; chưa có service ghi `checkins` và `experience_logs`; chưa có policy xác định số giờ.

**Entry gate requirements:** Phase 4 `READY`; registration active tồn tại; QR token chưa hết hạn tồn tại.

**Files:**

- Create: `Database/migrations/learner/005_checkin_experience.sql`
- Create: `app/learner/domain/CheckinService.php`
- Create: `app/learner/data/Contracts/CheckinRepository.php`
- Create: `app/learner/data/Database/DatabaseCheckinRepository.php`
- Create: `app/learner/api/v1/checkins/create.php`
- Create: `app/learner/api/v1/checkins/history.php`
- Create: `tests/learner_checkin_api_test.php`
- Modify: `app/learner/checkin.php`
- Modify: `app/learner/includes/activity-data.php`
- Modify: `assets/js/learner.js`
- Modify: `assets/js/learner-activities.js`

**Required schema:**

```sql
CREATE TABLE learner_activity_experience_rules (
  activityId CHAR(36) NOT NULL,
  confirmedHours DECIMAL(7,2) NOT NULL,
  locationPolicy LONGTEXT DEFAULT NULL CHECK (locationPolicy IS NULL OR JSON_VALID(locationPolicy)),
  PRIMARY KEY (activityId),
  CONSTRAINT fk_learner_experience_rules_activity
    FOREIGN KEY (activityId) REFERENCES activities(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_learner_experience_hours CHECK (confirmedHours >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

QR token là token ngắn hạn của activity và có thể được nhiều registration hợp lệ sử dụng trong cửa sổ hiệu lực. Anti-replay được bảo đảm bằng unique `checkins.registrationId`; không đặt unique trên `qrTokenId`.

**Command interface:**

```php
final class CheckinService
{
    public function checkIn(string $studentId, string $registrationId, string $token): array;
}
```

### Transaction rules

1. Hash/compare token theo chính sách lưu token đã chốt.
2. Lock registration, activity và QR token.
3. Kiểm tra ownership, registration status, activity, expiry và optional location policy.
4. Insert một checkin duy nhất.
5. Update registration thành `checked_in`.
6. Insert một experience log duy nhất từ configured hours.
7. Ghi audit log và commit.

### Tasks

- [ ] Viết failing tests cho expired token, wrong activity, cancelled registration, duplicate scan, cross-student registration và transaction rollback.
- [ ] Implement check-in repository/service/API.
- [ ] Implement camera permission flow bằng browser API; luôn có ô nhập token thủ công khi camera bị từ chối.
- [ ] Thay QR mẫu bằng QR/current registration state thật.
- [ ] Render lịch sử check-in và confirmed hours từ DB.
- [ ] Chạy MariaDB transaction/idempotency tests.

**Acceptance criteria:** một registration chỉ có một checkin; một checkin chỉ tạo một experience log; lỗi giữa transaction không để lại dữ liệu nửa chừng.

---

## Phase 6 — Assessment Attempts, Answers, Scoring and Published Evaluations

**Current status:** `PARTIALLY_READY`

**Current blockers:** Holland draft/result lưu localStorage; schema thiếu question dimension/order/version/required flag và stored answers; submit chưa được server xác nhận.

**Entry gate requirements:** Phase 1 `READY`; có bộ Holland 24 câu đủ 6 RIASEC dimensions; scoring version được chốt.

**Files:**

- Create: `Database/migrations/learner/006_assessment_lifecycle.sql`
- Create: `app/learner/domain/AssessmentAttemptService.php`
- Create: `app/learner/domain/HollandScoringService.php`
- Create: `app/learner/data/Contracts/AssessmentCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseAssessmentCommandRepository.php`
- Create: `app/learner/api/v1/assessment-attempts/start.php`
- Create: `app/learner/api/v1/assessment-attempts/save.php`
- Create: `app/learner/api/v1/assessment-attempts/submit.php`
- Create: `tests/learner_assessment_api_test.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentRepository.php`
- Modify: `app/learner/data/ReadModel/AssessmentReadModel.php`
- Modify: `app/learner/assessment.php`
- Modify: `app/learner/assessment-result.php`
- Modify: `app/learner/discover.php`
- Modify: `assets/js/learner-assessment.js`

**Required schema:**

```sql
ALTER TABLE talent_tests
  ADD COLUMN version INT NOT NULL DEFAULT 1,
  ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft',
  ADD COLUMN durationMinutes INT NOT NULL DEFAULT 10,
  ADD COLUMN retakeAfterDays INT NOT NULL DEFAULT 0,
  ADD COLUMN disclaimer VARCHAR(1000) DEFAULT NULL;

ALTER TABLE test_questions
  ADD COLUMN dimension VARCHAR(20) DEFAULT NULL,
  ADD COLUMN sortOrder INT NOT NULL DEFAULT 0,
  ADD COLUMN isRequired TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE test_attempts
  ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
  ADD COLUMN testVersion INT NOT NULL DEFAULT 1,
  ADD COLUMN currentQuestionIndex INT NOT NULL DEFAULT 0,
  ADD COLUMN submittedAt TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE test_answers (
  id CHAR(36) NOT NULL,
  attemptId CHAR(36) NOT NULL,
  questionId CHAR(36) NOT NULL,
  answerValue VARCHAR(255) NOT NULL,
  answeredAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_test_answers_attempt_question (attemptId, questionId),
  CONSTRAINT fk_test_answers_attempt
    FOREIGN KEY (attemptId) REFERENCES test_attempts(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_answers_question
    FOREIGN KEY (questionId) REFERENCES test_questions(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tasks

- [ ] Viết failing tests cho start/resume, autosave idempotency, missing answer, wrong student, version pinning, retake rule và double submit.
- [ ] Implement server-side question validation và Holland scoring; không tin điểm từ browser.
- [ ] Persist answers từng câu bằng upsert trong transaction ngắn.
- [ ] Submit lock attempt, validate đúng 24 answers, compute/store result và set submitted/completed state.
- [ ] Giữ localStorage chỉ làm offline draft cache; server state thắng khi merge.
- [ ] Đọc teacher evaluations chỉ khi status được learner mapping thành `published`.
- [ ] Chạy PHP, Node và MariaDB integration tests.

**Acceptance criteria:** đổi thiết bị vẫn resume được; result truy vết đúng test version; không submit thiếu; không đọc attempt của student khác.

---

## Phase 7 — Ecosystem, Opportunities and Student Application Lifecycle

**Current status:** `PARTIALLY_READY`

**Current blockers:** DB repository chỉ select một phần cột internship; submit/withdraw chỉ đổi UI; application timeline là mock; status vocabulary giữa enterprise mock và learner khác nhau.

**Entry gate requirements:** Phase 3 `READY`; database có verified enterprise/active internship; consent scope `application_profile_share` được định nghĩa.

**Files:**

- Create: `Database/migrations/learner/007_application_snapshot.sql`
- Create: `app/learner/domain/ApplicationService.php`
- Create: `app/learner/data/Contracts/ApplicationCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
- Create: `app/learner/api/v1/applications/create.php`
- Create: `app/learner/api/v1/applications/withdraw.php`
- Create: `app/learner/api/v1/applications/index.php`
- Create: `app/learner/application-profile.php`
- Create: `tests/learner_application_api_test.php`
- Modify: `app/learner/data/Database/DatabaseEcosystemRepository.php`
- Modify: `app/learner/data/Database/DatabaseApplicationRepository.php`
- Modify: `app/learner/data/ReadModel/EcosystemReadModel.php`
- Modify: `app/learner/data/ReadModel/ApplicationReadModel.php`
- Modify: `app/learner/ecosystem.php`
- Modify: `app/learner/opportunity.php`
- Modify: `assets/js/learner.js`

**Required schema:**

```sql
CREATE TABLE application_profile_snapshots (
  applicationId CHAR(36) NOT NULL,
  consentId CHAR(36) NOT NULL,
  profileData LONGTEXT NOT NULL CHECK (JSON_VALID(profileData)),
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (applicationId),
  CONSTRAINT fk_application_snapshot_application
    FOREIGN KEY (applicationId) REFERENCES internship_applications(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_snapshot_consent
    FOREIGN KEY (consentId) REFERENCES privacy_consents(id)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Learner-only external status mapping:**

```php
[
    'new' => 'submitted',
    'applied' => 'submitted',
    'reviewing' => 'reviewing',
    'interviewing' => 'interview',
    'shortlisted' => 'interview',
    'accepted' => 'accepted',
    'rejected' => 'declined',
    'declined' => 'declined',
    'withdrawn' => 'withdrawn',
]
```

Mapping nằm trong learner adapter; không sửa Enterprise module.

`internship_applications.cvUrl` là NOT NULL trong schema hiện tại. Student Portal không nhận đường dẫn file tùy ý từ browser. Khi tạo application, service đặt `cvUrl` thành route nội bộ `/app/learner/application-profile.php?id=<application-uuid>`, route này render immutable `application_profile_snapshots.profileData` và yêu cầu authorized viewer. Nếu shared authentication chưa cung cấp enterprise viewer context, route chỉ hoạt động cho current student và readiness của external enterprise access phải ghi `BLOCKED_BY_EXTERNAL_AUTH`, không nới lỏng authorization.

### Tasks

- [ ] Viết failing tests cho verified visibility, expired opportunity, duplicate application, missing consent, snapshot immutability, withdraw policy và ownership.
- [ ] Expand SELECT để dùng `description`, `benefits`, `duration`, `workMode`, `openings`, `targetStudents`, timestamps và requirements joins.
- [ ] Implement application transaction: create application + profile snapshot + initial status history + notification.
- [ ] Gán `cvUrl` tới immutable internal application-profile route; không dùng upload filename hoặc URL do client cung cấp.
- [ ] Implement application-profile authorization cho current student; enterprise viewer chỉ được bật khi shared auth contract đã tồn tại và được kiểm tra read-only.
- [ ] Implement withdraw giữ history, không delete application.
- [ ] Chuyển application drawer và submit modal sang API state.
- [ ] Chạy learner-only integration tests; không chạy write test vào enterprise code.

**Acceptance criteria:** application duy nhất theo post/student; snapshot không đổi khi profile cập nhật sau đó; timeline lấy từ `application_status_history`.

---

## Phase 8 — Notification Center and Learner Preferences

**Current status:** `NOT_READY`

**Current blockers:** header chỉ có nút mẫu; chưa có notification repository/API/page; chưa có preference schema. Notification do Teacher/School/Enterprise tạo là upstream dependency ngoài learner scope: Student Portal chỉ consume rows hợp lệ trong `notifications`; nếu producer bên ngoài chưa ghi notification thì đánh dấu `BLOCKED_BY_EXTERNAL_PRODUCER`, không sửa module nguồn và không tạo notification giả.

**Entry gate requirements:** Phase 4-7 services đã phát được learner domain events.

**Files:**

- Create: `Database/migrations/learner/008_notification_preferences.sql`
- Create: `app/learner/data/Contracts/NotificationRepository.php`
- Create: `app/learner/data/Database/DatabaseNotificationRepository.php`
- Create: `app/learner/domain/NotificationService.php`
- Create: `app/learner/api/v1/notifications/index.php`
- Create: `app/learner/api/v1/notifications/read.php`
- Create: `app/learner/api/v1/notification-preferences/update.php`
- Create: `app/learner/notifications.php`
- Create: `tests/learner_notifications_api_test.php`
- Modify: `app/learner/includes/header.php`
- Modify: `app/learner/includes/sidebar.php`
- Modify: `assets/js/learner.js`

**Required schema:**

```sql
CREATE TABLE learner_notification_preferences (
  studentId CHAR(36) NOT NULL,
  notificationType VARCHAR(50) NOT NULL,
  inAppEnabled TINYINT(1) NOT NULL DEFAULT 1,
  emailEnabled TINYINT(1) NOT NULL DEFAULT 0,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (studentId, notificationType),
  CONSTRAINT fk_learner_notification_preferences_student
    FOREIGN KEY (studentId) REFERENCES student_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tasks

- [ ] Viết failing tests cho unread count, pagination, mark one/read all, ownership, deep link allow-list và preferences.
- [ ] Implement notification repository scope thông qua current student's `userId`.
- [ ] Phát notification từ learner-owned events: registration, check-in, assessment submission, application creation/withdrawal, badge award.
- [ ] Render Notification Center với empty/error/loading states.
- [ ] Header badge lấy unread count từ DB.
- [ ] Không tạo email delivery worker trong phase này; chỉ lưu preference.

**Acceptance criteria:** notification của user khác không truy cập được; deep link chỉ tới learner allow-list; unread count đồng bộ sau refresh.

---

## Phase 9 — Badge Rule Engine, Levels and Personal Statistics

**Current status:** `NOT_READY`

**Current blockers:** badges/levels/statistics là arrays tĩnh; chưa có rule evaluator; dữ liệu nguồn chưa confirmed cho đến khi Phase 4-6 hoàn thành.

**Entry gate requirements:** Phases 4, 5 và 6 `READY`; experience logs và results có dữ liệu confirmed.

**Files:**

- Create: `app/learner/domain/BadgeRuleEngine.php`
- Create: `app/learner/domain/BadgeAwardService.php`
- Create: `app/learner/data/Contracts/StatisticsRepository.php`
- Create: `app/learner/data/Database/DatabaseStatisticsRepository.php`
- Create: `app/learner/api/v1/badges/index.php`
- Create: `app/learner/api/v1/statistics/index.php`
- Create: `tests/learner_badge_rules_test.php`
- Create: `tests/learner_statistics_data_test.php`
- Modify: `app/learner/badges.php`
- Modify: `app/learner/statistics.php`
- Modify: `app/learner/index.php`
- Modify: `assets/js/learner.js`

**Supported badge rule schema v1:**

```json
{
  "schema_version": 1,
  "metric": "confirmed_experience_hours",
  "operator": ">=",
  "threshold": 10,
  "filters": {
    "category": null
  }
}
```

Supported metrics v1 chỉ gồm `confirmed_experience_hours`, `completed_activities`, `completed_projects`, `published_assessments` và `completed_talent_tests`. Không chạy arbitrary expression từ database.

### Tasks

- [ ] Viết failing tests cho từng metric/operator, invalid rule schema và idempotent award.
- [ ] Implement allow-listed rule parser; invalid rule không award và ghi log.
- [ ] Award bằng unique `(studentId,badgeId)` trong transaction.
- [ ] Tính level từ confirmed hours bằng learner configuration đã version hóa.
- [ ] Implement personal-only weekly/monthly aggregates.
- [ ] Xóa school-wide ranking khỏi default; chỉ render khi có explicit privacy-approved dataset.
- [ ] Chuyển badges/statistics/dashboard KPI sang DB.

**Acceptance criteria:** refresh không award trùng; statistics chỉ của current student; không dùng UI clicks làm nguồn badge.

---

## Phase 10 — Frontend Completion, Accessibility, Error States and Security Hardening

**Current status:** `PARTIALLY_READY`

**Current blockers:** nhiều action đang giả lập thành công; loading/network/server errors chưa đồng nhất; chưa có API client module chung.

**Entry gate requirements:** Phases 2-9 APIs ổn định và có documented response envelope.

**Files:**

- Create: `assets/js/learner-api.js`
- Create: `tests/learner_api_client_test.js`
- Create: `tests/learner_accessibility_render_test.php`
- Modify: `assets/js/learner.js`
- Modify: `assets/js/learner-activities.js`
- Modify: `assets/js/learner-assessment.js`
- Modify: `assets/css/learner.css`
- Modify: learner pages có API actions.

**Frontend API interface:**

```javascript
async function learnerApi(path, {
  method = 'GET',
  body = null,
  signal = null,
  idempotencyKey = null,
} = {}) {}
```

### Tasks

- [x] Viết failing tests cho JSON success/error parsing, 401 redirect, 403 message, 409 conflict, 422 field errors, timeout/abort và double-submit guard.
- [x] Implement shared API client với CSRF header, request ID và safe error mapping.
- [x] Thay toàn bộ fake-success toast bằng API-confirmed state.
- [x] Thêm loading/disabled/retry/empty/offline states cho mọi action.
- [x] Đảm bảo focus management, keyboard navigation, `aria-live`, labels và reduced motion.
- [x] Kiểm tra responsive ở 360px, 768px, 1024px và desktop.
- [x] Escape server output; tránh `innerHTML` với dữ liệu không tin cậy.
- [x] Thêm rate limiting learner-only cho login, application submit và check-in attempts.

**Acceptance criteria:** không action nào báo thành công trước server; lỗi có thể phục hồi; keyboard-only flow hoàn thành được các nghiệp vụ chính.

---

## Phase 11 — Production Readiness, Data Migration, Full Verification and Release Gate

**Current status:** `APPROVED_PHASE_11` — executable implementation and verification complete; human release approval recorded on 2026-08-23 after fresh real-database and production HTTP/API verification. The release artifact is approved for separately authorized production deployment.

**Entry gate requirements:** Phases 0-10 đều `READY` và không có unresolved blocker.

**Files:**

- Create: `tests/student_portal_four_role_e2e_mysql_test.php`
- Create: `docs/superpowers/readiness/student-portal-release-checklist.md`
- Modify: learner deployment documentation only.

### Release tasks

- [x] Backup `talenthub_local`, ghi 29 migration versions, kích thước và SHA-256.
- [x] Restore và replay toàn bộ migration hai lần trên disposable MySQL 8.4.3 clone; cả hai lần no-op, drift false.
- [x] Tạo dataset tám actor không chứa dữ liệu cá nhân thật chỉ trong disposable clone.
- [x] Chạy end-to-end flow: profile/share → activity registration → check-in → hours → assessment/evaluation → application/review → notification → badge/statistics.
- [x] Chạy authorization matrix với hai actor cho mỗi vai trò và các case cross-read/cross-write.
- [x] Chạy migration lần hai để kiểm tra idempotency.
- [x] Chạy PHP lint trên 528 file thuộc `app`, `src`, `Database`, `bin`, `tests`.
- [x] Chạy 110 safe PHP suites, 13 Node suites và các MySQL rehearsal/concurrency gates áp dụng.
- [x] Chạy `git diff --check`.
- [x] Chạy forbidden/protected-scope audit; không có thay đổi protected hoặc applied migration.
- [x] Chạy server-truth services/repositories với database source trên disposable clone; không đổi mock fixture default.
- [x] Xác nhận evidence/log output không chứa password, raw token, CV URL riêng tư hoặc câu trả lời assessment.
- [x] Xác nhận backup, forward recovery, cleanup và forensic-retention procedure; Phase 11 không có migration mới cần rollback.

### Definition of Done — Student Portal Core

Student Portal core chỉ được coi là hoàn thành khi:

- Authenticated student identity lấy từ DB/session, không hard-code.
- Dashboard/Talent Passport đọc dữ liệu thật theo current student.
- Profile self-declared fields update và audit được.
- Certificate và skill evidence tuân thủ ownership/verification rules.
- Consent/share link có giới hạn field, expiry và revoke.
- Activity registration/cancellation persisted và transaction-safe.
- QR check-in idempotent và experience hours confirmed.
- Assessment attempt/answers/results persisted và versioned.
- Published evaluations hiển thị đúng quyền.
- Opportunity application/withdrawal/timeline persisted.
- Notification Center hoạt động.
- Badges/statistics tính từ confirmed data.
- Mọi page có loading/empty/error/accessibility states.
- Toàn bộ automated tests và MariaDB end-to-end tests pass.
- Mọi integration với Teacher, School và Enterprise chỉ đi qua contract đã duyệt, có positive/negative ownership regression và không tạo state owner xung đột.

**Release commit:**

```powershell
git add app/learner assets/css/learner.css assets/js/learner*.js Database/migrations/learner Database/seeds/learner tests/learner_* docs/superpowers/readiness
git commit -m "feat(learner): complete student portal core workflows"
```

---

## Phase 12 — AI Recommendations — Deferred and Locked

**Current status:** `LOCKED_UNTIL_CORE_COMPLETE`

AI không nằm trong implementation scope của plan core này. Chỉ được tạo một design spec và implementation plan AI riêng sau khi Phase 11 release gate đạt.

### Mandatory prerequisites before AI planning

- [ ] Student Portal Core Definition of Done đạt 100%.
- [ ] Có dữ liệu thật đã xác minh từ assessment, activities, experience, skills và evaluations.
- [ ] Có privacy consent cho từng nguồn dữ liệu dùng cá nhân hóa.
- [ ] Có data-quality flags và timestamps cho mọi input.
- [ ] Có rule-based recommendation baseline để so sánh chất lượng AI.
- [ ] Có explanation contract: mỗi gợi ý phải nêu nguồn dữ liệu và thời điểm cập nhật.
- [ ] Có feedback mechanism hữu ích/không phù hợp và opt-out.
- [ ] Có model version, prompt version, confidence, input snapshot hash và audit trail.
- [ ] Có chính sách không dùng AI để đưa ra quyết định tuyển sinh/tuyển dụng tự động.

### Future AI deliverables

Sau khi prerequisites đạt, tạo spec riêng cho:

1. Rule-based recommendation baseline.
2. AI recommendation generation service.
3. Data minimization và consent filtering.
4. Explainability/provenance UI.
5. Feedback/evaluation dataset.
6. Model quality, bias, safety và fallback tests.

Không được gọi model AI trực tiếp từ browser. Mọi request phải qua backend service có consent enforcement, rate limiting, model versioning và audit.

---

## 4. Phase Dependency Order

```text
Phase 0  Readiness automation
   |
Phase 1  DB runtime + auth + core schema safety
   |
Phase 2  Dashboard + Talent Passport reads
   |
Phase 3  Profile writes + consent + sharing
   |
Phase 4  Activity registration
   |
Phase 5  QR check-in + experience
   |
Phase 6  Assessment persistence
   |
Phase 7  Ecosystem applications
   |
Phase 8  Notifications
   |
Phase 9  Badges + personal statistics
   |
Phase 10 Frontend/security/accessibility hardening
   |
Phase 11 Production release gate
   |
Phase 12 AI design and implementation plan (separate document)
```

Phases không được chạy song song nếu phase sau phụ thuộc schema/data contract của phase trước. Chỉ các test độc lập trong cùng phase mới được thực hiện song song.

---

## 5. Final Verification Commands

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$node = 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'

Get-ChildItem app\learner,tests -Recurse -File -Filter *.php |
  Where-Object { $_.FullName -like '*\app\learner\*' -or $_.Name -like 'learner_*_test.php' } |
  ForEach-Object { & $php -l $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }

Get-ChildItem tests\learner_*_test.php | Sort-Object Name |
  ForEach-Object { & $php $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }

Get-ChildItem tests\learner_*_test.js | Sort-Object Name |
  ForEach-Object { & $node --test $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }

& $php app\learner\tools\readiness-check.php --phase=11 --format=json
git diff --check
git status --short --branch
```

Expected final readiness: `READY`, all tests exit `0`, no forbidden role paths modified, database source verified on staging.

---

## 6. Execution Rule

Mỗi phase phải đi qua chu trình sau:

1. Chạy readiness gate.
2. Nếu `NOT_READY`, hoàn thiện đúng prerequisite learner-owned được liệt kê trong phase.
3. Nếu `BLOCKED`, dừng và ghi rõ dependency bên ngoài; không giả lập thành công bằng mock.
4. Viết failing tests.
5. Implement tối thiểu để tests pass.
6. Chạy focused tests, full learner regression và MariaDB integration tests.
7. Review scope, security, data ownership và migrations.
8. Commit phase độc lập.
9. Chỉ mở phase tiếp theo khi current phase đạt acceptance criteria.
