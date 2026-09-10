# Kế hoạch Triển khai: Hoàn thiện Tạo Hoạt động & Tải lên Ảnh bìa cho Giảng viên

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thiện form tạo/sửa hoạt động của giảng viên (`app/teacher/activities/index.php`), cho phép tải ảnh bìa trực tiếp từ máy tính (kèm thư viện ảnh mẫu và xem trước), đưa toàn bộ thông tin quan trọng ra ngoài không thu gọn, tự động tính mốc thời gian đăng ký và giờ công nhận, đồng thời đồng bộ hiển thị hoàn hảo ở phía sinh viên (`app/learner/`).

**Architecture:** 
- Thư mục `storage/activity-covers/` lưu trữ ảnh upload với kiểm tra MIME an toàn và atomic write.
- `TeacherActivityService` chấp nhận cả URL `/storage/activity-covers/...` và preset `assets/activities/covers/...`, tự sinh alt text.
- `ActivityReadModel` và helper `learner_activity_cover_or_fallback` giải quyết đúng đường dẫn ảnh upload và preset.
- Form giảng viên được tái cấu trúc thành 4 khối phẳng trực quan, hỗ trợ `multipart/form-data`, xem trước ảnh tức thì bằng `FileReader` và modal chọn nhanh 15 ảnh mẫu theo 4 chủ đề.

**Tech Stack:** PHP 8.3, Vanilla JavaScript, HTML5 File API, CSS3 Grid/Flexbox, SQLite/MySQL PDO, Git.

## Global Constraints
- PHP CLI binary: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- Dung lượng ảnh tối đa: 5MB (`5 * 1024 * 1024` bytes).
- Định dạng ảnh cho phép: `image/jpeg`, `image/png`, `image/webp`.
- Đường dẫn lưu trữ: `d:\TalentHub\storage\activity-covers`.
- Giữ nguyên khả năng tương thích ngược 100% với các hoạt động cũ dùng ảnh mẫu trong hệ thống.
- Tuyệt đối không để accordion thu gọn che khuất các trường bắt buộc như mô tả hay lịch đăng ký.

---

### Task 1: Mở rộng Hỗ trợ Đường dẫn Ảnh Upload ở Backend & Tầng Đọc Dữ liệu

**Files:**
- Modify: `src/Modules/Teacher/Service/TeacherActivityService.php:191-198`
- Modify: `app/learner/data/ReadModel/ActivityReadModel.php:351-358`
- Modify: `app/learner/activities.php:10-23`
- Modify: `app/learner/activity-detail.php:6-23`
- Modify: `app/learner/my-activities.php:7-23`
- Modify: `app/learner/includes/activity-data.php:6-23`
- Test: `tests/learner_activity_cover_test.php`

**Interfaces:**
- Consumes: input string `coverImageUrl` (e.g. `/storage/activity-covers/cover-test.png` or `assets/activities/covers/talenthub-python-workshop.webp`)
- Produces: Normalized relative or root-relative URL safely rendered in `<img src="...">`

- [ ] **Step 1: Cập nhật file test `tests/learner_activity_cover_test.php` với test case cho `/storage/activity-covers/`**

Thêm các test case kiểm tra:
1. Đường dẫn upload `/storage/activity-covers/cover-sample.webp` được nhận diện hợp lệ.
2. File tồn tại trong `storage/activity-covers/` trả về `/storage/activity-covers/cover-sample.webp`.
3. File không tồn tại trong `storage/activity-covers/` fallback về SVG illustration.

- [ ] **Step 2: Chạy test để xác nhận test ban đầu thất bại (hoặc chưa hỗ trợ storage)**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_activity_cover_test.php`
Expected: Thất bại do hàm chưa nhận diện đường dẫn `/storage/activity-covers/`.

- [ ] **Step 3: Cập nhật `TeacherActivityService.php`, `ActivityReadModel.php` và các hàm `learner_activity_cover_or_fallback`**

1. Trong `TeacherActivityService.php`:
   - Mở rộng regex `coverImageUrl` để cho phép:
     `#\A(?:/app/learner/)?(?:assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)|/storage/activity-covers/[a-zA-Z0-9_\-\.]+\.(?:webp|png|jpe?g))\z#i`
   - Nếu `coverImageUrl` có giá trị mà `coverImageAlt` là null hoặc rỗng, tự động gán: `'Ảnh minh họa cho hoạt động ' . $title`.
