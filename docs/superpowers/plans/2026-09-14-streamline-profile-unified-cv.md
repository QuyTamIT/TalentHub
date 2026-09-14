# Tinh Gọn Hồ Sơ Năng Lực & Đồng Nhất Giao Diện CV Chứng Thực Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tinh gọn các nút hành động trên trang Hồ sơ năng lực (rút từ 4 nút còn đúng 3 nút: Xuất CV, Chia sẻ hồ sơ, Chỉnh sửa; bỏ Talent Passport), loại bỏ toàn bộ chữ "360" trong bản CV xuất ra (bảo đảm chuẩn 1 trang A4 duy nhất), và đồng nhất giao diện khi quét QR / mở link chia sẻ cho nhà tuyển dụng để hiển thị đúng 100% bản CV chứng thực đã xuất.

**Architecture:** Sử dụng chung engine hiển thị CV 2 cột chuẩn A4 (`passport-cv-template.php`, `PassportCvViewModel`, `DatabasePassportCvRepository`, `learner-passport-cv.css`) cho cả hai luồng: luồng sinh viên xem trước/xuất PDF và luồng nhà tuyển dụng/người ngoài quét mã QR hoặc mở link chia sẻ (`shared-profile.php`). Thêm cờ `$isGuestView` để tùy biến thanh công cụ cho nhà tuyển dụng (In/Tải PDF) mà không lộ các đường dẫn nội bộ sinh viên.

**Tech Stack:** PHP 8.3 (CLI: `D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`), HTML5/CSS3 (A4 print layout), JavaScript (qrcodejs, window.print).

## Global Constraints
- Nút tại Hồ sơ năng lực (`profile.php`): Đúng 3 nút: `[Xuất CV]`, `[Chia sẻ hồ sơ]`, `[Chỉnh sửa]`.
- Tuyệt đối không còn xuất hiện cụm từ "360" hoặc "360°" trong template và dữ liệu hiển thị của CV.
- Định dạng in PDF của CV phải bảo đảm trọn vẹn trong đúng 1 trang A4 duy nhất, bản in khớp 100% với bản xem trước.
- Trang công khai `shared-profile.php` khi quét mã QR hoặc click link chia sẻ phải hiển thị bản CV với cấu trúc và phong cách giống hệt 100% bản CV xuất ra.
- An toàn bảo mật: Người ngoài không cần đăng nhập vẫn xem được CV qua token/code hợp lệ; token hết hạn hoặc không hợp lệ phải hiển thị thông báo lỗi 404 thân thiện.

---

### Task 1: Tinh gọn các nút tại trang Hồ sơ năng lực (`app/learner/profile.php`)

**Files:**
- Modify: `app/learner/profile.php:66-80`
- Modify: `app/learner/profile.php:478-486`
- Modify: `bin/test-student-upgrades.php:145-152`
- Test: `bin/test-student-upgrades.php`

**Interfaces:**
- Nút 1: `<a class="learner-btn learner-btn--primary" href="talent-passport-cv.php">... Xuất CV</a>`
- Nút 2: `<button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-share-modal">... Chia sẻ hồ sơ</button>`
- Nút 3: `<button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-edit-modal">... Chỉnh sửa</button>`

- [ ] **Step 1: Viết test kiểm tra số lượng và nhãn nút bấm trên `profile.php`**

Cập nhật dòng 150 trong `bin/test-student-upgrades.php`:
```php
assertCondition("Profile page has button 'Xuất CV' linking to talent-passport-cv.php", str_contains($profileHtml, 'talent-passport-cv.php') && str_contains($profileHtml, 'Xuất CV'));
assertCondition("Profile page does NOT have button 'Talent Passport'", !str_contains($profileHtml, '> Talent Passport<') && !str_contains($profileHtml, '>Talent Passport<'));
```

- [ ] **Step 2: Chạy test để xác nhận test thất bại trước khi sửa code**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" bin/test-student-upgrades.php`
Expected: FAIL ở điều kiện kiểm tra không có 'Talent Passport'.

- [ ] **Step 3: Cập nhật `app/learner/profile.php`**

