# AI ghép dự án và cơ hội cho học sinh, sinh viên — Design Spec

## Trạng thái

- Ngày chốt thiết kế: 2026-08-29.
- Đối tượng: học sinh và sinh viên đã đăng nhập trong cổng Learner.
- Phạm vi demo: trả về đúng Top 3 dự án/cơ hội phù hợp nhất.
- Quyết định chấm điểm đã duyệt: 70% dữ liệu có cấu trúc và 30% phân tích Gemini.
- Giao diện đã duyệt: AI là tính năng inline trong tab `Cơ hội`, không có tab AI, modal, drawer hoặc trang AI riêng.

## Mục tiêu

Khi người học bấm `AI gợi ý dự án phù hợp` tại trang `Hệ sinh thái & Cơ hội`, TalentHub đối chiếu hồ sơ đã được phép sử dụng với các dự án/cơ hội thật đang mở trong database. Hệ thống hiển thị Top 3 ở đầu danh sách cơ hội, trước khu vực `Tất cả cơ hội đang mở`.

Mỗi kết quả phải là một phân tích riêng cho đúng dự án đó, gồm:

- Điểm phù hợp từ 0 đến 100.
- Lý do phù hợp dựa trên bằng chứng của người học và yêu cầu dự án.
- Kỹ năng hiện tại đáp ứng.
- Kỹ năng còn thiếu hoặc nên bổ sung.
- Kỹ năng/kết quả người học có thể phát triển sau khi hoàn thành.
- Nguồn dữ liệu đã dùng.
- Liên kết về bản ghi dự án/cơ hội thật trong TalentHub.

Điểm phù hợp là chỉ số tham khảo cá nhân, không phải điểm năng lực tổng quát, xếp hạng giữa người học hoặc cam kết trúng tuyển/đạt giải.

## Phạm vi

### Trong phạm vi

- Trang `app/learner/ecosystem.php`, tab `Cơ hội`.
- Dự án học tập đang triển khai và cơ hội doanh nghiệp đang mở, có bản ghi canonical trong database.
- Hồ sơ người học, kỹ năng, kết quả đánh giá, hoạt động/kinh nghiệm và đánh giá đã công bố mà người học đã cho phép AI sử dụng.
- Pipeline lọc ứng viên, chấm điểm có cấu trúc, phân tích Gemini, kiểm chứng, lưu kết quả và render Top 3.
- Trạng thái consent, thiếu dữ liệu, đang xử lý, thành công, kết quả cũ, lỗi nguồn và lỗi Gemini.
- Theo dõi click, phản hồi hữu ích/chưa phù hợp và audit metadata không chứa dữ liệu nhạy cảm.

### Ngoài phạm vi

- Dashboard learner.
- Trang roadmap `AI gợi ý` hiện tại.
- Cổng giáo viên, nhà trường, doanh nghiệp và admin.
- So sánh/xếp hạng học sinh, sinh viên với nhau.
- Đề xuất dự án không tồn tại trong database.
- Tự động ứng tuyển hoặc chia sẻ hồ sơ với doanh nghiệp.
- Dùng dữ liệu nhạy cảm hoặc đặc điểm được bảo vệ để chấm điểm.

## Trải nghiệm giao diện đã duyệt

Trang giữ nguyên hai tab `Doanh nghiệp` và `Cơ hội`. Tab `Cơ hội` chứa thanh tìm kiếm, bộ lọc lĩnh vực, bộ lọc địa điểm và nút cam `AI gợi ý dự án phù hợp`.

Sau khi bấm nút:

1. Một khối xử lý inline xuất hiện ngay dưới toolbar; không rời trang.
2. Khi hoàn tất, khối `Top 3 dự án AI đề xuất cho bạn` xuất hiện trước danh sách thường.
3. Mỗi thẻ hiển thị hạng, dự án/đơn vị, điểm `/100`, lý do, kỹ năng phù hợp, kỹ năng thiếu, kết quả dự kiến, nguồn phân tích và CTA.
4. Bên dưới Top 3 là `Tất cả cơ hội đang mở`; tìm kiếm và bộ lọc hiện tại vẫn áp dụng cho danh sách thường.
5. `Phân tích lại` tạo run mới. Trong lúc chờ, Top 3 gần nhất vẫn được giữ nếu còn hợp lệ và được gắn nhãn kết quả gần nhất.

