# Tinh Chỉnh Bố Cục & Khoảng Cách Thẻ Chứng Chỉ & Huy Hiệu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khắc phục triệt để tình trạng thẻ và text chứng chỉ / huy hiệu bị dính sát lề trên trang profile, badges và dashboard; nâng cấp khoảng đệm (padding), nhịp thở (gap), tỷ lệ huy hiệu và các chi tiết thẩm mỹ theo chuẩn Card Dashboard.

**Architecture:** Tinh chỉnh trực tiếp file `assets/css/learner.css` tập trung vào khối `.learner-school-credential-section`, `.learner-school-credential-heading`, `.learner-school-credential-grid`, và hai mẫu thẻ `.learner-credential-card--certificate`, `.learner-credential-card--badge`. Đảm bảo responsive mượt mà từ desktop đến mobile và bảo toàn tương thích với các trang liên quan.

**Tech Stack:** CSS3 (CSS Grid, Flexbox, clamp, color-mix, CSS custom properties), PHP render testing.

## Global Constraints
- File chỉnh sửa chính: `assets/css/learner.css`.
- Giữ nguyên các class semantic trong HTML (`profile.php`, `badges.php`, `index.php`, `school-credential-grid.php`).
- Đảm bảo responsive không bị vỡ layout ở mọi kích thước màn hình (Desktop 1440px/1200px, Tablet 768px, Mobile 375px).
- Toàn bộ test hiện có chạy thành công.

---

### Task 1: Chuẩn hóa khoảng đệm (Padding) & Viền khung ngoài `.learner-school-credential-section`

**Files:**
- Modify: `assets/css/learner.css:5599-5655`
- Test: `tests/learner_accessibility_render_test.php`

**Interfaces:**
- Consumes: CSS custom properties (`--surface`, `--border`, `--radius-md`, `--shadow-sm`)
- Produces: Class `.learner-app .learner-school-credential-section` với padding đầy đủ và responsive

- [ ] **Step 1: Viết test hoặc kiểm tra cú pháp CSS hiện tại**

Run: `git diff assets/css/learner.css`

- [ ] **Step 2: Cập nhật padding và layout cho `.learner-school-credential-section` trong `assets/css/learner.css`**

Thêm `padding: 28px 32px;` trên desktop, bo góc `border-radius: var(--radius-md, 16px);`, nền `--surface`, bóng đổ nhẹ. Thêm media query cho tablet (`padding: 24px 20px;`) và mobile (`padding: 20px 16px;`).

- [ ] **Step 3: Cập nhật Header `.learner-school-credential-heading`**

Cải tiến `.learner-school-credential-heading__eyebrow` thành dạng badge/pill với padding `4px 12px`, border-radius `999px`, màu nền tinh tế `color-mix(in srgb, var(--primary) 9%, transparent)`.
Cân chỉnh `h2` (`margin: 8px 0 6px`), `p` (`line-height: 1.55`).
Nâng cấp link `.learner-school-credential-heading > a` thành dạng subtle pill button với padding `7px 16px`, bo góc `999px`, nền nhẹ và hiệu ứng hover.

