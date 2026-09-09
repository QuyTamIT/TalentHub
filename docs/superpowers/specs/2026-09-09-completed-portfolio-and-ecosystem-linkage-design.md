# Đặc Tả Thiết Kế: Thẻ Dự Án & Thực Tập Đã Hoàn Thành và Liên Kết Hệ Sinh Thái

- **Ngày tạo:** 2026-09-09
- **Trạng thái:** Đã thống nhất thiết kế (Approved)
- **Tác giả:** Antigravity (Google DeepMind) & User

---

## 1. Mục Tiêu & Bối Cảnh (Context & Goals)

Trong màn hình Hồ sơ năng lực của học sinh (`app/learner/profile.php`):
1. **Lọc trạng thái hiển thị:**
   - Phần **"Dự án đã tham gia"** và phần **"Dự án và quá trình thực tập"** trước đây hiển thị cả những dự án và kỳ thực tập đang dang dở (`in_progress`, `active`, hoặc chưa có nghiệm thu).
   - Mục tiêu: Chỉ hiển thị các **dự án đã hoàn thành** và các **vị trí thực tập đã hoàn thành / đã được xác nhận nghiệm thu**. Tuyệt đối không hiển thị các dự án / vị trí thực tập đang diễn ra.
2. **Tối ưu giao diện dạng Thẻ gọn (Compact Card):**
   - Thay thế việc bung form nhập báo cáo lộ thiên dài dòng bằng các Thẻ tóm tắt (Card) trực quan, gọn gàng theo chuẩn Design System TalentHub.
   - Khi người dùng bấm **"Xem chi tiết"**, hệ thống mở Modal Popup hiển thị đầy đủ thông tin: chi tiết đóng góp, minh chứng, số giờ, nhận xét của Mentor/Giảng viên và kỹ năng được cấp.
3. **Móc nối 2 chiều với Phân hệ Hệ sinh thái & Dự án (`ecosystem.php`):**
   - Thẻ Quá trình thực tập đã hoàn thành móc nối với bộ lọc Doanh nghiệp / Vị trí ứng tuyển đã hoàn thành (`ecosystem.php?tab=enterprises&filter=completed`) và trang Doanh nghiệp đối tác (`partner.php`).
   - Thẻ Dự án đã hoàn thành móc nối với bộ lọc Dự án đã hoàn thành (`ecosystem.php?tab=opportunities&filter=completed`) và trang chi tiết Dự án (`project.php?id=...`).

---

## 2. Logic Lọc Dữ Liệu Chi Tiết (Data Filtering Logic)

### 2.1. Khối "Dự án đã hoàn thành" (`profile.php`)
- **Bộ lọc trạng thái:**
  - Database mode: Truy vấn hoặc lọc từ mảng `$projects` sao cho `p.status` thuộc danh sách hoàn thành (`'completed'`, `'đã hoàn thành'`).
  - Mock mode: Trong `student-data.php`, chỉ giữ lại các dự án có trạng thái đã hoàn thành (ví dụ: `Smart Garden IoT` với status `'Đã hoàn thành'`; loại bỏ các dự án `in_progress` như `EduTalent Hackathon 2025` khỏi khối này).
- **Tiêu đề khối:** Cập nhật thành **"Dự án đã hoàn thành"**.
- **Empty state:** Nếu sinh viên chưa có dự án nào hoàn thành, hiển thị thông báo nhẹ nhàng: *"Chưa có dự án nào được ghi nhận hoàn thành. Hãy tiếp tục triển khai các dự án đang tham gia để hoàn thành hồ sơ."*

### 2.2. Khối "Quá trình thực tập đã hoàn thành" (`portfolio-panel.php` / `learner-portfolio.js` / API)
- **Tiêu đề khối:** Cập nhật thành **"Quá trình thực tập đã hoàn thành"**.
- **Bộ lọc dữ liệu:**
  - Đối với Thực tập (`kind = 'internship'`): Chỉ lấy các vị trí thực tập mà:
    - Báo cáo giai đoạn là hoàn thành: `stage = 'completed'`.
    - Trạng thái báo cáo đã được giảng viên duyệt: `status = 'verified'` (hoặc đơn thực tập đã hoàn tất).
    - Ẩn toàn bộ các vị trí thực tập đang diễn ra (`stage = 'active'`) hoặc chưa hoàn thành.
  - Đối với Dự án trong panel (`kind = 'project'`): Chỉ lấy các báo cáo dự án đã được nghiệm thu (`status = 'verified'`).
- **Nộp báo cáo:**
  - Màn hình Hồ sơ năng lực đóng vai trò là "Showcase năng lực đã hoàn thành".
  - Việc nộp báo cáo cho các dự án đang triển khai được thực hiện tại trang chi tiết của chính dự án đó (`project.php?id=...`), nơi `portfolio-panel.php` được gọi với `$portfolioProjectId` cụ thể.

---

## 3. Thiết Kế Giao Diện (UI / UX Components)

### 3.1. Thẻ Gọn Gàng (Compact Card Grid)
- Sử dụng layout CSS Grid 2 cột responsive (chuyển sang 1 cột trên mobile).
- **Thẻ Dự án đã hoàn thành:**
  - Tiêu đề dự án (font 1rem, bold, màu `#0F172A`).
  - Badge doanh nghiệp bảo trợ (nếu có): icon khiên bảo trợ, nền xanh tím `#EEF2FF`, chữ `#4338CA`.
  - Đoạn tóm tắt nội dung 1-2 dòng (line-clamp, màu `#64748B`).
  - Footer thẻ:
    - Badge vai trò: `Trưởng nhóm` / `Lập trình viên` / `Thành viên` (nền xám `#F1F5F9`).
    - Badge trạng thái: `● Đã hoàn thành` (nền `#DCFCE7`, chữ `#15803D`, viền `#86EFAC`).
  - Hàng nút bấm hành động:
    - Nút **"Xem chi tiết"** (style outline cam `#EA580C`, icon mắt hoặc xem).
    - Nút icon **"Mở dự án"** (link chuyển tới `project.php?id=...` hoặc tab Hệ sinh thái).
