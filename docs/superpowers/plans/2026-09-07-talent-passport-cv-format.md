# Kế Hoạch Triển Khai: Bố Cục CV Chuyên Nghiệp 2 Cột Cho Xuất PDF Hồ Sơ Năng Lực

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuyển đổi toàn diện giao diện và định dạng in/xuất file PDF của trang Hộ chiếu Năng lực Số (`talent-passport.php`) sang bố cục CV chuyên nghiệp 2 cột chuẩn quốc tế, tận dụng đầy đủ dữ liệu thực của sinh viên trong hệ sinh thái TalentHub.

**Architecture:** Tái cấu trúc file `app/learner/talent-passport.php` thành cấu trúc 2 cột đồng bộ cho cả Web Preview và A4 Print Preview. Bổ sung trích xuất dữ liệu Hoạt động trải nghiệm (`confirmed_entries`) và Huy hiệu danh dự (`badges`) bên cạnh 4 bài test, kỹ năng, dự án bảo trợ, chứng chỉ và đánh giá giảng viên. Thiết lập hệ thống CSS Modern Executive kết hợp CSS Print `@media print` phân trang chống rách khối.

**Tech Stack:** PHP 8.3, HTML5 Semantics, CSS Grid/Flexbox, CSS Print Media Queries (`@page`, `break-inside`), QRCode.js, Vanilla JavaScript.

## Global Constraints

- Không làm đứt gãy bất kỳ assertion nào trong `bin/test-student-upgrades.php` (bao gồm: `DIGITAL TALENT PASSPORT`, `THANG ĐIỂM 100`, `Python`, `PyTorch`, `ThS. Nguyễn Văn Hùng`, `window.print()`, `Quét để xem hồ sơ đã đồng ý chia sẻ` / `passport-verification-qr`).
- Dữ liệu hoàn toàn lấy từ model và database thực, không hardcode dữ liệu giả khi người dùng đã có profile.
- Đảm bảo in ấn PDF bằng trình in của trình duyệt (`window.print()`) đạt chuẩn vector và căn lề A4 chuẩn mực.

---

### Task 1: Bổ sung dữ liệu Hoạt động & Huy hiệu và phân nhóm Kỹ năng trong PHP

**Files:**
- Modify: `d:/TalentHub/app/learner/talent-passport.php:80-165`

**Interfaces:**
- Consumes: `$talentPassport`, `$student`, `$activities`, `$learnerBadges` từ `includes/student-data.php`.
- Produces: 
  - `$technicalSkills` (array kỹ năng chuyên môn)
  - `$softSkills` (array kỹ năng mềm)
  - `$displayActivities` (array hoạt động trải nghiệm thực tế)
  - `$displayBadges` (array huy hiệu đạt được)
  - `$professionalSummary` (tóm tắt hồ sơ năng lực cá nhân)

- [ ] **Step 1: Chuẩn bị khối dữ liệu trích xuất mới trong `talent-passport.php`**
  - Tách `$displaySkills` thành `$technicalSkills` và `$softSkills`.
  - Nạp `$displayActivities` từ `$talentPassport['experience']['confirmed_entries']` hoặc fallback `$activities`.
  - Nạp `$displayBadges` từ `$talentPassport['badges']` hoặc fallback `$learnerBadges`.
  - Xây dựng `$professionalSummary` lấy từ `$student['bio']` hoặc tổng hợp ngắn gọn theo chuyên môn, số giờ trải nghiệm và kỹ năng nổi bật.

- [ ] **Step 2: Chạy kiểm tra cú pháp PHP bằng CLI**
  - Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -l app/learner/talent-passport.php`
  - Expected: `No syntax errors detected in app/learner/talent-passport.php`

---

### Task 2: Tái cấu trúc HTML Markup sang Bố cục 2 Cột Chuẩn CV

**Files:**
- Modify: `d:/TalentHub/app/learner/talent-passport.php:980-1255`

**Interfaces:**
- Consumes: Toàn bộ các biến dữ liệu từ Task 1.
- Produces: Cấu trúc HTML phân chia rõ ràng:
  - Header Banner: Nhận diện Digital Talent Passport 360° TalentHub.
  - `.passport-cv-body`: Khung bao 2 cột chính.
  - `.passport-cv-sidebar` (Cột Trái ~34%):
    - Avatar & Xác thực tài khoản
    - Thông tin liên hệ đầy đủ
    - Thẻ Điểm Năng lực Tổng hợp & Xếp loại
    - Hồ sơ 4 bài đánh giá (MBTI, Holland, DISC, Đa trí thông minh)
    - Nhóm Kỹ năng Chuyên môn & Nhóm Kỹ năng Mềm
    - Khung QR Code xác thực số
  - `.passport-cv-main` (Cột Phải ~66%):
    - Header Họ tên & Chức danh / Headline
    - Tóm tắt Hồ sơ Năng lực (Professional Summary)
    - Đề án Đổi mới Sáng tạo & Doanh nghiệp Bảo trợ (Projects)
    - Hoạt động Trải nghiệm & Ngoại khóa (Activities)
    - Chứng chỉ & Huy hiệu Đã thẩm định (Certificates & Badges)
    - Nhận xét Chứng thực của Giảng viên & Dấu mộc điện tử (Teacher Endorsement)
  - Footer trang in có ngày giờ xuất bản và nguồn chứng thực.

- [ ] **Step 1: Thay thế khối markup cũ bằng cấu trúc CV 2 cột chuyên nghiệp**
- [ ] **Step 2: Đảm bảo giữ nguyên các ID cần thiết cho JavaScript (`passport-verification-qr`, `passport-qr-status`, `talent-passport-card`, `btn-print-passport`, `btn-copy-passport-link`)**
- [ ] **Step 3: Chạy test PHP Lint**
  - Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -l app/learner/talent-passport.php`
  - Expected: `No syntax errors detected in app/learner/talent-passport.php`

---

### Task 3: Thiết kế CSS Hiện đại & Tối ưu Hóa In Ấn PDF A4 (`@media print`)

**Files:**
- Modify: `d:/TalentHub/app/learner/talent-passport.php:175-980`

**Interfaces:**
- Stylesheet nội tuyến chuyên biệt cho Talent Passport:
  - Layout Web Desktop & Responsive Mobile
  - Hệ màu sang trọng: Navy Blue (`#0F172A`, `#1E3A8A`), Slate (`#334155`, `#F8FAFC`), Accent Emerald (`#059669`)
  - Chế độ in `@media print`:
    * `@page { size: A4 portrait; margin: 8mm; }`
    * Bảo toàn bố cục 2 cột in ấn
    * Tự động căn tỷ lệ chữ và khoảng cách (pt, mm)
    * Áp dụng `break-inside: avoid; page-break-inside: avoid;` cho từng mục
    * Bật `print-color-adjust: exact`

- [ ] **Step 1: Viết bộ CSS hiện đại cho giao diện 2 cột CV**
- [ ] **Step 2: Viết khối CSS `@media print` tương thích cao cho PDF**
- [ ] **Step 3: Kiểm tra tính toàn vẹn cú pháp CSS và HTML**

---

### Task 4: Chạy Toàn Bộ Test Suite & Nghiệm Thu Trực Quan

**Files:**
- Test: `d:/TalentHub/bin/test-student-upgrades.php`

- [ ] **Step 1: Chạy test suite `bin/test-student-upgrades.php`**
  - Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" bin/test-student-upgrades.php`
  - Expected: All tests PASS.

- [ ] **Step 2: Kiểm tra DOM và render file HTML hoàn chỉnh**
- [ ] **Step 3: Commit các thay đổi vào git**
