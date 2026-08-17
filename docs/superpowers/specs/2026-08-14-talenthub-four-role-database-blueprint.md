# Blueprint Database TalentHub thống nhất bốn vai trò

> Trạng thái tài liệu: Đề xuất thiết kế để Database Owner review và chuyển thành migrations có kiểm thử.
>
> Cảnh báo: Các đoạn SQL trong tài liệu chỉ là DDL minh họa. Không chạy trực tiếp trên development, staging hoặc production trước khi đối chiếu `information_schema`, sao lưu dữ liệu và viết migration idempotent.

## 1. Mục tiêu

Tài liệu này mô tả cách chỉnh lại database chính của TalentHub để bốn vai trò dùng chung một nguồn dữ liệu nhất quán:

- Học sinh/Sinh viên (`learner`) đọc và ghi đúng dữ liệu thuộc sở hữu của mình.
- Giáo viên/HLV (`teacher`) tạo hoạt động, duyệt đăng ký, xác nhận check-in và công bố đánh giá.
- Nhà trường (`school`) quản lý trường, lớp, enrollment, chương trình và dữ liệu đã xác minh.
- Doanh nghiệp (`enterprise`) quản lý doanh nghiệp, cơ hội thực tập và trạng thái tuyển chọn.

Mục tiêu cuối là database đủ contract để hoàn thành Student Portal Phase 1 đến Phase 11 mà không phụ thuộc mock data hoặc `localStorage` làm nguồn dữ liệu chính. AI không thuộc phạm vi tài liệu này.

## 2. Nguồn đã đối chiếu

Thiết kế được rút ra từ các nguồn hiện có trong repository:

- Schema chính: `Database/Talenthub_DB.sql`.
- Learner roadmap: `docs/superpowers/plans/2026-08-14-student-portal-completion-roadmap.md`.
- Learner contracts, database repositories, read models và mock providers trong `app/learner`.
- Teacher SELECT contract trong `app/teacher/includes/dashboard-data.php`.
- School mock data trong `app/school` và `app/school/includes`.
- Enterprise internship, applicant, talent, analytics và sponsorship mock data trong `app/enterprise/includes`.
- Giao diện chọn bốn vai trò trong `role-selection.php`.

### 2.1 Giới hạn xác minh runtime

Tại thời điểm lập tài liệu, PHP có `pdo_mysql` nhưng không kết nối được MariaDB bằng cấu hình môi trường hiện có. Vì vậy:

- Đã đối chiếu tĩnh schema SQL và code.
- Chưa xác nhận được schema runtime đã import đúng phiên bản nào.
- Chưa xác nhận được row count, duplicate, orphan hoặc phân bố status trên dữ liệu thật.
- Database Owner bắt buộc chạy bộ truy vấn read-only ở phần 16 trước khi thiết kế migration.

## 3. Kết luận thiết kế

Database hiện tại có nền tảng quan hệ tương đối tốt nhưng chưa đủ để vận hành đồng bộ bốn vai trò. Các vấn đề cốt lõi là:

1. `users.roles` là chuỗi đơn, không biểu diễn an toàn một người có nhiều vai trò hoặc vai trò theo tổ chức.
2. School chưa có membership/authentication contract tương đương Enterprise.
3. Student liên kết trực tiếp một `classId`, không lưu được lịch sử trường/lớp/năm học.
4. Status là `VARCHAR` tự do; mock và code của các vai trò đang dùng vocabulary khác nhau.
5. Nhiều quan hệ thiếu unique key nên có thể tạo bản ghi trùng.
6. Hoạt động, check-in, assessment và application thiếu lifecycle hoặc audit cần thiết.
7. Talent Passport thiếu dữ liệu tự khai, evidence, verification, consent và sharing snapshot.
8. School opportunities chưa có schema.
9. Notification, badge và statistics chưa đủ repository-facing contract.
10. `Talenthub_DB.sql` có `DELETE FROM` sau mỗi bảng và chỉ nên được coi là schema dump, không phải migration production.

**Verdict audit: `NOT_READY` để Student Portal bắt đầu dùng dữ liệu thật/Phase 1 trên database hiện tại.**

Chỉ chuyển verdict thành `READY` sau khi hoàn thành toàn bộ hạng mục P0, chạy audit dữ liệu runtime không còn duplicate/orphan nghiêm trọng, repository/API Student đã dùng schema canonical, và kiểm thử luồng tích hợp bốn vai trò đạt tiêu chí ở phần 23.

### 3.1 Danh sách thiếu hoặc sai theo mức độ ưu tiên

| Ưu tiên | Thiếu hoặc sai hiện tại | Ảnh hưởng | Hướng xử lý bắt buộc |
|---|---|---|---|
| **P0 — chặn dữ liệu thật** | Chưa có `user_roles`, `school_members`; `users.roles` chỉ chứa một chuỗi role | Không biểu diễn an toàn một user nhiều vai trò hoặc quyền theo tổ chức | Tạo role/membership canonical, backfill và chỉ deprecate cột cũ sau khi bốn portal đã chuyển |
| **P0 — chặn dữ liệu thật** | Student chỉ có `classId`, thiếu lịch sử `student_enrollments` | Không xác định đúng Student thuộc School/lớp/năm học nào; Teacher/School có thể đọc sai phạm vi | Tạo enrollment có thời gian hiệu lực, trạng thái, primary flag, FK và unique key theo quy tắc ở phần 8 |
| **P0 — chặn dữ liệu thật** | Status giữa schema, mock và code không đồng nhất | Query/filter và state transition có thể mất hoặc hiển thị sai dữ liệu | Chốt canonical vocabulary, mapping dữ liệu cũ và enforcement tại service/database theo phần 6 |
| **P0 — chặn dữ liệu thật** | Nhiều quan hệ nghiệp vụ thiếu unique key/FK/index; runtime chưa được audit duplicate/orphan | Có thể phát sinh đăng ký, check-in, kết quả, application hoặc membership trùng và record mồ côi | Chạy audit read-only phần 16, làm sạch có phê duyệt, rồi mới thêm constraint/index phần 15 |
| **P0 — chặn dữ liệu thật** | Activity/check-in/experience thiếu lifecycle, idempotency và rule nguồn | Student có thể được cộng giờ sai hoặc cộng lặp | Bổ sung token hash, unique attendance, rule, status và source reference theo phần 10 |
| **P0 — chặn dữ liệu thật** | Test attempt thiếu answer persistence/version; assessment thiếu publication contract | Không khôi phục được bài làm, khó audit kết quả, Student có thể thấy đánh giá nháp | Thêm `test_answers`, test version, attempt lifecycle và assessment publish fields theo phần 11 |
| **P0 — chặn dữ liệu thật** | Internship application thiếu snapshot/consent rõ ràng và status contract dùng chung | Enterprise có thể đọc profile thay đổi ngoài phạm vi Student đã chia sẻ | Tạo consent/share và `application_profile_snapshots`; chuẩn hóa status/history theo phần 9 và 13 |
| **P0 — chặn dữ liệu thật** | Student code vẫn có nhánh mock/localStorage và chưa có repository/API thật cho mọi feature | Database đúng vẫn không bảo đảm portal dùng dữ liệu thật | Xây contract/API/repository Student theo mapping Phase 1–11; loại fallback khỏi production sau cutover |
| **P1 — cần cho đủ Phase** | Thiếu profile details, skill evidence, certificate/project verification và Talent Passport sharing | Talent Passport chưa đủ tin cậy để School/Teacher/Enterprise sử dụng | Bổ sung các bảng ở phần 9 cùng ownership, verifier và visibility |
| **P1 — cần cho đủ Phase** | Thiếu `school_opportunities`, feedback, notification preferences, experience levels | Thiếu các luồng ecosystem, phản hồi và cá nhân hóa thông báo | Bổ sung schema ở phần 10, 12 và 14; nối API theo role ownership |
| **P1 — cần cho vận hành** | Chưa có migration chain thực tế ngoài registry learner | Không thể nâng cấp an toàn, kiểm chứng hoặc rollback theo từng domain | Xây migrations theo thứ tự phần 20, mỗi migration có preflight/backfill/verify/rollback |
| **P2 — tối ưu sau cutover** | Aggregate/statistics và một số index đọc chưa được chốt bằng workload runtime | Dashboard có thể chậm khi dữ liệu tăng | Đo query plan/volume thật, thêm aggregate hoặc index có căn cứ; không lưu số liệu suy diễn trùng nguồn |

