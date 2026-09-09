# Kế Hoạch Triển Khai: Thẻ Dự Án & Quá Trình Thực Tập Đã Hoàn Thành và Móc Nối Hệ Sinh Thái

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuyển đổi các khối Dự án và Quá trình thực tập trên Hồ sơ năng lực học sinh (`profile.php`) sang chỉ hiển thị các mục **đã hoàn thành**, dưới dạng **thẻ (card) gọn gàng** có nút **Xem chi tiết** mở Modal popup, và móc nối 2 chiều đồng bộ với Phân hệ Doanh nghiệp & Dự án (`ecosystem.php`).

**Architecture:**
- **Backend & Data Layer:** Lọc triệt để các dự án có trạng thái `completed` / `Đã hoàn thành` trong `student-data.php` và `DatabaseTalentPassportRepository`. Bổ sung tham số lọc `filter=completed` trong `PortfolioHttp` / `PortfolioRepository` để chỉ trả về báo cáo thực tập / dự án đã được phê duyệt `verified` và giai đoạn `completed`.
- **Frontend Presentation Layer:** Thiết kế lại lưới thẻ gọn (`.learner-project-card`, `.portfolio-card`) với badge trạng thái xanh lá `● Đã hoàn thành` / `● Đã xác nhận`. Ẩn các form nhập báo cáo lộ thiên trên trang profile.
- **Modal Component:** Xây dựng modal xem chi tiết (`#learner-project-detail-modal`, `#learner-internship-detail-modal`) hiển thị thông tin minh chứng, nhận xét mentor, số giờ và kỹ năng được cấp.
- **Ecosystem Linkage:** Tích hợp liên kết điều hướng 2 chiều: Từ Card/Modal nhảy sang `ecosystem.php?tab=opportunities&filter=completed` và `ecosystem.php?tab=enterprises&filter=completed`; từ `ecosystem.php` bộ lọc "Đã hoàn thành" hiển thị chính xác các mục tương ứng.

**Tech Stack:** PHP 8.3 (Native OOP / PDO), JavaScript (Vanilla ES6+), HTML5 / CSS3 (TalentHub Design System).

## Global Constraints
- PHP CLI binary: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- Tuyệt đối không hiển thị dự án `in_progress` hoặc kỳ thực tập `active` / chưa hoàn thành trên Hồ sơ năng lực (`profile.php`).
- Form nộp/sửa báo cáo của dự án vẫn phải hoạt động bình thường trên trang chi tiết dự án (`project.php?id=...`).
- Tuân thủ cấu trúc bảo mật XSS (`learner_escape`), CSRF token và a11y (focus trap, ARIA attributes).

---

### Task 1: Backend Data Filtering - Chỉ lấy Dự án và Thực tập Đã Hoàn Thành

**Files:**
- Modify: `app/learner/includes/student-data.php`
- Modify: `src/Modules/Student/Service/PortfolioHttp.php`
- Modify: `src/Modules/Student/Repository/PortfolioRepository.php`
- Test: `tests/learner_completed_portfolio_filter_test.php`

**Interfaces:**
- Consumes: PDO database connection, `student_profiles`, `projects`, `learner_internship_reports`.
- Produces: `PortfolioRepository::listForStudent(string $studentId, bool $completedOnly = false): array`
- Query param: `GET /app/learner/api/v1/portfolio.php?filter=completed`

- [ ] **Step 1: Viết bài test kiểm tra bộ lọc chỉ lấy mục đã hoàn thành**

Tạo file `tests/learner_completed_portfolio_filter_test.php`:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Modules\Student\Repository\PortfolioRepository;

$config = require __DIR__ . '/../config/database.php';
$pdo = (new Connection($config))->connect();

$repo = new PortfolioRepository($pdo);

// 1. Kiểm tra filter completedOnly của PortfolioRepository
$studentRow = $pdo->query("SELECT id FROM student_profiles WHERE studyStatus='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$studentRow) {
    echo "[SKIP] Không có học sinh để test\n";
    exit(0);
}
$studentId = (string)$studentRow['id'];

$allData = $repo->listForStudent($studentId, false);
$completedData = $repo->listForStudent($studentId, true);

// Mọi mục thực tập trong $completedData phải có report verified và stage completed
foreach ($completedData['internships'] as $intern) {
    $report = $intern['report'] ?? null;
    assert($report !== null, "Mục hoàn thành phải có report");
    assert(($report['status'] ?? '') === 'verified', "Report phải verified");
    assert(($report['stage'] ?? '') === 'completed', "Stage phải completed");
}

