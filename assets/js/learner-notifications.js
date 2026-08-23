/**
 * TalentHub learner notifications: database-backed inbox, unread badge and preferences.
 */
(function () {
    'use strict';

    const ENDPOINT = '/app/learner/api/v1/notifications.php';
    const ALLOWED_DEEP_LINKS = [
        '/app/learner/my-activities.php',
        '/app/learner/checkin.php',
        '/app/learner/assessment-result.php',
        '/app/learner/ecosystem.php',
        '/app/learner/badges.php',
    ];

    function isSafeDeepLink(url) {
        return typeof url === 'string' && ALLOWED_DEEP_LINKS.includes(url);
    }

    function normalizePreferences(preferences) {
        if (!preferences || typeof preferences !== 'object' || Array.isArray(preferences)) {
            return [];
        }
        return Object.entries(preferences).map(([notificationType, value]) => ({
            notificationType,
            inAppEnabled: value && value.inAppEnabled === true,
            emailEnabled: value && value.emailEnabled === true,
            updatedAt: value && typeof value.updatedAt === 'string' ? value.updatedAt : null,
        }));
    }

    function buildNotificationQuery(filter = 'all', limit = 25, offset = 0) {
        const safeFilter = filter === 'unread' ? 'unread' : 'all';
        return `${ENDPOINT}?filter=${safeFilter}&limit=${limit}&offset=${offset}`;
    }

    function getBootContext() {
        const bootEl = document.getElementById('learner-notifications-boot')
            || document.getElementById('learner-session-boot');
        if (!bootEl) return { csrfToken: '' };
        try {
            return JSON.parse(bootEl.textContent || '{}');
        } catch (error) {
            return { csrfToken: '' };
        }
    }

    function el(tag, attrs = {}, children = []) {
        const element = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'className') {
                element.className = value;
            } else if (key === 'textContent') {
                element.textContent = value;
            } else if (key.startsWith('on') && typeof value === 'function') {
                element.addEventListener(key.slice(2).toLowerCase(), value);
            } else if (value !== null && value !== undefined) {
                element.setAttribute(key, value);
            }
        }
        for (const child of children) {
            if (typeof child === 'string') {
                element.appendChild(document.createTextNode(child));
            } else if (child instanceof Node) {
                element.appendChild(child);
            }
        }
        return element;
    }

    async function apiRequest(endpoint, method = 'GET', data = null) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const csrfToken = getBootContext().csrfToken || '';
        if (csrfToken !== '') headers['X-CSRF-Token'] = csrfToken;

        const options = { method, headers };
        if (data !== null && ['POST', 'PATCH', 'PUT'].includes(method)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        const response = await fetch(endpoint, options);
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error(`HTTP_${response.status}`);
        }
        if (!response.ok || !payload || payload.success === false || payload.error) {
            const code = payload && payload.error && payload.error.code
                ? payload.error.code
                : `HTTP_${response.status}`;
            throw new Error(code);
        }
        return payload;
    }

    function formatTime(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
        if (minutes < 1) return 'Vừa xong';
        if (minutes < 60) return `${minutes} phút trước`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} giờ trước`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days} ngày trước`;
        return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function showToast(message) {
        const toast = document.getElementById('learner-toast');
        if (!toast) return;
        const target = toast.querySelector('.learner-toast__message');
        if (target) target.textContent = message;
        toast.classList.add('is-visible');
        setTimeout(() => toast.classList.remove('is-visible'), 3000);
    }

    class LearnerNotificationManager {
        constructor() {
            this.filter = 'all';
            this.limit = 25;
            this.offset = 0;
            this.hasMore = false;
            this.notifications = [];
            this.unreadCount = 0;
            this.list = document.getElementById('learner-notification-list');
            this.loadMoreButton = document.getElementById('learner-notification-load-more');
            this.badge = document.getElementById('learner-unread-badge');
            this.preferenceModal = document.getElementById('learner-notification-prefs-modal');
            this.preferenceTrigger = document.getElementById('learner-open-prefs');
            this.lastFocusedElement = null;
            this.init();
        }

        init() {
            this.updateUnreadCount();
            window.setInterval(() => this.updateUnreadCount(), 30000);
            if (!this.list) return;

            document.querySelectorAll('[data-notification-filter]').forEach((button) => {
                button.addEventListener('click', () => this.changeFilter(button));
            });
            document.getElementById('learner-mark-all-read')
                ?.addEventListener('click', () => this.markAllAsRead());
            this.loadMoreButton?.addEventListener('click', () => this.loadNotifications(true));
            this.setupPreferenceModal();
            this.loadNotifications(false);
        }

        async updateUnreadCount() {
            try {
                const response = await apiRequest(buildNotificationQuery('unread', 1, 0));
                this.unreadCount = Number(response.data?.unreadCount || 0);
                this.renderBadge();
            } catch (error) {
                // The badge is non-critical; the Notification Center exposes retry for list errors.
            }
        }

        renderBadge() {
            if (!this.badge) return;
            if (this.unreadCount > 0) {
                this.badge.textContent = this.unreadCount > 99 ? '99+' : String(this.unreadCount);
                this.badge.style.display = 'inline-flex';
                this.badge.setAttribute('aria-hidden', 'false');
            } else {
                this.badge.textContent = '';
                this.badge.style.display = 'none';
                this.badge.setAttribute('aria-hidden', 'true');
            }
        }

        changeFilter(activeButton) {
            document.querySelectorAll('[data-notification-filter]').forEach((button) => {
                const active = button === activeButton;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            this.filter = activeButton.dataset.notificationFilter === 'unread' ? 'unread' : 'all';
            this.loadNotifications(false);
        }

        async loadNotifications(append) {
            if (!this.list) return;
            const requestedOffset = append ? this.offset : 0;
            if (!append) {
                this.list.replaceChildren(el('div', {
                    className: 'learner-notification-loading',
                    textContent: 'Đang tải thông báo...',
                }));
            }
            if (this.loadMoreButton) this.loadMoreButton.disabled = true;

            try {
                const response = await apiRequest(buildNotificationQuery(this.filter, this.limit, requestedOffset));
                const data = response.data || {};
                if (!Array.isArray(data.notifications) || !data.pagination) {
                    throw new Error('INVALID_RESPONSE');
                }
                this.notifications = append
                    ? this.notifications.concat(data.notifications)
                    : data.notifications;
                this.offset = requestedOffset + data.notifications.length;
                this.hasMore = data.pagination.hasMore === true;
                this.unreadCount = Number(data.unreadCount || 0);
                this.renderNotifications();
                this.renderBadge();
                this.renderLoadMore();
            } catch (error) {
                if (!append) this.renderListError();
                else showToast('Không thể tải thêm thông báo.');
                this.renderLoadMore();
            }
        }

        renderNotifications() {
            this.list.replaceChildren();
            if (this.notifications.length === 0) {
                this.list.appendChild(el('div', { className: 'learner-notification-empty' }, [
                    el('p', {
                        textContent: this.filter === 'unread'
                            ? 'Bạn không có thông báo chưa đọc nào.'
                            : 'Bạn chưa có thông báo nào.',
                    }),
                ]));
                return;
            }
            this.notifications.forEach((notification) => {
                this.list.appendChild(this.notificationCard(notification));
            });
        }

        notificationCard(notification) {
            const unread = !notification.readAt;
            const card = el('article', {
                className: `learner-notification-card${unread ? ' is-unread' : ''}`,
                'data-notification-id': notification.id,
            });
            card.appendChild(el('div', {
                className: 'learner-notification-card__icon',
                textContent: this.iconFor(notification.notificationType),
                'aria-hidden': 'true',
            }));

            const body = el('div', { className: 'learner-notification-card__body' });
            body.appendChild(el('h3', {
                className: 'learner-notification-card__title',
                textContent: String(notification.title || ''),
            }));
            body.appendChild(el('p', {
                className: 'learner-notification-card__message',
                textContent: String(notification.message || ''),
            }));
            const meta = el('div', { className: 'learner-notification-card__meta' }, [
                el('span', { textContent: formatTime(notification.createdAt) }),
            ]);
            if (isSafeDeepLink(notification.deepLink)) {
                const link = el('a', {
                    className: 'learner-notification-card__link-hint',
                    href: notification.deepLink,
                    textContent: 'Xem chi tiết',
                });
                link.addEventListener('click', async (event) => {
                    if (!unread) return;
                    event.preventDefault();
                    const marked = await this.markAsRead(notification.id);
                    if (marked) window.location.assign(notification.deepLink);
                });
                meta.appendChild(link);
            }
            body.appendChild(meta);
            card.appendChild(body);

            if (unread) {
                card.appendChild(el('div', { className: 'learner-notification-card__actions' }, [
                    el('button', {
                        type: 'button',
                        className: 'learner-btn learner-btn--ghost learner-btn--sm',
                        textContent: 'Đánh dấu đã đọc',
                        onClick: () => this.markAsRead(notification.id),
                    }),
                ]));
            }
            return card;
        }

        iconFor(type) {
            if (String(type).startsWith('activity_')) return '📅';
            if (type === 'assessment_submitted') return '🎯';
            if (String(type).startsWith('internship_')) return '💼';
            return '🔔';
        }

        renderListError() {
            const retry = el('button', {
                type: 'button',
                className: 'learner-btn learner-btn--secondary learner-btn--sm',
                textContent: 'Thử lại',
                onClick: () => this.loadNotifications(false),
            });
            this.list.replaceChildren(el('div', { className: 'learner-notification-empty' }, [
                el('p', { textContent: 'Không thể tải danh sách thông báo.' }),
                retry,
            ]));
        }

        renderLoadMore() {
            if (!this.loadMoreButton) return;
            this.loadMoreButton.hidden = !this.hasMore;
            this.loadMoreButton.disabled = !this.hasMore;
        }

        async markAsRead(notificationId) {
            try {
                const response = await apiRequest(ENDPOINT, 'PATCH', {
                    action: 'mark-read',
                    notificationId,
                });
                const confirmed = response.data?.notification;
                if (!confirmed) throw new Error('INVALID_RESPONSE');
                const index = this.notifications.findIndex((item) => item.id === notificationId);
                if (index >= 0) this.notifications[index] = confirmed;
                if (this.filter === 'unread') {
                    this.notifications = this.notifications.filter((item) => item.id !== notificationId);
                }
                this.unreadCount = Number(response.data.unreadCount || 0);
                this.renderNotifications();
                this.renderBadge();
                return true;
            } catch (error) {
                showToast('Không thể đánh dấu đã đọc.');
                return false;
            }
        }

        async markAllAsRead() {
            try {
                const response = await apiRequest(ENDPOINT, 'PATCH', { action: 'mark-all-read' });
                this.unreadCount = Number(response.data?.unreadCount || 0);
                showToast('Đã đánh dấu tất cả là đã đọc.');
                await this.loadNotifications(false);
            } catch (error) {
                showToast('Không thể đánh dấu tất cả đã đọc.');
            }
        }

        setupPreferenceModal() {
            const closeButton = document.getElementById('learner-prefs-close');
            this.preferenceTrigger?.addEventListener('click', () => this.openPreferences());
            closeButton?.addEventListener('click', () => this.closePreferences());
            this.preferenceModal?.addEventListener('click', (event) => {
                if (event.target === this.preferenceModal) this.closePreferences();
            });
            this.preferenceModal?.addEventListener('keydown', (event) => this.handleModalKeydown(event));
        }

        openPreferences() {
            if (!this.preferenceModal) return;
            this.lastFocusedElement = document.activeElement;
            this.preferenceModal.classList.add('is-open');
            this.preferenceModal.setAttribute('aria-hidden', 'false');
            document.getElementById('learner-prefs-close')?.focus();
            this.loadPreferences();
        }

        closePreferences() {
            if (!this.preferenceModal) return;
            this.preferenceModal.classList.remove('is-open');
            this.preferenceModal.setAttribute('aria-hidden', 'true');
            if (this.lastFocusedElement && typeof this.lastFocusedElement.focus === 'function') {
                this.lastFocusedElement.focus();
            }
        }

        handleModalKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.closePreferences();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(this.preferenceModal.querySelectorAll('button, input, [href], [tabindex]:not([tabindex="-1"])'));
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        async loadPreferences() {
            const container = document.getElementById('learner-prefs-list');
            if (!container) return;
            container.replaceChildren(el('div', { textContent: 'Đang tải cài đặt...' }));
            try {
                const response = await apiRequest(buildNotificationQuery('all', 1, 0));
                const preferences = normalizePreferences(response.data?.preferences);
                if (preferences.length === 0) throw new Error('INVALID_RESPONSE');
                this.renderPreferences(preferences);
            } catch (error) {
                container.replaceChildren(el('div', { textContent: 'Không thể tải cài đặt thông báo.' }));
            }
        }

        renderPreferences(preferences) {
            const container = document.getElementById('learner-prefs-list');
            if (!container) return;
            container.replaceChildren();
            preferences.forEach((preference) => {
                const inApp = el('input', { type: 'checkbox', id: `pref-inapp-${preference.notificationType}` });
                const email = el('input', { type: 'checkbox', id: `pref-email-${preference.notificationType}` });
                inApp.checked = preference.inAppEnabled;
                email.checked = preference.emailEnabled;
                inApp.addEventListener('change', () => this.persistPreference(preference.notificationType, inApp, email, inApp));
                email.addEventListener('change', () => this.persistPreference(preference.notificationType, inApp, email, email));

                const row = el('div', { className: 'learner-pref-row' }, [
                    el('div', { className: 'learner-pref-info' }, [
                        el('strong', { textContent: this.preferenceLabel(preference.notificationType) }),
                    ]),
                    el('div', { className: 'learner-pref-toggles' }, [
                        el('label', { className: 'learner-pref-toggle', for: inApp.id }, [inApp, el('span', { textContent: 'Ứng dụng' })]),
                        el('label', { className: 'learner-pref-toggle', for: email.id }, [email, el('span', { textContent: 'Email' })]),
                    ]),
                ]);
                container.appendChild(row);
            });
        }

        preferenceLabel(type) {
            const labels = {
                activity_registration_created: 'Đăng ký hoạt động',
                activity_registration_cancelled: 'Hủy đăng ký hoạt động',
                activity_registration_promoted: 'Được chuyển khỏi danh sách chờ',
                activity_registration_approved: 'Đăng ký được phê duyệt',
                activity_registration_rejected: 'Đăng ký bị từ chối',
                activity_checkin_committed: 'Check-in và giờ trải nghiệm',
                assessment_submitted: 'Nộp bài đánh giá năng lực',
                internship_application_submitted: 'Nộp hồ sơ thực tập',
                internship_application_withdrawn: 'Rút hồ sơ thực tập',
                internship_application_status_changed: 'Trạng thái hồ sơ thực tập',
                badge_awarded: 'Huy hiệu được trao',
            };
            return labels[type] || type;
        }

        async persistPreference(notificationType, inApp, email, changedInput) {
            const previous = !changedInput.checked;
            inApp.disabled = true;
            email.disabled = true;
            try {
                const response = await apiRequest(ENDPOINT, 'PATCH', {
                    action: 'update-preference',
                    notificationType,
                    inAppEnabled: inApp.checked,
                    emailEnabled: email.checked,
                });
                const confirmed = response.data?.preference;
                if (!confirmed) throw new Error('INVALID_RESPONSE');
                inApp.checked = confirmed.inAppEnabled === true;
                email.checked = confirmed.emailEnabled === true;
                showToast('Đã lưu tùy chọn thông báo.');
            } catch (error) {
                changedInput.checked = previous;
                showToast('Không thể lưu tùy chọn thông báo.');
            } finally {
                inApp.disabled = false;
                email.disabled = false;
            }
        }
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            ALLOWED_DEEP_LINKS,
            isSafeDeepLink,
            normalizePreferences,
            buildNotificationQuery,
            apiRequest,
            el,
            formatTime,
            LearnerNotificationManager,
        };
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            window.learnerNotificationManager = new LearnerNotificationManager();
        });
    }
})();
