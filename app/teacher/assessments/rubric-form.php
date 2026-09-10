<?php if ($assessmentStatus === 'published'): ?>
    <section class="teacher-assessment-readonly" aria-label="Đánh giá đã công bố">
        <p><strong>Đã công bố — chỉ đọc</strong> · <?= $escape($student['publishedAt'] ?? ''); ?></p>
        <p>Điểm tổng: <strong><?= $escape($student['overallScore']); ?>/100</strong></p>
        <dl>
            <?php foreach ($student['savedCriteria'] ?? [] as $criterion): ?>
                <dt><?= $escape($criterion['name']); ?></dt>
                <dd><?= $escape($criterion['score']); ?> / <?= $escape($criterion['maxScore']); ?></dd>
            <?php endforeach; ?>
        </dl>
        <p class="teacher-assessment-comment"><?= $escape($student['comment'] ?? 'Chưa có nhận xét.'); ?></p>
    </section>
<?php else: ?>
    <form method="post" class="teacher-grading-form">
        <input type="hidden" name="csrfToken" value="<?= $escape($session->csrfToken()); ?>">
        <input type="hidden" name="mode" value="<?= $escape($mode); ?>">
        <input type="hidden" name="contextId" value="<?= $escape($data['selectedContext']['id']); ?>">
        <input type="hidden" name="studentId" value="<?= $escape($student['studentId']); ?>">
        <input type="hidden" name="assessmentId" value="<?= $escape($student['assessmentId'] ?? ''); ?>">
        <input type="hidden" name="expectedVersion" value="<?= $escape($student['assessmentVersion'] ?? 0); ?>">
        <input type="hidden" name="q" value="<?= $escape($search); ?>">
        <label class="teacher-grading-field">
            <span>Điểm tổng / 100 (nhập độc lập)</span>
            <input type="number" name="overallScore" min="0" max="100" step="0.01" value="<?= $escape($student['overallScore'] ?? ''); ?>">
        </label>
        <fieldset class="teacher-grading-criteria">
            <legend>Điểm tiêu chí Rubric</legend>
            <div class="teacher-grading-criteria__grid">
                <?php foreach ($data['criteria'] as $criterion): ?>
                    <label class="teacher-grading-field">
                        <span><?= $escape($criterion['name']); ?> (<?= $escape($criterion['minScore']); ?>–<?= $escape($criterion['maxScore']); ?>)</span>
                        <input type="number" name="criteria[<?= $escape($criterion['id']); ?>]"
                            min="<?= $escape($criterion['minScore']); ?>" max="<?= $escape($criterion['maxScore']); ?>"
                            step="<?= $escape($criterion['scoreStep'] ?? '0.01'); ?>"
                            value="<?= $escape($student['criteriaScores'][$criterion['id']] ?? ''); ?>">
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <label class="teacher-grading-field teacher-grading-field--comment">
            <span>Nhận xét</span>
            <textarea name="comment" rows="3" maxlength="1000"><?= $escape($student['comment'] ?? ''); ?></textarea>
        </label>
        <div class="teacher-grading-form__actions">
            <button type="submit" name="assessmentStatus" value="draft" class="teacher-grading-button teacher-grading-button--secondary">Lưu nháp</button>
            <button type="submit" name="assessmentStatus" value="published" class="teacher-grading-button teacher-grading-button--primary">Công bố đánh giá</button>
        </div>
    </form>
<?php endif; ?>