Quy ước: **P0** phải hoàn thành trước cutover dữ liệu thật; **P1** hoàn thành để phủ đủ Phase Student và vận hành ổn định; **P2** tối ưu sau khi đã có số liệu runtime.

## 4. Nguyên tắc bắt buộc

### 4.1 Một nguồn dữ liệu cho mỗi domain

- Teacher là nguồn ghi chính của activity và teacher assessment.
- School là nguồn ghi chính của school, class, enrollment và school verification.
- Enterprise là nguồn ghi chính của enterprise, internship post và employer review status.
- Student là nguồn ghi chính của self-declared profile, consent, application submit/withdraw và assessment attempt.
- Derived data như experience hours, badge và statistics chỉ được tính từ dữ liệu đã xác nhận.

Không sao chép mock của vai trò khác thành bảng Student riêng. Student phải đọc bảng domain chung qua FK và repository có authorization.

### 4.2 Không tạo bảng dùng chung mang prefix `learner_`

Các bảng được nhiều vai trò đọc hoặc ghi phải dùng tên domain-neutral:

- Dùng `activity_experience_rules`, không dùng `learner_activity_experience_rules`.
- Dùng `notification_preferences`, không dùng `learner_notification_preferences`.
- Dùng `activity_details` nếu tách detail khỏi `activities`, không dùng `learner_activity_details`.

Prefix `student_` chỉ dùng khi entity thực sự thuộc một Student, ví dụ `student_skills`, `student_profile_shares`.

### 4.3 Chuẩn identifier và naming

- Giữ `CHAR(36)` UUID để tương thích code hiện tại.
- UUID phải là lowercase RFC 4122 và được tạo ở backend.
- Giữ camelCase trong database trong giai đoạn này vì schema hiện hữu dùng camelCase; repository tiếp tục map sang snake_case.
- Không đưa table/column name từ request vào SQL.
- Mọi timestamp lưu UTC; frontend chuyển timezone khi hiển thị.
- Mọi bảng nghiệp vụ có `createdAt`; bảng có update phải có `updatedAt`.
- Không dùng `ON UPDATE CURRENT_TIMESTAMP` cho timestamp nghiệp vụ như `startAt`, `deadline`, `submittedAt`.

### 4.4 Soft lifecycle thay cho xóa dây chuyền

- User, school, enterprise, activity, test và opportunity dùng status để archive/suspend.
- Không hard-delete activity đã có registration/check-in/experience.
- Không hard-delete internship post đã có application.
- Không cascade xóa application history, assessment history hoặc confirmed experience chỉ vì entity cha bị archive.
- Quy trình xóa dữ liệu cá nhân phải là workflow riêng có audit, không dựa vào cascade ngầm.

### 4.5 Quy tắc authorization

Database FK bảo vệ quan hệ; service/API vẫn phải kiểm tra actor, role và ownership.

- Student query/write luôn scope bằng current `studentId` hoặc current `userId`.
- Teacher chỉ thao tác activity/assessment thuộc `teacherId` và school scope hợp lệ.
- School admin chỉ thao tác dữ liệu thuộc school membership đang active.
- Enterprise member chỉ thao tác post/application thuộc enterprise membership đang active.
- Không cho client tự gửi `studentId`, `teacherId`, `schoolId`, `enterpriseId` để quyết định quyền; backend suy ra từ session.

## 5. Ma trận ownership bốn vai trò

| Domain | Bảng nguồn chính | Vai trò ghi | Vai trò đọc | Quyền Student |
|---|---|---|---|---|
| Identity | `users`, `user_roles` | Authentication service | Cả bốn qua session | Đọc account của mình |
| School membership | `school_members` | School/platform admin | School, Teacher | Không ghi |
| Enterprise membership | `enterprise_members` | Enterprise/platform admin | Enterprise | Không ghi |
| Student enrollment | `student_enrollments` | School | Student, Teacher, School | Chỉ đọc enrollment của mình |
| Student profile | `student_profiles`, `student_profile_details` | School + Student theo field ownership | Student, người nhận được consent | Chỉ sửa field tự khai |
| Skill/evidence | `student_skills`, `student_skill_evidence` | Student khai evidence; Teacher/School xác minh | Student; Enterprise qua share/snapshot | Không tự sửa verified result |
| Certificate | `certificates` | Student khai; Teacher/School xác minh | Student; Enterprise qua consent | Sửa/xóa bản pending của mình |
| Project | `projects`, `project_members` | School/Teacher | Student, School, Enterprise qua consent | Đọc project có membership |
| Activity | `activities`, `activity_experience_rules` | Teacher; School publish/govern | Cả bốn theo scope | Đọc activity visible |
| Registration | `activity_registrations`, status history | Student create/cancel; Teacher approve | Student, Teacher, School | Chỉ hồ sơ của mình |
| Check-in | `activity_qr_tokens`, `checkins` | Teacher phát token; Student submit; organizer confirm | Student, Teacher, School | Chỉ registration của mình |
| Experience | `experience_logs` | System/Teacher confirmation | Student, Teacher, School | Chỉ đọc confirmed của mình |
| Talent test | `talent_tests`, `test_questions` | School/expert | Student khi published | Không sửa định nghĩa |
| Test attempt | `test_attempts`, `test_answers`, `test_results` | Student + scoring service | Student; counselor theo policy | Chỉ attempt của mình |
| Teacher evaluation | `assessments`, `assessment_scores` | Teacher; School publish policy | Student khi published | Read-only |
| School opportunity | `school_opportunities` | School | Student | Đọc active; apply nếu internal |
| Internship | `internship_posts`, requirements | Enterprise | Student | Đọc active/paused policy |
| Application | `internship_applications`, history, snapshot | Student create/withdraw; Enterprise review | Student và owning Enterprise | Chỉ application của mình |
| Notification | `notifications`, `notification_preferences` | Domain services; recipient controls read/preferences | Recipient | Chỉ notification của own user |
| Badge/level | `badges`, `student_badges`, `experience_levels` | Rule engine/admin | Student | Read-only |
| Statistics | Aggregate query/view | System | Student, School theo permission | Chỉ personal aggregate |

## 6. Status vocabulary chuẩn

Status phải dùng code tiếng Anh lowercase trong database. Nhãn tiếng Việt thuộc presentation layer.

### 6.1 Danh sách canonical

| Entity | Giá trị hợp lệ |
|---|---|
| `users.status` | `active`, `inactive`, `suspended`, `deleted` |
| `student_profiles.studyStatus` | `active`, `inactive`, `graduated`, `suspended` |
| `student_enrollments.status` | `pending`, `active`, `completed`, `transferred`, `cancelled` |
| `schools.status` | `draft`, `active`, `suspended`, `archived` |
| `enterprises.status` | `draft`, `active`, `suspended`, `archived` |
| `enterprises.verificationStatus` | `pending`, `verified`, `rejected`, `revoked` |
| `activities.status` | `draft`, `published`, `active`, `closed`, `cancelled`, `completed` |
| `activity_registrations.status` | `pending`, `registered`, `waitlisted`, `rejected`, `cancelled`, `checked_in`, `completed` |
| `checkins.status` | `pending`, `confirmed`, `rejected`, `revoked` |
| `experience_logs.status` | `pending`, `confirmed`, `rejected`, `revoked` |
| `talent_tests.status` | `draft`, `published`, `archived` |
| `test_attempts.status` | `in_progress`, `submitted`, `completed`, `expired`, `cancelled` |
| `assessments.status` | `draft`, `pending_review`, `published`, `withdrawn` |
| `projects.status` | `draft`, `active`, `completed`, `cancelled`, `archived` |
| `internship_posts.status` | `draft`, `active`, `paused`, `closed`, `cancelled` |
| `school_opportunities.status` | `draft`, `active`, `paused`, `closed`, `cancelled`, `completed` |
| `internship_applications.status` | `submitted`, `reviewing`, `interview`, `accepted`, `declined`, `withdrawn` |
| `notifications.notificationStatus` | `queued`, `sent`, `failed`, `cancelled` |
| Verification chung | `pending`, `verified`, `rejected`, `revoked` |

