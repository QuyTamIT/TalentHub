# Thiết kế frontend khu vực Học sinh/Sinh viên

**Ngày:** 2026-08-12

**Nhánh:** `feature/student`

**Phạm vi:** Ba trang đầu tiên của khu vực Học sinh/Sinh viên, dùng PHP, HTML, CSS và JavaScript thuần.

## 1. Mục tiêu

Xây dựng ba trang Learner bám sát các mockup đã duyệt, dùng chung một dashboard shell, tách dữ liệu mẫu khỏi markup, đáp ứng tốt trên desktop, tablet và điện thoại, đồng thời không làm thay đổi hoặc gây xung đột với khu vực Enterprise đang có.

Các URL chuẩn:

- `/app/learner/index.php`: Tổng quan.
- `/app/learner/profile.php`: Hồ sơ năng lực.
- `/app/learner/discover.php`: Khám phá năng khiếu.

Tên thư mục `app/learner` được chọn thay cho `app/student` để thống nhất với route `/app/learner` đã có trong trang chọn vai trò.

## 2. Căn cứ thiết kế

Ba mockup chính là nguồn đối chiếu bố cục và phân cấp nội dung:

- `design/student-mockups/01-tong-quan.png`
- `design/student-mockups/02-ho-so-nang-luc.png`
- `design/student-mockups/03-kham-pha-nang-khieu.png`

Ba ảnh gốc trong `D:\Personal_Project\FtalenHub\mockup` chỉ dùng để hiểu thêm ý đồ thiết kế. Khi ảnh gốc khác với mockup chính, mockup chính và bộ design token trong yêu cầu được ưu tiên. Vì vậy phiên bản triển khai không sử dụng gradient hồng–tím của ảnh gốc.

## 3. Phương án kiến trúc

Module Learner được triển khai độc lập theo cùng tinh thần tổ chức của `app/enterprise`, nhưng không dùng chung selector hoặc JavaScript với Enterprise.

### 3.1. Cấu trúc file

```text
app/learner/
├── index.php
├── profile.php
├── discover.php
└── includes/
    ├── header.php
    ├── sidebar.php
    ├── student-data.php
    └── icons.php                 # Chỉ thêm nếu giúp tránh lặp SVG đáng kể

assets/css/learner.css
assets/js/learner.js
tests/learner_frontend_test.php
```

Hai file hiện có được sửa ở phạm vi tối thiểu:

- `role-selection.php`: giữ route Học sinh/Sinh viên là `/app/learner`.
- `assets/js/role-selection.js`: công nhận `/app/learner` là module đã triển khai và điều hướng thật.

Không sửa `app/enterprise`, `assets/css/enterprise.css` hoặc `assets/js/enterprise.js`.

### 3.2. Quy tắc tránh xung đột

