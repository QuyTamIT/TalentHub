# Thiết kế tải ảnh bìa hoạt động từ máy giảng viên

## Mục tiêu

Cho phép giảng viên chọn ảnh bìa từ máy khi tạo hoặc chỉnh sửa hoạt động. Ảnh không bắt buộc; nếu hoạt động không có ảnh, giao diện sinh viên tiếp tục dùng ảnh mặc định hiện có.

Thay đổi không làm đổi quy trình nghiệp vụ bản nháp → nhà trường duyệt → công bố và không yêu cầu migration cơ sở dữ liệu.

## Phạm vi

- Thay trường nhập đường dẫn ảnh thủ công trong form giảng viên bằng trường chọn tệp.
- Hiển thị ảnh xem trước và tên tệp đã chọn.
- Lưu tệp tải lên trong `storage/activity-covers`.
- Tiếp tục lưu đường dẫn công khai trong cột `activity_details.coverImageUrl` và mô tả ảnh trong `coverImageAlt`.
- Khi chỉnh sửa, không chọn tệp mới thì giữ ảnh hiện tại; chọn tệp mới thì thay ảnh.
- Khi không có ảnh, trang sinh viên sử dụng ảnh mặc định hiện tại.

Ngoài phạm vi: thư viện ảnh, cắt ảnh, chỉnh sửa ảnh, lưu ảnh trên dịch vụ đám mây và thay đổi schema database.

## Giao diện

Form tạo/chỉnh sửa hoạt động dùng `multipart/form-data` và cung cấp:

- Trường chọn tệp `coverImageFile`, chấp nhận JPEG, PNG và WebP.
- Hướng dẫn dung lượng tối đa 5 MB.
- Khung xem trước ảnh bằng JavaScript trước khi gửi form.
- Trường mô tả ảnh cho trình đọc màn hình. Trường này chỉ bắt buộc khi có ảnh được tải lên hoặc hoạt động đang giữ ảnh hiện tại.
- Khi sửa hoạt động có ảnh, hiển thị ảnh hiện tại. Không chọn tệp mới đồng nghĩa giữ nguyên ảnh đó.

Không còn cho giảng viên nhập trực tiếp `coverImageUrl` để tránh đường dẫn sai hoặc không an toàn.

## Kiến trúc và luồng dữ liệu

1. Trình duyệt gửi dữ liệu hoạt động và tệp ảnh trong cùng form POST.
2. Controller trang giảng viên kiểm tra lỗi upload, kích thước và chuyển tệp tới thành phần lưu ảnh chuyên trách.
3. Thành phần lưu ảnh xác minh MIME từ nội dung tệp, kiểm tra tệp là ảnh đọc được và chỉ nhận JPEG, PNG hoặc WebP.
4. Ảnh được đặt tên ngẫu nhiên và ghi vào `storage/activity-covers`.
5. Đường dẫn `/storage/activity-covers/<random-name>.<ext>` được đưa vào payload hiện có dưới khóa `coverImageUrl`.
6. `TeacherActivityService` xác thực đường dẫn upload nội bộ bên cạnh các asset hoạt động hiện có.
7. `TeacherActivityRepository` tiếp tục ghi đường dẫn vào cột hiện có; không đổi database.
8. Read model phía sinh viên chấp nhận đường dẫn upload nội bộ và render ảnh. Nếu đường dẫn rỗng hoặc không hợp lệ, UI dùng ảnh mặc định.

Thành phần lưu ảnh độc lập với service nghiệp vụ hoạt động để việc kiểm tra filesystem và việc xác thực dữ liệu hoạt động có trách nhiệm rõ ràng.

## Tính nhất quán và vòng đời tệp

- Tệp mới được ghi trước khi gọi service lưu hoạt động.
- Nếu lưu hoạt động thất bại, tệp mới vừa tạo được xóa để tránh tệp mồ côi.
- Khi cập nhật thành công bằng ảnh mới, ảnh upload cũ mới được xóa.
- Chỉ xóa tệp nằm trong `storage/activity-covers`; không bao giờ xóa ảnh mẫu trong `app/learner/assets`.
- Không chọn ảnh khi tạo mới sẽ lưu `coverImageUrl` rỗng; fallback thuộc trách nhiệm giao diện sinh viên.
- Không chọn ảnh khi chỉnh sửa sẽ giữ `coverImageUrl` và `coverImageAlt` hiện có.

## Bảo mật và xác thực

- Giữ nguyên kiểm tra đăng nhập giảng viên, quyền sở hữu hoạt động và CSRF hiện tại.
- Giới hạn 5 MB và xử lý đầy đủ mã lỗi upload của PHP.
- Dùng MIME phát hiện từ nội dung tệp, không tin tên hoặc phần mở rộng do trình duyệt gửi.
- Dùng `getimagesize` hoặc kiểm tra tương đương để loại tệp giả ảnh.
- Không hỗ trợ SVG vì SVG có thể chứa nội dung chủ động.
- Tạo tên tệp bằng dữ liệu ngẫu nhiên mật mã; không dùng tên tệp gốc.
- Đường dẫn được chuẩn hóa và chỉ chấp nhận hai namespace nội bộ: asset hoạt động hiện hữu và ảnh upload trong `/storage/activity-covers`.

## Xử lý lỗi

- Lỗi ảnh hiển thị tại trường chọn ảnh và giữ lại các giá trị văn bản đã nhập.
- Trình duyệt không thể tự điền lại trường file sau lỗi; giao diện thông báo giảng viên chọn lại ảnh.
- Lỗi ghi thư mục hoặc tệp trả thông báo thân thiện, không làm lộ đường dẫn máy chủ.
- Ảnh không hợp lệ không được chuyển cho service lưu hoạt động.

## Kiểm thử và xác minh

- Kiểm thử đơn vị thành phần lưu ảnh: JPEG/PNG/WebP hợp lệ, MIME giả, định dạng không hỗ trợ, tệp quá 5 MB, lỗi upload và tên tệp an toàn.
- Kiểm thử controller/form: tạo không ảnh, tạo có ảnh, lỗi ảnh giữ dữ liệu form, sửa giữ ảnh cũ và sửa thay ảnh mới.
- Kiểm thử transaction filesystem: lưu database thất bại sẽ xóa ảnh mới; thay thành công mới xóa ảnh upload cũ; asset mẫu không bị xóa.
- Kiểm thử service/read model cho `/storage/activity-covers/...` và từ chối traversal hoặc URL ngoài.
- Kiểm thử render trang sinh viên: ảnh upload hợp lệ được hiển thị, hoạt động không ảnh dùng fallback mặc định.
- Chạy lại bộ kiểm thử hoạt động giảng viên và khám phá hoạt động sinh viên hiện có.

## Tác động triển khai

- Không có migration database.
- Máy chủ cần cho PHP quyền tạo và ghi `storage/activity-covers`.
- Thư mục ảnh upload là dữ liệu runtime và không được coi là source asset; cấu hình triển khai/backup cần giữ thư mục này qua các lần release.
- Workflow trạng thái hoạt động và quy trình duyệt của nhà trường không thay đổi.
