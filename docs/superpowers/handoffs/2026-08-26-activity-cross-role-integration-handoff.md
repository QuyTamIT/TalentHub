# Bàn giao tích hợp workflow Hoạt động xuyên vai trò

**Ngày đối soát:** 2026-08-26

**Phạm vi:** Learner Activity Phase 12 / Task 24

**Nguồn trạng thái:** MySQL và các service/repository hiện hữu

**Trạng thái tài liệu:** `DOCUMENTATION READY`

**Joint UAT:** `BLOCKED_EXTERNAL_OWNER`

Tài liệu này độc lập, có thể gửi trực tiếp cho owner Giáo viên/HLV (TE), Nhà trường (SC), Doanh nghiệp (EN), vận hành scheduler (OPS) và người điều phối joint UAT. Nó không xác nhận production workflow đã hoàn tất: internal isolated UAT đã có bằng chứng kỹ thuật, nhưng joint UAT chỉ hợp lệ khi owner giáo viên thật đăng nhập và trực tiếp thực hiện các mutation thuộc vai trò của họ.

## 1. Phạm vi và nguồn đã đối chiếu

Phase 12 không sửa code của TE/SC/EN, không chạy migration/seeder và không ghi database. Contract được đối chiếu từ:

- `app/teacher/activities/index.php` và teacher activity repository/service.
- Canonical teacher route `PATCH /api/v1/teachers/me/activities/{activityId}/registrations/{registrationId}`.
- `app/teacher/checkins/index.php` và teacher QR repository/service.
- Learner registration/check-in repositories, endpoints và transaction tests.
- No-show reconciliation repository/service/CLI.
- School ownership theo `student_profiles.classId -> classes.schoolId`, `teacher_profiles.schoolId` và `activities.schoolId`.
- Hai migration Activity đã áp dụng: `20260825000100`, `20260825000200`.

Hai migration enterprise ngoài workflow Activity vẫn pending và **không được áp dụng trong Phase 12**:

- `20260825000150 create_enterprise_talent_access_grants`.
- `20260825000300 create_school_enterprise_partnerships`.

## 2. Vòng đời canonical

```text
TE/SC tạo activity + details + registration policy + experience policy
  -> activity published
  -> ST chỉ thấy activity eligible của trường mình
  -> ST đăng ký
     -> automatic: approved
     -> teacher_review: pending
        -> TE approve: approved
        -> TE reject: rejected
  -> TE chuyển published -> ongoing
  -> TE tạo/mở QR session
  -> ST có registration approved quét QR
  -> một transaction:
       check-in confirmed
       registration attended
       experience confirmed theo confirmedHours policy
  -> TE chuyển ongoing -> completed
  -> sau endAt + 24 giờ:
       approved + không có confirmed check-in -> no_show
```

Các nguyên tắc không được diễn giải khác:

- “Đã đăng ký” không đồng nghĩa “đã tham gia”.
- Chỉ bộ ba `registration=attended`, `checkin=confirmed`, `experience=confirmed` mới được cộng giờ và dùng làm evidence.
- `no_show` không cộng giờ, KPI attendance, badge hoặc AI evidence; đây là trạng thái terminal.
- Capacity chỉ tính `approved + attended`; `pending` không chiếm chỗ.
- Database là nguồn trạng thái chính thức. UI, notification hoặc QR fixture không thay thế database truth.

## 3. Schema và metadata bàn giao

### 3.1. `activity_details`

Migration `20260825000100` tạo bảng forward-only gồm đúng 27 cột:

| Field | Contract |
|---|---|
| `activityId` | PK/FK đến `activities.id`, cascade update/delete |
| `responsibleTeacherId` | Nullable FK đến `teacher_profiles.id`; teacher phải cùng trường |
| `audienceScope` | Bắt buộc, hiện chỉ chấp nhận `school_only` |
| `displayCategory` | Nhãn category trình bày cho người dùng |
| `filterCategory` | Nhãn/nhóm dùng để lọc |
| `summary` | Tóm tắt, tối đa 500 ký tự |
| `description` | Mô tả đầy đủ |
| `experienceHighlights` | JSON list hợp lệ |
| `skillTags` | JSON list hợp lệ |
| `eligibilityRules` | JSON list hợp lệ |
| `benefitItems` | JSON list hợp lệ |
| `locationName` | Tên địa điểm; không bịa dữ liệu khi thiếu |
| `locationAddress` | Địa chỉ nullable |
| `deliveryMode` | `in_person`, `online` hoặc `hybrid` |
| `onlineMeetingUrl` | Nullable; chỉ dùng khi phù hợp delivery mode |
| `organizerName` | Đơn vị tổ chức |
| `organizerContact` | Nhãn/thông tin liên hệ nullable |
| `organizerEmail` | Email nullable; không tạo email cá nhân giả |
| `organizerPhone` | Số điện thoại nullable; không tạo số giả |
| `coverImageUrl` | Asset local/licensed theo learner contract |
| `coverImageAlt` | Alt text có nghĩa; nullable ở schema nhưng owner phải nhập khi có cover |
| `feeAmount` | Decimal không âm, mặc định `0.00` |
| `currency` | Mã 3 ký tự, mặc định `VND` |
| `targetAudience` | Đối tượng mục tiêu |
| `certificateLabel` | Nhãn chứng nhận nullable |
| `createdAt` | UTC `DATETIME(6)` |
| `updatedAt` | UTC `DATETIME(6)`, tự cập nhật |

Các index/FK/CHECK phải được giữ nguyên: PK `activityId`, index scope/category, index teacher, FK activity, FK teacher, CHECK `school_only`, delivery mode và phí không âm.

Ví dụ payload metadata an toàn:

```json
{
  "activityId": "<activity-uuid>",
  "responsibleTeacherId": "<same-school-teacher-uuid>",
  "audienceScope": "school_only",
  "displayCategory": "Kỹ thuật",
  "filterCategory": "Kỹ thuật",
  "summary": "Ngày trải nghiệm robotics dành cho học viên trong trường.",
  "description": "Nội dung mô tả đã được đơn vị tổ chức duyệt.",
  "experienceHighlights": ["Lắp ráp", "Lập trình", "Trình bày"],
  "skillTags": ["Tư duy hệ thống", "Làm việc nhóm"],
  "eligibilityRules": ["Đang theo học tại trường"],
  "benefitItems": ["Được hướng dẫn trực tiếp"],
  "locationName": "Phòng Robotics B305",
  "deliveryMode": "in_person",
  "organizerName": "Đơn vị tổ chức đã xác minh",
  "organizerContact": "Liên hệ đơn vị tổ chức",
  "coverImageUrl": "assets/activities/covers/example-activity.webp",
  "coverImageAlt": "Học viên thực hành robotics trong phòng học",
  "feeAmount": "0.00",
  "currency": "VND",
  "targetAudience": "Học viên của trường"
}
```

JSON metadata phải là list, cover không được là remote URL/`javascript:`/path traversal. Metadata thiếu phải dùng empty state/fallback rõ ràng, không tạo địa điểm, email hoặc số điện thoại giả.

### 3.2. Registration policy

Mỗi activity mở đăng ký cần một record gồm:

- `registrationOpensAt`.
- `registrationClosesAt`.
- `cancellationClosesAt`.
- `approvalMode`: `automatic` hoặc `teacher_review`.

Boundary bắt buộc:

```text
registrationOpensAt <= now
now < registrationClosesAt
now < activity.startAt
registrationClosesAt < activity.startAt
activity.endAt > activity.startAt
```

Không duyệt registration khác trường. Không đưa `no_show`, `cancelled` hoặc `rejected` quay lại state machine bằng thao tác approve. Learner discovery loại own registration active thuộc `pending`, `approved`, `waitlisted`, `attended`; `cancelled/rejected` chỉ được xét lại theo registration contract hiện hành.

### 3.3. Experience policy

