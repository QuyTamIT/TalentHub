# Thiết kế mockup Dashboard Học sinh TalentHub

Ngày: 2026-08-25

## Mục tiêu

Thiết kế lại dashboard học sinh theo ngôn ngữ thị giác của hai ảnh tham chiếu do người dùng cung cấp, đồng thời giữ nguyên phạm vi module, kiểu dữ liệu và luồng tương tác đang tồn tại trong TalentHub. Mockup tập trung làm rõ ba nhóm nội dung đang khó đọc: kỹ năng, chứng chỉ và dự án.

## Phạm vi

- Một mockup desktop độ trung thực cao cho dashboard học sinh.
- Giữ sidebar và header để thể hiện đầy đủ luồng điều hướng hiện có.
- Trình bày hero chào mừng, KPI, hồ sơ kỹ năng, AI gợi ý, hoạt động, chứng chỉ và dự án.
- Không tạo module, số liệu nghiệp vụ hoặc trạng thái dữ liệu mới.
- Không thay đổi mã nguồn giao diện trong giai đoạn tạo mockup.

## Ngôn ngữ thị giác

- Font chính: `Be Vietnam Pro`, sans-serif.
- Màu chính: `#F97316`; hover: `#EA580C`; nền nhạt: `#FFF7ED`.
- Màu phụ: `#2563EB`; nền phụ: `#EFF6FF`.
- Accent và success: `#16A34A`.
- Nền trang: `#F8FAFC`; surface: `#FFFFFF`.
- Chữ chính: `#0F172A`; chữ phụ: `#64748B`.
- Viền: `#E2E8F0`.
- Warning: `#F59E0B`; danger: `#DC2626`.
- Bo góc nhỏ: `8px`; bo góc card: `12px`.
- Shadow nhẹ, viền rõ, khoảng trắng rộng; không dùng gradient hồng–tím của ảnh tham chiếu.
- Gradient chỉ dùng có kiểm soát ở hero, từ `#F97316` sang `#EA580C`, có thể điểm một vùng sáng `#FB923C`.

## Bố cục desktop

### Sidebar

Sidebar rộng khoảng 248–272px, chứa logo TalentHub, nhãn “Khu vực Học sinh” và các module hiện có:

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

Mục Tổng quan là trạng thái active với nền cam và chữ trắng. Cuối sidebar hiển thị cấp độ cùng tiến độ giờ trải nghiệm.

### Header

Header có điều hướng đổi vai trò, ô tìm kiếm “Tìm hoạt động, kỹ năng...”, thông báo và avatar. Các thành phần bám đúng luồng hiện có, không thêm hành động mới.

### Nội dung chính

1. Hero chào mừng: chuỗi ngày liên tiếp, tên học sinh, giờ trải nghiệm và hai CTA “Khám phá hoạt động”, “Làm test năng khiếu”.
2. Bốn KPI: điểm năng lực, huy hiệu, giờ trải nghiệm và xếp hạng lớp; lấy nhãn và giá trị từ dữ liệu dashboard hiện có.
3. Khu vực hai cột: hồ sơ kỹ năng ở cột lớn, AI gợi ý ở cột nhỏ.
4. Khu vực chứng chỉ và dự án: chứng chỉ ở cột trái, dự án ở cột phải; ưu tiên đọc nhanh và trạng thái xác minh.
5. Hoạt động: ba card hoạt động ở cuối trang, dùng nhãn, thời gian, địa điểm và CTA tương ứng với dữ liệu hiện có.

## Thành phần dữ liệu

### Kỹ năng

- Tối đa bốn kỹ năng trên dashboard.
- Mỗi dòng gồm tên, điểm `/100` và thanh tiến độ.
- Dùng cam cho kỹ năng nổi bật, xanh dương cho nhóm bổ trợ và xanh lá cho trạng thái tiến bộ tốt.
- Liên kết “Xem tất cả” dẫn tới Hồ sơ năng lực.

### Chứng chỉ

- Mỗi chứng chỉ có tên, tổ chức cấp, ngày hoặc năm cấp và badge “Đã xác minh” khi dữ liệu cho phép.
- Nút “Thêm chứng chỉ” giữ nguyên luồng mở modal hiện có.
- Trạng thái trống dùng thông báo “Chưa có chứng chỉ nào được ghi nhận.”

### Dự án

- Mỗi dự án có tên, mô tả ngắn, vai trò và trạng thái.
- Dùng badge màu từ hệ token hiện có; không suy diễn tiến độ hoặc thành viên khi database không cung cấp.
- Trạng thái trống dùng thông báo “Chưa có dự án nào được ghi nhận.”

### AI gợi ý

- Chỉ hiển thị kết quả khi dữ liệu đã xác minh và chính sách hiển thị AI cho phép.
- Nếu chưa có dữ liệu, hiển thị trạng thái trung tính và CTA xem trạng thái AI.
- Không hiển thị khuyến nghị giả trong chế độ database.

### Hoạt động

- Chế độ database dùng hoạt động đã xác nhận.
- Chế độ mock dùng hoạt động sắp diễn ra.
- Card gồm danh mục, tiêu đề, thời gian, địa điểm và liên kết xem chi tiết.

## Tương tác thể hiện trong mockup

- CTA hero dẫn tới Hoạt động và Khám phá năng khiếu.
- “Xem tất cả” của kỹ năng dẫn tới Hồ sơ năng lực.
- AI CTA dẫn tới trang AI hoặc phân tích năng khiếu tùy trạng thái dữ liệu.
- Chứng chỉ hỗ trợ thêm mới và xem trạng thái xác minh.
- Hoạt động hỗ trợ xem chi tiết; mockup không biểu diễn đăng ký trực tiếp nếu luồng hiện tại không yêu cầu.
- Header và sidebar giữ nguyên khả năng điều hướng giữa các module.

## Tiêu chí hoàn thành

- Mockup nhìn nhất quán với ảnh tham chiếu về nhịp bố cục, card lớn, khoảng trắng và phân cấp thông tin.
- Màu sắc và font khớp hoàn toàn với token người dùng cung cấp.
- Kỹ năng, chứng chỉ và dự án dễ quét, không còn cảm giác dày hoặc rời rạc.
- Mọi nhãn và hành động đều ánh xạ được về module hoặc dữ liệu đang có trong repository.
- Ảnh không chứa gradient hồng–tím, watermark, thương hiệu ngoài TalentHub hoặc nội dung không thuộc hệ thống.
