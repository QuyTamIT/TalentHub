# Compact Accepted Application Card Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thu gọn giao diện hồ sơ ứng tuyển đã được duyệt (trạng thái "Đã nhận" / trúng tuyển), thay thế thanh tiến trình 4 bước cồng kềnh bằng khối banner chúc mừng tinh gọn, đồng bộ màu sắc và tối ưu không gian.

**Architecture:**
Trong `app/learner/ecosystem.php`, kiểm tra `$isAccepted = in_array($appStatus, ['accepted', 'hired'], true);`. Nếu đúng, thay vì render `.learner-app-stepper` và `.learner-app-status-note`, render component `.learner-app-accepted-banner` chứa icon thành công, tiêu đề trúng tuyển, thời gian hoàn tất, thông báo chúc mừng & hướng dẫn nhận việc, cùng lời nhắn gửi kèm (nếu có). Thêm CSS chuyên biệt cho banner trong `assets/css/learner.css`.

**Tech Stack:** PHP 8+, HTML5, CSS3 (Design tokens từ TalentHub).

## Global Constraints

- File spec gốc: `docs/superpowers/specs/2026-09-15-compact-accepted-application-card-design.md`
- Giữ nguyên hiển thị stepper đối với các trạng thái ứng tuyển khác (`submitted`, `reviewing`, `interview`, `declined`, `withdrawn`).
- Sử dụng biến màu và phong cách chuẩn của TalentHub (`--color-success`, `--color-success-light`, `--color-success-border`, `learner_icon('check-circle', 22)`).
- Không làm gián đoạn các tính năng khác trong trang `ecosystem.php` (bộ lọc, AI matching, chi tiết cơ hội).

---

### Task 1: Thêm CSS định kiểu cho `.learner-app-accepted-banner`

**Files:**
- Modify: `assets/css/learner.css:7300-7320`

**Interfaces:**
- Produces: CSS classes `.learner-app-accepted-banner`, `__icon`, `__body`, `__header`, `__title`, `__time`, `__message`, `__user-note`

- [ ] **Step 1: Viết rules CSS cho `.learner-app-accepted-banner` trong `assets/css/learner.css`**

Thêm các quy tắc styling hiện đại, responsive, hỗ trợ tốt cả light và dark mode:
```css
/* Compact Accepted Application Banner */
.learner-app-accepted-banner {
    margin-top: 14px;
    padding: 16px 18px;
    background: #f0fdf4;
    background: var(--color-success-light, #f0fdf4);
    border: 1px solid #bbf7d0;
    border: 1px solid var(--color-success-border, #bbf7d0);
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: all 0.2s ease;
}

.learner-app-accepted-banner__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    color: var(--color-success, #15803d);
    flex-shrink: 0;
}

.learner-app-accepted-banner__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.learner-app-accepted-banner__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.learner-app-accepted-banner__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #166534;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.learner-app-accepted-banner__time {
    font-size: 0.78rem;
    color: #15803d;
    font-weight: 500;
    background: #dcfce7;
    padding: 2px 8px;
    border-radius: 6px;
}

.learner-app-accepted-banner__message {
    margin: 0;
    font-size: 0.85rem;
    color: #1e293b;
    line-height: 1.5;
}

.learner-app-accepted-banner__user-note {
    margin-top: 4px;
    padding-top: 8px;
    border-top: 1px dashed #bbf7d0;
    font-size: 0.8rem;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.learner-app-accepted-banner__user-note em {
    color: #0f172a;
    font-style: normal;
}

@media (max-width: 640px) {
    .learner-app-accepted-banner {
        flex-direction: column;
        gap: 10px;
    }
    .learner-app-accepted-banner__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}
```

- [ ] **Step 2: Xác minh cú pháp CSS không bị lỗi**

Kiểm tra file `assets/css/learner.css` đảm bảo đóng mở ngoặc hợp lệ và không ghi đè selector khác.

- [ ] **Step 3: Commit CSS**

```bash
git add assets/css/learner.css
git commit -m "style(learner): add styles for compact accepted application banner"
```

---

### Task 2: Cập nhật template hiển thị thẻ ứng tuyển trong `app/learner/ecosystem.php`

**Files:**
- Modify: `app/learner/ecosystem.php:275-325`

**Interfaces:**
- Consumes: `$app`, `$appStatus`, `$statusNote`, `learner_icon()`, `learner_escape()`
- Produces: Giao diện thẻ ứng tuyển tinh gọn khi `$isAccepted === true`

- [ ] **Step 1: Thêm cờ `$isAccepted` và trích xuất mốc thời gian hoàn tất trong `ecosystem.php`**

```php
$isAccepted = in_array($appStatus, ['accepted', 'hired'], true);
$acceptedDate = '';
if ($isAccepted) {
    if (!empty($app['pipeline'])) {
        $lastStep = end($app['pipeline']);
        $acceptedDate = $lastStep['date'] ?? '';
    }
    if (empty($acceptedDate)) {
        $acceptedDate = $app['updated_at_formatted'] ?? '';
    }
}
```

- [ ] **Step 2: Cấu trúc điều kiện rẽ nhánh HTML**

```php
<?php if ($isAccepted): ?>
    <div class="learner-app-accepted-banner" role="status">
        <div class="learner-app-accepted-banner__icon" aria-hidden="true">
            <?= learner_icon('check-circle', 22); ?>
        </div>
        <div class="learner-app-accepted-banner__body">
            <div class="learner-app-accepted-banner__header">
                <h4 class="learner-app-accepted-banner__title">Trúng tuyển &amp; Được tiếp nhận</h4>
                <?php if (!empty($acceptedDate)): ?>
                    <span class="learner-app-accepted-banner__time">Hoàn tất lúc <?= learner_escape($acceptedDate); ?></span>
                <?php endif; ?>
            </div>
            <p class="learner-app-accepted-banner__message">
                Chúc mừng bạn đã trúng tuyển thực tập! Doanh nghiệp đã duyệt tiếp nhận hồ sơ và sẽ sớm liên hệ hướng dẫn nhận việc qua email hoặc số điện thoại.
            </p>
            <?php if (!empty($app['message'])): ?>
                <div class="learner-app-accepted-banner__user-note">
                    <?= learner_icon('mail', 13); ?>
                    <span>Lời nhắn gửi kèm của bạn: <em>“<?= learner_escape($app['message']); ?>”</em></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Toàn bộ stepper, message-preview, status-note cũ giữ nguyên -->
<?php endif; ?>
```

- [ ] **Step 3: Chạy PHP lint kiểm tra cú pháp**

Run: `php -l app/learner/ecosystem.php`
Expected: `No syntax errors detected in app/learner/ecosystem.php`

- [ ] **Step 4: Commit thay đổi template**

```bash
git add app/learner/ecosystem.php
git commit -m "feat(learner): render compact banner for accepted applications in ecosystem tracker"
```

---

### Task 3: Kiểm thử và Xác minh toàn diện

**Files:**
- Test: Kiểm tra render trực tiếp qua PHP CLI hoặc curl trên server local

- [ ] **Step 1: Chạy kiểm tra PHP lint toàn bộ file liên quan**

Run:
```bash
php -l app/learner/ecosystem.php
```

- [ ] **Step 2: Chạy test suite hiện có của dự án (nếu có)**

Run:
```bash
composer test || vendor/bin/phpunit
```

- [ ] **Step 3: Xác minh trực quan kết quả**

Kiểm tra DOM output mô phỏng đơn ứng tuyển trạng thái `accepted` để đảm bảo `.learner-app-accepted-banner` được render đúng, stepper không xuất hiện, và các trạng thái khác vẫn render stepper bình thường.
