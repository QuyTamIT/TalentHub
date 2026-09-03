# Design: Thống nhất CSS và Cải thiện Thiết kế toàn dự án

## Kiến trúc CSS sau khi hoàn thành

```
assets/css/
├── home.css                    ← [MỞ RỘNG] Design Tokens + Reset + Base + Landing Page
├── global.css                  ← [TẠO MỚI] Shared components dùng chung mọi portal
├── auth.css                    ← [VIẾT LẠI] Login, Register, Accept Invitation
├── role-selection.css          ← [CẢI THIỆN] Role selection page
├── learner.css                 ← [REFACTOR] Learner portal
├── school.css                  ← [MỞ RỘNG] School portal
├── teacher.css                 ← [REFACTOR] Teacher portal
├── enterprise.css              ← [REFACTOR] Enterprise portal (gộp analytics + sponsorships)
├── enterprise-analytics.css    ← [GIỮ NGUYÊN hoặc import vào enterprise.css]
├── enterprise-sponsorships.css ← [GIỮ NGUYÊN hoặc import vào enterprise.css]
├── admin.css                   ← [VIẾT LẠI] Admin portal
├── student.css                 ← [CẬP NHẬT] import learner.css + bổ sung
└── typeui-selects.css          ← [GIỮ NGUYÊN] Shared select component
```

**Load order trong mỗi layout:**
```html
<link rel="stylesheet" href="/assets/css/home.css">    <!-- 1. Tokens + Reset -->
<link rel="stylesheet" href="/assets/css/global.css">  <!-- 2. Shared components -->
<link rel="stylesheet" href="/assets/css/[portal].css"> <!-- 3. Portal-specific -->
<link rel="stylesheet" href="/assets/css/typeui-selects.css"> <!-- 4. Select component -->
```

---

## Design Decisions

### 1. Màu sắc & Brand

Giữ nguyên brand colors hiện tại — đây là bản thống nhất, không rebrand:

| Token | Value | Dùng cho |
|---|---|---|
| `--primary` | `#F97316` | CTAs, active states, brand accent |
| `--primary-hover` | `#EA580C` | Button hover |
| `--primary-light` | `#FFF7ED` | Active nav background, highlight |
| `--secondary` | `#2563EB` | Info, links, secondary actions |
| `--secondary-light` | `#EFF6FF` | |
| `--accent` | `#16A34A` | Success, positive metrics |
| `--accent-light` | `#F0FDF4` | |

**Thêm mới — State colors:**
| Token | Value |
|---|---|
| `--color-success` | `#16A34A` |
| `--color-success-light` | `#F0FDF4` |
| `--color-warning` | `#D97706` |
| `--color-warning-light` | `#FFFBEB` |
| `--color-danger` | `#DC2626` |
| `--color-danger-light` | `#FEF2F2` |
| `--color-info` | `#2563EB` |
| `--color-info-light` | `#EFF6FF` |
| `--color-neutral` | `#64748B` |
| `--color-neutral-light` | `#F1F5F9` |

---

### 2. Typography Scale

Font: **Be Vietnam Pro** (giữ nguyên, đã load trong `home.css`)

```css
:root {
  /* Font sizes */
  --text-xs:   0.75rem;    /* 12px — badges, meta */
  --text-sm:   0.8125rem;  /* 13px — table headers, labels */
  --text-base: 0.875rem;   /* 14px — inputs, buttons, nav */
  --text-md:   0.9375rem;  /* 15px — body text */
  --text-lg:   1rem;       /* 16px — card titles */
  --text-xl:   1.125rem;   /* 18px — section subtitles */
  --text-2xl:  1.25rem;    /* 20px — section titles */
  --text-3xl:  1.5rem;     /* 24px — page sub-titles */
  --text-4xl:  1.875rem;   /* 30px — page titles */
  --text-5xl:  2.25rem;    /* 36px — hero headings */

  /* Line heights */
  --leading-tight:  1.25;
  --leading-snug:   1.375;
  --leading-normal: 1.5;
  --leading-relaxed:1.625;

  /* Font weights */
  --font-normal:    400;
  --font-medium:    500;
  --font-semibold:  600;
  --font-bold:      700;
  --font-extrabold: 800;
}
```

**Typography roles (sử dụng nhất quán toàn dự án):**
- `page-title`: `--text-4xl / --font-bold` (desktop), `--text-3xl` (mobile)
- `section-title`: `--text-2xl / --font-bold`
- `card-title`: `--text-lg / --font-semibold`
- `body`: `--text-md / --font-normal`
- `label`: `--text-base / --font-medium`
- `meta/caption`: `--text-sm / --font-normal`
- `badge`: `--text-xs / --font-semibold`

---

### 3. Spacing Scale

Dùng 4px base unit:

```css
:root {
  --space-1:  0.25rem;   /* 4px */
  --space-2:  0.5rem;    /* 8px */
  --space-3:  0.75rem;   /* 12px */
  --space-4:  1rem;      /* 16px */
  --space-5:  1.25rem;   /* 20px */
  --space-6:  1.5rem;    /* 24px */
  --space-8:  2rem;      /* 32px */
  --space-10: 2.5rem;    /* 40px */
  --space-12: 3rem;      /* 48px */
  --space-16: 4rem;      /* 64px */
}
```

