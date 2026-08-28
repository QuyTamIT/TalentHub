# Thiết kế tinh gọn trang AI gợi ý cho học viên

## Mục tiêu

Tinh gọn trang `AI gợi ý` để học viên hiểu ngay định hướng phát triển, vị trí hiện tại trong roadmap 90 ngày và bước nên làm tiếp theo. Giao diện phải bám mockup đã duyệt, dùng đúng hệ màu TalentHub và font `Be Vietnam Pro`, đồng thời tiếp tục hiển thị hoàn toàn từ dữ liệu API hiện có.

## Phạm vi

- Sắp xếp lại trang `app/learner/ai-recommendations.php` và các style chỉ áp dụng cho `.learner-page-ai`.
- Cập nhật renderer trong `assets/js/learner-ai-roadmap.js` để tạo roadmap và radar trực quan.
- Giữ nguyên API, hợp đồng roadmap, luồng tạo/cập nhật, trạng thái loading/error/consent/insufficient và các hành động đang hoạt động.
- Không thay đổi trang của giáo viên, nhà trường, doanh nghiệp hoặc quản trị viên.
- Không thêm thư viện biểu đồ; radar được dựng bằng SVG nội tuyến an toàn.

## Hệ thống thị giác

- Font chính: `Be Vietnam Pro`, sans-serif.
- Cỡ chữ: tiêu đề trang 32px; tiêu đề roadmap 24px; tiêu đề section 20px; tiêu đề card 16px; nội dung 14px; metadata 12px.
- Màu chính: `#F97316`; hover `#EA580C`; nền cam nhạt `#FFF7ED`.
- Màu phụ: `#2563EB`; nền xanh nhạt `#EFF6FF`; màu tích cực `#16A34A`.
- Nền trang `#F8FAFC`; surface `#FFFFFF`; chữ chính `#0F172A`; chữ phụ `#64748B`; border `#E2E8F0`.
- Bo góc control 8px và card 12px. Bóng đổ rất nhẹ, không gradient và không glassmorphism.

## Kiến trúc thông tin

### 1. Header gọn

Header chỉ gồm nhãn `AI GỢI Ý`, tiêu đề `Lộ trình phát triển cá nhân`, trạng thái độ mới và nút `Cập nhật phân tích`. Bộ chọn phiên bản được giữ nhưng trình bày nhỏ, không cạnh tranh với tiêu đề.

### 2. Tổng quan và bước tiếp theo

Hàng đầu tiên có hai card:

- `Định hướng của bạn`: tóm tắt AI, hướng phát triển ưu tiên, độ tin cậy và số nguồn bằng chứng.
- `Việc nên làm tiếp theo`: lấy nhiệm vụ roadmap chưa hoàn thành đầu tiên, hiển thị một CTA `Tiếp tục lộ trình`.

Các hướng thay thế và insight dài không chiếm thêm card ở vùng đầu trang.

### 3. Roadmap phát triển 90 ngày

Roadmap là vùng lớn nhất của trang và luôn hiển thị ngay sau phần tổng quan.

- Thanh tiến độ tổng cho biết số nội dung hoàn thành trên tổng số và phần trăm tương ứng.
- Timeline gồm đúng ba chặng từ payload hiện có:
  - `01 — Khám phá`, ngày 1–30.
  - `02 — Thực hành`, ngày 31–60.
  - `03 — Bứt phá`, ngày 61–90.
- Mỗi chặng chỉ hiển thị mục tiêu ngắn và tối đa hai hướng hành động lấy từ task thật của chặng đó.
- Chặng hiện tại có nhãn `Bạn đang ở đây`; chặng hoàn thành dùng xanh lá; chặng hiện tại dùng cam; chặng tiếp theo dùng xanh dương/xám.
- Desktop dùng timeline ngang nối liền. Dưới 720px chuyển thành timeline dọc theo thứ tự thời gian.
- Tương tác cập nhật task hiện có vẫn được giữ. Nội dung chi tiết hơn của chặng có thể mở khi cần, nhưng mặc định trang phải gọn.

