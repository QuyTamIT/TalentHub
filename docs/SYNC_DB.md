# Hướng dẫn sync database TalentHub cho team

> Đối tượng: bất kỳ thành viên nào trong team (Laragon + MariaDB 10.4+) muốn có local DB giống hệt local của người viết plan này. Thời gian thực hiện: ~5 phút.

## Mục tiêu

Sau khi chạy xong hướng dẫn, local DB phải có:

- 7 migrations đã apply (`001` → `007`).
- 4 roles: `student`, `teacher`, `school`, `business`.
- 84 permissions, **99 role_permissions mappings**.
- (tuỳ chọn) 1 school demo `THPT Nguyễn Trãi` + 1 user `school.admin@talenthub.vn` + 4 classes + 4 teachers + 8 students + memberships tương ứng.

> File dump cũ `Database/Talenthub_DB.sql` **tuyệt đối không import** — schema đã lỗi thời (xung đột `users.roles varchar(50)` vs schema mới dùng `roleId`). Nguồn schema chính thức là 7 file trong [`Database/migrations/`](../Database/migrations/).

---

## Bước 1 — Chuẩn bị

```powershell
cd c:\laragon\www\FTalentHUB
git pull
```

Đảm bảo Laragon đang chạy (icon **Apache** + **MySQL** xanh). Nếu chưa, mở Laragon → Start All.

---

## Bước 2 — Cấu hình env cho Apache (một lần duy nhất)

Mở `C:\laragon\etc\apache2\httpd.conf`, thêm khối sau vào cuối file:

```apache
SetEnv APP_ENV local
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_DATABASE talenthub_local
SetEnv DB_USERNAME root
SetEnv DB_PASSWORD
SetEnv TALENTHUB_TEST_PASSWORD TestPassword_2026
```

Restart Apache từ Laragon panel. Sau đó không cần làm lại bước này ở các lần sync sau.

**Vì sao cần bước này?** Code app đọc env qua [`src/Config/Environment.php`](../src/Config/Environment.php) và **không tự load `.env`** — nó phụ thuộc `$_ENV` / `$_SERVER` / `getenv()`. SetEnv trong Apache là cách chuẩn để cả CLI lẫn web cùng nhìn thấy biến môi trường.

> **Quy ước**: từ đây trở đi mọi lệnh `php bin/*` mặc định đọc được env. Nếu chạy CLI ở shell không phải Laragon terminal, prefix thêm `$env:APP_ENV='local'` (PowerShell) hoặc `export APP_ENV=local` (bash).

---

## Bước 3 — Tạo 2 database (idempotent)

```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS talenthub_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS talenthub_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "SHOW DATABASES LIKE 'talenthub_%';"
```

Kỳ vọng: 2 dòng `talenthub_local` + `talenthub_test`.

- `talenthub_local` — DB chính cho app local & web.
- `talenthub_test` — DB smoke test (chỉ migrate khi cần test, không dùng cho dev).

---

## Bước 4 — Chạy migration

```powershell
cd c:\laragon\www\FTalentHUB\TalentHub
php bin/migrate.php status
```

Trước khi migrate: 7 dòng `pending`. Sau đó chạy:

```powershell
php bin/migrate.php migrate
php bin/migrate.php status
```

Kỳ vọng: 7 dòng `applied`, **không có `Fatal`/`Warning`**. Thứ tự:

1. `20260814000100_create_identity_catalogs` — roles, permissions, role_permissions, id_counters, settings
2. `20260814000200_create_users_and_role_permissions` — users, auth_credentials, refresh tokens
3. `20260814000300_create_organizations_and_classes` — schools, school_members, enterprises, classes
4. `20260814000400_create_profiles_and_memberships` — student_profiles, teacher_profiles, activity_*, report_*
5. `20260814000500_create_audit_logs`
6. `20260814000600_extend_teacher_profiles` — thêm `phone`, `specialization`, `bio`
7. `20260814000700_extend_schools` — thêm `logoUrl`, `address`, `phone`, `email`, `website`, `level`, `studentCount`, `teacherCount`, `academicYear`

Migrations được chạy bởi [`bin/migrate.php`](../bin/migrate.php), qua [`src/Database/Migration/MigrationRunner.php`](../src/Database/Migration/MigrationRunner.php). Mỗi migration idempotent — chạy lại không phá dữ liệu.

