# Thiết kế dashboard theo dõi tiến bộ học sinh

Ngày: 2026-08-28

## Mục tiêu

Tối ưu trang `app/learner/index.php` để học sinh tự theo dõi tiến bộ nhanh hơn, giảm chiều dài và mật độ thông tin, đồng thời bổ sung cách trình bày trực quan cho hồ sơ kỹ năng theo thang 100 và bản tóm tắt AI.

Thiết kế đã được người dùng duyệt qua mockup ngày 2026-08-28.

## Phạm vi

### Giữ nguyên

- Header hiện tại, gồm breadcrumb/đổi vai trò, tìm kiếm, chuông thông báo và tài khoản.
- Sidebar hiện tại, điều hướng, trạng thái active và thẻ cấp độ.
- Hero hiện tại: nền kem/cam nhạt, chuỗi ngày liên tiếp, lời chào, số giờ trải nghiệm, trạng thái hoàn thành đánh giá, hai CTA và minh họa học sinh bên phải.
- Font `Be Vietnam Pro` và design tokens hiện có trong `assets/css/home.css`.
- Luồng onboarding, phân quyền, nguồn dữ liệu database và các liên kết hiện có.

### Thay đổi

- Nội dung bên dưới hero trên dashboard.
- Cách trình bày KPI, kỹ năng, AI và hoạt động sắp diễn ra.
- Empty state của các khu vực này.
- Thứ tự ưu tiên và khoảng cách giữa các khối.

Không thay đổi schema database trong phạm vi này.

## Ngôn ngữ thị giác

- Font: `Be Vietnam Pro`, sans-serif.
- Primary: `#F97316`; primary hover: `#EA580C`; primary light: `#FFF7ED`.
- Secondary: `#2563EB`; secondary light: `#EFF6FF`.
- Accent/success: `#16A34A`.
- Background: `#F8FAFC`; surface: `#FFFFFF`.
- Text primary: `#0F172A`; text secondary: `#64748B`.
- Border: `#E2E8F0`; warning: `#F59E0B`; danger: `#DC2626`.
- Radius control: `8px`; radius card: `12px`.
- Card dùng viền 1px và shadow nhẹ. Không dùng viền nét đứt, glassmorphism, hồng/tím hoặc icon quá lớn.

## Bố cục desktop

Ngay dưới hero là ba tầng thông tin:

1. Ba KPI gọn trên một hàng.
2. Hồ sơ kỹ năng và AI tóm tắt trên một hàng, tỷ lệ khoảng 62/38.
3. Hoạt động sắp diễn ra dạng một card ngang.

Khối thành tích và chứng chỉ vẫn tồn tại bên dưới nhưng là nội dung thứ cấp, không tranh sự chú ý với kỹ năng và AI.

## KPI

Hiển thị ba card:

1. Điểm năng lực.
2. Giờ trải nghiệm đã xác thực.
3. Huy hiệu đã đạt.

Mỗi card có icon tile nhỏ, nhãn và số chính. Không hiển thị số tăng trưởng nếu không có dữ liệu lịch sử thật.

`Điểm năng lực` ưu tiên nguồn `student_profiles.talentScore` nếu cột và giá trị tồn tại. Nếu không có, dùng trung bình `levelScore` của các kỹ năng hợp lệ của học sinh. Nếu cả hai nguồn đều không có thì hiển thị `Chưa có dữ liệu`; không hardcode `92` trong chế độ database.

## Hồ sơ kỹ năng

Card hiển thị tối đa bốn kỹ năng, sắp xếp điểm giảm dần. Mỗi dòng gồm:

- Tên kỹ năng.
- Nhãn mức độ: `Rất tốt`, `Tốt`, `Trung bình` hoặc `Cơ bản`.
- Thanh tiến độ mảnh 8px.
- Điểm `/100` căn phải.
- Dấu xác thực nhỏ khi `verificationStatus = verified`.

Màu thanh tiến độ được chọn theo thứ tự để dễ phân biệt nhưng luôn dùng palette hiện tại: cam, xanh dương, xanh lá và vàng cảnh báo. Màu không mang ý nghĩa đạt/trượt.

Link `Xem tất cả` mở `profile.php`.

Nếu chưa có kỹ năng, card co chiều cao theo nội dung và hiển thị empty state nhỏ với CTA `Bắt đầu đánh giá`; không giữ khoảng trắng lớn và không dùng viền nét đứt.

## AI tóm tắt tiến độ

Card sử dụng dữ liệu `ai_capability_profile` đang có:

