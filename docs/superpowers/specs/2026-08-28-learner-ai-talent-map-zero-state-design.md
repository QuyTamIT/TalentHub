# Thiết kế bản đồ năng khiếu luôn hiển thị

## Mục tiêu

Khối `Bản đồ năng khiếu` trên trang `AI gợi ý` phải luôn có hình trực quan và các giá trị phần trăm. Khi dữ liệu chưa có hoặc chưa đủ, giao diện hiển thị điểm `0%` thay vì để một card trắng lớn hoặc chỉ hiện thông báo lỗi dữ liệu.

Thiết kế không tạo điểm năng lực giả. `0%` trong trường hợp thiếu dữ liệu mang nghĩa “chưa có dữ liệu để xác định”, không mang nghĩa học viên không có năng lực.

## Nguyên nhân hiện tại

Frontend chỉ vẽ radar khi `talent_map` có ít nhất ba record. Roadmap đang hoạt động có hai record hợp lệ nên renderer chuyển sang nội dung `Chưa đủ dữ liệu để vẽ bản đồ năng khiếu.`

Contract hiện tại cho phép Gemini bỏ qua `talent_map` hoặc trả ít hơn ba record:

- `talent_map` chưa nằm trong danh sách trường bắt buộc của output schema.
- Schema chưa khai báo `minItems` và `maxItems`.
- Validator chấp nhận danh sách rỗng hoặc danh sách có một đến hai record.

## Mô hình ba trục chuẩn

Bản đồ dùng ba trục cố định, theo đúng ngôn ngữ sản phẩm đã duyệt:

1. `Tư duy Logic & Hệ thống`
2. `Kỹ năng Thực hành & Thao tác`
3. `Tổ chức & Điều phối`

Mỗi trục có:

- `field`: nhãn chuẩn.
- `score`: số từ `0` đến `100` sau khi chuẩn hóa.
- `hasEvidence`: cho biết điểm đến từ dữ liệu AI hợp lệ hay chỉ là giá trị 0 thay thế.
- `evidence_ref_ids`: danh sách nguồn bằng chứng khi có.

## Quy tắc hiển thị

### Đủ dữ liệu

Khi cả ba trục có điểm hợp lệ, renderer vẽ radar SVG như hiện tại. Nhãn và phần trăm được hiển thị quanh radar; vùng dữ liệu dùng màu cam theo design token `--primary`.

### Dữ liệu chưa đủ

Khi chỉ có một hoặc hai nhóm có thể ánh xạ:

- Trục có dữ liệu dùng điểm thật.
- Trục thiếu dùng `0%` và trạng thái `hasEvidence: false`.
- Radar vẫn được vẽ đủ ba trục.
- Bên dưới radar có chú thích ngắn: `0% là dữ liệu chưa được xác định, không phải đánh giá năng lực thấp.`
- Nhãn trục thiếu có trạng thái thị giác trung tính nhưng vẫn đọc được.

### Không có dữ liệu

Khi `talent_map` rỗng hoặc không hợp lệ:

- Vẽ radar ba trục co tại tâm với cả ba điểm bằng `0%`.
- Hiển thị đầy đủ ba nhãn chuẩn và `0%`.
- Hiển thị chú thích giải thích ý nghĩa của 0%.
- Không dùng card trắng, skeleton kéo dài hoặc dữ liệu mẫu.

## Chuẩn hóa và ánh xạ dữ liệu cũ

Frontend tạo một hàm thuần để chuyển `talent_map` cũ sang ba trục chuẩn. Việc ánh xạ chỉ dựa trên từ khóa rõ ràng, không suy luận điểm mới:

- `logic`, `hệ thống`, `phân tích`, `tư duy` → `Tư duy Logic & Hệ thống`.
- `thực hành`, `thao tác`, `kỹ thuật`, `ứng dụng` → `Kỹ năng Thực hành & Thao tác`.
- `tổ chức`, `điều phối`, `quản lý`, `quy trình` → `Tổ chức & Điều phối`.