- [ ] **Step 4: Chạy test kiểm tra không ảnh hưởng hệ thống**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_accessibility_render_test.php`
Expected: OK

- [ ] **Step 5: Commit**

```bash
git add assets/css/learner.css
git commit -m "style(learner): add generous padding and pill header to school credential section"
```

---

### Task 2: Tối ưu khoảng đệm nội bộ và bố cục của thẻ Chứng chỉ Diploma (`.learner-credential-card--certificate`)

**Files:**
- Modify: `assets/css/learner.css:11770-11895`
- Test: `tests/learner_accessibility_render_test.php`

**Interfaces:**
- Consumes: Markup của `app/learner/includes/school-credential-grid.php`
- Produces: Giao diện thẻ Diploma cân đối, thoáng đãng, sang trọng

- [ ] **Step 1: Tinh chỉnh khoảng đệm và kích thước thẻ Diploma**

Tăng padding ngoài của thẻ `.learner-credential-card--certificate` lên `10px`.
Khung bằng khen bên trong (`.learner-credential-card__diploma-frame`):
- Tăng padding trong lên `20px 22px 18px` (desktop) và `16px 16px 14px` (mobile).
- Căn chỉnh khoảng cách:
  * Crest (nón cử nhân và vòng nguyệt quế) căn giữa với `margin: 0 auto 8px`.
  * Nhãn trạng thái `learner-credential-card__diploma-state` có khoảng cách hợp lý.
  * Tên chứng chỉ `h3` căn giữa, `margin: 12px 0 8px`, `line-height: 1.35`, font-size `1.05rem`.
  * Đường kẻ phân cách `learner-credential-card__diploma-rule` và tên trường `learner-credential-card__diploma-issuer`.
  * Đáy thẻ: Con dấu "Đã xác minh" hoặc "Cấp 1 • 100% hoàn thành" được định vị vững chãi, khoảng cách thanh lịch.

- [ ] **Step 2: Cập nhật Lưới hiển thị `.learner-school-credential-grid--certificates`**

Đảm bảo khoảng cách `gap: 20px;`, hiển thị 3 cột trên màn hình rộng, 2 cột ở tablet (<=1100px), 1 cột ở mobile.

- [ ] **Step 3: Chạy test xác minh**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_accessibility_render_test.php`
Expected: OK

- [ ] **Step 4: Commit**

```bash
git add assets/css/learner.css
git commit -m "style(learner): refine diploma certificate card padding, crest and typography"
```

---

### Task 3: Tối ưu khoảng đệm nội bộ và bố cục của thẻ Huy hiệu Medal (`.learner-credential-card--badge`)

**Files:**
- Modify: `assets/css/learner.css:11895-12025`
- Test: `tests/learner_accessibility_render_test.php`

**Interfaces:**
- Consumes: Markup của `app/learner/includes/school-credential-grid.php`
- Produces: Thẻ huy hiệu cân xứng, không bị ép chữ, bố cục đẹp mắt

- [ ] **Step 1: Tinh chỉnh padding và tỷ lệ thẻ Huy hiệu**

Padding bên trong thẻ `.learner-credential-card--badge` tăng lên `22px 18px 20px`.
Cân chỉnh vòng tiến độ và huy hiệu tròn: `margin: 8px auto 16px`.
Nhãn trạng thái (`.learner-credential-card__badge-state`): khoảng cách `margin-top: 4px`.
Tên huy hiệu (`h3`): padding an toàn hai bên, `margin-top: 8px`, `font-size: 0.95rem`, `line-height: 1.4`.
Phần thông tin tiêu chí / con dấu đáy thẻ: `margin-top: auto`, căn giữa thoáng đãng.

- [ ] **Step 2: Cập nhật Lưới hiển thị `.learner-school-credential-grid--badges`**

`grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));` với `gap: 20px;`. Căn chỉnh đều đặn trên toàn bộ chiều rộng khung thẻ.

- [ ] **Step 3: Chạy test xác minh toàn diện**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_accessibility_render_test.php`
Expected: OK

- [ ] **Step 4: Commit**

```bash
git add assets/css/learner.css
git commit -m "style(learner): enhance school medal badge card spacing and grid layout"
```

---

### Task 4: Kiểm tra hiển thị thực tế & Xác thực giao diện (Visual & Render Verification)

**Files:**
- Inspect: `app/learner/profile.php`, `app/learner/badges.php`, `app/learner/index.php`
- Test: Chạy server / render PHP và kiểm tra HTML / CSS output

- [ ] **Step 1: Chạy render kiểm tra các trang PHP sinh mã HTML hoàn chỉnh**
- [ ] **Step 2: Đảm bảo không còn bất kỳ thành phần nào dính sát lề trên cả 3 trang**
- [ ] **Step 3: Tổng kết tài liệu walkthrough**
