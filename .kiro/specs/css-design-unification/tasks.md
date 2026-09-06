# Tasks: Thống nhất CSS và Cải thiện Thiết kế toàn dự án

## Task 1: Mở rộng Design Token System trong `home.css`

**File:** `assets/css/home.css`

Mở rộng block `:root` của `home.css` để thêm:
- Typography scale tokens: `--text-xs` đến `--text-5xl`, `--leading-*`, `--font-*`
- Spacing scale tokens: `--space-1` đến `--space-16`
- Z-index scale tokens: `--z-dropdown`, `--z-sticky`, `--z-sidebar`, `--z-backdrop`, `--z-modal`, `--z-toast`
- State/semantic color tokens: `--color-success`, `--color-warning`, `--color-danger`, `--color-info`, `--color-neutral` (cùng `-hover` và `-light` variants)
- Layout constants: `--sidebar-width: 264px`, `--header-height: 64px`, `--content-max: 1400px`
- Focus ring token: `--focus-ring` → dùng trong `:focus-visible`
- Thêm `--transition-slow: 0.4s cubic-bezier(0.4, 0, 0.2, 1)`
- Cập nhật base `:focus-visible` dùng `--focus-ring` token
- Thêm `@media (prefers-reduced-motion: reduce)` block

---

## Task 2: Tạo `global.css` — Shared Component Library

**File:** `assets/css/global.css` (tạo mới)