2. Trong `ActivityReadModel.php`:
   - Cập nhật `safeLocalActivityAsset` để chấp nhận cả `/storage/activity-covers/[a-zA-Z0-9_\-\.]+\.(?:webp|png|jpe?g)` và trả về đúng đường dẫn.
3. Trong các file `app/learner/activities.php`, `activity-detail.php`, `my-activities.php`, `includes/activity-data.php`:
   - Cập nhật `learner_activity_cover_or_fallback`:
     Nếu candidate bắt đầu bằng `/storage/activity-covers/`: kiểm tra file trên đĩa tại `dirname(__DIR__, 2) . $candidate` (hoặc `dirname(__DIR__) . $candidate` tùy cấp thư mục), nếu có trả về đúng đường dẫn `$candidate`.

- [ ] **Step 4: Chạy lại test `tests/learner_activity_cover_test.php` để xác nhận PASS**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_activity_cover_test.php`
Expected: `[ALL PASSED] learner_activity_cover_test.php completed successfully`.

- [ ] **Step 5: Commit Task 1**

```bash
git add src/Modules/Teacher/Service/TeacherActivityService.php app/learner/ tests/learner_activity_cover_test.php
git commit -m "feat(activity): support storage cover image paths in service, read model, and view helpers"
```

---

### Task 2: Xây dựng Module Xử lý Upload Ảnh Bìa An toàn cho Giáo viên

**Files:**
- Create: `app/teacher/includes/cover-upload.php`
- Create: `tests/teacher_activity_cover_upload_test.php`

**Interfaces:**
- Produces: `teacherActivitiesHandleCoverUpload(array $file, string $teacherId): ?string`
  - Input: `$_FILES['coverFile']`, `$teacherId`
  - Output: `/storage/activity-covers/cover-{prefix8}-{random12}.{ext}` hoặc ném `ApiException` khi file lỗi / trả về null nếu không chọn file.

- [ ] **Step 1: Viết test `tests/teacher_activity_cover_upload_test.php`**

Kiểm tra:
1. Bỏ qua khi không có file upload (`UPLOAD_ERR_NO_FILE`) -> trả về null.
2. Ném ngoại lệ khi upload file không phải ảnh (ví dụ: text file, mime giả mạo).
3. Ném ngoại lệ khi file quá 5MB.
4. Upload thành công file ảnh thật (PNG, JPG, WebP) -> tạo file an toàn trong `storage/activity-covers/`, trả về URL hợp lệ, nội dung file khớp 100%.

- [ ] **Step 2: Chạy test để xác nhận FAIL vì file chưa tồn tại**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/teacher_activity_cover_upload_test.php`
Expected: FAIL "No such file or directory".

- [ ] **Step 3: Cài đặt `app/teacher/includes/cover-upload.php`**

Triển khai:
- Tạo thư mục `d:\TalentHub\storage\activity-covers` nếu chưa có.
- Hàm `teacherActivitiesHandleCoverUpload(array $file, string $teacherId): ?string`:
  - Kiểm tra `$file['error']`. Nếu `UPLOAD_ERR_NO_FILE` return null.
  - Nếu khác `UPLOAD_ERR_OK` ném `ApiException(422, 'VALIDATION_FAILED', 'Tải ảnh lên thất bại.')`.
  - Đọc MIME qua `finfo` và `getimagesize`. Chấp nhận `image/jpeg`, `image/png`, `image/webp`.
  - Giới hạn dung lượng: <= 5MB. Giới hạn kích thước ảnh <= 4096px x 4096px.
  - Sinh tên file `cover-` . substr(preg_replace('/[^a-zA-Z0-9]/', '', $teacherId), 0, 8) . '-' . bin2hex(random_bytes(6)) . '.' . $ext.
  - Ghi file qua `.tmp` và `rename` nguyên tử.
  - Trả về `/storage/activity-covers/{filename}`.