- Activity phải có `activity_experience_policies` trước check-in.
- `confirmedHours` là số giờ chính thức; không suy ra từ `endAt - startAt`.
- Experience chỉ được tạo trong QR transaction hợp lệ.
- Teacher QR flow hiện có thể upsert `confirmedHours` khi tạo QR cho activity `ongoing`; owner TE vẫn cần UI/quy trình quản lý policy độc lập trước giờ check-in.

## 4. QR security và check-in

- Database chỉ lưu `tokenHash`; raw token chỉ được hiển thị một lần cho owner TE.
- Không ghi raw token vào log, audit metadata, notification, analytics hoặc tài liệu này.
- QR session có `status`, `expiresAt`, `maxScans`, `usedScans`, có thể revoke.
- Chỉ activity `ongoing` và registration `approved` mới check-in được.
- `pending`, `waitlisted`, `attended`, `no_show`, `cancelled`, `rejected` đều bị từ chối cho transaction check-in mới.
- Revoked, expired, exhausted hoặc token sai phải bị từ chối.
- Duplicate check-in không được tạo thêm check-in/experience hoặc cộng giờ lần hai.
- Không gửi thư mục QR fixture/handoff qua kênh công khai và không dùng fixture như bằng chứng production UAT.

Transaction hợp lệ phải giữ tính nguyên tử cho:

1. Tăng `usedScans` có compare-and-swap.
2. Chuyển registration `approved -> attended`.
3. Tạo check-in `confirmed`.
4. Tạo experience `confirmed` với `confirmedHours`.
5. Tạo audit `checkin.confirmed`.

## 5. Phân công owner và gap hiện tại

### 5.1. Giáo viên/HLV — TE

Owner TE chịu trách nhiệm:

1. Tạo activity đúng `schoolId` và `createdByTeacherId`.
2. Nhập/duy trì đủ `activity_details` và same-school `responsibleTeacherId`.
3. Tạo registration policy và experience policy trước khi mở workflow.
4. Xử lý `pending -> approved|rejected` bằng route/service canonical.
5. Chuyển `published -> ongoing -> completed` đúng thời điểm.
6. Tạo, trình chiếu và thu hồi QR; không log raw token.
7. Đối soát registration, check-in và experience.
8. Không duyệt lại `no_show` hoặc registration terminal.

**Đã có:** teacher page có approve/reject pending, CSRF/RBAC, optimistic expected status, lifecycle `draft -> published -> ongoing -> completed -> archived`, QR create/revoke và danh sách check-in do giáo viên quản lý.

**Gap giao task riêng cho TE:** form create/edit chưa ghi 27 trường `activity_details`; chưa có UI registration policy; experience policy mới gắn với lúc tạo QR, chưa có màn quản lý độc lập; danh sách registration chưa có nhãn/filter `no_show`; activity page còn trình bày canonical category/location thiếu metadata; nội dung notice QR còn mô tả learner scan là giai đoạn sau dù learner check-in đã tồn tại. Phase 12 chỉ ghi nhận, không sửa các file này.

### 5.2. Nhà trường — SC

Owner SC chịu trách nhiệm:

1. Bảo đảm `student -> class -> school` và `teacher -> school` chính xác.
2. Kiểm tra `activity.schoolId`, creator và responsible teacher không lệch trường.
3. Duy trì toàn bộ workflow hiện tại là `school_only`.
4. Chọn một owner vận hành scheduler no-show và người thay thế.
5. Ban hành chính sách ảnh, thông tin liên hệ, consent và thời hạn lưu trữ.
6. Đối soát báo cáo chỉ dùng verified attendance.
7. Mở change request mới nếu tương lai cần liên trường; không tự đổi scope hiện tại sang public.

**Gap giao task riêng cho SC:** chưa có quy trình UI/approval tập trung để audit completeness của details/policies và same-school responsible teacher; chưa có owner scheduler được xác nhận trong task này; chưa có acceptance sign-off cho mở liên trường.

### 5.3. Doanh nghiệp — EN

