# Bàn giao thay đổi Giảng viên — Issue #07

Trạng thái: đã triển khai và kiểm thử luồng báo cáo/duyệt/liên kết CV trên MySQL local, 2026-09-09. Người dùng đã cho phép phần duyệt tối thiểu Giảng viên và yêu cầu **không sửa ISSUES_LOG.md**; file đó được giữ nguyên trong #07.

## File thuộc Giảng viên

| File | Thao tác | Mục đích |
|---|---|---|
| `app/teacher/portfolio-reviews.php` | Tạo mới | Trang duyệt báo cáo dự án/thực tập; session Teacher, kiểm tra user active/role thực tế; không dùng demo autologin. |
| `app/teacher/api/portfolio.php` | Tạo mới | Route cố định role Teacher cho GET danh sách được phân công và POST quyết định duyệt. |
| `app/teacher/includes/sidebar.php` | Chỉnh sửa | Thêm đường dẫn “Duyệt báo cáo dự án / thực tập”; không đổi các menu/chức năng đang có. |

Không sửa `grading.php`, service/repository chấm rubric, quyền đánh giá lớp cũ hoặc luồng tuyển dụng Doanh nghiệp.

## Mã dùng chung liên quan Giảng viên

- `src/Modules/Student/Repository/PortfolioRepository.php` (mới): kiểm tra mentor của dự án/phân công thực tập, trạng thái và phiên bản; lưu lịch sử và kỹ năng được xác nhận.
- `src/Modules/Student/Service/PortfolioAccess.php` (mới): xác thực role/user hiện tại từ database.
- `src/Modules/Student/Service/PortfolioHttp.php` (mới): phân luồng GET/POST, whitelist payload, CSRF, không cho truyền danh tính chủ thể.
- `app/learner/api/portfolio-endpoint.php` (mới): bootstrap session/DB, trả JSON/no-store/lỗi an toàn; được hai route role cố định gọi.
- `assets/js/learner-portfolio.js`, `assets/css/learner-portfolio.css` (mới): form/panel dùng chung, teacher chỉ nhận nút quyết định thích hợp. Phân quyền vẫn bắt buộc ở server.
- `app/learner/data/Service/NotificationService.php` (sửa): thêm loại thông báo `portfolio_submitted`, `portfolio_reviewed`, đường dẫn trang duyệt và Profile; giữ nguyên loại thông báo cũ.
- `Database/migrations/learner/021_create_learner_portfolio_reports.php` (mới): bảng báo cáo/lịch sử/kỹ năng riêng, không đổi trạng thái tuyển dụng hoặc xóa bảng cũ.

## Luồng dùng và quyền

1. Nhà trường phân công mentor dự án bằng `projects.mentorTeacherId`, thực tập bằng `internship_mentor_assignments` hiện có.
2. Sinh viên lưu nháp/gửi báo cáo. Giảng viên đăng nhập → menu **Duyệt báo cáo dự án / thực tập**.
3. Chỉ mentor hiện tại, cùng trường, được duyệt bản gửi của sinh viên thuộc phạm vi. Không có quyền duyệt hộ mọi sinh viên cho School/Admin trong route này.
4. **Xác nhận**: chỉ chọn kỹ năng có minh chứng thực tế; có thể không chọn kỹ năng. **Yêu cầu sửa** và **Thu hồi xác nhận** phải nhập lý do.
5. Mỗi thao tác dùng expectedVersion; dữ liệu đã thay đổi phải tải lại. Bản verified không được sinh viên sửa tại chỗ; tạo revision mới sẽ gỡ xác nhận hiện tại khỏi CV, giữ bản cũ trong lịch sử.
6. Hồ sơ và lần xuất CV tiếp theo chỉ coi dữ liệu verified là minh chứng. Dự án: nhãn bài nộp được nghiệm thu, không tự ghi toàn dự án hoàn thành. Thực tập: phân biệt đang thực tập/hoàn thành và ghi rõ giảng viên xác nhận.

## Kết quả nghiệm thu

- [x] Test lifecycle/ownership/mentor/trái trường/CSRF/version/history/thu hồi PASS; nháp không hiện cho Teacher, kể cả nháp trong history trả về.
- [x] Migration 021 APPLIED trên database local, chỉ thêm 4 bảng mới. MySQL test dùng database ngẫu nhiên riêng trong namespace kiểm thử được cấp quyền, dọn đúng database đó; không sửa hồ sơ sinh viên thật.
- [x] Form Sinh viên/Giảng viên qua 3 browser fixtures PASS; đây là browser với API mô phỏng. HTTP dispatch + SQL + notifier thật được kiểm thử riêng bằng MySQL cô lập, chưa thay thế UAT đăng nhập thủ công.
- [x] CV mapping verified/thu hồi/không trùng accepted PASS; MySQL duyệt internship hoàn thành với giờ lẻ 120.25 → CV → thu hồi PASS. GET read-only local 2 sinh viên/2 giảng viên và CV200 PASS.
- [x] 4 PDF fixtures đúng 1 trang A4 + 2 kiểm tra refresh/overflow PASS; 5 Ecosystem/Application UI regression PASS. Test thông báo cũ lỗi fixture thiếu enterprises.status (không sửa); hai file test đăng ký dự án cũ không tồn tại. Không khẳng định toàn bộ suite dự án đã qua.

**Để bên Giảng viên kiểm tra:** phân công mentor qua màn hình Nhà trường hiện có → Sinh viên gửi báo cáo → Teacher mở menu mới → duyệt với kỹ năng phù hợp → Sinh viên tải lại Profile/Passport và xuất CV → Teacher thu hồi kèm lý do → xuất lại để xác nhận minh chứng không còn. Không dùng School/Admin thay tài khoản Teacher ở route mới.

Không sửa service/repository Teacher cũ: phần kiểm tra mentor nằm ở repository Portfolio mới trong namespace Student dùng chung cho hai role. Không đổi giao diện tuyển dụng Doanh nghiệp, không gắn nhãn “Doanh nghiệp xác nhận”. Thiết kế lại bố cục A4 và đồng bộ nguồn kỹ năng portfolio mới vào AI matching không thuộc đợt này.

Chi tiết phạm vi và tiến độ: [plan-issue-07.md](plan-issue-07.md).