- Một câu tóm tắt tiến độ.
- Tối đa hai điểm mạnh.
- Tối đa hai điểm cần cải thiện.
- Một tín hiệu xu hướng nếu dữ liệu AI cung cấp.
- Link `Xem phân tích đầy đủ` tới `ai-recommendations.php` hoặc phần AI trong hồ sơ.

Card chủ yếu dùng nền trắng, chỉ có viền hoặc tint xanh dương rất nhẹ. Nội dung AI phải dựa trên dữ liệu và consent hiện tại; không tạo nhận định giả.

Nếu chưa có phân tích AI, giữ các trạng thái nghiệp vụ hiện tại: chưa đủ bài đánh giá, đủ dữ liệu để phân tích, dữ liệu tạm thời không tải được. Empty state phải ngắn, có CTA phù hợp và không làm card cao quá mức.

## Hoạt động sắp diễn ra

Thay hai card lớn `Hoạt động đang mở cho bạn` và `Hoạt động đã xác nhận` trên dashboard bằng một card `Hoạt động sắp diễn ra`.

- Hiển thị tối đa ba hoạt động theo thời gian bắt đầu tăng dần.
- Mỗi item có tên, ngày/giờ, địa điểm và link `Xem chi tiết` tới `activity-detail.php?id=<activity_id>`.
- Link `Tất cả hoạt động` mở `activities.php`.
- Lịch sử và hoạt động đã xác nhận vẫn truy cập được từ trang hoạt động/lịch sử, không cần chiếm một card riêng trên dashboard.

Nếu không có hoạt động, dùng empty state gọn với icon lịch, thông báo một dòng và CTA `Khám phá hoạt động`.

## Thành tích và chứng chỉ

Khối thành tích do trường cấp vẫn giữ dữ liệu và luồng hiện tại nhưng được đặt sau hoạt động. Phạm vi triển khai không thay đổi logic cấp huy hiệu/chứng chỉ và không ghi đè các chỉnh sửa card thành tích đang có trong worktree.

## Responsive

- Trên màn hình từ 1100px trở xuống: kỹ năng và AI xếp dọc; KPI vẫn ưu tiên ba cột nếu đủ chỗ.
- Trên màn hình từ 720px trở xuống: KPI xếp hai cột rồi một cột cuối; mọi card full-width; hoạt động xếp dọc.
- Dòng kỹ năng trên mobile gồm tên và điểm ở hàng đầu, thanh tiến độ ở hàng dưới.
- Header, sidebar mobile và hero tiếp tục dùng hành vi responsive hiện tại.

## Luồng dữ liệu

1. `student-data.php` xây dựng KPI và danh sách kỹ năng từ Talent Passport/database.
2. `index.php` giới hạn bốn kỹ năng, ba hoạt động và render các trạng thái dữ liệu.
3. `learner.css` chỉ định layout, màu, spacing và responsive trong scope `.learner-page-overview` để không ảnh hưởng trang khác.
4. Các liên kết tiếp tục dùng route hiện tại; không thêm API mới.

## Khả năng truy cập

- Thanh kỹ năng giữ `role=progressbar`, `aria-valuemin`, `aria-valuemax` và `aria-valuenow`.
- Icon trang trí có `aria-hidden`; link và CTA có tên rõ ràng.
- Màu không phải tín hiệu duy nhất: mọi điểm đều có số và mọi trạng thái đều có nhãn chữ.
- Tương phản chữ và focus state dùng token hiện tại.

## Kiểm thử

- Cập nhật hoặc bổ sung UI test kiểm tra ba KPI, bốn dòng kỹ năng, điểm `/100`, card AI và tối đa ba hoạt động.
- Kiểm tra cả database mode có dữ liệu và trạng thái rỗng.
- Chạy PHP syntax check cho các file PHP thay đổi khi runtime PHP khả dụng.
- Chạy test dashboard PowerShell hiện có.
- Render hoặc chụp dashboard ở desktop và mobile để kiểm tra chồng lấn, chiều cao card và khoảng trắng.

## Tiêu chí hoàn thành

- Header và hero hiện tại không thay đổi markup, nội dung hoặc giao diện.
- Dưới hero chỉ còn ba KPI chính, khu kỹ năng/AI và một khu hoạt động sắp diễn ra.
- Hồ sơ kỹ năng hiển thị dữ liệu thật theo thang 100 và tối đa bốn dòng.
- Không còn empty state nét đứt hoặc card rỗng cao bất hợp lý.
- AI không hiển thị nhận định giả khi chưa có dữ liệu.
- Hoạt động liên kết đúng trang danh sách và trang chi tiết.
- Responsive không gây tràn ngang ở 1440px, 1024px, 768px và 390px.
- Không làm mất hoặc ghi đè các thay đổi không liên quan đang có trong worktree.
