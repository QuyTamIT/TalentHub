# Issue 04 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Produce a single, current A4 portrait talent passport PDF with compact, useful evidence.

**Architecture:** Build a print-specific view model with bounded sections and use print CSS plus a measured browser/PDF check. Screen layout remains unchanged.

**Tech Stack:** PHP, CSS print media, existing `window.print()` JavaScript, browser/PDF rendering.

## Chốt phạm vi mới nhất theo yêu cầu người dùng — 2026-09-09

**Ưu tiên luồng dữ liệu và xuất CV khổ A4. Phần bố cục/giao diện A4 sẽ chỉnh sau, chưa nghiệm thu thiết kế cuối cùng.** Giữ bản xem trước A4 cơ bản đang có; không tiếp tục trang trí hoặc thay đổi cách bố trí trong đợt này.

- [x] Có trang xem trước và luồng xuất PDF A4 riêng cho sinh viên đăng nhập.
- [x] Mỗi lần mở trang hoặc bấm **Lấy dữ liệu mới & xuất PDF** đọc dữ liệu hiện tại từ database của đúng sinh viên; không lấy nội dung từ ảnh chụp dashboard, mock hoặc kết quả AI cũ. Trang đang mở không tự cập nhật liên tục; nút xuất luôn tải lại trước khi in.
- [x] Đánh giá giảng viên đã công bố, trạng thái dự án và đơn thực tập được đọc lại khi xuất. Hoạt động chỉ ghi nhận tham gia ngoại khóa đã xác nhận; không suy ra kỹ năng hoặc tự ghi “tham gia thường xuyên”.
- [x] Kỹ năng chỉ lấy xác nhận giảng viên/tiêu chí đánh giá hợp lệ, bao gồm đánh giá dự án; việc đăng ký hoạt động, có tag dự án hoặc được nhận thực tập không tự chứng minh thành thạo.
- [ ] Nối nguồn xác nhận bắt đầu/hoàn thành thực tập, đánh giá kỹ năng thực tập và nghiệm thu bài nộp dự án khi workflow #07 được triển khai. Hiện chưa có đủ nguồn này, nên **chưa tuyên bố hoàn tất luồng liên thông toàn bộ vòng đời**.
- [ ] **Bố cục A4 sẽ chỉnh sau:** sắp xếp khối, khoảng cách, kiểu chữ, màu sắc, mức độ chọn lọc và thiết kế CV chuyên nghiệp cuối cùng. Khi chỉnh phải giữ đúng nguồn dữ liệu, khổ A4 và chạy lại kiểm thử xuất PDF.

“Mới nhất” là dữ liệu đã lưu/công bố hợp lệ tại thời điểm truy vấn, không bao gồm thay đổi chưa lưu hoặc đánh giá nháp. PDF đã tải là bản chụp tại thời điểm xuất; cần xuất lại để nhận thay đổi mới.

## Bố cục cơ bản đang có — chưa phải thiết kế cuối cùng

CV một cột, A4 dọc, nền trắng, điểm nhấn xanh đậm; có xem trước riêng và xuất PDF chữ thật. Mỗi lần xuất phải tải lại từ database qua phiên sinh viên, không dùng mock/cache cũ, không tạo link chia sẻ tự động. Bản PDF đã tải là snapshot có thời điểm, không tự cập nhật.

Thay đổi triển khai so với kế hoạch ban đầu: thêm reader/view-model riêng cho CV, không mở rộng aggregate dùng chung của AI và các vai trò khác. Bảng hiện có chỉ ghi đơn thực tập accepted, chưa xác nhận bắt đầu/hoàn thành: chỉ được ghi “Đã được tiếp nhận”, không phải kinh nghiệm đã hoàn thành. Không tự tạo schema/workflow thuộc #07.

