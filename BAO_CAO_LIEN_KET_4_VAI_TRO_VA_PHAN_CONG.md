# Báo cáo liên kết 4 vai trò và kế hoạch chỉnh sửa xuyên vai trò Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thiện liên kết dữ liệu, quyền truy cập, thông báo và workflow giữa Học sinh/Sinh viên, Giáo viên, Nhà trường và Doanh nghiệp; mặc định giao chỉnh sửa cho ba thành viên Nhà trường, Giáo viên và Doanh nghiệp, phần Sinh viên giữ vai trò kiểm tra contract và chỉ sửa khi thật sự bắt buộc.

**Architecture:** MySQL là nguồn sự thật duy nhất cho hồ sơ, hoạt động, đánh giá, dự án, tuyển dụng, tài trợ và thông báo; mỗi write đi qua service/repository có authorization, CSRF, transaction, audit và event idempotent. Giao diện bốn vai trò đọc cùng một tập trạng thái và ownership; mọi dữ liệu cá nhân mà Doanh nghiệp truy cập phải tuân thủ consent, scope, thời hạn và quan hệ tổ chức.

**Tech Stack:** PHP 8.3, MySQL 8.4/InnoDB, PDO prepared statements, session-based authentication, RBAC, PHP server-rendered UI, vanilla JavaScript, migration PHP, integration tests PHP/SQLite/MySQL.

## Global Constraints

- Không thay schema hoặc ghi dữ liệu trên database làm việc `talenthub` để chạy bài test destructive.
- Không triển khai production khi còn form POST thiếu CSRF, truy cập chéo trường không được chủ ý, dữ liệu mock hiển thị như dữ liệu thật hoặc hai API cùng thao tác nhưng phát sự kiện khác nhau.
- Học sinh/Sinh viên là vai trò trung tâm để nghiệm thu; người phụ trách Sinh viên không mặc định nhận task backend thuộc Nhà trường, Giáo viên hoặc Doanh nghiệp.
- Mọi đề xuất migration phải forward-only, review trước khi apply, giữ dữ liệu hiện có và bổ sung index/FK phù hợp.
- Không cho Doanh nghiệp đọc hồ sơ sinh viên chỉ vì biết `studentId`; yêu cầu consent còn hiệu lực và đúng phạm vi.
- Consent discovery/contact phải gắn đúng `studentId + enterpriseId + scope`, có expiry/revoke; không dùng consent chung hoặc token share công khai làm quyền discovery của mọi doanh nghiệp.
- Không cho Giáo viên/Nhà trường truy cập application cá nhân ngoài phạm vi trường, nhiệm vụ mentoring hoặc consent đã được định nghĩa.
- Trạng thái dùng UTC trong database; status/API/event payload phải thống nhất giữa bốn portal.
- Phân biệt rõ record persisted từ seed/demo, feature đã có ở runtime và traffic production; không suy luận production-ready chỉ từ việc database có record.
- Tài liệu này phân biệt rõ `hiện có`, `cần sửa`, `đề xuất phase sau`; không tuyên bố tính năng production-ready chỉ vì có test contract hoặc UI.

---

## 1. Phạm vi và cách phân công

### 1.1. Bốn vai trò

| Mã | Thành viên/vai trò | Trách nhiệm chính | Không giao mặc định |
|---|---|---|---|
| `ST` | Học sinh/Sinh viên | Đối chiếu UI, API response, quyền xem, consent, trải nghiệm end-to-end; báo cáo mismatch | Tự sửa module quản lý trường, module giáo viên, backend doanh nghiệp |
| `SC` | Nhà trường | Ownership theo trường, lớp, quản trị giáo viên/sinh viên, partnership, project, oversight và báo cáo | Sửa UI sinh viên nếu contract hiện tại đã đáp ứng |
| `TE` | Giáo viên | Hoạt động, đăng ký, QR/check-in, đánh giá, xác minh năng lực, mentoring | Sửa service quản trị trường hoặc mock doanh nghiệp |
| `EN` | Doanh nghiệp | Tin tuyển dụng, đơn ứng tuyển, talent discovery, contact request, sponsorship, analytics doanh nghiệp | Tự cấp quyền đọc hồ sơ hoặc đổi consent từ phía sinh viên |

### 1.2. Quy tắc owner

- **Owner chính:** viết test, sửa module thuộc vai trò, tạo migration nếu domain sở hữu dữ liệu, cập nhật contract.
- **Owner phối hợp:** duyệt payload, bổ sung read-model/UI hoặc producer/consumer thuộc module của mình.
- **ST:** kiểm thử bằng portal sinh viên; chỉ nhận ownership phần UI/contract learner đã phân công rõ. File nằm dưới `app/learner` có thể bắt buộc chỉnh bởi SC/TE/EN khi workflow domain của họ gọi trực tiếp file đó.
- **Hạ tầng chung:** khi sửa `src/Bootstrap/Application.php`, notification dùng chung, RBAC hoặc migration, owner là thành viên sở hữu workflow; không chuyển mặc định cho ST.

## 2. Hiện trạng đã xác minh ngày 2026-08-25

### 2.1. Dữ liệu đang chạy

| Chỉ số | Giá trị | Ý nghĩa đối với phối hợp |
|---|---:|---|
| Bảng MySQL | 69 | Schema thật đã có foundation cho đa vai trò |
| Migration đã apply | 38 | Không dùng baseline migration cũ để nghiệm thu |
| Học sinh/Sinh viên | 29 | Có record persisted trong MySQL; phần lớn là dữ liệu demo/seed, không được mô tả là người dùng production |
| Giáo viên | 11 | Có record persisted trong MySQL; cần phân biệt dữ liệu seed với tài khoản production |
| Tài khoản Nhà trường | 3 | Có record persisted trong MySQL; gồm `TalentHub Test School` |
| Doanh nghiệp | 1 | Có enterprise đã verify nhưng chưa có traffic tuyển dụng |
| Lớp | 11 | Có quan hệ `student_profiles.classId -> classes.schoolId` |
| Hoạt động | 26 | Có 14 `published`, 5 `ongoing`, 7 `completed`; dữ liệu hiện tại có nguồn demo/seed |
| Đăng ký hoạt động | 40 | Có dữ liệu registration xuyên vai trò |
| Check-in | 20 | Có dữ liệu xác nhận tham gia |
| Experience logs | 20 | Có dữ liệu giờ trải nghiệm |
| Đánh giá giáo viên | 20 | Đều `published`, có thể cấp cho learner và AI |
| Kết quả trắc nghiệm | 63 | Có dữ liệu đầu vào recommendation |
| Huy hiệu | 59 | Có automation đánh giá thành tích |
| Thông báo | 80 | 21 `assessment_submitted`, 59 `badge_awarded`; chưa có sự kiện tuyển dụng/duyệt hoạt động trong dữ liệu hiện tại |
| Kỹ năng sinh viên | 77 | Cả 77 đang `verified`, `sourceType='teacher'` và có `verifiedAt`; dữ liệu này do seeder tạo, chưa chứng minh có workflow TE xác minh tại runtime |
| Học sinh dưới 18 tuổi | 8 | Đều thuộc THPT Nguyễn Trãi; safeguarding là yêu cầu hiện hữu, không chỉ là tình huống giả định |
| Privacy consents/profile shares | 0/0 | Backend có contract nhưng chưa có bằng chứng consent/share chạy bằng dữ liệu persisted hiện tại |
| Internship posts | 0 | Chưa có bằng chứng EN -> ST chạy bằng dữ liệu thật |
| Internship applications | 0 | Chưa có bằng chứng ST -> EN -> SC/TE chạy bằng dữ liệu thật |
| Projects/sponsorships/payments | 0 | Chưa có vòng hợp tác SC -> TE/ST -> EN |

### 2.2. Phần đã liên kết tốt, cần giữ nguyên

1. SC quản lý lớp, hồ sơ và giáo viên thông qua `SchoolDashboardService` và `SchoolRepository`.
2. TE tạo hoạt động thuộc `activities.schoolId` và `activities.createdByTeacherId`.
3. ST đăng ký có transaction, chống trùng, kiểm tra lịch, capacity và waitlist.
4. TE duyệt đăng ký có ownership theo giáo viên và thông báo ngược cho ST.
5. TE tạo QR; ST check-in sinh `checkins` và `experience_logs`; SC tổng hợp theo `activities.schoolId`.
6. TE publish assessment; ST đọc đánh giá; AI nhận `assessments.status='published'`; hệ thống xét badge.
7. EN có backend tạo/publish internship; ST có backend cấp consent, nộp đơn và snapshot hồ sơ bất biến. Database hiện chưa có internship/consent/share record để chứng minh journey này đã chạy end-to-end.

### 2.3. Kết quả test hiện có

Tám nhóm test sau đã chạy thành công trong workspace:

```text
tests/notification_domain_producer_test.php
tests/phase5_enterprise_denial_test.php
tests/phase5_school_aggregate_test.php
tests/phase9_cross_role_contract_test.php
tests/student_portal_cross_role_contract_test.php
tests/teacher_activity_registration_page_contract_test.php
tests/teacher_activity_registration_route_contract_test.php
tests/teacher_activity_registration_transition_test.php
```

Điều này xác nhận contract hiện có, không thay thế E2E trên database disposable hoặc chứng minh dữ liệu production. Tám test trên đã được chạy lại ngày 2026-08-25 bằng PHP 8.3.30 và đều exit code `0`.

## 3. Ma trận thiếu liên kết và owner

| ID | Mức | Liên kết đang thiếu/sai | Owner chính | Phối hợp | ST phải sửa? |
|---|---|---|---|---|---|
| `LK-01` | P0 | SC -> TE -> ST: phạm vi hoạt động không thống nhất giữa list, register và AI | SC + TE | ST kiểm tra | Có thay đổi bắt buộc ở repository/interface learner; SC/TE vẫn sở hữu policy |
| `LK-02` | P0 | SC -> TE/ST: form POST trường thiếu CSRF; sửa teacher bypass service | SC | TE/ST kiểm tra | Không |
| `LK-03` | P0 | ST -> EN: hai workflow review application phát event khác nhau | EN | ST kiểm tra | Không nếu giữ response hiện tại |
| `LK-04` | P1 | ST -> TE, ST -> EN, EN -> SC, TE -> SC: notification chỉ hỗ trợ portal learner | EN + TE + SC | ST kiểm tra notification hiện có | Không |
| `LK-05` | P1 | TE -> ST -> SC -> EN: published assessment/skill verification chưa thành evidence đồng bộ | TE | SC + EN | Chỉ sửa learner nếu cần hiện field xác minh mới |
| `LK-06` | P1 | EN -> ST: talent explorer/detail/contact vẫn mock; scope đề xuất chưa được schema cho phép và consent chưa gắn enterprise/thời hạn | EN | SC + TE xác định evidence | Có thay đổi bắt buộc để cấp/revoke grant theo enterprise |
| `LK-07` | P1 | SC <-> EN -> TE/ST: chưa có partnership, cột audience hoặc bảng target school cho tin tuyển dụng | SC + EN | TE + ST kiểm tra | Có thay đổi read/filter learner/AI để enforce audience |
| `LK-08` | P1 | ST -> EN -> TE/SC: application chưa có oversight/mentoring dành cho trường | SC + TE | EN | Không |
| `LK-09` | P1 | SC -> TE -> ST -> EN: có schema project/member/sponsorship nhưng thiếu producer/UI dự án thật | SC + TE + EN | ST kiểm tra read model | Chỉ sửa nếu yêu cầu trang dự án learner |
| `LK-10` | P1 | SC/TE/EN -> ST: dashboard và analytics chưa cùng source-of-truth | SC + EN | TE | Không |
| `LK-11` | P1 | SC -> TE/ST: onboarding tài khoản phát mật khẩu tạm plaintext | SC | TE/ST kiểm tra đăng nhập | Không |
| `LK-12` | P0 | ST + TE + SC + EN: bài E2E hard-code database/baseline cũ | SC + EN | TE + ST cùng nghiệm thu | Không |
| `LK-13` | P0 | SC + EN -> ST: 8 học sinh dưới 18 tuổi; chưa kiểm tra độ tuổi, cấp học và consent phù hợp trước khi chia sẻ PII | SC + EN | TE giám sát; ST kiểm tra | Repository tạo snapshot bắt buộc sửa; UI sửa khi cần xin approval |

