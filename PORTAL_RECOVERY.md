# TalentHub – Hướng dẫn xử lý lỗi "missing profile" khi vào khu vực

Tài liệu này dành cho thành viên mới onboard hoặc gặp lỗi khi truy cập các khu vực theo vai trò (Learner / Teacher / School / Enterprise).

---

## 1. Bối cảnh

Mỗi khu vực trên TalentHub yêu cầu tài khoản đăng nhập phải liên kết với **hồ sơ chuyên biệt** trong database:

| Khu vực | URL | Cần có row trong bảng |
|---|---|---|
| Học sinh / Sinh viên | `/app/learner/index.php` | `student_profiles` |
| Giáo viên / HLV | `/app/teacher/index.php` | `teacher_profiles` |
| Nhà trường | `/app/school/index.php` | `school_members` (gắn với `schools`) |
| Doanh nghiệp | `/app/enterprise/index.php` | `enterprise_members` (gắn với `enterprises`) |

Khi một user đăng nhập thành công nhưng **không có hồ sơ tương ứng**, hệ thống sẽ redirect về `/role-selection.php` cùng query `error=...` để thông báo.

---

## 2. Triệu chứng nhận biết

Bạn sẽ thấy URL dạng:

```
http://localhost:8080/role-selection.php?error=student_profile_missing&hint=...
http://localhost:8080/role-selection.php?error=school_missing&hint=...
http://localhost:8080/role-selection.php?error=enterprise_missing&hint=...
```

Trên trang `role-selection.php` có banner cảnh báo vàng kèm hướng dẫn cụ thể.

---

## 3. Tài khoản test mặc định

Sau khi chạy `php bin/seed.php --testing`, hệ thống tạo sẵn 4 tài khoản (password lấy từ `.env`):

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Học sinh | `student@test.talenthub.local` | `testpassword123` |
| Giáo viên | `teacher@test.talenthub.local` | `testpassword123` |
| Nhà trường | `school@test.talenthub.local` | `testpassword123` |
| Doanh nghiệp | `business@test.talenthub.local` | `testpassword123` |

Mật khẩu được định nghĩa trong `.env`:

```
TALENTHUB_TEST_PASSWORD=testpassword123   # 4 tài khoản role trên
TALENTHUB_ADMIN_PASSWORD=adminpassword123 # admin seed
```

> **Lưu ý:** Với môi trường `local` hoặc `test` mới có thể chạy được `--testing`. Production không cho phép chạy seed này.

---

## 4. Các bước xử lý khi gặp lỗi

### Bước 1: Chẩn đoán

Chạy script kiểm tra (script dùng PDO, không cần mysql client):

```bash
cd /Users/khangnguyenminh/Desktop/TalentHub
php bin/check-student.php
```

Kết quả cho biết chính xác thiếu gì:

- ❌ User không tồn tại → chạy `php bin/seed.php --testing`
- ❌ User không có role `student` → kiểm tra seed roles
- ❌ Thiếu `student_profiles` → chạy `php bin/fix-student.php`
- ❌ Thiếu class/school → cũng được fix bởi `fix-student.php`

### Bước 2: Fix nhanh cho tài khoản student test

```bash
php bin/fix-student.php
```

Script sẽ:

1. Đảm bảo `schools` + `classes` tồn tại
2. Insert (hoặc update) `student_profiles` cho `student@test.talenthub.local`

### Bước 3: Verify

```bash
php bin/check-student.php
# Phải in ra: 🎉 All checks PASSED.
```

Đăng nhập lại tại `http://localhost:8080/login.php` với email + password ở mục 3.

---

## 5. Nếu vẫn chưa vào được

### 5.1 Kiểm tra cache session

Session lưu thông tin user. Sau khi fix DB, **phải đăng xuất và đăng nhập lại** để session được refresh.

### 5.2 Kiểm tra file `.env`

Đảm bảo các biến sau tồn tại và đúng:

```
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=talenthub
DB_USERNAME=talenthub_app
DB_PASSWORD=talenthub_pass_2024
TALENTHUB_TEST_PASSWORD=testpassword123
```

### 5.3 Chạy lại toàn bộ seed (mạnh nhất)

Nếu cần reset dữ liệu về trạng thái sạch:

```bash
php bin/seed.php --testing
```

Script này dùng `INSERT IGNORE`, không phá dữ liệu đang có. Chạy được nhiều lần an toàn.

### 5.4 Dùng `gh` CLI (nếu có GitHub CLI)

Để xem các issue / PR liên quan:

```bash
gh issue list --search "student_profile_missing"
```

---

## 6. Các script hỗ trợ

| Script | Mục đích |
|---|---|
| `bin/check-student.php` | Diagnostic student test account |
| `bin/fix-student.php` | Bootstrap `student_profiles` + schools/classes |
| `bin/check-student.sql` | Phiên bản SQL thuần (dùng khi không có PHP CLI) |
| `bin/fix-student.sql` | Phiên bản SQL thuần |
| `bin/seed.php --testing` | Tạo đầy đủ 4 tài khoản test + hồ sơ |
| `bin/seed.php --demo` | Thêm dữ liệu demo trường THPT Nguyễn Du |
| `bin/seed.php --demo-ai` | Full demo dataset + AI plan |

---

## 7. Liên hệ

Nếu sau khi làm theo hướng dẫn mà vẫn không vào được khu vực của mình, ping trong nhóm và đính kèm:

1. Output của `php bin/check-student.php`
2. URL đang gặp lỗi (đầy đủ kèm query string)
3. Email đang thử đăng nhập
4. Branch + commit hash hiện tại (`git log -1 --oneline`)

---

*Cập nhật lần cuối: tháng 8/2026 – bởi nhóm phát triển TalentHub.*