- Không cần sửa module doanh nghiệp để hoàn thành workflow activity nội bộ trường.
- Không đưa enterprise activity vào learner catalog nếu chưa có school ownership, audience scope, consent, contract và owner rõ ràng.
- Không dùng registration `approved` làm evidence kỹ năng.
- Chỉ dùng verified attended/check-in/experience khi có quyền truy cập và consent phù hợp.
- Không áp dụng hai migration enterprise pending trong Phase 12.

**Gap giao task riêng cho EN:** chưa có contract enterprise-sponsored activity được duyệt; chưa có ownership/consent projection cho evidence Activity. Đây không phải blocker của catalog nội bộ trường.

### 5.4. Vận hành scheduler — OPS

OPS phải sở hữu lịch chạy, giám sát, retry và incident. Owner cụ thể hiện **chưa được chỉ định**.

## 6. API và error contract hiện hữu

Không thêm mã lỗi mới trong tài liệu. Các mã dưới đây được đối chiếu trực tiếp từ source:

| HTTP | Error code | Tình huống tiêu biểu |
|---:|---|---|
| 401 | `AUTHENTICATION_REQUIRED` | Chưa đăng nhập |
| 403 | `PERMISSION_DENIED` | Sai role/permission hoặc thiếu learner profile |
| 403 | `CSRF_INVALID` / `CSRF_TOKEN_INVALID` | CSRF không hợp lệ theo adapter/route |
| 403 | `ACTIVITY_SCHOOL_SCOPE_DENIED` | Learner đăng ký activity khác trường |
| 404 | `RESOURCE_NOT_FOUND` | Activity/registration không thuộc owner hoặc không tồn tại |
| 404 | `QR_TOKEN_INVALID` | Token QR không khớp session khả dụng |
| 404 | `TEACHER_PROFILE_NOT_FOUND` | Session user không có teacher profile |
| 409 | `REGISTRATION_EXISTS` | Own registration active đã tồn tại |
| 409 | `SCHEDULE_CONFLICT` | Trùng lịch với registration active |
| 409 | `STATUS_CONFLICT` | Teacher transition dùng expected status cũ |
| 409 | `CAPACITY_REACHED` | Teacher approve khi capacity đã đầy |
| 409 | `INVALID_REGISTRATION_STATE` | Hủy registration ở trạng thái không cho phép |
| 409 | `REGISTRATION_STATE_CONFLICT` | Race khi hủy |
| 409 | `CHECKIN_ALREADY_EXISTS` | Registration đã check-in |
| 409 | `ACTIVITY_NOT_CHECKIN_ELIGIBLE` | Activity chưa `ongoing` |
| 409 | `REGISTRATION_NOT_ELIGIBLE` | Registration không phải `approved` |
| 409 | `QR_SESSION_REVOKED` | Phiên đã thu hồi |
| 409 | `QR_SESSION_EXPIRED` | Phiên hết hạn |
| 409 | `QR_SESSION_EXHAUSTED` | Hết lượt quét |
| 409 | `CHECKIN_STATE_CONFLICT` | QR/registration thay đổi trong transaction |
| 409 | `EXPERIENCE_POLICY_MISSING` | Thiếu confirmed-hours policy |
| 409 | `QR_SESSION_NOT_REVOCABLE` | Teacher không thể thu hồi session |
| 422 | `VALIDATION_FAILED` | UUID, action, token hoặc input không hợp lệ |
| 422 | `REGISTRATION_CLOSED` | Status/window không còn nhận đăng ký |
| 422 | `REGISTRATION_CANCELLATION_CLOSED` | Hết hạn hủy |
| 422 | `INVALID_ACTIVITY` | Tạo QR cho activity không `ongoing`/không thuộc teacher |
| 429 | `RATE_LIMIT_EXCEEDED` | Check-in vượt rate limit; response có `Retry-After` |
| 503 | `ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE` | Production schema thiếu school-scope contract |
| 503 | `SERVICE_UNAVAILABLE` | Adapter/repository tạm thời không khả dụng |

Endpoints cần dùng đúng contract:

