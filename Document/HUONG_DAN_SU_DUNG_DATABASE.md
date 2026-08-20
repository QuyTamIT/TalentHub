# Hướng dẫn sử dụng Database TalentHub

> Tài liệu dành cho lập trình viên và AI Agent làm giao diện Student, Teacher, School và Enterprise. Nguồn sự thật của cấu trúc database là các migration trong repository, không phải một bản dump hoặc schema tự ghi bên ngoài.

## 1. Mục tiêu và nguyên tắc bắt buộc

TalentHub dùng MySQL 8.4, bảng InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci`, khóa chính dạng UUID lưu trong `CHAR(36)` và thời gian chính xác microsecond bằng `DATETIME(6)`.

Các nguyên tắc chung:

1. Không sửa cấu trúc database bằng Adminer, phpMyAdmin, HeidiSQL hoặc câu `ALTER TABLE` chạy tay rồi chỉ lưu ở máy cá nhân.
2. Mọi thay đổi schema dùng chung phải nằm trong một migration mới ở `Database/migrations/`.
3. Không sửa hoặc xóa migration đã được áp dụng trên database dùng chung. Nếu cần sửa hậu quả của migration cũ, tạo migration reconcile mới, idempotent và forward-only.
4. Không đưa mật khẩu hoặc file `.env` lên Git. Chỉ commit `.env.example` khi cần bổ sung tên biến môi trường.
5. Code giao diện không kết nối MySQL trực tiếp từ JavaScript và không chứa thông tin đăng nhập database. UI gọi PHP/service/repository/API ở server.
6. Không bỏ qua foreign key, unique index, `CHECK constraint` hoặc RBAC bằng cách tắt kiểm tra database.
7. Không dùng dữ liệu thật trong seed/test. Seed demo/testing chỉ được chạy ở môi trường `local` hoặc `test`.
8. Trước migration trên database có dữ liệu phải backup và kiểm tra đúng `DB_DATABASE`.

## 2. Cấu hình kết nối

Sao chép `.env.example` thành `.env` và cấu hình tối thiểu:

```dotenv
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=talenthub
DB_USERNAME=talenthub_app
DB_PASSWORD=your_local_password
DB_CONNECT_TIMEOUT=5
DB_PERSISTENT=false
```

Yêu cầu máy chạy:

- PHP có extension `pdo_mysql`.
- MySQL 8.4.x đang hoạt động.
- Database trong `DB_DATABASE` đã được tạo.
- User database có quyền cần thiết để chạy migration trong môi trường phát triển.
- Session database nên dùng timezone UTC (`+00:00`); ứng dụng chịu trách nhiệm hiển thị theo múi giờ người dùng.

Kiểm tra kết nối mà không thay đổi dữ liệu:

```powershell
php bin/connect-check.php
php bin/connect-check.php --quick
php bin/connect-check.php --json
```

Không dán nội dung `.env`, password, token QR thô hoặc dữ liệu cá nhân vào prompt cho AI Agent.

## 3. Khởi tạo database mới đúng chuẩn

Thứ tự chuẩn cho một database trống:

```powershell
php bin/connect-check.php --quick
php bin/migrate.php status
php bin/migrate.php validate
php bin/migrate.php migrate
php bin/seed.php
php bin/migrate.php validate
php bin/connect-check.php
```

Ý nghĩa:

- `status`: xem migration đã chạy và còn pending.
- `validate`: kiểm tra lịch sử migration và phát hiện source drift.
- `migrate`: áp dụng các migration chưa chạy theo thứ tự tên file.
- `seed`: đồng bộ bốn role hệ thống, permission và `role_permissions`; có thể chạy lại an toàn.
- `connect-check`: xác nhận kết nối và liệt kê bảng thực tế.

Không cần import dump để tạo database mới nếu migration có thể chạy từ đầu. Nếu phải import dump, hãy import vào database trống, sau đó vẫn chạy `status`, `migrate`, `seed` và `validate` để schema hội tụ về phiên bản hiện tại.

## 4. Cập nhật database đã tồn tại

Quy trình cho mỗi lần pull code có migration mới:

1. Xác nhận `.env` đang trỏ đúng database.
2. Backup database nếu database có dữ liệu cần giữ.
3. Chạy `php bin/migrate.php status`.
4. Chạy `php bin/migrate.php validate`; nếu báo drift thì dừng lại, không chạy tiếp.
5. Chạy `php bin/migrate.php migrate`.
6. Chạy `php bin/seed.php` khi permission/role system thay đổi.
7. Chạy lại `php bin/migrate.php validate` và test liên quan.

Production mặc định chặn migration. Chỉ quy trình deployment được phê duyệt mới đặt `ALLOW_PRODUCTION_MIGRATIONS=true`. Không bật `ALLOW_PRODUCTION_ROLLBACK` trừ khi migration được xác nhận reversible và có kế hoạch khôi phục dữ liệu.

Các migration QR/check-in hiện tại là forward-only vì rollback có thể làm mất liên kết check-in. Không cố rollback chúng.

## 5. Bản đồ schema dùng chung

| Nhóm | Bảng chính | Chức năng |
|---|---|---|
| Identity và RBAC | `roles`, `permissions`, `role_permissions`, `users` | Tài khoản, role hệ thống, permission và ánh xạ role-permission |
| Tổ chức | `schools`, `enterprises`, `classes` | Trường học, doanh nghiệp, lớp và trạng thái tổ chức |
| Hồ sơ/thành viên | `student_profiles`, `teacher_profiles`, `school_members`, `enterprise_members` | Nối user với hồ sơ nghiệp vụ và tổ chức sở hữu |
| Hoạt động | `activities`, `activity_registrations` | Hoạt động do giáo viên quản lý và đăng ký của học sinh |
| QR/check-in | `activity_qr_sessions`, `checkins` | Phiên QR có hạn dùng/số lượt quét và check-in duy nhất cho mỗi registration |
| Đánh giá | `assessment_criteria`, `assessments`, `assessment_scores` | Tiêu chí, đánh giá giáo viên và điểm theo tiêu chí |
| School reporting | `reports` | Báo cáo thuộc phạm vi trường học |
| An toàn/vận hành | `audit_logs`, `auth_rate_limits`, `schema_migrations` | Audit hành động, giới hạn đăng nhập và lịch sử migration |

Các quan hệ quan trọng:

```text
roles -> users -> student_profiles -> activity_registrations -> checkins
              -> teacher_profiles -> activities -> activity_qr_sessions
