<?php
/**
 * School Dashboard - Recent Activity Component
 */

$recentActivities = [
    [
        'text' => 'Nguyễn Văn Minh đạt giải Nhất cuộc thi Toán cấp Quận',
        'time' => '2 giờ trước'
    ],
    [
        'text' => 'Lớp 12A hoàn thành 100% hồ sơ năng lực',
        'time' => '4 giờ trước'
    ],
    [
        'text' => 'Câu lạc bộ Âm nhạc khai giảng khóa mới với 45 thành viên',
        'time' => '1 ngày trước'
    ],
    [
        'text' => '25 học sinh đăng ký tham gia sân chơi lập trình tháng 9',
        'time' => '1 ngày trước'
    ],
    [
        'text' => 'Trường được vinh danh "Top 10 trường có hoạt động năng khiếu xuất sắc"',
        'time' => '2 ngày trước'
    ]
];
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Hoạt động gần đây</h3>
            <p class="school-section-box__subtitle">Cập nhật mới nhất từ trường</p>
        </div>
    </div>
    <div class="school-activity-timeline">
        <?php foreach ($recentActivities as $activity): ?>
            <div class="school-activity-item">
                <span class="school-activity-item__indicator"></span>
                <span class="school-activity-item__text"><?= htmlspecialchars($activity['text']); ?></span>
                <span class="school-activity-item__time"><?= htmlspecialchars($activity['time']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