Tạo file chứa shared UI components dùng chung tất cả portal:
1. **Buttons:** `.btn`, `.btn-sm`, `.btn-md`, `.btn-lg`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-danger` + loading state `.btn--loading`
2. **Badges:** `.badge`, `.badge-success`, `.badge-warning`, `.badge-danger`, `.badge-info`, `.badge-neutral`
3. **Form elements:** `.form-group`, `.form-label`, `.form-input`, `.form-select` (native), `.form-textarea`, `.form-helper`, `.form-error`, `.form-input--error`, `.required-mark`
4. **Cards:** `.card`, `.card--hoverable`, `.card-header`, `.card-body`, `.card-footer`
5. **Data Table:** `.table-wrapper` (responsive scroll), `.data-table` base styles
6. **Avatar:** `.avatar`, `.avatar-sm`, `.avatar-md`, `.avatar-lg`
7. **Empty State:** `.empty-state`, `.empty-state__icon`, `.empty-state__title`, `.empty-state__desc`
8. **Loading Skeleton:** `.skeleton`, `.skeleton-text`, `.skeleton-avatar` với keyframe animation
9. **Divider:** `.divider`, `.divider--vertical`
10. **Utility:** `.visually-hidden`, `.truncate`, `.text-overflow-2`
11. **Status Dot:** `.status-dot`, `.status-dot--online`, `.status-dot--away`, `.status-dot--offline`

---

## Task 3: Cập nhật Layout Files để load `global.css`

**Files:**
- `app/school/includes/layout.php` — thêm `global.css` giữa `home.css` và `school.css`
- `app/learner/*.php` (tất cả trang learner có `<head>`) — thêm `global.css`
- `app/teacher/*.php` (tất cả trang teacher) — thêm `global.css`
- `app/enterprise/*.php` + subfolders — thêm `global.css`
- `app/employer/*.php` — thêm `global.css`
- `app/admin/index.php` — thêm `global.css`
- `index.php`, `login.php`, `register*.php`, `role-selection.php` (root pages) — thêm `global.css`

**Lưu ý:** Load order phải là: `home.css` → `global.css` → `[portal].css` → `typeui-selects.css`

---

## Task 4: Refactor `learner.css`

**File:** `assets/css/learner.css`

- Thay thế hard-coded spacing values bằng `--space-*` tokens
- Thay thế hard-coded font-size/weight bằng `--text-*` và `--font-*` tokens
- Chuẩn hóa `--learner-sidebar-width` → dùng `var(--sidebar-width)`
- Chuẩn hóa `--learner-header-height` → dùng `var(--header-height)`
- Cải thiện sidebar:
  - Active item: thêm `::before` indicator bar 3px
  - Hover animation mượt hơn
  - Section headers (nav group labels) styling chuẩn
- Cải thiện header:
  - `backdrop-filter` + subtle border thay vì shadow
  - Account dropdown animation
- Cải thiện card components: hover elevation
- Xóa các button/badge/form styles trùng với `global.css`
- Thêm missing responsive breakpoints:
  - `@media (max-width: 1024px)` — sidebar collapse
  - `@media (max-width: 768px)` — layout adjustments
  - `@media (max-width: 480px)` — compact mobile

---

## Task 5: Mở rộng `school.css`

**File:** `assets/css/school.css`

- Chuẩn hóa sidebar width → `var(--sidebar-width)`
- Chuẩn hóa header height → `var(--header-height)`
- Cải thiện sidebar active state + indicator bar
- **Thêm missing component styles:**
  - KPI/Stat cards (`.school-stat-card`)
  - Data table styles (`.school-table`, `.school-table-wrapper`)
  - Form section styles (`.school-form-section`)
  - Page header (`.school-page-header`, `.school-page-title`, `.school-page-actions`)
  - Filter bar (`.school-filter-bar`)
  - Student card list item
  - Report card styles
- Thêm responsive breakpoints đầy đủ:
  - `@media (max-width: 1024px)` — sidebar collapse
  - `@media (max-width: 768px)` — single column
  - `@media (max-width: 480px)` — compact

---

## Task 6: Refactor `teacher.css`

**File:** `assets/css/teacher.css`

- Chuẩn hóa sidebar width → `var(--sidebar-width)` và `--z-sidebar`
- Chuẩn hóa header → `var(--header-height)`
- Cải thiện sidebar: active indicator bar, hover states, section titles
- Cải thiện header: backdrop-filter, account area
- Xóa button/badge/form styles đã có trong `global.css`
- Thêm QR code session component styles (`.teacher-qr-session`)
- Thêm grade/assessment card styles
- Responsive breakpoints đầy đủ

---

## Task 7: Refactor `enterprise.css`

**File:** `assets/css/enterprise.css`

- Thay thế `--ent-text-*` typography tokens bằng shared `--text-*` tokens
- Chuẩn hóa sidebar → `var(--sidebar-width)`, `--z-sidebar`
- Chuẩn hóa header → `var(--header-height)`
- Xóa button/badge styles trùng với `global.css`
- Cải thiện KPI cards: thống nhất với school portal design
- Cải thiện talent card / candidate card designs
- **Gộp `enterprise-analytics.css` content** vào cuối `enterprise.css` (section rõ ràng)
- **Gộp `enterprise-sponsorships.css` content** vào cuối `enterprise.css`
- Sau khi gộp: cập nhật `app/enterprise/*.php` bỏ load 2 file con đó
- Responsive breakpoints chuẩn hóa

---

## Task 8: Viết lại `auth.css`

**File:** `assets/css/auth.css`

File hiện tại là minified 1 dòng — viết lại readable với improvements:
- Login form: centered card, max-width 420px, shadow-lg
- Register form: max-width 520px, multi-step indicator (nếu có)
- Cải thiện input focus states
- Show/hide password button
- Social login buttons (nếu có)
- Error message styling
- Responsive: full-width trên mobile

---

## Task 9: Cải thiện `role-selection.css`

**File:** `assets/css/role-selection.css`

- Cải thiện role card: border → highlight khi hover, selected state rõ hơn
- Thêm selected state: `border: 2px solid var(--primary)`, check indicator
- Animation: card hover lift + scale nhẹ
- Responsive improvements cho 2-col → 1-col

---

## Task 10: Viết lại `admin.css`

**File:** `assets/css/admin.css`

File hiện tại là minified, rất ngắn — viết lại đầy đủ:
- Admin layout: sidebar + main content
- Dashboard stats grid
- User management table styles
- System health indicators
- Dùng `--color-danger` cho destructive actions

---

## Task 11: Cập nhật `student.css`

**File:** `assets/css/student.css`

Hiện tại chỉ import `learner.css` và thêm `.learner-progress`. Sau khi `learner.css` được refactor, kiểm tra và cập nhật nếu cần.

---

## Thứ tự thực hiện

```
Task 1 (home.css tokens) 
  → Task 2 (global.css)
  → Task 3 (update layouts để load global.css)
  → Tasks 4–10 (song song theo portal)
```

Task 1, 2, 3 phải hoàn thành trước vì các task sau phụ thuộc vào tokens và global components.
