# Student Shared Core and Vertical Slices Design

**Ngày:** 2026-08-15  
**Trạng thái:** Đã được người dùng duyệt hướng triển khai  
**Phạm vi:** Vai trò Học sinh/Sinh viên (Student/Learner), cùng các thay đổi shared-core tối thiểu cần thiết để tích hợp an toàn với Teacher, School và Business/Enterprise

## 1. Mục tiêu

Hoàn thiện Student Portal bằng dữ liệu thật và luồng nghiệp vụ thật, thống nhất với database và ba vai trò còn lại. Việc triển khai ưu tiên tính tương thích, toàn vẹn dữ liệu và khả năng nâng cấp sau này; không tái thiết kế lớn hoặc tối ưu sớm khi các luồng cốt lõi chưa hoàn thành.

Kết quả mục tiêu:

- Student dùng identity và `student_id` lấy từ phiên đăng nhập thực.
- Frontend Student giao tiếp với API dùng chung thay vì dùng mock hoặc `localStorage` làm nguồn dữ liệu chính.
- Backend Student tuân theo cấu trúc repository/service/router hiện có trong `src`.
- Database dùng một schema và một chuỗi migration chuẩn cho cả bốn vai trò.
- Dữ liệu do School, Teacher hoặc Enterprise tạo được Student đọc đúng quyền; dữ liệu Student tạo được các vai trò liên quan xử lý đúng quyền.
- Mỗi vertical slice có kiểm thử backend, API, database, frontend và hồi quy liên vai trò trước khi được coi là hoàn thành.

## 2. Hiện trạng làm cơ sở thiết kế

- Phase 0 readiness đang trả `READY`.
- Phase 1 đang trả `NOT_READY` vì Student data source vẫn là `mock`, chưa cấu hình PDO và chưa có `student_id` runtime.
- Shared core đã có authentication, session, CSRF, RBAC, JSON response, PDO connection và migration framework.
- `src/Bootstrap/Application.php` đã có các endpoint hồ sơ và dashboard Student cơ bản.
- `src/Modules/Student` đã có repository và service hồ sơ cơ bản nhưng chưa bao phủ các domain Student còn lại.
- Giao diện Student hiện có đủ nền tảng để mở rộng, nhưng đăng ký hoạt động và assessment vẫn ghi vào `localStorage`.
- Database hiện có phần lớn bảng nghiệp vụ cốt lõi, nhưng cần audit constraint, lifecycle status, ownership và các bảng liên kết còn thiếu trước khi bật ghi production.

## 3. Quyết định kiến trúc

Chọn mô hình **Shared Core + Vertical Slices**.

```text
Student frontend
      |
      v
Shared /api/v1 endpoints
      |
      v
src/Modules/Student services and repositories
      |
      v
Shared PDO, transactions, RBAC and audit
      |
      v
Canonical TalentHub database
      |
      +--> School consumers
      +--> Teacher consumers
      +--> Business/Enterprise consumers
```

Không tạo một hệ authentication, session, API envelope, PDO factory hoặc production migration runner thứ hai bên trong `app/learner`. Các lớp learner hiện có chỉ được giữ khi chúng đóng vai trò view adapter/read model, mock test fixture hoặc readiness tooling không cạnh tranh với shared core.

Mỗi vertical slice phải hoàn thiện theo chuỗi:

```text
schema/contract -> repository -> service -> API -> frontend -> integration tests
```

Không chuyển sang slice tiếp theo khi slice hiện tại chỉ render được giao diện nhưng chưa lưu/đọc dữ liệu thật và chưa kiểm tra quyền.

## 4. Ranh giới component

### 4.1 Shared core

Shared core chịu trách nhiệm:

- Khởi tạo PDO và quản lý lỗi kết nối.
- Authentication, session, CSRF và login rate limiting.
- RBAC và permission vocabulary.
- Request parsing, route dispatch, JSON response/error envelope và request ID.
- Migration registry/runner dùng chung.
- Audit log và các primitive giao dịch dùng chung.

Các file shared-core chỉ được chỉnh khi Student cần mở rộng contract chung. Thay đổi phải giữ tương thích với Teacher, School và Business/Enterprise.

### 4.2 Student backend module

`src/Modules/Student` là implementation production cho nghiệp vụ Student:

- Repository chỉ truy cập dữ liệu và luôn scope bằng identity/ownership phù hợp.
- Service thực hiện validation, authorization cấp nghiệp vụ, transaction và chuyển dữ liệu sang API shape.
- Không đặt HTML hoặc logic giao diện trong service/repository.
- Không nhận tên bảng, tên cột hoặc SQL fragment từ request.

### 4.3 Student frontend

`app/learner` và learner-owned assets chịu trách nhiệm:

- Render layout, page state và tương tác người dùng.
- Gọi API dùng chung thông qua một client thống nhất.
- Hiển thị loading, empty, success, validation, authorization, expired-session và database-error states.
- Dùng `localStorage` chỉ cho draft/cache có thể mất, không dùng làm nguồn sự thật cho đăng ký, check-in, assessment, application hoặc profile.