## 4. Contract dùng chung trước khi sửa

### 4.1. Ownership bắt buộc

```text
schoolId(student) = classes.schoolId thông qua student_profiles.classId
schoolId(teacher) = teacher_profiles.schoolId
schoolId(activity) = activities.schoolId
teacherId(activity) = activities.createdByTeacherId
enterpriseId(post) = internship_posts.enterpriseId
enterpriseId(application) = internship_posts.enterpriseId thông qua internship_applications.postId
schoolId(application) = student_profiles.classId -> classes.schoolId
schoolId(project) = projects.schoolId
teacherId(project) = projects.mentorTeacherId
studentId(project member) = project_members.studentId
```

### 4.2. State machine hiện có và trạng thái đề xuất

Các state machine dưới đây phản ánh constraint/service đã kiểm tra ngày 2026-08-25. Trạng thái chỉ được thêm sau khi có migration forward-only, cập nhật contract và test replay tương ứng.

```text
activity:
draft -> published -> ongoing -> completed -> archived

activity_registration:
pending -> approved -> attended
pending -> rejected
approved -> cancelled
pending -> cancelled
waitlisted -> pending | approved
waitlisted -> cancelled

internship_post:
draft -> active -> closed
draft | active -> cancelled nếu service/permission cho phép; schema đã chấp nhận cancelled

internship_application:
submitted -> reviewing -> interview -> accepted
submitted -> reviewing -> accepted
submitted -> declined
reviewing -> declined
interview -> declined
submitted | reviewing | interview -> withdrawn

school_enterprise_partnership:
pending -> approved | rejected
approved -> suspended

project:
draft -> in_progress -> completed -> archived

project_sponsorship:
pledged -> pending_payment -> paid
pledged -> cancelled
paid -> refunded
```

Trạng thái project `published` hoặc `cancelled` là **đề xuất phase sau**, hiện bị `chk_projects_status` từ chối. Nếu nhóm cần các trạng thái này, Task 9 phải bổ sung migration mở rộng constraint và test; không tự thêm enum ở UI hoặc API. Quyền **xem** activity có thể bao gồm `published`, `ongoing`, `completed`; quyền **đăng ký** chỉ áp dụng với `published` và registration window còn hiệu lực.

### 4.3. Event contract tối thiểu

```json
{
  "eventKey": "internship_application:9f575f48-f0ca-474d-8812-db7a4b718b81:submitted:enterprise:31d926c0-328f-4cc8-a365-c809b48e57ca",
  "eventType": "internship_application_submitted",
  "actorUserId": "4fa7a5f9-acde-4928-9ca2-a2f170c50b1a",
  "actorRole": "student",
  "recipientUserId": "31d926c0-328f-4cc8-a365-c809b48e57ca",
  "recipientRole": "enterprise",
  "entityType": "internship_application",
  "entityId": "9f575f48-f0ca-474d-8812-db7a4b718b81",
  "schoolId": "8531d4a4-310d-4c36-bc58-eaa9a8ef275c",
  "enterpriseId": "b819e3e8-28f6-4897-bdf6-8c9815b0a4ed",
  "deepLink": "/app/enterprise/internships/applicants.php",
  "occurredAt": "2026-08-25T10:00:00Z"
}
```

Mỗi recipient có `eventKey` riêng. Sự kiện nằm trong cùng transaction với trạng thái domain hoặc được ghi vào transactional outbox; không gửi message trước khi transaction commit.

### 4.4. Contract API response

```json
{
  "id": "f45f54ca-06c0-45ca-a1e8-40f7613970e0",
  "status": "reviewing",
  "expectedCurrentStatus": "submitted",
  "schoolId": "8531d4a4-310d-4c36-bc58-eaa9a8ef275c",
  "enterpriseId": "b819e3e8-28f6-4897-bdf6-8c9815b0a4ed",
  "updatedAt": "2026-08-25T10:00:00Z",
  "history": [
    {
      "fromStatus": "submitted",
      "toStatus": "reviewing",
      "changedByRole": "enterprise",
      "createdAt": "2026-08-25T10:00:00Z"
    }
  ]
}
```

Các vai trò không có quyền xem field nhạy cảm phải nhận projection riêng; `reviewerNote`, dữ liệu liên hệ và chi tiết consent không được tự động đưa vào dashboard trường/giáo viên.

---

## 5. Đầu việc chi tiết theo workflow

### Task 1: LK-01 — Chuẩn hóa phạm vi hoạt động giữa Nhà trường, Giáo viên và Sinh viên

**Mức:** P0.

**Vai trò thiếu liên kết:** SC -> TE -> ST. SC sở hữu chính sách trường; TE tạo hoạt động; ST list/register; AI hiện chỉ gợi ý hoạt động cùng trường.

**Bằng chứng hiện trạng:** `DatabaseActivityRepository::all()` chỉ lọc `activities.status`; `DatabaseActivityCommandRepository::activityForUpdate()` tìm theo `activity.id` mà không so `schoolId` của sinh viên. Ngược lại, `DatabaseOpportunitySource::ACTIVITY_SQL_WITH_REGISTRATIONS` join `student_profiles -> classes -> schools`, nên AI và trang activity đang khác phạm vi.

**Owner chính:** SC chốt policy + migration; TE ghi policy khi tạo/cập nhật activity.

**Owner phối hợp:** ST đối chiếu list/detail/register/AI.

**Files:**
- Create: `Database/migrations/20260825000100_add_activity_visibility_contract.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/Teacher/Repository/TeacherActivityRepository.php`
- Modify: `src/Modules/Teacher/Service/TeacherActivityService.php`
- Modify: `app/teacher/activities/index.php`
- Modify: `app/learner/data/Contracts/ActivityRepository.php`
- Modify: `app/learner/data/Database/DatabaseActivityRepository.php`
- Modify: `app/learner/data/Database/DatabaseActivityCommandRepository.php`
- Modify: `app/learner/data/Mock/MockActivityRepository.php`
- Modify: `app/learner/includes/activity-data.php`
- Modify: `app/learner/includes/ecosystem-data.php`
- Modify: `app/learner/ai/Sources/Database/DatabaseOpportunitySource.php`
- Test: `tests/teacher_activity_registration_transition_test.php`
- Create test: `tests/activity_visibility_cross_role_test.php`

**Interfaces:**
- Consumes: `student_profiles.classId`, `classes.schoolId`, `activities.schoolId`, `activities.createdByTeacherId`.
- Produces: `activities.visibility = school_only | public`; optional `activity_allowed_schools(activityId, schoolId)` nếu SC duyệt liên trường.
- Produces: `ActivityRepository::allForStudent(string $studentId): array` và `findVisibleForStudent(string $activityId, string $studentId): ?array`; method legacy `all()` chỉ giữ adapter tương thích khi caller không expose dữ liệu xuyên trường.

**Contract database:**

```sql
ALTER TABLE activities
  ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'school_only',
  ADD CONSTRAINT chk_activities_visibility CHECK (visibility IN ('school_only', 'public')),
  ADD INDEX idx_activities_school_visibility_status (schoolId, visibility, status);
```

**Điều kiện ownership dùng chung cho read model và command:**

```sql
WHERE (
    activity.visibility = 'public'
    OR activity.schoolId = (
      SELECT class.schoolId
      FROM student_profiles student
      INNER JOIN classes class ON class.id = student.classId
      WHERE student.id = :studentId
    )
  )
```

**Điều kiện nghiệp vụ tách riêng:**

```sql
-- Danh sách/detail: có thể xem cả activity đã diễn ra.
AND activity.status IN ('published', 'ongoing', 'completed')

-- Đăng ký mới: không cho ongoing/completed; giữ validation registration window.
AND activity.status = 'published'
```

