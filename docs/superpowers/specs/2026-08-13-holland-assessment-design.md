# Holland Assessment Design

## Goal

Hoàn thiện luồng bài test Holland trong Student Portal bằng mock data, hoạt động được trên trình duyệt và không thay đổi database hoặc code của vai trò khác.

## Scope

- Chỉ sửa `app/learner/**`, `assets/css/learner.css`, `assets/js/learner-assessment.js` và test learner.
- Một bài Holland/RIASEC phiên bản `holland-riasec@1.0` gồm 24 phát biểu, 4 câu cho mỗi nhóm R, I, A, S, E, C.
- Mock provider cung cấp định nghĩa bài test, câu hỏi, lựa chọn Likert và lịch sử kết quả mẫu.
- LocalStorage lưu bản nháp và kết quả mới trên trình duyệt hiện tại.
- Không đồng bộ đa thiết bị ở giai đoạn mock. Khi có backend, thay `AssessmentStorage` bằng API adapter.

## Data contract

Mọi attempt có các trường `id`, `student_id`, `assessment_id`, `assessment_version`, `status`, `started_at`, `updated_at`, `expires_at`, `submitted_at`, `answers` và `result`. ID câu hỏi ổn định theo mẫu `holland-r-01`. Trạng thái dùng `in_progress`, `submitted`, `expired` để tương thích repository/database tương lai.

Định nghĩa bộ đề có `source_role=school_expert`, `source=learner_mock`, `status=published` và `version=1.0`. Student Portal chỉ đọc bộ đề, không giả lập quyền tạo hoặc xuất bản của trường học.

## Screens and states

1. `assessment.php?id=holland`: giới thiệu bài test trước khi bắt đầu.
2. Cùng route chuyển sang runner: một câu mỗi bước, tiến độ, câu trước/sau, bảng điều hướng câu và lưu nháp tự động.
3. Xác nhận nộp: cảnh báo số câu chưa trả lời; không cho nộp khi còn thiếu.
4. Loading: hiển thị trong lúc khởi tạo storage.
5. Error: hiển thị khi boot data/storage không hợp lệ và có nút thử lại.
6. Expired: khóa câu trả lời khi hết thời gian, giữ attempt ở trạng thái expired và cho phép bắt đầu lại.
7. `assessment-result.php?id=holland`: kết quả RIASEC, nhóm nổi bật, diễn giải, khuyến nghị và lịch sử các lần làm.

## Persistence

Storage key là `talenthub.learner.assessments.v1`. Dữ liệu được đóng trong `{schema_version, attempts}`. Parser phải chịu được JSON hỏng bằng cách trả về state rỗng thay vì làm hỏng trang. Lịch sử mẫu luôn đến từ PHP provider nên xuất hiện trên mọi máy; lịch sử mới chỉ xuất hiện trên trình duyệt đã làm bài.

## Scoring

Mỗi câu dùng thang Likert 1–5. Tổng từng nhóm được chuẩn hóa về 0–100 bằng `(sum - min) / (max - min) * 100`. Nhóm cao nhất và hai nhóm kế tiếp tạo mã Holland ba chữ cái. Kết quả chỉ mang tính định hướng, không phải chẩn đoán.

## Accessibility and responsive

- Radio có label rõ ràng, trạng thái lỗi dùng live region.
- Có thể thao tác toàn bộ bằng bàn phím.
- Focus chuyển tới tiêu đề câu khi đổi bước.
- Timer có `role=timer`; cảnh báo dưới 2 phút.
- Runner và kết quả dùng được ở 390px mà không tràn ngang.

## Testing

- PHP test kiểm tra 24 câu, đủ 6 nhóm, ID duy nhất, version và mock history.
- Node test kiểm tra scoring, câu chưa trả lời, timer, parser và storage adapter.
- Render test kiểm tra route giới thiệu, runner/result markers và trạng thái lịch sử.
- PHP lint, JS syntax, HTTP smoke và scope guard trước khi hoàn thành.
