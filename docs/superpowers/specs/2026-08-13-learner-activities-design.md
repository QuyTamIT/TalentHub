# Learner Activities Design

## Goal

Hoàn thiện hành trình hoạt động trải nghiệm bằng mock provider và LocalStorage: khám phá, xem chi tiết, đăng ký, chờ duyệt/waitlist, quản lý đăng ký, hủy, liên kết check-in và phản hồi.

## Boundaries

- Chỉ thay đổi `app/learner/**`, `assets/css/learner.css`, `assets/js/learner*.js` và test learner.
- Không sửa database hoặc module vai trò khác.
- Hoạt động mang `source_role=teacher`, `school_id`, `created_by_teacher_id`; Student Portal chỉ đọc.
- Registration dùng ID ổn định, `student_id`, `activity_id`, status và timestamps tương thích schema/API tương lai.

## Status rules

- Còn chỗ, không cần duyệt: `registered`.
- Cần giáo viên duyệt: `pending`.
- Hết chỗ: `waitlisted`.
- Hủy: `cancelled`; check-in: `checked_in`; đã xác nhận giờ: `completed`.
- Một registration hiện hành cho mỗi student/activity; không đăng ký trùng.

## Persistence and migration

Mock history dùng chung đến từ PHP provider. Thao tác mới lưu trong `talenthub.learner.activities.v1`; UI chỉ gọi `ActivityStorage`, vì vậy backend sau này thay adapter mà không đổi màn hình.

## Screens

1. Catalog có bộ lọc và link chi tiết.
2. Chi tiết hoạt động có điều kiện, lịch, tổ chức, sức chứa và CTA theo trạng thái.
3. Trang Hoạt động của tôi lọc theo trạng thái, hủy đăng ký, đi tới check-in và phản hồi hoạt động đã hoàn thành.
4. Check-in hiển thị liên kết từ registration; camera vẫn ghi rõ là demo.

## Holland closure

Trang Khám phá hiển thị Holland là bản thử nghiệm, ba bài còn lại là sắp triển khai/dữ liệu demo; kết quả Holland mới nhất được đọc từ LocalStorage và không làm thay đổi biểu đồ Đa trí thông minh.
