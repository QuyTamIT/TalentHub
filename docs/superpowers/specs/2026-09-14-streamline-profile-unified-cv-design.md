# Đặc Tả Thiết Kế: Tinh Gọn Hồ Sơ Năng Lực & Đồng Nhất Giao Diện CV Chứng Thực

## 1. Mục tiêu & Bối cảnh
- **Mục tiêu**:
  1. Tinh gọn thanh hành động tại trang Hồ sơ năng lực của sinh viên (`app/learner/profile.php`): Gộp nút "Xuất CV A4" và "Talent Passport" thành một nút duy nhất là **"Xuất CV"**, loại bỏ nút "Talent Passport".
  2. Chuẩn hóa trang Xuất CV (`app/learner/talent-passport-cv.php` & `app/learner/includes/passport-cv-template.php`): Loại bỏ toàn bộ cụm từ "360" / "360°" trong CV; đảm bảo bản xem trước (preview) và file in/xuất PDF chuẩn xác 100% đồng nhất trong đúng 1 trang A4 duy nhất.
  3. Đồng nhất 100% giao diện khi nhà tuyển dụng/người ngoài quét mã QR hoặc mở link từ tính năng "Chia sẻ hồ sơ" (`app/learner/shared-profile.php`): Hiển thị đúng bản CV A4 chuyên nghiệp đã xuất để đối soát và chứng minh năng lực, kèm thanh công cụ hỗ trợ nhà tuyển dụng in/tải PDF.

---

## 2. Chi Tiết Thay Đổi Giao Diện & Trải Nghiệm Người Dùng

### 2.1. Trang Hồ sơ năng lực (`app/learner/profile.php`)
- **Trước thay đổi**: 4 nút hành động (`[Xuất CV A4]`, `[Talent Passport]`, `[Chia sẻ hồ sơ]`, `[Chỉnh sửa]`).
- **Sau thay đổi**: Rút gọn còn đúng 3 nút:
  1. **`[Xuất CV]`**: Nút chính (primary gradient xanh), icon `file-text`, dẫn trực tiếp tới `talent-passport-cv.php`.
  2. **`[Chia sẻ hồ sơ]`**: Nút outline, icon `share`, mở modal chia sẻ (`learner-share-modal`).
  3. **`[Chỉnh sửa]`**: Nút outline, icon `edit`, mở modal chỉnh sửa thông tin cá nhân (`learner-edit-modal`).
- **Modal "Chia sẻ hồ sơ" (`learner-share-modal`)**:
  - Cập nhật liên kết phụ "Tải bản in PDF" trỏ tới `talent-passport-cv.php` thay vì `talent-passport.php`.

### 2.2. Trang Xuất CV (`talent-passport-cv.php` & `passport-cv-template.php`)
- **Loại bỏ cụm từ "360" / "360°"**:
  - Dòng badge xác thực: `✓ Xác thực Năng lực TalentHub` (thay cho `✓ Xác thực TalentHub 360°`).
  - Con dấu chân sidebar: Đổi `TALENT PASSPORT 360°` thành `TALENT PASSPORT`.
  - Tiêu đề và nội dung chân trang không còn chứa "360" hay "360°".
- **Thanh điều hướng**:
  - Nút quay lại: `<a href="profile.php">← Hồ sơ năng lực</a>` (thay vì trỏ về `talent-passport.php`).
- **Đảm bảo đúng 1 trang A4 duy nhất**:
  - Giữ nguyên cấu trúc 2 cột tỷ lệ tối ưu (cột trái ~33%, cột phải ~67%) trong khung kích thước cố định A4 portrait (`210mm x 297mm`).
  - Khi xem trước (preview): hiển thị trọn vẹn tờ A4 trên nền xám nhẹ.
  - Khi người dùng bấm `[Lấy dữ liệu mới & xuất PDF]` hoặc kích hoạt lệnh In của trình duyệt:
    - CSS `@media print` ẩn toolbar, căn lề 0, giữ nguyên toàn bộ màu sắc, thanh kỹ năng, con dấu và mã QR.
    - Bản in/PDF xuất ra khớp 100% từng pixel và dòng chữ với bản xem trước, nằm trọn vẹn trong 1 trang A4.

### 2.3. Trang Xác Thực Hồ Sơ Công Khai Cho Người Ngoài (`app/learner/shared-profile.php`)
- **Kịch bản truy cập**:
  - **Kịch bản 1 (Quét QR)**: Người ngoài quét mã QR trên bản CV hoặc truy cập URL `shared-profile.php?code=...`.
  - **Kịch bản 2 (Link chia sẻ)**: Nhà tuyển dụng click vào liên kết được gửi từ sinh viên `shared-profile.php?token=...`.
- **Giao diện hiển thị**:
  - Không sử dụng giao diện card cũ dạng portal.
  - Sử dụng chung template CV (`passport-cv-template.php`) và stylesheet (`learner-passport-cv.css`) để tạo ra giao diện **giống hệt 100% bản CV xuất ra của sinh viên**.
  - **Thanh công cụ cho khách (`$isGuestView = true`)**:
    - Nhãn xác thực: `Hồ sơ CV xác thực điện tử bởi TalentHub • Bản gốc chứng thực` kèm mã số hồ sơ.
    - Nút hành động: `[In / Tải PDF]` cho phép nhà tuyển dụng nhấn để in hoặc lưu file PDF ngay lập tức với định dạng chuẩn 1 trang A4.
