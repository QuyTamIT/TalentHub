(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var triggers = document.querySelectorAll(
            '#ent-notif-trigger, #teacher-notif-trigger, #school-notif-trigger'
        );
        triggers.forEach(function (trigger) {
            var button = trigger.querySelector('button');
            if (!button || trigger.dataset.notificationsReady === 'true') return;
            trigger.dataset.notificationsReady = 'true';

            var panel = document.createElement('div');
            panel.className = 'portal-notifications-panel';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', 'Thông báo');
            panel.hidden = true;
            panel.innerHTML =
                '<div class="portal-notifications-panel__header"><strong>Thông báo</strong>' +
                '<button type="button" class="portal-notifications-panel__close" aria-label="Đóng thông báo">&times;</button></div>' +
                '<ul class="portal-notifications-panel__list">' +
                '<li><strong>Cập nhật hồ sơ</strong><span>Thông tin hồ sơ của bạn đã được đồng bộ.</span></li>' +
                '<li><strong>Hoạt động mới</strong><span>Có hoạt động mới phù hợp với tài khoản của bạn.</span></li>' +
                '<li><strong>Nhắc việc</strong><span>Hãy kiểm tra các mục đang chờ xử lý.</span></li>' +
                '</ul>';
            trigger.appendChild(panel);

            var close = panel.querySelector('.portal-notifications-panel__close');
            var toggle = function () {
                panel.hidden = !panel.hidden;
                button.setAttribute('aria-expanded', String(!panel.hidden));
            };
            button.setAttribute('aria-expanded', 'false');
            button.addEventListener('click', toggle);
            close.addEventListener('click', function () {
                panel.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                button.focus();
            });
            document.addEventListener('click', function (event) {
                if (!trigger.contains(event.target)) {
                    panel.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });
    });
})();