- Learner registration: `POST /app/learner/api/v1/activity-registrations.php`, action `register|cancel`.
- Learner check-in: `POST /app/learner/api/v1/checkins.php`, token opaque và idempotency key.
- Teacher transition: `PATCH /api/v1/teachers/me/activities/{activityId}/registrations/{registrationId}` với `expectedStatus=pending`, action `approve|reject`, CSRF và permission `activity_registration.update_managed`.

## 7. No-show scheduler runbook

### 7.1. Command và lịch đề xuất

CLI hiện có:

```text
php app/learner/jobs/reconcile-activity-attendance.php
  --schema=<approved-schema>
  --grace-hours=24
  --limit=100
  [--dry-run]
  [--allow-primary]
```

Lịch đề xuất: mỗi 15 phút, clock UTC. Không gọi job từ HTTP GET.

### 7.2. Quy tắc chọn và idempotency

- Chỉ registration `approved`.
- `activity.endAt <= now - 24h`.
- Không có check-in `confirmed`.
- Update compare-and-swap chỉ khi status vẫn `approved`.
- Ghi `attendanceResolvedAt`, reason `no_confirmed_checkin_after_24h`, audit và notification idempotent.
- Lần chạy sau không được đổi lại record đã `no_show`.

### 7.3. Quy trình vận hành

1. Dùng service account chỉ có quyền cần thiết; không dùng session người dùng.
2. Cấu hình UTC và exact schema; production apply bắt buộc cờ `--allow-primary`.
3. Chạy dry-run/read-only khi triển khai lịch lần đầu.
4. Theo dõi exit code: `0` thành công, `2` bị từ chối do input/safety, `3` failure an toàn.
5. Log chỉ timestamp UTC, schema, exit code và aggregate count; không log token, cookie, PII hoặc audit metadata.
6. Retry theo backoff và khóa scheduler để tránh nhiều runner đồng thời; idempotency là lớp bảo vệ cuối, không phải lý do chạy trùng tùy ý.
7. Khi lỗi QR/check-in/no-show, giữ nguyên evidence và mở incident; không sửa SQL tay hoặc lùi state machine.

**Phase 12.1 — coupling đã được xử lý:** transaction reconciliation chỉ commit chuyển trạng thái `no_show` và audit. Notification được phát sau commit; nếu persistence phát sinh exception, scheduler vẫn báo failure để OPS giám sát nhưng status/audit không bị rollback. Lần chạy sau truy vấn các registration `no_show` có canonical reason + reconciliation audit nhưng chưa có canonical notification `eventKey`, rồi retry idempotent. Preference tắt được loại khỏi hàng đợi retry. Automated failure-injection đã chứng minh exception không rollback state/audit và retry sau đó chỉ tạo đúng một notification.

## 8. Joint UAT gate và quy trình

### 8.1. Điều kiện bắt đầu

Joint UAT trên `talenthub` chỉ bắt đầu khi đồng thời có:

- Authorization riêng, rõ ràng của người dùng.
- Owner giáo viên thật có mặt, tự đăng nhập và thao tác.
- Sinh viên UAT thật và activity `teacher_review` cùng trường, phù hợp ngày tổ chức.
- Kế hoạch giữ evidence; dữ liệu UAT thật không bị xóa/rollback.
- Backup mới và snapshot counts/checksum ngay trước mutation.
- Đồng thuận rằng không sửa timestamp production để ép no-show.

Nếu phải giữ nguyên 15 activity `published`, không dùng database chính; cần task riêng cho schema clone/fixture. Phase 12 không tự tạo schema.

### 8.2. Core flow