Nội dung AI dùng `textContent`; không render HTML do model trả về.

## Phương án kiến trúc

Ba phương án đã được cân nhắc:

1. Gemini tự đọc và tự chấm toàn bộ: nhanh nhưng điểm thiếu ổn định, khó giải thích và có nguy cơ tạo nhận xét chung chung.
2. Công thức thuần túy: ổn định nhưng phân tích ngữ cảnh và giá trị phát triển hạn chế.
3. Hybrid: lọc/chấm điểm từ database, Gemini phân tích ngữ cảnh và diễn giải; đây là phương án được duyệt.

Không gọi Gemini với toàn bộ catalog. Backend lọc điều kiện bắt buộc và chấm điểm sơ bộ trước, sau đó chỉ gửi tối đa 10 ứng viên tốt nhất. Gemini trả về đúng 3 `catalog_id` từ allow-list đó.

## Thành phần và ranh giới

### 1. Learner match profile builder

Tái sử dụng hạ tầng snapshot hiện tại để tạo hồ sơ chỉ gồm dữ liệu được consent:

- Cấp học/education band và thông tin học tập cần thiết cho eligibility.
- Điểm đánh giá, bản đồ năng khiếu và định hướng.
- Kỹ năng, điểm kỹ năng và trạng thái xác thực.
- Hoạt động, dự án và kinh nghiệm đã tham gia.
- Đánh giá đã công bố.

Builder không đưa email, số điện thoại, địa chỉ chi tiết, dữ liệu sức khỏe hoặc đặc điểm được bảo vệ vào payload Gemini.

### 2. Opportunity candidate source

Tái sử dụng nguồn catalog/opportunity hiện có nhưng chuẩn hóa mọi ứng viên thành một contract chung:

```json
{
  "catalog_id": "uuid",
  "catalog_type": "project",
  "title": "Smart Campus IoT",
  "provider_name": "FPT Education",
  "description": "...",
  "required_skills": [{"code": "python", "minimum_score": 60}],
  "learning_outcomes": ["iot_basics", "sensor_data"],
  "education_bands": ["upper_secondary", "college"],
  "difficulty": "intermediate",
  "location": "Ha Noi",
  "deadline_at": "2026-09-30T23:59:59Z",
  "availability": {"remaining": 12},
  "status": "active",
  "canonical_url": "/app/learner/opportunity.php?..."
}
```

Dự án/cơ hội thiếu `catalog_id`, đã đóng, quá hạn, hết chỗ hoặc không phù hợp education band bị loại trước khi chấm điểm.

### 3. Structured opportunity scorer

Scorer thuần dữ liệu trả về `structured_score` từ 0 đến 100 và breakdown:

| Thành phần | Trọng số |
|---|---:|
| Kỹ năng hiện tại đáp ứng yêu cầu | 35 |
| Kết quả đánh giá và định hướng năng khiếu | 25 |
| Hoạt động, kinh nghiệm liên quan | 15 |
| Tiềm năng phát triển từ khoảng thiếu kỹ năng vừa sức | 15 |
| Điều kiện tham gia và tính khả thi | 10 |

Deadline, trạng thái hoạt động, education band và điều kiện bắt buộc là hard gate. Không cộng điểm để bù cho việc không đủ điều kiện.

### 4. Gemini opportunity analyzer

Gemini nhận hồ sơ đã lọc, tối đa 10 ứng viên, breakdown có cấu trúc và evidence reference. Model phải:

- Chỉ chọn `catalog_id` trong allow-list.
- Trả đúng Top 3, không trùng `catalog_id`.
- Cho `gemini_score` từ 0 đến 100.
- Viết lý do riêng, gắn với dự án và bằng chứng cụ thể.
- Chỉ chọn matched/missing skill từ danh mục kỹ năng đã cung cấp.
- Chỉ mô tả learning outcome đã có trong bản ghi dự án hoặc được suy ra trực tiếp từ mô tả đã xác minh; nội dung suy ra phải dùng ngôn ngữ `có thể phát triển`, không khẳng định chắc chắn.
- Không chẩn đoán, suy luận thuộc tính nhạy cảm hoặc cam kết tuyển dụng/đầu ra.

