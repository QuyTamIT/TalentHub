<?php
/**
 * School Dashboard - Class List Component
 */

$classes = [
    [
        'name' => '10A',
        'grade' => 'Khối 10',
        'students' => 42,
        'homeroom' => 'Nguyễn Thị Mai',
        'status' => 'success',
        'status_text' => 'Hoạt động tốt'
    ],
    [
        'name' => '10B',
        'grade' => 'Khối 10',
        'students' => 40,
        'homeroom' => 'Trần Văn Hùng',
        'status' => 'success',
        'status_text' => 'Hoạt động tốt'
    ],
    [
        'name' => '11A',
        'grade' => 'Khối 11',
        'students' => 38,
        'homeroom' => 'Lê Thị Hương',
        'status' => 'warning',
        'status_text' => 'Cần cải thiện'
    ],
    [
        'name' => '12A',
        'grade' => 'Khối 12',
        'students' => 45,
        'homeroom' => 'Phạm Văn Đức',
        'status' => 'success',
        'status_text' => 'Xuất sắc'
    ]
];
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Danh sách lớp</h3>
            <p class="school-section-box__subtitle">12 lớp trong trường</p>
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
            <?php foreach ($classes as $class): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($class['name']); ?></strong></td>
                    <td><?= htmlspecialchars($class['grade']); ?></td>
                    <td><?= htmlspecialchars($class['students']); ?> HS</td>
                    <td><?= htmlspecialchars($class['homeroom']); ?></td>
                    <td>
                        <span class="school-class-badge school-class-badge--<?= $class['status']; ?>">
                            <?= htmlspecialchars($class['status_text']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