- [ ] **Step 4: Chạy test để xác nhận PASS**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/teacher_activity_cover_upload_test.php`
Expected: `[ALL PASSED] teacher_activity_cover_upload_test.php completed successfully`.

- [ ] **Step 5: Commit Task 2**

```bash
git add app/teacher/includes/cover-upload.php tests/teacher_activity_cover_upload_test.php
git commit -m "feat(teacher): implement secure cover image upload handler"
```

---

### Task 3: Tái Cấu Trúc Bố Cục Form Giáo viên & Tích Hợp Upload / Preset Gallery (HTML & CSS)

**Files:**
- Modify: `app/teacher/activities/index.php`
- Modify: `assets/css/teacher.css` (hoặc inline scoped styles trong teacher activities)

**Interfaces:**
- Form: `<form method="post" enctype="multipart/form-data">`
- Fields:
  - File input: `<input type="file" id="activity-cover-file" name="coverFile" accept="image/png,image/jpeg,image/webp" style="display:none">`
  - Hidden input: `<input type="hidden" id="activity-cover-url" name="coverImageUrl" value="...">`
  - Preview Box: `<div class="teacher-cover-preview" id="cover-preview-box">...</div>`
  - Preset Modal: `<div id="preset-gallery-modal" class="teacher-modal" hidden>...</div>`
  - 4 Blocks: Thông tin chính & Ảnh, Lịch trình & Đăng ký, Trải nghiệm & Kỹ năng, Tổ chức & Chi phí.

- [ ] **Step 1: Thêm kiểu dáng CSS cho Banner Preview Box và Preset Gallery Modal vào `assets/css/teacher.css`**

Thêm các lớp:
- `.teacher-cover-uploader`: khung chứa ảnh bìa tỉ lệ 16:9 hoặc chiều cao cố định ~220px, bo góc, viền nét đứt khi chưa có ảnh, bóng nhẹ.
- `.teacher-cover-preview__img`: ảnh hiển thị đầy đủ, `object-fit: cover`.
- `.teacher-cover-actions`: các nút "Tải ảnh từ máy", "Chọn ảnh mẫu", "Xóa ảnh".
- `.teacher-preset-modal`: modal chọn ảnh mẫu với 4 tab chủ đề, hiển thị lưới thumbnail kèm tiêu đề rõ ràng.
- Lưới 2 cột cho Khối Trải nghiệm & Kỹ năng (`.teacher-activities-form__two-col`).

- [ ] **Step 2: Cập nhật `app/teacher/activities/index.php` xử lý upload file và dữ liệu mặc định thông minh**

1. Thêm `require_once __DIR__ . '/../includes/cover-upload.php';`.
2. Khi xử lý POST:
   - Nếu có `$_FILES['coverFile']` được tải lên, gọi `teacherActivitiesHandleCoverUpload` và ghi đè `$formValues['coverImageUrl']` bằng URL mới.
   - Nếu không có file upload mới, giữ nguyên giá trị `$formValues['coverImageUrl']` từ POST (ảnh mẫu hoặc ảnh cũ).
3. Đặt giá trị mặc định khi tạo mới:
   - `startAt`: 2 ngày sau lúc 09:00.
   - `endAt`: 2 ngày sau lúc 12:00.
   - `registrationOpensAt`: Ngày giờ hiện tại.
   - `registrationClosesAt`: `startAt` trừ 2 tiếng.
   - `cancellationClosesAt`: `startAt` trừ 1 ngày.
   - `confirmedHours`: `2.00`.
   - `targetAudience`: `Học sinh trong trường`.
   - `certificateLabel`: `Minh chứng tham gia trên TalentHub`.

- [ ] **Step 3: Thay thế toàn bộ mã HTML form cũ bằng cấu trúc 4 khối trực quan và Modal Preset Gallery**

- Khối 1: Thông tin chính & Ảnh bìa (gồm Tiêu đề, Nhóm, Ảnh bìa + Preview + Nút upload/chọn mẫu, Giới thiệu ngắn, Mô tả đầy đủ, Sức chứa, Hình thức + Địa điểm/Link).
- Khối 2: Lịch trình & Đăng ký (Bắt đầu, Kết thúc, Mở đăng ký, Đóng đăng ký, Cho phép hủy đến, Cách duyệt, Giờ công nhận).
- Khối 3: Trải nghiệm & Kỹ năng (Nội dung trải nghiệm, Kỹ năng phát triển, Điều kiện tham gia, Quyền lợi & cơ hội dạng lưới 2 cột).
- Khối 4: Ban tổ chức & Chi phí (Đối tượng tham gia, Đơn vị tổ chức, Giáo viên phụ trách, Liên hệ, Chi phí miễn phí/có phí, Chứng nhận).
- Modal bộ sưu tập 15 ảnh mẫu phân theo 4 nhóm.

- [ ] **Step 4: Kiểm tra cú pháp PHP bằng lệnh CLI**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -l app/teacher/activities/index.php`
Expected: `No syntax errors detected in app/teacher/activities/index.php`.