### 6.2 Mapping dữ liệu cũ

| Giá trị hiện có/mock | Canonical | Ghi chú |
|---|---|---|
| Enterprise application `new` | `submitted` | Hồ sơ mới nộp |
| Enterprise application `interviewing` | `interview` | Đồng nhất Student enum |
| Enterprise application `rejected` | `declined` | Không dùng hai từ cho cùng trạng thái |
| Teacher registration `waiting`, `new`, `cho_duyet`, `cho_xac_nhan` | `pending` | Chỉ backfill khi đúng nghĩa chờ duyệt |
| Teacher activity `open`, `ongoing`, `in_progress`, `dang_mo`, `mo` | `active` | Cần review dữ liệu trước update |
| Teacher assessment `new`, `need_review`, `awaiting_review`, `cho_cham`, `chua_cham` | `pending_review` | Không map thành published |
| Enterprise post UI `Đang tuyển` | `active` | Nhãn không lưu vào status |
| Enterprise post UI `Tạm dừng` | `paused` | Hiện analytics mock đã dùng ý nghĩa này |
| School class `success`, `warning` | Không migrate vào status | Đây là UI tone/derived health, không phải domain status |
| Sponsorship `near_completion` | Derived metric | Không dùng làm lifecycle nếu có thể tính từ raised/target |

Database Owner phải tạo báo cáo `GROUP BY status` trước khi backfill. Giá trị lạ không được tự map sang `active`; đưa vào bảng review và xử lý có chủ đích.

## 7. Identity, role và organization membership

### 7.1 `users` — giữ và mở rộng

Giữ các cột `id`, `email`, `passwordHash`, `fullName`, `status`, `createdAt`.

Thay đổi bắt buộc:

- Giữ unique `email` sau khi normalize lowercase/trim.
- Thêm `updatedAt TIMESTAMP`.
- Thêm `emailVerifiedAt TIMESTAMP NULL`.
- Thêm `lastLoginAt TIMESTAMP NULL`.
- Thêm index `(status, createdAt)`.
- Ngừng dùng `roles` làm nguồn quyền sau giai đoạn chuyển đổi.
- Chưa drop `roles` cho đến khi toàn bộ runtime đọc `user_roles`.

### 7.2 `user_roles` — bảng mới

| Cột | Kiểu | Constraint/ý nghĩa |
|---|---|---|
| `userId` | `CHAR(36)` | PK phần 1, FK `users.id` |
| `roleCode` | `VARCHAR(50)` | PK phần 2; `learner`, `teacher`, `school`, `enterprise`, `platform_admin` |
| `grantedByUserId` | `CHAR(36) NULL` | FK `users.id` |
| `grantedAt` | `TIMESTAMP` | Default current timestamp |
| `revokedAt` | `TIMESTAMP NULL` | Role không hợp lệ khi đã revoke |

Một user có thể vừa là Teacher vừa là School admin. Role xác định portal được truy cập; membership xác định tổ chức nào được thao tác.

### 7.3 `school_members` — bảng mới

School hiện chưa có contract membership. Tạo bảng tương đương `enterprise_members`:

| Cột | Kiểu | Constraint/ý nghĩa |
|---|---|---|
| `id` | `CHAR(36)` | PK |
| `schoolId` | `CHAR(36)` | FK `schools.id` |
| `userId` | `CHAR(36)` | FK `users.id` |
| `role` | `VARCHAR(50)` | `owner`, `admin`, `staff`, `counselor`, `viewer` |
| `status` | `VARCHAR(50)` | `active`, `inactive`, `revoked` |
| `createdAt` | `TIMESTAMP` | Audit |
| `updatedAt` | `TIMESTAMP` | Audit |

Unique bắt buộc: `(schoolId, userId)`.

### 7.4 `enterprise_members` — giữ và mở rộng

- Thêm unique `(enterpriseId, userId)`.
- Chuẩn hóa `role`: `owner`, `admin`, `recruiter`, `reviewer`, `viewer`.
- Thêm `status`, `createdAt`, `updatedAt`.
- Mọi enterprise query suy ra `enterpriseId` từ active membership.

### 7.5 `teacher_profiles`

- Thêm unique `userId` nếu một user chỉ có một teacher profile.
- Giữ `schoolId` cho primary school trong giai đoạn hiện tại.
- `isSchoolAdmin` chuyển thành quyền trong `school_members`; giữ tạm để tương thích rồi deprecate.
- Thêm `status`, `bio`, `specialization`, `createdAt`, `updatedAt` nếu Teacher Portal cần profile đầy đủ.

## 8. School, class và Student enrollment

### 8.1 `schools` — mở rộng

Schema hiện chỉ có `id`, `name`, `status`, trong khi Student và School mock cần nhiều dữ liệu hơn.

Thêm:

- `shortName VARCHAR(100) NULL`
- `schoolType VARCHAR(100) NULL`
- `educationLevel VARCHAR(100) NULL`
- `logoUrl VARCHAR(500) NULL`
- `description TEXT NULL`
- `website VARCHAR(500) NULL`
- `email VARCHAR(255) NULL`
- `phone VARCHAR(30) NULL`
- `address VARCHAR(500) NULL`
- `provinceCode VARCHAR(20) NULL`
- `districtCode VARCHAR(20) NULL`
- `verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending'`
- `verifiedAt TIMESTAMP NULL`
- `verifiedBy CHAR(36) NULL`
- `createdAt`, `updatedAt`

Index cần có: `(status, verificationStatus)`, `(provinceCode, status)`.

Student chỉ nhìn thấy school `active` và `verificationStatus='verified'` trong ecosystem. School hiện tại của Student vẫn hiển thị theo enrollment ngay cả khi school bị tạm ẩn khỏi catalog.

### 8.2 `classes` — mở rộng

- Thêm `homeroomTeacherId CHAR(36) NULL` FK `teacher_profiles.id`.
- Thêm `status`: `active`, `completed`, `archived`.
- Thêm `createdAt`, `updatedAt`.
- Unique `(schoolId, name, academicYear)`.
- Index `(schoolId, academicYear, status)`.

### 8.3 `student_enrollments` — bảng mới, nguồn liên kết Student–School–Teacher

Không nên dùng duy nhất `student_profiles.classId` vì không lưu lịch sử chuyển lớp/trường.

| Cột | Kiểu | Constraint/ý nghĩa |
|---|---|---|
| `id` | `CHAR(36)` | PK |
| `studentId` | `CHAR(36)` | FK `student_profiles.id` |
| `schoolId` | `CHAR(36)` | FK `schools.id` |
| `classId` | `CHAR(36) NULL` | FK `classes.id` |
| `academicYear` | `VARCHAR(20)` | Ví dụ `2026-2027` |
| `status` | `VARCHAR(50)` | Canonical enrollment status |
| `isPrimary` | `TINYINT(1)` | Enrollment hiện dùng cho portal |
| `startedAt` | `DATE` | Ngày bắt đầu |
| `endedAt` | `DATE NULL` | Ngày kết thúc |
| `createdByUserId` | `CHAR(36)` | School actor |
| `createdAt` | `TIMESTAMP` | Audit |
| `updatedAt` | `TIMESTAMP` | Audit |