1. Snapshot và backup mới.
2. ST đăng ký activity `teacher_review` -> `pending`.
3. TE xác nhận pending và approve qua UI/route canonical.
4. ST thấy `approved` trong “Đã đăng ký”; KPI giờ chưa tăng.
5. TE chuyển activity `published -> ongoing`.
6. TE tạo QR session và tự trình chiếu token một lần.
7. ST quét QR qua trang Check-in QR hiện có.
8. Đối chiếu registration attended, check-in confirmed, experience confirmed, audit và notification.
9. Đối chiếu “Đã đăng ký” -> “Lịch sử”, Dashboard, Statistics, Profile, AI và Badge.
10. TE chuyển `ongoing -> completed`.
11. Dùng một registration approved khác không check-in để quan sát no-show sau đủ 24 giờ.
12. Nếu chưa đủ thời gian, ghi `NO_SHOW PRODUCTION UAT: BLOCKED_TIME_WINDOW`; không sửa timestamp.
13. Snapshot sau và giữ nguyên toàn bộ evidence thật.

AI/student task không được impersonate TE, tự approve, tự đổi lifecycle, tự tạo/revoke QR, dùng SQL tay, xóa evidence hoặc trả activity về `published`.

## 9. Ma trận 20 test case bàn giao

Mọi test case dưới đây đã được chuẩn bị nhưng **chưa thực hiện joint UAT** vì thiếu owner giáo viên thật. Cột automated evidence chỉ là bằng chứng kỹ thuật, không thay thế UAT.

| ID | Owner | Tiền điều kiện / thao tác | Expected DB + learner UI | Audit/notification + evidence | Trạng thái |
|---|---|---|---|---|---|
| UAT-01 | ST/TE | Automatic activity, ST register | Registration `approved`; vào Đã đăng ký; chưa cộng giờ | Registration audit/notification; screenshot + row IDs | Ready; `BLOCKED_EXTERNAL_OWNER` |
| UAT-02 | ST/TE | Teacher-review activity, ST register | Registration `pending`; Chờ duyệt | Created event/audit; screenshot | Ready; `BLOCKED_EXTERNAL_OWNER` |
| UAT-03 | TE | Approve pending | `pending -> approved`; CTA/status cập nhật | Approved audit/notification | Automated contract PASS; joint blocked |
| UAT-04 | TE | Reject pending | `pending -> rejected`; không check-in | Rejected audit/notification | Automated contract PASS; joint blocked |
| UAT-05 | ST/SC | Register activity khác trường | Không insert; HTTP 403 scope denied | Không audit success/notification | Automated scope PASS; joint blocked |
| UAT-06 | ST | Pending quét QR | Không check-in/experience | Error evidence, no success audit | Automated check-in PASS; joint blocked |
| UAT-07 | ST/TE | Activity vẫn published, approved quét QR | Bị từ chối; không DML | `ACTIVITY_NOT_CHECKIN_ELIGIBLE` | Automated check-in PASS; joint blocked |
| UAT-08 | TE | Chuyển published -> ongoing | Activity `ongoing`; learner detail còn truy cập | Lifecycle evidence + screenshot | Ready; `BLOCKED_EXTERNAL_OWNER` |
| UAT-09 | TE/ST | Approved + ongoing + valid QR | Attended + confirmed check-in + confirmed experience | Check-in audit/notification; before/after rows | Isolated PASS; joint blocked |
| UAT-10 | ST | Quét lại cùng QR/registration | Không cộng lần hai | Conflict, counts/checksum ổn định | Automated transaction PASS; joint blocked |
| UAT-11 | TE/ST | Revoke hoặc chờ QR expire rồi quét | Không check-in/experience | Error code + QR status evidence | Automated QR PASS; joint blocked |
| UAT-12 | TE | Chuyển ongoing -> completed | Activity `completed`; history vẫn giữ evidence | Lifecycle screenshot/audit nếu producer hỗ trợ | Ready; `BLOCKED_EXTERNAL_OWNER` |
| UAT-13 | OPS | Approved, không check-in, endAt +24h | Registration `no_show`; History 0 giờ | Reconciliation audit/notification | Isolated PASS; joint/time blocked |
| UAT-14 | OPS | Chạy reconciler lần hai | Không thay đổi record/count | Không duplicate audit/notification | Automated idempotency PASS; joint blocked |
| UAT-15 | ST | Xem no_show | Không giờ/KPI/badge/AI evidence | History screenshot + aggregate queries | Automated cross-module PASS; joint blocked |
| UAT-16 | ST/OPS | Tắt notification preference rồi reconcile | Status/audit vẫn commit, không notification | Preference + DB evidence | Automated PASS; joint blocked |
| UAT-17 | ST/SC/TE | Đối chiếu attended | Dashboard/Profile/Statistics/AI/Badge cùng truth | Screenshots + aggregate IDs | Isolated PASS; joint blocked |
| UAT-18 | ST/SC | Student khác trường list/detail | Không thấy list; detail safe not-found | Không lộ title/metadata | Automated scope PASS; joint blocked |
| UAT-19 | ST | Cùng student/time kiểm tra eligible | AI/Khám phá/Dashboard/Ecosystem cùng ID set | ID set capture | Automated parity PASS; joint blocked |
| UAT-20 | TE/ST | Metadata/cover/contact chứa input kiểm thử | Hiển thị đúng, escape, cover local/fallback | Screenshot, network/console log sạch | Security/render PASS; joint blocked |