---

## Bước 5 — Seed hệ thống (RBAC)

```powershell
php bin/seed.php
```

Kỳ vọng output:

```
[OK] system seed
```

Verify nhanh bằng mysql:

```sql
USE talenthub_local;
SELECT COUNT(*) AS roles    FROM roles;            -- 4
SELECT COUNT(*) AS perms    FROM permissions;      -- 84
SELECT COUNT(*) AS mappings FROM role_permissions; -- 99
```

> Con số **99 mappings** = (6 common × 4 roles) + (28 student + 3 teacher + 23 school + 21 business) = 24 + 75 = 99. Tính từ [`Database/seeds/System/RolePermissionSeeder.php`](../Database/seeds/System/RolePermissionSeeder.php) → `expectedCounts()`. Đây là số chuẩn của phiên bản hiện tại trong repo.

Seeder là idempotent — chạy nhiều lần không sinh duplicate nhờ `INSERT IGNORE` trên `role_permissions` và `INSERT ... ON DUPLICATE KEY UPDATE` cho roles/permissions.

---

## Bước 6 — (Tuỳ chọn) Seed demo school

Chỉ cần thiết nếu bạn muốn test dashboard school:

```powershell
php bin/seed.php --demo
```

Kỳ vọng:

```
[OK] system seed
[OK] demo seed
```

Tạo ra:

- 1 school: `THPT Nguyễn Trãi`, address + phone + email + level + studentCount/teacherCount
- 1 user: `school.admin@talenthub.vn` (password = giá trị `TALENTHUB_TEST_PASSWORD`, mặc định `TestPassword_2026`), role `school`, fullName `Ban Giám hiệu THPT Nguyễn Trãi`
- 1 row trong `school_members` (role='admin')
- 4 classes: `10A1`, `10A2`, `11A1`, `12A1`
- 4 teachers (mỗi người 1 lớp GVCN) + 4 `teacher_profiles`
- 8 students (chia đều 4 lớp) + 8 `student_profiles`

**Guard**: chỉ chạy khi `APP_ENV` ∈ {`local`, `test`, `development`, `testing`}. Sẽ báo `[FAIL]` nếu đang ở môi trường khác.

Nếu cần seed thêm user test cho RBAC (dùng cho smoke test auth):

```powershell
php bin/seed.php --testing
```

---

## Bước 7 — Verify cuối cùng (checklist thống nhất)

```sql
USE talenthub_local;

-- Số bảng (kỳ vọng 20+ bảng)
SHOW TABLES;

-- Bảng schools đã được mở rộng (migration 007)
DESCRIBE schools;
-- Phải có: logoUrl, address, phone, email, website, level,
--          studentCount, teacherCount, academicYear

-- Bảng teacher_profiles đã được mở rộng (migration 006)
DESCRIBE teacher_profiles;
-- Phải có: phone, specialization, bio

-- Demo data
SELECT id, name, level, studentCount, teacherCount, academicYear FROM schools;
SELECT email FROM users
  WHERE roleId = (SELECT id FROM roles WHERE code='school');
-- Kỳ vọng: school.admin@talenthub.vn (nếu đã chạy --demo)
```

Smoke test (nếu repo có sẵn):

```powershell
php bin/smoke-database.php
php bin/smoke-school-api.php        # chỉ có nếu đã chạy plan School module
php bin/smoke-school-dashboard.php  # chỉ có nếu đã chạy plan School module
```

Tất cả phải `[OK]`, không `[FAIL]`.

---

## Bước 8 — Đối chiếu 3 số liệu giữa các thành viên team

Sau khi mọi người chạy xong Bước 1-7, so sánh 3 con số sau. Nếu **trùng nhau** → local DB đã thống nhất.

| Metric | Cách kiểm tra | Expected | Nếu lệch → xử lý |
|---|---|---|---|
| Số migrations đã apply | `php bin/migrate.php status` | **7/7 applied** | `git pull` rồi chạy `php bin/migrate.php migrate` |
| Số mappings RBAC | `SELECT COUNT(*) FROM role_permissions;` | **99** | Chạy lại `php bin/seed.php` (idempotent) |
| Số schools | `SELECT COUNT(*) FROM schools;` | **0** (chưa `--demo`) hoặc **1** (đã `--demo`) | `php bin/seed.php --demo` |