- [ ] **Step 1: SC viết test** tạo student trường A, activity `school_only` trường B, activity `public` trường B; kỳ vọng A không đọc/đăng ký activity private của B, nhưng được đọc activity public nếu policy trường cho phép.
- [ ] **Step 2: TE cập nhật create/update form** gửi `visibility`, mặc định `school_only`; validate TE chỉ chỉnh activity của `createdByTeacherId` và `schoolId` của mình.
- [ ] **Step 3: SC + TE cập nhật bắt buộc** contract/repository/caller learner, command registration và AI để truyền `studentId`; dùng chung ownership nhưng tách read-status khỏi register-status. Đường dẫn nằm dưới `app/learner` không làm ST tự động trở thành owner.
- [ ] **Step 4: ST kiểm thử** 4 tình huống: cùng trường, khác trường private, khác trường public, activity đổi visibility sau khi đã mở trang.
- [ ] **Step 5: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/activity_visibility_cross_role_test.php`; kỳ vọng exit code `0`, toàn bộ assert ownership pass.

**Nghiệm thu:** list, detail, register, AI recommendation và teacher approval đều trả cùng quyết định eligibility; không truy cập chéo trường khi visibility mặc định.

### Task 2: LK-02 — Vá CSRF và thống nhất service-layer trên portal Nhà trường

**Mức:** P0.

**Vai trò thiếu liên kết:** SC -> TE và SC -> ST. Nếu form trường bị CSRF, kẻ tấn công có thể thay đổi giáo viên, lớp, sinh viên hoặc mật khẩu; các role downstream nhận dữ liệu sai.

**Bằng chứng hiện trạng:** các form POST trực tiếp tại `app/school/teachers.php`, `reports.php`, `settings.php`, `student-edit.php`, `class-edit.php`, `account.php`, `teacher-edit.php` không gọi `assertCsrf()`. `app/school/teacher-edit.php` gọi PDO update trực tiếp thay vì domain service.

**Owner chính:** SC.

**Owner phối hợp:** TE/ST chỉ xác nhận dữ liệu đã sửa vẫn hiển thị đúng.

**Files:**
- Modify: `src/Bootstrap/SchoolAppContext.php`
- Modify: `app/school/teachers.php`
- Modify: `app/school/reports.php`
- Modify: `app/school/settings.php`
- Modify: `app/school/student-edit.php`
- Modify: `app/school/class-edit.php`
- Modify: `app/school/account.php`
- Modify: `app/school/teacher-edit.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/School/Repository/SchoolRepository.php`
- Create test: `tests/school_portal_post_csrf_contract_test.php`

**Interfaces:**
- Consumes: `$ctx['session']`, `SessionManager::csrfToken()`, `SessionManager::assertCsrf(?string)`.
- Produces: `SchoolDashboardService::updateTeacherProfile(string $actorUserId, string $teacherProfileId, array $payload): array`.

**Mẫu bắt buộc cho từng form:**

```php
$session = $ctx['session'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    $service->updateTeacherProfile($userId, $teacherProfileId, [
        'specialization' => (string) ($_POST['specialization'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'bio' => (string) ($_POST['bio'] ?? ''),
    ]);
}
```

```php
<input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
```

- [ ] **Step 1: SC viết failing test** POST không token/invalid token cho cả invite teacher, sửa student, archive class, đổi password; kỳ vọng `403` và row count không đổi.
- [ ] **Step 2: SC thêm token** vào toàn bộ form thường và form inline, validate trước action branching.
- [ ] **Step 3: SC chuyển update teacher** vào `SchoolDashboardService::updateTeacherProfile`; service kiểm tra `schoolId`, input length, quyền ghi và audit.
- [ ] **Step 4: TE/ST kiểm tra** profile teacher và student vẫn được đọc đúng sau mutation hợp lệ.
- [ ] **Step 5: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/school_portal_post_csrf_contract_test.php`; kỳ vọng POST thiếu token bị từ chối và không có side effect.

**Nghiệm thu:** mọi browser write trên portal SC có CSRF + authorization + service validation; không còn direct PDO write trong page handler.

### Task 3: LK-03 — Hợp nhất hai backend review application để Doanh nghiệp cập nhật là Sinh viên nhận đủ trạng thái và thông báo

**Mức:** P0.

**Vai trò thiếu liên kết:** EN -> ST; kéo theo SC/TE nếu xây oversight sau.

**Bằng chứng hiện trạng:** `InternshipRepository::review()` ghi `application_status_history` và gọi `NotificationService::publish()` cho ST. `BusinessWorkflowRepository::review()` cũng update/history nhưng không phát notification. Cả hai đều được expose từ `src/Bootstrap/Application.php`, nên cùng nghiệp vụ có thể cho kết quả UI khác nhau.

**Owner chính:** EN.

**Owner phối hợp:** ST kiểm tra ecosystem/application status; SC/TE chỉ tiêu thụ event khi oversight được thêm.

**Files:**
- Modify: `src/Bootstrap/Application.php`
- Modify: `src/Modules/Business/Repository/BusinessWorkflowRepository.php`
- Modify: `src/Modules/Business/Service/BusinessWorkflowService.php`
- Modify: `src/Modules/Business/Repository/InternshipRepository.php`
- Modify: `src/Modules/Business/Service/InternshipService.php`
- Modify: `assets/js/applicant-management.js`
- Modify: `app/enterprise/internships/applicants.php`
- Test: `tests/notification_domain_producer_test.php`
- Create test: `tests/enterprise_review_endpoint_parity_test.php`

**Interfaces:**
- Consumes: `PATCH /api/v1/businesses/me/internship-applications/{applicationId}` và `PATCH /api/v1/businesses/me/internship-applications/{applicationId}/review`.
- Produces: một service canonical `InternshipService::review(string $userId, string $applicationId, array $payload): array`; cả route cũ/new adapter phải gọi cùng method.

**Payload canonical:**

```json
{
  "expectedCurrentStatus": "submitted",
  "targetStatus": "reviewing",
  "reviewerNote": "Hồ sơ phù hợp, chuyển vòng xem xét"
}
```

**Adapter tương thích:**

```php
$canonicalPayload = [
    'expectedCurrentStatus' => (string) ($input['expectedCurrentStatus'] ?? $current['status']),
    'targetStatus' => (string) ($input['targetStatus'] ?? $input['status'] ?? ''),
    'reviewerNote' => (string) ($input['reviewerNote'] ?? ''),
];

return $internshipService->review($userId, $applicationId, $canonicalPayload);
```

- [ ] **Step 1: EN viết failing test** gọi lần lượt hai endpoint với application khác nhau; assert mỗi request sinh đúng 1 history row và 1 notification `internship_application_status_changed`.
- [ ] **Step 2: EN chuyển route phụ** thành adapter tới `InternshipService`; giữ response shape cũ cho caller hiện hữu, không cho repository phụ tự update application.
- [ ] **Step 3: EN kiểm tra** transition không hợp lệ trả `422`, stale `expectedCurrentStatus` trả `409`, enterprise khác trả `404`.
- [ ] **Step 4: ST xác nhận** ecosystem thay status và có notification, không sửa UI nếu response giữ nguyên.
- [ ] **Step 5: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/enterprise_review_endpoint_parity_test.php` và `tests/notification_domain_producer_test.php`; kỳ vọng cả hai exit `0`.

**Nghiệm thu:** application không thể đổi trạng thái qua nhánh “im lặng”; tất cả route review có transaction/history/notification/authorization giống nhau.

### Task 4: LK-04 — Mở rộng thông báo để 4 vai trò nhận đúng sự kiện và đúng portal

**Mức:** P1.

**Vai trò thiếu liên kết:** ST -> TE; ST -> EN; EN -> ST; TE -> SC; EN -> SC; SC -> TE/ST.

**Bằng chứng hiện trạng:** publisher `app/learner/data/Service/NotificationService.php` chỉ allow-list `/app/learner/...`; module chung `src/Modules/Notification/Service/NotificationService.php` đã có API đọc/mark read cho nhiều role nhưng chưa có publisher dùng chung. Đăng ký hoạt động và nộp application hiện chỉ tạo notification cho chính ST; TE/EN không nhận sự kiện phải xử lý.

**Owner chính:** TE phụ trách event hoạt động; EN phụ trách event tuyển dụng; SC phụ trách event trường/project. EN sở hữu refactor notification dùng chung vì enterprise có nhiều recipient path nhất.

**Files:**
- Modify: `src/Modules/Notification/Service/NotificationService.php`
- Modify: `src/Modules/Notification/Repository/NotificationRepository.php`
- Create: `src/Modules/Notification/Service/CrossRoleNotificationPublisher.php`
- Modify only if required: `app/learner/data/Service/NotificationService.php`
- Modify only if required: `app/learner/data/Database/DatabaseNotificationRepository.php`
- Modify: `src/Modules/Teacher/Repository/TeacherActivityRepository.php`
- Modify: `src/Modules/Teacher/Repository/TeacherGradingRepository.php`
- Modify: `src/Modules/Business/Repository/InternshipRepository.php`
- Modify: `src/Modules/Business/Repository/BusinessWorkflowRepository.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify only if required: `app/learner/data/Database/DatabaseActivityCommandRepository.php`
- Modify only if required: `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
- Test: `tests/notification_domain_producer_test.php`
- Create test: `tests/cross_role_notification_matrix_test.php`

**Interfaces:**
- Consumes: `notifications.userId`, role-specific user membership, unique event key và endpoint chung `GET /api/v1/notifications` hiện có.
- Produces: `CrossRoleNotificationPublisher::publish(string $recipientUserId, string $recipientRole, string $eventType, string $eventKey, ?string $deepLink, array $metadata): ?array`; learner publisher cũ chỉ giữ adapter tương thích nếu cần.

**Bảng producer/recipient bắt buộc:**

| Event | Actor | Recipient | Deep-link |
|---|---|---|---|
| `activity_registration_pending_review` | ST | TE owner | `/app/teacher/activities/index.php` |
| `activity_registration_cancelled_by_student` | ST | TE owner | `/app/teacher/activities/index.php` |
| `activity_registration_approved` | TE | ST | `/app/learner/my-activities.php` |
| `activity_registration_rejected` | TE | ST | `/app/learner/my-activities.php` |
| `teacher_assessment_published` | TE | ST | `/app/learner/evaluation.php` |
| `internship_application_received` | ST | EN owner | `/app/enterprise/internships/applicants.php` |
| `internship_application_status_changed` | EN | ST | `/app/learner/ecosystem.php` |
| `internship_application_withdrawn_by_student` | ST | EN owner | `/app/enterprise/internships/applicants.php` |
| `school_partnership_requested` | EN | SC admin | `/app/school/partnerships.php` |
| `school_partnership_approved` | SC | EN owner | `/app/enterprise/partnerships.php` |
| `project_sponsorship_pledged` | EN | SC admin + TE mentor | `/app/school/projects.php` / `/app/teacher/projects/index.php` |
| `project_sponsorship_paid` | EN/payment | SC admin + TE mentor + ST members | Portal theo role |

**Allow-list role-aware:**

```php
private const ROLE_DEEP_LINKS = [
    'student' => [
        '/app/learner/my-activities.php',
        '/app/learner/evaluation.php',
        '/app/learner/ecosystem.php',
        '/app/learner/badges.php',
    ],
    'teacher' => [
        '/app/teacher/activities/index.php',
        '/app/teacher/assessments/index.php',
        '/app/teacher/projects/index.php',
    ],
    'school' => [
        '/app/school/partnerships.php',
        '/app/school/projects.php',
        '/app/school/students.php',
    ],
    'enterprise' => [
        '/app/enterprise/internships/applicants.php',
        '/app/enterprise/partnerships.php',
        '/app/enterprise/sponsorships/index.php',
    ],
];
```

- [ ] **Step 1: EN viết failing test** notification user role teacher/enterprise/school với deep-link đúng portal; assert deep-link role khác bị từ chối.
- [ ] **Step 2: TE phát event** khi registration `pending`, cancelled và assessment `published`; owner recipient phải lấy từ activity owner hoặc student owner thật.
- [ ] **Step 3: EN phát event** cho enterprise khi application submit/withdraw, cho student khi review.
- [ ] **Step 4: SC phát event** cho admin/teacher/student ở partnership/project theo quyền và membership.
- [ ] **Step 5: Toàn nhóm test** replay event key, transaction rollback, cross-tenant recipient và preference của student.
- [ ] **Step 6: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/cross_role_notification_matrix_test.php`; kỳ vọng 4 role, 0 duplicate, 0 notification sai tenant.

**Nghiệm thu:** mỗi hành động cần người khác xử lý phải tạo notification đúng người, đúng portal, đúng một lần.

### Task 5: LK-05 — Chuyển đánh giá giáo viên thành evidence được Sinh viên, Nhà trường và Doanh nghiệp dùng thống nhất

**Mức:** P1.

**Vai trò thiếu liên kết:** TE -> ST -> SC -> EN.

**Bằng chứng hiện trạng:** database có 77 `student_skills` đều `verificationStatus='verified'`, `sourceType='teacher'`, có `verifiedAt` và 77 `learner_skill_evidence`; `Database/seeds/Demo/CompleteAiDemoSeeder.php` tạo các record này. TE publish assessment đã cấp dữ liệu cho learner AI và badge, nhưng chưa có event `teacher_assessment_published`; chưa tìm thấy workflow runtime để TE tự xác minh skill. Có dữ liệu verified seed không đồng nghĩa đã có feature xác minh production; EN talent explorer vẫn mock nên chưa đọc verified evidence thật từ database.

**Owner chính:** TE.

**Owner phối hợp:** SC định nghĩa quyền mentor/teacher; EN đọc projection đã consent; ST kiểm tra evaluation/badge/AI.

**Files:**
- Modify: `src/Modules/Teacher/Service/TeacherGradingService.php`
- Modify: `src/Modules/Teacher/Repository/TeacherGradingRepository.php`
- Modify: `app/teacher/assessments/index.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `app/learner/ai/Sources/Database/DatabasePublishedEvaluationSource.php`
- Modify: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Create test: `tests/teacher_published_evidence_cross_role_test.php`

**Interfaces:**
- Consumes: `assessments.status`, `assessments.publishedAt`, `assessment_scores`, `student_skills.verificationStatus`, `student_skills.verifiedAt`.
- Produces: `publishedEvaluationCount`, `verifiedSkills[]`, `verificationStatus='verified'`, notification `teacher_assessment_published`.

**Projection dùng chung:**

```json
{
  "studentId": "9d6e7b31-9d77-49a2-8e64-2173fd4d7452",
  "schoolId": "8531d4a4-310d-4c36-bc58-eaa9a8ef275c",
  "activityId": "e323a7c7-a2ff-447f-8ccd-cef9b0f0c9b3",
  "assessmentStatus": "published",
  "overallScore": 92.5,
  "publishedAt": "2026-08-25T10:00:00Z",
  "verifiedSkills": [
    {
      "code": "presentation",
      "name": "Thuyết trình",
      "verificationStatus": "verified",
      "verifiedAt": "2026-08-25T10:00:00Z"
    }
  ]
}
```

- [ ] **Step 1: TE viết failing test** tạo assessment draft và published; draft không xuất hiện ở ST/AI/EN, published xuất hiện khi consent cho phép.
- [ ] **Step 2: TE publish trong transaction** assessment, criteria scores, optional skill verification, badge evaluation và student notification.
- [ ] **Step 3: SC chỉ đọc aggregate** theo trường: số student được đánh giá, số verified skills; không lấy private comments nếu policy không cho phép.
- [ ] **Step 4: EN đọc verified evidence** từ contract Talent Discovery của Task 6; không truy cập assessment nội bộ trực tiếp.
- [ ] **Step 5: ST kiểm tra** evaluation page, badge page, AI snapshot và notification; chỉ chỉnh UI nếu cần hiện nhãn verified mới.
- [ ] **Step 6: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/teacher_published_evidence_cross_role_test.php`; kỳ vọng draft bị ẩn, published xuất hiện đúng scope.

**Nghiệm thu:** cùng một lần publish tạo một nguồn evidence nhất quán cho ST, dashboard SC và talent projection EN.

### Task 6: LK-06 — Bỏ mock Talent Explorer và kết nối hồ sơ thật có consent

**Mức:** P1.

**Vai trò thiếu liên kết:** EN -> ST, có evidence từ TE và ownership của SC.

**Bằng chứng hiện trạng:** `app/enterprise/talents.php` nhúng `$mockTalents`; `assets/js/talent-search.js` đọc `talents-mock-data`; `assets/js/talent-detail.js` mô tả contact request mock. ST đã có `ProfileSharingService`, `student_profile_shares`, `privacy_consents` và `DatabaseTalentPassportRepository`, nhưng `ProfileSharingService` hiện chỉ tạo scope `profile_share`, bảng consent/share đều có 0 record, `privacy_consents` chưa có enterprise/thời hạn, và `chk_privacy_consents_scope` chỉ cho phép `assessment`, `skills`, `activity`, `evaluation`, `profile_share`, `application_profile_share`. Scope `enterprise_talent_discovery` hoặc scope contact sẽ bị MySQL từ chối nếu không có migration; chưa có bảng grant theo enterprise hoặc contact request.

**Owner chính:** EN.

**Owner phối hợp:** TE xác minh skill/evaluation; SC chốt phạm vi partner school; ST nghiệm thu grant/revoke và dữ liệu hiển thị. EN sở hữu cả migration/shared adapter nằm trong thư mục learner; không mặc định chuyển ownership cho ST.

**Files:**
- Create: `Database/migrations/20260825000150_create_enterprise_talent_access_grants.php`
- Create: `src/Modules/Business/Repository/EnterpriseTalentRepository.php`
- Create: `src/Modules/Business/Service/EnterpriseTalentService.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `app/enterprise/talents.php`
- Modify: `app/enterprise/talents/detail.php`
- Modify: `assets/js/talent-search.js`
- Modify: `assets/js/talent-detail.js`
- Modify: `app/learner/data/Service/ProfileSharingService.php`
- Modify: `app/learner/ecosystem.php`
- Reuse: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Create test: `tests/enterprise_talent_consent_scope_test.php`

**Interfaces:**
- Produces: `GET /api/v1/businesses/me/talents`.
- Produces: `GET /api/v1/businesses/me/talents/{studentId}`.
- Produces: `POST /api/v1/businesses/me/talents/{studentId}/contact-requests`.
- Produces: `POST /api/v1/students/me/enterprise-profile-grants`, `DELETE /api/v1/students/me/enterprise-profile-grants/{grantId}`.
- Produces: `enterprise_talent_access_grants(studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, revokedAt)` và `enterprise_contact_requests(enterpriseId, studentId, idempotencyKey, status, message, requestedAt)`.
- Consumes: consent scope `enterprise_talent_discovery` hoặc `enterprise_talent_contact`, active/verified enterprise, grant đúng `enterpriseId`, grant chưa hết hạn/chưa revoke và partnership `approved` của trường sinh viên.

**Migration bắt buộc trước khi ghi scope mới:**

```sql
ALTER TABLE privacy_consents DROP CHECK chk_privacy_consents_scope;
ALTER TABLE privacy_consents
  ADD CONSTRAINT chk_privacy_consents_scope CHECK (
    scope IN (
      'assessment', 'skills', 'activity', 'evaluation', 'profile_share',
      'application_profile_share', 'enterprise_talent_discovery',
      'enterprise_talent_contact'
    )
  );

CREATE TABLE enterprise_talent_access_grants (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  consentId CHAR(36) NOT NULL,
  scope VARCHAR(50) NOT NULL,
  grantedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_enterprise_talent_grant (studentId, enterpriseId, scope),
  KEY idx_enterprise_talent_grant_lookup (enterpriseId, scope, revokedAt, expiresAt),
  CONSTRAINT fk_enterprise_talent_grant_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT fk_enterprise_talent_grant_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_enterprise_talent_grant_consent FOREIGN KEY (consentId) REFERENCES privacy_consents(id),
  CONSTRAINT chk_enterprise_talent_grant_scope CHECK (scope IN ('enterprise_talent_discovery', 'enterprise_talent_contact')),
  CONSTRAINT chk_enterprise_talent_grant_expiry CHECK (expiresAt > grantedAt)
);

CREATE TABLE enterprise_contact_requests (
  id CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  idempotencyKey CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  message VARCHAR(1000) NULL,
  requestedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_enterprise_contact_idempotency (enterpriseId, idempotencyKey),
  KEY idx_enterprise_contact_student_status (studentId, status),
  CONSTRAINT fk_enterprise_contact_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_enterprise_contact_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT chk_enterprise_contact_status CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))
);
```

Revoke/reactivate phải cập nhật grant hiện có trong transaction, ghi audit, đồng bộ consent tương ứng và không làm doanh nghiệp khác được cấp quyền. Không dùng `profile_share` token công khai để thay thế enterprise-scoped discovery grant.

**SQL cơ sở:**

```sql
SELECT student.id,
       CONCAT('Ứng viên ', LEFT(REPLACE(student.id, '-', ''), 8)) AS displayName,
       school.id AS schoolId,
       school.name AS schoolName,
       COUNT(DISTINCT verifiedSkill.id) AS verifiedSkillCount
