# Minh bạch điểm số TalentHub: vấn đề và yêu cầu chỉnh sửa

- Ngày: 15/09/2026.
- Trạng thái: yêu cầu đã được người dùng đồng ý triển khai; tài liệu này mô tả hành vi cần đạt, không xác nhận đã sửa xong.
- Kế hoạch đi kèm: [Triển khai, phạm vi và kiểm thử](plan-2026-09-15-score-transparency.md).
- Phạm vi dữ liệu đã khảo sát: mã nguồn trong `D:/TalentHub` và database local đang được cấu hình cho dự án. Không suy rộng thành kết luận về môi trường production.

## 1. Vấn đề người dùng gặp

Cùng một hồ sơ xuất hiện nhiều điểm có tên gần giống nhau nhưng lấy từ các nguồn khác nhau. Ví dụ: Logic 100% ở trắc nghiệm, Logic & Hệ thống 90% ở bản đồ AI, Python 95/100 trong kỹ năng và điểm giảng viên 92/100. Giao diện chưa giải thích đầy đủ nguồn, ý nghĩa và cách tính, khiến khách hàng có thể hiểu tất cả là điểm năng lực đã được kiểm chứng.

Vấn đề chính không phải mọi điểm đều sai. Bốn nhóm điểm đang bị trình bày như có cùng căn cứ, trong khi có nhóm do backend chấm, nhóm do người đánh giá nhập và nhóm do Gemini suy luận.

**Nguyên tắc bắt buộc: một con số đã lưu vào database chưa đủ chứng minh đó là dữ liệu thực.** Phải truy được bản ghi nguồn, cách đo, người đánh giá hoặc công thức, phiên bản và thời điểm. Điểm trắc nghiệm có nguồn vẫn là kết quả tự trả lời; không phải bằng chứng trực tiếp về khả năng thực hành nghề nghiệp.

## 2. Những gì đã kiểm tra được

| Màn hình/nhóm điểm | Dữ liệu đã đối chiếu | Nhận định |
|---|---|---|
| Bản đồ AI | `talent_map` chứa 0.9, 0.75, 0.8, hiển thị 90%, 75%, 80% | Là điểm model tạo trong phân tích, chưa có công thức đánh giá độc lập chứng minh các số này. |
| Kỹ năng hồ sơ | `student_skills` chứa 95, 90, 90, 88, 85, 80, 70, 65 | Là số lưu trong hệ thống. Nguồn ghi `teacher/verified` cần tiếp tục nối về bản đánh giá gốc; không đủ chỉ kiểm tra hai nhãn này. |
| MI, Holland, MBTI, DISC | Có câu trả lời, lượt làm, phiên bản chấm và kết quả | Đã tính lại bốn kết quả mới nhất của tài khoản khảo sát và khớp kết quả database tại thời điểm kiểm tra. |
| Điểm giảng viên | Bản công bố có `overallScore = 92` và tiêu chí riêng | Điểm tổng đang có thể nhập độc lập; không được mô tả là trung bình tiêu chí nếu không có công thức tương ứng. |
| “Top 10% học sinh tiêu biểu” | Được gán theo ngưỡng điểm ≥90 | Chưa phải phép tính phân vị/thứ hạng từ một nhóm sinh viên thực tế. |

Tài khoản Nguyễn Hoài An xuất hiện trong bộ tài khoản demo; lịch sử local còn có bản đánh giá mang dấu kiểm thử `E2E-VERIFIED`. Không kết luận tất cả dữ liệu của tài khoản đều giả, cũng không coi mọi bản ghi của tài khoản là minh chứng ngoài thực tế. Phân loại ở cấp bản ghi dựa trên nguồn và lịch sử.

## 3. Nguyên nhân trong mã nguồn