Nếu một record cũ chứa từ khóa của nhiều trục, điểm chỉ được gán cho trục có từ khóa khớp ưu tiên sớm nhất theo thứ tự trên. Không nhân bản một điểm sang nhiều trục. Record không khớp rõ ràng không được dùng để nâng điểm; các trục còn thiếu giữ `0%`.

Nếu nhiều record cùng khớp một trục, dùng record có điểm cao nhất và giữ chính `evidence_ref_ids` của record đó. Điểm `0..1` được đổi sang `0..100`; điểm ngoài miền được chặn về `0..100` theo quy tắc frontend hiện tại.

## Contract Gemini cho dữ liệu mới

`RoadmapPromptRegistry` được cập nhật để:

- Đưa `talent_map` vào danh sách trường bắt buộc.
- Yêu cầu đúng ba record.
- Giới hạn `field` bằng enum ba nhãn chuẩn.
- Yêu cầu mỗi record có `score` trong `0..1` và ít nhất một `evidence_ref_id` hợp lệ.
- Nêu rõ mỗi nhãn chỉ xuất hiện một lần và không gộp hai nhóm vào cùng một record.

`RoadmapAnalysisValidator` kiểm tra kết quả có đúng ba record, đủ ba nhãn chuẩn và không trùng nhãn. Kết quả vi phạm bị coi là provider payload không hợp lệ; hệ thống giữ roadmap gần nhất thay vì lưu bản thiếu dữ liệu.

## Luồng dữ liệu

```text
Gemini 3.7 Flash
  → output schema bắt buộc 3 trục
  → RoadmapAnalysisValidator kiểm tra đủ/duy nhất/có bằng chứng
  → lưu roadmap
  → API trả talent_map
  → buildRoadmapViewModel ánh xạ về 3 trục chuẩn
  → renderTalentRadar luôn nhận đúng 3 trục
```

Roadmap cũ đi thẳng qua bước ánh xạ tương thích. Không cần migration và không sửa dữ liệu đã lưu.

## Khả năng truy cập và giao diện

- SVG giữ `role="img"` và `aria-label` liệt kê cả ba nhãn cùng phần trăm.
- Chú thích 0% là nội dung văn bản thật, không chỉ biểu đạt bằng màu.
- Màu dùng các token hiện có: cam cho dữ liệu có bằng chứng, xám trung tính cho trục chưa xác định, nền trắng và border `#E2E8F0`.
- Font tiếp tục dùng `Be Vietnam Pro`.
- Radar giữ kích thước responsive hiện tại và không tạo cuộn ngang trên màn hình nhỏ.

## Xử lý lỗi

- Payload rỗng hoặc thiếu: biểu đồ ba trục 0% vẫn hiển thị.
- Payload cũ chỉ có một hoặc hai record: ánh xạ record có thể xác định; phần còn lại 0%.
- Payload mới từ Gemini sai contract: không lưu; giữ roadmap gần nhất và sử dụng luồng lỗi cập nhật hiện có.
- Không hiển thị prompt, chain-of-thought, API key hoặc dữ liệu đánh giá thô.

## Kiểm thử chấp nhận

1. `talent_map = []` tạo radar SVG ba trục, mỗi trục `0%`, không hiện câu `Chưa đủ dữ liệu để vẽ...`.
2. Một record cũ về logic chỉ cập nhật trục Logic; hai trục còn lại bằng 0.
3. Record kết hợp nhiều nhóm không bị nhân bản điểm sang nhiều trục.
4. Ba record chuẩn tạo radar với đúng ba điểm và phần trăm đã chuẩn hóa.
5. Output schema yêu cầu `talent_map`, đúng ba item và enum ba nhãn.
6. Validator từ chối thiếu trục, nhãn trùng, nhãn ngoài danh sách hoặc thiếu bằng chứng.
7. Các test roadmap, recommendation và provider contract hiện có vẫn vượt qua.

## Ngoài phạm vi

- Không thay đổi thuật toán chấm điểm từ các bài Holland, MBTI, DISC hoặc Multiple Intelligence.
- Không backfill hoặc chỉnh sửa trực tiếp các roadmap đã lưu.
- Không thêm thư viện biểu đồ bên thứ ba.
- Không thay đổi các khối huy hiệu, chứng chỉ hoặc roadmap 90 ngày.