### 5. Match composer và validator

Điểm cuối:

```text
match_score = round(0.70 * structured_score + 0.30 * gemini_score)
```

Validator kiểm tra:

- Có đúng 3 item khi catalog còn ít nhất 3 ứng viên hợp lệ; nếu ít hơn thì trả số item thực tế và nêu trạng thái catalog thiếu.
- Điểm thành phần và điểm cuối nằm trong `0..100`.
- `catalog_id` tồn tại, còn mở và khớp evidence trên cùng item.
- Matched skill có trong hồ sơ người học và yêu cầu dự án.
- Missing skill có trong yêu cầu/outcome dự án nhưng chưa đạt ngưỡng.
- Mọi nguồn phân tích ánh xạ được về snapshot evidence.
- Không có lý do trùng nguyên văn hoặc gần như giống nhau giữa các item.
- Không chứa nội dung hứa chắc chắn được tuyển, đạt giải, nhập học hoặc có việc làm.

Nếu output Gemini sai schema, validator từ chối toàn bộ run và retry một lần với cùng snapshot/idempotency key. Nếu vẫn sai, hệ thống không hiển thị kết quả mới.

### 6. Persistence và API

Tạo endpoint dành riêng cho trải nghiệm này:

- `GET /app/learner/api/v1/opportunity-matches.php`: lấy run thành công gần nhất còn hợp lệ.
- `POST /app/learner/api/v1/opportunity-matches.php`: tạo hoặc làm mới phân tích; bắt buộc CSRF, session learner, consent, idempotency key và rate limit.

Tái sử dụng recommendation run/snapshot/evidence/audit hiện có. Forward migration mở rộng item để lưu có cấu trúc:

- `catalogId`.
- `structuredScore`.
- `geminiScore`.
- `matchScore`.
- `analysisJson`, gồm `why_fit`, `matched_skills`, `missing_skills`, `expected_outcomes`, `score_breakdown` và `evidence_refs`.

Các cột mới nullable cho item cũ nhưng bắt buộc ở tầng domain/validator đối với opportunity-match run. Database giữ snapshot hash, provider, model version, prompt version, generated time và trạng thái để giải thích kết quả cũ.

## API response đã chuẩn hóa

```json
{
  "state": "ready_model",
  "generated_at": "2026-08-29T09:00:00Z",
  "items": [
    {
      "item_id": "uuid",
      "catalog_id": "project-uuid",
      "catalog_type": "project",
      "rank": 1,
      "match_score": 92,
      "score_label": "Rất phù hợp",
      "why_fit": "...",
      "matched_skills": [{"code": "python", "label": "Python"}],
      "missing_skills": [{"code": "iot_basics", "label": "IoT cơ bản"}],
      "expected_outcomes": ["Xây dựng prototype ESP32"],
      "evidence": [],
      "canonical_url": "/app/learner/opportunity.php?..."
    }
  ]
}
```

Frontend không đọc `structured_score` hoặc `gemini_score`; hai điểm thành phần được lưu cho audit. Người học chỉ thấy `match_score`, lý do và nguồn dữ liệu dễ hiểu.

## Trạng thái và xử lý lỗi

- `not_generated`: chưa từng bấm AI; chỉ hiển thị nút tạo gợi ý.
- `consent_required`: giải thích ngắn và dẫn tới quản lý quyền dữ liệu.
- `insufficient_data`: nêu nhóm dữ liệu còn thiếu, không gọi là lỗi Gemini.
- `processing`: khóa nút sinh trùng; hiển thị tiến trình inline.
- `ready_model`: hiển thị Top 3 mới nhất.
- `stale_model`: giữ Top 3 gần nhất, gắn nhãn đang dùng kết quả trước đó và cho phép thử lại.
- `catalog_empty`: không có đủ dự án/cơ hội đang mở phù hợp điều kiện.
- `provider_unavailable`, `rate_limited`, `invalid_response`: thông báo an toàn, cho thử lại khi phù hợp; không lộ API key, prompt, stack trace hoặc raw payload.