---

### 4. Layout Constants

Chọn giá trị thống nhất cho các layout constants:

| Constant | Token | Value | Ghi chú |
|---|---|---|---|
| Sidebar width (desktop) | `--sidebar-width` | `264px` | Trung bình giữa 260 và 274 |
| Header height | `--header-height` | `64px` | Chuẩn hoá từ 60/68px |
| Content max-width | `--content-max` | `1400px` | |
| Sidebar mobile breakpoint | — | `1024px` | collapse tại đây |

---

### 5. Z-index Scale

```css
:root {
  --z-base:      1;
  --z-dropdown:  100;
  --z-sticky:    200;
  --z-sidebar:   1000;
  --z-backdrop:  998;
  --z-modal:     1100;
  --z-toast:     1200;
}
```

---

### 6. Breakpoints

```css
/* Desktop first approach với 4 breakpoints: */
/* xl: max-width 1280px */
/* lg: max-width 1024px → sidebar collapse */
/* md: max-width 768px  → 2-col → 1-col */
/* sm: max-width 480px  → compact spacing */
```

---

### 7. Shared Component Designs (`global.css`)

#### Buttons
```
.btn → base: inline-flex, items-center, gap-2, font-base, font-semibold, 
        border-radius-sm, transition-base, focus-visible ring
.btn-sm   → padding: 6px 14px
.btn-md   → padding: 9px 20px   (default)
.btn-lg   → padding: 12px 28px

.btn-primary → bg: --primary, text: white, hover: --primary-hover
.btn-secondary → bg: --surface, border: --border, text: --text-primary
.btn-ghost → bg: transparent, text: --text-secondary, hover: --primary-light + --primary
.btn-danger → bg: --color-danger, text: white
```

#### Badges
```
.badge → inline-flex, px-2.5 py-0.5, text-xs, font-semibold, border-radius-full
.badge-success / .badge-warning / .badge-danger / .badge-info / .badge-neutral
```

#### Form Elements
```
.form-group → flex-col, gap-space-2
.form-label → text-base, font-medium, text-primary
.form-input → full-width, padding: 9px 12px, border: --border, radius-sm
              focus: border-primary, ring-2 primary/20
              placeholder: --text-muted
.form-error → text-sm, color-danger, mt-space-1
.form-helper → text-sm, text-secondary, mt-space-1
```

#### Cards
```
.card → bg: --surface, border: 1px --border, radius-md, shadow-sm
.card:hover → shadow-md, transform: translateY(-1px)  (optional, dùng với .card--hoverable)
.card-header → px-6 py-4, border-bottom: 1px --border
.card-body → p-6
.card-footer → px-6 py-4, border-top: 1px --border, bg: --background/40
```

---

### 8. Portal-level Design Improvements

#### Sidebar (tất cả portal)
- Width: `264px` (token `--sidebar-width`)
- Brand section height: ~72px
- Active nav item: `bg: --primary-light, color: --primary, font-weight: 600`
- Active indicator: `::before` pseudo-element — 3px solid bar bên trái
- Hover: `bg: --primary-light/60, color: --primary-hover`
- Transition: `background 0.15s, color 0.15s`
- Nav section headers: `text-xs, font-bold, color: --text-muted, letter-spacing: 0.06em`

#### Header (tất cả portal)
- Height: `64px` (token `--header-height`)
- Background: `rgba(255,255,255,0.96)` + `backdrop-filter: blur(8px)`
- Shadow: `0 1px 0 var(--border)` (border thay vì shadow nặng)
- Avatar: 36px circle, gradient bg, initials centered

#### KPI/Stat Cards
- Unified design: icon bên trái (48px circle bg), metric value + label bên phải
- Border-left accent: 3px solid `--primary` (hoặc theo màu semantic)
- Value: `--text-4xl / --font-bold`
- Label: `--text-sm / --font-medium / --text-secondary`
- Hover: subtle `transform: translateY(-2px)` + `shadow-md`

---

### 9. Files cần tạo mới / sửa

| File | Action | Ưu tiên |
|---|---|---|
| `assets/css/home.css` | MỞ RỘNG — thêm spacing, typography, z-index, state color tokens | 🔴 P0 |
| `assets/css/global.css` | TẠO MỚI — shared components | 🔴 P0 |
| `assets/css/learner.css` | REFACTOR — chuẩn hóa tokens, bỏ duplicate | 🔴 P0 |
| `assets/css/school.css` | MỞ RỘNG — thêm missing components, responsive | 🔴 P0 |
| `assets/css/teacher.css` | REFACTOR + THÊM responsive | 🔴 P0 |
| `assets/css/enterprise.css` | REFACTOR — bỏ ent-text-* tokens, gộp analytics/sponsorships | 🔴 P0 |
| `assets/css/auth.css` | VIẾT LẠI — đang dùng minified, cần readable + improved | 🟠 P1 |
| `assets/css/role-selection.css` | CẢI THIỆN | 🟠 P1 |
| `assets/css/admin.css` | VIẾT LẠI — đang minified, thiếu styles | 🟡 P2 |
| Layout files (mỗi portal) | CẬP NHẬT — thêm `global.css` vào load order | 🔴 P0 |