---

## Lưu ý rủi ro

1. **MariaDB vs MySQL**: tất cả migration dùng cú pháp tương thích **MariaDB 10.4+** và **MySQL 8.0+** (cụ thể là `ADD COLUMN ... AFTER` + check constraint cho JSON). Nếu team đang dùng MySQL 5.7 sẽ fail.
2. **Không import `Database/Talenthub_DB.sql`** — file này đã được commit xoá, nhưng nếu ai đó lỡ import trước đó cần `DROP DATABASE talenthub_local; CREATE DATABASE talenthub_local ...` rồi chạy lại từ Bước 4.
3. **`.env` đã được `.gitignore`** (`TalentHub/.gitignore` dòng 1: `.env`). KHÔNG commit file `.env` thật, chỉ commit `.env.example` khi thêm biến mới.
4. **Test DB `talenthub_test`**: Bước 3 chỉ tạo, **chưa migrate**. Khi cần smoke test: `$env:APP_ENV='test'; php bin/migrate.php migrate`.
5. **Quyền ghi DB**: user `root` Laragon mặc định đủ quyền DDL. Nếu team muốn dùng user riêng, cần `GRANT ALL ON talenthub_local.* TO 'user'@'localhost';` rồi cập nhật `DB_USERNAME`/`DB_PASSWORD`.
6. **Lock trên seed**: `bin/seed.php` dùng `GET_LOCK('talenthub:system_seeds')` để tránh 2 tiến trình seed cùng lúc. Nếu migration chạy song song trong 2 cửa sổ terminal, cái thứ 2 sẽ đợi 30s rồi báo `[FAIL]`.
7. **Session cho web**: Sau Bước 2, login web ở `http://talenthub.test/login.php` mới dùng được. Nếu không SetEnv, login sẽ fail với `DB_DATABASE` rỗng.

---

## Tóm tắt nhanh (TL;DR)

```powershell
# 1 lần duy nhất: thêm SetEnv vào httpd.conf + restart Apache

# Mỗi lần sync DB mới:
cd c:\laragon\www\FTalentHUB
git pull

mysql -u root -e "CREATE DATABASE IF NOT EXISTS talenthub_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS talenthub_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

cd TalentHub
php bin/migrate.php migrate
php bin/seed.php
php bin/seed.php --demo           # tuỳ chọn

# Verify
mysql -u root talenthub_local -e "SELECT COUNT(*) AS m FROM role_permissions;"
# Kỳ vọng: m = 99
```

---

## File / đường dẫn liên quan

- [`TalentHub/.env.example`](../.env.example) — mẫu biến môi trường chuẩn
- [`TalentHub/config/database.php`](../config/database.php) — đọc env để build config
- [`TalentHub/src/Database/Connection.php`](../src/Database/Connection.php) — PDO bootstrap
- [`TalentHub/src/Config/Environment.php`](../src/Config/Environment.php) — đọc env (KHÔNG auto-load .env)
- [`TalentHub/bin/migrate.php`](../bin/migrate.php) — CLI migrate
- [`TalentHub/bin/seed.php`](../bin/seed.php) — CLI seed (có flag `--testing` / `--demo`)
- [`TalentHub/bin/smoke-database.php`](../bin/smoke-database.php) — smoke test DB
- [`TalentHub/bin/smoke-school-api.php`](../bin/smoke-school-api.php) — smoke test API school
- [`TalentHub/bin/smoke-school-dashboard.php`](../bin/smoke-school-dashboard.php) — smoke test dashboard school
- [`TalentHub/Database/migrations/`](../Database/migrations/) — **7 file migration chính thức**
- [`TalentHub/Database/seeds/System/RolePermissionSeeder.php`](../Database/seeds/System/RolePermissionSeeder.php) — RBAC seed (4 roles, 84 perms, 99 mappings)
- [`TalentHub/Database/seeds/Demo/SchoolDemoSeeder.php`](../Database/seeds/Demo/SchoolDemoSeeder.php) — demo school seed
- `C:\laragon\etc\apache2\httpd.conf` — nơi khai báo SetEnv (ngoài repo)