echo "[PASS] learner_completed_portfolio_filter_test passed\n";
```

- [ ] **Step 2: Chạy test để xác nhận fail**

Chạy:
```powershell
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_completed_portfolio_filter_test.php
```
Kỳ vọng: Lỗi do `listForStudent` chưa hỗ trợ tham số `$completedOnly`.

- [ ] **Step 3: Cập nhật `PortfolioRepository.php` & `PortfolioHttp.php` & `student-data.php`**

Trong `src/Modules/Student/Repository/PortfolioRepository.php`:
Cập nhật hàm `listForStudent`:
```php
    public function listForStudent(string $studentId, bool $completedOnly = false): array
    {
        $student = $this->student($studentId);
        $projectSql = "SELECT 'project' kind,p.id contextId,p.title,s.name organization,u.fullName mentorName,r.id reportId,p.status projectStatus FROM projects p JOIN schools s ON s.id=p.schoolId JOIN project_members pm ON pm.projectId=p.id AND pm.studentId=:studentId LEFT JOIN teacher_profiles tp ON tp.id=p.mentorTeacherId LEFT JOIN users u ON u.id=tp.userId LEFT JOIN project_submissions r ON r.projectId=p.id AND r.studentId=:studentId2 WHERE p.schoolId=:schoolId AND (pm.status='active' OR r.id IS NOT NULL) ORDER BY p.title";
        $project = $this->pdo->prepare($projectSql);
        $project->execute(['studentId'=>$studentId,'studentId2'=>$studentId,'schoolId'=>$student['schoolId']]);

        $internSql = "SELECT 'internship' kind,a.id contextId,ip.title,e.name organization,u.fullName mentorName,r.id reportId,a.status applicationStatus FROM internship_applications a JOIN internship_posts ip ON ip.id=a.postId JOIN enterprises e ON e.id=ip.enterpriseId LEFT JOIN internship_mentor_assignments ima ON ima.applicationId=a.id LEFT JOIN teacher_profiles tp ON tp.id=ima.mentorTeacherId LEFT JOIN users u ON u.id=tp.userId LEFT JOIN learner_internship_reports r ON r.applicationId=a.id AND r.studentId=:studentId2 WHERE a.studentId=:studentId AND (a.status='accepted' OR r.id IS NOT NULL) ORDER BY ip.title";
        $intern = $this->pdo->prepare($internSql);
        $intern->execute(['studentId'=>$studentId,'studentId2'=>$studentId]);

        $projectRows = $this->contextRows($project->fetchAll(PDO::FETCH_ASSOC) ?: [],'project');
        $internRows = $this->contextRows($intern->fetchAll(PDO::FETCH_ASSOC) ?: [],'internship');

        if ($completedOnly) {
            $projectRows = array_values(array_filter($projectRows, static function(array $item): bool {
                $status = $item['report']['status'] ?? '';
                $pStatus = $item['projectStatus'] ?? '';
                return $status === 'verified' || $pStatus === 'completed';
            }));
            $internRows = array_values(array_filter($internRows, static function(array $item): bool {
                $report = $item['report'] ?? [];
                return ($report['status'] ?? '') === 'verified' && ($report['stage'] ?? '') === 'completed';
            }));
        }

        return ['projects'=>$projectRows,'internships'=>$internRows];
    }