- **Thẻ Quá trình thực tập đã hoàn thành:**
  - Vị trí thực tập (font 1rem, bold, màu `#0F172A`).
  - Tên Doanh nghiệp đối tác kèm icon tòa nhà.
  - Thông tin tóm tắt: Thời gian thực tập (`startDate → endDate`), Tổng số giờ hoàn thành (`... giờ thực tế`), Tên Mentor/Giảng viên hướng dẫn.
  - Footer thẻ:
    - Badge trạng thái: `● Đã xác nhận hoàn thành` (nền `#DCFCE7`, chữ `#15803D`).
  - Hàng nút bấm hành động:
    - Nút **"Xem chi tiết"** (mở Modal chi tiết).
    - Nút icon **"Xem doanh nghiệp"** (link chuyển tới `partner.php?type=enterprise&id=...`).

### 3.2. Modal Popup Chi Tiết
- Sử dụng cấu trúc modal chuẩn của TalentHub (`.learner-modal`, backdrop mờ, phím `ESC` đóng, click outside đóng, bẫy focus bàn phím).
- **Modal Chi Tiết Dự Án:**
  - Header: Tên dự án, lĩnh vực, đơn vị bảo trợ / trường.
  - Thông tin thành viên & vai trò, thời gian bắt đầu - kết thúc.
  - Đóng góp cụ thể của sinh viên trong dự án.
  - Minh chứng sản phẩm: Link kho mã nguồn (GitHub/GitLab) và Link Demo (nếu có).
  - Kỹ năng được ghi nhận qua dự án.
  - Footer action: Nút đóng và nút **"Xem trong Hệ sinh thái"** (chuyển tới `ecosystem.php?tab=opportunities&filter=completed`).
- **Modal Chi Tiết Quá Trình Thực Tập:**
  - Header: Vị trí thực tập, tên doanh nghiệp tiếp nhận, giảng viên hướng dẫn.
  - Thời gian thực tập chính thức & Tổng số giờ thực tế được xác nhận.
  - Nội dung báo cáo kết quả thực tập & link minh chứng sản phẩm.
  - **Đánh giá & Nhận xét chính thức của Mentor**.
  - **Kỹ năng nghề nghiệp đã được giảng viên chứng thực**.
  - Lịch sử phê duyệt (`history`).
  - Footer action: Nút đóng và nút **"Xem Doanh nghiệp trong Hệ sinh thái"** (chuyển tới `ecosystem.php?tab=enterprises&filter=completed`).

---

## 4. Móc Nối 2 Chiều Với Hệ Sinh Thái & Dự Án (`ecosystem.php`)

### 4.1. Từ Hồ sơ năng lực ➔ Hệ sinh thái:
- Nút liên kết từ thẻ hoặc modal Dự án đã hoàn thành:
  - Link 1: Chi tiết dự án cụ thể: `project.php?id={id}`
  - Link 2: Hệ sinh thái lọc dự án đã hoàn thành: `ecosystem.php?tab=opportunities&filter=completed`
- Nút liên kết từ thẻ hoặc modal Thực tập đã hoàn thành:
  - Link 1: Chi tiết doanh nghiệp: `partner.php?type=enterprise&id={enterpriseId}`
  - Link 2: Hệ sinh thái lọc doanh nghiệp/vị trí đã hoàn thành: `ecosystem.php?tab=enterprises&filter=completed`

### 4.2. Từ Hệ sinh thái ➔ Khớp với Hồ sơ năng lực:
- Tại `ecosystem.php`:
  - Khi người dùng chọn bộ lọc **"Đã hoàn thành"** (`filter=completed`):
    - Tab `enterprises`: Lọc danh sách hiển thị các doanh nghiệp mà sinh viên có đơn thực tập đã hoàn thành (hoặc danh sách đơn trúng tuyển/hoàn thành trong `learner-applications-tracker`).
    - Tab `opportunities`: Lọc danh sách hiển thị các dự án đã hoàn thành (`status = 'completed'` / `membership_status = 'completed'`).
  - Hỗ trợ nạp trạng thái bộ lọc từ Query parameter (`?tab=...&filter=completed`) khi tải trang, tự động kích hoạt thẻ tab và dropdown filter tương ứng.

---

## 5. Kế Hoạch Kiểm Thử & Xác Minh (Verification Plan)

1. **Kiểm thử logic lọc (Backend & Repositories):**
   - Viết test PHP kiểm tra repository và controller chỉ trả về các dự án có status `completed` và thực tập có stage `completed` / status `verified` trên trang hồ sơ năng lực.
   - Đảm bảo dự án `in_progress` hoặc thực tập `active` không xuất hiện trên `profile.php`.
2. **Kiểm thử tương tác giao diện (Browser / JS Tests):**
   - Kiểm tra hiển thị dạng thẻ gọn (không có form dài chiếm chỗ).
   - Kiểm tra click "Xem chi tiết" mở đúng Modal với đầy đủ thông tin minh chứng, mentor, kỹ năng.
   - Kiểm tra đóng modal bằng nút X, click backdrop và phím Escape.
   - Kiểm tra click link điều hướng sang `ecosystem.php` với tham số `tab` và `filter=completed` hoạt động chính xác.