schools -> classes -> student_profiles
schools -> teacher_profiles
activities + student_profiles -> activity_registrations -> assessments -> assessment_scores
enterprises -> enterprise_members -> users
```

Không tự suy đoán tên cột snake_case. Schema dùng tên cột camelCase như `roleId`, `studentId`, `createdAt`, trong khi read model/API có thể chuyển thành snake_case cho frontend.

## 6. Phạm vi dữ liệu theo từng giao diện

### Student

Student đọc/cập nhật hồ sơ của chính mình, đọc hoạt động khả dụng, tạo/hủy đăng ký của chính mình, thực hiện check-in hợp lệ và đọc trải nghiệm/đánh giá được công bố của mình. Chuỗi dữ liệu cơ bản:

```text
users -> student_profiles -> activity_registrations -> checkins
```

Không cho client tự gửi `studentId` tùy ý rồi tin giá trị đó. Backend phải lấy identity từ session đã xác thực và kiểm tra ownership.

### Teacher

Teacher quản lý hồ sơ của mình, hoạt động do mình tạo, registration thuộc hoạt động mình quản lý, QR session, check-in và assessment tương ứng. Các permission quan trọng gồm `activity.*_managed`, `activity_registration.read_managed`, `qr_session.*_managed`, `checkin.read_managed` và `assessment.*_managed`.

Backend phải kiểm tra `activities.createdByTeacherId` hoặc phạm vi quản lý tương đương; có permission không đồng nghĩa được đọc hoạt động của mọi giáo viên.

### School

School làm việc trong phạm vi `schoolId`: hồ sơ trường, lớp, student/teacher thuộc trường, hoạt động của trường và báo cáo. Mọi truy vấn danh sách phải lọc theo school đang đăng nhập, không nhận `schoolId` từ request mà không xác minh membership.

### Enterprise

Enterprise làm việc trong phạm vi `enterpriseId` qua `enterprise_members`: hồ sơ doanh nghiệp, thành viên và các domain tuyển dụng/tài trợ khi module tương ứng có schema/API. Không giả định một bảng đã tồn tại chỉ vì giao diện có mockup. Luôn kiểm tra migration và repository hiện hành trước khi nối UI.

## 7. RBAC và tài khoản

Bốn role chuẩn là `student`, `teacher`, `school`, `enterprise`. Không tạo role `business`; migration đã chuẩn hóa tên legacy này thành `enterprise`.

Permission chuẩn được khai báo trong `Database/seeds/System/RolePermissionSeeder.php`. Khi thêm một chức năng protected:

1. Chọn permission theo mẫu `resource.action_scope`, ví dụ `checkin.read_managed`.
2. Thêm permission vào đúng role trong `RolePermissionSeeder`.
3. Bảo vệ endpoint/page bằng permission service ở backend.
4. Kiểm tra ownership/scope trong query hoặc service.
5. Chạy `php bin/seed.php` để đồng bộ database.
6. Viết test cho phép đúng role và từ chối role/owner khác.

Ẩn nút ở frontend chỉ là UX, không phải authorization. Backend luôn phải kiểm tra lại.

Không insert trực tiếp role/permission với UUID ngẫu nhiên. Seeder tạo ID ổn định và đồng bộ chính xác mapping chuẩn.

## 8. Quy tắc dữ liệu cần giữ nguyên

- Email trong `users` là duy nhất.
- Mỗi user chỉ có tối đa một `student_profile` hoặc một `teacher_profile` tương ứng theo unique key hiện tại.
- Mỗi student chỉ đăng ký một lần cho mỗi activity: unique `(activityId, studentId)`.
- Mỗi registration chỉ có một check-in: `uq_checkins_registration`.
- QR runtime dùng `activity_qr_sessions`; không viết code mới dựa trên `activity_qr_tokens` hoặc `qrTokenId` legacy.
- Không lưu hoặc log QR token thô; chỉ lưu hash theo contract của service.
- `checkins.status` chỉ nhận `pending`, `checked_in`, `confirmed`, `rejected` và timestamp phải phù hợp status.
- Assessment `published` phải có `overallScore` và `publishedAt`.
- Không xóa parent row nếu foreign key không cho phép. Dùng lifecycle status/archive theo domain.
- Dùng transaction cho luồng ghi nhiều bảng, ví dụ registration/check-in/audit hoặc assessment/scores/audit.
- Dùng prepared statement; không ghép input người dùng vào SQL.

## 9. Migration AI/Learner riêng

`Database/migrations/learner/` chứa foundation, input extensions và recommendation store cho learner/AI. Đây không phải luồng được `php bin/migrate.php migrate` tự động áp dụng.

Các migration này có runner, registry và quy trình phê duyệt riêng; chỉ chạy đúng version đã được phê duyệt trên đúng database mục tiêu. Đọc tài liệu trong `docs/superpowers/database-change-requests/` trước khi thao tác. Thành viên frontend không tự chạy, sửa checksum, chèn registry row hoặc copy SQL từ các file này vào database dùng chung.

Nếu UI cần một bảng AI/learner nhưng bảng chưa tồn tại, báo dependency thiếu cho người phụ trách database thay vì tự tạo bảng.

## 10. Cách thêm hoặc thay đổi schema

Khi chức năng mới thực sự cần thay đổi database:

1. Kiểm tra xem dữ liệu đã có bảng/cột tương đương chưa.
2. Xác định owner của dữ liệu, vòng đời status, foreign key, unique rule và permission.
3. Tạo migration mới có version lớn hơn migration mới nhất; không sửa migration đã deploy.
4. Viết `preflight()` để dừng sớm khi schema đầu vào không phù hợp.
5. Viết `up()` idempotent khi migration có nhiệm vụ reconcile dump/legacy schema.
6. Chỉ viết `down()` khi rollback không làm mất hoặc hiểu sai dữ liệu; nếu không, đặt migration là irreversible.
7. Thêm index cho foreign key và các truy vấn scope phổ biến.
8. Thêm `CHECK constraint` cho enum/status và quy tắc timestamp.
9. Viết contract/integration test và thử trên database test trống lẫn bản sao dump.
10. Chạy migration hai lần trong test: lần đầu thành công, lần hai phải no-op.
11. Cập nhật tài liệu này nếu contract mà frontend cần biết thay đổi.

Không đưa SQL migration vào code chạy request. Ứng dụng không được tự sửa schema khi người dùng mở trang.

## 11. Import dump an toàn

1. Tạo database đích mới hoặc backup database đích hiện tại.
2. Kiểm tra dump không chứa secret hoặc dữ liệu production ngoài phạm vi được phép.
3. Import dump.
4. Cấu hình `.env` trỏ chính xác database vừa import.
5. Chạy `php bin/connect-check.php` và `php bin/migrate.php status`.
6. Nếu `validate` báo checksum drift hoặc lịch sử migration mâu thuẫn, dừng và nhờ người phụ trách database phân tích.
7. Nếu hợp lệ, chạy `php bin/migrate.php migrate`, sau đó `php bin/seed.php`.
8. Chạy `php bin/migrate.php validate` và smoke/integration test.

Không xóa `schema_migrations`, không đánh dấu migration “đã chạy” bằng tay, và không đổi checksum để ép validation pass.

## 12. Chẩn đoán lỗi thường gặp

| Hiện tượng | Kiểm tra | Cách xử lý đúng |
|---|---|---|
| `DATABASE_CONNECTION_FAILED` | MySQL, host/port, DB name, user/password, `pdo_mysql` | Sửa môi trường; không sửa migration |
| `schema_migrations table not found` | Database có trống hay là dump legacy | Với DB trống chạy migration; với dump phải audit trước |
| Migration báo drift/checksum | File migration đã bị sửa sau khi deploy | Dừng, khôi phục source đúng hoặc tạo migration mới |
| Lỗi duplicate khi thêm unique | Dữ liệu cũ có nhiều row cùng business key | Backup, xác định record hợp lệ và lập kế hoạch làm sạch có review |
| Lỗi foreign key | Parent không tồn tại hoặc sai thứ tự tạo dữ liệu | Tạo parent đúng domain trước; không tắt FK checks |
| Lỗi `CHECK constraint` | Status/timestamp không hợp lệ | Sửa luồng nghiệp vụ hoặc dữ liệu legacy có kiểm soát |
| Teacher bị 403 khi đọc check-in | Thiếu seed hoặc scope ownership sai | Chạy system seed, kiểm tra `checkin.read_managed` và activity owner |
| UI có dữ liệu nhưng refresh mất | UI còn dùng mock/localStorage | Xây API/repository persistence; không coi mock là database |

## 13. Checklist bàn giao cho lập trình viên hoặc AI Agent

Trước khi code:

- Đọc file này, migration liên quan, repository/service và test liên quan.
- Chạy `php bin/connect-check.php --quick` và `php bin/migrate.php status`.
- Xác định role, permission, owner/scope và bảng nguồn.
- Phân biệt mock/read model với dữ liệu thực trong MySQL.

Trước khi tạo pull request:

- Không có secret, dump thật hoặc `.env` trong diff.
- Không có SQL nối chuỗi từ input.
- Endpoint kiểm tra authentication, permission và ownership.
- Mutation nhiều bảng có transaction và audit khi cần.
- Migration mới không phá migration history và có test.
- Đã chạy PHP lint, test liên quan và `php bin/migrate.php validate` trên database test phù hợp.
- Đã mô tả rõ developer khác cần chạy `migrate`, `seed`, hay cả hai.

## 14. Các file nguồn cần đọc khi cần xác minh

- Cấu hình mẫu: `.env.example`
- Kết nối: `config/database.php`, `src/Database/Connection.php`
- Migration runner: `bin/migrate.php`, `src/Database/Migration/`
- Shared schema: `Database/migrations/*.php`
- RBAC system seed: `Database/seeds/System/RolePermissionSeeder.php`
- Seed entry point: `bin/seed.php`
- Health check: `bin/connect-check.php`
- Database smoke test: `bin/smoke-database.php`, `tests/Smoke/DatabaseMigrationSmoke.php`
- Learner/AI controlled migrations: `Database/migrations/learner/`
- Database change approvals: `docs/superpowers/database-change-requests/`

Khi tài liệu và code khác nhau, migration đã validate cùng repository/service hiện tại là nguồn kỹ thuật ưu tiên; đồng thời phải cập nhật lại tài liệu trong cùng thay đổi.
