# FTalentHub

Ứng dụng PHP 8.3 + MySQL, gồm các cổng Học viên, Giáo viên, Nhà trường, Doanh nghiệp và Quản trị viên.

## Chạy nhanh ở local

### Yêu cầu

- PHP 8.3 trở lên với các extension `pdo_mysql`, `mbstring`, `json`, `curl`, `openssl`
- MySQL tương thích MySQL 8
- File `.env` đã được tạo từ `.env.example`

Tạo file cấu hình lần đầu:

```bash
cp .env.example .env
```

Các biến bắt buộc cho setup dữ liệu mẫu:

```dotenv
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=talenthub
DB_USERNAME=talenthub_app
DB_PASSWORD=your_database_password
TALENTHUB_TEST_PASSWORD=choose_a_password_with_12_characters
TALENTHUB_ADMIN_PASSWORD=choose_an_admin_password_with_12_characters
```

Tài khoản/database trong `.env` phải tồn tại và có quyền tạo bảng, index, trigger, cùng quyền đọc/ghi dữ liệu trong schema được cấu hình.

### 1. Migrate và tạo toàn bộ dữ liệu mẫu

```bash
php bin/setup-local.php
```

Lệnh có thể chạy lặp lại an toàn. Nó thực hiện migration, seed quyền hệ thống, 12 bộ câu hỏi đánh giá, dữ liệu trường/lớp/học viên/giáo viên, kỹ năng, hoạt động, đánh giá, dự án, cơ hội tuyển dụng, chứng chỉ và các tài khoản test.

Lệnh này bị khóa cứng ở `APP_ENV=local` hoặc `APP_ENV=test`; không chạy trên staging/production.

### 2. Chạy web

```bash
php -S 127.0.0.1:8080 -t .
```

Mở:

- Trang chủ: <http://127.0.0.1:8080/>
- Đăng nhập: <http://127.0.0.1:8080/login.php>

### Tài khoản mẫu

| Vai trò | Email | Mật khẩu lấy từ |
| --- | --- | --- |
| Học viên (hồ sơ có dữ liệu phong phú) | `hs.minh@talenthub.vn` | `TALENTHUB_TEST_PASSWORD` |
| Giáo viên | `gv.mai@talenthub.vn` | `TALENTHUB_TEST_PASSWORD` |
| Nhà trường | `school.admin@talenthub.vn` | `TALENTHUB_TEST_PASSWORD` |
| Doanh nghiệp | `business@test.talenthub.local` | `TALENTHUB_TEST_PASSWORD` |
| Quản trị viên | `admin@admin.com` | `TALENTHUB_ADMIN_PASSWORD` |

Ngoài ra có các tài khoản fixture tối giản: `student@test.talenthub.local`, `teacher@test.talenthub.local`, `school@test.talenthub.local`, đều dùng `TALENTHUB_TEST_PASSWORD`.

## Kiểm tra nhanh

```bash
php bin/migrate.php validate
php bin/migrate.php status
```

Không dùng `Database/Talenthub.sql` để setup mới. File dump đó là snapshot legacy; luồng chuẩn là migration + `bin/setup-local.php`.