# Learner Journey Dashboard Mockup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tạo phiên bản PNG thứ hai của dashboard sinh viên TalentHub theo thiết kế “Tổng quan hành trình” và dữ liệu database hiện tại.

**Architecture:** Chỉnh lại mockup v1 bằng built-in image generator, giữ ngôn ngữ thị giác cam–xanh và phân cấp tổng thể nhưng thay nội dung không còn đúng với dashboard hiện tại. Kết quả là một ảnh độc lập trong workspace; không sửa PHP, CSS, JavaScript hoặc database.

**Tech Stack:** Built-in `image_gen`, local PNG inspection, PowerShell file verification.

## Global Constraints

- Font: `Be Vietnam Pro`, sans-serif.
- Primary `#F97316`, primary hover `#EA580C`, primary light `#FFF7ED`.
- Secondary `#2563EB`, secondary light `#EFF6FF`, success `#16A34A`.
- Background `#F8FAFC`, surface `#FFFFFF`, text primary `#0F172A`, text secondary `#64748B`, border `#E2E8F0`.
- Radius nhỏ `8px`, radius card `12px`.
- Không dùng hồng, tím, magenta, watermark hoặc dữ liệu không có nguồn trong hệ thống.
- Không hiển thị “Điểm năng lực 92” hoặc “Xếp hạng lớp #7”.
- Không sửa hoặc ghi đè `docs/mockups/learner-dashboard-desktop-v1.png`.
- Không chỉnh sửa mã nguồn sản phẩm.

---

### Task 1: Chuẩn hóa reference và nội dung

**Files:**
- Read: `docs/superpowers/specs/2026-08-26-learner-journey-dashboard-mockup-design.md`
- Reference edit target: `docs/mockups/learner-dashboard-desktop-v1.png`
- Read: `app/learner/index.php`
- Read: `app/learner/includes/student-data.php`
- Read: `app/learner/includes/school-credential-grid.php`

**Interfaces:**
- Consumes: Mockup v1 và spec đã duyệt.
- Produces: Prompt chỉnh ảnh khóa chính xác bố cục, nội dung và trạng thái dữ liệu.

- [x] **Step 1: Xác nhận ảnh v1 và spec tồn tại**

Run: `Test-Path docs/mockups/learner-dashboard-desktop-v1.png; Test-Path docs/superpowers/specs/2026-08-26-learner-journey-dashboard-mockup-design.md`

Expected: hai giá trị `True`.

- [x] **Step 2: Khóa nội dung giữ lại**

Giữ sidebar 10 module, hero cam, bốn KPI, khu kỹ năng, card AI, hệ thống card trắng và typography Be Vietnam Pro.

- [x] **Step 3: Khóa nội dung phải thay đổi**

Thay KPI thành “Cấp độ hiện tại”, “Huy hiệu đạt được”, “Giờ trải nghiệm”, “Hoạt động đã tham gia”; thay Chứng chỉ + Dự án bằng “Huy hiệu & chứng chỉ dành cho bạn”; thay hoạt động dạng text bằng card có ảnh; bổ sung “Hoạt động đã xác nhận”; đổi header sang vùng tài khoản đầy đủ; đổi AI sang trạng thái đã phân tích.

### Task 2: Tạo mockup v2

**Files:**
- Create: `docs/mockups/learner-journey-dashboard-desktop-v2.png`

**Interfaces:**
- Consumes: Prompt và edit target từ Task 1.
- Produces: Một PNG landscape hoàn chỉnh.

- [x] **Step 1: Gọi built-in image generator**

Dùng `docs/mockups/learner-dashboard-desktop-v1.png` làm edit target. Yêu cầu giữ hệ thống bố cục và nhận diện, thay chính xác các khu vực đã khóa ở Task 1, hiển thị đủ phần nội dung trong một ảnh full-page, chữ tiếng Việt dễ đọc.

- [x] **Step 2: Lưu file không phá hủy**

Copy output được chọn từ thư mục generated images sang `docs/mockups/learner-journey-dashboard-desktop-v2.png`. Nếu tên này đã tồn tại, dùng `learner-journey-dashboard-desktop-v3.png` thay vì ghi đè.

### Task 3: Kiểm tra và bàn giao

**Files:**
- Inspect: `docs/mockups/learner-journey-dashboard-desktop-v2.png`

**Interfaces:**
- Consumes: PNG từ Task 2.
- Produces: Ảnh đã xác minh và đường dẫn bàn giao.

- [x] **Step 1: Kiểm tra cấu trúc**

Xác nhận ảnh có sidebar, header tài khoản, hero, bốn KPI database, kỹ năng, AI đã phân tích, ba card thành tích trường, hoạt động đang mở và hoạt động đã xác nhận.

- [x] **Step 2: Kiểm tra nội dung loại bỏ**

Xác nhận không còn “Điểm năng lực”, “Xếp hạng lớp”, khối “Dự án đã tham gia” hoặc danh sách chứng chỉ cá nhân của mockup cũ.

- [x] **Step 3: Kiểm tra file**

Run: PowerShell mở PNG bằng `System.Drawing.Image`, xác nhận chiều rộng và chiều cao lớn hơn `1000`, file lớn hơn `100000` byte, rồi in SHA-256.

Expected: ảnh hợp lệ, landscape, không rỗng và có hash.

- [x] **Step 4: Bàn giao**

Hiển thị ảnh trong phản hồi, cung cấp liên kết workspace, prompt cuối và xác nhận ảnh được tạo bằng built-in image generator.