Kết quả cũ chỉ được hiển thị nếu cả ba `catalog_id` vẫn còn hợp lệ. Item đã đóng/quá hạn bị loại; nếu còn dưới 3 item, UI hiển thị số item còn hợp lệ và mời phân tích lại.

## An toàn cho học sinh, sinh viên

- Chỉ chạy trong learner portal và chỉ cho chính chủ hồ sơ.
- Không dùng giới tính, tôn giáo, sức khỏe, dân tộc, khuyết tật, tình trạng hôn nhân hoặc thuộc tính nhạy cảm khác.
- Education band chỉ dùng để bảo đảm eligibility và độ khó phù hợp, không dùng để xếp hạng giá trị người học.
- Với người học chưa thành niên, chỉ dùng scope được consent và không tự động chia sẻ hồ sơ ra bên ngoài.
- Copy UI dùng `mức độ phù hợp tham khảo`, `có thể phát triển`, `nên bổ sung`; không dùng ngôn ngữ bảo đảm kết quả.

## Dữ liệu cần bổ sung

Nguồn dự án hiện tại chưa bảo đảm có kỹ năng yêu cầu và learning outcome có cấu trúc. Trước khi bật tính năng cho dữ liệu thật, schema/catalog adapter phải cung cấp tối thiểu:

- Required skill code và ngưỡng điểm tùy chọn.
- Learning outcome skill code/label.
- Education band, độ khó và điều kiện bắt buộc.
- Thời hạn, trạng thái, số chỗ còn lại và canonical URL.

Nếu dự án thiếu các trường cốt lõi, dự án không được gửi vào Top 10. Gemini không được tự tạo phần dữ liệu còn thiếu.

## Kiểm thử

### Domain và scoring

- Structured score đúng trọng số và không vượt `0..100`.
- Hard gate loại dự án đóng, quá hạn, hết chỗ hoặc sai education band.
- Công thức `70/30` cho kết quả xác định từ hai điểm đầu vào.
- Matched/missing skill được tính từ code canonical, không so sánh bằng chuỗi hiển thị.

### Gemini contract và an toàn

- Parser chấp nhận đúng schema Top 3.
- Validator từ chối catalog ID bịa, ID trùng, điểm ngoài biên, nguồn không tồn tại và claim bị cấm.
- Validator từ chối ba lý do giống nhau hoặc gần như giống nhau.
- Mỗi item giữ title/URL canonical từ database thay vì title/URL do model trả về.

### API và persistence

- GET/POST yêu cầu learner auth, consent và CSRF đúng.
- Idempotency không tạo hai run cho cùng request.
- Rate limit, retry và stale result hoạt động đúng.
- Run lưu model/prompt version, snapshot hash và ba score mà không lưu dữ liệu nhạy cảm ngoài snapshot cho phép.

### UI

- Nút AI chỉ xuất hiện trong tab `Cơ hội`.
- Không có tab AI, modal, drawer hoặc điều hướng sang trang AI khác.
- Top 3 nằm trước `Tất cả cơ hội đang mở`.
- Ba thẻ có nội dung riêng và link đúng catalog item.
- Loading, consent, insufficient, stale, error, empty và mobile layout không vỡ.
- Nội dung model dùng `textContent`; keyboard focus và screen reader status hoạt động.

## Tiêu chí nghiệm thu

1. Người học bấm một nút trong tab Cơ hội và nhận tối đa Top 3 dự án/cơ hội thật từ database.
2. Khi có ít nhất 3 ứng viên hợp lệ, kết quả chứa đúng 3 catalog ID khác nhau.
3. Điểm hiển thị dùng công thức 70% structured và 30% Gemini.
4. Mỗi thẻ có lý do, matched skill, missing skill, outcome và evidence riêng; không tái sử dụng cùng một phân tích.
5. Không có title, URL, project ID, deadline hoặc nguồn dữ liệu do Gemini tự tạo.
6. Kết quả AI nằm đầu danh sách; danh sách cơ hội thường vẫn hoạt động độc lập bên dưới.
7. Không ảnh hưởng trang roadmap AI, dashboard hoặc portal của vai trò khác.
8. Test domain, API, persistence, provider, UI và security liên quan đều pass.