| Vị trí | Vấn đề cần chỉnh |
|---|---|
| `app/learner/includes/student-data.php` | Khi không có kỹ năng, lấy `talent_map` của AI thay vào kỹ năng và điểm năng lực. |
| `app/learner/ai/Model/RoadmapPromptRegistry.php` | Hợp đồng/prompt vẫn yêu cầu model trả ba nhóm điểm năng lực. |
| `app/learner/ai/Validation/RoadmapAnalysisValidator.php` | Kiểm tra cấu trúc, giới hạn số và mã evidence chưa chứng minh phép tính ra số. |
| `app/learner/ai/Service/ProfileAnalysisRefreshService.php` | Chuyển bản đồ AI sang hồ sơ tổng hợp. |
| `app/learner/ai/Persistence/DatabaseRoadmapRepository.php` | Bản phân tích lưu cũ có thể tiếp tục trả điểm AI cho nơi sử dụng. |
| `app/learner/data/Database/DatabaseTalentPassportRepository.php` | Kỹ năng từ portfolio có `levelScore = NULL`; cần bảo toàn nghĩa chưa chấm khi đưa lên UI. |
| `app/learner/data/Database/DatabaseStatisticsRepository.php` | Nguồn đọc khác hồ sơ; lấy cả kỹ năng tự khai, đang chờ và đã xác minh. |
| `src/Modules/Teacher/Service/TeacherGradingService.php` | Điểm tổng giảng viên nhập riêng, chưa buộc khớp công thức từ tiêu chí. |
| `app/learner/data/Service/StatisticsService.php` | Nhãn Top % và cách xếp loại có thể tạo hiểu nhầm về căn cứ. |
| `assets/js/learner-assessment.js` | MI có tám chiều ở bộ chấm nhưng biểu đồ đang dùng sáu; MBTI còn quy đổi tỷ lệ khi hiển thị. Cần giải thích và thống nhất, không thay dữ liệu gốc âm thầm. |

Đây là danh sách vị trí đã khảo sát, không phải khẳng định chỉ có từng đó file cần sửa. Phải rà các nơi đọc dữ liệu ở cổng giảng viên, nhà trường, doanh nghiệp và CV.

## 4. Hành vi sau chỉnh sửa

### 4.1. Bốn bài test

- Backend chấm, Gemini không tham gia tạo hoặc sửa điểm.
- Dùng lượt nộp hợp lệ mới nhất của từng loại test theo thứ tự xác định; giữ lịch sử lượt cũ.
- Mỗi điểm liên kết lượt làm, câu trả lời, phiên bản bộ câu hỏi và phiên bản chấm.
- Giữ đúng loại chỉ số: Holland là xu hướng sở thích; MI là kết quả tự đánh giá các chiều; MBTI/DISC là xu hướng theo bài test. Không biến chúng thành điểm Python, Piano hoặc khả năng làm một nghề.
- MI phải cho xem đủ tám chiều. Nếu biểu đồ chỉ hiển thị một phần, ghi rõ và có bảng đầy đủ bên cạnh.
- Bản đồ ba điểm AI hiện tại được bỏ hoặc thay bằng dữ liệu test gốc có nhãn nguồn; không tạo công thức mới chỉ để giữ hình thức ba thanh điểm.

### 4.2. Giảng viên đánh giá kỹ năng

- Điểm chính thức phải nối được người học, kỹ năng, người có quyền chấm, bản đánh giá được công bố và phiên bản.
- Điểm chấm trực tiếp phải ghi đúng là “Giảng viên chấm trực tiếp”.
- Điểm theo rubric phải do backend tính, có chi tiết tiêu chí và công thức; không để frontend hay Gemini quyết định điểm tổng.
- Dữ liệu lịch sử nhập điểm tổng trực tiếp vẫn giữ nguyên, ghi rõ phương thức. Không hồi tố công thức mới lên số cũ.
- Khi có nhiều bản đánh giá cùng kỹ năng, lấy bản công bố hợp lệ mới nhất; thứ tự phụ là mã bản ghi để xử lý trùng thời gian. Bản nháp, thu hồi, bị thay thế hoặc ngoài quyền không được dùng.

### 4.3. Dự án và thực tập

- Một dự án hoàn thành không tự chứng minh mọi thành viên đã đạt mọi kỹ năng.
- Cần có liên kết cá nhân, ngữ cảnh hợp lệ, báo cáo hoàn thành và quyết định duyệt của người có quyền.
- Có minh chứng nhưng chưa chấm: hiển thị “Có minh chứng · Chưa chấm điểm”; giá trị điểm là `NULL`.
- Muốn hiện điểm thì phải có đánh giá kỹ năng trực tiếp hoặc rubric gắn đúng báo cáo/ngữ cảnh và người đánh giá có quyền.
- Sửa/thu hồi minh chứng phải cập nhật các điểm phụ thuộc. Không cộng điểm tự động theo số dự án, số giờ hoặc số báo cáo.

### 4.4. AI và điểm phù hợp

