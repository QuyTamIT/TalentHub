<?php

declare(strict_types=1);

if (!function_exists('credential_escape')) {
    function credential_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('credential_date_label')) {
    function credential_date_label(mixed $value): string
    {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? (string) $value : date('d/m/Y', $timestamp);
    }
}

if (!function_exists('credential_badge_reason')) {
    function credential_badge_reason(mixed $context): string
    {
        $decoded = json_decode((string) $context, true);
        return is_array($decoded) ? trim((string) ($decoded['reason'] ?? $decoded['note'] ?? '')) : '';
    }
}

$credentialData = is_array($credentialData ?? null) ? $credentialData : [];
$badges = is_array($credentialData['badges'] ?? null) ? $credentialData['badges'] : [];
$certificates = is_array($credentialData['certificates'] ?? null) ? $credentialData['certificates'] : [];
$students = is_array($credentialData['students'] ?? null) ? $credentialData['students'] : [];
$classes = is_array($credentialData['classes'] ?? null) ? $credentialData['classes'] : [];
$certificateAwards = is_array($credentialData['awards'] ?? null) ? $credentialData['awards'] : [];
$badgeAwards = is_array($credentialData['badgeAwards'] ?? null) ? $credentialData['badgeAwards'] : [];
$learning = is_array($credentialData['learningSummary'] ?? null) ? $credentialData['learningSummary'] : [];
$csrf = (string) ($credentialCsrfToken ?? '');
$issuer = (string) ($credentialIssuerName ?? 'Nhà trường');
$today = date('Y-m-d');
?>

