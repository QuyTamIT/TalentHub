/**
 * TalentHub Learner - Applications Tracker Controller
 * Handles toggling, withdrawal confirmation, and UI updates for student's applications.
 */
(function initApplicationsTracker(global) {
    'use strict';

    function initTracker() {
        const tracker = document.querySelector('[data-applications-tracker]');
        if (!tracker) return;

        const toggleBtn = tracker.querySelector('[data-tracker-toggle]');
        const body = tracker.querySelector('[data-tracker-body]');
        const toggleLabel = tracker.querySelector('[data-toggle-label]');
        const toggleIcon = tracker.querySelector('[data-toggle-icon]');

        const urlParams = new URLSearchParams(window.location.search);
        const shouldAutoExpand = urlParams.get('view') === 'applications'
            || window.location.hash === '#my-applications';

        function setExpanded(expanded) {
            if (!body || !toggleBtn) return;
            body.hidden = !expanded;
            toggleBtn.setAttribute('aria-expanded', String(expanded));
            if (toggleLabel) {
                toggleLabel.textContent = expanded ? 'Thu gọn' : 'Xem danh sách đơn';
            }
            if (toggleIcon) {
                toggleIcon.style.transform = expanded ? 'rotate(180deg)' : 'none';
            }
        }

        if (shouldAutoExpand) {
            setExpanded(true);
        }

        toggleBtn?.addEventListener('click', () => {
            const isCurrentlyExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            setExpanded(!isCurrentlyExpanded);
        });

        tracker.addEventListener('click', async (event) => {
            const withdrawBtn = event.target.closest('[data-withdraw-btn]');
            if (!withdrawBtn || withdrawBtn.disabled) return;

            const appId = withdrawBtn.dataset.withdrawId;
            if (!appId) return;

            const confirmed = window.confirm('Bạn có chắc chắn muốn rút hồ sơ ứng tuyển này không? Hành động này không thể hoàn tác.');
            if (!confirmed) return;

            withdrawBtn.disabled = true;
            const originalText = withdrawBtn.textContent;
            withdrawBtn.textContent = 'Đang xử lý...';

            try {
                let response;
                if (global.LearnerApi && typeof global.LearnerApi.send === 'function') {
                    response = await global.LearnerApi.send('PATCH', '/applications.php', {
                        action: 'withdraw',
                        applicationId: appId,
                        reason: 'Học viên chủ động rút hồ sơ'
                    });
                } else {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || (window.LearnerApiContext?.csrfToken) || '';
                    const res = await fetch('api/v1/applications.php', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            action: 'withdraw',
                            applicationId: appId,
                            reason: 'Học viên chủ động rút hồ sơ'
                        })
                    });
                    response = await res.json();
                }

                const card = withdrawBtn.closest('[data-app-card]');
                if (card) {
                    const statusBadge = card.querySelector('[data-app-status-badge]');
                    if (statusBadge) {
                        statusBadge.className = 'learner-status-badge is-withdrawn';
                        statusBadge.textContent = 'Đã rút';
                    }
                    const timeline = card.querySelector('.learner-application-timeline');
                    if (timeline) {
                        const activeSteps = timeline.querySelectorAll('.is-current');
                        activeSteps.forEach((el) => {
                            el.classList.remove('is-current');
                        });
                        const withdrawnStep = timeline.querySelector('.is-withdrawn');
                        if (withdrawnStep) {
                            withdrawnStep.classList.add('is-current');
                        }
                    }
                }
                withdrawBtn.remove();

                if (typeof global.showToast === 'function') {
                    global.showToast('Đã rút hồ sơ ứng tuyển thành công.', 'warning');
                } else {
                    alert('Đã rút hồ sơ ứng tuyển thành công.');
                }
            } catch (err) {
                withdrawBtn.disabled = false;
                withdrawBtn.textContent = originalText;
                const msg = err?.message || 'Có lỗi xảy ra khi rút hồ sơ. Vui lòng thử lại sau.';
                if (typeof global.showToast === 'function') {
                    global.showToast(msg, 'error');
                } else {
                    alert(msg);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTracker);
    } else {
        initTracker();
    }
})(typeof window !== 'undefined' ? window : globalThis);