## 10. Acceptance checklist theo owner

### Learner implementation đã có automated evidence

- [x] Bốn màn hình Activity tồn tại: Khám phá, Chi tiết, Đã đăng ký, Lịch sử.
- [x] Catalog/detail/mutation/AI/Ecosystem áp dụng school scope.
- [x] Automatic và teacher-review tạo status đúng.
- [x] QR transaction yêu cầu approved + ongoing.
- [x] Attended chỉ được công nhận với check-in/experience confirmed.
- [x] No-show sau grace period, không cộng evidence.
- [x] Dashboard/History/Statistics/Profile/AI/Badge dùng verified truth.
- [x] Notification/deep-link learner có contract.

### Cần owner ngoài task sinh viên xác nhận

- [ ] TE xác nhận và sở hữu form ghi đủ `activity_details`.
- [ ] TE xác nhận UI registration/experience policy và presentation `no_show`.
- [ ] SC xác nhận ownership mappings, policy ảnh/liên hệ/consent.
- [ ] SC chỉ định OPS scheduler và người thay thế.
- [x] Notification-failure coupling đã được tách khỏi reconciliation transaction và có retry idempotent dựa trên database truth.
- [ ] OPS triển khai lịch chạy, scheduler lock, monitoring, alert và backoff retry trong môi trường vận hành.
- [ ] EN xác nhận không tiêu thụ Activity evidence khi chưa có contract/consent.
- [ ] Owner TE thật hoàn thành joint UAT core flow và lưu evidence.
- [ ] No-show production UAT chờ đủ 24 giờ hoặc ghi `BLOCKED_TIME_WINDOW`.

## 11. Evidence cần lưu khi joint UAT được mở

- Backup path, size, SHA-256 và dấu dump hoàn tất.
- HEAD/migration status và database snapshot trước/sau.
- Activity, registration, check-in, experience, QR session và audit IDs; không ghi raw token.
- Screenshot TE pending/approved/lifecycle/QR status và learner Registered/History/Dashboard/Statistics/Profile.
- Kết quả AI/Badge bằng ID/evidence reference, không chứa credential.
- Network status/error code và `Retry-After` khi kiểm tra rate limit.
- Tên owner thực hiện, timestamp UTC, kết quả từng UAT ID và blocker.

Không lưu cookie/session, raw QR token, PII không cần thiết hoặc QR fixture trong tài liệu công khai.

## 12. Trạng thái bàn giao

```text
PHASE 12 HANDOFF DOCUMENTATION COMPLETE
NOTIFICATION FAILURE COUPLING: RESOLVED_AUTOMATED
JOINT UAT: BLOCKED_EXTERNAL_OWNER
```

Để gỡ blocker, cần owner giáo viên thật và authorization riêng cho joint UAT. Không được đổi trạng thái thành `JOINT UAT COMPLETE WITH REAL ROLE OWNERS` dựa trên SQLite, fixture, internal isolated UAT hoặc thao tác thay owner.
