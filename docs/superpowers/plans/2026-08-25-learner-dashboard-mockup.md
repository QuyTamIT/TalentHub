# Learner Dashboard Mockup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tạo một ảnh mockup desktop độ trung thực cao cho dashboard học sinh TalentHub, bám bố cục ảnh tham chiếu nhưng dùng đúng token màu, font, module và kiểu dữ liệu hiện có.

**Architecture:** Đây là một deliverable hình ảnh độc lập, không chỉnh sửa mã nguồn sản phẩm. Hai ảnh người dùng cung cấp đóng vai trò reference về phân cấp, khoảng trắng và cấu trúc card; repository và spec đã duyệt đóng vai trò nguồn sự thật cho nội dung, điều hướng và trạng thái dữ liệu.

**Tech Stack:** Built-in `image_gen`, local image inspection, PNG output.

## Global Constraints

- Font chính: `Be Vietnam Pro`, sans-serif.
- Màu chính: `#F97316`; hover: `#EA580C`; nền nhạt: `#FFF7ED`.
- Màu phụ: `#2563EB`; nền phụ: `#EFF6FF`.
- Accent và success: `#16A34A`.
- Nền trang: `#F8FAFC`; surface: `#FFFFFF`; viền: `#E2E8F0`.
- Chữ chính: `#0F172A`; chữ phụ: `#64748B`.
- Bán kính card: `12px`; bán kính thành phần nhỏ: `8px`.
- Không dùng gradient hồng–tím, watermark hoặc module không tồn tại.
- Không sửa mã nguồn PHP, CSS, JavaScript hoặc database trong giai đoạn mockup.

---

### Task 1: Chuẩn hóa prompt và reference

**Files:**
- Read: `docs/superpowers/specs/2026-08-25-learner-dashboard-mockup-design.md`
- Reference: `C:/Users/CHINGU~1/AppData/Local/Temp/codex-clipboard-d0733723-fabc-4184-b50e-f5ad50fe51fe.png`
- Reference: `C:/Users/CHINGU~1/AppData/Local/Temp/codex-clipboard-af636614-dca7-45ed-84d5-185b48232cb1.png`

**Interfaces:**
- Consumes: Hai ảnh reference và đặc tả thiết kế đã duyệt.
- Produces: Một prompt `ui-mockup` duy nhất mô tả chính xác bố cục, nội dung và constraints.

- [x] **Step 1: Xác nhận hai ảnh reference có thể đọc được**

Mở cả hai ảnh ở độ phân giải gốc và kiểm tra chúng thể hiện hero, KPI, kỹ năng, AI và hoạt động.

- [x] **Step 2: Khóa nội dung mockup**

Prompt phải chứa các nhãn: “Tổng quan”, “Hồ sơ năng lực”, “Khám phá năng khiếu”, “Hoạt động”, “Check-in QR”, “Đánh giá”, “AI gợi ý”, “Hệ sinh thái & Cơ hội”, “Huy hiệu”, “Thống kê”, “Hồ sơ kỹ năng”, “Chứng chỉ”, “Dự án đã tham gia”.

- [x] **Step 3: Khóa dữ liệu minh họa**

Dùng tên “Nguyễn Văn A”, các KPI `92`, `12`, `64h`, `#7`; kỹ năng `IoT 85/100`, `Lập trình 90/100`, `Làm việc nhóm 88/100`, `Thuyết trình 72/100`. Chứng chỉ và dự án chỉ dùng trường dữ liệu mà repository hiện có: tên, tổ chức cấp, ngày/năm, xác minh; tên dự án, mô tả, vai trò, trạng thái.

### Task 2: Tạo ảnh mockup desktop

**Files:**
- Create: `docs/mockups/learner-dashboard-desktop-v1.png`

**Interfaces:**
- Consumes: Prompt và reference từ Task 1.
- Produces: Ảnh mockup PNG landscape độ trung thực cao.

- [x] **Step 1: Gọi built-in image generator**

Tạo một dashboard desktop landscape, dùng hai ảnh làm reference về bố cục. Yêu cầu sidebar 10 module, header, hero cam, 4 KPI, kỹ năng + AI, chứng chỉ + dự án, và hoạt động. Yêu cầu chữ tiếng Việt rõ, đúng dấu, không watermark.

- [x] **Step 2: Lưu output vào workspace**

Sao chép ảnh được chọn từ thư mục output mặc định của built-in generator sang `docs/mockups/learner-dashboard-desktop-v1.png`; không ghi đè file khác.

### Task 3: Kiểm tra và bàn giao

**Files:**
- Inspect: `docs/mockups/learner-dashboard-desktop-v1.png`

**Interfaces:**
- Consumes: Ảnh PNG từ Task 2.
- Produces: Mockup đã kiểm tra và đường dẫn bàn giao.

- [x] **Step 1: Kiểm tra bố cục**

Xác nhận ảnh có sidebar, header, hero, KPI, kỹ năng, AI, chứng chỉ, dự án và hoạt động; không có vùng bị cắt hoặc chồng lấn.

- [x] **Step 2: Kiểm tra nhận diện**

Xác nhận nền `#F8FAFC`, card trắng, hero cam, action phụ xanh, font mang đặc trưng Be Vietnam Pro, radius 8–12px và không có gradient hồng–tím.

- [x] **Step 3: Kiểm tra nội dung**

Xác nhận các nhãn chính đúng tiếng Việt và mọi module đều thuộc TalentHub hiện có. Nếu lỗi chữ nhỏ không ảnh hưởng cấu trúc, ghi rõ đó là giới hạn của ảnh AI; nếu lỗi nội dung chính, tạo lại một lần với prompt sửa đúng phần đó.

- [x] **Step 4: Bàn giao**

Hiển thị ảnh trực tiếp trong phản hồi và cung cấp liên kết đến `docs/mockups/learner-dashboard-desktop-v1.png` cùng prompt cuối và thông tin đã dùng built-in image generator.