Thay thế khối `.learner-profile-actions` (dòng 66-79) thành:
```html
<div class="learner-profile-actions">
    <a class="learner-btn learner-btn--primary" href="talent-passport-cv.php" style="background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%); color: #FFFFFF; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);" title="Xem trước và xuất bản CV A4 chuyên nghiệp">
        <?= learner_icon('file-text', 18); ?> Xuất CV
    </a>
    <button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-share-modal">
        <?= learner_icon('share', 18); ?> Chia sẻ hồ sơ
    </button>
    <button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-edit-modal">
        <?= learner_icon('edit', 18); ?> Chỉnh sửa
    </button>
</div>
```
Và trong modal `learner-share-modal` (dòng 479):
```html
<a class="learner-btn learner-btn--outline" href="talent-passport-cv.php" target="_blank" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.875rem;" title="Xem trước và tải bản CV A4">
    <?= learner_icon('file-text', 16); ?> Xem trước &amp; Tải CV
</a>
```

- [ ] **Step 4: Chạy test để xác nhận test đã pass**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" bin/test-student-upgrades.php`
Expected: PASS cho các điều kiện của Profile page.

- [ ] **Step 5: Commit thay đổi**

```bash
git add app/learner/profile.php bin/test-student-upgrades.php
git commit -m "feat(learner): streamline profile action buttons and remove talent passport button"
```

---

### Task 2: Chuẩn hóa trang Xuất CV và loại bỏ "360" (`passport-cv-template.php` & `talent-passport-cv.php`)

**Files:**
- Modify: `app/learner/includes/passport-cv-template.php`
- Modify: `app/learner/talent-passport-cv.php`
- Test: `tests/test_passport_cv_rendering.php`

**Interfaces:**
- Nhận biến `$isGuestView` (boolean, mặc định `false`).
- Nếu `$isGuestView === false`: Hiển thị toolbar sinh viên (`← Hồ sơ năng lực`, hướng dẫn, nút `Lấy dữ liệu mới & xuất PDF`).
- Nếu `$isGuestView === true`: Hiển thị toolbar khách (`Hồ sơ CV xác thực điện tử bởi TalentHub`, nút `In / Tải PDF`).
- Bỏ cụm từ `360` và `360°` trong toàn bộ giao diện CV.

- [ ] **Step 1: Viết test kiểm tra nội dung CV không còn "360" và hỗ trợ cờ `$isGuestView`**

Tạo file `tests/test_passport_cv_rendering.php`:
```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/ReadModel/PassportCvViewModel.php';

$mockData = [
    'student' => [
        'id' => '11111111-1111-4111-8111-111111111111',
        'fullName' => 'Nguyễn Hoài An',
        'email' => 'an.nh@talenthub.vn',
        'phone' => '0901234567',
        'location' => 'Hà Nội',
        'school' => 'Đại học FPT',
        'class' => 'K18-AI',
        'headline' => 'Kỹ sư Trí tuệ Nhân tạo',
        'bio' => 'Đam mê nghiên cứu Machine Learning.',
        'avatarUrl' => null,
    ],
    'skills' => [
        ['name' => 'Python', 'levelScore' => 88, 'verificationStatus' => 'verified'],
    ],
    'projects' => [
        ['id' => 'p1', 'title' => 'Hệ thống AI Khuyến nghị', 'category' => 'AI', 'status' => 'completed', 'role' => 'Lead', 'contribution' => 'Thiết kế model', 'memberStatus' => 'active'],
    ],
    'internships' => [],
    'teacher_evaluations' => [],
    'assessment_results' => [],
    'experience' => ['confirmed_entries' => [], 'summary' => ['total_hours' => 20, 'total_activities' => 3]],
    'badges' => [['name' => 'Innovator', 'description' => 'Huy hiệu Đổi mới Sáng tạo']],
];

$cv = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($mockData, '14/09/2026 21:00:00');

// Test 1: Student view
$isGuestView = false;
$verificationUrl = 'https://talenthub.vn/app/learner/shared-profile.php?code=' . urlencode($cv['passport_code']);
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$studentHtml = ob_get_clean();

assert(!str_contains($studentHtml, '360°'), 'Student CV must not contain 360°');
assert(!str_contains($studentHtml, ' 360 '), 'Student CV must not contain 360');
assert(str_contains($studentHtml, 'profile.php'), 'Student toolbar must link to profile.php');
assert(str_contains($studentHtml, 'Lấy dữ liệu mới &amp; xuất PDF'), 'Student toolbar has refresh & export button');

// Test 2: Guest view
$isGuestView = true;
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$guestHtml = ob_get_clean();

assert(str_contains($guestHtml, 'Bản CV xác thực số bởi TalentHub'), 'Guest toolbar shows verified banner');
assert(str_contains($guestHtml, 'In / Tải PDF'), 'Guest toolbar has In / Tải PDF button');
assert(!str_contains($guestHtml, 'talent-passport.php'), 'Guest view must not contain internal links');

echo "PASS: Passport CV template renders cleanly without 360 and supports both student and guest view.\n";
```

