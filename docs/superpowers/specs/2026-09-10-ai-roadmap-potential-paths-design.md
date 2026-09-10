# Thiết Kế Cải Thiện Hiển Thị "Tiềm Năng Mở Rộng" Trên Bản Đồ Năng Khiếu AI

- **Ngày tạo:** 2026-09-10
- **Trạng thái:** Đã duyệt thiết kế (Approved)
- **Mục tiêu:** Đảm bảo khu vực "Tiềm năng mở rộng" (Bản đồ năng khiếu) trên giao diện Lộ trình AI (`ai-recommendations.php`) luôn hiển thị đầy đủ, cân xứng với "Điểm mạnh nổi bật", dựa trên dữ liệu thực tế và mới nhất của học viên, không ảo giác (zero-hallucination).

---

## 1. Bối cảnh & Vấn đề

Trên giao diện Lộ trình AI (`app/learner/ai-recommendations.php`), trong thẻ **ĐÁNH GIÁ NĂNG LỰC / Bản đồ năng khiếu**:
- Cột bên trái hiển thị 3 nhóm năng khiếu: Tư duy Logic & Hệ thống, Kỹ năng Thực hành & Thao tác, Tổ chức & Điều phối.
- Cột bên phải có 2 nhóm thẻ nhận định:
  - **Điểm mạnh nổi bật** (`data-roadmap-strengths`): Hiển thị 2 thẻ năng lực thực tế.
  - **Tiềm năng mở rộng** (`data-roadmap-potential-paths`): Hiện thông báo rỗng *"Chưa có hướng phát triển đủ bằng chứng."*.

### Nguyên nhân gốc rễ:
1. **Dữ liệu AI:** Đối với bản phân tích hiện tại, trường `potential_paths` từ AI trả về mảng rỗng `[]` vì prompt trước đó không bắt buộc số lượng tối thiểu và có ràng buộc về `catalog_id`, trong khi danh mục nghề chuẩn trong snapshot rỗng. Tuy nhiên, AI **đã phân tích đầy đủ tiềm năng** nhưng lưu trong `alternative_directions` (2 hướng nghề nghiệp thay thế tiềm năng) và `insights` (danh mục `potential`).
2. **Giao diện Frontend:** `learner-ai-roadmap.js` chỉ đọc đơn lẻ trường `model.potentialPaths`. Khi mảng này rỗng, giao diện rơi vào trạng thái rỗng thay vì tận dụng dữ liệu tiềm năng sẵn có từ cùng phiên phân tích.

---

## 2. Giải pháp Kỹ thuật Chi tiết

### 2.1. Frontend Fallback Thông minh (`assets/js/learner-ai-roadmap.js`)
Áp dụng tại hàm `buildRoadmapViewModel(payload)`:
- **Nguyên tắc ưu tiên:**
  1. Nếu `payload.potential_paths` có phần tử: Chuẩn hóa và hiển thị danh sách này (dùng `label` hoặc kết hợp `text`).
  2. Nếu `payload.potential_paths` rỗng: Fallback sang `payload.alternative_directions`.
     - Chuyển đổi mỗi hướng thay thế thành một thẻ tiềm năng:
       - Thuộc tính `label`: `${dir.label}: ${dir.rationale}` (hoặc `${dir.label} – ${dir.rationale}`).
       - Thuộc tính `evidence_ref_ids`: kế thừa từ các tham chiếu bằng chứng của lộ trình.
  3. Nếu `alternative_directions` rỗng: Fallback sang các nhận định trong `payload.insights` có `category === 'potential'`.
     - Thuộc tính `label`: `${insight.title}: ${insight.summary}`.
     - Thuộc tính `evidence_ref_ids`: trích dẫn bằng chứng từ insight đó.
  4. Nếu cả 3 nguồn trên đều không có: Hiển thị thông báo rỗng *"Chưa có hướng phát triển đủ bằng chứng."*.
- **Hiệu quả giao diện:**
  - Thẻ hiển thị có độ dài cân bằng (2–3 dòng), tương thích với `min-height: 82px` và padding của `.learner-roadmap-capability__record`.
  - Giữ tính năng xem nguồn bằng chứng (`title` attribute trích dẫn bằng chứng thực tế).
  - Bản phân tích hiện tại của học viên lập tức hiển thị đầy đủ 2 thẻ tiềm năng mà không cần phải phân tích lại AI.

### 2.2. Tối ưu Backend Prompt & Grounding (`app/learner/ai/Model/RoadmapPromptRegistry.php`)
Áp dụng cho các phiên tạo mới/làm mới lộ trình tiếp theo:
- **Chỉ dẫn trích xuất:** Yêu cầu AI luôn trích xuất từ 2 đến 3 hướng tiềm năng mở rộng (`potential_paths`) phù hợp nhất từ sự kết hợp giữa các năng khiếu cao liền kề (ở bản đồ năng khiếu) với sở thích và kỹ năng thực tế trong 4 bài đánh giá (Holland, MBTI, DISC, Multiple Intelligence).
- **Nguyên tắc Strict Grounding (Dữ liệu thực & Không ảo giác):**
  - Mỗi mục trong `potential_paths` **bắt buộc** phải trích dẫn danh sách mã bằng chứng (`evidence_ref_ids`) thực tế được cung cấp trong snapshot mới nhất.
  - Trường `label` mô tả rõ tên hướng phát triển và lý do ngắn gọn gắn liền với bằng chứng.
  - `catalog_id` là tùy chọn: chỉ cung cấp khi có catalog evidence tương ứng trong snapshot; nếu không có catalog thì chỉ cần xuất `label` và `evidence_ref_ids`.
- **Đảm bảo tính hợp lệ:** Cấu trúc này hoàn toàn tương thích với schema và validator hiện tại (`RoadmapAnalysisValidator.php`), không gây lỗi validation fail-closed.

---

## 3. Kế hoạch Kiểm thử & Xác minh (Verification Plan)

1. **Kiểm thử Giao diện (Frontend Fallback):**
   - Tải lại trang `ai-recommendations.php` với bản ghi ID `18b04fc8-8220-4e24-b4ba-be3b875a5ae4` (bản ghi đang hiển thị trên giao diện của học viên).
   - Xác minh khu vực "Tiềm năng mở rộng" hiển thị đủ 2 thẻ tiềm năng từ `alternative_directions`.
   - Xác minh tooltip bằng chứng hiển thị chính xác.
2. **Kiểm thử Kiểm định Backend (Validation Unit Tests):**
   - Chạy test PHP hiện có (`tests/learner_ai_roadmap_freshness_test.php`, `tests/learner_ai_roadmap_provider_timeout_test.php`) để đảm bảo không phá vỡ logic cũ.
   - Thử nghiệm sinh request từ `RoadmapPromptRegistry` với snapshot thực tế, đảm bảo output schema và validator phê duyệt hợp lệ.
3. **Kiểm tra Tính toàn vẹn (Regression & Console):**
   - Đảm bảo không có lỗi JavaScript trên console trình duyệt.
   - Đảm bảo không ảnh hưởng đến các màn hình liên quan (`discover.php`, `profile.php`, skill gap matching).
