<?php
/** @var array $cv Fresh, bounded PassportCvViewModel output. No database or sharing side effects here. */
$escapeCv = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $escapeCv($cv['name']); ?> - CV Talent Passport</title>
    <link rel="stylesheet" href="../../assets/css/learner-passport-cv.css">
</head>
<body data-cv-preview>
    <nav class="cv-toolbar" aria-label="Xuất CV">
        <a href="talent-passport.php">← Talent Passport</a>
        <p>CV chọn lọc 1 trang A4. Khi lưu PDF: chọn A4, tỷ lệ 100%, tắt đầu/chân trang của trình duyệt.</p>
        <button type="button" data-cv-export>Lấy dữ liệu mới &amp; xuất PDF</button>
    </nav>
    <p class="cv-error" data-cv-error role="alert" hidden></p>
    <main class="cv-sheet" aria-label="CV một trang A4">
        <div class="cv-content" data-cv-content>
            <header class="cv-header">
                <p class="cv-eyebrow">TALENT PASSPORT · HỒ SƠ ỨNG TUYỂN</p>
                <h1<?= mb_strlen($cv['name'])>80 ? ' class="cv-name-long"' : ''; ?>><?= $escapeCv($cv['name'] ?: 'Chưa cập nhật họ tên'); ?></h1>
                <?php if ($cv['email'] || $cv['phone']): ?><p class="cv-contact"><?= $escapeCv(implode(' · ', array_filter([$cv['email'],$cv['phone']]))); ?></p><?php endif; ?>
            </header>
            <?php if ($cv['school'] || $cv['class']): ?>
            <section><h2>Học vấn</h2><p class="cv-strong"><?= $escapeCv($cv['school']); ?></p><p><?= $escapeCv($cv['class']); ?></p></section>
            <?php endif; ?>
            <?php if ($cv['projects']): ?>
            <section><h2>Dự án</h2>
                <?php foreach ($cv['projects'] as $project): ?>
                <article><h3><?= $escapeCv($project['title']); ?></h3><p class="cv-meta"><?= $escapeCv(implode(' · ',array_filter([$project['role'],$project['status_label']]))); ?></p>
                    <?php if ($project['contribution']): ?><p>Đóng góp được ghi nhận: <?= $escapeCv($project['contribution']); ?></p><?php endif; ?>
                </article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            <?php if ($cv['internships']): ?>
            <section><h2>Thực tập</h2>
                <?php foreach ($cv['internships'] as $internship): ?>
                <article><h3><?= $escapeCv($internship['title']); ?></h3><p><?= $escapeCv($internship['enterprise']); ?></p><p class="cv-meta"><?= $escapeCv($internship['status_label']); ?></p><?php if (!empty($internship['details'])): ?><p class="cv-meta"><?= $escapeCv($internship['details']); ?></p><?php endif; ?></article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            <?php if ($cv['skills']): ?>
            <section><h2>Kỹ năng có xác nhận</h2><ul class="cv-skills">
                <?php foreach ($cv['skills'] as $skill): ?><li><strong><?= $escapeCv($skill['name']); ?></strong><span><?= $escapeCv($skill['source']); ?></span></li><?php endforeach; ?>
            </ul></section>
            <?php endif; ?>
            <?php if ($cv['evaluations']): ?>
            <section><h2>Nhận xét giảng viên</h2>
                <?php foreach ($cv['evaluations'] as $evaluation): ?>
                <p><?= $escapeCv($evaluation['comment']); ?></p><p class="cv-meta"><?= $escapeCv(implode(' · ',array_filter([$evaluation['teacher'],$evaluation['date']]))); ?> · Đã công bố</p>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            <?php if ($cv['activities']): ?>
            <section class="cv-extracurricular"><h2>Hoạt động ngoại khóa</h2><ul>
                <?php foreach ($cv['activities'] as $activity): ?><li><?= $escapeCv($activity['title']); ?> <span class="cv-meta">· Tham gia đã xác nhận <?= $escapeCv($activity['date']); ?></span></li><?php endforeach; ?>
            </ul></section>
            <?php endif; ?>
            <footer class="cv-footer">Dữ liệu ghi nhận lúc <?= $escapeCv($cv['generated_at']); ?> (Việt Nam).<?php if ($cv['omitted']): ?> Bản chọn lọc; hồ sơ trên TalentHub còn <?= $escapeCv($cv['omitted']); ?> mục khác.<?php endif; ?></footer>
        </div>
    </main>
    <script src="../../assets/js/learner-passport-cv.js"></script>
</body>
</html>
