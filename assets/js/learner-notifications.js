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
        '/app/learner/activity-history.php',
        '/app/learner/talent-passport.php',
        '/app/teacher/projects/index.php',
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

    function preferenceLabel(type) {
        const labels = {
            activity_registration_created: 'Đăng ký hoạt động',
            activity_registration_cancelled: 'Hủy đăng ký hoạt động',
            activity_registration_promoted: 'Được chuyển khỏi danh sách chờ',
            activity_registration_approved: 'Đăng ký được phê duyệt',
            activity_registration_rejected: 'Đăng ký bị từ chối',
            activity_checkin_committed: 'Check-in và giờ trải nghiệm',
            activity_attendance_no_show: 'Thông báo hoạt động không tham gia',
            assessment_submitted: 'Nộp bài đánh giá năng lực',
            internship_application_submitted: 'Nộp hồ sơ thực tập',
            internship_application_withdrawn: 'Rút hồ sơ thực tập',
            internship_application_status_changed: 'Trạng thái hồ sơ thực tập',
            badge_awarded: 'Huy hiệu được trao',
        };
        return labels[type] || type;
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

    function createSvgIcon(type, width = 15, height = 15, strokeWidth = '2.5') {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', String(width));
        svg.setAttribute('height', String(height));
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', String(strokeWidth));
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');

        if (type === 'check') {
            const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
            polyline.setAttribute('points', '20 6 9 17 4 12');
            svg.appendChild(polyline);
        } else if (type === 'cross') {
            const line1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line1.setAttribute('x1', '18');
            line1.setAttribute('y1', '6');
            line1.setAttribute('x2', '6');
            line1.setAttribute('y2', '18');
            const line2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line2.setAttribute('x1', '6');
            line2.setAttribute('y1', '6');
            line2.setAttribute('x2', '18');
            line2.setAttribute('y2', '18');
            svg.appendChild(line1);
            svg.appendChild(line2);
        }
        return svg;
    }

    function el(tag, attrs = {}, children = []) {
        const element = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'className') {
                element.className = value;
            } else if (key === 'textContent') {
                element.textContent = value;
            } else if (key === 'style' && typeof value === 'string') {
                element.style.cssText = value;
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

    function createNotificationClient() {
        if (!globalThis.TalentHubLearnerApi) return null;
        const csrfToken = getBootContext().csrfToken || '';
        return globalThis.TalentHubLearnerApi.createLearnerApiClient({
            baseUrl: '/app/learner/api/v1',
            csrfToken,
        });
    }

    async function apiRequest(endpoint, method = 'GET', data = null, client = null, requestOptions = {}) {
        const prefix = '/app/learner/api/v1';
        if (typeof endpoint !== 'string' || !endpoint.startsWith(`${prefix}/`)) {
            throw new Error('INVALID_NOTIFICATION_ENDPOINT');
        }
        const api = client || createNotificationClient();
        if (!api) throw new Error('NOTIFICATION_API_UNAVAILABLE');
        const path = endpoint.slice(prefix.length);
        const responseData = method === 'GET'
            ? await api.get(path, requestOptions)
            : await api.send(method, path, data, requestOptions);
        return { data: responseData };
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
        let toast = document.getElementById('learner-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'learner-toast';
            toast.className = 'learner-toast';
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#0F172A;color:#FFFFFF;padding:12px 20px;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.25);z-index:9999;font-weight:600;font-size:0.9rem;display:flex;align-items:center;gap:8px;';
            const msgSpan = document.createElement('span');
            msgSpan.className = 'learner-toast__message';
            toast.appendChild(msgSpan);
            document.body.appendChild(toast);
        }
        const target = toast.querySelector('.learner-toast__message') || toast;
        target.textContent = message;
        toast.classList.add('is-visible');
        toast.style.display = 'flex';
        setTimeout(() => {
            toast.classList.remove('is-visible');
            toast.style.display = 'none';
        }, 3500);
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
            this.listController = null;
            this.listRequestSequence = 0;
            this.init();
        }

        init() {
            if (getBootContext().onboardingRestricted === true) return;
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
            this.listController?.abort();
            const controller = new AbortController();
            this.listController = controller;
            const sequence = ++this.listRequestSequence;
            const requestedOffset = append ? this.offset : 0;
            const requestedFilter = this.filter;
            if (!append) {
                this.list.replaceChildren(el('div', {
                    className: 'learner-notification-loading',
                    textContent: 'Đang tải thông báo...',
                }));
            }
            if (this.loadMoreButton) this.loadMoreButton.disabled = true;

            try {
                const response = await apiRequest(
                    buildNotificationQuery(requestedFilter, this.limit, requestedOffset),
                    'GET',
                    null,
                    null,
                    { signal: controller.signal },
                );
                if (sequence !== this.listRequestSequence) return;
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
                if (error?.code === 'REQUEST_ABORTED' || sequence !== this.listRequestSequence) return;
                if (!append) this.renderListError();
                else showToast('Không thể tải thêm thông báo.');
                this.renderLoadMore();
            } finally {
                if (this.listController === controller) this.listController = null;
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

        getAvatarInfo(notification) {
            let name = '';
            if (notification.invitation && notification.invitation.enterpriseName) {
                name = notification.invitation.enterpriseName;
            } else if (notification.title && /từ\s+/i.test(notification.title)) {
                name = notification.title.split(/từ\s+/i)[1] || '';
            } else if (notification.title) {
                name = notification.title;
            }

            let cleanName = name
                .replace(/^(Công ty TNHH Phần mềm|Công ty TNHH|Công ty Cổ phần|Công ty CP|Công ty|Tập đoàn|Trường Đại học|Trường Cao đẳng|Trường|Học viện)\s+/i, '')
                .trim();

            if (!cleanName) cleanName = name.trim() || 'TalentHub';

            const words = cleanName.split(/\s+/).filter(Boolean);
            let initials = 'TH';
            if (words.length >= 2) {
                initials = (words[0][0] + words[1][0]).toUpperCase();
            } else if (words.length === 1) {
                initials = words[0].slice(0, 2).toUpperCase();
            }

            const palettes = [
                { bg: '#ecfdf5', text: '#047857', border: '#a7f3d0' }, // Green (Enterprise/Success)
                { bg: '#e0e7ff', text: '#4338ca', border: '#c7d2fe' }, // Indigo (Education)
                { bg: '#eff6ff', text: '#1d4ed8', border: '#bfdbfe' }, // Blue
                { bg: '#fef3c7', text: '#b45309', border: '#fde68a' }, // Amber
                { bg: '#faf5ff', text: '#7e22ce', border: '#e9d5ff' }, // Purple
                { bg: '#fff1f2', text: '#be123c', border: '#fecdd3' }, // Rose
            ];

            let hash = 0;
            for (let i = 0; i < cleanName.length; i++) {
                hash = cleanName.charCodeAt(i) + ((hash << 5) - hash);
            }
            const palette = palettes[Math.abs(hash) % palettes.length];

            return { initials, palette, name: cleanName };
        }

        notificationCard(notification) {
            const unread = !notification.readAt;
            const card = el('article', {
                className: `learner-notification-card${unread ? ' is-unread' : ''}`,
                'data-notification-id': notification.id,
            });

            const avatarInfo = this.getAvatarInfo(notification);
            card.appendChild(el('div', {
                className: 'learner-notification-card__icon',
                style: `width: 44px; height: 44px; min-width: 44px; border-radius: 50%; background: ${avatarInfo.palette.bg} !important; color: ${avatarInfo.palette.text} !important; border: 1.5px solid ${avatarInfo.palette.border}; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 700 !important; font-size: 14px !important; letter-spacing: 0.5px; text-transform: uppercase; font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif; box-shadow: 0 1px 2px rgba(0,0,0,0.05);`,
                textContent: avatarInfo.initials,
                title: avatarInfo.name,
                'aria-label': avatarInfo.name,
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

            // Check if notification is an internship invitation
            const isInvite = notification.invitation || 
                (notification.title && notification.title.toLowerCase().includes('lời mời thực tập')) ||
                (notification.message && notification.message.toLowerCase().includes('lời mời bạn tham gia thực tập')) ||
                (notification.notificationType === 'internship_invitation');

            if (isInvite) {
                const inviteStatus = notification.invitation?.status || 'invited';
                const entName = notification.invitation?.enterpriseName || 'FPT Software';

                const actionBox = el('div', { 
                    className: 'learner-notification-invite-actions',
                    style: 'margin-top: 14px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;'
                });

                if (inviteStatus === 'accepted') {
                    actionBox.appendChild(el('span', {
                        className: 'badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill fw-bold learner-invite-badge-accepted',
                        style: 'background: #ecfdf5 !important; color: #047857 !important; border: 1.5px solid #10b981 !important; font-weight: 700 !important; font-size: 13.5px !important; padding: 7px 16px !important; border-radius: 999px !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; width: auto !important; height: auto !important; white-space: nowrap !important;',
                    }, [
                        createSvgIcon('check', 15, 15, '3'),
                        el('span', { textContent: 'Đã tiếp nhận thực tập' }),
                    ]));
                } else if (inviteStatus === 'declined' || inviteStatus === 'rejected') {
                    actionBox.appendChild(el('span', {
                        className: 'badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill fw-bold learner-invite-badge-declined',
                        style: 'background: #fef2f2 !important; color: #b91c1c !important; border: 1.5px solid #f87171 !important; font-weight: 700 !important; font-size: 13.5px !important; padding: 7px 16px !important; border-radius: 999px !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; width: auto !important; height: auto !important; white-space: nowrap !important;',
                    }, [
                        createSvgIcon('cross', 15, 15, '3'),
                        el('span', { textContent: 'Đã từ chối' }),
                    ]));
                } else {
                    // Two buttons: [Chấp nhận lời mời] & [Từ chối] (clean icon + text, locked with data-id)
                    const acceptBtn = el('button', {
                        type: 'button',
                        className: 'learner-invite-btn-accept',
                        'data-id': notification.id,
                        'data-notification-id': notification.id,
                        style: 'display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; background: #10b981 !important; color: #ffffff !important; font-weight: 700 !important; font-size: 14px !important; line-height: 1.4 !important; padding: 9px 18px !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; text-decoration: none !important; white-space: nowrap !important; width: auto !important; height: auto !important; min-width: fit-content !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;',
                        onClick: (e) => {
                            e.stopPropagation();
                            const notifId = acceptBtn.getAttribute('data-id') || notification.id;
                            if (typeof window.handleAcceptInvitation === 'function') {
                                window.handleAcceptInvitation(notifId, entName, actionBox);
                            } else {
                                this.respondInvitation(notifId, 'accept', actionBox, entName);
                            }
                        }
                    }, [
                        createSvgIcon('check', 15, 15, '2.5'),
                        el('span', { textContent: 'Chấp nhận lời mời' }),
                    ]);

                    const declineBtn = el('button', {
                        type: 'button',
                        className: 'learner-invite-btn-decline',
                        'data-id': notification.id,
                        'data-notification-id': notification.id,
                        style: 'display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; background: #ffffff !important; color: #4b5563 !important; font-weight: 600 !important; font-size: 14px !important; line-height: 1.4 !important; padding: 8px 16px !important; border-radius: 8px !important; border: 1.5px solid #d1d5db !important; cursor: pointer !important; text-decoration: none !important; white-space: nowrap !important; width: auto !important; height: auto !important; min-width: fit-content !important;',
                        onClick: (e) => {
                            e.stopPropagation();
                            const notifId = declineBtn.getAttribute('data-id') || notification.id;
                            if (typeof window.handleDeclineInvitation === 'function') {
                                window.handleDeclineInvitation(notifId, entName, actionBox);
                            } else {
                                this.respondInvitation(notifId, 'decline', actionBox, entName);
                            }
                        }
                    }, [
                        createSvgIcon('cross', 15, 15, '2.5'),
                        el('span', { textContent: 'Từ chối' }),
                    ]);

                    actionBox.appendChild(acceptBtn);
                    actionBox.appendChild(declineBtn);
                }

                body.appendChild(actionBox);
            }

            if (unread) {
                card.appendChild(el('div', { className: 'learner-notification-card__actions' }, [
                    el('button', {
                        type: 'button',
                        className: 'learner-btn learner-btn--ghost learner-btn--sm',
                        'data-id': notification.id,
                        textContent: 'Đánh dấu đã đọc',
                        onClick: () => this.markAsRead(notification.id),
                    }),
                ]));
            }
            return card;
        }

        async respondInvitation(notificationId, decision, actionBox, entName) {
            actionBox.replaceChildren(el('span', {
                style: 'font-size: 0.85rem; color: #64748B; font-style: italic;',
                textContent: 'Đang xử lý phản hồi...',
            }));

            try {
                const response = await apiRequest(ENDPOINT, 'PATCH', {
                    action: 'respond-invitation',
                    notificationId,
                    notification_id: notificationId,
                    decision,
                });

                const data = response.data || {};
                const newStatus = data.status || (decision === 'accept' ? 'accepted' : 'declined');
                actionBox.replaceChildren();

                if (newStatus === 'accepted') {
                    actionBox.appendChild(el('span', {
                        className: 'badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill fw-bold learner-invite-badge-accepted',
                        style: 'background: #ecfdf5 !important; color: #047857 !important; border: 1.5px solid #10b981 !important; font-weight: 700 !important; font-size: 13.5px !important; padding: 7px 16px !important; border-radius: 999px !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; width: auto !important; height: auto !important; white-space: nowrap !important;',
                    }, [
                        createSvgIcon('check', 15, 15, '3'),
                        el('span', { textContent: 'Đã tiếp nhận thực tập' }),
                    ]));
                    showToast(data.message || `Bạn đã chấp nhận lời mời thực tập từ ${entName}!`);
                } else {
                    actionBox.appendChild(el('span', {
                        className: 'badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill fw-bold learner-invite-badge-declined',
                        style: 'background: #fef2f2 !important; color: #b91c1c !important; border: 1.5px solid #f87171 !important; font-weight: 700 !important; font-size: 13.5px !important; padding: 7px 16px !important; border-radius: 999px !important; display: inline-flex !important; align-items: center !important; gap: 6px !important; width: auto !important; height: auto !important; white-space: nowrap !important;',
                    }, [
                        createSvgIcon('cross', 15, 15, '3'),
                        el('span', { textContent: 'Đã từ chối' }),
                    ]));
                    showToast(data.message || 'Bạn đã từ chối lời mời thực tập.');
                }

                // Mark card unread style removed for ONLY this specific card
                const card = document.querySelector(`[data-notification-id="${notificationId}"]`) || document.querySelector(`[data-id="${notificationId}"]`);
                if (card) {
                    card.classList.remove('is-unread');
                    const markBtn = card.querySelector('.learner-notification-card__actions');
                    if (markBtn) markBtn.remove();
                }

                this.updateUnreadCount();
            } catch (err) {
                console.error(err);
                showToast('Không thể gửi phản hồi lời mời lúc này.');
                this.loadNotifications(false);
            }
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
            return preferenceLabel(type);
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
            createNotificationClient,
            apiRequest,
            el,
            formatTime,
            preferenceLabel,
            LearnerNotificationManager,
        };
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            window.learnerNotificationManager = new LearnerNotificationManager();
        });
    }
})();
