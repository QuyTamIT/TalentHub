# Debug Session Report — 2026-08-16

## Tóm tắt
Debug session sửa 3 lỗi liên quan đến đường dẫn (`app_href()`) trên các
trang `enterprise`, `learner`, `teacher/students`, `teacher/assessments`
khi app được mount dưới subdirectory `/FTalentHUB/TalentHub`.

Commit gốc (chưa có fix): `9716550`
Commit cuối (sau fix):    `32d254f` (HEAD)

Tất cả fix đều đã được verify bằng runtime evidence qua `curl`:
HTTP 302 redirect chain trỏ đúng file, HTTP 200 ở đích cuối.

---

## Bug 1 — `app/enterprise/index.php` & `app/learner/index.php` 500

### Triệu chứng
Fatal: `Call to undefined function app_href()`.

### Nguyên nhân
Commit `9716550` đổi tất cả link tuyệt đối sang `app_href()`. Một số
trang `enterprise` / `learner` không require `bin/bootstrap.php` trước
khi include sidebar → sidebar chạy code dùng `app_href()` → fatal.

### Hệ thống liên quan
- `app/enterprise/includes/sidebar.php`
- `app/learner/includes/sidebar.php`

### Fix — Commit `63ae833`
Sidebar tự bootstrap `app_href()` nếu caller chưa load. Pattern:

```php
if (!function_exists('app_href') && is_file(__DIR__ . '/../../../bin/bootstrap.php')) {
    require_once __DIR__ . '/../../../bin/bootstrap.php';
}
```

Hai file khác (`app/enterprise/index.php`, `app/enterprise/talents/detail.php`)
chỉ whitespace — không có thay đổi logic; xuất hiện trong commit vì
một số tooling tự normalize trailing whitespace.

### Verify
- `curl /FTalentHUB/TalentHub/app/enterprise/index.php` → HTTP 200
- `curl /FTalentHUB/TalentHub/app/learner/index.php`   → HTTP 200

---

## Bug 2 — `teacher/students` & `teacher/assessments` 404 về `/app/login.php`

### Triệu chứng
```
http://localhost/FTalentHUB/TalentHub/app/login.php?next=... → 404
```

### Nguyên nhân
Sau commit `9716550`, hai trang teacher dùng relative path
`../../login.php`. Nhưng:

| File | Depth | Đường dẫn đúng | Đường dẫn sai |
|---|---|---|---|
| `app/teacher/students/index.php`    | 3 | `../../../login.php` | `../../login.php` |
| `app/teacher/assessments/index.php` | 3 | `../../../login.php` | `../../login.php` |

Browser relative-resolve từ `/FTalentHUB/TalentHub/app/teacher/students/index.php`
+ `../../login.php` → `/FTalentHUB/TalentHub/app/login.php` (404 vì login
sống ở root, không dưới `app/`).

Cùng lỗi cho `../../role-selection.php`.

### Hệ thống liên quan
- `app/teacher/students/index.php` — dòng 73, 77
- `app/teacher/assessments/index.php` — dòng 22, 26

### Fix — Commit `32d254f`
Thay hardcoded `../../login.php` bằng `app_href('/login.php')`. Helper
tự tính depth đúng (3 `..` cho path 3-deep), tránh lặp lại off-by-one.

```diff
- header('Location: ../../login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
+ header('Location: ' . app_href('/login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI']));
```

### Verify
- `curl /FTalentHUB/TalentHub/app/teacher/students/index.php` →
  302 → `../../../login.php?next=...` → 200.
- `curl /FTalentHUB/TalentHub/app/teacher/assessments/index.php` →
  302 → `../../../login.php?next=...` → 200.

---

## Tổng commit trong session

```
32d254f fix(redirect): use app_href() in teacher/students and teacher/assessments
63ae833 fix(sidebar): self-bootstrap app_href() in enterprise and learner sidebars
9716550 fix(nav): resolve absolute-path 404s when app is mounted under a subdirectory  (origin)
```

Tổng diffstat: **6 files changed, 16 insertions(+), 7 deletions(-)**.

---

## Repro / Verify chung

Yêu cầu: PHP server đang chạy tại `http://localhost/FTalentHUB/TalentHub/`,
Laragon default.

```bash
# Bug 1
curl -s -o /dev/null -w "%{http_code}\n" \
  http://localhost/FTalentHUB/TalentHub/app/enterprise/index.php
# expect: 200

curl -s -o /dev/null -w "%{http_code}\n" \
  http://localhost/FTalentHUB/TalentHub/app/learner/index.php
# expect: 200

# Bug 2
curl -s -L -o /dev/null -w "final %{http_code} %{url_effective}\n" \
  http://localhost/FTalentHUB/TalentHub/app/teacher/students/index.php
# expect: final 200 http://localhost/FTalentHUB/TalentHub/login.php?next=...

curl -s -L -o /dev/null -w "final %{http_code} %{url_effective}\n" \
  http://localhost/FTalentHUB/TalentHub/app/teacher/assessments/index.php
# expect: final 200 http://localhost/FTalentHUB/TalentHub/login.php?next=...
```

Đã chạy và xác nhận pass.