Constraints:

- Unique `(studentId, schoolId, academicYear)` hoặc unique chi tiết hơn nếu nghiệp vụ cho phép nhiều enrollment trong một năm.
- Check `endedAt IS NULL OR endedAt >= startedAt`.
- Service bảo đảm tối đa một enrollment `isPrimary=1 AND status='active'` cho mỗi Student.
- `classId` phải thuộc cùng `schoolId`; kiểm tra bằng service hoặc trigger có kiểm thử vì FK đơn không bảo vệ điều kiện này.

Lộ trình chuyển đổi:

1. Backfill enrollment từ `student_profiles.classId -> classes.schoolId`.
2. Chuyển Student/Teacher/School repositories sang enrollment.
3. Giữ `student_profiles.classId` read-only trong một release.
4. Chỉ drop hoặc nullable cột cũ khi không còn consumer.

## 9. Student profile và Talent Passport

### 9.1 `student_profiles` — dữ liệu định danh do School/Account quản lý

Giữ `id`, `userId`, `dateOfBirth`, `phone`, `studyStatus`.

Thay đổi:

- Unique `userId`.
- Chuyển liên kết trường/lớp chính sang `student_enrollments`.
- Thêm `createdAt`, `updatedAt`.
- Xác định field ownership:
  - `userId`, enrollment, `studyStatus`: School/account service.
  - `dateOfBirth`: School/account verification workflow.
  - `phone`: Student được sửa sau bước xác minh theo policy.

### 9.2 `student_profile_details` — bảng mới, dữ liệu tự khai

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `studentId` | `CHAR(36)` | PK/FK `student_profiles.id` |
| `headline` | `VARCHAR(255) NULL` | Tiêu đề Talent Passport |
| `bio` | `VARCHAR(1000) NULL` | Giới thiệu |
| `location` | `VARCHAR(255) NULL` | Vị trí tự khai |
| `avatarUrl` | `VARCHAR(500) NULL` | Chỉ URL/file route được backend cấp |
| `preferredFields` | `LONGTEXT NULL` | JSON array đã validate |
| `updatedAt` | `TIMESTAMP` | Lần sửa gần nhất |

Student được update các cột này. School, class, email, account status không nằm trong profile form tự khai.

### 9.3 `skills` và `student_skills`

`skills`:

- Thêm unique `code` ổn định, ví dụ `python`, `teamwork`, `iot`.
- Thêm `description`, `isActive`, `createdAt`, `updatedAt`.
- Không dùng tên hiển thị làm khóa liên kết.

`student_skills`:

- Unique `(studentId, skillId)`.
- Thay `level` tự do bằng `levelCode` có vocabulary: `beginner`, `intermediate`, `advanced`, `expert`.
- Có thể thêm `score DECIMAL(5,2) NULL`, check `0 <= score <= 100`.
- Thêm `verificationStatus`, `verifiedByUserId`, `verifiedAt`, `rejectionReason`.
- Thêm `sourceType`, `sourceEntityId` nếu giá trị được tổng hợp từ assessment/activity/project.
- Thêm `declaredAt`, `updatedAt`.

Student chỉ được khai skill/evidence. Student không được tự sửa `score`, verified level hoặc verification fields.

### 9.4 `student_skill_evidence` — bảng mới

Lưu nhiều minh chứng cho một skill thay vì nhét một URL vào `student_skills`:

- `id` PK.
- `studentSkillId` FK.
- `evidenceType`: `certificate`, `project`, `activity`, `assessment`, `url`, `file`.
- `evidenceUrl` nullable.
- `sourceEntityType`, `sourceEntityId` nullable.
- `description` nullable.
- `verificationStatus`, `verifiedByUserId`, `verifiedAt`, `rejectionReason`.
- `createdAt`, `updatedAt`.

### 9.5 `certificates`

Thêm:

- `evidenceUrl VARCHAR(500) NULL`
- `credentialCode VARCHAR(255) NULL`
- `verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending'`
- `verifiedByUserId CHAR(36) NULL`
- `verifiedAt TIMESTAMP NULL`
- `rejectionReason VARCHAR(500) NULL`
- `createdAt`, `updatedAt`

Check `expiryDate IS NULL OR expiryDate >= issueDate`. Student chỉ sửa/xóa certificate `pending`; verified/rejected record giữ lịch sử.

### 9.6 `projects` và `project_members`

`projects` cần thêm `summary`, `description`, `startDate`, `endDate`, `resultSummary`, `evidenceUrl`, `createdByUserId`, timestamps. Không dùng `targetAmount/raisedAmount` làm dữ liệu Talent Passport duy nhất; đó là sponsorship concern.

`project_members`:

- Unique `(projectId, studentId)`.
- Thêm `joinedAt`, `leftAt`, `contributionSummary`, `verificationStatus`, `verifiedByUserId`.
- Student đọc membership của mình; School/Teacher quản lý membership.

### 9.7 Consent và profile sharing

Mở rộng `privacy_consents`:

- Thêm `purpose`, `recipientType`, `recipientId`, `grantedAt`, `createdAt`, `updatedAt`.
- Index `(studentId, scope, isGranted, revokedAt)`.
- Không overwrite lịch sử policy version.
- Một consent chỉ hợp lệ khi `isGranted=1`, `revokedAt IS NULL` và policy còn hiệu lực.

Tạo `student_profile_shares`:

- `id`, `studentId`, `consentId`.
- `tokenHash CHAR(64)` unique; không lưu token plaintext.
- `sharedFields LONGTEXT` JSON allow-list.
- `recipientType`, `recipientSchoolId`, `recipientEnterpriseId` nullable có CHECK đúng một recipient nếu share có đích.
- `expiresAt`, `revokedAt`, `createdAt`.

Enterprise không được đọc trực tiếp toàn bộ `student_profiles`. Enterprise chỉ đọc:

1. Snapshot gắn application; hoặc
2. Profile share đang còn hiệu lực và đúng recipient.

## 10. Activity, registration, check-in và experience

### 10.1 `activities`

Teacher sở hữu nội dung; School quản trị publication. Bổ sung trực tiếp vào domain chung hoặc tách `activity_details` 1:1 nếu Database Owner muốn tránh ALTER lớn.

Các field cần có:

- `summary VARCHAR(500) NULL`
- `description TEXT NULL`
- `location VARCHAR(500) NULL`
- `format VARCHAR(50)` với `onsite`, `online`, `hybrid`
- `registrationOpensAt`, `registrationClosesAt`, `cancellationClosesAt`
- `approvalMode`: `automatic`, `teacher_review`, `school_review`
- `costAmount DECIMAL(12,2) NULL`, `currency CHAR(3) NULL`
- `organizerContact VARCHAR(255) NULL`
- `requirements LONGTEXT NULL` JSON
- `benefits LONGTEXT NULL` JSON
- `createdAt`, `updatedAt`, `publishedAt`

Sửa bắt buộc: loại `ON UPDATE CURRENT_TIMESTAMP` khỏi `startAt`.

Indexes:

- `(status, startAt)`
- `(schoolId, status, startAt)`
- `(createdByTeacherId, status, startAt)`

### 10.2 `activity_registrations`

Thêm:

- Unique `(activityId, studentId)`.
- `createdAt`, `updatedAt`, `cancelledAt`, `cancellationReason`.
- `reviewedByUserId`, `reviewedAt`, `rejectionReason`.
- Index `(studentId, status, createdAt)`.
- Index `(activityId, status, createdAt)`.

Tạo `activity_registration_status_history` để không mất timeline khi Teacher duyệt hoặc Student hủy.

### 10.3 `activity_qr_tokens`

