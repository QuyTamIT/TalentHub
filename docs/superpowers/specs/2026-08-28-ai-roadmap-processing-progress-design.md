# AI Roadmap Processing Progress Design

**Ngày:** 2026-08-28  
**Phạm vi:** Trang học viên “AI gợi ý” — trạng thái tạo/cập nhật roadmap 90 ngày

## 1. Mục tiêu

Thay màn hình loading trắng bằng bảng tiến trình dễ hiểu, giúp học sinh và sinh viên biết AI đang xử lý yêu cầu. Khi cập nhật, roadmap gần nhất vẫn được giữ nguyên bên dưới để người học tiếp tục xem và không có cảm giác mất dữ liệu.

## 2. Nguyên tắc trải nghiệm

- Không ẩn roadmap đang có trong lúc cập nhật.
- Không trình bày phần trăm ước tính như số liệu backend chính xác.
- Nội dung ngắn, thân thiện, tránh thuật ngữ kỹ thuật.
- Phân biệt rõ tải dữ liệu đã lưu với tạo roadmap mới bằng Gemini.
- Khi lỗi, giữ nguyên roadmap cũ và cung cấp thao tác thử lại.
- Hỗ trợ trình đọc màn hình và tôn trọng `prefers-reduced-motion`.

## 3. Phương án được duyệt

Khi người dùng bấm “Cập nhật phân tích”, một bảng tiến trình xuất hiện phía trên roadmap hiện tại. Roadmap bên dưới vẫn hiển thị, giảm độ nổi bật nhẹ nhưng vẫn đọc và cuộn được.

Bảng tiến trình gồm bốn bước:

1. **Chuẩn bị dữ liệu năng lực** — tổng hợp dữ liệu đánh giá đã được người học cho phép.
2. **Gemini đang phân tích** — phân tích điểm mạnh, điểm cần cải thiện và hướng phát triển.
3. **Xây dựng roadmap 90 ngày** — tạo ba giai đoạn cùng các nhiệm vụ phù hợp.
4. **Kiểm tra và hoàn thiện** — kiểm tra cấu trúc, đầu ra và khả năng theo dõi tiến độ.

Bảng có thanh tiến độ ước tính, thời gian đã xử lý và thông báo “Bạn có thể tiếp tục xem roadmap hiện tại”. Nhãn “Tiến độ ước tính” phải được hiển thị để tránh gây hiểu nhầm.

## 4. Trạng thái giao diện

### 4.1. Tải trang lần đầu

Nếu chưa có dữ liệu trong trình duyệt, hiển thị trạng thái nhỏ “Đang tải lộ trình đã lưu”. Không dùng bốn bước xử lý AI vì lúc này hệ thống chỉ đang đọc dữ liệu.

### 4.2. Tạo roadmap lần đầu

Nếu chưa có roadmap, bảng tiến trình chiếm vùng nội dung chính và hiển thị bốn bước. Không có roadmap cũ để giữ bên dưới.

### 4.3. Cập nhật roadmap

- Giữ `data-roadmap-ready` hiển thị.
- Hiện bảng tiến trình phía trên nội dung ready.
- Vô hiệu hóa nút “Cập nhật phân tích” trong thời gian request đang chạy để tránh gửi trùng.
- Roadmap cũ có trạng thái trực quan “Đang hiển thị bản hiện tại trong lúc cập nhật”.
- Người học vẫn có thể đọc roadmap; thao tác thay đổi nhiệm vụ được giữ nguyên trừ khi phát sinh xung đột kỹ thuật.

### 4.4. Thành công

- Bước cuối chuyển sang hoàn thành.
- Hiển thị thông báo “Roadmap mới đã sẵn sàng”.
- Render payload mới và cập nhật danh sách phiên bản.
- Tự ẩn bảng tiến trình sau một khoảng ngắn; không tự cuộn trang gây mất ngữ cảnh.

### 4.5. Thất bại hoặc timeout

