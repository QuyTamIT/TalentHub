# Kiểm tra và chuẩn bị merge — 15/09/2026

## Bản tích hợp

- Nhánh nguồn đang mở: `feature/student-v3`, HEAD `cb6cd4ccce56babb8d7c6c454148834f093151e9`, có thay đổi chưa commit.
- Main đã xác nhận trực tiếp bằng `git ls-remote origin refs/heads/main`: `f827fa4f8987811b6469e8cb3d975841aec4c879`.
- Bản tích hợp: `codex/score-transparency-merge-ready`, dựng trên main nói trên tại `D:/TalentHub/.tmp/merge-ready-20260915`.
- Đã ghép thay đổi bằng Git three-way. Xung đột ở `app/enterprise/talents/detail.php` đã xử lý: giữ bố cục main mới, điểm NULL hiển thị “Chưa có điểm”, điểm 0 không bị vẽ thành 6%.
- Giữ bản đang làm việc của người dùng. Không đưa scratch, SQL export, thông tin môi trường hoặc báo cáo cũ vào commit tích hợp.

## Sửa lỗi

1. CV nhận kỹ năng có nguồn `evaluation`, giữ điểm 0 hợp lệ.
2. Người mở link chia sẻ sử dụng quyền đọc riêng được kiểm tra bằng token, consent, thời hạn và học viên cụ thể; không giả lập phiên học viên. Đọc lại sau thu hồi phải bị từ chối. CV lọc trường chia sẻ và ẩn thông tin người chấm/bài test.
3. QR dùng chính sách mã xác thực công khai hiện hữu, kiểm tra định dạng và từ chối prefix trùng nhiều học viên. Bỏ mã kiểm thử đặc biệt và ký tự SQL wildcard. QR công khai vẫn là một cơ chế khác với token chia sẻ có thời hạn.
4. Công bố đánh giá phải có đủ mọi tiêu chí active, kể cả khi request gửi rubric rỗng. Quy tắc chấm trực tiếp của cấu hình rubric cũ vẫn giữ nguyên.
5. Kỹ năng có điểm nhưng chưa biết ngưỡng yêu cầu hiển thị “Vị trí chưa công bố mức yêu cầu.”; không bị gọi là kỹ năng thiếu. Giữ cách trình bày ngắn gọn vừa sửa.
6. Thêm allowlist Git cho các test nguồn gốc điểm và matching đang bị bỏ qua.

## Kiểm thử thực tế

Trên bản tích hợp:

- 61 file PHP đạt `php -l`.
- 14 bộ PHP đạt khi bật `-d zend.assertions=1 -d assert.exception=1`: merge_readiness_regression, shared_cv_score_access, job_match_model_engine, learner_ai_job_matching_api, teacher_grading_school_skills, teacher_rubric_calculation, enterprise_official_score_query, learner_evidence_backed_scores, learner_portfolio_score_provenance, learner_audit_score_provenance, learner_ai_no_competency_scores, learner_score_matching, job_match_gemini_contract, score_provenance_migration (tên file kết thúc `_test.php`).
- 7 test Node trong `tests/learner_ai_skill_gap_ui_test.js` đạt.
- Chromium headless: `tests/merge-readiness-ui.spec.js` đạt cho thiếu điểm, chưa biết ngưỡng, điểm 0 và đạt yêu cầu; không cần tài khoản hoặc AI provider.
- Migration được chạy hai lần trên SQLite in-memory: giữ chỉ mục, khóa ngoại và dữ liệu liên kết, chấp nhận điểm NULL.
- `git diff --cached --check` đạt; không còn file xung đột.

Kiểm tra MySQL hiện tại bằng transaction chỉ đọc:

- MySQL 8.4.3 kết nối được và schema điểm đạt `assertSchemaReady(true)`.
- Đọc điểm và dựng CV cho 5 hồ sơ active thành công; query featured talents thành công.
- Không ghi dữ liệu ứng dụng khi kiểm tra.

## Triển khai

Migration `20260915000300_add_score_provenance_metadata.php` chưa được ghi trong `schema_migrations` của database local, mặc dù các cột cần thiết đã tồn tại. Khi triển khai, dùng runner của dự án, kiểm tra các migration đang chờ và chạy migration trước khi phục vụ mã mới. Không thêm bản ghi lịch sử bằng tay.

```text
php bin/migrate.php status
php bin/migrate.php validate
php bin/migrate.php migrate
php bin/audit-score-provenance.php --dry-run
```

Chưa chạy migration MySQL hoặc audit `--apply` trên database ứng dụng; chưa chạy toàn bộ E2E bốn cổng hoặc gọi AI provider thật. Kết luận sẵn sàng tích hợp dựa trên các kiểm tra được liệt kê, không phải cam kết không còn lỗi trong toàn bộ hệ thống.

Nhánh tích hợp chưa được push hoặc merge vào main. Khi main thay đổi sau commit đã kiểm tra, cần ghép và kiểm thử lại trước khi merge.