- Đổi từ `token` plaintext sang `tokenHash CHAR(64)`.
- Thêm `validFrom`, `expiresAt`, `revokedAt`, `createdByUserId`, `createdAt`.
- Unique `tokenHash`.
- Một token activity có thể dùng cho nhiều registration hợp lệ; không unique `qrTokenId` trong `checkins`.

### 10.4 `checkins`

Thêm:

- Unique `registrationId` để chống quét lại.
- `status`, `confirmedByUserId`, `confirmedAt`, `rejectionReason`.
- `locationEvidence LONGTEXT NULL` JSON đã minimize.
- `createdAt` ngoài `checkedInAt` nếu cần tách submit và confirm.

Transaction phải khóa registration, activity và QR token; validate ownership, time window và token trước khi tạo check-in.

### 10.5 `activity_experience_rules` — bảng mới

- `activityId` PK/FK.
- `confirmedHours DECIMAL(7,2)` check không âm.
- `confirmationMode`: `automatic`, `teacher_review`, `school_review`.
- `locationPolicy LONGTEXT NULL` JSON.
- `createdAt`, `updatedAt`.

### 10.6 `experience_logs`

- Unique `checkinId`.
- Thêm `status`, `confirmedByUserId`, `confirmedAt`, `rejectedAt`, `rejectionReason`.
- Thêm `createdAt`, `updatedAt`.
- Index `(studentId, status, confirmedAt)`.
- Student KPI/statistics chỉ cộng row `status='confirmed'`.

### 10.7 `activity_feedback` — bảng mới

- `id`, `registrationId`, `studentId`, `rating`, `comment`, `createdAt`, `updatedAt`.
- Unique `registrationId`.
- Check rating từ 1 đến 5.
- Chỉ cho feedback khi registration `completed` và thuộc Student hiện tại.

## 11. Talent tests và Teacher evaluations

### 11.1 `talent_tests`

Thêm:

- `code VARCHAR(100)` unique.
- `version INT NOT NULL`.
- `status VARCHAR(50)`.
- `description TEXT NULL`.
- `durationMinutes INT`.
- `retakeAfterDays INT`.
- `disclaimer VARCHAR(1000)`.
- `createdByUserId`, `publishedAt`, `createdAt`, `updatedAt`.

Một phiên bản test đã có completed attempt không được sửa scoring semantics; tạo version mới.

### 11.2 `test_questions`

Thêm `dimension`, `sortOrder`, `isRequired`, `questionType`, `createdAt`, `updatedAt`.

Unique `(testId, sortOrder)`. Holland published version phải có đúng 24 câu, sáu dimension RIASEC, mỗi dimension bốn câu và option 1–5 hợp lệ.

### 11.3 `test_attempts`

Thêm `status`, `testVersion`, `currentQuestionIndex`, `expiresAt`, `submittedAt`, `updatedAt`.

Indexes:

- `(studentId, testId, startedAt)`
- `(studentId, status, updatedAt)`

Service không cho nhiều in-progress attempt trái retake policy.

### 11.4 `test_answers` — bảng mới

- `id` PK.
- `attemptId` FK.
- `questionId` FK.
- `answerValue VARCHAR(255)`.
- `answeredAt`, `updatedAt`.
- Unique `(attemptId, questionId)`.

Backend validate question thuộc đúng `testId/testVersion` của attempt. Không tin answer set hoặc score do browser gửi.

### 11.5 `test_results`

- Unique `attemptId`.
- Thêm `scoringVersion`, `calculatedAt`, `createdAt`.
- `dimensionScores` tiếp tục là JSON nhưng phải validate đủ dimension của test version.
- Result immutable sau khi attempt completed; recalculation tạo audit event/version mới.

### 11.6 `assessments`, `assessment_scores`, `assessment_criteria`

Teacher evaluation khác talent test và giữ domain riêng.

`assessments` thêm:

- `periodCode`, `rubricVersion`.
- `publishedAt`, `withdrawnAt`, `createdAt`, `updatedAt`.
- `reviewedByUserId` nếu School có approval.
- Index `(studentId, status, publishedAt)`.
- Index `(teacherId, status, createdAt)`.

`assessment_scores` thêm unique `(assessmentId, criteriaId)`.

Student repository chỉ trả assessment `published`; không trả draft comment, pending score hoặc withdrawn assessment.

Tạo `assessment_status_history` và `assessment_appeals` nếu Student Portal hỗ trợ phúc khảo. Appeal phải chứa `studentId`, `assessmentId`, `message`, `status`, response, timestamps và unique active appeal theo policy.

## 12. School/Enterprise ecosystem và opportunities

### 12.1 `enterprises`

Schema hiện đã có nhiều field hữu ích. Bổ sung theo Enterprise mock nếu cần:

- `shortName`, `sizeLabel`, `foundedYear`, `provinceCode`.
- Index `(status, verificationStatus, industry)`.
- Không đưa `verificationNote` nội bộ vào Student read model.

### 12.2 `internship_posts`

Giữ Enterprise là owner. Chuẩn hóa:

- Thêm `fieldCode` hoặc category FK.
- Giữ `description`, `benefits`, `duration`, `location`, `workMode`, `openings`, `targetStudents`, `deadline`.
- Thêm `applicationOpensAt`, `applicationClosesAt` nếu deadline đơn không đủ.
- Thêm `publishedAt`, `closedAt`.
- Xóa một trong hai index trùng trên `enterpriseId` sau khi xác minh runtime.
- Không hard-delete post đã có applications.

`internship_requirements` cần unique `(postId, skillId)` và có thể thêm `isRequired`, `weight`.

### 12.3 `school_opportunities` — bảng mới

Phục vụ open day, scholarship, cuộc thi, mentoring hoặc chương trình trải nghiệm không phù hợp với `activities` nội bộ.

Các cột tối thiểu:

- `id`, `schoolId`, `createdByUserId`.
- `opportunityType`: `open_day`, `scholarship`, `competition`, `program`, `mentoring`.
- `title`, `summary`, `description`.
- `location`, `format`, `capacity`.
- `targetStudents`, `requirements`, `benefits`.
- `applicationMode`: `internal`, `external`, `information_only`.
- `externalUrl` chỉ hợp lệ khi mode external.
- `opensAt`, `deadline`, `startAt`, `endAt`.
- `status`, `publishedAt`, `createdAt`, `updatedAt`.

Index `(schoolId, status, deadline)` và `(opportunityType, status, deadline)`.

Nếu `applicationMode='internal'`, tạo `school_opportunity_applications` với unique `(opportunityId, studentId)`, status history và consent snapshot tương tự internship application. Nếu event chỉ cần registration/check-in, School nên tạo `activities` thay vì duplicate luồng.

### 12.4 Student ecosystem read contract

Student API có thể hợp nhất hai nguồn ở read-model/repository layer:

- Enterprise opportunities từ `internship_posts`.
- School opportunities từ `school_opportunities`.

Không bắt buộc tạo polymorphic FK hoặc một bảng `opportunities` chung ngay lập tức. Tách source-owned tables giữ FK rõ ràng và tránh làm hỏng Enterprise code hiện tại.

## 13. Application, consent snapshot và Enterprise access

### 13.1 `internship_applications`

Schema hiện đã có unique `(postId, studentId)` và timestamps. Cần:

- Chuẩn hóa status canonical.
- Thêm `withdrawnAt`, `withdrawalReason`.
- Thêm `consentId` FK hoặc liên kết qua snapshot.
- Không nhận `cvUrl` tùy ý từ browser.
- `cvUrl` chỉ trỏ tới route nội bộ được authorization hoặc được thay bằng snapshot ID.
- Chuyển FK post từ cascade delete sang restrict/archive policy sau review.

### 13.2 `application_status_history`

Giữ bảng hiện có, bổ sung index `(applicationId, createdAt)` nếu runtime thiếu và bảo đảm mọi status update ghi history trong cùng transaction.

