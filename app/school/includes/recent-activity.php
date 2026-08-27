<?php
/**
 * School Dashboard - Recent Activity Component
 */

$recentActivities = [
    [
        'text' => 'Trần Minh Đức đạt giải Nhất cuộc thi AI Hackathon 2026',
        'time' => '2 giờ trước'
    ],
    [
        'text' => 'Lớp BTEC-AI-2026A hoàn thành 100% hồ sơ năng lực',
        'time' => '4 giờ trước'
    ],
    [
        'text' => 'CLB Khởi nghiệp & AI khai giảng khóa mới với 45 thành viên',
        'time' => '1 ngày trước'
    ],
    [
        'text' => '25 sinh viên đăng ký tham gia phỏng vấn tuyển dụng thực tập',
        'time' => '1 ngày trước'
    ],
    [
        'text' => 'Nhà trường ký kết hợp tác hướng nghiệp cùng Doanh nghiệp đối tác',
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
