# Thiết Kế Bố Cục CV Chuyên Nghiệp Cho Xuất PDF Hồ Sơ Năng Lực (Talent Passport)

**Ngày lập**: 2026-09-07  
**Trạng thái**: Đã phê duyệt  
**Mục tiêu**: Chuyển đổi toàn diện giao diện và định dạng in/xuất file PDF của trang Hộ chiếu Năng lực Số (`talent-passport.php`) sang bố cục CV chuyên nghiệp 2 cột chuẩn quốc tế, tận dụng đầy đủ dữ liệu thực của sinh viên trong hệ sinh thái TalentHub.

---

## 1. Bối cảnh & Vấn đề

- **Thực trạng**: Giao diện xuất PDF hiện tại (`app/learner/talent-passport.php`) có bố cục dạng thẻ dọc đơn cột, mang tính chất bảng kê hành chính / báo cáo điểm số thô thay vì một bản CV ấn tượng phục vụ nộp hồ sơ xin việc, thực tập hoặc học bổng.
- **Yêu cầu**: 
  - Tái cấu trúc thành giao diện CV hiện đại chuẩn mực tuyển dụng.
  - Phân bổ thông tin khoa học: Kỹ năng & điểm mạnh, Đề án dự án tham gia, Hoạt động ngoại khóa & thực tế, Chứng chỉ & huy hiệu, Kết quả 4 bài đánh giá (MBTI, Holland, DISC, Đa trí thông minh), Đánh giá của giảng viên hướng dẫn, Điểm năng lực tổng hợp và Mã QR xác thực số.
  - Tối ưu hóa tuyệt đối cho khổ in PDF A4 (`@media print`): Không rách khối nội dung, màu sắc chuẩn xác, typography trang trọng.

---

## 2. Kiến trúc Bố cục CV 2 Cột (Modern 2-Column CV)

### 2.1. Cấu trúc tổng thể
- **Tỷ lệ phân chia**:
  - Cột Trái (`.passport-cv-sidebar`): Chiếm ~34% chiều rộng (nền xám nhạt / slate tinh tế `#F8FAFC`, viền phải `#E2E8F0`).
  - Cột Phải (`.passport-cv-main`): Chiếm ~66% chiều rộng (nền trắng `#FFFFFF`, không gian thoáng đãng cho kinh nghiệm & dự án).
- **Màu sắc nhận diện**:
  - Primary / Header: Xanh Navy đậm (`#0F172A`, `#1E3A8A`, `#1D4ED8`).
  - Phụ trợ / Điểm nhấn: Xanh Emerald (`#059669`), Xanh Royal (`#2563EB`), Slate (`#475569`, `#64748B`).

---

### 2.2. Chi tiết Cột Trái (Sidebar ~34%)

1. **Thẻ Chân dung & Thông tin cơ bản**:
   - Ảnh đại diện kích thước chuẩn 88×88px bo góc hiện đại (`border-radius: 12px`). Nếu chưa có ảnh thì hiển thị Avatar viết tắt 2 chữ cái với dải màu gradient sang trọng.
   - Nhãn định danh: "Tài khoản Sinh viên Xác thực • TalentHub".
2. **Thông tin liên hệ (Contact Details)**:
   - Email sinh viên.
   - Số điện thoại liên lạc.
   - Địa chỉ / Khu vực cư trú.
   - Trường học & Lớp học hiện tại.
   - Tổng thời lượng trải nghiệm thực tế (`X giờ`).
3. **Đánh giá Năng lực Tổng hợp (Overall Competency)**:
   - Thẻ điểm số tổng hợp: Điểm số thang 100 (ví dụ: `85/100`), Xếp loại năng lực (ví dụ: `Xuất sắc`) và Tỷ lệ xếp hạng.
4. **Hồ sơ 4 Bài Đánh giá Tư duy & Hành vi (Psychometrics)**:
   - **MBTI**: Mã kiểu hình (ví dụ: `INTJ - Nhà chiến lược`) + tóm lược thế mạnh tư duy.
   - **Holland**: Mã nhóm nghề nghiệp (ví dụ: `ACR - Nghệ thuật & Kỹ thuật`) + định hướng nghề tương thích.
   - **DISC**: Phong cách giao tiếp & làm việc nhóm (ví dụ: `CDIS - Tuân thủ & Điềm tĩnh`).
   - **Đa trí thông minh (MI)**: Miền trí thông minh nổi bật nhất (ví dụ: `Logic - Không gian - Ngôn ngữ`).
5. **Kỹ năng Chuyên môn & Kỹ năng Mềm (Core Skills)**:
   - Phân định rõ 2 nhóm:
     * *Kỹ năng Chuyên môn/Kỹ thuật*: Lập trình Python, Phân tích dữ liệu, Machine Learning, IoT...
     * *Kỹ năng Mềm*: Giao tiếp & Thuyết trình, Kỹ năng làm việc nhóm, Tư duy phản biện...
   - Mỗi kỹ năng gồm: Tên kỹ năng, Điểm số thẩm định (`X/100`), Thanh tiến độ bo góc mảnh và huy hiệu tích xanh xác thực.