- Gemini được diễn giải dữ kiện và đề xuất hành động, không được tạo điểm năng lực, điểm kỹ năng, điểm giảng viên hoặc thành tích đã xảy ra.
- Bỏ trường điểm năng lực khỏi hợp đồng đầu ra model. Backend chặn phản hồi chứa trường trái hợp đồng; không chỉ thêm lời nhắc vào prompt.
- Con số đánh giá trong phần giải thích được dựng từ dữ kiện đã xác minh bằng mẫu/backend. Lời văn model có số không đối chiếu được phải bị loại hoặc thay bằng lời giải thích từ dữ liệu hệ thống.
- Điểm phù hợp chỉ xuất hiện ở gợi ý hoạt động, dự án và vị trí thực tập. Giữ bộ tính hệ thống hiện có trong `OpportunityScore`, kèm thành phần và căn cứ; điểm Gemini cũ chỉ phục vụ chẩn đoán, không dùng hiển thị/xếp hạng.
- Không gọi độ phù hợp là năng lực thực tế hoặc xác suất được tuyển dụng. Không đưa điểm phù hợp ngược vào hồ sơ kỹ năng.
- Chạy lại AI, thay model hoặc AI lỗi không được làm đổi điểm đánh giá chính thức.
- Bản AI cũ vẫn có thể giữ để truy lịch sử nhưng không được tiếp tục cấp điểm năng lực cho giao diện/API.

## 5. Công thức và cách giải thích

### 5.1. Likert trong bộ chấm hiện hành

Với `n` câu hợp lệ của một chiều và các giá trị `x` sau đảo chiều:

```text
score = round(100 × (sum(x) − n) / (4 × n))
Câu đảo chiều: x = 6 − câu_trả_lời
```

Ví dụ: bốn câu đều chọn 2, không đảo chiều: `(8 − 4) / 16 × 100 = 25%`. Đây là tỷ lệ quy đổi câu trả lời; không có nghĩa người học chỉ có 25% năng lực thực hành.

Không tính “đã có kết quả” nếu thiếu câu bắt buộc hoặc không có câu hợp lệ. MBTI áp dụng xử lý từng trục của bộ chấm có phiên bản; tỷ lệ cặp trục trên UI phải dùng chung quy tắc và giải thích chênh lệch làm tròn nếu có.

### 5.2. Rubric mới

Phiên bản đề xuất `rubric-weighted-1.0`, tiêu chí bắt đầu từ 0:

```text
total = round(100 × sum(weight_i × score_i / max_i) / sum(weight_i), 2)
```

- `max_i > 0`, `weight_i > 0`, `0 <= score_i <= max_i`.
- Cần đủ tiêu chí bắt buộc; thiếu điểm không đổi thành 0 và không tự chia lại trọng số.
- Phiên bản mặc định dùng trọng số 1 cho mỗi tiêu chí; lưu trọng số trong bản chụp phép tính.
- Thang có điểm tối thiểu khác 0 không tự dùng công thức này; phải có phiên bản công thức được định nghĩa riêng.
- Ví dụ: 8/10 và 18/20, trọng số 1 và 1 → 85/100.
- Mọi thay đổi công thức áp dụng theo phiên bản mới, không thay lịch sử.

### 5.3. Điểm tổng hồ sơ và xếp loại

Không trộn điểm test, điểm phù hợp và kỹ năng thực hành. Nếu giữ chỉ số tổng hồ sơ, tên phải là “Trung bình kỹ năng đã được chấm”, công thức là trung bình các điểm kỹ năng hiện hành hợp lệ trên thang 100, tính một lần cho mỗi kỹ năng. Không có kỹ năng đủ điều kiện thì hiện “Chưa đủ dữ liệu”. Lưu/hiển thị danh sách thành phần và phiên bản phép tổng hợp `skill-mean-1.0`; chỉ dùng trọng số bằng nhau đã công bố.

Một loại điểm dùng chung quy tắc làm tròn và xếp loại trên mọi trang. Bỏ nhãn Top % chưa có dữ liệu nhóm và cách tính phân vị; xây hệ thống phân vị thực nằm ngoài phạm vi thay đổi này.

## 6. Database: tận dụng và bổ sung có giới hạn

