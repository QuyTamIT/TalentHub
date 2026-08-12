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
        '/app/learner/activities.php',
        '/app/learner/checkin.php',
        '/app/learner/evaluation.php',
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

    function normalizeSearchText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLocaleLowerCase('vi')
            .trim();
    }

    function activityMatches(activity, query, category) {
        const normalizedCategory = category || 'Tất cả';
        const categoryMatches = normalizedCategory === 'Tất cả'
            || activity.filterCategory === normalizedCategory;
        const haystack = normalizeSearchText([
            activity.title,
            activity.category,
            activity.filterCategory,
            activity.location,
        ].join(' '));

        return categoryMatches && haystack.includes(normalizeSearchText(query));
    }

    function getEvaluationTerm(terms, termId) {
        if (!terms || typeof terms !== 'object') return null;
        return Object.prototype.hasOwnProperty.call(terms, termId) ? terms[termId] : null;
    }

    global.LearnerUI = {
        validateProfile,
        nextAssessmentState,
        isImplementedRoute,
        normalizeSearchText,
        activityMatches,
        getEvaluationTerm,
    };

    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const toast = document.getElementById('learner-toast');
        let toastTimer = null;
        let activeModal = null;
        let returnFocusTarget = null;
        let activeAssessment = null;

        const getFocusableElements = (container) => Array.from(container.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((element) => !element.hidden && element.offsetParent !== null);

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
        const sidebarClose = document.getElementById('learner-sidebar-close');
        const sidebarBackdrop = document.getElementById('learner-sidebar-backdrop');
        const drawerMedia = global.matchMedia('(max-width: 1100px)');

        const setSidebarOpen = (shouldOpen, restoreFocus = true) => {
            if (!sidebar || !sidebarToggle) return;

            if (!drawerMedia.matches) shouldOpen = false;
            const wasOpen = sidebar.classList.contains('is-open');

            sidebar.classList.toggle('is-open', shouldOpen);
            sidebarBackdrop?.classList.toggle('is-visible', shouldOpen);
            sidebarToggle.setAttribute('aria-expanded', String(shouldOpen));
            sidebarToggle.setAttribute('aria-label', shouldOpen ? 'Đóng danh mục điều hướng' : 'Mở danh mục điều hướng');
            document.body.classList.toggle('learner-sidebar-open', shouldOpen);

            if (drawerMedia.matches) {
                sidebar.inert = !shouldOpen;
                sidebar.setAttribute('aria-hidden', String(!shouldOpen));
            } else {
                sidebar.inert = false;
                sidebar.setAttribute('aria-hidden', 'false');
            }

            if (shouldOpen) {
                global.requestAnimationFrame(() => {
                    const target = sidebar.querySelector('[aria-current="page"]') || getFocusableElements(sidebar)[0];
                    target?.focus();
                });
            } else if (wasOpen && restoreFocus) {
                sidebarToggle.focus();
            }
        };

        const syncSidebarMode = () => {
            setSidebarOpen(false, false);
        };

        sidebarToggle?.addEventListener('click', () => {
            setSidebarOpen(!sidebar?.classList.contains('is-open'));
        });
        sidebarClose?.addEventListener('click', () => setSidebarOpen(false));
        sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));
        drawerMedia.addEventListener('change', syncSidebarMode);
        syncSidebarMode();

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

        const closeModal = (modal = activeModal, fallbackFocusTarget = null) => {
            if (!modal) return;

            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('learner-modal-open');
            activeModal = null;
            const focusTarget = fallbackFocusTarget
                || (returnFocusTarget && !returnFocusTarget.disabled ? returnFocusTarget : null);
            focusTarget?.focus();
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

        const activityCards = Array.from(document.querySelectorAll('[data-activity-card]'));
        const activityFilters = Array.from(document.querySelectorAll('[data-activity-filter]'));
        const activityEmpty = document.querySelector('[data-activity-empty]');
        const activityResultStatus = document.querySelector('[data-activity-result-status]');
        const activitySearch = document.getElementById('learner-search-input');
        const registrationModal = document.getElementById('learner-registration-modal');
        const registrationName = registrationModal?.querySelector('[data-registration-name]');
        const registrationConfirm = registrationModal?.querySelector('[data-confirm-registration]');
        let activeActivityCategory = 'Tất cả';
        let pendingRegistrationButton = null;

        const updateActivityResults = () => {
            let visibleCount = 0;
            activityCards.forEach((card) => {
                const matches = activityMatches({
                    title: card.dataset.title,
                    category: card.dataset.category,
                    filterCategory: card.dataset.filterCategory,
                    location: card.dataset.location,
                }, activitySearch?.value || '', activeActivityCategory);
                card.hidden = !matches;
                if (matches) visibleCount += 1;
            });

            if (activityEmpty) activityEmpty.hidden = visibleCount !== 0;
            if (activityResultStatus) activityResultStatus.textContent = `${visibleCount} hoạt động phù hợp`;
        };

        if (activityCards.length > 0) {
            activitySearch?.addEventListener('input', updateActivityResults);
            activityFilters.forEach((filter) => {
                filter.addEventListener('click', () => {
                    activeActivityCategory = filter.dataset.activityFilter || 'Tất cả';
                    activityFilters.forEach((item) => {
                        item.setAttribute('aria-pressed', String(item === filter));
                    });
                    updateActivityResults();
                });
            });

            document.querySelectorAll('[data-activity-register]').forEach((button) => {
                button.addEventListener('click', () => {
                    pendingRegistrationButton = button;
                    if (registrationName) {
                        registrationName.textContent = button.dataset.activityName || 'hoạt động này';
                    }
                    openModal(registrationModal, button);
                });
            });

            registrationConfirm?.addEventListener('click', () => {
                if (!pendingRegistrationButton) return;

                const completedButton = pendingRegistrationButton;
                const fallbackFocusTarget = Array.from(document.querySelectorAll('[data-activity-register]:not(:disabled)'))
                    .find((button) => button !== completedButton && !button.closest('[hidden]'))
                    || activitySearch;

                completedButton.textContent = 'Đã đăng ký';
                completedButton.disabled = true;
                completedButton.classList.add('is-complete');
                pendingRegistrationButton = null;
                closeModal(registrationModal, fallbackFocusTarget);
                showToast('Đăng ký hoạt động thành công.');
            });

            updateActivityResults();
        }

        const evaluationSelect = document.getElementById('learner-evaluation-term');
        const evaluationPayload = document.getElementById('learner-evaluation-data');
        const evaluationContent = document.querySelector('[data-evaluation-content]');
        const evaluationSummary = document.querySelector('[data-evaluation-summary]');
        const evaluationEmpty = document.querySelector('[data-evaluation-empty]');
        const evaluationCriteria = document.querySelector('[data-evaluation-criteria]');
        const evaluationStatus = document.querySelector('[data-evaluation-status]');

        if (evaluationSelect && evaluationPayload) {
            let evaluationTerms = {};
            try {
                evaluationTerms = JSON.parse(evaluationPayload.textContent || '{}');
            } catch (error) {
                showToast('Không thể tải dữ liệu đánh giá.', 'warning');
            }

            const setEvaluationText = (selector, value) => {
                const target = document.querySelector(selector);
                if (target) target.textContent = String(value ?? '');
            };

            const renderEvaluation = (termId) => {
                const term = getEvaluationTerm(evaluationTerms, termId);
                const evaluation = term?.evaluation || null;

                if (evaluationStatus) {
                    evaluationStatus.textContent = term?.status || 'Chưa có dữ liệu';
                    evaluationStatus.dataset.state = evaluation ? 'published' : 'empty';
                }
                if (evaluationContent) evaluationContent.hidden = !evaluation;
                if (evaluationSummary) evaluationSummary.hidden = !evaluation;
                if (evaluationEmpty) evaluationEmpty.hidden = Boolean(evaluation);
                if (!evaluation || !evaluationCriteria) return;

                const rows = evaluation.criteria.map((criterion) => {
                    const row = document.createElement('article');
                    row.className = 'learner-evaluation-criterion';
                    row.dataset.evaluationCriterion = '';

                    const heading = document.createElement('div');
                    heading.className = 'learner-evaluation-criterion__heading';
                    const name = document.createElement('span');
                    const score = document.createElement('strong');
                    name.textContent = criterion.name;
                    score.textContent = `${criterion.score}/${criterion.max}`;
                    heading.append(name, score);

                    const progress = document.createElement('div');
                    progress.className = 'learner-progress';
                    progress.setAttribute('role', 'progressbar');
                    progress.setAttribute('aria-label', criterion.name);
                    progress.setAttribute('aria-valuemin', '0');
                    progress.setAttribute('aria-valuemax', String(criterion.max));
                    progress.setAttribute('aria-valuenow', String(criterion.score));

                    const bar = document.createElement('span');
                    bar.className = `learner-progress--${criterion.tone}`;
                    bar.style.setProperty('--learner-progress', `${criterion.score / criterion.max * 100}%`);
                    progress.append(bar);
                    row.append(heading, progress);
                    return row;
                });

                evaluationCriteria.replaceChildren(...rows);
                setEvaluationText('[data-evaluation-total]', evaluation.total);
                setEvaluationText('[data-evaluation-classification]', evaluation.classification);
                setEvaluationText('[data-evaluation-ranking]', evaluation.ranking);
                setEvaluationText('[data-evaluation-comment]', evaluation.comment);
                setEvaluationText('[data-evaluation-reviewer]', evaluation.reviewer);
            };

            evaluationSelect.addEventListener('change', () => {
                renderEvaluation(evaluationSelect.value);
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (activeModal) {
                    closeModal();
                } else {
                    setSidebarOpen(false);
                }
                return;
            }

            if (event.key !== 'Tab') return;

            if (!activeModal && drawerMedia.matches && sidebar?.classList.contains('is-open')) {
                const sidebarFocusable = getFocusableElements(sidebar);
                if (sidebarFocusable.length === 0) return;

                const firstSidebarItem = sidebarFocusable[0];
                const lastSidebarItem = sidebarFocusable[sidebarFocusable.length - 1];
                if (event.shiftKey && document.activeElement === firstSidebarItem) {
                    event.preventDefault();
                    lastSidebarItem.focus();
                } else if (!event.shiftKey && document.activeElement === lastSidebarItem) {
                    event.preventDefault();
                    firstSidebarItem.focus();
                }
                return;
            }

            if (!activeModal) return;

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
            const button = event.currentTarget;
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

            button.textContent = copied ? 'Đã sao chép' : 'Chọn liên kết';
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
