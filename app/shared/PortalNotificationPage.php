<?php

declare(strict_types=1);

if (!function_exists('renderPortalNotificationCenter')) {
    /** Render the role-neutral notification inbox body. */
    function renderPortalNotificationCenter(string $description): void
    {
        ?>
        <section class="portal-notification-center" data-portal-notifications-page aria-labelledby="portal-notification-title">
            <div class="portal-notification-hero">
                <span class="portal-notification-hero__icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </span>
                <div class="portal-notification-hero__copy">
                    <span class="portal-notification-hero__eyebrow">Trung tâm cập nhật</span>
                    <h2 id="portal-notification-title">Thông báo</h2>
                    <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="portal-notification-toolbar">
                <div class="portal-notification-filters" aria-label="Lọc thông báo">
                    <button class="portal-notification-filter is-active" type="button" data-portal-notification-filter="all" aria-pressed="true">Tất cả</button>
                    <button class="portal-notification-filter" type="button" data-portal-notification-filter="unread" aria-pressed="false">Chưa đọc</button>
                </div>
                <div class="portal-notification-toolbar__actions">
                    <span class="portal-notification-status" id="portal-notification-status" aria-live="polite"></span>
                    <button class="portal-notification-button portal-notification-button--primary" id="portal-mark-all-read" type="button">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m5 12 4 4L19 6"></path>
                        </svg>
                        <span>Đánh dấu tất cả đã đọc</span>
                    </button>
                </div>
            </div>

            <div class="portal-notification-list" id="portal-notification-list" aria-live="polite" aria-busy="true">
                <div class="portal-notification-state">
                    <span class="portal-notification-spinner" aria-hidden="true"></span>
                    <p>Đang tải thông báo...</p>
                </div>
            </div>
            <div class="portal-notification-pagination">
                <button class="portal-notification-button portal-notification-button--secondary" id="portal-notification-load-more" type="button" hidden>Tải thêm thông báo</button>
            </div>
        </section>
        <?php
    }
}