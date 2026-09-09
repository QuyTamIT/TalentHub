# Design Specification: Sinh viên - Vòng đời trạng thái Hoạt động & Check-in QR

**Ngày tạo:** 09/09/2026  
**Chủ đề:** Chuẩn hóa luồng trạng thái hoạt động sinh viên, tự động phân luồng (Đã đăng ký ➔ Lịch sử) và đồng bộ widget Check-in QR  
**Tác giả:** Antigravity  

---

## 1. Mục tiêu & Bối cảnh

### 1.1 Vấn đề hiện tại
- Trong cơ sở dữ liệu, một số hoạt động trong tương lai (tháng 09/2026) của sinh viên bị gán sai trạng thái đăng ký là `attended` (Đã tham gia) dù chưa hề diễn ra và chưa từng có lượt quét mã QR check-in.
- Do trạng thái là `attended`, trang **Lịch sử hoạt động** (`activity-history.php`) lấy các hoạt động này hiển thị lên đầu tiên (do sắp xếp theo ngày tháng mới nhất), nhưng lại kèm ghi chú bất hợp lý: *"Chưa có check-in xác nhận"* và *0 giờ trải nghiệm*.
- Trong khi đó, khung *"Lịch sử check-in / Hoạt động gần đây"* tại trang **Check-in QR** (`checkin.php`) chỉ truy vấn các bản ghi check-in thực tế (tháng 6 và 7/2026). Điều này dẫn đến sự thiếu đồng bộ: 2 hoạt động xuất hiện ở trang Check-in lại bị đẩy xuống đáy ở trang Lịch sử.
- Thiếu cơ chế tự động chuyển hoạt động quá hạn mà sinh viên không quét QR từ tab **"Đã đăng ký"** sang tab **"Lịch sử"** với trạng thái **Vắng mặt**.

### 1.2 Kết quả mong đợi
1. **Tab "Đã đăng ký" (`my-activities.php`)**: Chứa các hoạt động sinh viên đã đăng ký đang trong tiến trình: `pending` (Chờ duyệt), `approved` (Đã duyệt, có nút check-in), `waitlisted` (Danh sách chờ).
2. **Chuyển sang Tab "Lịch sử" (`activity-history.php`) qua 2 ngã rẽ**:
   - **Thành công (Đã tham gia)**: Sinh viên quét QR hoặc nhập token thành công ➔ Chuyển sang Lịch sử với trạng thái "Đã tham gia", hiển thị mốc giờ check-in và số giờ trải nghiệm.
   - **Vắng mặt (Không tham gia)**: Đã được duyệt nhưng không quét QR và sự kiện đã kết thúc thời gian tổ chức (`now > endAt`) ➔ Tự động chuyển sang Lịch sử với trạng thái "Vắng mặt" (0 giờ trải nghiệm).
3. **Đồng bộ trang "Check-in QR" (`checkin.php`)**:
   - Mục "Lịch sử check-in / Hoạt động gần đây" chỉ hiển thị các hoạt động sinh viên **Đã tham gia** (khớp với nhóm "Đã tham gia" ở trang Lịch sử), sắp xếp theo lần check-in gần nhất lên đầu.
   - Sửa lỗi định dạng chuỗi ngày tháng bị dính số (như `16:07 0014/7/2026`).
4. **Khắc phục dữ liệu lỗi**: Đưa các hoạt động chưa diễn ra đang bị gán `attended` về lại `approved`.

---

## 2. Quy tắc nghiệp vụ & Máy trạng thái (State Machine)

```
[Tab Khám phá (activities.php)]
          │ (Sinh viên bấm Đăng ký)
          ▼
[Tab Đã đăng ký (my-activities.php)]
          ├── pending (Chờ duyệt)
          ├── waitlisted (Danh sách chờ)
          └── approved (Đã duyệt tham gia, hiển thị nút "Đi tới Check-in QR")
                   │
         ┌─────────┴────────────────────────┐
         │                                  │
 (Quét QR / Token thành công)        (Quá giờ kết thúc `now > endAt` mà chưa check-in)
         ▼                                  ▼
[Tab Lịch sử (activity-history.php)]  [Tab Lịch sử (activity-history.php)]
  Trạng thái: "Đã tham gia"             Trạng thái: "Vắng mặt"
  - Có giờ check-in xác nhận            - 0 giờ trải nghiệm
  - Có số giờ trải nghiệm               - Không có bản ghi check-in
  - Đồng bộ sang "Check-in QR"
```

---

## 3. Các thay đổi chi tiết