FROM student_profiles student
INNER JOIN users learner ON learner.id = student.userId
INNER JOIN classes class ON class.id = student.classId
INNER JOIN schools school ON school.id = class.schoolId
INNER JOIN enterprises enterprise
  ON enterprise.id = :enterpriseId
 AND enterprise.status = 'active'
 AND enterprise.verificationStatus = 'verified'
INNER JOIN enterprise_talent_access_grants accessGrant
  ON accessGrant.studentId = student.id
 AND accessGrant.enterpriseId = enterprise.id
 AND accessGrant.scope = 'enterprise_talent_discovery'
 AND accessGrant.revokedAt IS NULL
 AND accessGrant.expiresAt > UTC_TIMESTAMP(6)
INNER JOIN privacy_consents consent
  ON consent.id = accessGrant.consentId
 AND consent.studentId = student.id
  AND consent.scope = 'enterprise_talent_discovery'
  AND consent.isGranted = 1
  AND consent.revokedAt IS NULL
INNER JOIN school_enterprise_partnerships partnership
  ON partnership.schoolId = school.id
 AND partnership.enterpriseId = enterprise.id
 AND partnership.status = 'approved'
LEFT JOIN student_skills verifiedSkill
  ON verifiedSkill.studentId = student.id
 AND verifiedSkill.verificationStatus = 'verified'
WHERE learner.status = 'active'
GROUP BY student.id, school.id, school.name;
```

**Response giới hạn dữ liệu:**

```json
{
  "items": [
    {
      "studentId": "9d6e7b31-9d77-49a2-8e64-2173fd4d7452",
      "displayName": "Nguyễn A.",
      "schoolName": "Trường A",
      "verifiedSkillCount": 3,
      "verifiedSkills": ["presentation", "php"],
      "contactAllowed": true
    }
  ]
}
```

- [ ] **Step 1: EN viết failing test** cho scope bị constraint cũ từ chối, chưa consent, grant hợp lệ, grant hết hạn, grant revoked, enterprise chưa verified, enterprise khác organization, partnership suspended và contact chưa có scope riêng.
- [ ] **Step 2: EN tạo migration** mở rộng `chk_privacy_consents_scope`, tạo `enterprise_talent_access_grants` và `enterprise_contact_requests`; verify migrate/replay trên disposable database, không apply tự động vào `talenthub`.
- [ ] **Step 3: EN cập nhật adapter/API learner bắt buộc** để ST grant/revoke theo `enterpriseId`, `scope`, `expiresAt`; scope discovery không mặc định cấp contact và không cấp quyền cho enterprise khác.
- [ ] **Step 4: EN xây repository/service** enforce partnership/grant/expiry ở SQL và service; chỉ trả allow-listed field, không SELECT/serialize email hoặc phone cho discovery projection.
- [ ] **Step 5: EN thay `$mockTalents`** bằng fetch API thật; bỏ nhúng `talents-mock-data` trên production.
- [ ] **Step 6: EN persist contact request** có CSRF, rate-limit, idempotency key, notification ST và discovery grant hợp lệ; chỉ cho direct contact/đọc email-phone sau khi ST cấp riêng scope `enterprise_talent_contact` và safeguarding policy cho phép.
- [ ] **Step 7: ST nghiệm thu** grant đúng enterprise, expiry, revoke, partnership suspended và projection; EN giữ ownership dù shared adapter nằm dưới `app/learner`.
- [ ] **Step 8: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/enterprise_talent_consent_scope_test.php`; kỳ vọng constraint/migration hợp lệ, enterprise B không đọc grant của A và revoke/expiry làm hồ sơ biến mất ngay.

**Nghiệm thu:** talent search/detail/contact dùng MySQL thật, không mock, không lộ PII và không nhìn thấy student ngoài scope.

### Task 7: LK-07 — Tạo quan hệ Nhà trường–Doanh nghiệp và targeting tin tuyển dụng

**Mức:** P1.

**Vai trò thiếu liên kết:** SC <-> EN -> TE/ST.

