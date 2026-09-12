<?php
$skillCategoryLabels = [
    'technical' => 'Công nghệ & Kỹ thuật',
    'business' => 'Kinh doanh & Khởi nghiệp',
    'marketing' => 'Marketing & Truyền thông',
    'creative' => 'Thiết kế & Sáng tạo',
    'data' => 'Dữ liệu & Thống kê',
    'academic' => 'Học thuật & Nghiên cứu',
    'finance' => 'Tài chính & Kế toán',
    'music' => 'Âm nhạc & Trình diễn',
    'arts' => 'Mỹ thuật & Nghệ thuật',
    'soft' => 'Kỹ năng mềm & Giao tiếp',
    'operations' => 'Vận hành & Sản xuất',
    'sports' => 'Thể thao & Thể chất',
];

$skillsByCategory = [];
foreach ($data['availableSkills'] ?? [] as $avail) {
    $cat = $avail['category'] ?? 'technical';
    $skillsByCategory[$cat][] = $avail;
}
?>

<?php if ($assessmentStatus === 'published'): ?>
    <section class="teacher-assessment-readonly" aria-label="Đánh giá đã công bố">
        <div class="teacher-assessment-readonly__meta-bar">
            <span class="teacher-status-pill teacher-status-pill--positive">Đã công bố</span>
            <span class="teacher-assessment-date">Ngày công bố: <?= $escape($student['publishedAt'] ?? ''); ?></span>
        </div>

        <div class="teacher-assessment-score-card">
            <span class="teacher-assessment-score-label">Điểm tổng kết:</span>
            <span class="teacher-assessment-score-val">
                <?= $escape($student['overallScore']); ?><span class="teacher-assessment-score-denom">/100</span>
            </span>
        </div>

        <?php if (!empty($student['savedCriteria'])): ?>
            <div class="teacher-assessment-section">
                <h4 class="teacher-assessment-section__title">Điểm Tiêu chí Rubric</h4>
                <dl class="teacher-rubric-summary-grid">
                    <?php foreach ($student['savedCriteria'] as $criterion): ?>
                        <div class="teacher-rubric-summary-item">
                            <dt><?= $escape($criterion['name']); ?></dt>
                            <dd>
                                <strong><?= $escape($criterion['score']); ?></strong>
                                <span>/ <?= $escape($criterion['maxScore']); ?></span>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>

        <?php if (!empty($student['studentSkills'])): ?>
            <div class="teacher-assessment-section">
                <h4 class="teacher-assessment-section__title">Kỹ năng sinh viên đã ghi nhận (Talent Passport & AI Matching)</h4>
                <div class="teacher-skills-pill-list">
                    <?php foreach ($student['studentSkills'] as $sk): ?>
                        <span class="teacher-skill-pill">
                            <span class="teacher-skill-pill__cat"><?= $escape($skillCategoryLabels[$sk['category']] ?? $sk['category']); ?></span>
                            <strong class="teacher-skill-pill__name"><?= $escape($sk['name']); ?></strong>
                            <span class="teacher-skill-pill__score"><?= number_format((float) $sk['levelScore'], 1); ?>/100</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($student['comment'])): ?>
            <div class="teacher-assessment-section">
                <h4 class="teacher-assessment-section__title">Nhận xét của Giảng viên</h4>
                <blockquote class="teacher-assessment-comment-box"><?= $escape($student['comment']); ?></blockquote>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <form method="post" class="teacher-grading-form" data-student-id="<?= $escape($student['studentId']); ?>">
        <input type="hidden" name="csrfToken" value="<?= $escape($session->csrfToken()); ?>">
        <input type="hidden" name="mode" value="<?= $escape($mode); ?>">
        <input type="hidden" name="contextId" value="<?= $escape($data['selectedContext']['id']); ?>">
        <input type="hidden" name="studentId" value="<?= $escape($student['studentId']); ?>">
        <input type="hidden" name="assessmentId" value="<?= $escape($student['assessmentId'] ?? ''); ?>">
        <input type="hidden" name="expectedVersion" value="<?= $escape($student['assessmentVersion'] ?? 0); ?>">
        <input type="hidden" name="q" value="<?= $escape($search); ?>">

        <!-- Overall Score Box -->
        <div class="teacher-grading-score-panel">
            <label class="teacher-grading-field teacher-grading-field--score">
                <span class="teacher-grading-field__label">
                    <strong>Điểm tổng kết / 100</strong>
                    <span class="teacher-field-hint">(Điểm chung đánh giá năng lực của học viên)</span>
                </span>
                <div class="teacher-input-affix-group">
                    <input type="number" name="overallScore" min="0" max="100" step="0.01"
                           placeholder="Ví dụ: 85.50"
                           value="<?= $escape($student['overallScore'] ?? ''); ?>" required>
                    <span class="teacher-input-affix">/ 100</span>
                </div>
            </label>
        </div>

        <!-- Rubric Criteria Fieldset -->
        <?php if (!empty($data['criteria'])): ?>
            <fieldset class="teacher-grading-fieldset teacher-grading-criteria">
                <legend class="teacher-grading-fieldset__legend">
                    <span>Điểm tiêu chí Rubric</span>
                    <span class="teacher-field-badge">Bắt buộc khi công bố</span>
                </legend>
                <div class="teacher-grading-criteria__grid">
                    <?php foreach ($data['criteria'] as $criterion): ?>
                        <label class="teacher-grading-field">
                            <span class="teacher-grading-field__label">
                                <?= $escape($criterion['name']); ?>
                                <small class="teacher-field-scale">(<?= $escape($criterion['minScore']); ?>–<?= $escape($criterion['maxScore']); ?>)</small>
                            </span>
                            <input type="number" name="criteria[<?= $escape($criterion['id']); ?>]"
                                min="<?= $escape($criterion['minScore']); ?>" max="<?= $escape($criterion['maxScore']); ?>"
                                step="<?= $escape($criterion['scoreStep'] ?? '0.01'); ?>"
                                placeholder="<?= $escape($criterion['minScore']); ?> - <?= $escape($criterion['maxScore']); ?>"
                                value="<?= $escape($student['criteriaScores'][$criterion['id']] ?? ''); ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <!-- Student Skills Evaluation Fieldset -->
        <fieldset class="teacher-grading-fieldset teacher-grading-skills" id="skills-fieldset-<?= $escape($student['studentId']); ?>">
            <div class="teacher-grading-skills__header">
                <div>
                    <legend class="teacher-grading-fieldset__legend">
                        <span>Đánh giá Kỹ năng Năng lực</span>
                        <span class="teacher-badge-ai">AI Matching & Talent Passport</span>
                    </legend>
                    <p class="teacher-grading-skills__hint">
                        Ghi nhận kỹ năng đạt được. Kỹ năng này sẽ được xác thực chính thức trên Talent Passport của sinh viên và tính vào 40% điểm kỹ năng + 25% bằng chứng thực tế khi AI gợi ý việc làm.
                    </p>
                </div>
                <button type="button" class="teacher-btn-add-skill" onclick="window.teacherAddSkillRow('<?= $escape($student['studentId']); ?>')">
                    + Thêm kỹ năng
                </button>
            </div>

            <div class="teacher-grading-skills__list" id="skills-list-<?= $escape($student['studentId']); ?>">
                <?php
                $existingSkills = $student['studentSkills'] ?? [];
                if (empty($existingSkills)) {
                    $existingSkills = [['skillId' => '', 'name' => '', 'category' => 'technical', 'levelScore' => '']];
                }
                foreach ($existingSkills as $idx => $sk):
                    $currentSkillId = $sk['skillId'] ?? '';
                    $currentName = $sk['name'] ?? '';
                    $currentCat = $sk['category'] ?? 'technical';
                    $currentScore = isset($sk['levelScore']) && $sk['levelScore'] !== '' ? number_format((float) $sk['levelScore'], 2, '.', '') : '';
                    $isCustom = $currentSkillId === '' && $currentName !== '';
                ?>
                    <div class="teacher-skill-row" data-index="<?= $idx; ?>">
                        <div class="teacher-skill-col teacher-skill-col--select">
                            <label class="teacher-sublabel">Chọn kỹ năng</label>
                            <select name="skills[<?= $idx; ?>][skillId]" class="typeui-select teacher-skill-select" onchange="window.teacherOnSkillSelectChange(this)">
                                <option value="">-- Chọn kỹ năng trong danh mục --</option>
                                <?php foreach ($skillsByCategory as $catKey => $catSkills): ?>
                                    <optgroup label="<?= $escape($skillCategoryLabels[$catKey] ?? $catKey); ?>">
                                        <?php foreach ($catSkills as $avail): ?>
                                            <option value="<?= $escape($avail['id']); ?>"
                                                    data-category="<?= $escape($avail['category']); ?>"
                                                    <?= $currentSkillId === $avail['id'] || ($currentName === $avail['name'] && $currentSkillId === '') ? 'selected' : ''; ?>>
                                                <?= $escape($avail['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <option value="__custom__" <?= $isCustom ? 'selected' : ''; ?>>+ Kỹ năng khác (tự nhập)...</option>
                            </select>
                        </div>

                        <div class="teacher-skill-col teacher-skill-col--custom" style="<?= $isCustom ? '' : 'display: none;'; ?>">
                            <label class="teacher-sublabel">Tên kỹ năng mới</label>
                            <input type="text" name="skills[<?= $idx; ?>][skillName]" class="teacher-skill-name-input"
                                   placeholder="Ví dụ: Thiết kế hệ thống, Piano..."
                                   value="<?= $escape($currentName); ?>">
                        </div>

                        <div class="teacher-skill-col teacher-skill-col--cat" style="<?= $isCustom ? '' : 'display: none;'; ?>">
                            <label class="teacher-sublabel">Nhóm ngành</label>
                            <select name="skills[<?= $idx; ?>][category]" class="typeui-select teacher-skill-cat-select">
                                <?php foreach ($skillCategoryLabels as $catVal => $catName): ?>
                                    <option value="<?= $escape($catVal); ?>" <?= $currentCat === $catVal ? 'selected' : ''; ?>>
                                        <?= $escape($catName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="teacher-skill-col teacher-skill-col--score">
                            <label class="teacher-sublabel">Điểm năng lực</label>
                            <div class="teacher-input-affix-group">
                                <input type="number" name="skills[<?= $idx; ?>][score]" min="0" max="100" step="0.1"
                                       placeholder="0 - 100"
                                       value="<?= $escape($currentScore); ?>">
                                <span class="teacher-input-affix">/ 100</span>
                            </div>
                        </div>

                        <div class="teacher-skill-col teacher-skill-col--remove">
                            <button type="button" class="teacher-btn-remove-skill" title="Xóa kỹ năng này" onclick="window.teacherRemoveSkillRow(this)">
                                &times;
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- Teacher Comment -->
        <label class="teacher-grading-field teacher-grading-field--comment">
            <span class="teacher-grading-field__label">
                <strong>Nhận xét & Lời khuyên định hướng</strong>
                <span class="teacher-field-hint">(Hiển thị trực tiếp trên hồ sơ sinh viên)</span>
            </span>
            <textarea name="comment" rows="3" maxlength="1000"
                      placeholder="Nhận xét cụ thể về năng lực, tinh thần trách nhiệm và định hướng phát triển cho sinh viên..."><?= $escape($student['comment'] ?? ''); ?></textarea>
        </label>

        <div class="teacher-grading-form__actions">
            <button type="submit" name="assessmentStatus" value="draft" class="teacher-grading-button teacher-grading-button--secondary">Lưu nháp</button>
            <button type="submit" name="assessmentStatus" value="published" class="teacher-grading-button teacher-grading-button--primary">Công bố đánh giá</button>
        </div>
    </form>
<?php endif; ?>
