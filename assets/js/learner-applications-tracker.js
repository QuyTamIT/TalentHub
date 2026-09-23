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
        const shouldAutoExpand = urlParams.get('tab') === 'applications'
            || urlParams.get('view') === 'applications'
            || window.location.hash === '#my-applications'
            || window.location.hash === '#applications-tracker-title'
            || tracker.dataset.hasApplications === 'true';

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
            const cancelAcceptedBtn = event.target.closest('[data-cancel-accepted-btn]');
            if (cancelAcceptedBtn && !cancelAcceptedBtn.disabled) {
                const appId = cancelAcceptedBtn.dataset.applicationId;
                if (!appId) return;
                const confirmed = window.confirm('Bạn chắc chắn muốn hủy xác nhận vị trí này để có thể nhận lời mời khác?');
                if (!confirmed) return;

                cancelAcceptedBtn.disabled = true;
                const originalText = cancelAcceptedBtn.textContent;
                cancelAcceptedBtn.textContent = 'Đang xử lý...';

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || document.getElementById('learner-session-boot')?.textContent && (() => {
                            try {
                                return JSON.parse(document.getElementById('learner-session-boot').textContent || '{}').csrfToken || '';
                            } catch {
                                return '';
                            }
                        })()
                        || '';
                    let client = null;
                    if (global.TalentHubLearnerApi?.createLearnerApiClient) {
                        client = global.TalentHubLearnerApi.createLearnerApiClient({
                            baseUrl: '/app/learner/api/v1',
                            csrfToken,
                        });
                    }
                    const response = client
                        ? await client.send('PATCH', '/notifications.php', {
                            action: 'respond-invitation',
                            applicationId: appId,
                            decision: 'decline',
                        })
                        : null;
                    if (!response || response.status !== 'declined') {
                        throw new Error('Không thể hủy xác nhận vị trí.');
                    }

                    const card = cancelAcceptedBtn.closest('[data-app-card]');
                    if (card) {
                        const statusBadge = card.querySelector('[data-app-status-badge]');
                        if (statusBadge) {
                            statusBadge.className = 'learner-status-badge is-declined';
                            statusBadge.innerHTML = '<span class="dot" aria-hidden="true"></span> Đã từ chối';
                        }
                        const banner = card.querySelector('.learner-app-accepted-banner');
                        if (banner) banner.remove();
                    }
                    cancelAcceptedBtn.remove();
                    if (typeof global.showToast === 'function') {
                        global.showToast('Đã hủy xác nhận vị trí. Bạn có thể chấp nhận lời mời khác.', 'warning');
                    } else {
                        alert('Đã hủy xác nhận vị trí. Bạn có thể chấp nhận lời mời khác.');
                    }
                } catch (err) {
                    cancelAcceptedBtn.disabled = false;
                    cancelAcceptedBtn.textContent = originalText;
                    const msg = err?.message || 'Không thể hủy xác nhận vị trí.';
                    if (typeof global.showToast === 'function') {
                        global.showToast(msg, 'error');
                    } else {
                        alert(msg);
                    }
                }
                return;
            }

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
                        statusBadge.innerHTML = '<span class="dot" aria-hidden="true"></span> Đã rút';
                    }
                    const stepper = card.querySelector('.learner-app-stepper');
                    if (stepper) {
                        const currentSteps = stepper.querySelectorAll('.is-current');
                        currentSteps.forEach((el) => {
                            el.classList.remove('is-current');
                        });
                        const decisionStep = stepper.querySelector('[data-step-id="decision"]');
                        if (decisionStep) {
                            decisionStep.className = 'learner-app-step is-withdrawn';
                            const node = decisionStep.querySelector('.learner-app-step__node');
                            if (node) {
                                node.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';
                            }
                            const desc = decisionStep.querySelector('.learner-app-step__desc');
                            if (desc) {
                                desc.textContent = 'Học viên đã chủ động rút hồ sơ';
                            }
                        }
                    }
                    let statusNote = card.querySelector('.learner-app-status-note');
                    if (!statusNote) {
                        statusNote = document.createElement('div');
                        statusNote.className = 'learner-app-status-note';
                        card.appendChild(statusNote);
                    }
                    statusNote.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><div>Bạn đã chủ động rút hồ sơ khỏi vị trí tuyển dụng này.</div>';
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
