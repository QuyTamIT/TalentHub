# Đặc tả thiết kế: Giao diện làm bài đánh giá cuộn liên tục (Continuous Scroll Assessment UI)

- **Mục tiêu:** Nâng cấp trải nghiệm làm 4 bài đánh giá năng lực & tính cách (Holland, MBTI, DISC, Đa trí thông minh) trên F-TalentHub theo phong cách trắc nghiệm cuộn liên tục (lấy cảm hứng từ TopCV nhưng giữ trọn bộ nhận diện màu sắc F-TalentHub).
- **Phạm vi tác động:** Chỉ can thiệp vào tầng giao diện người dùng (Frontend UI/UX: HTML, CSS, JS), giữ nguyên 100% API, Backend, cơ sở dữ liệu và thuật toán chấm điểm.

---

## 1. Bối cảnh & Hiện trạng

- **Hiện tại:** Giao diện bài test hiển thị 1 câu hỏi trên mỗi màn hình. Người dùng chọn đáp án và phải bấm "Câu tiếp" hoặc dùng bảng số bên cạnh để chuyển câu. Có widget đếm ngược thời gian (timer).
- **Yêu cầu mới:**
  - Bỏ hiển thị thời gian / đếm ngược hoàn toàn.
  - Toàn bộ danh sách câu hỏi hiển thị dạng cuộn dọc liên tục ở giữa trang.
  - Mỗi câu hỏi gồm tiêu đề rõ ràng và các phương án trả lời dạng nút bo tròn (Pill) có nút radio tròn `○` bên trái.
  - Khi người dùng chọn đáp án cho 1 câu, tự động lưu đáp án và sau ~200ms tự động cuộn mượt (*smooth scroll*) xuống câu tiếp theo.
  - Người dùng tự do cuộn lên/xuống bất kỳ lúc nào để xem lại hoặc đổi đáp án.
  - Thanh Header dính trên đầu trang (Sticky Top) hiển thị số câu đã làm (ví dụ `2/32`), thanh tiến độ cam, và nút "Xem chi tiết" (mở bảng danh sách câu để nhảy nhanh).
  - Nút nộp bài xuất hiện ở cuối danh sách câu hỏi và trên thanh Header khi đã hoàn thành.

---

## 2. Chi tiết Thiết kế Giao diện (UI Design)

### 2.1. Bảng màu & Phong cách F-TalentHub
- **Nền trang:** Sử dụng màu canvas dịu (`#F8FAFC`).
- **Thẻ câu hỏi (`.learner-assessment-question-item`):**
  - Nền thẻ trắng (`#FFFFFF`), bo tròn góc lớn `16px`, viền xám siêu mảnh (`#E2E8F0`), đổ bóng mềm `0 4px 16px rgba(15, 23, 42, 0.04)`.
  - Tiêu đề câu: Đậm nét, cỡ chữ `1.15rem`, màu `#0F172A`, định dạng: `[Số thứ tự]. [Nội dung câu hỏi]` (ví dụ: `1. Trong một buổi tiệc, bạn sẽ:`).
  - Khoảng cách giữa các thẻ câu hỏi: `18px - 24px`.
- **Nút phương án trả lời (`.learner-likert-pill`):**
  - Dàn ngang theo hàng linh hoạt (responsive flex-wrap hoặc grid) trên máy tính và tự xuống hàng trên thiết bị nhỏ.
  - Bên trái là nút tròn Radio `○` đường kính `20px` với viền `2px solid #CBD5E1`.
  - Bên phải là nhãn nội dung lựa chọn.
  - **Trạng thái chưa chọn:** Nền `#FFFFFF` hoặc `#F8FAFC`, viền `#E2E8F0`, màu chữ `#334155`.
  - **Trạng thái Hover:** Viền đổi sang cam nhạt (`#FDBA74`), nền ấm nhẹ.
  - **Trạng thái Đã chọn (Active):**
    - Nền chuyển sang cam nhạt dịu (`#FFF7ED` - `var(--primary-light)`).
    - Viền chuyển sang cam chuẩn F-TalentHub (`#EA580C` / `#FF6B00` - `var(--primary)`).
    - Nút tròn radio hiển thị tâm cam đậm nổi bật (`◉`).
    - Chữ hiển thị đậm hơn (`font-weight: 600`), màu `#0F172A`.

### 2.2. Thanh tiến độ cố định (Sticky Header)
- Cố định ở đầu trang khi cuộn: `position: sticky; top: var(--learner-header-height, 0); z-index: 100`.
- Nền trắng với hiệu ứng mờ kính nhẹ (glassmorphism: `backdrop-filter: blur(8px)`), viền dưới mỏng.
- Thành phần:
  - **Bên trái:** Tên bài test nhỏ và chỉ số tiến độ nổi bật: `<span class="progress-count">X/Y</span>` (ví dụ `2/32`).
  - **Ở giữa:** Thanh tiến độ với thanh track xám nhạt và vạch đo bo tròn hai đầu màu cam F-TalentHub.
  - **Bên phải:** 
    - Nút `"Xem chi tiết"` (mở bảng số câu hỏi).
    - Nút `"Nộp bài"` dạng nút cam phụ hoặc nổi bật khi đã làm đủ số câu.

### 2.3. Modal / Drawer danh sách câu hỏi ("Xem chi tiết")
- Kích hoạt khi bấm nút "Xem chi tiết" trên header.
- Hiển thị ma trận các ô số câu hỏi (1 .. N):
  - Ô đã làm: Đánh dấu nền cam, viền cam F-TalentHub.
  - Ô chưa làm: Nền trắng/xám viền mỏng.
