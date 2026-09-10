# Thiết kế Hoàn thiện Quy trình Tạo Hoạt động & Tải lên Ảnh bìa cho Giảng viên

## 1. Tổng quan & Mục tiêu

### 1.1 Vấn đề hiện tại
1. **Thiếu tính năng tải ảnh bìa trực tiếp:** Form tạo hoạt động của giáo viên (`app/teacher/activities/index.php`) chỉ có ô text yêu cầu nhập đường dẫn cục bộ nội bộ (`assets/activities/...`). Không hỗ trợ upload ảnh từ máy tính, không có preview, và regex backend (`TeacherActivityService.php`) chặn hoàn toàn các URL ảnh ngoài.
2. **Ảnh bìa và các thông tin quan trọng bị giấu:** Trường "Ảnh bìa", "Mô tả đầy đủ", "Kỹ năng", "Điều kiện", v.v. bị đặt trong khối `<details>` (thu gọn/đóng) khiến giáo viên bỏ sót.
3. **Bẫy lỗi validation:** Khi tạo mới (`$creating = true`), backend bắt buộc phải có `description` và `summary`, đồng thời nếu các trường ngày đăng ký (`registrationOpensAt`, `registrationClosesAt`, `cancellationClosesAt`) để trống sẽ gây lỗi chặn lưu mà giáo viên không rõ nguyên nhân.
4. **Trải nghiệm học sinh:** Phía học sinh (`app/learner/activities.php` và `activity-detail.php`) hiển thị rất nhiều thông tin (ảnh bìa, mô tả chi tiết, kỹ năng, trải nghiệm, quyền lợi, giờ rèn luyện), nhưng nếu giáo viên không điền đủ do form bị ẩn thì trang học sinh sẽ bị khuyết dữ liệu hoặc chỉ hiện ảnh SVG minh họa mặc định.

### 1.2 Mục tiêu
- Cung cấp tính năng **tải ảnh bìa trực tiếp từ máy tính** (JPG, PNG, WebP) kèm bộ sưu tập ảnh mẫu có sẵn (Preset Gallery) và khung xem trước (Preview).
- Tái cấu trúc form tạo/chỉnh sửa hoạt động thành 4 khối trực quan, không dùng accordion thu gọn cho các trường cốt lõi.
- Tự động hóa các giá trị mặc định thông minh (mốc thời gian đăng ký tự tính theo ngày bắt đầu, giờ công nhận mặc định 2.0 giờ, tự sinh alt text cho ảnh bìa).
- Đảm bảo đồng bộ hiển thị chuẩn xác ở tất cả các trang của học sinh/sinh viên.

---

## 2. Kiến trúc & Thiết kế Kỹ thuật

### 2.1 Quản lý Lưu trữ & Upload Ảnh (`storage/activity-covers/`)
- **Thư mục lưu trữ:** `d:\TalentHub\storage\activity-covers` (được tạo tự động nếu chưa có, phân quyền `0775`).
- **Xác thực tệp tải lên:**
  - MIME types cho phép: `image/jpeg`, `image/png`, `image/webp`.
  - Dung lượng tối đa: 5MB (`5 * 1024 * 1024` bytes).
  - Kiểm tra tính hợp lệ bằng `mime_content_type` hoặc `getimagesize`.
  - Giới hạn kích thước ảnh tối đa 4096px x 4096px để tránh tấn công pixel flood.
- **Quy tắc đặt tên file:**
  - `cover-{teacherId_prefix8}-{random12}.{ext}` (ví dụ: `cover-10000000-a1b2c3d4e5f6.webp`).
  - Ghi file qua file tạm (`.tmp`) rồi `rename` nguyên tử để chống hỏng file khi upload dở dang.
- **Đường dẫn URL trả về:** `/storage/activity-covers/{filename}`.

### 2.2 Bộ sưu tập ảnh mẫu có sẵn (Preset Catalog)
- Cung cấp danh sách 15 ảnh cover chất lượng cao có sẵn trong `app/learner/assets/activities/covers/`:
  - **Kỹ thuật & Công nghệ:** `talenthub-python-workshop.webp`, `talenthub-stem-robotics.webp`, `fpt-ai-hacklab.webp`, `nguyen-trai-python-robot.webp`.
  - **Kinh doanh & Khởi nghiệp:** `nguyen-trai-young-business.webp`, `nguyen-trai-startup-debate.webp`, `talenthub-digital-marketing.webp`, `fpt-startup-demo-day.webp`, `fpt-product-sprint.webp`.
  - **Sáng tạo & Nghệ thuật:** `talenthub-creative-studio.webp`, `nguyen-trai-poster-design.webp`, `fpt-music-showcase.webp`.
  - **Cộng đồng & Môi trường:** `talenthub-green-school.webp`, `nguyen-trai-green-campus.webp`, `fpt-green-campus.webp`.
