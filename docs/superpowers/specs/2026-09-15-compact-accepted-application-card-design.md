# Design Document: Hiển thị tinh gọn hồ sơ ứng tuyển đã được duyệt (Compact Accepted Application Card)

## 1. Tổng quan
- **Vấn đề**: Khi hồ sơ ứng tuyển của sinh viên/học viên đã đạt trạng thái "Đã nhận" (Trúng tuyển thực tập), việc tiếp tục hiển thị thanh tiến trình 4 bước (`learner-app-stepper`) kèm các hộp thông tin rời rạc tạo cảm giác cồng kềnh, chiếm nhiều diện tích dọc màn hình và không cần thiết vì toàn bộ các vòng xét duyệt đều đã kết thúc thành công.
- **Giải pháp**: Thay thế thanh tiến trình (`learner-app-stepper`) và khối ghi chú trạng thái (`learner-app-status-note`) bằng một khối banner chúc mừng tinh gọn (`learner-app-accepted-banner`), áp dụng màu xanh ngọc chủ đạo (emerald/green) hài hòa với dark mode của hệ thống.

## 2. Phạm vi thay đổi

### 2.1. Logic hiển thị (`app/learner/ecosystem.php`)
- Kiểm tra trạng thái hồ sơ: `$isAccepted = in_array($appStatus, ['accepted', 'hired'], true);`
- Nếu `$isAccepted === true`:
  - Ẩn khối thanh tiến trình `learner-app-stepper`.
  - Hiển thị khối `.learner-app-accepted-banner`.
  - Xác định mốc thời gian hoàn tất: Lấy thời gian hoàn tất từ bước cuối cùng của pipeline (bước "Kết quả tiếp nhận") hoặc fallback về `$app['updated_at_formatted']`.
  - Tích hợp lời nhắn ứng viên (`$app['message']`) vào bên trong hoặc ngay dưới banner với kiểu dáng tối giản (dòng chữ nhỏ, thanh lịch), không tạo khung viền rời rạc.
  - Không hiển thị hộp `.learner-app-status-note` trùng lặp bên dưới nữa.
- Nếu `$isAccepted === false`:
  - Giữ nguyên hiển thị thanh tiến trình stepper và status note cho các trạng thái khác (`submitted`, `reviewing`, `interview`, `declined`, `withdrawn`).

### 2.2. Giao diện & CSS (`assets/css/learner.css`)
- Định nghĩa class `.learner-app-accepted-banner`:
  - `display: flex; align-items: flex-start; gap: 1rem;`
  - `padding: 1rem 1.25rem;`
  - `background: rgba(16, 185, 129, 0.08);`
  - `border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 12px;`
- Icon tích xanh:
  - Icon container hình tròn hoặc icon trực tiếp (`learner_icon('check-circle', 22)`), màu `#34d399`.
- Tiêu đề & Nội dung:
  - Header: **Trúng tuyển & Được tiếp nhận** + nhãn ngày giờ hoàn tất.
  - Nội dung: *"Chúc mừng bạn đã trúng tuyển thực tập! Doanh nghiệp đã duyệt tiếp nhận hồ sơ và sẽ sớm liên hệ hướng dẫn nhận việc qua email hoặc số điện thoại."*
  - Lời nhắn: Text phụ gọn gàng `“<lời nhắn>”` với icon mail nhỏ nếu có lời nhắn.
- Tương thích Responsive:
  - Đảm bảo hiển thị đẹp mắt trên màn hình nhỏ và di động (flex wrap, cỡ chữ phù hợp).

## 3. Kiểm thử & Xác minh
- Kiểm tra hiển thị của thẻ có trạng thái `accepted` / `hired` trên trang `app/learner/ecosystem.php`.
- Đảm bảo các thẻ có trạng thái khác (`submitted`, `reviewing`, `interview`, v.v.) không bị ảnh hưởng và vẫn hiển thị stepper bình thường.
- Đảm bảo độ tương phản màu sắc và tính thẩm mỹ trên Dark Mode.
