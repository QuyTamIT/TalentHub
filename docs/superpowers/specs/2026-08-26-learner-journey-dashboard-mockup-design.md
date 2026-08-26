# Thiết kế mockup Dashboard “Tổng quan hành trình”

Ngày: 2026-08-26

## Mục tiêu

Tạo mockup desktop độ trung thực cao cho dashboard sinh viên TalentHub theo cấu trúc hiện tại của `app/learner/index.php`. Mockup phải phản ánh dữ liệu database và các trạng thái nghiệp vụ đang được hỗ trợ, không hiển thị số demo như dữ liệu thật.

## Nguyên tắc dữ liệu

- Chọn trạng thái người dùng đã hoàn thành đủ bốn bài đánh giá và đã có phân tích AI để thể hiện đầy đủ dashboard.
- Không hiển thị “Điểm năng lực 92” vì hệ thống chưa có công thức tổng hợp chính thức.
- Không hiển thị “Xếp hạng lớp #7” vì database chưa có nguồn xếp hạng lớp.
- Không đưa danh sách dự án hoặc chứng chỉ cá nhân tự khai báo lên dashboard; các nội dung này thuộc Hồ sơ năng lực.
- Các khu vực không có dữ liệu thật phải hỗ trợ empty state, không tạo card giả.

## Ngôn ngữ thị giác

- Font: `Be Vietnam Pro`, sans-serif.
- Primary: `#F97316`; primary hover: `#EA580C`; primary light: `#FFF7ED`.
- Secondary: `#2563EB`; secondary light: `#EFF6FF`.
- Accent và success: `#16A34A`.
- Background: `#F8FAFC`; surface: `#FFFFFF`.
- Text primary: `#0F172A`; text secondary: `#64748B`.
- Border: `#E2E8F0`; warning: `#F59E0B`; danger: `#DC2626`.
- Radius nhỏ `8px`, radius card `12px`.
- Viền mảnh, shadow nhẹ, khoảng trắng rộng; không dùng hồng, tím hoặc magenta.

## Bố cục

### Sidebar

Sidebar rộng khoảng 252px, có thương hiệu TalentHub, nhãn “Khu vực sinh viên”, 10 module hiện tại:

1. Tổng quan
2. Hồ sơ năng lực
3. Khám phá năng khiếu
4. Hoạt động
5. Check-in QR
6. Đánh giá
7. AI gợi ý
8. Hệ sinh thái & Cơ hội
9. Huy hiệu
10. Thống kê

Mục Tổng quan active màu cam. Cuối sidebar có thẻ cấp độ, thanh tiến độ tới cấp tiếp theo và nút Đăng xuất.

### Header

Header có ô tìm kiếm, thông báo và vùng tài khoản gồm avatar, tên sinh viên, nhãn “Tài khoản Sinh viên” và chevron menu. Không hiển thị nút “Đổi vai trò”.

### Hero

Hero cam hiển thị:

- Chuỗi ngày liên tiếp.
- “Chào mừng trở lại, Nguyễn Văn A”.
- Tổng giờ trải nghiệm xác thực.
- Trạng thái “Đã hoàn thành 4/4 bài đánh giá”.
- CTA “Khám phá hoạt động” và “Xem lộ trình AI”.
- Minh họa học tập tối giản ở phía phải, cùng hệ màu cam–xanh.

### KPI

Bốn KPI lấy từ dữ liệu database:

1. Cấp độ hiện tại.
2. Huy hiệu đạt được.
3. Giờ trải nghiệm đã xác nhận.
4. Hoạt động đã tham gia.

Mockup dùng giá trị minh họa có nhãn “dữ liệu xác thực”; không dùng chỉ số tăng trưởng nếu nguồn database không cung cấp.

### Hồ sơ kỹ năng

- Tối đa bốn kỹ năng từ Talent Passport.
- Mỗi dòng có tên, điểm `/100`, cấp độ và thanh tiến độ.
- Có link “Xem tất cả” tới Hồ sơ năng lực.
- Hỗ trợ empty state “Chưa có dữ liệu kỹ năng.”

### AI gợi ý

Mockup chính dùng trạng thái `analysis_completed = true`:

- Tiêu đề “Đã có lộ trình và thành tích phù hợp”.
- Nội dung giải thích AI đã đối chiếu bốn bài đánh giá với huy hiệu và chứng chỉ chính thức của trường.
- CTA “Xem gợi ý của AI”.

Thiết kế card phải đủ linh hoạt cho hai trạng thái còn lại: chưa đủ bốn bài và đủ dữ liệu nhưng chưa phân tích.

### Thành tích do trường cấp

Khối full-width “Huy hiệu & chứng chỉ dành cho bạn”, hiển thị ba card tiêu biểu. Mỗi card có:

- Loại “Huy hiệu trường” hoặc “Chứng chỉ trường”.
- Tên và mô tả.
- Tên trường cấp.
- Trạng thái “Đã đạt”, “Đủ điều kiện”, “AI gợi ý” hoặc “Chưa mở khóa”.
- Ngày ghi nhận hoặc lý do AI đề xuất.
- Tiến độ hoặc phần trăm phù hợp.

Mockup dùng ba item demo đang có trong repository:

- Hồ sơ năng lực hoàn chỉnh — Đã đạt — 100%.
- Nhà tư duy phân tích — AI gợi ý — 89% phù hợp.
- Thực hành dự án ứng dụng — AI gợi ý — 84% phù hợp, tiến độ 72%.

### Hoạt động đang mở

Ba card hoạt động gồm ảnh bìa 16:9, danh mục, tiêu đề, ngày bắt đầu, địa điểm và CTA “Xem chi tiết”. Có link “Tất cả hoạt động”.

### Hoạt động đã xác nhận

Một khu riêng bên dưới, dùng cùng cấu trúc card có ảnh. Tiêu đề “Hoạt động đã xác nhận”, link “Xem lịch sử”. Nếu không có dữ liệu, dùng empty state.

### Onboarding

Modal onboarding không xuất hiện trong mockup chính vì mockup chọn người dùng đã hoàn thành bốn bài đánh giá. Trạng thái tài khoản mới vẫn giữ nguyên trong sản phẩm hiện tại.

## Thứ tự ưu tiên thị giác

1. Hero và hành động tiếp theo.
2. KPI xác thực.
3. Kỹ năng và AI.
4. Thành tích do trường cấp.
5. Hoạt động đang mở.
6. Hoạt động đã xác nhận.

## Tiêu chí hoàn thành

- Ảnh landscape thể hiện đủ toàn bộ cấu trúc trên mà không chồng lấn hoặc cắt card.
- Nội dung tiếng Việt chính xác, dễ đọc và đúng trạng thái nghiệp vụ.
- Bốn KPI khớp database hiện tại.
- Không còn hai khối “Chứng chỉ” và “Dự án đã tham gia” của mockup cũ.
- Card thành tích có loại, trạng thái, trường cấp, lý do/ngày và tiến độ.
- Card hoạt động có ảnh bìa và tách rõ hoạt động đang mở với hoạt động đã xác nhận.
- Header, sidebar và account area khớp giao diện hiện tại.
- Không có hồng, tím, magenta, watermark hoặc module không tồn tại.