- [ ] **Step 2: Chạy test để xác nhận test thất bại**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test_passport_cv_rendering.php`
Expected: FAIL do template hiện tại vẫn chứa `360°` và chưa có nhánh `$isGuestView`.

- [ ] **Step 3: Cập nhật `app/learner/includes/passport-cv-template.php` và `app/learner/talent-passport-cv.php`**

Trong `app/learner/includes/passport-cv-template.php`:
1. Thêm khởi tạo: `$isGuestView = $isGuestView ?? false;`
2. Cập nhật thanh toolbar:
```php
    <nav class="cv-toolbar" aria-label="Xuất CV">
        <?php if ($isGuestView): ?>
            <span style="font-weight: 700; color: #1e3a8a; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Hồ sơ CV xác thực điện tử bởi TalentHub • Mã số: <?= $escapeCv($cv['passport_code'] ?? ''); ?>
            </span>
            <p style="margin: 0; color: #475569; font-size: 13px;">Bản CV chuẩn A4 được trích xuất từ dữ liệu chứng thực của nhà trường.</p>
            <button type="button" onclick="window.print()" style="background: #1e40af; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                In / Tải PDF
            </button>
        <?php else: ?>
            <a href="profile.php">← Hồ sơ năng lực</a>
            <p>CV 2 cột chọn lọc 1 trang A4. Khi lưu PDF: chọn A4, tỷ lệ 100%, bật đồ họa nền, tắt đầu/chân trang của trình duyệt.</p>
            <button type="button" data-cv-export>Lấy dữ liệu mới &amp; xuất PDF</button>
        <?php endif; ?>
    </nav>
```
3. Đổi title: `<title><?= $escapeCv($cv['name']); ?> - CV Năng lực TalentHub</title>`
4. Đổi badge dòng 36: `<div class="cv-badge-verified">✓ Xác thực Năng lực TalentHub</div>`
5. Đổi con dấu dòng 142: `<span class="cv-seal-title">TALENT PASSPORT</span>`

Trong `app/learner/talent-passport-cv.php`:
Đổi link thông báo lỗi:
`<a href="profile.php">Quay lại Hồ sơ năng lực</a>`

- [ ] **Step 4: Chạy test để xác nhận test đã pass**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test_passport_cv_rendering.php`
Expected: Output: `PASS: Passport CV template renders cleanly without 360 and supports both student and guest view.`

- [ ] **Step 5: Commit thay đổi**

```bash
git add app/learner/includes/passport-cv-template.php app/learner/talent-passport-cv.php tests/test_passport_cv_rendering.php
git commit -m "feat(learner): clean up CV template, remove 360 wording, add guest view toolbar"
```

---

### Task 3: Đồng nhất trang Quét mã QR & Link chia sẻ cho Nhà tuyển dụng (`app/learner/shared-profile.php`)

**Files:**
- Modify: `app/learner/shared-profile.php`
- Test: `tests/test_shared_profile_cv_view.php`

**Interfaces:**
- Nhận `?code=...` hoặc `?token=...`.
- Nếu xác thực thành công: Lấy `studentId`, gọi `DatabasePassportCvRepository::forStudent($studentId)` và `PassportCvViewModel::build($data, $stamp)`.
- Kết xuất `passport-cv-template.php` với `$isGuestView = true`.
- Nếu không tìm thấy hoặc hết hạn: Hiển thị giao diện 404 trang nhã.

- [ ] **Step 1: Viết test kiểm tra luồng xác thực `shared-profile.php` hiển thị giao diện CV A4**