- Giao diện cho phép bấm chọn nhanh, ảnh sẽ được áp dụng ngay vào khung preview và lưu đường dẫn vào `coverImageUrl`.

### 2.3 Cập nhật Backend Validation (`TeacherActivityService.php`)
- Cập nhật phương thức `payload()`:
  - Cho phép `coverImageUrl` nhận:
    1. Đường dẫn upload: `/storage/activity-covers/[a-zA-Z0-9_\-\.]+\.(webp|png|jpe?g)`
    2. Đường dẫn preset: `(?:/app/learner/)?assets/activities/[a-zA-Z0-9_\-\./]+\.(?:webp|png|jpe?g|svg)`
  - Xử lý `coverImageAlt`: Nếu `coverImageUrl` có giá trị nhưng `coverImageAlt` để trống, hệ thống tự động gán: `"Ảnh minh họa cho hoạt động " . $title` (không ném ngoại lệ validation).
  - Xử lý `description`: Đưa ra giao diện chính kèm `required` để đảm bảo luôn có dữ liệu cho sinh viên đọc.

### 2.4 Cập nhật Tầng đọc dữ liệu Học sinh (`ActivityReadModel.php` & Helper)
- Cập nhật `ActivityReadModel::safeLocalActivityAsset($value)`:
  - Cho phép chấp nhận các đường dẫn bắt đầu bằng `/storage/activity-covers/` (hoặc `storage/activity-covers/`).
- Cập nhật hàm `learner_activity_cover_or_fallback($value, $fallback)`:
  - Nếu là `/storage/activity-covers/...` và file tồn tại trong thư mục storage dự án: trả về chính đường dẫn đó để trình duyệt tải từ gốc web server.
  - Nếu là `assets/activities/...`: trả về relative path như hiện tại.
  - Nếu không tồn tại: trả về `$fallback`.

---

## 3. Tái cấu trúc Giao diện Form Tạo/Chỉnh sửa Hoạt động

Form trong `app/teacher/activities/index.php` chuyển sang `enctype="multipart/form-data"` và tổ chức thành 4 khối thẻ rõ ràng:

### Khối 1: Thông tin cốt lõi & Ảnh bìa
- **Tên hoạt động (`title`)**: Text input, `required`, tối đa 255 ký tự.
- **Nhóm hoạt động (`categoryChoice`)**: Select dropdown (Kỹ thuật, Kinh doanh, Sáng tạo, Cộng đồng), `required`.
- **Khu vực Ảnh bìa (`coverImage`)**:
  - Khung xem trước (Preview Banner Box) hiển thị ảnh hiện tại hoặc placeholder.
  - Nút 📤 **"Tải ảnh từ máy tính"**: File input ẩn (`accept="image/png,image/jpeg,image/webp"`), JS preview tức thì khi người dùng chọn file.
  - Nút 🖼️ **"Chọn ảnh mẫu có sẵn"**: Mở modal / drawer chọn ảnh mẫu với 4 tab chủ đề.
  - Nút ❌ **"Xóa ảnh"** (khi đã có ảnh): Đưa về trạng thái chưa chọn ảnh.
  - Trường ẩn `coverImageUrl`: Lưu đường dẫn ảnh mẫu hoặc đường dẫn ảnh hiện tại (nếu là edit).
  - Ô nhập `coverImageAlt` (tùy chọn): Tự động điền theo tên hoạt động, có thể sửa.
- **Giới thiệu ngắn (`summary`)**: Textarea 2 dòng, `required`, tối đa 500 ký tự.
- **Mô tả đầy đủ (`description`)**: Textarea 5 dòng, `required`, hướng dẫn chi tiết nội dung hoạt động.
- **Sức chứa (`capacity`)**: Number input, min=1, mặc định 30.
- **Hình thức tổ chức (`deliveryMode`)**: Select dropdown (Trực tiếp, Trực tuyến, Kết hợp).
  - Khi Trực tiếp / Kết hợp: Hiện ô **Tên địa điểm / phòng** (`locationName`) và **Địa chỉ** (`locationAddress`).
  - Khi Trực tuyến / Kết hợp: Hiện ô **Đường dẫn trực tuyến** (`onlineMeetingUrl`).