**Bằng chứng hiện trạng:** enterprise được platform verify nhưng không có quan hệ partnership theo school; `internship_posts` không có cột `audience`, không có bảng mapping `postId -> schoolId`, và learner/AI chưa có filter targeting. Do đó SC không biết doanh nghiệp nào đang tiếp cận student của mình, TE không có danh sách cơ hội hợp tác để mentor.

**Owner chính:** SC sở hữu duyệt partnership; EN sở hữu tạo yêu cầu và audience của post.

**Owner phối hợp:** TE đọc cơ hội của trường; ST kiểm tra chỉ thấy post đủ điều kiện.

**Files:**
- Create: `Database/migrations/20260825000200_create_school_enterprise_partnerships.php`
- Create: `src/Modules/School/Repository/SchoolPartnershipRepository.php`
- Create: `src/Modules/School/Service/SchoolPartnershipService.php`
- Create: `app/school/partnerships.php`
- Create: `app/enterprise/partnerships.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `src/Modules/Business/Repository/InternshipRepository.php`
- Modify: `src/Modules/Business/Service/InternshipService.php`
- Modify: `app/enterprise/internships/create.php`
- Modify: `app/learner/data/Database/DatabaseEcosystemRepository.php`
- Modify: `app/learner/ai/Sources/Database/DatabaseOpportunitySource.php`
- Create test: `tests/school_enterprise_partnership_audience_test.php`

**Interfaces:**
- Produces: `POST /api/v1/businesses/me/partnership-requests`.
- Produces: `GET /api/v1/schools/me/partnerships`.
- Produces: `PATCH /api/v1/schools/me/partnerships/{partnershipId}`.
- Produces: `internship_posts.audience = public | partner_schools`.
- Produces: `internship_post_target_schools(postId, schoolId, createdAt)`; `targetSchoolIds` phải được persist thay vì chỉ tồn tại trong request JSON.

**Contract database:**

```sql
CREATE TABLE school_enterprise_partnerships (
  id CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  requestedByUserId CHAR(36) NOT NULL,
  reviewedByUserId CHAR(36) NULL,
  reviewedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_enterprise_partnership (schoolId, enterpriseId),
  CONSTRAINT fk_school_enterprise_partnership_school FOREIGN KEY (schoolId) REFERENCES schools(id),
  CONSTRAINT fk_school_enterprise_partnership_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_school_enterprise_partnership_requester FOREIGN KEY (requestedByUserId) REFERENCES users(id),
  CONSTRAINT fk_school_enterprise_partnership_reviewer FOREIGN KEY (reviewedByUserId) REFERENCES users(id),
  CONSTRAINT chk_school_enterprise_partnership_status CHECK (status IN ('pending', 'approved', 'rejected', 'suspended'))
);

ALTER TABLE internship_posts
  ADD COLUMN audience VARCHAR(20) NOT NULL DEFAULT 'public',
  ADD CONSTRAINT chk_internship_post_audience CHECK (audience IN ('public', 'partner_schools')),
  ADD INDEX idx_internship_post_audience_status (audience, status, enterpriseId);

CREATE TABLE internship_post_target_schools (
  postId CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (postId, schoolId),
  KEY idx_internship_post_target_school (schoolId, postId),
  CONSTRAINT fk_internship_post_target_post FOREIGN KEY (postId) REFERENCES internship_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_internship_post_target_school FOREIGN KEY (schoolId) REFERENCES schools(id)
);
```

```json
{
  "title": "Backend PHP Internship",
  "audience": "partner_schools",
  "targetSchoolIds": ["8531d4a4-310d-4c36-bc58-eaa9a8ef275c"],
  "deadline": "2026-09-25T23:59:59Z"
}
```

- [ ] **Step 1: SC + EN viết failing test** partnership pending không cho post target; approved cho student đúng trường thấy; suspended ẩn post target khỏi ST/AI; migration persist `audience` và mọi `targetSchoolIds`.
- [ ] **Step 2: SC + EN tạo migration** đủ ba phần: `school_enterprise_partnerships`, `internship_posts.audience`, `internship_post_target_schools`; giữ post cũ `public` để tương thích.
- [ ] **Step 3: EN tạo request** chỉ khi enterprise active/verified; event gửi SC admin.
- [ ] **Step 4: SC approve/reject/suspend** với school ownership, audit và event ngược EN.
- [ ] **Step 5: EN validate/persist post audience** và mỗi `targetSchoolId` phải có partnership approved cùng enterprise; cập nhật post + mappings trong một transaction.
- [ ] **Step 6: SC + EN sửa read model learner/AI bắt buộc** để post `public` vẫn hiện bình thường, post `partner_schools` chỉ hiện khi target mapping và partnership đều hợp lệ; TE đọc cùng tập opportunity.
- [ ] **Step 7: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/school_enterprise_partnership_audience_test.php`; kỳ vọng public/partner/pending/suspended và post target school B bị ẩn khỏi student A.

**Nghiệm thu:** SC biết và quản lý doanh nghiệp nào được tiếp cận learner; EN không tự ý target school; TE/ST thấy cùng tập opportunity.

### Task 8: LK-08 — Bổ sung oversight và mentoring thực tập cho Nhà trường/Giáo viên

**Mức:** P1.

**Vai trò thiếu liên kết:** ST -> EN -> SC + TE.

**Bằng chứng hiện trạng:** application chỉ có student/enterprise visibility. SC và TE không có query dashboard theo `classes.schoolId`, không có assignment mentor, không nhận event khi application accepted/interview.

**Owner chính:** SC sở hữu dashboard/permission; TE sở hữu mentoring assignment và read-model.

**Owner phối hợp:** EN phát event trạng thái canonical; ST chỉ kiểm tra không lộ reviewer note hoặc PII.

**Files:**
- Create: `Database/migrations/20260825000300_create_internship_mentor_assignments.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/School/Repository/SchoolRepository.php`
- Create: `app/school/internships.php`
- Create: `src/Modules/Teacher/Repository/TeacherInternshipMentorRepository.php`
- Create: `src/Modules/Teacher/Service/TeacherInternshipMentorService.php`
- Create: `app/teacher/internships/index.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `src/Modules/Business/Repository/InternshipRepository.php`
- Create test: `tests/school_teacher_internship_oversight_scope_test.php`

**Interfaces:**
- Produces: `GET /api/v1/schools/me/internship-applications`.
- Produces: `POST /api/v1/schools/me/internship-applications/{applicationId}/mentor`.
- Produces: `GET /api/v1/teachers/me/internship-mentees`.
- Consumes: accepted/interview applications có student thuộc school hiện tại.

**Projection SC/TE:**

```sql
SELECT application.id,
       application.status,
       student.id AS studentId,
       school.id AS schoolId,
       post.title,
       enterprise.name AS enterpriseName
FROM internship_applications application
INNER JOIN student_profiles student ON student.id = application.studentId
INNER JOIN classes class ON class.id = student.classId
INNER JOIN schools school ON school.id = class.schoolId
INNER JOIN internship_posts post ON post.id = application.postId
INNER JOIN enterprises enterprise ON enterprise.id = post.enterpriseId
WHERE school.id = :schoolId;
```

- [ ] **Step 1: SC viết failing test** school A đọc application student A, không đọc student B; teacher chỉ đọc assignment của mình.
- [ ] **Step 2: SC tạo dashboard** aggregate submitted/interview/accepted/declined theo partner school và academic period.
- [ ] **Step 3: SC assign teacher mentor** chỉ khi teacher và student cùng `schoolId`; unique assignment theo application.
- [ ] **Step 4: TE xây mentee list** và action hướng dẫn, không được mutate hiring decision của EN.
- [ ] **Step 5: EN phát status event** tới SC/TE chỉ nếu policy consent/oversight cho phép; student vẫn giữ quyền rút đơn.
- [ ] **Step 6: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/school_teacher_internship_oversight_scope_test.php`; kỳ vọng cross-school/cross-mentor trả `403` hoặc `404`.

**Nghiệm thu:** ST ứng tuyển, EN xét tuyển, SC theo dõi, TE hỗ trợ; không role nào vượt authority của role còn lại.

### Task 9: LK-09 — Hoàn thiện chuỗi dự án, thành viên sinh viên và tài trợ doanh nghiệp

**Mức:** P1.

**Vai trò thiếu liên kết:** SC -> TE -> ST -> EN -> SC.

**Bằng chứng hiện trạng:** database có `projects`, `project_members`, `project_sponsorships`, `payment_orders`; `BusinessWorkflowRepository::projects()` chỉ đọc project `in_progress` có `fundingGoal`, nhưng không tìm thấy runtime `INSERT INTO projects` hoặc project-management UI của SC/TE. `app/enterprise/sponsorships/index.php` dùng `getMockProjects()`. Backend hiện chỉ tạo payment `pending`; chưa có callback/webhook/service xác nhận `payment_orders.paymentStatus='paid'`, nên workflow tài trợ chưa thể hoàn tất.

**Owner chính:** SC tạo/publish project; TE quản mentor/member; EN thay mock bằng API projects/sponsorships/payments thật.

**Owner phối hợp:** ST kiểm tra project membership và evidence trong talent passport.

**Files:**
- Create: `src/Modules/School/Repository/SchoolProjectRepository.php`
- Create: `src/Modules/School/Service/SchoolProjectService.php`
- Create: `app/school/projects.php`
- Create: `src/Modules/Teacher/Repository/TeacherProjectRepository.php`
- Create: `app/teacher/projects/index.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `src/Modules/Business/Repository/BusinessWorkflowRepository.php`
- Modify: `src/Modules/Business/Service/BusinessWorkflowService.php`
- Create: `src/Modules/Business/Service/PaymentConfirmationService.php`
- Modify: `app/enterprise/sponsorships/index.php`
- Modify: `assets/js/enterprise-sponsorships.js`
- Reuse: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Create test: `tests/four_role_project_sponsorship_workflow_test.php`

**Interfaces:**
- Produces: `POST /api/v1/schools/me/projects`.
- Produces: `PATCH /api/v1/schools/me/projects/{projectId}`.
- Produces: `POST /api/v1/teachers/me/projects/{projectId}/members`.
- Reuses: `GET /api/v1/projects`, `POST /api/v1/businesses/me/sponsorships`, `POST /api/v1/businesses/me/payments`; kiểm tra route exact trong bootstrap trước khi nối UI.
- Produces: callback/provider-confirmation adapter xác thực chữ ký, idempotency và cập nhật cùng transaction `payment_orders.paymentStatus='paid'`, `payment_orders.paidAt`, `project_sponsorships.status='paid'`.

**Payload tạo project:**

```json
{
  "title": "Trạm quan trắc môi trường học đường",
  "category": "stem",
  "mentorTeacherId": "a34e4d2b-31d8-4f9a-9b92-911f730dce41",
  "description": "Nhóm học sinh thiết kế thiết bị đo chất lượng không khí.",
  "fundingGoal": "25000000.00",
  "startAt": "2026-09-01T00:00:00Z",
  "endAt": "2026-12-01T00:00:00Z",
  "status": "in_progress"
}
```

**Workflow đích:**

```text
SC tạo project + fundingGoal
  -> SC gán TE mentor cùng trường
  -> TE thêm ST members cùng trường
  -> EN verified đọc project thật
  -> EN pledge sponsorship
  -> EN tạo payment order
  -> payment xác nhận paid
  -> SC nhận aggregate tài trợ
  -> TE/ST nhận notification và project evidence