- [ ] **Step 5: Commit Task 3**

```bash
git add app/teacher/activities/index.php assets/css/teacher.css
git commit -m "feat(teacher): redesign activity form with image uploader, preset gallery, and 4 clear blocks"
```

---

### Task 4: Xây dựng Script Tương Tác: Xem Trước Ảnh & Tự Động Tính Ngày Giờ

**Files:**
- Modify: `app/teacher/activities/index.php` (hoặc file script phụ trợ tương ứng)

**Interfaces:**
- Event: File input `change` -> `FileReader.readAsDataURL` -> Cập nhật `src` preview.
- Event: Preset thumbnail `click` -> Gán value hidden input `coverImageUrl` -> Cập nhật `src` preview -> Đóng modal.
- Event: "Xóa ảnh" button `click` -> Xóa file input + value hidden input -> Trở về placeholder preview.
- Event: `startAt` `change` -> Tự động tính các mốc mở/đóng/hủy đăng ký nếu đang trống hoặc khi người dùng đổi ngày bắt đầu.

- [ ] **Step 1: Bổ sung mã JavaScript xử lý tương tác vào trang `app/teacher/activities/index.php`**

Triển khai các tính năng:
1. Preview ảnh upload: lắng nghe `change` trên file input, dùng `FileReader` cập nhật ảnh tức thì.
2. Bộ chọn ảnh mẫu: mở/đóng modal, khi click ảnh mẫu thì cập nhật ảnh preview và xóa giá trị file input để ưu tiên ảnh mẫu.
3. Nút xóa ảnh: reset preview về placeholder và xóa dữ liệu ảnh.
4. Auto-date calculate: khi đổi `startAt`, tự động tính `registrationClosesAt` (trước 2h) và `cancellationClosesAt` (trước 1 ngày) để giảng viên không bao giờ bị lỗi validation ngày tháng.

- [ ] **Step 2: Kiểm tra cú pháp và đảm bảo không có lỗi console**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -l app/teacher/activities/index.php`
Expected: `No syntax errors detected in app/teacher/activities/index.php`.

- [ ] **Step 3: Commit Task 4**

```bash
git add app/teacher/activities/index.php
git commit -m "feat(teacher): add client-side image preview and smart date calculation"
```

---

### Task 5: Kiểm Thử Tích Hợp End-to-End & Xác Minh Hiển Thị Phía Học Sinh

**Files:**
- Create: `tests/teacher_activity_e2e_flow_test.php`
- Test: `tests/learner_activity_cover_test.php`

- [ ] **Step 1: Viết test kịch bản End-to-End `tests/teacher_activity_e2e_flow_test.php`**

Kịch bản:
1. Tạo một hoạt động mới với ảnh upload từ máy tính và đầy đủ các trường (Mô tả, Kỹ năng, Trải nghiệm, Lịch trình, Giờ công nhận).
2. Lưu bản nháp thành công và kiểm tra bản ghi trong bảng `activities` và `activity_details`.
3. Kiểm tra ảnh trong `storage/activity-covers/` tồn tại và đường dẫn khớp DB.
4. Chuyển trạng thái hoạt động sang `published`.
5. Gọi `ActivityReadModel` và kiểm tra dữ liệu hiển thị cho học sinh:
   - Thẻ card hiển thị đúng ảnh upload.
   - Trang chi tiết hiển thị đầy đủ tiêu đề, ảnh, mô tả chi tiết, kỹ năng, trải nghiệm, điều kiện, quyền lợi, thông tin liên hệ.

- [ ] **Step 2: Chạy test End-to-End**

Run: `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/teacher_activity_e2e_flow_test.php`
Expected: `[ALL PASSED] teacher_activity_e2e_flow_test.php completed successfully`.

- [ ] **Step 3: Chạy lại toàn bộ test liên quan đến hoạt động**

Run:
1. `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/learner_activity_cover_test.php`
2. `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/teacher_activity_cover_upload_test.php`
3. `& "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" tests/teacher_activity_e2e_flow_test.php`
Expected: Tất cả các bài test đều PASS.

- [ ] **Step 4: Commit Task 5**

```bash
git add tests/teacher_activity_e2e_flow_test.php
git commit -m "test(activity): add end-to-end integration test for teacher creation and student view"
```
