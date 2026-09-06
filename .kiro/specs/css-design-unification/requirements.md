# Requirements: Thống nhất CSS và Cải thiện Thiết kế toàn dự án

## Tổng quan

Project FTalentHub là một ứng dụng PHP thuần với 5 portal (Learner, School, Teacher, Enterprise, Admin) cùng các trang public (Landing, Auth, Role Selection). Hiện tại CSS được tổ chức khá tốt với `home.css` làm nguồn design token, nhưng giữa các portal còn nhiều **sự không nhất quán** về spacing, typography scale, component visual, và responsive behavior. Mục tiêu là **thống nhất và nâng cấp** toàn bộ CSS mà không thay đổi cấu trúc PHP/HTML hiện có.

---

## Requirements

### REQ-1: Mở rộng Design Token System trong `home.css`

**Vấn đề hiện tại:** `home.css` có token cơ bản (colors, radius, shadows, transitions) nhưng thiếu các token quan trọng: typography scale, spacing scale, z-index scale, breakpoints, và state colors (success, warning, danger, info).

**Yêu cầu:**
- MUST có typography scale chuẩn: `--text-xs` đến `--text-4xl` (font-size + line-height + letter-spacing)
- MUST có spacing scale: `--space-1` đến `--space-16` (4px base unit)
- MUST có z-index scale: `--z-dropdown`, `--z-sidebar`, `--z-modal`, `--z-toast`
- MUST có semantic state colors: `--color-success`, `--color-warning`, `--color-danger`, `--color-info` (cùng `-hover`, `-light` variants)
- MUST có focus ring token: `--focus-ring` dùng nhất quán qua `:focus-visible`
- SHOULD có token cho transition chuẩn: hiện có `--transition-fast` và `--transition-base` — cần thêm `--transition-slow`

---

### REQ-2: Tạo file `global.css` — Shared Components dùng chung nhiều portal

**Vấn đề hiện tại:** Nhiều component được viết lại hoàn toàn cho từng portal (button, badge, form input, card, table header, modal backdrop, empty state, skeleton loading). Kết quả là cùng một `btn-primary` nhưng padding, border-radius khác nhau giữa School và Enterprise.

**Yêu cầu:**
- MUST tạo `/assets/css/global.css` chứa shared utility components:
  - **Buttons:** `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.btn-danger` với variants size (sm, md, lg)
  - **Badges/Tags:** `.badge`, `.badge-success`, `.badge-warning`, `.badge-danger`, `.badge-info`, `.badge-neutral`
  - **Form elements:** `.form-input`, `.form-select`, `.form-label`, `.form-group`, `.form-helper`, `.form-error`
  - **Cards:** `.card`, `.card-header`, `.card-body`, `.card-footer`
  - **Table:** `.data-table` (responsive wrapper + header styling)
  - **Empty state:** `.empty-state` component
  - **Loading skeleton:** `.skeleton` animation
  - **Divider:** `.divider`
  - **Avatar:** `.avatar`, `.avatar-sm`, `.avatar-md`, `.avatar-lg`
- MUST được load sau `home.css` trong tất cả layout files
- Các portal CSS chỉ override nếu thực sự cần — không duplicate

---

### REQ-3: Thống nhất CSS của từng Portal

**Vấn đề hiện tại:** Mỗi portal dùng prefix khác nhau (`.learner-`, `.school-`, `.teacher-`, `.ent-`) nhưng nội dung bên trong lại có spacing/sizing khác nhau cho cùng một loại component.

**Yêu cầu theo portal:**

#### 3a. `learner.css` (8346 lines — file lớn nhất)
- MUST chuẩn hóa spacing: `padding` và `gap` dùng `--space-*` tokens thay vì giá trị hard-coded
- MUST chuẩn hóa typography: heading/body dùng `--text-*` tokens
- MUST bỏ các rule CSS trùng lặp (có nhiều selector define cùng property)
- SHOULD refactor các block CSS inline `style=""` được render từ PHP sang class CSS

#### 3b. `school.css` (1632 lines — file ngắn, thiếu nhiều component)
- MUST bổ sung missing component styles: stat cards, data tables, form sections
- MUST thêm responsive breakpoints cho tablet (768px) và mobile (480px)
- MUST chuẩn hóa header height: hiện dùng `60px` — cần thống nhất với learner (`68px`) hoặc chọn 1 giá trị