```

Trong `src/Modules/Student/Service/PortfolioHttp.php`:
Đọc tham số query `filter`:
```php
        if ($request->method==='GET') {
            $completedOnly = ($request->query('filter') ?? '') === 'completed';
            $data=$role==='student' ? $repository->listForStudent($identity['studentId'], $completedOnly) : ['items'=>$repository->listForTeacher($identity['userId'])];
            if ($role==='teacher') $data['skills']=$pdo->query("SELECT id,name FROM skills WHERE status='active' ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
            $data['csrfToken']=$session->csrfToken();
            return $data;
        }
```

Trong `app/learner/includes/student-data.php`:
Lọc mảng `$projects` cho Hồ sơ năng lực (chỉ lấy status `completed` / `Đã hoàn thành`):
Trong mock mode: Bỏ dự án `'status' => 'Đang triển khai'`, chỉ giữ các dự án có `'status' => 'Đã hoàn thành'`.
Trong database mode:
```php
    $projects = array_values(array_filter($tp['projects'] ?? [], static function (array $project): bool {
        $status = strtolower((string)($project['status'] ?? ''));
        return in_array($status, ['completed', 'đã hoàn thành'], true);
    }));
```

- [ ] **Step 4: Chạy test để xác nhận pass**

Chạy:
```powershell
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_completed_portfolio_filter_test.php
```
Kỳ vọng: `[PASS] learner_completed_portfolio_filter_test passed`.

- [ ] **Step 5: Git commit**

```powershell
git add tests/learner_completed_portfolio_filter_test.php src/Modules/Student/Repository/PortfolioRepository.php src/Modules/Student/Service/PortfolioHttp.php app/learner/includes/student-data.php
git commit -m "feat(portfolio): add completed-only filter for projects and internships"
```

---

### Task 2: Hồ Sơ Năng Lực - Thẻ Gọn "Dự Án Đã Hoàn Thành"

**Files:**
- Modify: `app/learner/profile.php:226-276`
- Modify: `assets/css/learner.css`
- Test: `tests/learner_profile_completed_projects_ui_test.js`

**Interfaces:**
- Hiển thị danh sách thẻ dự án đã hoàn thành trong `<section class="learner-card learner-projects">`.
- Nút "Xem chi tiết" gọi Modal `#learner-project-detail-modal`.
- Nút icon "Xem dự án" liên kết tới `project.php?id={id}` hoặc `ecosystem.php?tab=opportunities&filter=completed`.

- [ ] **Step 1: Viết test UI kiểm tra khối Dự án đã hoàn thành trên profile.php**

Tạo `tests/learner_profile_completed_projects_ui_test.js`:
```javascript
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('profile.php renders Dự án đã hoàn thành with compact card and modal triggers', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
    assert.match(html, /Dự án đã hoàn thành/);
    assert.doesNotMatch(html, /Dự án đã tham gia/);
    assert.match(html, /data-open-project-detail/);
    assert.match(html, /ecosystem\.php\?tab=opportunities(&|&amp;)filter=completed/);
});
```

- [ ] **Step 2: Chạy test để xác nhận fail**

Chạy:
```powershell
node tests/learner_profile_completed_projects_ui_test.js
```
Kỳ vọng: Fail vì tiêu đề vẫn là "Dự án đã tham gia".

- [ ] **Step 3: Cập nhật giao diện khối Dự án trong `profile.php`**

Cập nhật dòng 226-276 trong `app/learner/profile.php`:
1. Tiêu đề: "Dự án đã hoàn thành".
2. Khối rỗng: Thông báo phù hợp khi chưa có dự án nào hoàn thành.
3. Card dự án:
   - Tên dự án, badge bảo trợ doanh nghiệp.
   - Mô tả ngắn gọn (clamp 2 dòng).
   - Footer: Badge vai trò + Badge `● Đã hoàn thành` (`background: #DCFCE7; color: #15803D;`).
   - Action buttons:
     - Nút `data-open-project-detail`: "Xem chi tiết" (mở modal).
     - Nút `a.learner-btn`: "Xem trong Hệ sinh thái" dẫn tới `ecosystem.php?tab=opportunities&filter=completed` (hoặc `project.php?id=...`).

- [ ] **Step 4: Chạy test để xác nhận pass**

Chạy:
```powershell
node tests/learner_profile_completed_projects_ui_test.js
```
Kỳ vọng: PASS.

- [ ] **Step 5: Git commit**

```powershell
git add app/learner/profile.php assets/css/learner.css tests/learner_profile_completed_projects_ui_test.js
git commit -m "feat(profile): update completed projects section to compact card layout"
```

---

### Task 3: Panel Quá Trình Thực Tập Đã Hoàn Thành

**Files:**
- Modify: `app/learner/includes/portfolio-panel.php`
- Modify: `assets/js/learner-portfolio.js`
- Modify: `assets/css/learner-portfolio.css`
- Test: `tests/learner_completed_internship_cards_test.js`

**Interfaces:**
- `<div data-portfolio data-role="student" data-filter="completed" ...>`
- Render các thẻ thực tập hoàn thành: Vị trí, Doanh nghiệp, Thời gian, Số giờ, Mentor, Badge "Đã xác nhận hoàn thành", nút "Xem chi tiết", nút "Xem doanh nghiệp".

- [ ] **Step 1: Viết test cho compact internship cards**

Tạo `tests/learner_completed_internship_cards_test.js`:
```javascript
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('learner-portfolio.js handles completed filter and renders compact cards', () => {
    const js = fs.readFileSync(path.join(__dirname, '../assets/js/learner-portfolio.js'), 'utf8');
    assert.match(js, /filter=completed/);
    assert.match(js, /data-open-internship-detail/);
    assert.match(js, /partner\.php\?type=enterprise/);
});
```

- [ ] **Step 2: Chạy test để xác nhận fail**

Chạy:
```powershell
node tests/learner_completed_internship_cards_test.js
```
Kỳ vọng: Fail vì `learner-portfolio.js` chưa có logic `data-open-internship-detail`.

- [ ] **Step 3: Cập nhật `portfolio-panel.php`, `learner-portfolio.js` và `learner-portfolio.css`**

1. Trong `portfolio-panel.php`:
   - Khi `$portfolioContext === ''` (ngữ cảnh Profile):
     - Đặt tiêu đề `<h2>Quá trình thực tập đã hoàn thành</h2>`.
     - Thêm thuộc tính `data-filter="completed"` vào thẻ root `div[data-portfolio]`.
2. Trong `learner-portfolio.js`:
   - Khi `root.dataset.filter === 'completed'`, truyền query `?filter=completed` trong `fetch()` request.
   - Render compact cards:
     - Header: Tiêu đề vị trí thực tập, icon doanh nghiệp + Tên doanh nghiệp.
     - Body: Thông tin `startDate → endDate`, `... giờ thực tế`, `Hướng dẫn: {mentorName}`.
     - Footer: Badge `● Đã xác nhận hoàn thành`.
     - Actions: Nút "Xem chi tiết" (`data-open-internship-detail`), Nút "Doanh nghiệp" dẫn sang `partner.php?type=enterprise&id={enterpriseId}` hoặc `ecosystem.php?tab=enterprises&filter=completed`.
   - Lưu dữ liệu report vào object dataset hoặc memory cache để Modal mở nhanh.
3. Trong `learner-portfolio.css`:
   - Style `.portfolio-compact-grid`, `.portfolio-compact-card`, `.portfolio-badge-success`.

- [ ] **Step 4: Chạy test để xác nhận pass**

Chạy:
```powershell
node tests/learner_completed_internship_cards_test.js
```
Kỳ vọng: PASS.

- [ ] **Step 5: Git commit**

```powershell
git add app/learner/includes/portfolio-panel.php assets/js/learner-portfolio.js assets/css/learner-portfolio.css tests/learner_completed_internship_cards_test.js
git commit -m "feat(portfolio): render compact cards for completed internships"
```

---

### Task 4: Xây Dựng Modal Chi Tiết Dự Án & Thực Tập Trên Profile

**Files:**
- Modify: `app/learner/profile.php`
- Modify: `assets/js/learner-portfolio.js`
- Test: `tests/learner_profile_detail_modals_test.js`

**Interfaces:**
- Modal ID: `#learner-project-detail-modal`
- Modal ID: `#learner-internship-detail-modal`
- Hỗ trợ đóng mở bằng phím ESC, click nút đóng `[data-close-modal]`, click backdrop, focus management.

- [ ] **Step 1: Viết test cho Modal chi tiết trên Profile**

Tạo `tests/learner_profile_detail_modals_test.js`:
```javascript
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('profile.php has detail modals for project and internship', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
    assert.match(html, /id="learner-project-detail-modal"/);
    assert.match(html, /id="learner-internship-detail-modal"/);
});
```

- [ ] **Step 2: Chạy test để xác nhận fail**

Chạy:
```powershell
node tests/learner_profile_detail_modals_test.js
```
Kỳ vọng: Fail vì modals chưa tồn tại.

- [ ] **Step 3: Bổ sung Modal chi tiết vào `profile.php` và logic JS xử lý**

1. Trong `app/learner/profile.php`:
   Thêm 2 modal:
   - `#learner-project-detail-modal`: Hiển thị Tên dự án, Đơn vị bảo trợ, Lĩnh vực, Vai trò, Mô tả chi tiết, Đóng góp, Minh chứng kho mã nguồn & Demo, Nút "Xem trong Hệ sinh thái".
   - `#learner-internship-detail-modal`: Hiển thị Vị trí, Doanh nghiệp, Thời gian, Số giờ thực tế, Minh chứng báo cáo, Nhận xét của Mentor, Kỹ năng được cấp, Nút "Xem Doanh nghiệp trong Hệ sinh thái".
2. Trong `assets/js/learner-portfolio.js` & `assets/js/learner.js`:
   - Lắng nghe click `[data-open-project-detail]` và `[data-open-internship-detail]`.
   - Đổ dữ liệu tương ứng vào các trường trong modal và gọi `window.LearnerUI.openModal()`.

- [ ] **Step 4: Chạy test để xác nhận pass**

Chạy:
```powershell
node tests/learner_profile_detail_modals_test.js
```
Kỳ vọng: PASS.

- [ ] **Step 5: Git commit**

```powershell
git add app/learner/profile.php assets/js/learner-portfolio.js tests/learner_profile_detail_modals_test.js
git commit -m "feat(profile): add detail modals for completed projects and internships"
```

---

### Task 5: Móc Nối 2 Chiều Với Hệ Sinh Thái & Dự Án (`ecosystem.php`)

**Files:**
- Modify: `app/learner/ecosystem.php`
- Modify: `assets/js/learner.js`
- Test: `tests/learner_ecosystem_completed_linkage_test.js`

**Interfaces:**
- Query string: `ecosystem.php?tab=opportunities&filter=completed` -> Tự động chọn tab Dự án và lọc các dự án sinh viên đã hoàn thành.
- Query string: `ecosystem.php?tab=enterprises&filter=completed` -> Tự động chọn tab Doanh nghiệp và lọc các cơ hội/doanh nghiệp đã hoàn thành.

- [ ] **Step 1: Viết test cho linkage giữa Profile và Ecosystem**

Tạo `tests/learner_ecosystem_completed_linkage_test.js`:
```javascript
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('ecosystem.php properly initialises completed filter from query params', () => {
    const html = fs.readFileSync(path.join(__dirname, '../app/learner/ecosystem.php'), 'utf8');
    assert.match(html, /\$initialLifecycleFilter/);
    assert.match(html, /filter=completed/);
});
```

- [ ] **Step 2: Chạy test để xác nhận trạng thái hiện tại**

Chạy:
```powershell
node tests/learner_ecosystem_completed_linkage_test.js
```

- [ ] **Step 3: Hoàn thiện logic lọc và hiển thị tại `ecosystem.php`**

1. Kiểm tra và đảm bảo khi `filter === 'completed'`, tab Doanh nghiệp hiển thị các vị trí thực tập / cơ hội đã hoàn thành khớp với Quá trình thực tập trên Hồ sơ năng lực.
2. Kiểm tra tab Dự án hiển thị các dự án có trạng thái đã hoàn thành (`status = 'completed'`).
3. Đảm bảo các nút điều hướng từ `profile.php` chuyển thẳng sang đúng tab và bộ lọc:
   - `ecosystem.php?tab=opportunities&filter=completed`
   - `ecosystem.php?tab=enterprises&filter=completed`

- [ ] **Step 4: Chạy test để xác nhận pass**

Chạy:
```powershell
node tests/learner_ecosystem_completed_linkage_test.js
```
Kỳ vọng: PASS.

- [ ] **Step 5: Git commit**

```powershell
git add app/learner/ecosystem.php assets/js/learner.js tests/learner_ecosystem_completed_linkage_test.js
git commit -m "feat(ecosystem): strengthen completed status filter synchronization with profile"
```

---

### Task 6: Kiểm Thử Toàn Diện (Regression & End-to-End Verification)

**Files:**
- Test files: Tất cả test PHP và Node.js liên quan đến profile, portfolio, và ecosystem.

- [ ] **Step 1: Chạy toàn bộ test suite PHP**

Chạy:
```powershell
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_completed_portfolio_filter_test.php
& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_activity_cover_test.php
```
Kỳ vọng: Tất cả tests đều đạt `[PASS]`.

- [ ] **Step 2: Chạy toàn bộ test suite Node.js**

Chạy:
```powershell
node tests/learner_profile_completed_projects_ui_test.js
node tests/learner_completed_internship_cards_test.js
node tests/learner_profile_detail_modals_test.js
node tests/learner_ecosystem_completed_linkage_test.js
```
Kỳ vọng: Tất cả tests đều đạt `OK / PASS`.

- [ ] **Step 3: Kiểm tra Git status và Clean up**

Chạy:
```powershell
git status -s
```
Xác nhận không còn file tạm hoặc lỗi cú pháp.
