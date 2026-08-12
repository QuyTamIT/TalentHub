/**
 * TalentHub Learner frontend interactions.
 * Keeps all behavior scoped to pages under app/learner.
 */
(function initLearnerModule(global) {
    'use strict';

    const implementedRoutes = new Set([
        '/app/learner',
        '/app/learner/',
        '/app/learner/index.php',
        '/app/learner/profile.php',
        '/app/learner/discover.php',
    ]);

    function validateProfile(data) {
        const requiredFields = [
            ['name', 'Vui lòng nhập họ và tên.'],
            ['class', 'Vui lòng nhập lớp.'],
            ['school', 'Vui lòng nhập trường.'],
            ['email', 'Vui lòng nhập email.'],
            ['location', 'Vui lòng nhập địa điểm.'],
        ];

        for (const [field, message] of requiredFields) {
            if (!String(data[field] || '').trim()) {
                return { valid: false, field, message };
            }
        }

        const email = String(data.email).trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return {
                valid: false,
                field: 'email',
                message: 'Email chưa đúng định dạng.',
            };
        }

        return { valid: true, field: '', message: '' };
    }

    function nextAssessmentState(state) {
        return state === 'start' ? 'continue' : state;
    }

    function isImplementedRoute(route) {
        if (typeof route !== 'string') return false;

        const normalizedRoute = route.split('?')[0].split('#')[0];
        return implementedRoutes.has(normalizedRoute);
    }

    global.LearnerUI = {
        validateProfile,
        nextAssessmentState,
        isImplementedRoute,
    };

    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const toast = document.getElementById('learner-toast');
        let toastTimer = null;
        let activeModal = null;
        let returnFocusTarget = null;
        let activeAssessment = null;

        const showToast = (message, tone = 'success') => {
            if (!toast) return;

            const messageElement = toast.querySelector('.learner-toast__message');
            if (messageElement) messageElement.textContent = message;
            toast.dataset.tone = tone;
            toast.classList.add('is-visible');

            global.clearTimeout(toastTimer);
            toastTimer = global.setTimeout(() => {
                toast.classList.remove('is-visible');
            }, 3500);
        };

        const sidebar = document.getElementById('learner-sidebar');
        const sidebarToggle = document.getElementById('learner-sidebar-toggle');
        const sidebarBackdrop = document.getElementById('learner-sidebar-backdrop');

        const setSidebarOpen = (shouldOpen) => {
            if (!sidebar || !sidebarToggle) return;

            sidebar.classList.toggle('is-open', shouldOpen);
            sidebarBackdrop?.classList.toggle('is-visible', shouldOpen);
            sidebarToggle.setAttribute('aria-expanded', String(shouldOpen));
            sidebarToggle.setAttribute('aria-label', shouldOpen ? 'Đóng danh mục điều hướng' : 'Mở danh mục điều hướng');
            document.body.classList.toggle('learner-sidebar-open', shouldOpen);
        };

        sidebarToggle?.addEventListener('click', () => {
            setSidebarOpen(!sidebar?.classList.contains('is-open'));
        });
        sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

        document.querySelectorAll('[data-pending-route]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const label = link.textContent.trim().replace(/\s+/g, ' ');
                showToast(`Tính năng “${label}” đang được phát triển.`, 'info');
                setSidebarOpen(false);
            });
        });

        document.getElementById('learner-notification-button')?.addEventListener('click', () => {
            showToast('Bạn có 3 thông báo mới về hoạt động và hồ sơ năng lực.', 'info');
        });

        document.querySelector('.learner-avatar')?.addEventListener('click', () => {
            showToast('Tài khoản của Nguyễn Văn A đang được hiển thị.', 'info');
        });

        document.getElementById('learner-search-form')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = document.getElementById('learner-search-input');
            const query = input?.value.trim() || '';
            showToast(query ? `Đang tìm kiếm “${query}” trong TalentHub.` : 'Nhập từ khóa để tìm hoạt động hoặc kỹ năng.', 'info');
        });

        document.querySelectorAll('[data-register-activity]').forEach((button) => {
            button.addEventListener('click', () => {
                if (button.disabled) return;

                button.textContent = 'Đã đăng ký';
                button.disabled = true;
                button.classList.add('is-complete');
                showToast('Đăng ký hoạt động thành công.');
            });
        });

        const getFocusableElements = (modal) => Array.from(modal.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((element) => !element.hidden && element.offsetParent !== null);

        const openModal = (modal, trigger) => {
            if (!modal) return;

            activeModal = modal;
            returnFocusTarget = trigger || document.activeElement;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('learner-modal-open');

            global.requestAnimationFrame(() => {
                const focusTarget = getFocusableElements(modal)[0] || modal.querySelector('.learner-modal__dialog');
                focusTarget?.focus();
            });
        };

        const closeModal = (modal = activeModal) => {
            if (!modal) return;

            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('learner-modal-open');
            activeModal = null;
            returnFocusTarget?.focus();
            returnFocusTarget = null;
        };

        document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                openModal(document.getElementById(trigger.dataset.openModal), trigger);
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => closeModal(trigger.closest('.learner-modal')));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (activeModal) {
                    closeModal();
                } else {
                    setSidebarOpen(false);
                }
                return;
            }

            if (event.key !== 'Tab' || !activeModal) return;

            const focusable = getFocusableElements(activeModal);
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
        });

        const profileForm = document.getElementById('learner-profile-form');
        profileForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = Object.fromEntries(new FormData(profileForm).entries());
            const validation = validateProfile(formData);

            profileForm.querySelectorAll('.learner-field__error').forEach((error) => {
                error.textContent = '';
            });
            profileForm.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
                input.removeAttribute('aria-invalid');
            });

            if (!validation.valid) {
                const field = profileForm.elements.namedItem(validation.field);
                const error = profileForm.querySelector(`[data-error-for="${validation.field}"]`);
                if (error) error.textContent = validation.message;
                field?.setAttribute('aria-invalid', 'true');
                field?.focus();
                return;
            }

            Object.entries(formData).forEach(([field, value]) => {
                const target = document.querySelector(`[data-profile-${field}]`);
                if (target) target.textContent = String(value).trim();
            });

            closeModal(profileForm.closest('.learner-modal'));
            showToast('Hồ sơ đã được cập nhật trên giao diện.');
        });

        document.querySelector('[data-copy-profile]')?.addEventListener('click', async (event) => {
            const input = document.getElementById('learner-share-link');
            if (!input) return;

            let copied = false;
            try {
                if (global.navigator?.clipboard && global.isSecureContext) {
                    await global.navigator.clipboard.writeText(input.value);
                    copied = true;
                } else {
                    input.focus();
                    input.select();
                    copied = document.execCommand('copy');
                }
            } catch (error) {
                copied = false;
            }

            event.currentTarget.textContent = copied ? 'Đã sao chép' : 'Chọn liên kết';
            showToast(copied ? 'Đã sao chép liên kết hồ sơ.' : 'Hãy chọn và sao chép liên kết thủ công.', copied ? 'success' : 'warning');
        });

        const assessmentModal = document.getElementById('learner-assessment-modal');
        const assessmentTitle = assessmentModal?.querySelector('[data-assessment-modal-title]');
        const assessmentCopy = assessmentModal?.querySelector('[data-assessment-modal-copy]');
        const assessmentConfirm = assessmentModal?.querySelector('[data-confirm-assessment]');

        document.querySelectorAll('[data-assessment-action]').forEach((button) => {
            button.addEventListener('click', () => {
                activeAssessment = { button, card: button.closest('[data-assessment-card]') };
                if (assessmentTitle) assessmentTitle.textContent = button.dataset.assessmentName || 'Bài đánh giá';
                if (assessmentCopy) assessmentCopy.textContent = button.dataset.assessmentResult || '';

                const state = button.dataset.assessmentAction;
                if (assessmentConfirm) {
                    assessmentConfirm.textContent = state === 'start' ? 'Bắt đầu ngay' : state === 'continue' ? 'Tiếp tục bài test' : 'Đã hiểu';
                }
                openModal(assessmentModal, button);
            });
        });

        assessmentConfirm?.addEventListener('click', () => {
            if (!activeAssessment) {
                closeModal(assessmentModal);
                return;
            }

            const { button, card } = activeAssessment;
            const state = button.dataset.assessmentAction || 'result';
            const nextState = nextAssessmentState(state);

            if (state === 'start') {
                button.dataset.assessmentAction = nextState;
                button.textContent = 'Tiếp tục';
                button.classList.remove('learner-btn--primary');
                button.classList.add('learner-btn--secondary');
                if (card) card.dataset.state = nextState;
                showToast(`Đã bắt đầu bài test ${button.dataset.assessmentName}.`);
            } else if (state === 'continue') {
                showToast(`Đang tiếp tục bài test ${button.dataset.assessmentName}.`, 'info');
            } else {
                showToast(`Đã xem kết quả ${button.dataset.assessmentName}.`, 'info');
            }

            activeAssessment = null;
            closeModal(assessmentModal);
        });
    });
})(typeof window !== 'undefined' ? window : globalThis);