6. **Mã QR Xác thực Hồ sơ Trực tuyến (Digital QR Verification)**:
   - Mã QR kích thước nét, đặt ở cuối cột trái kèm chú thích: "Quét để xem hồ sơ trực tuyến đã ký số (Hiệu lực 30 ngày)".

---

### 2.3. Chi tiết Cột Phải (Main Content ~66%)

1. **Header Họ Tên & Mục tiêu Chuyên môn**:
   - Họ và tên sinh viên in hoa, kích thước lớn (font size 24-28pt, font-weight: 800, màu `#0F172A`).
   - Chức danh / Định hướng nghề nghiệp mục tiêu (`headline`).
   - Tóm tắt hồ sơ năng lực (Professional Summary): 2-3 câu giới thiệu định hướng, thế mạnh cá nhân và mục tiêu cống hiến.
2. **Đề án Đổi mới Sáng tạo & Dự án Thực tế (Featured Projects)**:
   - Hiển thị danh sách đề án với:
     * Tên dự án in đậm nổi bật.
     * Nhãn trạng thái (ví dụ: `Đang triển khai`, `Đã hoàn thành`).
     * Vai trò phụ trách (ví dụ: *Trưởng nhóm kỹ thuật*, *Lập trình viên AI*) & Lĩnh vực nghiên cứu.
     * Tóm tắt đóng góp kỹ thuật & kết quả đạt được.
     * **Huy hiệu Doanh nghiệp Bảo trợ**: Tên doanh nghiệp bảo trợ (ví dụ: `Bảo trợ bởi: Viettel Solutions`) kèm kinh phí nếu có.
3. **Hoạt động Trải nghiệm & Ngoại khóa (Practical Activities)**:
   - Trích xuất từ các hoạt động thực tế đã xác nhận (`confirmed_entries`):
     * Tên hoạt động (IoT Lab, AI Bootcamp, Startup Pitch...).
     * Phân loại lĩnh vực (Kỹ thuật, Sáng tạo, Kinh doanh, Cộng đồng).
     * Thời gian tham gia, địa điểm và **số giờ trải nghiệm được xác nhận**.
4. **Chứng chỉ & Huy hiệu Đã Thẩm định (Certifications & Badges)**:
   - Danh sách chứng chỉ chuyên môn: Tên chứng chỉ, Đơn vị cấp, Năm cấp, Mã tra cứu số (Credential ID).
   - Danh sách huy hiệu danh dự từ hệ sinh thái: *Thủ lĩnh trẻ, Đồng đội xuất sắc, Người khám phá...*
5. **Nhận xét Chứng thực của Giảng viên Hướng dẫn (Teacher Endorsement)**:
   - Khối trích dẫn (quote) viền chỉ xanh Navy:
     * Lời nhận xét chuyên môn về phẩm chất, năng lực và thái độ của sinh viên.
     * Ký danh Giảng viên, Học vị, Đơn vị/Bộ môn.
     * Dấu mộc điện tử chứng thực "Đã ghi nhận trên hệ thống TalentHub".

---

## 3. Quy chuẩn Kỹ thuật In ấn PDF (`@media print`)

- **Khổ in**: `@page { size: A4 portrait; margin: 8mm; }`.
- **Ngắt trang chống rách khối (`break-inside: avoid`)**: Bọc thuộc tính chống ngắt trang dở dang cho từng thẻ dự án, khối hoạt động, chứng chỉ, bài test và nhận xét.
- **Ẩn thành phần thừa khi in**: Ẩn triệt để thanh menu sidebar, header web, nút in/chia sẻ, breadcrumb.
- **Màu sắc in**: Bật `-webkit-print-color-adjust: exact` và `print-color-adjust: exact`.
- **Bảo toàn tỷ lệ 2 cột**: Đảm bảo trên bản in A4, cột trái và cột phải giữ đúng tỷ lệ 34% / 66%, không bị vỡ bố cục.

---

## 4. Kế hoạch Kiểm thử & Xác minh (Verification Plan)

1. Kiểm tra hiển thị trực quan trên giao diện Web (Desktop & Mobile Responsive).
2. Kiểm tra thao tác bấm nút "In / Xuất File PDF":
   - Tạo tự động mã QR xác thực 30 ngày.
   - Mở cửa sổ in của trình duyệt (`window.print()`).
   - Kiểm tra bản in preview: Khổ A4 sắc nét, đúng bố cục 2 cột, không bị cắt ngang khối thông tin.
3. Chạy test suite `bin/test-student-upgrades.php` để đảm bảo tương thích 100% các điều kiện kiểm thử của hệ thống.