### 4.4 Read model và compatibility layer

Read model/adapters hiện có trong `app/learner/data` có thể được dùng làm biên tương thích trong quá trình chuyển đổi. Chúng không được tạo một production data path song song với `src/Modules/Student`.

Khi một trang đã chuyển sang API production và có regression coverage, mock path của trang đó chỉ còn phục vụ demo/test có chủ đích.

## 5. Database và ownership bốn vai trò

### 5.1 Một nguồn dữ liệu cho mỗi domain

- Identity và role: `users`, `roles`, `permissions`, `user_roles` cùng các membership liên quan.
- School/class/student relationship: `schools`, `classes`, `student_profiles` và enrollment/membership contract được migration chuẩn xác định.
- Activities: `activities`, registration, QR token, check-in, experience và feedback.
- Assessment: test, question, attempt, answer, result và Teacher assessment/evaluation.
- Opportunity/application: Enterprise/School opportunity, Student application, status history và consent snapshot.
- Talent Passport: profile details, skills/evidence, certificates, projects, experience, evaluations và badges.
- Notification/statistics: notification/preferences, badge/level rules và các aggregate query có nguồn rõ ràng.

Không tạo bảng `learner_*` nếu bảng đó trùng ý nghĩa với domain dùng chung.

### 5.2 Quyền sở hữu dữ liệu

- School quản lý school, class, enrollment và các trường hồ sơ hành chính được chỉ định.
- Teacher quản lý assessment/evaluation và xác nhận nghiệp vụ thuộc quyền Teacher.
- Business/Enterprise quản lý internship/opportunity và quyết định trạng thái ứng tuyển thuộc quyền doanh nghiệp.
- Student quản lý dữ liệu tự khai, consent, registration, submission và các thao tác cá nhân được cho phép.
- Shared service kiểm soát chuyển trạng thái; frontend không tự quyết định trạng thái nghiệp vụ cuối cùng.

### 5.3 Migration policy

- Production schema dùng migration framework chung trong `Database/migrations`.
- Migration Student là additive và idempotent: ưu tiên tạo bảng liên kết, column, foreign key, unique/index và canonical status còn thiếu.
- Không sửa trực tiếp dữ liệu production để che lỗi migration.
- Trước migration phải audit duplicate, orphan, status distribution và cross-domain consistency.
- Migration có ảnh hưởng dữ liệu dùng chung phải có forward verification và rollback/compensation strategy phù hợp.
- `Database/Talenthub_DB.sql` là schema snapshot; chỉ đồng bộ lại sau khi migration đã được kiểm chứng nếu convention của repository yêu cầu.

## 6. API contract

Student API mở rộng dưới namespace `/api/v1/students/me` và dùng chung authentication/session hiện có.

Nhóm endpoint dự kiến:

- Profile, dashboard và Talent Passport.
- Privacy consent và profile sharing.
- Activities, registrations, QR check-in và experience.
- Assessment attempts, answers, results và published evaluations.
- Opportunities, applications, withdrawal và application history.
- Notifications, preferences, badges và statistics.

Mọi endpoint phải tuân theo:

- Response envelope shared-core hiện có.
- HTTP method và status code nhất quán.
- Session bắt buộc cho dữ liệu cá nhân.
- Role `student` và permission tương ứng.
- CSRF cho request thay đổi trạng thái.
- Validation lỗi theo field khi phù hợp.
- Ownership dựa trên current user/current student, không nhận `student_id` tùy ý từ client.
- Transaction cho nghiệp vụ ghi nhiều bảng.
- Idempotency hoặc unique constraint cho đăng ký hoạt động, check-in, submit assessment và application khi cần.
- Không lộ SQL, DSN, credential hoặc stack trace trong API response.

## 7. Trình tự phát triển nghiệp vụ

### Slice 1 — Production foundation

Kết nối Student frontend với shared auth/session/API, xác lập current Student identity từ database, hoàn thiện permission và migration gate. Slice này là điều kiện bắt buộc cho các slice còn lại.

### Slice 2 — Profile, dashboard và Talent Passport

Đưa dashboard/profile sang dữ liệu thật; hoàn thiện phần tự khai, skill/evidence, certificate, project, experience, evaluation và badge read model. Không hiển thị AI insight giả khi dữ liệu chưa đủ.

### Slice 3 — Profile editing, consent và sharing

Phân tách field ownership, hỗ trợ chỉnh dữ liệu được phép, privacy consent và cơ chế chia sẻ hồ sơ có kiểm soát.

### Slice 4 — Activities và registration lifecycle

Đọc catalog hoạt động dùng chung, đăng ký/hủy theo transition hợp lệ, chống đăng ký trùng và phản ánh trạng thái do School/Teacher quản lý.

### Slice 5 — QR check-in và experience

Xác thực QR token, registration, thời gian và anti-replay trong transaction; chỉ tạo experience hợp lệ một lần.

### Slice 6 — Assessment và Teacher evaluation