### Khối 2: Lịch trình & Thiết lập đăng ký
- **Bắt đầu (`startAt`)** và **Kết thúc (`endAt`)**: Datetime-local inputs, `required`.
- **Tự động điền ngày giờ đăng ký thông minh**:
  - Khi người dùng chọn hoặc thay đổi `startAt`, nếu các ô đăng ký đang trống hoặc chưa sửa:
    - `registrationOpensAt` = Ngày giờ hiện tại.
    - `registrationClosesAt` = `startAt` trừ 2 tiếng.
    - `cancellationClosesAt` = `startAt` trừ 1 ngày (hoặc bằng giờ đóng đăng ký nếu sự kiện diễn ra gấp).
- **Cách duyệt đăng ký (`approvalMode`)**: Select dropdown ("Duyệt tự động" - mặc định, "Giáo viên duyệt").
- **Giờ trải nghiệm công nhận (`confirmedHours`)**: Number input, bước nhảy 0.01, mặc định điền sẵn **`2.00`** giờ (thay vì để trống).

### Khối 3: Nội dung trải nghiệm & Kỹ năng phát triển (Sinh viên xem)
Bố trí lưới 2 cột trực quan (thay vì giấu trong disclosure):
- 💡 **Nội dung trải nghiệm (`experienceHighlights`)**: Textarea (mỗi dòng một mục), placeholder ví dụ cụ thể.
- 🎯 **Kỹ năng phát triển (`skillTags`)**: Textarea (mỗi dòng một kỹ năng), placeholder ví dụ cụ thể.
- 📋 **Điều kiện tham gia (`eligibilityRules`)**: Textarea (mỗi dòng một điều kiện).
- 🎁 **Quyền lợi & cơ hội (`benefitItems`)**: Textarea (mỗi dòng một quyền lợi).

### Khối 4: Ban tổ chức, Chi phí & Chứng nhận
- **Đối tượng tham gia (`targetAudience`)**: Text input, gợi ý mặc định "Học sinh trong trường".
- **Đơn vị tổ chức (`organizerName`)**: Tự động điền tên trường của giáo viên.
- **Giáo viên phụ trách (`responsibleTeacherId`)**: Select dropdown giáo viên.
- **Đầu mối liên hệ / Email / Số điện thoại**: Các ô liên hệ tùy chọn.
- **Chi phí tham gia (`feeMode`, `feeAmount`, `currency`)**: Radio Miễn phí / Có phí + ô số tiền VND.
- **Chứng nhận sau hoạt động (`certificateLabel`)**: Text input, gợi ý mặc định "Minh chứng tham gia trên TalentHub".

---

## 4. Kế hoạch Kiểm thử & Xác minh

1. **Kiểm thử Upload ảnh:**
   - Upload file ảnh hợp lệ (PNG, JPG, WebP) -> Kiểm tra file lưu vào `storage/activity-covers/`, đường dẫn lưu vào DB.
   - Thử upload file không hợp lệ (file text, file quá 5MB) -> Báo lỗi thân thiện, không làm mất các trường thông tin khác đã nhập.
   - Thử chọn ảnh từ bộ sưu tập Preset -> Áp dụng ảnh ngay vào preview và lưu chuẩn xác.
2. **Kiểm thử Tạo hoạt động mới:**
   - Điền thông tin chính, không cần mở accordion nào -> Lưu bản nháp thành công ngay lần đầu, không gặp lỗi validation ngày tháng hay mô tả.
   - Kiểm tra ngày giờ đăng ký và số giờ công nhận được tính toán tự động chuẩn xác.
3. **Kiểm thử Hiển thị phía Học sinh:**
   - Mở `/app/learner/activities.php` (hoặc `/app/student/activities.php`): Hoạt động hiển thị đúng ảnh bìa vừa upload/chọn mẫu trên card.
   - Mở `/app/learner/activity-detail.php`: Banner hero hiển thị ảnh sắc nét, các phần "Giới thiệu hoạt động", "Bạn sẽ trải nghiệm", "Kỹ năng phát triển", "Điều kiện", "Quyền lợi", "Thông tin liên hệ" hiển thị đầy đủ, không bị rỗng.
4. **Kiểm tra Tương thích ngược:**
   - Các hoạt động cũ có sẵn trong CSDL vẫn giữ nguyên ảnh và hiển thị bình thường.
