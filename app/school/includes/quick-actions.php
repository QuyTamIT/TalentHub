<?php
/**
 * School Dashboard - Quick Actions Component
 */

$quickActions = [
    [
        'title' => 'Thêm hoạt động mới',
        'subtitle' => 'Tạo sự kiện hoặc cuộc thi',
        'icon' => 'plus',
        'type' => 'primary'
    ],
    [
        'title' => 'Duyệt hồ sơ chờ xác nhận',
        'subtitle' => '5 hồ sơ đang chờ',
        'icon' => 'clock',
        'type' => 'warning',
        'count' => 5
    ],
    [
        'title' => 'Xuất báo cáo tháng',
        'subtitle' => 'Tổng hợp hoạt động tháng 8',
        'icon' => 'download',
        'type' => 'default'
    ],
    [
        'title' => 'Gửi thông báo',
        'subtitle' => 'Thông báo đến phụ huynh',
        'icon' => 'send',
        'type' => 'default'
    ]
];
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Hành động nhanh</h3>
        </div>
    </div>
    <div class="school-actions-list">
        <?php foreach ($quickActions as $action): ?>
            <div class="school-action-row <?= $action['type'] === 'warning' ? 'school-action-row--warning' : ''; ?>">
                <div class="school-action-row__icon">
                    <?php if ($action['icon'] === 'plus'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                    <?php elseif ($action['icon'] === 'clock'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    <?php elseif ($action['icon'] === 'download'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="school-action-row__body">
                    <div class="school-action-row__title"><?= htmlspecialchars($action['title']); ?></div>
                    <div class="school-action-row__subtitle"><?= htmlspecialchars($action['subtitle']); ?></div>
                </div>
                <div class="school-action-row__btn">
                    <button class="btn btn-sm <?= $action['type'] === 'primary' ? 'btn-primary' : ($action['type'] === 'warning' ? 'btn-warning' : 'btn-outline'); ?>">
                        <?= $action['type'] === 'primary' ? 'Tạo mới' : 'Thực hiện'; ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