- Nhấp vào bất kỳ số câu nào: Tự động đóng bảng và kích hoạt `scrollIntoView({ behavior: 'smooth', block: 'center' })` đến câu hỏi đó.

### 2.4. Loại bỏ hoàn toàn yếu tố thời gian
- Xóa bỏ widget đồng hồ đếm ngược `learner-assessment-timer`.
- Xóa bỏ icon đồng hồ và nhãn "12 phút" trên thẻ giới thiệu bài test (`data-assessment-intro`).
- Giao diện không kích hoạt trạng thái hết giờ (`expired`).

---

## 3. Luồng Tương tác & Kỹ thuật (Interaction & Technical Flow)

### 3.1. Khởi tạo danh sách câu hỏi
- Khi người dùng bắt đầu bài test hoặc tải lại bài nháp đang làm dở:
  - Hàm `renderQuestionList(attempt)` duyệt qua toàn bộ mảng `attempt.questions` và kết xuất toàn bộ câu hỏi vào container chính `.learner-assessment-stream`.
  - Đáp án đã lưu trước đó (nếu có trong `attempt.answers`) được tự động gán checked cho radio tương ứng.
  - Cập nhật số câu đã làm trên thanh tiến độ (`X/Y`).

### 3.2. Xử lý khi chọn đáp án & Tự động cuộn (Auto-Scroll)
1. Bắt sự kiện `change` trên các input radio của danh sách câu hỏi.
2. Cập nhật ngay lập tức giao diện của câu vừa chọn sang trạng thái Active (phản hồi thị giác 0ms).
3. Gọi `controller.saveAnswer(questionId, value)` để lưu đáp án ngầm vào hệ thống.
4. Cập nhật ngay chỉ số tiến độ trên thanh header (`answeredCount / total`).
5. Đợi một khoảng trễ cực ngắn `180ms - 220ms` (đủ để mắt người dùng ghi nhận lựa chọn đã được tick).
6. Tìm câu hỏi tiếp theo cần làm:
   - Ưu tiên: Câu tiếp theo liền kề (`index + 1`). Nếu câu liền kề đã làm rồi thì tìm câu chưa trả lời gần nhất phía dưới.
   - Nếu tìm thấy, thực hiện cuộn mượt:
     ```javascript
     nextQuestionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
     ```
7. Người dùng hoàn toàn tự do lăn chuột lên/xuống bất cứ lúc nào mà không bị gián đoạn hay hạn chế.

### 3.3. Xử lý Nộp bài (Submit)
- Ở cuối danh sách câu hỏi: Có thẻ hoàn thành hiển thị tổng kết tiến độ (ví dụ: `Đã hoàn thành 32/32 câu`) kèm nút bấm cam nổi bật `"Kiểm tra & nộp bài"`.
- Khi người dùng nhấn Nộp bài (ở cuối trang hoặc trên thanh header):
  - Mở Modal xác nhận nộp bài hiện có (`#learner-assessment-submit-modal`).
  - Modal hiển thị số câu đã làm và số câu chưa làm.
  - Nếu người dùng xác nhận nộp: gọi `controller.submit()` như quy trình gốc, sau đó chuyển hướng đến trang kết quả `assessment-result.php`.

---

## 4. Danh sách Tệp cần Sửa đổi

1. **`d:\TalentHub\app\learner\assessment.php`**:
   - Cập nhật cấu trúc HTML của runner: bỏ đồng hồ thời gian, thay thế cấu trúc 1 câu hỏi cũ bằng danh sách cuộn liên tục `learner-assessment-stream`, bổ sung thanh header sticky mới với nút "Xem chi tiết" và nút nộp bài.
   - Thêm modal/drawer hiển thị bảng nhảy câu nhanh cho nút "Xem chi tiết".
2. **`d:\TalentHub\assets\js\learner-assessment.js`**:
   - Nâng cấp `createDomView`: kết xuất toàn bộ danh sách câu hỏi thay vì 1 câu đơn lẻ.
   - Bổ sung logic auto-scroll thông minh (~200ms) sau khi chọn đáp án.
   - Kết nối sự kiện nút "Xem chi tiết" và điều hướng cuộn đến từng câu.
   - Loại bỏ các logic phụ thuộc vào timer.
3. **`d:\TalentHub\assets\css\learner.css`**:
   - Thêm bộ styles hiện đại cho `.learner-assessment-stream`, `.learner-assessment-question-item`, `.learner-likert-pill`, sticky header, progress indicator và responsive layout chuẩn màu F-TalentHub.

---

## 5. Kế hoạch Kiểm thử & Xác minh

- **Kiểm thử giao diện cả 4 bài test:**
  1. MBTI (`/app/learner/assessment.php?code=mbti`)
  2. Holland (`/app/learner/assessment.php?code=holland`)
  3. DISC (`/app/learner/assessment.php?code=disc`)
  4. Đa trí thông minh (`/app/learner/assessment.php?code=multiple_intelligence`)
- **Kiểm tra hành vi:**
  - Chọn câu 1 -> tự động cuộn xuống câu 2 mượt mà sau ~200ms.
  - Lăn chuột lên câu 1 đổi lại đáp án -> lưu thành công và không bị giật lag.
  - Thanh tiến độ trên sticky header nhảy đúng số câu (ví dụ `1/32` -> `2/32`).
  - Bấm "Xem chi tiết" -> mở danh sách câu hỏi, bấm số bất kỳ -> cuộn chính xác tới câu đó.
  - Không có bất kỳ hiển thị đồng hồ thời gian nào.
  - Nộp bài -> modal xác nhận hiển thị đúng số câu, nộp bài thành công và chuyển hướng sang trang kết quả.