- **Xử lý khi liên kết lỗi hoặc hết hạn**:
  - Nếu token không tồn tại, đã bị thu hồi hoặc hết hạn: Hiển thị màn hình thông báo trang nhã ("Hồ sơ không tồn tại hoặc liên kết chia sẻ đã hết hạn").

---

## 3. Kiến Trúc Dữ Liệu & Tích Hợp Kỹ Thuật

```
[Sinh viên: profile.php] ──(Bấm "Xuất CV")──> [talent-passport-cv.php] (Bản xem trước A4)
                                                      │
                                                      ├──> In/Tải PDF (1 trang A4 duy nhất)
                                                      │
                                                      └──> Con dấu có chứa Mã QR / Link xác thực
                                                                     │
[Nhà tuyển dụng / Khách ngoài]                                       │
  ├── Quét mã QR (?code=...) ────────────────────────────────────────┤
  └── Click link chia sẻ (?token=...) ───────────────────────────────┤
                                                                     ▼
                                                      [shared-profile.php]
                                                                     │
                                                      ┌──────────────┴──────────────┐
                                                      ▼                             ▼
                                              ProfileSharingService      DatabasePassportCvRepository
                                              (Xác thực Token/Code)      (Nạp dữ liệu CV chứng thực)
                                                      │                             │
                                                      └──────────────┬──────────────┘
                                                                     ▼
                                                         PassportCvViewModel::build()
                                                                     ▼
                                                         passport-cv-template.php
                                                         (Giao diện CV A4 100% đồng nhất)
```

1. **`shared-profile.php`**:
   - Khi có `?token=...`: Gọi `ProfileSharingService::resolveShare($token)`. Lấy `studentId`.
   - Khi có `?code=...`: Gọi `ProfileSharingService::resolvePassportCode($code)`. Lấy `studentId`.
   - Khi có `studentId`: Gọi `DatabasePassportCvRepository::forStudent($studentId)` và `PassportCvViewModel::build($data, $stamp)`.
   - Nếu truy cập từ token có danh sách quyền `sharedFields`: áp dụng lọc các trường nhạy cảm (như email/phone) nếu sinh viên không chọn chia sẻ.
   - Nạp biến `$isGuestView = true` và `require __DIR__ . '/includes/passport-cv-template.php'`.

2. **`app/learner/includes/passport-cv-template.php`**:
   - Thêm cờ `$isGuestView = $isGuestView ?? false`.
   - Nếu `$isGuestView` là `true`:
     - Toolbar hiển thị: Trạng thái xác thực chứng chỉ số + Nút `In / Tải PDF` (gọi `window.print()`).
     - Ẩn nút quay lại trang quản trị sinh viên.
   - Nếu `$isGuestView` là `false` (sinh viên đang đăng nhập xem):
     - Toolbar hiển thị: Nút `← Hồ sơ năng lực` (`profile.php`) + Hướng dẫn + Nút `Lấy dữ liệu mới & xuất PDF`.
   - Bỏ toàn bộ chữ `360` / `360°`.

---

## 4. Kế Hoạch Kiểm Thử & Xác Nhận (Verification Plan)
1. **Kiểm tra giao diện Hồ sơ năng lực (`profile.php`)**:
   - Đảm bảo hiển thị đúng 3 nút: `[Xuất CV]`, `[Chia sẻ hồ sơ]`, `[Chỉnh sửa]`.
   - Không còn nút `[Talent Passport]`.
   - Bấm `[Xuất CV]` chuyển đúng sang `talent-passport-cv.php`.
2. **Kiểm tra giao diện Xuất CV (`talent-passport-cv.php`)**:
   - Không còn bất kỳ chữ `360` hay `360°` nào trên toàn bộ trang.
   - Bản xem trước hiển thị trọn vẹn trong 1 trang A4.
   - Khi bấm in/xuất PDF, bản in khớp 100% với bản xem trước và không bị ngắt sang trang thứ 2.
3. **Kiểm tra giao diện Quét QR & Link chia sẻ (`shared-profile.php`)**:
   - Truy cập với `?code=...` (quét từ mã QR): Hiển thị bản CV A4 giống hệt bản đã xuất.
   - Truy cập với `?token=...` (từ tính năng chia sẻ): Hiển thị bản CV A4 giống hệt bản đã xuất.
   - Thanh công cụ phía trên hiển thị đúng thông tin xác thực và nút `[In / Tải PDF]`.
   - Truy cập với mã/token không hợp lệ: Hiển thị thông báo lỗi 404 trang nhã.
4. **Kiểm thử tự động (Automated Test Suite)**:
   - Chạy test cập nhật cho `bin/test-student-upgrades.php`.
   - Viết test script kiểm chứng đầu ra HTML của `profile.php`, `talent-passport-cv.php`, và `shared-profile.php`.
