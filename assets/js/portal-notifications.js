/**
 * Database-backed notification inbox shared by Teacher, School and Enterprise.
 */
(function () {
    'use strict';

    const POLL_INTERVAL_MS = 30000;
    const DEFAULT_LIMIT = 25;

    function readBootContext() {
        const element = document.getElementById('portal-notifications-boot');
        if (!element) return null;
        try {
            const parsed = JSON.parse(element.textContent || '{}');
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (_) {
            return null;
        }
    }

    function appUrl(path) {
        if (typeof path !== 'string' || path === '') return '';
        const pathname = (window.location && window.location.pathname) || '';
        const appIndex = pathname.indexOf('/app/');
        const prefix = appIndex >= 0 ? pathname.slice(0, appIndex) : '';
        if (prefix && (path === prefix || path.startsWith(prefix + '/'))) return path;
        return prefix + '/' + path.replace(/^\/+/, '');
    }

    function normalizeCount(value) {
        const number = Number(value);
        return Number.isFinite(number) && number > 0 ? Math.floor(number) : 0;
    }

    function renderBadge(badge, count) {
        if (!badge) return;
        const normalized = normalizeCount(count);
        badge.textContent = normalized > 99 ? '99+' : (normalized ? String(normalized) : '');
        badge.style.display = normalized ? 'inline-flex' : 'none';
        badge.setAttribute('aria-hidden', normalized ? 'false' : 'true');

        const bell = badge.closest('a, button');
        if (bell) {
            bell.setAttribute('aria-label', normalized
                ? `Xem thông báo (${normalized} chưa đọc)`
                : 'Xem thông báo');
        }
    }

    function isSafeDeepLink(value, portal) {
        if (typeof value !== 'string' || value === '' || !['teacher', 'school', 'enterprise'].includes(portal)) {
            return false;
        }
        try {
            const parsed = new URL(value, window.location.origin);
            const expectedPrefix = appUrl(`/app/${portal}/`);
            return parsed.origin === window.location.origin
                && parsed.pathname.startsWith(expectedPrefix)
                && !parsed.username
                && !parsed.password;
        } catch (_) {
            return false;
        }
    }

    function formatTime(value) {
        if (!value) return '';
        const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z';
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return String(value);
        const minutes = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
        if (minutes < 1) return 'Vừa xong';
        if (minutes < 60) return `${minutes} phút trước`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} giờ trước`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days} ngày trước`;
        return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function iconFor(type) {
        const value = String(type || '');
        if (value.startsWith('activity_')) return 'calendar';
        if (value.startsWith('assessment_') || value.startsWith('teacher_assessment_')) return 'target';
        if (value.startsWith('internship_')) return 'briefcase';
        if (value.includes('badge') || value.includes('certificate')) return 'award';
        if (value.startsWith('project_')) return 'folder';
        return 'bell';
    }

    function svgIcon(name) {
        const paths = {
            calendar: '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 11h18"></path>',
            target: '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle><path d="M12 3v2M21 12h-2M12 21v-2M3 12h2"></path>',
            briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"></path>',
            award: '<circle cx="12" cy="8" r="5"></circle><path d="m8.5 12-1 9 4.5-3 4.5 3-1-9"></path>',
            folder: '<path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"></path>',
            bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
            check: '<path d="m5 12 4 4L19 6"></path>',
        };
        return `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.bell}</svg>`;
    }

    function showToast(message, error) {
        let toast = document.getElementById('portal-notification-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'portal-notification-toast';
            toast.className = 'portal-notification-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.toggle('is-error', error === true);
        toast.classList.add('is-visible');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('is-visible'), 3500);
    }

    class PortalNotificationManager {
        constructor(boot) {
            this.boot = boot;
            this.portal = String(boot.portal || '');
            this.endpoint = String(boot.endpoint || '');
            this.csrfToken = String(boot.csrfToken || '');
            this.badge = document.getElementById(String(boot.badgeId || ''));
            this.list = document.getElementById('portal-notification-list');
            this.loadMoreButton = document.getElementById('portal-notification-load-more');
            this.markAllButton = document.getElementById('portal-mark-all-read');
            this.status = document.getElementById('portal-notification-status');
            this.filter = 'all';
            this.offset = 0;
            this.limit = DEFAULT_LIMIT;
            this.hasMore = false;
            this.items = [];
            this.requestSequence = 0;
            this.controller = null;
        }

        init() {
            if (!this.endpoint) return;
            document.querySelectorAll('[data-portal-notification-filter]').forEach((button) => {
                button.addEventListener('click', () => this.changeFilter(button));
            });
            this.markAllButton?.addEventListener('click', () => this.markAllRead());
            this.loadMoreButton?.addEventListener('click', () => this.loadNotifications(true));

            this.refreshBadge();
            if (this.list) this.loadNotifications(false);
            window.setInterval(() => this.refreshBadge(), POLL_INTERVAL_MS);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.refreshBadge();
            });
        }

        async request(method, body, query, signal) {
            const endpoint = appUrl(this.endpoint) + (query ? `?${query}` : '');
            const options = {
                method,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal,
            };
            if (method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.headers['X-CSRF-Token'] = this.csrfToken;
                options.body = JSON.stringify(body || {});
            }
            const response = await fetch(endpoint, options);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(payload?.error?.message || 'Không thể xử lý yêu cầu thông báo.');
                error.status = response.status;
                throw error;
            }
            return payload.data || {};
        }

        async refreshBadge() {
            if (!this.badge) return;
            try {
                const data = await this.request('GET', null, 'filter=unread&limit=1&offset=0');
                renderBadge(this.badge, data.unreadCount);
            } catch (_) {
                // Badge refresh is non-critical; the inbox itself exposes retry.
            }
        }

        changeFilter(activeButton) {
            document.querySelectorAll('[data-portal-notification-filter]').forEach((button) => {
                const isActive = button === activeButton;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            this.filter = activeButton.dataset.portalNotificationFilter === 'unread' ? 'unread' : 'all';
            this.loadNotifications(false);
        }

        async loadNotifications(append) {
            if (!this.list) return;
            this.controller?.abort();
            this.controller = new AbortController();
            const sequence = ++this.requestSequence;
            const offset = append ? this.offset : 0;

            if (!append) {
                this.list.innerHTML = '<div class="portal-notification-state"><span class="portal-notification-spinner" aria-hidden="true"></span><p>Đang tải thông báo...</p></div>';
            }
            if (this.loadMoreButton) this.loadMoreButton.disabled = true;

            try {
                const query = new URLSearchParams({
                    filter: this.filter,
                    limit: String(this.limit),
                    offset: String(offset),
                }).toString();
                const data = await this.request('GET', null, query, this.controller.signal);
                if (sequence !== this.requestSequence) return;
                const received = Array.isArray(data.items) ? data.items : [];
                this.items = append ? this.items.concat(received) : received;
                this.offset = offset + received.length;
                this.hasMore = data.pagination?.hasMore === true;
                renderBadge(this.badge, data.unreadCount);
                this.list.setAttribute('aria-busy', 'false');
                this.renderList();
            } catch (error) {
                if (error.name === 'AbortError') return;
                this.renderError(error);
            } finally {
                if (this.loadMoreButton) this.loadMoreButton.disabled = false;
            }
        }

        renderList() {
            this.list.replaceChildren();
            if (this.items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'portal-notification-state portal-notification-state--empty';
                empty.innerHTML = `${svgIcon('bell')}<h2>${this.filter === 'unread' ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo'}</h2><p>${this.filter === 'unread' ? 'Bạn đã cập nhật tất cả thông báo.' : 'Các cập nhật mới sẽ xuất hiện tại đây.'}</p>`;
                this.list.appendChild(empty);
            } else {
                this.items.forEach((notification) => this.list.appendChild(this.createCard(notification)));
            }
            if (this.loadMoreButton) this.loadMoreButton.hidden = !this.hasMore;
            if (this.markAllButton) {
                this.markAllButton.disabled = !this.items.some((item) => !item.isRead && !item.readAt);
            }
            if (this.status) this.status.textContent = `${this.items.length} thông báo đang hiển thị`;
        }

        createCard(notification) {
            const unread = !notification.isRead && !notification.readAt;
            const article = document.createElement('article');
            article.className = `portal-notification-card${unread ? ' is-unread' : ''}`;
            article.dataset.notificationId = String(notification.id || '');

            const icon = document.createElement('span');
            icon.className = 'portal-notification-card__icon';
            icon.innerHTML = svgIcon(iconFor(notification.notificationType));

            const body = document.createElement('div');
            body.className = 'portal-notification-card__body';
            const title = document.createElement('h2');
            title.className = 'portal-notification-card__title';
            title.textContent = String(notification.title || 'Thông báo');
            const message = document.createElement('p');
            message.className = 'portal-notification-card__message';
            message.textContent = String(notification.message || '');
            const meta = document.createElement('div');
            meta.className = 'portal-notification-card__meta';
            const time = document.createElement('time');
            time.dateTime = String(notification.createdAt || '');
            time.textContent = formatTime(notification.createdAt);
            meta.appendChild(time);
            if (unread) {
                const label = document.createElement('span');
                label.className = 'portal-notification-card__unread-label';
                label.textContent = 'Chưa đọc';
                meta.appendChild(label);
            }
            body.append(title, message, meta);

            const actions = document.createElement('div');
            actions.className = 'portal-notification-card__actions';
            if (unread) {
                const mark = document.createElement('button');
                mark.type = 'button';
                mark.className = 'portal-notification-card__mark';
                mark.innerHTML = `${svgIcon('check')}<span>Đã đọc</span>`;
                mark.addEventListener('click', () => this.markRead(notification, mark));
                actions.appendChild(mark);
            }
            if (isSafeDeepLink(notification.deepLink, this.portal)) {
                const open = document.createElement('a');
                open.className = 'portal-notification-card__open';
                open.href = appUrl(notification.deepLink);
                open.textContent = 'Mở chi tiết';
                open.addEventListener('click', (event) => {
                    if (unread) {
                        event.preventDefault();
                        this.markRead(notification, open, open.href);
                    }
                });
                actions.appendChild(open);
            }

            article.append(icon, body, actions);
            return article;
        }

        async markRead(notification, control, navigateTo) {
            control.disabled = true;
            try {
                const data = await this.request('POST', {
                    action: 'mark-read',
                    notificationId: notification.id,
                });
                const confirmed = data.notification || notification;
                const index = this.items.findIndex((item) => item.id === notification.id);
                if (index >= 0) this.items[index] = Object.assign({}, this.items[index], confirmed, { isRead: true });
                if (this.filter === 'unread') {
                    this.items = this.items.filter((item) => item.id !== notification.id);
                }
                renderBadge(this.badge, data.unreadCount);
                this.renderList();
                if (navigateTo) window.location.assign(navigateTo);
            } catch (error) {
                control.disabled = false;
                showToast(error.message || 'Không thể đánh dấu đã đọc.', true);
            }
        }

        async markAllRead() {
            if (!this.markAllButton) return;
            this.markAllButton.disabled = true;
            try {
                const data = await this.request('POST', { action: 'mark-all-read' });
                renderBadge(this.badge, data.unreadCount);
                showToast('Đã đánh dấu tất cả thông báo là đã đọc.');
                await this.loadNotifications(false);
            } catch (error) {
                this.markAllButton.disabled = false;
                showToast(error.message || 'Không thể đánh dấu tất cả đã đọc.', true);
            }
        }

        renderError(error) {
            this.list.replaceChildren();
            this.list.setAttribute('aria-busy', 'false');
            const state = document.createElement('div');
            state.className = 'portal-notification-state portal-notification-state--error';
            const title = document.createElement('h2');
            title.textContent = error.status === 401 ? 'Phiên đăng nhập đã hết hạn' : 'Không thể tải thông báo';
            const message = document.createElement('p');
            message.textContent = error.message || 'Vui lòng thử lại sau.';
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'portal-notification-button portal-notification-button--secondary';
            retry.textContent = 'Thử lại';
            retry.addEventListener('click', () => this.loadNotifications(false));
            state.append(title, message, retry);
            this.list.appendChild(state);
            if (this.loadMoreButton) this.loadMoreButton.hidden = true;
        }
    }

    function init() {
        const boot = readBootContext();
        if (!boot) return;
        const manager = new PortalNotificationManager(boot);
        manager.init();
        window.portalNotificationManager = manager;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { appUrl, normalizeCount, renderBadge, isSafeDeepLink, formatTime, iconFor };
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();