- `assets/css/home.css` tiếp tục là nguồn design token và reset dùng chung; `learner.css` chỉ chứa style theo module.
- Mọi selector riêng dùng tiền tố `learner-`; trạng thái dùng các tên có phạm vi như `.learner-sidebar.is-open`.
- Mọi ID JavaScript riêng dùng tiền tố `learner-`.
- JavaScript được bọc trong phạm vi khởi tạo của module; chỉ API thực sự cần thiết mới được gắn vào `window`.
- Dữ liệu Learner chỉ nằm trong `student-data.php`; không thêm biến toàn cục vào các trang công khai hoặc Enterprise.
- Các file PHP dùng `__DIR__` cho `require_once` và `include`, tránh phụ thuộc working directory.
- Output động được escape bằng helper dựa trên `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Không thay đổi class `.btn`, `.container` hoặc các component dùng chung trong `home.css`; module Learner dùng biến thể `.learner-btn` để không ảnh hưởng trang khác.

## 4. Dữ liệu và luồng render

`student-data.php` cung cấp các mảng dữ liệu thuần PHP:

- Thông tin người học: họ tên, chữ cái đại diện, lớp, trường, email, địa điểm, trạng thái xác minh.
- Cấp độ hiện tại: tên cấp, điểm tiến độ và ngưỡng kế tiếp.
- Danh sách sidebar và route dự kiến.
- KPI tổng quan và KPI hồ sơ.
- Kỹ năng, điểm số, nhãn đánh giá và màu semantic.
- Hoạt động sắp diễn ra.
- Chứng chỉ và dự án.
- Bài đánh giá năng khiếu, trạng thái và CTA.
- Điểm radar và kết quả định hướng tổng hợp.

Mỗi trang thực hiện cùng một luồng:

1. Nạp `student-data.php` bằng `require_once`.
2. Đặt `$pageTitle` và `$currentRoute`.
3. Render layout gồm sidebar, header và main content.
4. Nạp `home.css`, `learner.css` và `learner.js` bằng đường dẫn tương đối ổn định.

Không có truy vấn database hoặc gọi API trong giai đoạn này. Cấu trúc mảng được đặt tên rõ để có thể thay thế bằng repository/service sau này mà không phải viết lại markup.

## 5. Dashboard shell dùng chung

### 5.1. Sidebar

Sidebar cố định bên trái trên desktop, gồm logo, nhãn “Khu vực Học sinh”, chín mục điều hướng và card cấp độ ở cuối. Mục hiện tại có nền cam nhạt hoặc cam đặc theo mockup, chữ/icon tương phản và `aria-current="page"`.

Ba route đã triển khai điều hướng thật. Sáu route dự kiến dùng đường dẫn có nghĩa và thuộc tính `data-pending-route`; JavaScript ngăn navigation rồi hiển thị toast tiếng Việt, nhờ đó người dùng không rơi vào trang lỗi.

Trên tablet và điện thoại, sidebar là drawer ngoài màn hình, có backdrop, nút đóng/mở, cập nhật `aria-expanded`, khóa cuộn body và đóng bằng phím Escape.

### 5.2. Header

Header dùng chung gồm:

- Nút mở sidebar trên màn hình nhỏ.
- Liên kết “Đổi vai trò”.
- Ô tìm kiếm mock có label truy cập được.
- Nút thông báo chỉ có icon và `aria-label`.
- Avatar chữ “A”.

Tìm kiếm và thông báo chưa có backend nhưng phải tạo phản hồi toast rõ ràng, không để thao tác im lặng.

### 5.3. Component dùng chung

Các component được định nghĩa một lần trong `learner.css`: card bề mặt, nút chính/phụ, badge, progress bar, icon tile, KPI card, section header, modal, toast và card cấp độ. Chúng dùng design token hiện có, border 1px và shadow nhẹ, với transition ngắn cho hover/focus.

## 6. Trang Tổng quan

Trang Tổng quan bám bố cục mockup theo thứ tự:

1. Banner chào mừng có chuỗi 7 ngày, nội dung tiến độ, hai CTA và minh họa SVG nội tuyến theo palette cam–xanh–lục.
2. Bốn KPI: Điểm năng lực, Huy hiệu, Giờ trải nghiệm và Xếp hạng lớp.
3. Khối hai cột gồm Hồ sơ kỹ năng và AI gợi ý.
4. Danh sách ba hoạt động sắp diễn ra.

CTA “Khám phá hoạt động” trỏ route dự kiến và được xử lý bằng toast cho tới khi trang hoạt động tồn tại. CTA “Làm test năng khiếu” mở `discover.php`. “Xem tất cả” của kỹ năng mở `profile.php`; “Xem phân tích đầy đủ” mở `discover.php`.

Các nút đăng ký hoạt động cập nhật trạng thái ngay trên card thành “Đã đăng ký”, vô hiệu hóa lần bấm tiếp theo và phát thông báo qua vùng `aria-live`.

## 7. Trang Hồ sơ năng lực

Phần đầu trang là profile card gồm avatar, tên Nguyễn Văn A, lớp, trường, email, địa điểm, badge “Đã xác minh”, nút “Chia sẻ hồ sơ”, nút “Chỉnh sửa” và ba KPI.

Phần nội dung gồm:

- Card Kỹ năng hai cột trên desktop, mỗi kỹ năng có điểm, nhãn mức độ và progress bar.
- Card Chứng chỉ có tổ chức cấp, năm và trạng thái xác minh.
- Card Dự án đã tham gia gồm vai trò và trạng thái.

Nút “Chỉnh sửa” mở modal frontend chứa họ tên, lớp, trường, email và địa điểm. Submit thực hiện kiểm tra trường bắt buộc, cập nhật các vùng profile tương ứng trong DOM, đóng modal và hiện toast; dữ liệu chỉ tồn tại trong phiên trang.

Nút “Chia sẻ hồ sơ” mở modal chứa liên kết mẫu có thể chọn và nút “Sao chép”. Clipboard API được dùng khi có; fallback dùng selection và `document.execCommand('copy')`. Kết quả sao chép được thông báo bằng `aria-live`.

Modal dùng `role="dialog"`, `aria-modal="true"`, tiêu đề được liên kết bằng `aria-labelledby`, giữ focus trong dialog, đóng bằng nút, backdrop hoặc Escape, rồi trả focus về nút đã mở.

## 8. Trang Khám phá năng khiếu

Trang gồm bốn card đánh giá Holland, MBTI, DISC và Đa trí thông minh. Mỗi card đọc trạng thái từ dữ liệu và render một trong ba CTA:

- “Xem kết quả”: mở modal tóm tắt kết quả mẫu của bài test.
- “Tiếp tục”: hiện modal thông báo tiến độ và nút tiếp tục phiên mẫu.
- “Bắt đầu bài test”: mở modal giới thiệu bài test; khi xác nhận, card chuyển sang trạng thái đang thực hiện và CTA thành “Tiếp tục”.

Biểu đồ radar được dựng bằng SVG với sáu trục Logic, Sáng tạo, Vận động, Giao tiếp, Âm nhạc và Tự nhiên. Lưới, polygon dữ liệu, điểm và nhãn dùng token màu; SVG có `role="img"` và mô tả văn bản hỗ trợ accessibility.

Card kết quả tổng hợp hiển thị Kỹ thuật 40%, Kinh doanh 30%, Học thuật 20% và Nghệ thuật 10% bằng progress bar và màu semantic trong design system.

## 9. Responsive và accessibility

Desktop là bố cục chuẩn, với sidebar cố định và vùng nội dung rộng. Tablet giảm khoảng đệm, các grid bốn cột chuyển thành hai cột và sidebar thành drawer. Điện thoại dùng một cột, header thu gọn, nút có chiều rộng phù hợp và modal chiếm gần toàn bộ viewport.

Yêu cầu accessibility:

- Dùng semantic `header`, `nav`, `main`, `section`, `article`, `aside` và `button` đúng vai trò.
- Mọi nút chỉ có icon có `aria-label`.
- Focus-visible rõ bằng màu primary.
- Tương phản chữ và nền tuân theo palette token.
- Trạng thái động được báo qua `aria-live`.
- Tôn trọng `prefers-reduced-motion` để giảm transition.

## 10. Chiến lược kiểm thử

Phát triển tuân theo chu kỳ red–green–refactor. Một script kiểm thử PHP nhỏ render từng trang trong output buffer và kiểm tra:

- Ba trang render được mà không có PHP warning/fatal.
- Header và sidebar chung xuất hiện trên cả ba trang.
- Active route đúng theo từng trang.
- Dữ liệu tiếng Việt và các section bắt buộc xuất hiện.
- Chuỗi động có ký tự đặc biệt được escape.
- Các nút/modal có thuộc tính accessibility cần thiết.
- Route Learner trong trang chọn vai trò điều hướng tới module thật.

Xác minh cuối gồm:

- `php -l` trên toàn bộ file PHP mới hoặc sửa.
- Chạy script test PHP và xác nhận mọi assertion vượt qua.
- `node --check assets/js/learner.js` và file role-selection đã sửa nếu Node khả dụng.
- Khởi chạy PHP built-in server nếu PHP khả dụng.
- Mở ba trang và kiểm tra console JavaScript, navigation, drawer, modal, copy và các CTA.
- Chụp ở desktop, tablet và mobile; đối chiếu lần lượt với ba mockup chính rồi chỉnh cho tới khi không còn lỗi layout rõ ràng.

## 11. Tiêu chí chấp nhận

- Có đủ ba trang và các partial/data/assets đã thống nhất dưới tên `learner`.
- Giao diện tiếng Việt, dùng Be Vietnam Pro và đúng design token.
- Không có gradient hồng–tím hoặc màu tùy tiện ngoài mục đích minh họa semantic đã nêu.
- Không thay đổi giao diện hoặc hành vi của khu vực Enterprise.
- Dữ liệu mock tách khỏi markup và output động được escape.
- Sidebar, header, card cấp độ và component nền tảng dùng chung giữa ba trang.
- Các CTA đều điều hướng hoặc phản hồi frontend có nghĩa.
- Menu mobile, modal và thao tác bàn phím hoạt động.
- Không có lỗi PHP syntax, JavaScript syntax, console hoặc layout đã biết tại thời điểm bàn giao.