Các file: `app/learner/data/Database/DatabasePassportCvRepository.php` (đọc scoped, transaction snapshot), `app/learner/data/ReadModel/PassportCvViewModel.php` (lọc evidence, giới hạn và sắp xếp), `app/learner/talent-passport-cv.php` (auth/no-store), `app/learner/includes/passport-cv-template.php`, `assets/css/learner-passport-cv.css`, `assets/js/learner-passport-cv.js` (refresh trước print, đo tràn). Nút xuất trong Passport chuyển tới preview. Không thay đổi trang chia sẻ.

- [x] Test dữ liệu: không suy ra kỹ năng từ giờ hoạt động; loại nháp/thu hồi; trạng thái dự án đúng; accepted không thành completed; không đọc sinh viên khác.
- [x] Reader + view model + trang preview và CSS A4 có giới hạn số mục, chuỗi dài; nội dung chính tối thiểu 10pt, chú thích 9pt; không clip âm thầm.
- [x] Nút xuất tải lại snapshot mới; không tạo QR/chia sẻ, báo lỗi thay vì xuất dữ liệu cũ.
- [x] Render fixtures thiếu/dày dữ liệu bằng Chromium, kiểm tra PDF đúng 1 trang A4, chữ trích xuất được và không tràn; xem ảnh render.
- [x] Kiểm tra đọc MySQL local; cập nhật ISSUES_LOG và ghi rõ giới hạn #07.

## Kết quả triển khai và nghiệm thu — 2026-09-09

**Đã triển khai và kiểm thử phạm vi xuất CV A4 trên dữ liệu hiện có.** Không đồng nghĩa hoàn thành các workflow thực tập/nộp đồ án của Issue #07. Không merge/commit hoặc chạy migration trong task này.

### Cách sử dụng

1. Đăng nhập sinh viên → Talent Passport → **CV A4 / Xuất PDF**.
2. Trang `talent-passport-cv.php` hiển thị CV chọn lọc từ database. Các mục không có bằng chứng sẽ không được bịa để lấp chỗ trống.
3. Bấm **Lấy dữ liệu mới & xuất PDF**. Trình duyệt tải lại trang với no-store, đọc snapshot mới rồi mở hộp in.
4. Chọn **Save as PDF / Lưu dưới dạng PDF**, A4, tỷ lệ 100%, tắt header/footer của trình duyệt. Đường xuất được nghiệm thu là trang CV mới, không phải Ctrl+P của dashboard Passport cũ.

### Dữ liệu và phạm vi

- Reader CV riêng, không thay đổi aggregate/repository hiện có mà AI hoặc vai trò khác sử dụng. Không tạo schema mới.
- Trang xuất xác thực phiên, vai trò sinh viên, tài khoản active và permission `student_profile.read_own`; lấy studentId từ DB theo user phiên, bỏ qua identity trên query string. Không gọi demo autologin hoặc onboarding reconciliation khi xuất.
- Đọc cùng transaction; mỗi lượt export là snapshot tại thời điểm ghi ở chân trang. File PDF đã tải không thể tự cập nhật. Ctrl+P trong preview chỉ in snapshot đang xem; nút xuất mới bảo đảm đọc lại dữ liệu.
- Kỹ năng: tối đa 4 kỹ năng giảng viên xác nhận/đánh giá đã công bố với bản ghi xác nhận hợp lệ. Loại activity/import/self-declared; khi evidence gần nhất bị từ chối, thu hồi hoặc hết hạn, không dùng dòng verified cũ. Không chuyển tag dự án thành điểm thành thạo.
- Đánh giá: dùng `learner_evaluations` revision mới nhất và các assessment legacy published chưa được thay thế. Bỏ bản nháp/thu hồi và đánh giá ngữ cảnh hoạt động. Chỉ xuất 1 nhận xét mới nhất có nội dung; không tự xếp loại/phần trăm năng lực.
- Dự án: tối đa 2 dự án của membership active, mới nhất trước; vai trò/đóng góp từ DB. “Dự án đã hoàn thành” là trạng thái dự án, không khẳng định cá nhân đã nộp/được duyệt đồ án. Thành viên đã rời/bị loại không được gộp vào kinh nghiệm hiện tại.
- Thực tập: tối đa 1 đơn accepted, nhãn **Đã được tiếp nhận; chưa xác nhận bắt đầu**. Không gọi đó là kinh nghiệm hoàn thành; đơn pending/declined/withdrawn không vào CV.
- Ngoại khóa: tối đa 2 hoạt động có experience confirmed; không cộng vào kỹ năng và không tự gọi “tham gia thường xuyên”.
- Rút gọn mô tả/tiêu đề bằng dấu `…`, có ghi số mục không đưa vào bản chọn lọc; họ tên/email/điện thoại giữ đầy đủ. Không cắt nội dung bằng overflow hidden. Nếu dữ liệu bất thường vẫn vượt khổ, báo lỗi và không tự xuất bản bị mất chữ; không hứa mọi chuỗi tùy ý đều luôn xuất được.