## Bản đồ năng khiếu và nhận định

Sau roadmap là một hàng hai cột:

- `Bản đồ năng khiếu`: SVG radar màu cam, grid trung tính, điểm dữ liệu rõ và nhãn phần trăm. Radar dùng tối đa tám chiều từ `talent_map`; với ba chiều sẽ tạo tam giác như mockup.
- `Nhận định nổi bật`: chỉ hiển thị một điểm mạnh, một điểm cần cải thiện và một xu hướng. Nút `Xem toàn bộ phân tích` mở phần phân tích đầy đủ gồm điểm mạnh, cải thiện, xu hướng, hướng phát triển và giả thuyết tăng trưởng.

Frontend phải hỗ trợ cả hai dạng score đang tồn tại:

- `0–1` được nhân 100 trước khi hiển thị, ví dụ `0.82` thành `82%`.
- `0–100` được giữ nguyên và chặn trong khoảng hợp lệ.

Không được tiếp tục làm tròn trực tiếp `0.82` thành `1%`.

## Nội dung thứ cấp

Các khối không cần xem ngay được gom thành các vùng có thể mở:

- `Gợi ý hoạt động & cơ hội phù hợp`.
- `Huy hiệu & chứng chỉ phù hợp`.
- `Dữ liệu AI đã sử dụng` và `Thông tin kỹ thuật`.

Các hook DOM và controller hiện có vẫn được giữ để không làm mất chức năng tải dữ liệu, feedback, version history, recommendation click tracking và credential matching.

## Luồng dữ liệu

1. Trang tiếp tục gọi `/app/learner/api/v1/ai-roadmap.php` qua client hiện có.
2. `buildRoadmapViewModel()` chuẩn hóa score, xác định tiến độ và chặng hiện tại từ phase/task thật.
3. DOM view render phần tổng quan, timeline, radar và các nhóm nội dung phụ bằng `textContent`/SVG DOM API; không dùng `innerHTML` cho dữ liệu AI.
4. Khi học viên hoàn thành task, controller hiện có cập nhật lạc quan rồi đồng bộ lại với API.
5. Loading, pending, consent, insufficient-data, stale, fallback-rule và source-error tiếp tục có trạng thái riêng.

## Khả năng truy cập và responsive

- Radar có `role="img"`, tên và mô tả; danh sách nhãn/điểm vẫn có trong DOM để screen reader đọc được.
- Timeline dùng heading và danh sách semantic, không phụ thuộc riêng vào màu để biểu thị trạng thái.
- CTA có vùng bấm tối thiểu 44px; focus ring rõ.
- Hiệu ứng chuyển động tuân theo `prefers-reduced-motion`.
- Tablet xếp hai card tổng quan hợp lý; mobile chuyển toàn bộ thành một cột và timeline dọc.

## Kiểm thử

- Bổ sung test thất bại trước cho chuẩn hóa `0.82 → 82` và giữ `72 → 72`.
- Bổ sung test renderer cho SVG radar có nhãn truy cập và điểm phần trăm đúng.
- Bổ sung test DOM contract cho bố cục tinh gọn, roadmap 90 ngày và các vùng thu gọn.
- Bổ sung test CSS contract cho font, token, timeline desktop/mobile và phạm vi `.learner-page-ai`.
- Chạy lại toàn bộ test roadmap hiện có để bảo đảm các trạng thái, controller và hành động không hồi quy.

## Tiêu chí hoàn thành

- Roadmap 90 ngày là nội dung nổi bật và có thể hiểu trong vài giây.
- Học viên thấy rõ chặng hiện tại, tiến độ và bước tiếp theo.
- Không còn lưới sáu card phân tích ngang hàng ở trạng thái mặc định.
- Bản đồ năng khiếu là biểu đồ thật và score `0–1` hiển thị đúng thang phần trăm.
- Trang bám mockup đã duyệt và các token thiết kế đã cung cấp.
- Không mất chức năng AI roadmap, recommendation, credential, feedback, version hoặc error state hiện có.