<div class="credential-shell" data-credential-management>
    <header class="credential-heading">
        <div>
            <span class="credential-heading__eyebrow">Ghi nhận thành tích sinh viên</span>
            <h2>Huy hiệu &amp; Chứng chỉ</h2>
        </div>
        <span class="credential-heading__scope"><?= credential_escape($issuer); ?></span>
    </header>

    <?php if (!empty($credentialFlash)): ?>
        <div class="credential-alert credential-alert--success" role="status"><?= credential_escape($credentialFlash); ?></div>
    <?php endif; ?>
    <?php if (!empty($credentialError)): ?>
        <div class="credential-alert credential-alert--error" role="alert"><?= credential_escape($credentialError); ?></div>
    <?php endif; ?>

    <section class="credential-metrics" aria-label="Tổng quan thành tích">
        <article class="credential-metric">
            <span>Mẫu huy hiệu</span>
            <strong><?= number_format(count($badges)); ?></strong>
        </article>
        <article class="credential-metric credential-metric--certificate">
            <span>Mẫu chứng chỉ</span>
            <strong><?= number_format(count($certificates)); ?></strong>
        </article>
        <article class="credential-metric credential-metric--learning">
            <span>Phút học online</span>
            <strong><?= number_format((int) ($learning['totalMinutes'] ?? 0)); ?></strong>
        </article>
        <article class="credential-metric credential-metric--students">
            <span>Sinh viên đã ghi nhận</span>
            <strong><?= number_format((int) ($learning['activeStudents'] ?? 0)); ?>/<?= number_format((int) ($learning['trackedStudents'] ?? count($students))); ?></strong>
        </article>
    </section>

    <nav class="credential-tabs" aria-label="Quản lý thành tích" role="tablist">
        <button type="button" class="credential-tab is-active" role="tab" aria-selected="true" aria-controls="credential-award-panel" data-credential-tab="award">Cấp thành tích</button>
        <button type="button" class="credential-tab" role="tab" aria-selected="false" aria-controls="credential-catalog-panel" data-credential-tab="catalog">Danh mục</button>
        <button type="button" class="credential-tab" role="tab" aria-selected="false" aria-controls="credential-learning-panel" data-credential-tab="learning">Chuyên cần online</button>
        <button type="button" class="credential-tab" role="tab" aria-selected="false" aria-controls="credential-history-panel" data-credential-tab="history">Lịch sử cấp</button>
    </nav>

    <section id="credential-award-panel" class="credential-panel" role="tabpanel" data-credential-panel="award">
        <div class="credential-section-heading">
            <div>
                <h3>Cấp thủ công</h3>
                <p>Hoạt động phong trào, sự kiện và thành tích ngoại khóa</p>
            </div>
        </div>

        <?php if ($students === [] || ($badges === [] && $certificates === [])): ?>
            <div class="credential-empty">Cần có sinh viên và ít nhất một mẫu thành tích đang hoạt động.</div>
        <?php else: ?>
            <form method="post" class="credential-form" data-credential-award-form>
                <input type="hidden" name="csrfToken" value="<?= credential_escape($csrf); ?>">
                <input type="hidden" name="action" value="award_credential">

                <div class="credential-form__grid">
                    <label class="credential-field">
                        <span>Loại thành tích</span>
                        <select name="credentialKind" data-credential-kind required>
                            <?php if ($badges !== []): ?><option value="badge">Huy hiệu</option><?php endif; ?>
                            <?php if ($certificates !== []): ?><option value="certificate">Chứng chỉ</option><?php endif; ?>
                        </select>
                    </label>

                    <label class="credential-field" data-credential-select="badge">
                        <span>Huy hiệu</span>
                        <select name="badgeId" required>
                            <?php foreach ($badges as $badge): ?>
                                <?php if (($badge['status'] ?? '') === 'active'): ?>
                                    <option value="<?= credential_escape($badge['id']); ?>"><?= credential_escape($badge['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="credential-field" data-credential-select="certificate" hidden>
                        <span>Chứng chỉ</span>
                        <select name="catalogId" disabled required>
                            <?php foreach ($certificates as $certificate): ?>
                                <?php if (($certificate['status'] ?? '') === 'active'): ?>
                                    <option value="<?= credential_escape($certificate['id']); ?>"><?= credential_escape($certificate['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <fieldset class="credential-field credential-field--full credential-recipient">
                        <legend>Đối tượng nhận</legend>
                        <div class="credential-segmented">
                            <label><input type="radio" name="recipientType" value="student" checked><span>Sinh viên</span></label>
                            <label><input type="radio" name="recipientType" value="class"><span>Cả lớp</span></label>
                        </div>
                    </fieldset>

                    <label class="credential-field credential-field--full" data-recipient-field="student">
                        <span>Sinh viên</span>
                        <select name="studentId" required>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= credential_escape($student['id']); ?>" data-class-id="<?= credential_escape($student['classId']); ?>">
                                    <?= credential_escape($student['fullName']); ?> · <?= credential_escape($student['className']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="credential-field credential-field--full" data-recipient-field="class" hidden>
                        <span>Lớp học</span>
                        <select name="classId" disabled required>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= credential_escape($class['id']); ?>" data-student-count="<?= (int) $class['studentCount']; ?>">
                                    <?= credential_escape($class['name']); ?> · <?= number_format((int) $class['studentCount']); ?> sinh viên
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="credential-field">
                        <span>Ngày cấp</span>
                        <input type="date" name="issuedDate" value="<?= credential_escape($today); ?>" max="<?= credential_escape($today); ?>" required>
                    </label>

                    <label class="credential-field">
                        <span>Hoạt động/Sự kiện</span>
                        <input type="text" name="activityName" maxlength="255" placeholder="Tên hoạt động tham gia">
                    </label>

                    <label class="credential-field credential-field--full">
                        <span>Lý do ghi nhận</span>
                        <textarea name="reason" maxlength="1000" rows="4" required placeholder="Thành tích hoặc đóng góp được ghi nhận"></textarea>
                    </label>
                </div>

                <div class="credential-form__actions">
                    <span class="credential-target-count" data-credential-target-count aria-live="polite">1 sinh viên được chọn</span>
                    <button type="submit" class="credential-button credential-button--primary">Cấp thành tích</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section id="credential-catalog-panel" class="credential-panel" role="tabpanel" data-credential-panel="catalog" hidden>
        <div class="credential-section-heading">
            <div>
                <h3>Quản lý danh mục</h3>
                <p>Tạo mẫu sử dụng riêng trong phạm vi Nhà trường</p>
            </div>
        </div>

        <div class="credential-catalog-grid">
            <form method="post" class="credential-form credential-form--catalog">
                <input type="hidden" name="csrfToken" value="<?= credential_escape($csrf); ?>">
                <input type="hidden" name="action" value="create_badge">
                <h4>Mẫu huy hiệu</h4>
                <div class="credential-form__grid">
                    <label class="credential-field"><span>Mã</span><input name="code" maxlength="100" pattern="[a-z0-9][a-z0-9_-]*" required placeholder="tinh_nguyen_xanh"></label>
                    <label class="credential-field"><span>Tên huy hiệu</span><input name="name" maxlength="255" required></label>
                    <label class="credential-field"><span>Danh mục</span><input name="category" maxlength="64" value="school_activity" required></label>
                    <label class="credential-field"><span>Cấp độ</span><input name="level" type="number" value="1" min="1" max="100" required></label>
                    <label class="credential-field credential-field--full"><span>Cách cấp</span>
                        <select name="awardMode" data-award-mode>
                            <option value="manual">Cấp thủ công</option>
                            <option value="automatic">Tự động theo mốc</option>
                        </select>
                    </label>
                    <div class="credential-auto-fields credential-field--full" data-auto-fields hidden>
                        <label class="credential-field"><span>Tiêu chí</span>
                            <select name="fact" disabled>
                                <option value="online_learning_minutes">Phút học online</option>
                                <option value="attended_activity_count">Số hoạt động đã tham gia</option>
                                <option value="confirmed_experience_hours">Giờ trải nghiệm xác nhận</option>
                                <option value="submitted_assessment_type_count">Nhóm bài đánh giá hoàn thành</option>
                                <option value="published_teacher_evaluation_count">Đánh giá giáo viên đã công bố</option>
                            </select>
                        </label>
                        <label class="credential-field"><span>Ngưỡng</span><input type="number" name="threshold" min="1" max="1000000" value="60" disabled required></label>
                    </div>
                    <label class="credential-field credential-field--full"><span>Mô tả</span><textarea name="description" maxlength="5000" rows="4" required></textarea></label>
                </div>
                <button type="submit" class="credential-button credential-button--primary">Tạo huy hiệu</button>
            </form>

            <form method="post" class="credential-form credential-form--catalog">
                <input type="hidden" name="csrfToken" value="<?= credential_escape($csrf); ?>">
                <input type="hidden" name="action" value="create_certificate">
                <input type="hidden" name="status" value="active">
                <h4>Mẫu chứng chỉ</h4>
                <div class="credential-form__grid">
                    <label class="credential-field"><span>Mã</span><input name="code" maxlength="100" pattern="[a-z0-9][a-z0-9_-]*" required placeholder="chien_dich_mua_he"></label>
                    <label class="credential-field"><span>Tên chứng chỉ</span><input name="name" maxlength="255" required></label>
                    <label class="credential-field credential-field--full"><span>Đơn vị cấp</span><input name="issuerName" maxlength="255" value="<?= credential_escape($issuer); ?>" required></label>
                    <label class="credential-field credential-field--full"><span>Mô tả</span><textarea name="description" maxlength="5000" rows="4" required></textarea></label>
                </div>
                <button type="submit" class="credential-button credential-button--certificate">Tạo chứng chỉ</button>
            </form>
        </div>

        <div class="credential-table-block">
            <h4>Mẫu đang có</h4>
            <div class="credential-table-wrap">
                <table class="credential-table">
                    <thead><tr><th>Loại</th><th>Mã</th><th>Tên</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                        <?php foreach ($badges as $item): ?>
                            <tr><td><span class="credential-kind credential-kind--badge">Huy hiệu</span></td><td><?= credential_escape($item['code']); ?></td><td><?= credential_escape($item['name']); ?></td><td><?= credential_escape($item['status']); ?></td></tr>
                        <?php endforeach; ?>
                        <?php foreach ($certificates as $item): ?>
                            <tr><td><span class="credential-kind credential-kind--certificate">Chứng chỉ</span></td><td><?= credential_escape($item['code']); ?></td><td><?= credential_escape($item['name']); ?></td><td><?= credential_escape($item['status']); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($badges === [] && $certificates === []): ?><tr><td colspan="4">Chưa có mẫu thành tích.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="credential-learning-panel" class="credential-panel" role="tabpanel" data-credential-panel="learning" hidden>
        <div class="credential-section-heading">
            <div><h3>Chuyên cần online</h3><p>Thời gian học tập chủ động được ghi nhận trên TalentHub</p></div>
            <div class="credential-milestones" aria-label="Các mốc chuyên cần"><span>60 phút</span><span>300 phút</span><span>1.200 phút</span></div>
        </div>
        <div class="credential-table-wrap">
            <table class="credential-table credential-learning-table">
                <thead><tr><th>Sinh viên</th><th>Lớp</th><th>Thời gian</th><th>Mốc hiện tại</th><th>Tiến độ mốc kế</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $minutes = (int) ($student['onlineMinutes'] ?? 0);
                        $next = $minutes < 60 ? 60 : ($minutes < 300 ? 300 : ($minutes < 1200 ? 1200 : 1200));
                        $progress = $minutes >= 1200 ? 100 : min(100, (int) floor($minutes / max(1, $next) * 100));
                        ?>
                        <tr>
                            <td><strong><?= credential_escape($student['fullName']); ?></strong><small><?= credential_escape($student['email']); ?></small></td>
                            <td><?= credential_escape($student['className']); ?></td>
                            <td><?= number_format($minutes); ?> phút</td>
                            <td><?= (int) ($student['attendanceMilestone'] ?? 0) > 0 ? number_format((int) $student['attendanceMilestone']) . ' phút' : 'Chưa đạt mốc'; ?></td>
                            <td><div class="credential-progress"><span style="width: <?= $progress; ?>%"></span></div><small><?= $progress; ?>%</small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($students === []): ?><tr><td colspan="5">Chưa có sinh viên trong phạm vi quản lý.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="credential-history-panel" class="credential-panel" role="tabpanel" data-credential-panel="history" hidden>
        <div class="credential-section-heading"><div><h3>Lịch sử cấp</h3><p>Các lượt cấp và cập nhật gần nhất</p></div></div>

        <div class="credential-table-block">
            <h4>Huy hiệu</h4>
            <div class="credential-table-wrap">
                <table class="credential-table">
                    <thead><tr><th>Sinh viên</th><th>Lớp</th><th>Huy hiệu</th><th>Ngày cấp</th><th>Lý do</th></tr></thead>
                    <tbody>
                        <?php foreach ($badgeAwards as $award): ?>
                            <tr><td><?= credential_escape($award['studentName']); ?></td><td><?= credential_escape($award['className']); ?></td><td><?= credential_escape($award['name']); ?></td><td><?= credential_escape(credential_date_label($award['awardedAt'])); ?></td><td><?= credential_escape(credential_badge_reason($award['awardContext'])); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($badgeAwards === []): ?><tr><td colspan="5">Chưa có huy hiệu được cấp.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="credential-table-block">
            <h4>Chứng chỉ</h4>
            <div class="credential-table-wrap">
                <table class="credential-table">
                    <thead><tr><th>Sinh viên</th><th>Chứng chỉ</th><th>Ngày cấp</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        <?php foreach ($certificateAwards as $award): ?>
                            <tr>
                                <td><?= credential_escape($award['studentName']); ?><small><?= credential_escape($award['className']); ?></small></td>
                                <td><?= credential_escape($award['name']); ?></td>
                                <td><?= credential_escape(credential_date_label($award['issuedAt'])); ?></td>
                                <td><?= credential_escape($award['reason']); ?></td>
                                <td><span class="credential-status credential-status--<?= credential_escape($award['status']); ?>"><?= $award['status'] === 'issued' ? 'Đã cấp' : 'Đã thu hồi'; ?></span></td>
                                <td>
                                    <?php if ($award['status'] === 'issued'): ?>
                                        <form method="post" class="credential-revoke-form" data-confirm="Thu hồi chứng chỉ này?">
                                            <input type="hidden" name="csrfToken" value="<?= credential_escape($csrf); ?>">
                                            <input type="hidden" name="action" value="revoke_certificate">
                                            <input type="hidden" name="awardId" value="<?= credential_escape($award['id']); ?>">
                                            <input name="reason" maxlength="1000" required aria-label="Lý do thu hồi" placeholder="Lý do thu hồi">
                                            <button type="submit" class="credential-button credential-button--danger">Thu hồi</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($certificateAwards === []): ?><tr><td colspan="6">Chưa có chứng chỉ được cấp.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