```

- [ ] **Step 1: SC tạo test** project thuộc school A không cho teacher/student school B join.
- [ ] **Step 2: SC tạo project CRUD** có `schoolId`, mentor teacher validation, funding goal > 0, status và audit.
- [ ] **Step 3: TE quản member** bằng `project_members`, role/contribution, unique active membership, notification ST.
- [ ] **Step 4: EN thay `getMockProjects()`** bằng API project thật và existing sponsorship/payment service; thêm `PaymentConfirmationService` xác thực provider reference/signature, chống replay callback và chuyển pending -> paid atomically.
- [ ] **Step 5: SC/TE/EN bổ sung event** pledged/payment paid; ST kiểm tra passport projects và notification. Provider callback trùng không tạo payment/event thứ hai.
- [ ] **Step 6: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/four_role_project_sponsorship_workflow_test.php`; kỳ vọng 4 actor, một project, một pledge, một payment và projection đồng bộ.

**Nghiệm thu:** EN tài trợ project thật do SC tạo, TE hướng dẫn, ST tham gia; mọi dashboard thấy cùng `projectId` và sponsorship status.

### Task 10: LK-10 — Thống nhất dashboard/analytics theo sự thật dữ liệu của 4 vai trò

**Mức:** P1.

**Vai trò thiếu liên kết:** SC <-> TE <-> ST <-> EN.

**Bằng chứng hiện trạng:** school service còn method `topStudentsForDemo`; enterprise talent/analytics có mock; SC chưa tổng hợp accepted internship/sponsorship; TE dashboard chưa có mentoring/partner metrics.

**Owner chính:** SC định nghĩa metric và school scope; EN thay analytics mock.

**Owner phối hợp:** TE bổ sung teacher-specific filters; ST chỉ đối chiếu cùng entity/count.

**Files:**
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/School/Repository/SchoolRepository.php`
- Modify: `app/school/index.php`
- Modify: `app/school/analytics.php`
- Modify: `app/teacher/index.php`
- Modify: `app/teacher/includes/dashboard-data.php`
- Modify: `app/enterprise/index.php`
- Modify: `app/enterprise/analytics.php`
- Modify: `assets/js/enterprise-analytics.js`
- Create test: `tests/four_role_dashboard_metric_consistency_test.php`

**Metric contract:**

```json
{
  "schoolId": "20000000-0000-4000-8000-000000000001",
  "schoolName": "THPT Nguyễn Trãi",
  "activeStudents": 13,
  "activeTeachers": 6,
  "publishedActivities": 3,
  "approvedRegistrations": 4,
  "confirmedCheckins": 10,
  "publishedAssessments": 10,
  "verifiedSkills": 45,
  "approvedEnterprisePartners": 0,
  "activeInternshipPosts": 0,
  "acceptedInternshipApplications": 0,
  "activeProjects": 0,
  "paidSponsorshipAmount": "0.00"
}
```

Payload trên là snapshot đã kiểm tra của THPT Nguyễn Trãi ngày 2026-08-25; không dùng UUID minh họa không tồn tại và không gán tổng platform cho một trường. `publishedActivities` chỉ count `status='published'`, `approvedRegistrations` chỉ count `status='approved'`, `verifiedSkills` chỉ count `verificationStatus='verified'` theo school ownership.

| Trường | activeStudents | activeTeachers | publishedActivities | approvedRegistrations | confirmedCheckins | publishedAssessments | verifiedSkills |
|---|---:|---:|---:|---:|---:|---:|---:|
| Đại học FPT | 15 | 4 | 3 | 4 | 10 | 10 | 32 |
| THPT Nguyễn Trãi | 13 | 6 | 3 | 4 | 10 | 10 | 45 |
| TalentHub Test School | 1 | 1 | 8 | 0 | 0 | 0 | 0 |

Tổng `activities=26` gồm 14 `published`, 5 `ongoing`, 7 `completed`. Tổng `activity_registrations=40` gồm 8 `approved`, 20 `attended`, 6 `pending`, 6 `cancelled`. Các số toàn platform chỉ được trả từ endpoint/platform metric có quyền tương ứng.

- [ ] **Step 1: SC viết failing test** cùng một check-in/assessment/application accepted/project paid xuất hiện đúng trên dashboard SC, TE, ST, EN theo scope.
- [ ] **Step 2: SC thay aggregate demo** bằng query thật, tên metric và điều kiện count được tài liệu hóa.
- [ ] **Step 3: EN thay JS mock** bằng endpoint DB-backed; enterprise chỉ count post/application/sponsorship của chính mình.
- [ ] **Step 4: TE count** activity/mentee/project của chính teacher; không dùng school-wide metrics nếu không có quyền admin.
- [ ] **Step 5: ST đối chiếu** số liệu learner với detail pages; không thay UI nếu endpoint hiện có đã đủ.
- [ ] **Step 6: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/four_role_dashboard_metric_consistency_test.php`; kỳ vọng cùng event sinh cùng fact, mỗi role nhận projection đúng tenant.

**Nghiệm thu:** dashboard không hiển thị KPI giả; số liệu explainable từ bảng thật và đồng bộ sau event.

### Task 11: LK-11 — Thay mật khẩu tạm bằng invitation token cho Giáo viên/Sinh viên

**Mức:** P1.

**Vai trò thiếu liên kết:** SC -> TE, SC -> ST.

**Bằng chứng hiện trạng:** `SchoolDashboardService::inviteTeacher()` trả `generatedPassword`; `app/school/teachers.php` hiển thị plaintext để SC gửi thủ công. Đây là onboarding yếu và không có xác nhận người nhận/expiry rõ ràng.

**Owner chính:** SC.

**Owner phối hợp:** TE kiểm tra nhận lời mời; ST chỉ test nếu SC dùng cùng workflow khi tạo student.

**Files:**
- Create: `Database/migrations/20260825000400_create_account_invitations.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/School/Repository/SchoolRepository.php`
- Modify: `app/school/teachers.php`
- Modify: `app/school/student-edit.php`
- Create: `accept-invitation.php`
- Modify: `src/Bootstrap/Application.php`
- Create test: `tests/school_account_invitation_lifecycle_test.php`

**Interfaces:**
- Produces: `account_invitations(userId, invitedByUserId, schoolId, tokenHash, expiresAt, acceptedAt, revokedAt)`.
- Produces: `POST /api/v1/auth/invitations/{token}/accept`.
- Consumes: hashed token, expiry <= 72 giờ, password policy hiện hành, role assignment từ inviter school.

**Response cho SC:**

```json
{
  "userId": "613b72be-0b66-4237-a60d-05df1552b15d",
  "profileId": "a34e4d2b-31d8-4f9a-9b92-911f730dce41",
  "invitationStatus": "pending",
  "expiresAt": "2026-08-28T10:00:00Z"
}
```

- [ ] **Step 1: SC viết failing test** token hợp lệ, token hết hạn, token đã accept, token revoked và teacher trường khác.
- [ ] **Step 2: SC lưu hash token** và gửi raw token một lần qua kênh email/invitation delivery; không trả password plaintext cho UI/API.
- [ ] **Step 3: TE/ST đặt password** khi accept; session cũ không được tự động gán sai role.
- [ ] **Step 4: SC audit** invite, resend, revoke, accept; rate-limit việc gửi lại.
- [ ] **Step 5: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/school_account_invitation_lifecycle_test.php`; kỳ vọng token one-time và response không có `generatedPassword`.

**Nghiệm thu:** SC tạo account an toàn; TE/ST nhận đúng role/school; không hiển thị hoặc lưu log mật khẩu tạm.

### Task 12: LK-12 — Sửa harness E2E 4 vai trò và thiết lập cổng release

**Mức:** P0.

**Vai trò thiếu liên kết:** SC + TE + ST + EN.

**Bằng chứng hiện trạng:** `tests/student_portal_four_role_e2e_mysql_test.php` hard-code source `talenthub_local`, yêu cầu baseline `61` bảng và `29` migration, dùng root password rỗng và tự `CREATE/DROP DATABASE`. Workspace hiện tại dùng `talenthub`, `69` bảng, `38` migration; vì vậy bật biến test thôi chưa đủ để chạy an toàn hoặc đúng.

**Owner chính:** SC chỉnh fixture trường/teacher/student; EN chỉnh fixture enterprise/application/project; hai bên cùng ownership hạ tầng test.

**Owner phối hợp:** TE thêm QR/assessment evidence; ST viết checklist nghiệm thu, không tự sửa module domain khác.

**Files:**
- Modify: `tests/student_portal_four_role_e2e_mysql_test.php`
- Modify: `tests/Integration/EnterpriseApplicationLifecycleTest.php`
- Modify: `tests/Integration/SchoolDashboardApiTest.php`
- Modify: `tests/notification_domain_producer_test.php`
- Create: `tests/four_role_release_gate_test.php`

**Interfaces:**
- Consumes: `APP_ENV=test`, `TALENTHUB_DISPOSABLE_TEST_DB=1`, explicit `TALENTHUB_E2E_SOURCE_DB`, explicit disposable prefix, DB credentials từ environment.
- Produces: report JSON chứa actor counts, migration counts, tenant isolation, event matrix, snapshot equality và cleanup confirmation.

**Preflight thay cho baseline hard-code:**

```php
$sourceDatabase = (string) getenv('TALENTHUB_E2E_SOURCE_DB');
$allowListedSource = ['talenthub', 'talenthub_local'];

if (!in_array($sourceDatabase, $allowListedSource, true)) {
    throw new RuntimeException('E2E source database must be explicitly allow-listed.');
}

$targetDatabase = 'talenthub_four_role_rehearsal_' . gmdate('YmdHis');

if (!preg_match('/\Atalenthub_four_role_rehearsal_\d{14}\z/', $targetDatabase)) {
    throw new RuntimeException('Unsafe disposable database name.');
}