`changedBy` là user actor; `fromStatus/toStatus` phải canonical. Student thấy timeline nhưng không thấy internal-only note. Có thể thêm `visibility` với `internal`, `student_visible`.

### 13.3 `application_profile_snapshots` — bảng mới

- `applicationId` PK/FK.
- `consentId` FK.
- `profileData LONGTEXT` JSON đã minimize.
- `schemaVersion INT`.
- `snapshotHash CHAR(64)`.
- `createdAt`.

Snapshot immutable. Profile Student thay đổi sau khi apply không được âm thầm thay hồ sơ Enterprise đang review.

### 13.4 Application status mapping

Enterprise write API và Student read API phải dùng cùng canonical values. Trong giai đoạn chuyển đổi, adapter map:

```text
new          -> submitted
reviewing    -> reviewing
interviewing -> interview
accepted     -> accepted
rejected     -> declined
withdrawn    -> withdrawn
```

Sau backfill, Enterprise module cũng phải ghi canonical status; không duy trì hai vocabulary lâu dài.

## 14. Notifications, badges và statistics

### 14.1 `notifications`

Giữ `userId` làm ownership vì notification thuộc account, không trực tiếp thuộc role profile.

Thêm hoặc chuẩn hóa:

- `sourceRole`, `sourceEvent`, `producerUserId NULL`.
- `deepLink VARCHAR(500) NULL` chỉ chấp nhận route allow-list.
- Index `(userId, isRead, createdAt)`.
- Index `(userId, notificationType, createdAt)`.
- Khi mark-read phải scope `WHERE id=:id AND userId=:current_user_id`.

Producer:

- Teacher/School: registration approval, schedule change, published assessment, check-in confirmation.
- Enterprise: application review/interview/result.
- Student services: submission confirmation, withdraw, badge award.

### 14.2 `notification_preferences` — bảng mới dùng chung bốn vai trò

- `userId`, `notificationType` là composite PK.
- `inAppEnabled`, `emailEnabled`.
- `updatedAt`.

Không tạo email worker trong Student core; preference chỉ là contract lưu trữ.

### 14.3 `badges` và `student_badges`

`badges` thêm `code` unique, `description`, `category`, `icon`, `ruleVersion`, timestamps. `ruleCriteria` giữ JSON nhưng chỉ cho schema allow-list, không chạy arbitrary expression.

`student_badges`:

- Unique `(studentId, badgeId)`.
- Thêm `sourceEntityType`, `sourceEntityId`, `ruleVersion`, `revokedAt`.
- Award trong transaction; refresh không tạo trùng.

### 14.4 `experience_levels` — bảng mới

- `code` PK, ví dụ `explorer`, `innovator`, `expert`, `master`.
- `name`, `minConfirmedHours`, `sortOrder`, `isActive`.
- Unique `sortOrder` và check giờ không âm.

Level được tính từ confirmed hours; không lưu level vào Student profile nếu có thể derive.

### 14.5 Statistics

Phase đầu không cần bảng statistics riêng. Dùng aggregate query theo current Student từ:

- `experience_logs.status='confirmed'`
- completed registrations
- published assessments
- completed test attempts/results
- student badges chưa revoke
- verified skills

Chỉ tạo snapshot/materialized aggregate khi đo được performance bottleneck. Không hiển thị school ranking mặc định.

## 15. Integrity constraints và indexes bắt buộc

| Bảng | Constraint/index |
|---|---|
| `student_profiles` | Unique `(userId)` |
| `teacher_profiles` | Unique `(userId)` nếu một profile/user |
| `school_members` | Unique `(schoolId,userId)` |
| `enterprise_members` | Unique `(enterpriseId,userId)` |
| `classes` | Unique `(schoolId,name,academicYear)` |
| `student_skills` | Unique `(studentId,skillId)` |
| `project_members` | Unique `(projectId,studentId)` |
| `activity_registrations` | Unique `(activityId,studentId)` |
| `checkins` | Unique `(registrationId)` |
| `experience_logs` | Unique `(checkinId)` |
| `assessment_scores` | Unique `(assessmentId,criteriaId)` |
| `test_questions` | Unique `(testId,sortOrder)` |
| `test_answers` | Unique `(attemptId,questionId)` |
| `test_results` | Unique `(attemptId)` |
| `internship_requirements` | Unique `(postId,skillId)` |
| `internship_applications` | Unique `(postId,studentId)` — đã có trong schema file |
| `student_badges` | Unique `(studentId,badgeId)` |
| `activity_feedback` | Unique `(registrationId)` |
| `notifications` | Index `(userId,isRead,createdAt)` |

Database Owner phải kiểm tra duplicate và giải quyết dữ liệu trước khi thêm unique key. Không xóa bản ghi trùng tự động; chọn canonical row và remap child FK có audit.

## 16. Bộ truy vấn audit read-only trước migration

Không đưa connection string hoặc credential vào output/log.

### 16.1 Schema metadata

```sql
SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

SELECT table_name, column_name, column_type, is_nullable, column_default, extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
ORDER BY table_name, ordinal_position;

SELECT table_name, index_name, non_unique,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_index
FROM information_schema.statistics
WHERE table_schema = DATABASE()
GROUP BY table_name, index_name, non_unique
ORDER BY table_name, index_name;

SELECT table_name, constraint_name, referenced_table_name,
       update_rule, delete_rule
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
ORDER BY table_name, constraint_name;
```

### 16.2 Duplicate candidates

```sql
SELECT userId, COUNT(*) AS duplicateCount
FROM student_profiles
GROUP BY userId
HAVING COUNT(*) > 1;

SELECT activityId, studentId, COUNT(*) AS duplicateCount
FROM activity_registrations
GROUP BY activityId, studentId
HAVING COUNT(*) > 1;

SELECT registrationId, COUNT(*) AS duplicateCount
FROM checkins
GROUP BY registrationId
HAVING COUNT(*) > 1;

SELECT checkinId, COUNT(*) AS duplicateCount
FROM experience_logs
GROUP BY checkinId
HAVING COUNT(*) > 1;

SELECT studentId, skillId, COUNT(*) AS duplicateCount
FROM student_skills
GROUP BY studentId, skillId
HAVING COUNT(*) > 1;

SELECT attemptId, COUNT(*) AS duplicateCount
FROM test_results
GROUP BY attemptId
HAVING COUNT(*) > 1;

SELECT assessmentId, criteriaId, COUNT(*) AS duplicateCount
FROM assessment_scores
GROUP BY assessmentId, criteriaId
HAVING COUNT(*) > 1;

SELECT studentId, badgeId, COUNT(*) AS duplicateCount
FROM student_badges
GROUP BY studentId, badgeId
HAVING COUNT(*) > 1;
```

### 16.3 Orphan candidates

```sql
SELECT sp.id
FROM student_profiles sp
LEFT JOIN users u ON u.id = sp.userId
WHERE u.id IS NULL;

SELECT sp.id
FROM student_profiles sp
LEFT JOIN classes c ON c.id = sp.classId
WHERE c.id IS NULL;

SELECT ar.id
FROM activity_registrations ar
LEFT JOIN activities a ON a.id = ar.activityId
LEFT JOIN student_profiles sp ON sp.id = ar.studentId
WHERE a.id IS NULL OR sp.id IS NULL;

SELECT c.id
FROM checkins c
LEFT JOIN activity_registrations ar ON ar.id = c.registrationId
LEFT JOIN activity_qr_tokens qt ON qt.id = c.qrTokenId
WHERE ar.id IS NULL OR qt.id IS NULL;

SELECT ia.id
FROM internship_applications ia
LEFT JOIN internship_posts ip ON ip.id = ia.postId
LEFT JOIN student_profiles sp ON sp.id = ia.studentId
WHERE ip.id IS NULL OR sp.id IS NULL;
```

### 16.4 Status distribution

