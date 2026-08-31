<?php
/**
 * School Dashboard - Class List Component
 */

$classRows = isset($classes) && is_array($classes) ? array_values($classes) : [];
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Danh sách lớp</h3>
            <p class="school-section-box__subtitle"><?= count($classRows); ?> lớp trong trường</p>
        </div>
        <a href="../classes.php" class="school-section-box__link">Quản lý lớp</a>
    </div>
    <table class="school-class-table">
        <thead>
            <tr>
                <th>Lớp</th>
                <th>Khối</th>
                <th>Sĩ số</th>
                <th>GV Chủ nhiệm</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($classRows as $class): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($class['name']); ?></strong></td>
                    <td><?= htmlspecialchars($class['grade']); ?></td>
                    <td><?= htmlspecialchars($class['students']); ?> HS</td>
                    <td><?= htmlspecialchars($class['homeroom']); ?></td>
                    <td>
                        <span class="school-class-badge school-class-badge--<?= $class['status']; ?>">
                            <?= htmlspecialchars((string) ($class['statusText'] ?? $class['status_text'] ?? '')); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