$sourceBefore = snapshotDatabase($sourcePdo);
$expectedMigrationCount = countAppliedMigrations($sourcePdo);
$expectedTableCount = countTables($sourcePdo);
```

**Kịch bản E2E tối thiểu:**

```text
1. SC-A tạo class và invite TE-A/ST-A.
2. SC-B tạo class và invite TE-B/ST-B.
3. TE-A tạo activity school_only.
4. ST-A đăng ký; ST-B bị từ chối.
5. TE-A duyệt ST-A; TE-B bị từ chối.
6. TE-A tạo QR; ST-A check-in; SC-A count +1; SC-B count +0.
7. TE-A publish assessment; ST-A nhận notification/badge; AI thấy evidence.
8. EN-A xin partnership SC-A; SC-A approve.
9. EN-A publish post target SC-A; ST-A thấy; ST-B không thấy.
10. ST-A cấp consent và apply; EN-A nhận notification; EN-B bị từ chối.
11. EN-A review/accept; ST-A, SC-A và TE-A mentor nhận projection phù hợp.
12. SC-A tạo project; TE-A thêm ST-A; EN-A sponsor/payment.
13. Dashboard cả 4 vai trò cùng đọc entity thật theo scope.
14. Replay event không tạo duplicate; source DB trước/sau giữ nguyên.
15. Chỉ cleanup disposable schema đã validate prefix; không cleanup source.
```

- [ ] **Step 1: SC + EN viết failing preflight test** cho source không allow-list, target trùng source, credentials thiếu, prefix sai; assert không thực hiện destructive SQL.
- [ ] **Step 2: Bỏ baseline hard-code** `61/29` và `talenthub_local`; snapshot source động nhưng chỉ khi source explicit và read-only.
- [ ] **Step 3: Tách root credential** khỏi code; dùng env user ít đặc quyền phù hợp hoặc CI-provisioned disposable database.
- [ ] **Step 4: TE bổ sung** cross-school activity, QR, assessment, notification cases.
- [ ] **Step 5: EN bổ sung** partnership, audience, consent, application endpoint parity, sponsorship cases.
- [ ] **Step 6: ST chạy checklist** theo portal: activity, check-in, evaluation, ecosystem, notifications, AI evidence.
- [ ] **Step 7: Chỉ trên môi trường test đã cấu hình**, chạy:

```powershell
$env:APP_ENV = 'test'
$env:TALENTHUB_DISPOSABLE_TEST_DB = '1'
$env:TALENTHUB_E2E_SOURCE_DB = 'talenthub'
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/four_role_release_gate_test.php
```

**Nghiệm thu:** test trả PASS cho đủ 4 actor, không hard-code schema cũ, source snapshot trước/sau giống nhau, chỉ xóa disposable schema đã kiểm tra.

### Task 13: LK-13 — Áp dụng safeguarding cho học sinh chưa thành niên trước khi Doanh nghiệp xem/nhận hồ sơ

**Mức:** P0.

**Vai trò thiếu liên kết:** SC + EN -> ST; TE phối hợp mentoring/giám sát.

**Bằng chứng hiện trạng:** database có 8 học sinh dưới 18 tuổi tại THPT Nguyễn Trãi; `privacy_consents` và `student_profile_shares` hiện đều có 0 record. `DatabaseApplicationCommandRepository::buildSnapshot()` đưa `fullName`, `email`, `phone`, `dateOfBirth` vào snapshot ứng tuyển. Luồng submit mới kiểm tra consent chung `application_profile_share`, chưa thấy phân biệt học sinh phổ thông/chưa thành niên, điều kiện `internship_posts.educationLevel`, consent phụ huynh hoặc chặn enterprise contact trực tiếp. `BusinessWorkflowRepository::apply()` delegate trực tiếp sang repository learner này nên thay đổi snapshot/enforcement không phải tùy chọn.

**Owner chính:** SC định nghĩa safeguarding/guardian consent theo trường; EN validate eligibility trước discovery/apply/contact và giảm dữ liệu snapshot.

**Owner phối hợp:** TE chỉ định mentor/đầu mối nhà trường; ST kiểm tra flow và chỉ thêm UI nếu cần xin consent mới.

**Files:**
- Create: `Database/migrations/20260825000500_create_student_safeguarding_policies.php`
- Create: `src/Modules/School/Service/StudentSafeguardingService.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Modify: `src/Modules/Business/Service/InternshipService.php`
- Modify: `src/Modules/Business/Service/BusinessWorkflowService.php`
- Modify: `src/Modules/Business/Repository/InternshipRepository.php`
- Modify: `app/learner/data/Database/DatabaseApplicationCommandRepository.php`
- Modify: `app/learner/data/Service/ProfileSharingService.php`
- Modify only if required: `app/learner/ecosystem.php`
- Create test: `tests/student_minor_enterprise_safeguarding_test.php`

**Interfaces:**
- Consumes: `student_profiles.dateOfBirth`, `classes.gradeLevel`, `schools.level`, `internship_posts.educationLevel`, consent scopes và school partnership.
- Produces: `StudentSafeguardingService::eligibility(string $studentId, string $enterpriseId, string $postId): array`.
- Produces: machine-readable result `eligible`, `ageBand`, `guardianConsentRequired`, `schoolApprovalRequired`, `contactMode`, `allowedSnapshotFields`.
- Produces: `student_safeguarding_policies(schoolId, minimumDirectContactAge, guardianConsentRequired, schoolApprovalRequired)`, `student_guardian_consents(studentId, enterpriseId, grantedByUserId, grantedAt, expiresAt, revokedAt)` và `student_enterprise_school_approvals(studentId, enterpriseId, approvedByUserId, approvedAt, expiresAt, revokedAt)`.

**Migration safeguarding tối thiểu:**

```sql
CREATE TABLE student_safeguarding_policies (
  schoolId CHAR(36) NOT NULL,
  minimumDirectContactAge TINYINT UNSIGNED NOT NULL DEFAULT 18,
  guardianConsentRequired TINYINT UNSIGNED NOT NULL DEFAULT 1,
  schoolApprovalRequired TINYINT UNSIGNED NOT NULL DEFAULT 1,
  updatedByUserId CHAR(36) NOT NULL,
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (schoolId),
  CONSTRAINT fk_safeguarding_policy_school FOREIGN KEY (schoolId) REFERENCES schools(id),
  CONSTRAINT fk_safeguarding_policy_actor FOREIGN KEY (updatedByUserId) REFERENCES users(id),
  CONSTRAINT chk_safeguarding_direct_contact_age CHECK (minimumDirectContactAge BETWEEN 13 AND 25),
  CONSTRAINT chk_safeguarding_guardian_required CHECK (guardianConsentRequired IN (0, 1)),
  CONSTRAINT chk_safeguarding_school_required CHECK (schoolApprovalRequired IN (0, 1))
);

CREATE TABLE student_guardian_consents (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  grantedByUserId CHAR(36) NOT NULL,
  grantedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  PRIMARY KEY (id),
  KEY idx_guardian_consent_scope (studentId, enterpriseId, revokedAt, expiresAt),
  CONSTRAINT fk_guardian_consent_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT fk_guardian_consent_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_guardian_consent_actor FOREIGN KEY (grantedByUserId) REFERENCES users(id),
  CONSTRAINT chk_guardian_consent_expiry CHECK (expiresAt > grantedAt)
);

CREATE TABLE student_enterprise_school_approvals (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  enterpriseId CHAR(36) NOT NULL,
  approvedByUserId CHAR(36) NOT NULL,
  approvedAt DATETIME(6) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  PRIMARY KEY (id),
  KEY idx_school_approval_scope (studentId, enterpriseId, revokedAt, expiresAt),
  CONSTRAINT fk_school_approval_student FOREIGN KEY (studentId) REFERENCES student_profiles(id),
  CONSTRAINT fk_school_approval_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id),
  CONSTRAINT fk_school_approval_actor FOREIGN KEY (approvedByUserId) REFERENCES users(id),
  CONSTRAINT chk_school_approval_expiry CHECK (expiresAt > approvedAt)
);
```

`grantedByUserId` phải là actor guardian đã được hệ thống xác thực hoặc actor school được policy cho phép xác nhận consent guardian đã kiểm chứng; không coi mọi user ID là guardian hợp lệ. Approval/consent được scope theo student + enterprise, có thời hạn và revoke độc lập.

**Policy response ví dụ:**

```json
{
  "eligible": false,
  "ageBand": "minor",
  "guardianConsentRequired": true,
  "guardianConsentGranted": false,
  "schoolApprovalRequired": true,
  "contactMode": "school_mediated",
  "allowedSnapshotFields": [
    "displayName",
    "schoolName",
    "verifiedSkills",
    "projects",
    "experience"
  ],
  "blockedReason": "GUARDIAN_CONSENT_REQUIRED"
}
```

**Snapshot tối thiểu cho học sinh chưa thành niên:**

```json
{
  "student": {
    "displayName": "Nguyễn A.",
    "schoolName": "Trường A",
    "ageBand": "minor"
  },
  "verifiedSkills": ["presentation"],
  "schoolContact": {
    "department": "Phòng hướng nghiệp",
    "contactMode": "school_mediated"
  }
}
```

- [ ] **Step 1: SC + EN viết failing test** cho student chưa thành niên không consent phụ huynh, đã consent, consent revoked, post không phù hợp cấp học, enterprise contact trực tiếp và adult student hợp lệ.
- [ ] **Step 2: SC tạo migration và lưu school/guardian policy** cùng guardian consent và school approval theo `studentId + enterpriseId`, actor hợp lệ, expiry, revoke, audit; không giả định một consent ứng tuyển bao trùm mọi mục đích.
- [ ] **Step 3: EN enforce eligibility** ở search/detail/apply/contact và trước khi tạo enterprise-specific profile grant; không chỉ ẩn nút ở frontend.
- [ ] **Step 4: EN + SC sửa bắt buộc `DatabaseApplicationCommandRepository::buildSnapshot()`** để áp dụng field allow-list trước persist: mặc định không gửi phone, full date of birth, private email hoặc direct-contact details của minor khi policy chưa cho phép.
- [ ] **Step 5: TE nhận mentoring notification** khi student minor được tham gia chương trình đã approve; không dùng contact data ngoài phạm vi nhiệm vụ.
- [ ] **Step 6: ST chỉ sở hữu phần UI** nếu cần hiển thị `GUARDIAN_CONSENT_REQUIRED`, `SCHOOL_APPROVAL_REQUIRED` hoặc nút yêu cầu consent; EN/SC vẫn sở hữu thay đổi repository learner và logic enforce.
- [ ] **Step 7: Chạy** `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/student_minor_enterprise_safeguarding_test.php`; kỳ vọng minor thiếu consent bị từ chối và snapshot không chứa `phone`, `email`, `dateOfBirth`.

**Nghiệm thu:** học sinh phổ thông và sinh viên đại học có policy phù hợp; EN không tiếp cận PII/contact ngoài consent, TE/SC có giám sát khi cần.

---

## 6. Phân công gửi trực tiếp cho từng thành viên

### 6.1. Thành viên Nhà trường — SC

**Ưu tiên 1:** sửa toàn bộ POST thiếu CSRF và đưa update teacher vào service (`LK-02`).

**Ưu tiên 2:** chốt `school_only/public`, ownership class/school, chính sách cross-school, policy safeguarding, guardian consent và school approval theo từng enterprise (`LK-01`, `LK-13`).

**Ưu tiên 3:** xây partnership approval, internship oversight/mentor assignment, project CRUD và dashboard thật (`LK-07`, `LK-08`, `LK-09`, `LK-10`).

**Ưu tiên 4:** thay mật khẩu tạm bằng invite token và phối hợp sửa E2E (`LK-11`, `LK-12`).

**Bàn giao cho TE:** `schoolId`, policy visibility, mentor eligibility, projectId, danh sách partner approved.

**Bàn giao cho EN:** partnership status, targetable school IDs, project list/funding goal và projection oversight đã được phép.

**Bàn giao cho ST:** cung cấp acceptance matrix để test activity/audience/consent. Nếu workflow SC bắt buộc đổi file learner/AI, SC vẫn chịu owner code và ghi rõ file; không mặc định giao backend cho ST.

### 6.2. Thành viên Giáo viên — TE

**Ưu tiên 1:** thêm visibility khi tạo activity, phối hợp sửa contract/caller learner bắt buộc và dùng school policy thống nhất (`LK-01`).

**Ưu tiên 2:** phát event đúng khi ST đăng ký/chờ duyệt/hủy và khi publish assessment (`LK-04`, `LK-05`).

**Ưu tiên 3:** xây skill verification, mentor internship, safeguarding học sinh và project membership cùng trường (`LK-05`, `LK-08`, `LK-09`, `LK-13`).