Lưu attempt/answer/result thật; Student chỉ thấy evaluation đã publish và không thể sửa dữ liệu thuộc Teacher.

### Slice 7 — Opportunities và application lifecycle

Hiển thị cơ hội từ School/Enterprise, submit/withdraw application theo transition cho phép, lưu consent snapshot và status history để Enterprise xử lý.

### Slice 8 — Notifications

Tạo notification center và preference; event từ các domain khác được chuyển thành thông báo Student theo contract chung.

### Slice 9 — Badges và statistics

Tính badge/level/statistics từ dữ liệu nguồn có thể truy vết, không lưu aggregate mâu thuẫn nếu có thể tính lại an toàn.

### Slice 10 — Frontend completion và hardening

Hoàn thiện responsive/accessibility, state handling, security headers phù hợp, performance cơ bản và xóa các production path còn phụ thuộc mock/localStorage.

### Slice 11 — Release gate

Chạy migration/integration/regression đầy đủ, xác minh dữ liệu bốn vai trò, negative authorization, duplicate/idempotency, accessibility và recovery behavior.

AI recommendation bị hoãn đến sau khi Slice 1-11 đạt release gate.

## 8. Luồng dữ liệu liên vai trò

### School → Student

School tạo/quản lý lớp, enrollment và cơ hội thuộc trường. Student chỉ đọc dữ liệu thuộc enrollment hiện hành và thao tác trong quyền được cấp.

### Teacher → Student

Teacher tạo đánh giá/xác nhận. Student chỉ đọc bản đã publish; mọi thay đổi trạng thái Teacher-owned phải đi qua Teacher service.

### Enterprise → Student → Enterprise

Enterprise tạo internship/opportunity. Student gửi application cùng consent snapshot. Enterprise đọc snapshot được chia sẻ và cập nhật trạng thái qua transition hợp lệ; Student nhận status/history/notification.

### Student → các vai trò khác

Student profile tự khai, registration, assessment submission và application chỉ được chia sẻ theo permission và consent. Dữ liệu hành chính hoặc xác minh không bị Student tự sửa.

## 9. Xử lý lỗi và trạng thái

- `400`: request/body/method không hợp lệ ở mức giao thức.
- `401`: chưa đăng nhập hoặc phiên hết hạn.
- `403`: sai role, thiếu permission, thiếu consent hoặc không sở hữu tài nguyên.
- `404`: tài nguyên không tồn tại trong scope hiện tại.
- `409`: transition không hợp lệ, duplicate hoặc conflict đồng thời.
- `422`: validation nghiệp vụ/field.
- `500/503`: lỗi nội bộ hoặc database unavailable, trả thông báo an toàn kèm request ID.

Frontend phải phân biệt các trạng thái trên để đưa ra CTA phù hợp; không tự fallback sang mock khi API/database lỗi.

## 10. Testing và release gates

Mỗi slice cần tối thiểu:

- Unit test cho validation, mapping và transition rules.
- Repository/integration test với MariaDB cho SQL và constraint đặc thù.
- API test cho success, validation, unauthenticated, wrong role, wrong owner, CSRF và conflict/idempotency.
- Render/UI test cho loading, empty, error và success states.
- Regression toàn bộ learner tests hiện có.
- PHP lint, JavaScript syntax check và `git diff --check`.
- Regression shared-core và smoke test Teacher, School, Business/Enterprise khi shared file hoặc shared schema bị thay đổi.

Không đánh dấu slice hoàn thành nếu chỉ test mock hoặc chỉ test happy path.

## 11. Phạm vi chỉnh sửa

### Mặc định được phép

- `app/learner/**`
- Learner-owned CSS/JavaScript assets
- `src/Modules/Student/**`
- Student tests, fixtures và documentation
- Student-specific additive migrations theo migration policy

### Được phép khi có dependency thật và kiểm tra hồi quy

- `src/Bootstrap/Application.php`
- Shared migration/schema infrastructure
- Shared permissions/reference seeds
- Shared HTTP/auth/RBAC primitives nếu contract Student không thể thực hiện bằng API hiện có

### Không thuộc phạm vi

- Thiết kế lại hoặc sửa nghiệp vụ riêng của Teacher, School hoặc Business/Enterprise.
- Refactor shared core không phục vụ trực tiếp Student.
- AI recommendation trước release gate Student core.
- Đổi canonical status/ownership mà không có migration và compatibility mapping.

## 12. Tiêu chí hoàn thành tổng thể

Student Portal core hoàn thành khi:

- Production không cần mock hoặc demo identity.
- Các luồng profile, activity, check-in, assessment, application, notification và badge/statistics dùng dữ liệu thật.
- Database không có duplicate/orphan do luồng Student tạo ra.
- Quyền và ownership được kiểm tra ở backend, không chỉ ở giao diện.
- Teacher, School và Business/Enterprise tiếp tục hoạt động sau thay đổi shared-core/schema.
- Tất cả release gates và negative authorization tests đạt.
- Tài liệu contract, migration order và vận hành phản ánh đúng implementation thực tế.