- Không thay roadmap cũ bằng màn hình lỗi.
- Bảng tiến trình chuyển sang trạng thái cảnh báo với nội dung “Chưa thể cập nhật roadmap. Bản hiện tại vẫn được giữ nguyên.”
- Hiển thị nút “Thử cập nhật lại”.
- Nếu không có roadmap cũ, dùng màn hình lỗi hiện tại.

## 5. Tiến độ ước tính

Frontend điều phối trạng thái hiển thị theo thời gian đã chờ; đây không phải telemetry chi tiết từ Gemini:

- 0–5 giây: Chuẩn bị dữ liệu.
- 5–25 giây: Gemini đang phân tích.
- 25–55 giây: Xây dựng roadmap 90 ngày.
- Từ 55 giây: Kiểm tra và hoàn thiện.

Thanh tiến độ tăng có giới hạn và không đạt 100% trước khi API trả thành công. Phần trăm được gắn nhãn “ước tính”. Thời gian hiển thị theo giây và không dùng bộ đếm khi người dùng bật giảm chuyển động nếu việc cập nhật liên tục gây nhiễu.

## 6. Kiến trúc frontend

### `createRoadmapController`

- Gửi metadata loading rõ ngữ cảnh: `initial-load`, `first-generation`, hoặc `refresh-generation`.
- Báo cho view khi generation bắt đầu, thành công hoặc thất bại.
- Tiếp tục dùng một request đang chạy và timeout 90 giây hiện có.
- Giữ `lastReadyPayload` để phục hồi trạng thái stale khi refresh lỗi.

### `createDomView`

- Quản lý bộ đếm tiến trình và hủy timer khi trạng thái thay đổi hoặc controller bị dispose.
- Render bước hiện tại, bước đã hoàn thành, thời gian và tiến độ ước tính bằng `textContent`.
- Không chèn nội dung AI bằng `innerHTML`.

### Markup và CSS

- Thêm vùng tiến trình có `role="status"`, `aria-live="polite"` và tiêu đề rõ ràng.
- Dùng màu chính cam `#F97316`, màu hoàn thành xanh `#16A34A`, nền `#FFF7ED`/`#FFFFFF` và font Be Vietnam Pro theo hệ thống hiện tại.
- Responsive: desktop hiển thị bốn bước theo hàng ngang; màn hình nhỏ hiển thị theo cột dọc.
- Animation chỉ dùng opacity/transform nhẹ và tắt trong `prefers-reduced-motion`.

## 7. Xử lý dữ liệu và an toàn

- Không hiển thị prompt, dữ liệu đánh giá thô, API key, request body hoặc suy luận nội bộ của Gemini.
- Chỉ hiển thị mô tả quy trình cấp cao đã định nghĩa sẵn trong frontend.
- Không khẳng định một bước backend đã hoàn tất nếu API chưa cung cấp telemetry tương ứng; trạng thái phải ghi rõ là ước tính.
- Request tạo roadmap vẫn dùng CSRF, idempotency key và timeout 90 giây.

## 8. Kiểm thử chấp nhận

- Tải trang lần đầu chỉ hiển thị thông báo tải bản đã lưu.
- Refresh khi đã có roadmap giữ nội dung ready bên dưới.
- Bảng tiến trình chuyển qua bốn bước theo đồng hồ điều khiển trong test.
- Tiến độ không đạt 100% trước response thành công.
- Thành công render roadmap mới và dừng timer.
- Refresh lỗi giữ payload cũ ở trạng thái stale và hiển thị nút thử lại.
- Nút cập nhật không tạo request trùng khi đang chạy.
- DOM chỉ dùng API an toàn; không dùng `innerHTML` cho nội dung động.
- CSS responsive và có `prefers-reduced-motion`.

## 9. Ngoài phạm vi

- Streaming nội dung từ Gemini theo token.
- Hiển thị chain-of-thought hoặc suy luận nội bộ của mô hình.
- Thêm endpoint backend chỉ để báo phần trăm tiến độ.
- Thay đổi cấu trúc dữ liệu roadmap hoặc prompt Gemini 1.3.0.