```sql
SELECT 'users' AS entity, status, COUNT(*) AS total FROM users GROUP BY status
UNION ALL
SELECT 'schools', status, COUNT(*) FROM schools GROUP BY status
UNION ALL
SELECT 'enterprises', status, COUNT(*) FROM enterprises GROUP BY status
UNION ALL
SELECT 'activities', status, COUNT(*) FROM activities GROUP BY status
UNION ALL
SELECT 'activity_registrations', status, COUNT(*) FROM activity_registrations GROUP BY status
UNION ALL
SELECT 'assessments', status, COUNT(*) FROM assessments GROUP BY status
UNION ALL
SELECT 'internship_posts', status, COUNT(*) FROM internship_posts GROUP BY status
UNION ALL
SELECT 'internship_applications', status, COUNT(*) FROM internship_applications GROUP BY status
ORDER BY entity, status;
```

### 16.5 Cross-domain consistency

```sql
-- Experience phải khớp Student và activity qua registration/check-in.
SELECT el.id
FROM experience_logs el
JOIN checkins c ON c.id = el.checkinId
JOIN activity_registrations ar ON ar.id = c.registrationId
WHERE el.studentId <> ar.studentId
   OR el.activityId <> ar.activityId;

-- QR token phải thuộc cùng activity với registration.
SELECT c.id
FROM checkins c
JOIN activity_qr_tokens qt ON qt.id = c.qrTokenId
JOIN activity_registrations ar ON ar.id = c.registrationId
WHERE qt.activityId <> ar.activityId;

-- Application post phải thuộc enterprise mà reviewer membership cho phép.
SELECT ia.id, ip.enterpriseId, ia.reviewerId
FROM internship_applications ia
JOIN internship_posts ip ON ip.id = ia.postId
LEFT JOIN enterprise_members em
  ON em.enterpriseId = ip.enterpriseId AND em.userId = ia.reviewerId
WHERE ia.reviewerId IS NOT NULL AND em.id IS NULL;
```

## 17. Dữ liệu reference/seed tối thiểu

Seed chỉ dùng cho development/staging, không chứa PII thật.

### 17.1 Reference data

- Role codes: `learner`, `teacher`, `school`, `enterprise`, `platform_admin`.
- Skill catalog có `code`, `name`, `category`, active state.
- Assessment criteria/rubric version.
- Holland published version gồm đúng 24 câu và sáu dimension.
- Experience levels: Explorer, Innovator, Expert, Master cùng ngưỡng giờ đã duyệt.
- Badge definitions và `ruleCriteria` schema version 1.
- Notification type allow-list.
- Status mapping table/file dùng một lần cho migration backfill.

### 17.2 Dataset integration tối thiểu

Một dataset staging cần có:

1. Một School active/verified.
2. Hai classes trong cùng academic year.
3. Một School admin membership.
4. Một Teacher active thuộc School.
5. Hai Student users, profiles và active enrollments khác nhau.
6. Một Enterprise active/verified và một recruiter membership.
7. Ba skills và evidence ở các verification states khác nhau.
8. Một activity published, một draft và một completed.
9. Registration của hai Student để kiểm thử ownership/capacity.
10. QR token còn hạn và token hết hạn.
11. Confirmed check-in và confirmed experience log.
12. Holland test/version/questions.
13. Published và draft teacher assessment.
14. Internship active và draft.
15. Application cùng profile snapshot và status history.
16. Notification cho từng user để kiểm thử cross-user isolation.

## 18. Luồng dữ liệu bốn vai trò

### 18.1 School → Teacher/Student

1. School tạo school, class và enrollment.
2. Teacher membership/profile xác định school scope.
3. Student đọc primary enrollment để hiển thị trường/lớp.
4. Student không sửa school/class từ profile form.

### 18.2 Teacher → Student

1. Teacher tạo activity draft.
2. Teacher/School publish activity.
3. Student đọc catalog và tạo registration.
4. Teacher duyệt nếu approval mode yêu cầu.
5. Teacher phát QR token.
6. Student submit check-in.
7. Service/Teacher confirm và tạo confirmed experience.
8. Badge/statistics đọc confirmed data.

### 18.3 Teacher evaluation → Student

1. Teacher tạo assessment draft và scores.
2. School review nếu policy yêu cầu.
3. Publish tạo `publishedAt` và notification.
4. Student repository chỉ đọc published row.
5. Withdraw giữ history và phát notification thay đổi.

### 18.4 Enterprise → Student → Enterprise

1. Enterprise member tạo internship post.
2. Post active của enterprise verified xuất hiện ở Student ecosystem.
3. Student grant consent và submit application.
4. Transaction tạo application, immutable profile snapshot, initial history và notification.
5. Enterprise reviewer cập nhật canonical status.
6. Student đọc status history được đánh dấu student-visible.
7. Student withdraw theo policy; không delete application.

### 18.5 School opportunity → Student

1. School tạo opportunity.
2. Student đọc khi active/published.
3. Event nội bộ dùng activity registration/check-in.
4. Scholarship/program internal dùng school opportunity application và snapshot.
5. External mode chỉ redirect tới allow-listed URL, không giả lập application nội bộ.

## 19. Mapping database với Student Portal Phase 1–11

| Phase | Database contract phải sẵn sàng | Gate |
|---|---|---|
| Phase 1 | `users`, `user_roles`, `student_profiles`, membership/enrollment, migration registry, core unique indexes | Login trả đúng user/student; DB mode không fallback mock |
| Phase 2 | Profile joins, skills, certificates, projects, experience, assessments, badges | Dashboard/Talent Passport đọc đúng current Student |
| Phase 3 | `student_profile_details`, evidence, verification, consent, profile shares, audit | Field ownership, expiry/revoke và snapshot đúng |
| Phase 4 | Activities đầy đủ, registration lifecycle/history và unique activity-student | Transaction không vượt capacity/không đăng ký trùng |
| Phase 5 | QR token hash, check-in unique, experience rule/log status | Một registration tạo tối đa một check-in/log |
| Phase 6 | Test version/questions/attempts/answers/results; published evaluation | Resume đa thiết bị, server scoring, không lộ draft evaluation |
| Phase 7 | Verified ecosystem, internship/school opportunity, application/history/snapshot | Consent, duplicate guard, canonical status |
| Phase 8 | Notifications và preferences | Cross-user isolation, unread count chính xác |
| Phase 9 | Badge rules, unique awards, experience levels, aggregate indexes | Chỉ confirmed data; không award trùng |
| Phase 10 | Audit/idempotency/rate-limit support và error-safe contract | Không fake success; ownership negative tests pass |
| Phase 11 | Tất cả migration versions, staging seed, data-quality queries | E2E MariaDB và two-student authorization matrix pass |

Không triển khai AI trong Phase 1–11. Bảng `ai_recommendations` hiện có không phải lý do để mở AI phase.

## 20. Thứ tự migrations đề xuất

Mỗi migration phải idempotent, có preflight, backfill, verification và rollback strategy riêng.

1. **00_readonly_audit** — xuất metadata, duplicate, orphan, status report; không ghi.
2. **01_identity_roles_memberships** — `user_roles`, `school_members`, enterprise membership constraints.
3. **02_school_student_enrollment** — mở rộng school/class, tạo enrollments, backfill từ `classId`.
4. **03_core_integrity_indexes** — xử lý duplicate rồi thêm unique/composite indexes.
5. **04_student_talent_passport** — profile details, skill evidence, certificates, projects, consent/share.
6. **05_activity_registration** — activity fields, registration lifecycle/history, feedback.
7. **06_checkin_experience** — token hash, check-in status/unique, experience rules/log status.
8. **07_assessment_lifecycle** — test version/questions/answers/results và teacher assessment publication.
9. **08_ecosystem_opportunities** — school details, enterprise indexes, school opportunities.
10. **09_applications_snapshots** — canonical status backfill, snapshot, visibility-aware history.
11. **10_notifications** — preferences, unread/deep-link indexes.
12. **11_badges_statistics** — badge integrity, levels và aggregate indexes.
13. **12_deprecations** — chỉ sau khi code bốn vai trò đã chuyển: `users.roles`, `student_profiles.classId`, `teacher_profiles.isSchoolAdmin`.
14. **13_release_verification** — re-run metadata/data-quality/E2E; không chứa DDL mới.