### Kiểm thử đã thực hiện

- `tests/learner_passport_cv_test.php`: 6 kiểm tra view-model PASS (nguồn kỹ năng, trạng thái dự án/thực tập, nháp, giới hạn lịch sử, không bịa vai trò).
- `tests/learner_passport_cv_repository_test.php`: 4 kiểm tra SQL SQLite cô lập PASS (ownership, evidence thu hồi, snapshot sau thay đổi, ngoại khóa tách riêng).
- `tests/learner_passport_cv_endpoint_test.php student|guest|teacher`: kiểm thử route PHP với session test và DB local: 200/401/403 đúng; không thực hiện đăng nhập người dùng qua giao diện trình duyệt.
- `tests/learner_passport_cv_ui_test.js`: 2/2 PASS (chặn tràn, hợp đồng refresh/no-store/ownership/no-share).
- `tests/learner_passport_cv_pdf_test.js`: Edge/Chromium headless + pdf-lib, 3/3 PDF (normal/sparse/long) đúng **1 trang 210×297mm**, không tràn ngang/dọc. Chiều cao nội dung lần cuối: 845px / 242px / 986px, dưới ngân sách 268mm.
- Cùng bộ kiểm thử trình duyệt: 2 kịch bản PASS — bấm xuất tạo request mới, nhận nội dung đã thay đổi rồi mới gọi in; nội dung vượt khổ hiển thị lỗi và không tự gọi in. Dữ liệu thay đổi được mô phỏng tại route, không sửa hồ sơ thật.
- Poppler render PNG để kiểm tra trực quan; pypdf trích xuất được tiếng Việt, tên và kỹ năng (chữ thật, không raster).
- `.codex_tmp/issue04-read-probe.php`: đọc snapshot từ 3 hồ sơ local thành công. PDF QA sử dụng dữ liệu giả lập, không xuất hay gửi thông tin sinh viên thật ra bên ngoài.

Lệnh môi trường: PHP `D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`; Node PDF test cần `NODE_PATH` trỏ tới bundled node_modules có `playwright`/`pdf-lib` và Edge được cài. QA artifacts tại `.codex_tmp/issue04-pdf/`, không commit.

### Bàn giao Issue #07

**Cập nhật nối tiếp 2026-09-09:** #07 đã cung cấp báo cáo cá nhân, mentor xác nhận/thu hồi và nguồn `verifiedForStudent`. CV đã nối nguồn này; thực tập verified thay accepted tương ứng, hiển thị ngày/giờ, kỹ năng chỉ lấy xác nhận trực tiếp. Bài nộp dự án được nghiệm thu không đồng nghĩa toàn dự án hoàn tất. Thông tin “chưa có nguồn #07” ở phần kết quả ban đầu phía trên là lịch sử trước đợt tích hợp này. Kiểm thử thêm fixture portfolio (867px, 1 trang A4) và MySQL duyệt → CV → thu hồi PASS. Xem [plan #07](plan-issue-07.md) và [bàn giao Giảng viên](handoff-issue-07-teacher.md). **Bố cục A4 vẫn sẽ chỉnh sau**.