### 3.1 Xử lý dữ liệu Database (Data Remediation)
- Cập nhật lại các bản ghi trong `activity_registrations` có:
  - `status = 'attended'`
  - Chưa có bản ghi trong bảng `checkins`
  - Hoạt động chưa diễn ra hoặc đang diễn ra (`endAt >= NOW()` hoặc `startAt >= NOW()`)
  ➔ Đặt lại `status = 'approved'`.
- Áp dụng ngay cho 3 hoạt động của tài khoản `sv.fpt.an@talenthub.vn`:
  - `FPTU Music Studio Showcase` (27/09/2026)
  - `Startup Demo Day FPT University` (16/09/2026)
  - `Green Campus: Sáng kiến Bền vững` (12/09/2026)

### 3.2 Chuẩn hóa logic đọc dữ liệu (`app/learner/includes/activity-data.php`)
- **Hàm `learner_activity_active_registrations` (Tab Đã đăng ký)**:
  - Lọc các đăng ký có `status` trong `['pending', 'approved', 'waitlisted']`.
  - **Điều kiện loại trừ tự động**: Nếu đăng ký có `status === 'approved'` nhưng hoạt động đã kết thúc (`endAt !== null && now > endAt`) và chưa có check-in ➔ Loại khỏi danh sách "Đã đăng ký" (để chuyển vào Lịch sử).
- **Hàm `learner_activity_attendance_history` (Tab Lịch sử)**:
  - Bao gồm:
    1. Các đăng ký có `status = 'attended'` (đã quét QR / check-in thành công).
    2. Các đăng ký có `status = 'no_show'`, HOẶC các đăng ký `approved` nhưng sự kiện đã kết thúc (`now > endAt`) mà chưa check-in ➔ Tự động gán trạng thái ảo là `no_show` với nhãn hiển thị là **"Vắng mặt"** (0 giờ trải nghiệm).
  - Loại bỏ các đăng ký tương lai chưa diễn ra.

### 3.3 Hiển thị tại giao diện Lịch sử (`app/learner/activity-history.php`)
- Hiển thị nhãn thống nhất:
  - Tham gia thành công: **"Đã tham gia"** (màu xanh lá).
  - Không tham gia / Vắng: **"Vắng mặt"** (màu đỏ nhạt / xám).
- Chỉ hiển thị giờ check-in xác nhận khi thực sự có `checked_in_at`.

### 3.4 Sửa giao diện & định dạng tại Check-in QR (`assets/js/learner-checkin.js`)
- Sửa hàm `formatDate` trong `learner-checkin.js` để định dạng ngày giờ theo chuẩn tiếng Việt chuẩn (`dd/MM/yyyy · HH:mm`), loại bỏ triệt để lỗi nối chuỗi dính ký tự (như `16:07 0014/7/2026`).
- Đảm bảo danh sách "Lịch sử check-in" ở cột bên phải luôn sắp xếp giảm dần theo mốc `checkedInAt` mới nhất.

---

## 4. Kế hoạch kiểm thử & Xác thực (Verification Plan)

1. **Kiểm tra Tab "Đã đăng ký" (`my-activities.php`)**:
   - Đăng nhập tài khoản `sv.fpt.an@talenthub.vn`.
   - Xác nhận 3 hoạt động tháng 9/2026 xuất hiện đầy đủ ở tab "Đã đăng ký" với trạng thái "Đã được duyệt" và có nút "Đi tới Check-in QR".
2. **Kiểm tra Tab "Lịch sử" (`activity-history.php`)**:
   - Xác nhận 3 hoạt động tháng 9 không còn nằm ở trang Lịch sử.
   - 2 hoạt động tháng 6 và tháng 7 (`Digital Marketing thực chiến`, `FPTU Hackathon vì cộng đồng`) hiển thị đúng với trạng thái "Đã tham gia", đầy đủ số giờ trải nghiệm và ngày giờ check-in.
   - Tổng quan thống kê (KPI cards) hiển thị chính xác: 2 hoạt động đã tham gia, 8.5 giờ trải nghiệm.
3. **Kiểm tra trang "Check-in QR" (`checkin.php`)**:
   - Khung "Hoạt động gần đây / Lịch sử check-in" hiển thị 2 hoạt động đã tham gia, định dạng ngày tháng hiển thị chuẩn (ví dụ: `14/07/2026 · 09:07`).
4. **Kiểm thử tự động & Unit test**:
   - Chạy test suite PHP kiểm tra timeline hoạt động và lịch sử điểm danh.