Không gộp tất cả thay đổi vào một migration. Không chạy `Database/Talenthub_DB.sql` để nâng cấp database đang có dữ liệu.

## 21. DDL minh họa

Các ví dụ dưới đây giúp Database Owner hiểu hình dạng target. Tên constraint phải đối chiếu runtime trước khi đưa vào migration.

```sql
-- MINH HỌA, KHÔNG CHẠY TRỰC TIẾP
CREATE TABLE user_roles (
  userId CHAR(36) NOT NULL,
  roleCode VARCHAR(50) NOT NULL,
  grantedByUserId CHAR(36) DEFAULT NULL,
  grantedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revokedAt TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (userId, roleCode),
  KEY idx_user_roles_role (roleCode, revokedAt),
  CONSTRAINT fk_user_roles_user
    FOREIGN KEY (userId) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_roles_granted_by
    FOREIGN KEY (grantedByUserId) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- MINH HỌA, KHÔNG CHẠY TRỰC TIẾP
CREATE TABLE school_members (
  id CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  userId CHAR(36) NOT NULL,
  role VARCHAR(50) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_members_school_user (schoolId, userId),
  KEY idx_school_members_user_status (userId, status),
  CONSTRAINT fk_school_members_school
    FOREIGN KEY (schoolId) REFERENCES schools(id)
    ON UPDATE CASCADE,
  CONSTRAINT fk_school_members_user
    FOREIGN KEY (userId) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- MINH HỌA, KHÔNG CHẠY TRỰC TIẾP
CREATE TABLE student_enrollments (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  classId CHAR(36) DEFAULT NULL,
  academicYear VARCHAR(20) NOT NULL,
  status VARCHAR(50) NOT NULL,
  isPrimary TINYINT(1) NOT NULL DEFAULT 0,
  startedAt DATE NOT NULL,
  endedAt DATE DEFAULT NULL,
  createdByUserId CHAR(36) NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enrollments_student_status (studentId, status, isPrimary),
  KEY idx_enrollments_school_year (schoolId, academicYear, status),
  CONSTRAINT fk_enrollments_student FOREIGN KEY (studentId)
    REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_school FOREIGN KEY (schoolId)
    REFERENCES schools(id) ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_class FOREIGN KEY (classId)
    REFERENCES classes(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_creator FOREIGN KEY (createdByUserId)
    REFERENCES users(id) ON UPDATE CASCADE,
  CONSTRAINT chk_enrollments_dates CHECK (endedAt IS NULL OR endedAt >= startedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- MINH HỌA, KHÔNG CHẠY TRỰC TIẾP
CREATE TABLE test_answers (
  id CHAR(36) NOT NULL,
  attemptId CHAR(36) NOT NULL,
  questionId CHAR(36) NOT NULL,
  answerValue VARCHAR(255) NOT NULL,
  answeredAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_test_answers_attempt_question (attemptId, questionId),
  CONSTRAINT fk_test_answers_attempt FOREIGN KEY (attemptId)
    REFERENCES test_attempts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_answers_question FOREIGN KEY (questionId)
    REFERENCES test_questions(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- MINH HỌA, KHÔNG CHẠY TRỰC TIẾP
CREATE TABLE application_profile_snapshots (
  applicationId CHAR(36) NOT NULL,
  consentId CHAR(36) NOT NULL,
  profileData LONGTEXT NOT NULL CHECK (JSON_VALID(profileData)),
  schemaVersion INT NOT NULL DEFAULT 1,
  snapshotHash CHAR(64) NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (applicationId),
  CONSTRAINT fk_application_snapshots_application FOREIGN KEY (applicationId)
    REFERENCES internship_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_snapshots_consent FOREIGN KEY (consentId)
    REFERENCES privacy_consents(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- MINH HỌA: chỉ thêm unique sau khi duplicate query trả 0 row.
ALTER TABLE activity_registrations
  ADD UNIQUE KEY uq_activity_registrations_activity_student (activityId, studentId);

ALTER TABLE checkins
  ADD UNIQUE KEY uq_checkins_registration (registrationId);

ALTER TABLE experience_logs
  ADD UNIQUE KEY uq_experience_logs_checkin (checkinId);

ALTER TABLE student_badges
  ADD UNIQUE KEY uq_student_badges_student_badge (studentId, badgeId);
```

## 22. Migration safety và deployment gate

Mỗi migration phải đáp ứng:

- Chạy trên MariaDB cùng major/minor với production target.
- Kiểm tra table/column/index/constraint trước khi ALTER.
- Không log DSN, password, token, assessment answers hoặc profile snapshot.
- Backfill theo batch nếu bảng lớn.
- Không giữ transaction quá dài với DDL MariaDB.
- Có bước xác minh row count và checksum hợp lý trước/sau.
- Chạy lần hai không tạo object trùng hoặc ghi dữ liệu trùng.
- Code reader tương thích trước khi drop/deprecate cột cũ.
- Negative authorization test có ít nhất hai Student, hai organizations.
- Forbidden-role scope không có nghĩa là database contract của role khác bị bỏ qua; mọi shared schema change cần owner của domain ký duyệt.

## 23. Acceptance criteria cho Database Owner

Database được coi là đủ cho Student Portal core khi tất cả điều kiện sau đạt:

1. Runtime schema và migration registry khớp danh sách release.
2. Không có duplicate trong các unique business key ở phần 15.
3. Không có orphan ở các quan hệ Student–School–Teacher–Enterprise.
4. Tất cả status hiện hữu đã map sang canonical vocabulary hoặc được quarantine có báo cáo.
5. Một user có thể được authorize theo role và organization membership.
6. Student enrollment có lịch sử và xác định được primary school/class.
7. Talent Passport phân biệt self-declared, pending, verified, rejected và revoked.
8. Enterprise chỉ nhận đúng profile snapshot/field đã consent.
9. Registration, check-in, experience, result và badge có idempotency constraint.
10. Student không đọc assessment chưa published.
11. Student không đọc/write dữ liệu của Student khác.
12. Teacher không thao tác activity/assessment ngoài school/teacher scope.
13. School không thao tác enrollment của school khác.
14. Enterprise không thao tác post/application của enterprise khác.
15. Notification ownership dựa trên `userId` và unread index hoạt động.
16. Personal statistics chỉ tính confirmed/published/completed data hợp lệ.
17. Full MariaDB E2E chạy: login → profile → registration → check-in → hours → assessment → application → notification → badge/statistics.
18. Không còn action Student nào báo thành công chỉ vì DOM hoặc `localStorage` đã đổi.
19. Không chạy hoặc triển khai AI trong release core.

## 24. Quyết định cần Database Owner và domain owners ký duyệt

Trước khi viết migrations, cần ký duyệt rõ các quyết định sau:

1. Canonical status vocabulary ở phần 6.
2. `user_roles` + organization membership thay cho `users.roles`.
3. `student_enrollments` thay cho direct `student_profiles.classId`.
4. Chính sách archive/restrict thay cho cascade delete ở activity/post đã có history.
5. Field ownership của Student profile, skill và certificate.
6. Teacher/School publication workflow cho assessments.
7. Check-in confirmation mode và điều kiện cộng confirmed hours.
8. School opportunities dùng activity flow hay internal application flow.
9. Enterprise access chỉ qua consent share hoặc immutable application snapshot.
10. Migration/backfill plan cho dữ liệu status cũ.

Sau khi mười quyết định này được duyệt, đội database mới chuyển blueprint thành các migration nhỏ theo thứ tự phần 20.