**Bàn giao cho SC:** aggregate hoạt động, confirmed hours, published assessments, verified skills và mentee progress.

**Bàn giao cho EN:** chỉ cung cấp evidence thông qua projection đã consent; không gửi raw assessment/private comment.

**Bàn giao cho ST:** activity statuses, QR, published evaluation, badge trigger, notification deep-link.

### 6.3. Thành viên Doanh nghiệp — EN

**Ưu tiên 1:** hợp nhất review internship, transaction/history/notification, API payload và enforce safeguarding/age eligibility (`LK-03`, `LK-13`).

**Ưu tiên 2:** mở notification infrastructure cho recipient enterprise/teacher/school cùng owner từng domain (`LK-04`).

**Ưu tiên 3:** mở rộng constraint consent, tạo grant theo `studentId + enterpriseId + scope` có expiry/revoke, persist contact request, bỏ mock talent search/detail/contact và enforce enterprise verification + school partnership (`LK-06`).

**Ưu tiên 4:** partnership request, persist `internship_posts.audience` + target school mappings, project/sponsorship/payment DB-backed, analytics theo đúng scope và status (`LK-07`, `LK-09`, `LK-10`).

**Ưu tiên 5:** cập nhật fixture enterprise/application/payment trong E2E (`LK-12`).

**Bàn giao cho SC:** partnership request/status, internship school audience, accepted-placement aggregate, paid sponsorship fact.

**Bàn giao cho TE:** opportunity của partner school, mentor-visible hiring status và verified talent projection.

**Bàn giao cho ST:** active post, grant/revoke theo enterprise, consent requirements, application status/history, notification, expiry behavior. EN giữ ownership của shared learner repository nếu sửa snapshot hoặc consent adapter.

### 6.4. Phần Sinh viên — ST

**Không nhận mặc định:** sửa CSRF trường, tạo project trường, review application enterprise, quản trị teacher, analytics enterprise.

**Thay đổi learner bắt buộc nhưng owner vẫn thuộc domain phát sinh:**

1. `ActivityRepository`, repository/command/caller learner và AI phải nhận `studentId`, áp dụng visibility và tách read/register status; owner SC + TE.
2. `ProfileSharingService` và API/UI learner phải hỗ trợ grant/revoke `enterprise_talent_discovery`/`enterprise_talent_contact` theo `enterpriseId` và expiry; owner EN.
3. Ecosystem/AI phải filter post `audience` + target school; owner SC + EN. Phần trình bày label/UI có thể giao ST nếu đã chốt contract.
4. `DatabaseApplicationCommandRepository::buildSnapshot()` phải áp dụng safeguarding trước khi persist PII; owner SC + EN dù file thuộc `app/learner`.
5. Event `teacher_assessment_published` cần route `/app/learner/evaluation.php` trong deep-link allow-list nếu đi qua publisher learner; owner TE + EN.
6. ST chỉ sở hữu phần UI thuần túy như hiển thị `GUARDIAN_CONSENT_REQUIRED`, `SCHOOL_APPROVAL_REQUIRED` hoặc thao tác grant đã có backend contract; không sở hữu validation/security domain của SC/TE/EN.

**Checklist ST:**

- [ ] Activity list/detail/register/AI trả cùng eligibility.
- [ ] Duyệt/hủy/check-in/assessment cập nhật đúng notification.
- [ ] Enterprise chỉ thấy hồ sơ khi ST đã consent và không thấy sau revoke.
- [ ] Enterprise B không đọc grant của enterprise A; grant hết hạn/suspended partnership bị từ chối; discovery grant không tự cấp direct-contact permission.
- [ ] Tin partner school chỉ hiển thị đúng trường.
- [ ] Application state/history giống enterprise portal.
- [ ] SC/TE chỉ thấy oversight đúng scope, không lộ private data.
- [ ] Project membership và sponsorship không tạo hồ sơ mock.
- [ ] Học sinh chưa thành niên không lộ email/số điện thoại/ngày sinh và không apply khi thiếu approval phù hợp.

## 7. Thứ tự triển khai để tránh sửa chồng chéo

### Giai đoạn A — Security và source of truth

1. `LK-02`: SC sửa CSRF/service-layer.
2. `LK-01`: SC + TE chốt school visibility và tự sở hữu thay đổi bắt buộc ở contract/filter learner.
3. `LK-03`: EN hợp nhất review service trước khi nhóm dựa vào status event.
4. `LK-13`: SC + EN chốt safeguarding/minor consent trước khi mở talent search hoặc targeted hiring.
5. `LK-12`: SC + EN sửa preflight E2E để tạo môi trường test an toàn.

### Giai đoạn B — Event và evidence

6. `LK-04`: mở notification role-aware.
7. `LK-05`: TE chuẩn hóa published evidence/verified skills và phân biệt seed với runtime.
8. `LK-11`: SC chuẩn hóa invitation lifecycle.

### Giai đoạn C — Doanh nghiệp kết nối trường và sinh viên

9. `LK-07`: SC + EN hoàn thiện partnership, cột audience và target-school mapping.
10. `LK-06`: EN nối talent discovery với migration consent, grant theo enterprise, contact request, partnership và teacher evidence.
11. `LK-08`: SC + TE xây oversight/mentoring dùng event canonical của EN.

### Giai đoạn D — Project, sponsorship và release

12. `LK-09`: SC tạo project, TE thêm member, EN sponsor/pay.
13. `LK-10`: thay mọi KPI mock bằng aggregate đúng school/status.
14. `LK-12`: chạy full release gate 4 role trên disposable database.

## 8. Quy tắc handoff và review

Mỗi thành viên khi bàn giao phải ghi đúng format:

```markdown
### Handoff LK-XX

- Owner: SC | TE | EN.
- Vai trò bị ảnh hưởng: SC, TE, ST, EN.
- File đã sửa: đường dẫn cụ thể.
- API added/changed: HTTP method + exact path.
- Database added/changed: table, column, FK, unique index.
- Producer event: eventType, eventKey, recipientRole, deepLink.
- Permissions: code, ownership condition.
- Breaking change: có/không; cách giữ backward compatibility.
- File learner bắt buộc sửa: không | danh sách file + owner SC/TE/EN + lý do cụ thể.
- ST cần sửa UI: không | danh sách file giao riêng cho ST.
- Test: command + kết quả.
- Rollback/deploy: migration forward-only, feature flag hoặc compatibility adapter.
```

Không chấp nhận handoff chỉ ghi “đã nối database” hoặc “đã thêm tính năng”; phải chứng minh role nào tạo dữ liệu, role nào đọc dữ liệu, trạng thái và scope nào được áp dụng.

## 9. Definition of Done cho production 4 vai trò

- [ ] Không còn mock talent/project/KPI bị hiển thị như production data.
- [ ] Tất cả form/API ghi dữ liệu có CSRF, RBAC, ownership và audit.
- [ ] Activity list/register/AI nhất quán trường và visibility; list được xem completed nhưng register chỉ nhận `published` còn registration window.
- [ ] Application review chỉ có một source of truth; route legacy dùng adapter.
- [ ] Mọi event cần phản hồi có recipient đúng role và notification idempotent.
- [ ] Migration consent cho phép `enterprise_talent_discovery`/`enterprise_talent_contact`; EN chỉ đọc hồ sơ khi active/verified, grant đúng enterprise, chưa expiry/revoke và partnership approved.
- [ ] Contact request được persist/idempotent; discovery consent không tự động cho direct contact/email/phone.
- [ ] Học sinh chưa thành niên được age-gate, guardian/school approval theo policy và snapshot tối thiểu hóa PII.
- [ ] SC approve partnership trước khi EN target learner; `audience` và target-school mappings được persist, learner/AI enforce cùng điều kiện.
- [ ] SC và TE chỉ đọc oversight/mentoring đúng trường và đúng assignment.
- [ ] Project do SC tạo, TE mentor, ST member, EN sponsor; dashboard đọc cùng record.
- [ ] Invite teacher/student không hiển thị mật khẩu plaintext.
- [ ] Dashboard KPI dùng đúng school/status; dữ liệu verified seed không bị diễn giải thành workflow runtime production.
- [ ] Test cross-school, cross-enterprise, cross-teacher, expired/revoked consent và guardian/school approval đều pass.
- [ ] Full E2E 4 vai trò pass trên disposable schema; database `talenthub` không thay đổi.

## 10. Bản đồ file hiện có làm căn cứ kiểm tra

```text
src/Bootstrap/Application.php
src/Bootstrap/SchoolAppContext.php
src/Modules/School/Repository/SchoolRepository.php
src/Modules/School/Service/SchoolDashboardService.php
src/Modules/School/Service/SchoolCheckinAggregateService.php
src/Modules/Teacher/Repository/TeacherActivityRepository.php
src/Modules/Teacher/Repository/TeacherGradingRepository.php
src/Modules/Teacher/Repository/TeacherQrSessionRepository.php
src/Modules/Business/Repository/InternshipRepository.php
src/Modules/Business/Repository/BusinessWorkflowRepository.php
src/Modules/Business/Service/InternshipService.php
src/Modules/Business/Service/BusinessWorkflowService.php
Database/migrations/20260821000100_create_student_passport_sharing.php
Database/migrations/20260821000200_create_student_certificates_and_projects.php
Database/seeds/Demo/CompleteAiDemoSeeder.php
app/learner/data/Contracts/ActivityRepository.php
app/learner/data/Database/DatabaseActivityRepository.php
app/learner/data/Database/DatabaseActivityCommandRepository.php
app/learner/data/Database/DatabaseApplicationCommandRepository.php
app/learner/data/Database/DatabaseTalentPassportRepository.php
app/learner/data/Service/NotificationService.php
app/learner/data/Service/ProfileSharingService.php
app/learner/includes/activity-data.php
app/learner/includes/ecosystem-data.php
app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php
app/learner/ai/Sources/Database/DatabaseOpportunitySource.php
app/learner/ai/Sources/Database/DatabasePublishedEvaluationSource.php
app/school/teachers.php
app/school/student-edit.php
app/school/teacher-edit.php
app/teacher/activities/index.php
app/teacher/assessments/index.php
app/enterprise/talents.php
app/enterprise/sponsorships/index.php
assets/js/talent-search.js
assets/js/talent-detail.js
tests/student_portal_four_role_e2e_mysql_test.php
```

## 11. Kết luận để báo cáo nhóm

Chuỗi SC -> TE -> ST đã có record persisted trong MySQL và service tương đối hoàn chỉnh, nhưng phần lớn dữ liệu hiện tại có nguồn demo/seed; phải sửa CSRF, scope trường, safeguarding cho 8 học sinh dưới 18 tuổi và notification hai chiều. Có 77 skill verified từ seeder nhưng chưa chứng minh TE có workflow verify tại runtime. EN có backend internship/application nhưng chưa có traffic persisted, service review còn phân mảnh, chưa có consent scope/grant theo doanh nghiệp, partnership/targeting, contact request persisted, oversight, payment confirmation hoặc sponsorship khép kín. Muốn bốn vai trò vận hành chuẩn production, SC, TE và EN phải chịu ownership của 13 workflow kể cả khi bắt buộc chỉnh file dưới `app/learner`; ST tập trung nghiệm thu tích hợp và chỉ nhận phần UI/contract đã được giao rõ. Mọi migration, KPI, state machine và release gate phải đối chiếu schema thực tế và chỉ destructive-test trên database disposable.
