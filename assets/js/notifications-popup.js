/**
 * Shared notification popup for Teacher / School / Enterprise portals.
 * Works with header markup:
 *   <button data-notif-trigger data-portal="..." data-endpoint="..." data-detail-url="...">
 *   <dialog class="notif-popup" data-notif-dialog> ... <div data-notif-list> ... <button data-notif-close> ... <button data-notif-mark-all>
 * Badge unread count remains owned by portal-notifications.js (portal-notifications-boot JSON).
 */
(function () {
    'use strict';

    function appUrl(path) {
        if (typeof path !== 'string' || path === '') return '';
        // Absolute URL -> keep as-is.
        if (/^https?:\/\//i.test(path)) return path;
        var pathname = (window.location && window.location.pathname) || '';
        var appIndex = pathname.indexOf('/app/');
        var prefix = appIndex >= 0 ? pathname.slice(0, appIndex) : '';
        if (prefix && (path === prefix || path.indexOf(prefix + '/') === 0)) return path;
        return prefix + '/' + path.replace(/^\/+/, '');
    }

    function readBootCsrf() {
        var ids = ['portal-notifications-boot', 'learner-session-boot'];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (!el) continue;
            try {
                var parsed = JSON.parse(el.textContent || '{}');
                if (parsed && parsed.csrfToken) return String(parsed.csrfToken);
            } catch (_) { /* thử boot tiếp theo */ }
        }
        return '';
    }

    function refreshBadges() {
        // Portal (teacher/school/enterprise) manager.
        if (window.portalNotificationManager && typeof window.portalNotificationManager.refreshBadge === 'function') {
            try { window.portalNotificationManager.refreshBadge(); } catch (_) {}
        }
        // Learner manager (tự poll 30s, gọi sớm cho badge cập nhật ngay).
        if (window.learnerNotificationManager && typeof window.learnerNotificationManager.updateUnreadCount === 'function') {
            try { window.learnerNotificationManager.updateUnreadCount(); } catch (_) {}
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatTime(value) {
        if (!value) return '';
        var raw = String(value);
        var iso = raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T') + 'Z';
        var date = new Date(iso);
        if (isNaN(date.getTime())) return raw;
        return date.toLocaleString('vi-VN', {
            timeZone: 'Asia/Ho_Chi_Minh',
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function isSafeDeepLink(value, portal) {
        if (typeof value !== 'string' || value === '' || ['teacher', 'school', 'enterprise', 'learner'].indexOf(portal) < 0) {
            return false;
        }
        try {
            var parsed = new URL(value, window.location.origin);
            var expectedPrefix = appUrl('/app/' + portal + '/');
            return parsed.origin === window.location.origin
                && parsed.pathname.indexOf(expectedPrefix) === 0
                && !parsed.username
                && !parsed.password;
        } catch (_) {
            return false;
        }
    }

    function initForTrigger(trigger) {
        var header = trigger.closest('header');
        var dialog = header ? header.querySelector('[data-notif-dialog]') : document.querySelector('[data-notif-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') return;
        var list = dialog.querySelector('[data-notif-list]');
        var closeBtn = dialog.querySelector('[data-notif-close]');
        var markAllBtn = dialog.querySelector('[data-notif-mark-all]');
        var endpoint = trigger.getAttribute('data-endpoint') || '';
        var portal = trigger.getAttribute('data-portal') || '';
        var csrf = readBootCsrf();
        var loaded = false;

        function markOneRead(n, done) {
            if (!endpoint || !n || !n.id) { done(); return; }
            fetch(appUrl(endpoint), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({ action: 'mark-read', notificationId: n.id })
            }).catch(function () {}).then(function () { done(); });
        }

        function renderItems(items) {
            if (!list) return;
            list.innerHTML = '';
            if (!items.length) {
                var empty = document.createElement('p');
                empty.className = 'notif-popup__empty';
                empty.textContent = 'Chưa có thông báo.';
                list.appendChild(empty);
                return;
            }
            items.slice(0, 8).forEach(function (n) {
                var unread = !n.isRead && !n.readAt;
                var linkable = isSafeDeepLink(n.deepLink, portal);
                var item = document.createElement(linkable ? 'a' : 'div');
                item.className = 'notif-popup__item' + (unread ? ' is-unread' : '') + (linkable ? ' is-link' : '');
                if (linkable) {
                    item.href = appUrl(n.deepLink);
                    // Bấm vào thông báo → sang trang chi tiết (deepLink); nếu chưa đọc
                    // thì đánh dấu đã đọc trước rồi mới chuyển trang (lỗi cũng vẫn đi).
                    item.addEventListener('click', function (e) {
                        if (!unread) return;
                        e.preventDefault();
                        var href = item.href;
                        markOneRead(n, function () { window.location.href = href; });
                    });
                }
                item.innerHTML =
                    '<p class="notif-popup__item-title">' + escapeHtml(n.title || 'Thông báo') + '</p>' +
                    '<p class="notif-popup__item-message">' + escapeHtml(n.message || '') + '</p>' +
                    '<time class="notif-popup__item-time">' + escapeHtml(formatTime(n.createdAt)) + '</time>';
                list.appendChild(item);
            });
        }

        function load() {
            if (!list || !endpoint) return;
            list.innerHTML = '<p class="notif-popup__empty">Đang tải thông báo...</p>';
            fetch(appUrl(endpoint) + '?filter=all&limit=8&offset=0', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            })
                .then(function (res) { return res.json().catch(function () { return {}; }); })
                .then(function (payload) {
                    var data = (payload && payload.data) || {};
                    // Portal: data.items | Learner: data.notifications.
                    var items = Array.isArray(data.items) ? data.items
                        : (Array.isArray(data.notifications) ? data.notifications : []);
                    renderItems(items);
                    loaded = true;
                })
                .catch(function () {
                    list.innerHTML = '<p class="notif-popup__empty">Không thể tải thông báo.</p>';
                });
        }

        trigger.addEventListener('click', function () {
            if (!dialog.open) {
                try { dialog.showModal(); } catch (_) { dialog.setAttribute('open', ''); }
            }
            if (!loaded) load();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (dialog.open) dialog.close();
                else dialog.removeAttribute('open');
            });
        }
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) {
                if (dialog.open) dialog.close();
                else dialog.removeAttribute('open');
            }
        });

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function () {
                if (!endpoint) return;
                markAllBtn.disabled = true;
                fetch(appUrl(endpoint), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({ action: 'mark-all-read' })
                })
                    .then(function () { return load(); })
                    .catch(function () {})
                    .then(function () {
                        markAllBtn.disabled = false;
                        loaded = false;
                        // Cho cả portal manager lẫn learner manager refresh badge.
                        refreshBadges();
                    });
            });
        }
    }

    function init() {
        document.querySelectorAll('[data-notif-trigger]').forEach(initForTrigger);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