| Bảng đang có | Cách tận dụng |
|---|---|
| `test_attempts`, `test_results`, `learner_assessment_answers`, `learner_assessment_versions` | Nguồn test, câu trả lời, kết quả, phiên bản. Không tạo bản sao song song. |
| `assessments`, `assessment_scores`, `assessment_criteria` | Nguồn đánh giá giảng viên và tiêu chí hiện hành. |
| `learner_evaluations`, `learner_evaluation_items` | Bản đánh giá có revision, người chấm, ngữ cảnh, kỹ năng và điểm nullable. |
| `learner_skill_evidence` | Đã có nguồn/phiên bản nguồn, người ghi, thời điểm thu hồi và liên kết bản thay thế. |
| `student_skills` | Bản tổng hợp phục vụ đọc; không được tự coi là nguồn chứng minh cuối cùng. |
| `project_submissions`, `learner_internship_reports`, `learner_portfolio_skills`, `learner_portfolio_history` | Báo cáo, kỹ năng, người duyệt và lịch sử. |

Chỉ bổ sung metadata phương thức/công thức và snapshot phép tính còn thiếu vào bảng đánh giá hiện có; kế hoạch đi kèm xác định vị trí và cách chuyển dữ liệu. Có cột revision không đồng nghĩa mọi đường cập nhật đang giữ lịch sử đúng: phải kiểm tra và sửa cả hành vi ghi.

## 7. Nội dung “Nguồn và cách tính” trên giao diện

Mỗi điểm có phần giải thích gồm: tên loại điểm, giá trị/thang, phương thức, ngày tính/chấm, nguồn liên quan, người chấm nếu người xem có quyền, phiên bản công thức và phép tính/thành phần. Với điểm trực tiếp, chỉ dẫn đến bản đánh giá; không bịa ra công thức.

Ví dụ:

> Python 85/100 — Giảng viên chấm theo rubric. Tiêu chí: thực hành 8/10, sản phẩm 18/20; trọng số bằng nhau. Điểm tổng: 85/100. Nguồn: bản đánh giá được công bố và báo cáo dự án liên quan.

> Giao tiếp — Có minh chứng từ báo cáo thực tập đã duyệt. Chưa có đánh giá điểm.

Phần diễn giải phải đủ hiểu với khách hàng; mã kỹ thuật/ID đặt trong chi tiết khi cần đối soát. Giới hạn thông tin nguồn theo quyền người xem, không mở công khai câu trả lời và thông tin cá nhân.

## 8. Dữ liệu cũ, phạm vi ảnh hưởng và rủi ro

- Phân loại bản ghi: có nguồn đủ kiểm tra; dữ liệu demo có dấu nguồn; chưa đủ nguồn. Không suy theo tên hoặc email để tự hợp thức hóa/xóa dữ liệu.
- Bản chưa đủ nguồn không tham gia điểm chính thức, nhưng giữ lịch sử để đối soát. Không lấy điểm AI điền lại ô trống.
- Thử chuyển dữ liệu trên bản sao database trước. Không xóa bảng, reset tài khoản hoặc ghi đè điểm test lịch sử hàng loạt.
- Rà cổng người học, giảng viên, nhà trường, doanh nghiệp, API và xuất CV. Điểm/bộ lọc/gợi ý có thể đổi khi loại bỏ dữ liệu không đủ căn cứ.
- Rủi ro đáng chú ý: cache AI cũ, số liệu legacy không có nguồn, liên kết sai người học, trùng sự kiện, thu hồi chưa đồng bộ và cách đọc khác nhau giữa các trang.
- Workspace có chỉnh sửa chưa commit ở phần AI và giao diện; phải bảo toàn, đối chiếu diff trước khi sửa.

## 9. Những việc không nằm trong phạm vi

Không viết lại hệ thống đăng nhập/phân quyền, không đổi nhà cung cấp AI, không đổi trọng số gợi ý khi không cần thiết cho tính đúng của nguồn, không xây lại toàn bộ database, không tự cấp chứng chỉ/huy hiệu và không tuyên bố kiểm định khoa học cho bốn bài trắc nghiệm.

## 10. Kết quả bắt buộc khi hoàn thành

Khách hàng phải trả lời được năm câu hỏi với mỗi điểm: **điểm gì, lấy từ đâu, ai chấm hoặc công thức nào, tính khi nào, còn minh chứng hợp lệ không**. Tài khoản mới làm bốn bài test chỉ có kết quả tương ứng; điểm kỹ năng thực hành cần thêm đánh giá/minh chứng hợp lệ. Các bước triển khai và ma trận kiểm thử được quản lý trong file kế hoạch đi kèm.