Tạo file `tests/test_shared_profile_cv_view.php`:
```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

// Lấy 1 học viên mẫu trong DB
$stmt = $pdo->query("SELECT sp.id, u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE u.status = 'active' LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "SKIP: No active student found in DB for live test.\n";
    exit(0);
}

$studentId = (string)$student['id'];
$passportCode = 'TP-' . strtoupper(substr(str_replace('-', '', $studentId), 0, 8));

// Giả lập request GET với code
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['code'] = $passportCode;

ob_start();
require dirname(__DIR__) . '/app/learner/shared-profile.php';
$outputHtml = ob_get_clean();

assert(str_contains($outputHtml, 'cv-sheet'), "Shared profile must render CV sheet element");
assert(str_contains($outputHtml, 'cv-sidebar'), "Shared profile must render CV sidebar");
assert(str_contains($outputHtml, 'cv-main'), "Shared profile must render CV main column");
assert(str_contains($outputHtml, 'Hồ sơ CV xác thực điện tử bởi TalentHub'), "Shared profile must show verified guest toolbar");
assert(str_contains($outputHtml, 'In / Tải PDF'), "Shared profile must have print/pdf button for guest");
assert(!str_contains($outputHtml, '360°'), "Shared profile must not contain 360°");

echo "PASS: shared-profile.php successfully renders 100% identical CV A4 view for QR/shared links.\n";
```

- [ ] **Step 2: Chạy test để xác nhận test thất bại**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test_shared_profile_cv_view.php`
Expected: FAIL vì `shared-profile.php` hiện vẫn kết xuất card view cũ (`shared-profile-container`).

- [ ] **Step 3: Cập nhật `app/learner/shared-profile.php`**

Cập nhật `app/learner/shared-profile.php`:
1. Giải mã token hoặc code thông qua `ProfileSharingService`.
2. Khi có `$studentId`, khởi tạo `DatabasePassportCvRepository` và `PassportCvViewModel` để tạo `$cv`.
3. Nếu truy cập qua `token` có `sharedFields`, ẩn các trường mà sinh viên không chọn chia sẻ (ví dụ email, phone).
4. Thiết lập `$isGuestView = true;` và `$verificationUrl = ...;`
5. Gọi `require __DIR__ . '/includes/passport-cv-template.php';`
6. Nếu `$resolved === null`, hiển thị màn hình 404 thân thiện thông báo "Hồ sơ không tồn tại hoặc liên kết chia sẻ đã hết hạn".

- [ ] **Step 4: Chạy test để xác nhận test đã pass**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test_shared_profile_cv_view.php`
Expected: `PASS: shared-profile.php successfully renders 100% identical CV A4 view for QR/shared links.`

- [ ] **Step 5: Commit thay đổi**

```bash
git add app/learner/shared-profile.php tests/test_shared_profile_cv_view.php
git commit -m "feat(learner): unify shared profile and QR verification to display identical CV A4 layout"
```

---

### Task 4: Kiểm thử toàn diện toàn bộ chu trình & Xác nhận bố cục 1 trang A4

**Files:**
- Create: `tests/test_unified_cv_suite.php`

- [ ] **Step 1: Viết test tổng hợp kiểm chứng cả 3 luồng (Profile, Export CV, QR/Share)**

Tạo file `tests/test_unified_cv_suite.php` kiểm tra:
1. `profile.php`: Chỉ có 3 nút (`Xuất CV`, `Chia sẻ hồ sơ`, `Chỉnh sửa`), không có `Talent Passport`.
2. `talent-passport-cv.php`: Bố cục A4 2 cột, không có `360`, nút back trỏ về `profile.php`.
3. `shared-profile.php?code=...`: Bố cục A4 2 cột, giống hệt bản đã xuất, có nút `In / Tải PDF`, không có link nội bộ sinh viên.
4. `shared-profile.php?token=invalid`: Trả về giao diện 404 an toàn.

- [ ] **Step 2: Chạy test tổng hợp**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" tests/test_unified_cv_suite.php`
Expected: ALL PASS.

- [ ] **Step 3: Chạy lại test suite hệ thống `bin/test-student-upgrades.php`**

Run: `& "D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" bin/test-student-upgrades.php`
Expected: Profile page tests pass.

- [ ] **Step 4: Commit toàn bộ test suite hoàn thiện**

```bash
git add tests/test_unified_cv_suite.php
git commit -m "test(learner): add end-to-end verification suite for streamlined profile and unified CV views"
```