#### 3c. `teacher.css` (2715 lines)
- MUST xóa các utility class đã có trong `global.css` mới
- MUST chuẩn hóa sidebar width: hiện `260px` khác với learner (`274px`) — chọn 1 giá trị
- MUST thêm missing mobile responsive styles

#### 3d. `enterprise.css` (7136 lines)
- MUST gộp `enterprise-analytics.css` và `enterprise-sponsorships.css` vào file chính (hoặc tổ chức imports rõ ràng)
- MUST bỏ typography tokens `--ent-text-*` trùng lặp — dùng shared `--text-*` tokens thay thế
- MUST chuẩn hóa KPI card design nhất quán với school portal

#### 3e. `auth.css` + `role-selection.css`
- MUST chuẩn hóa dùng `global.css` button/form components
- MUST cải thiện mobile responsive cho login/register forms

---

### REQ-4: Cải thiện Visual Design (không thay đổi layout)

**Yêu cầu:**
- MUST cải thiện **sidebar** trên tất cả portal: active state rõ hơn, hover animation mượt, consistent icon alignment
- MUST cải thiện **header** trên tất cả portal: shadow nhẹ hơn, avatar/account area consistent
- MUST cải thiện **card components**: subtle hover elevation, consistent padding
- MUST cải thiện **button states**: hover/active/focus/disabled rõ hơn
- MUST cải thiện **form inputs**: focus state, error state, placeholder color nhất quán
- SHOULD cải thiện **table rows**: hover state, selected state
- SHOULD thêm **micro-animations** nhẹ: page load fade-in, sidebar item hover
- SHOULD cải thiện **empty states**: icon + message + CTA button consistent

---

### REQ-5: Typography thống nhất toàn dự án

**Vấn đề hiện tại:** Heading sizes hard-coded khác nhau giữa các portal. Enterprise dùng `--ent-text-page-title: 1.875rem`, school không có token, learner dùng sizes tự định nghĩa.

**Yêu cầu:**
- MUST định nghĩa type scale trong `home.css`:
  - Page title: `2rem / 700` (desktop), `1.625rem` (mobile)  
  - Section title: `1.25rem / 700`
  - Card title: `1rem / 600`
  - Body: `0.9375rem / 400`
  - Small/Meta: `0.8125rem / 400`
  - Label/Nav: `0.875rem / 500`
- MUST áp dụng đồng nhất cho tất cả portal

---

### REQ-6: Responsive Design chuẩn hóa

**Vấn đề hiện tại:** Breakpoints không nhất quán. Learner dùng `1024px`, `768px`, `640px`. School dùng `1024px`, `768px`. Enterprise dùng `1200px`, `1024px`, `768px`.

**Yêu cầu:**
- MUST chọn breakpoint system nhất quán: `xl: 1280px`, `lg: 1024px`, `md: 768px`, `sm: 480px`
- MUST sidebar trên tất cả portal collapse đúng cách ở `< 1024px`
- MUST tất cả data tables có responsive fallback (horizontal scroll hoặc card view)
- MUST form layouts stack vertical ở mobile

---

### REQ-7: Landing Page & Auth Pages

**Yêu cầu:**
- MUST cải thiện `home.css` landing page styles: hero section, feature cards, CTA sections
- MUST cải thiện login/register form visual: hiện đang dùng styles inline hoặc thiếu CSS
- MUST `role-selection.css` cải thiện role card hover states, selected state indicator

---

### REQ-8: Accessibility (a11y)

**Yêu cầu:**
- MUST tất cả interactive elements có `:focus-visible` outline rõ ràng dùng `--focus-ring` token
- MUST color contrast đạt WCAG AA (4.5:1 cho text, 3:1 cho UI components)
- MUST không dùng `color` là cách duy nhất truyền tải thông tin (badges cần có icon hoặc text)
- SHOULD `prefers-reduced-motion` media query cho các animations

---

## Phạm vi KHÔNG thay đổi

- Cấu trúc PHP files, routing, backend logic
- HTML class names hiện có (chỉ thêm, không xóa/đổi class name đang dùng)
- Chức năng JavaScript
- Database schema
