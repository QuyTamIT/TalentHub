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
        '/app/learner/ai-recommendations.php',
        '/app/learner/badges.php',
        '/app/learner/statistics.php',
        '/app/learner/ecosystem.php',
        '/app/learner/partner.php',
        '/app/learner/opportunity.php',
        '/app/learner/assessment.php',
        '/app/learner/assessment-result.php',
        '/app/learner/activity-detail.php',
        '/app/learner/my-activities.php',
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

    function getAiRecommendationState(data) {
        return data && data.sufficient === true ? 'ready' : 'insufficient';
    }

    function badgeMatchesStatus(badgeStatus, activeStatus) {
        return activeStatus === 'all' || badgeStatus === activeStatus;
    }

    function getStatisticsPeriod(periods, periodId) {
        if (!periods || typeof periods !== 'object') return null;
        return Object.prototype.hasOwnProperty.call(periods, periodId) ? periods[periodId] : null;
    }

    function buildLineChartPoints(values, width, height, maxValue) {
        if (!Array.isArray(values) || values.length === 0 || maxValue <= 0) return [];

        const denominator = Math.max(1, values.length - 1);
        return values.map((value, index) => [
            width * index / denominator,
            height - Math.max(0, Math.min(maxValue, Number(value) || 0)) / maxValue * height,
        ]);
    }

    function ecosystemItemMatches(item, filters = {}) {
        const query = normalizeSearchText(filters.query || '');
        const field = normalizeSearchText(filters.field || 'all');
        const location = normalizeSearchText(filters.location || 'all');
        const search = normalizeSearchText(item?.search || '');
        const itemField = normalizeSearchText(item?.field || '');
        const itemLocation = normalizeSearchText(item?.location || '');

        return (!query || search.includes(query))
            && (field === 'all' || itemField.includes(field))
            && (location === 'all' || itemLocation.includes(location));
    }

    function applicationMatches(application, query, status) {
        const normalizedStatus = status || 'all';
        return (normalizedStatus === 'all' || application?.status === normalizedStatus)
            && normalizeSearchText(application?.search || '').includes(normalizeSearchText(query));
    }

    function canApplyToOpportunity(opportunity, today) {
        if (!opportunity || opportunity.status !== 'active') return false;
        const currentDate = today || new Date().toISOString().slice(0, 10);
        return String(opportunity.deadline || '') >= currentDate;
    }

    function validateApplication(data) {
        const message = String(data?.message || '');
        if (message.length > 500) {
            return {
                valid: false,
                field: 'message',
                message: 'Lời nhắn không được vượt quá 500 ký tự.',
            };
        }
        if (data?.consent !== true) {
            return {
                valid: false,
                field: 'consent',
                message: 'Bạn cần đồng ý chia sẻ hồ sơ trước khi ứng tuyển.',
            };
        }
        return { valid: true, field: '', message: '' };
    }

    function resolveMutationBackend(source, hasApiClient) {
        if (hasApiClient) return 'server';
        return source === 'mock' ? 'mock' : 'unavailable';
    }

    global.LearnerProfileUiContract = Object.freeze({ resolveMutationBackend });

    global.LearnerUI = {
        validateProfile,
        nextAssessmentState,
        isImplementedRoute,
        normalizeSearchText,
        activityMatches,
        getEvaluationTerm,
        getAiRecommendationState,
        badgeMatchesStatus,
        getStatisticsPeriod,
        buildLineChartPoints,
        ecosystemItemMatches,
        applicationMatches,
        canApplyToOpportunity,
        validateApplication,
    };

    function createPageApiClient(baseOverride = '') {
        if (typeof document === 'undefined') return null;

        const node = document.getElementById('learner-session-boot');
        if (!node || !global.TalentHubLearnerApi) return null;

        let boot;
        try {
            boot = JSON.parse(node.textContent || '{}');
        } catch {
            return null;
        }

        try {
            return global.TalentHubLearnerApi.createLearnerApiClient({
                baseUrl: baseOverride || boot.apiBase || '/api/v1',
                csrfToken: boot.csrfToken || '',
                onUnauthorized: () => {
                    if (typeof global.location?.assign !== 'function') return;
                    global.location.assign(`/login.php?next=${encodeURIComponent(global.location.pathname + global.location.search)}`);
                },
            });
        } catch {
            return null;
        }
    }

    global.TalentHubLearnerClient = createPageApiClient();

    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const applicationApiClient = createPageApiClient('/app/learner/api/v1');
        const toast = document.getElementById('learner-toast');
        let toastTimer = null;
        let activeModal = null;
        let returnFocusTarget = null;

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

        document.querySelector('.learner-avatar')?.addEventListener('click', () => {
            showToast('Tài khoản của Nguyễn Văn A đang được hiển thị.', 'info');
        });

        document.getElementById('learner-search-form')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = document.getElementById('learner-search-input');
            const query = input?.value.trim() || '';
            showToast(query ? `Đang tìm kiếm “${query}” trong TalentHub.` : 'Nhập từ khóa để tìm hoạt động hoặc kỹ năng.', 'info');
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

        const ecosystemPage = document.querySelector('[data-ecosystem-page]');
        if (ecosystemPage) {
            const ecosystemTabs = Array.from(document.querySelectorAll('[data-ecosystem-tab]'));
            const ecosystemPanels = Array.from(document.querySelectorAll('[data-ecosystem-panel]'));
            const ecosystemSearch = document.querySelector('[data-ecosystem-search]');
            const headerEcosystemSearch = document.getElementById('learner-search-input');
            const ecosystemFilters = Array.from(document.querySelectorAll('[data-ecosystem-filter]'));

            const updateEcosystemResults = () => {
                const activePanel = ecosystemPanels.find((panel) => !panel.hidden);
                if (!activePanel) return;

                const filters = ecosystemFilters.reduce((result, select) => {
                    result[select.dataset.ecosystemFilter] = select.value;
                    return result;
                }, {
                    query: ecosystemSearch?.value || headerEcosystemSearch?.value || '',
                });
                filters.query = ecosystemSearch?.value || headerEcosystemSearch?.value || '';

                let visibleCount = 0;
                activePanel.querySelectorAll('[data-ecosystem-item]').forEach((card) => {
                    const visible = ecosystemItemMatches({
                        search: card.dataset.search,
                        field: card.dataset.field,
                        location: card.dataset.location,
                    }, filters);
                    card.hidden = !visible;
                    if (visible) visibleCount += 1;
                });

                const emptyState = activePanel.querySelector('[data-ecosystem-empty]');
                if (emptyState) emptyState.hidden = visibleCount !== 0;
            };

            const activateEcosystemTab = (tabId, focusTab = false) => {
                const nextTab = ecosystemTabs.find((tab) => tab.dataset.ecosystemTab === tabId)
                    || ecosystemTabs[0];
                ecosystemTabs.forEach((tab) => {
                    const selected = tab === nextTab;
                    tab.setAttribute('aria-selected', String(selected));
                    tab.tabIndex = selected ? 0 : -1;
                });
                ecosystemPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.ecosystemPanel !== nextTab.dataset.ecosystemTab;
                });
                const nextUrl = new URL(global.location.href);
                nextUrl.searchParams.set('tab', nextTab.dataset.ecosystemTab);
                global.history.replaceState({}, '', nextUrl);
                updateEcosystemResults();
                if (focusTab) nextTab.focus();
            };

            ecosystemTabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activateEcosystemTab(tab.dataset.ecosystemTab));
                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                    event.preventDefault();
                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    const nextIndex = (index + direction + ecosystemTabs.length) % ecosystemTabs.length;
                    activateEcosystemTab(ecosystemTabs[nextIndex].dataset.ecosystemTab, true);
                });
            });
            ecosystemFilters.forEach((select) => select.addEventListener('change', updateEcosystemResults));
            ecosystemSearch?.addEventListener('input', () => {
                if (headerEcosystemSearch) headerEcosystemSearch.value = ecosystemSearch.value;
                updateEcosystemResults();
            });
            headerEcosystemSearch?.addEventListener('input', () => {
                if (ecosystemSearch) ecosystemSearch.value = headerEcosystemSearch.value;
                updateEcosystemResults();
            });
            activateEcosystemTab(ecosystemPage.dataset.initialTab || 'enterprises');
        }

        const applicationItems = Array.from(document.querySelectorAll('[data-application-item]'));
        const applicationSearch = document.querySelector('[data-application-search]');
        const applicationFilters = Array.from(document.querySelectorAll('[data-application-filter]'));
        let activeApplicationStatus = 'all';

        const updateApplications = () => {
            let visibleCount = 0;
            applicationItems.forEach((item) => {
                const visible = applicationMatches({
                    search: item.dataset.search,
                    status: item.dataset.status,
                }, applicationSearch?.value || '', activeApplicationStatus);
                item.hidden = !visible;
                if (visible) visibleCount += 1;
            });
            const empty = document.querySelector('[data-application-empty]');
            if (empty) empty.hidden = visibleCount !== 0;
        };

        applicationSearch?.addEventListener('input', updateApplications);
        applicationFilters.forEach((button) => {
            button.addEventListener('click', () => {
                activeApplicationStatus = button.dataset.applicationFilter || 'all';
                applicationFilters.forEach((filter) => {
                    filter.setAttribute('aria-pressed', String(filter === button));
                });
                updateApplications();
            });
        });
        applicationItems.forEach((item) => {
            const toggle = item.querySelector('[data-application-toggle]');
            const details = item.querySelector('[data-application-details]');
            toggle?.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!expanded));
                if (details) details.hidden = expanded;
            });
            item.querySelector('[data-withdraw-application]')?.addEventListener('click', async (event) => {
                const button = event.currentTarget;
                const applicationId = item.dataset.applicationId || '';
                if (!applicationApiClient || !applicationId || button.disabled) return;
                button.disabled = true;
                try {
                    const response = await applicationApiClient.send('PATCH', '/applications.php', {
                        action: 'withdraw', applicationId, reason: 'Học viên chủ động rút hồ sơ',
                    });
                    const application = response?.application;
                    if (!application || application.status !== 'withdrawn') throw new Error('Invalid application response');
                    item.dataset.status = 'withdrawn';
                    const status = item.querySelector('.learner-application-status');
                    if (status) {
                        status.className = 'learner-application-status learner-application-status--withdrawn';
                        status.textContent = 'Đã rút hồ sơ';
                    }
                    button.remove();
                    showToast('Đã rút hồ sơ.', 'warning');
                    updateApplications();
                } catch (error) {
                    button.disabled = false;
                    showToast(error?.message || 'Không thể rút hồ sơ.', 'error');
                }
            });
        });

        const applicationForm = document.querySelector('[data-application-form]');
        const applicationMessage = applicationForm?.querySelector('[data-application-message]');
        const applicationMessageCount = applicationForm?.querySelector('[data-application-message-count]');
        const applicationError = applicationForm?.querySelector('[data-application-error]');
        applicationMessage?.addEventListener('input', () => {
            if (applicationMessageCount) applicationMessageCount.textContent = String(applicationMessage.value.length);
        });
        applicationForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const consent = applicationForm.querySelector('[data-application-consent]');
            const validation = validateApplication({
                message: applicationMessage?.value || '',
                consent: consent?.checked === true,
            });
            if (!validation.valid) {
                if (applicationError) {
                    applicationError.hidden = false;
                    applicationError.textContent = validation.message;
                }
                const target = applicationForm.elements.namedItem(validation.field);
                target?.focus();
                return;
            }
            if (applicationError) applicationError.hidden = true;
            const submitButton = applicationForm.querySelector('[type="submit"]');
            const opportunityId = document.body.dataset.opportunityId || '';
            if (!applicationApiClient || !opportunityId) {
                if (applicationError) { applicationError.hidden = false; applicationError.textContent = 'Không thể kết nối dịch vụ ứng tuyển.'; }
                return;
            }
            if (submitButton) submitButton.disabled = true;
            try {
                await applicationApiClient.send('POST', '/applications.php', { action: 'grant-consent', confirmed: true });
                const response = await applicationApiClient.send('POST', '/applications.php', {
                    action: 'submit', postId: opportunityId, message: applicationMessage?.value || '',
                });
                if (!response?.application || response.application.status !== 'submitted') throw new Error('Phản hồi ứng tuyển không hợp lệ.');
                closeModal(applicationForm.closest('.learner-modal'));
                showToast('Hồ sơ ứng tuyển đã được gửi thành công.');
                applicationForm.reset();
                if (applicationMessageCount) applicationMessageCount.textContent = '0';
            } catch (error) {
                if (applicationError) { applicationError.hidden = false; applicationError.textContent = error?.message || 'Không thể gửi hồ sơ ứng tuyển.'; }
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });

        const activityCards = Array.from(document.querySelectorAll('[data-activity-card]'));
        const activityFilters = Array.from(document.querySelectorAll('[data-activity-filter]'));
        const activityEmpty = document.querySelector('[data-activity-empty]');
        const activityResultStatus = document.querySelector('[data-activity-result-status]');
        const activitySearch = document.getElementById('learner-search-input');
        let activeActivityCategory = 'Tất cả';

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
                    const maximum = Number(criterion.max);
                    const percentage = maximum > 0
                        ? Math.max(0, Math.min(100, Number(criterion.score) / maximum * 100))
                        : 0;
                    bar.style.setProperty('--learner-progress', `${percentage}%`);
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

        const aiPage = document.querySelector('[data-ai-page]');
        const aiPayload = document.getElementById('learner-ai-data');

        if (aiPage && aiPayload) {
            const loadingState = aiPage.querySelector('[data-ai-loading]');
            const readyState = aiPage.querySelector('[data-ai-ready]');
            const insufficientState = aiPage.querySelector('[data-ai-insufficient]');
            const stateStatus = aiPage.querySelector('[data-ai-state-status]');
            let aiData = null;

            try {
                aiData = JSON.parse(aiPayload.textContent || 'null');
            } catch (error) {
                aiData = null;
            }

            const renderAiState = (state) => {
                if (loadingState) loadingState.hidden = state !== 'loading';
                if (readyState) readyState.hidden = state !== 'ready';
                if (insufficientState) insufficientState.hidden = state !== 'insufficient';
                aiPage.dataset.aiState = state;

                if (stateStatus) {
                    stateStatus.textContent = state === 'loading'
                        ? 'AI đang phân tích dữ liệu.'
                        : state === 'ready'
                            ? 'Phân tích năng lực đã sẵn sàng.'
                            : 'Chưa đủ dữ liệu để phân tích năng lực.';
                }
            };

            const targetState = getAiRecommendationState(aiData);
            if (targetState === 'ready') {
                renderAiState('loading');
                const delay = global.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 650;
                global.setTimeout(() => renderAiState('ready'), delay);
            } else {
                renderAiState('insufficient');
            }
        }

        const badgeCards = Array.from(document.querySelectorAll('[data-badge-card]'));
        const badgeFilters = Array.from(document.querySelectorAll('[data-badge-filter]'));
        const badgeEmpty = document.querySelector('[data-badge-empty]');
        const badgeResultStatus = document.querySelector('[data-badge-result-status]');

        if (badgeCards.length > 0 && badgeFilters.length > 0) {
            const renderBadgeFilter = (activeStatus) => {
                let visibleCount = 0;

                badgeCards.forEach((card) => {
                    const matches = badgeMatchesStatus(card.dataset.badgeStatus, activeStatus);
                    card.hidden = !matches;
                    if (matches) visibleCount += 1;
                });

                badgeFilters.forEach((filter) => {
                    filter.setAttribute('aria-pressed', String(filter.dataset.badgeFilter === activeStatus));
                });
                if (badgeEmpty) badgeEmpty.hidden = visibleCount !== 0;
                if (badgeResultStatus) badgeResultStatus.textContent = `${visibleCount} huy hiệu phù hợp`;
            };

            badgeFilters.forEach((filter) => {
                filter.addEventListener('click', () => {
                    renderBadgeFilter(filter.dataset.badgeFilter || 'all');
                });
            });

            renderBadgeFilter('all');
        }

        const statisticsSelect = document.getElementById('learner-statistics-period');
        const statisticsPayload = document.getElementById('learner-statistics-data');
        const statisticsContent = document.querySelector('[data-statistics-content]');
        const statisticsEmpty = document.querySelector('[data-statistics-empty]');
        const statisticsStatus = document.querySelector('[data-statistics-status]');

        if (statisticsSelect && statisticsPayload) {
            const svgNamespace = 'http://www.w3.org/2000/svg';
            let statisticsPeriods = {};

            try {
                statisticsPeriods = JSON.parse(statisticsPayload.textContent || '{}');
            } catch (error) {
                statisticsPeriods = {};
            }

            const createSvgElement = (name, attributes = {}) => {
                const element = document.createElementNS(svgNamespace, name);
                Object.entries(attributes).forEach(([attribute, value]) => {
                    element.setAttribute(attribute, String(value));
                });
                return element;
            };

            const renderExperienceChart = (experience) => {
                const barsLayer = document.querySelector('[data-experience-bars]');
                const lineLayer = document.querySelector('[data-experience-line]');
                const labelsLayer = document.querySelector('[data-experience-labels]');
                const description = document.querySelector('[data-experience-description]');
                if (!barsLayer || !lineLayer || !labelsLayer || !experience) return;

                const left = 46;
                const top = 24;
                const width = 550;
                const height = 170;
                const values = Array.isArray(experience.hours) ? experience.hours : [];
                const comparison = Array.isArray(experience.comparison) ? experience.comparison : [];
                const labels = Array.isArray(experience.labels) ? experience.labels : [];
                const maxValue = Math.max(20, ...values, ...comparison);
                const step = width / Math.max(1, values.length);
                const barWidth = Math.min(36, step * 0.42);

                const bars = values.map((value, index) => {
                    const barHeight = Number(value) / maxValue * height;
                    return createSvgElement('rect', {
                        x: left + (index + 0.5) * step - barWidth / 2,
                        y: top + height - barHeight,
                        width: barWidth,
                        height: barHeight,
                        rx: 5,
                    });
                });

                const chartPoints = buildLineChartPoints(comparison, width - step, height, maxValue)
                    .map(([x, y]) => [left + step / 2 + x, top + y]);
                const polyline = createSvgElement('polyline', {
                    points: chartPoints.map((point) => point.join(',')).join(' '),
                });
                const circles = chartPoints.map(([x, y]) => createSvgElement('circle', { cx: x, cy: y, r: 4 }));
                const labelNodes = labels.map((label, index) => {
                    const node = createSvgElement('text', {
                        x: left + (index + 0.5) * step,
                        y: 224,
                        'text-anchor': 'middle',
                    });
                    node.textContent = label;
                    return node;
                });

                barsLayer.replaceChildren(...bars);
                lineLayer.replaceChildren(polyline, ...circles);
                labelsLayer.replaceChildren(...labelNodes);
                if (description) {
                    const learnerSeries = labels.map((label, index) => `${label}: ${values[index]} giờ`).join(', ');
                    const comparisonSeries = labels.map((label, index) => `${label}: ${comparison[index]} giờ`).join(', ');
                    description.textContent = `Giờ trải nghiệm của bạn: ${learnerSeries}. Xu hướng tham chiếu: ${comparisonSeries}.`;
                }
            };

            const renderFieldChart = (fields) => {
                const segmentsLayer = document.querySelector('[data-field-segments]');
                const totalElement = document.querySelector('[data-field-total]');
                const legend = document.querySelector('[data-field-legend]');
                const description = document.querySelector('[data-field-description]');
                if (!segmentsLayer || !legend || !Array.isArray(fields)) return;

                const radius = 70;
                const circumference = 2 * Math.PI * radius;
                let offset = 0;
                const segments = fields.map((field) => {
                    const length = circumference * Number(field.percentage) / 100;
                    const segment = createSvgElement('circle', {
                        cx: 100,
                        cy: 100,
                        r: radius,
                        'stroke-dasharray': `${length} ${circumference - length}`,
                        'stroke-dashoffset': -offset,
                        class: `learner-statistics-donut__segment learner-statistics-donut__segment--${field.tone}`,
                    });
                    offset += length;
                    return segment;
                });
                const legendItems = fields.map((field) => {
                    const item = document.createElement('div');
                    item.className = 'learner-field-legend__item';
                    const dot = document.createElement('span');
                    dot.className = `learner-field-legend__dot learner-field-legend__dot--${field.tone}`;
                    dot.setAttribute('aria-hidden', 'true');
                    const copy = document.createElement('span');
                    const title = document.createElement('strong');
                    const detail = document.createElement('small');
                    title.textContent = field.label;
                    detail.textContent = `${field.hours} giờ (${field.percentage}%)`;
                    copy.append(title, detail);
                    item.append(dot, copy);
                    return item;
                });

                segmentsLayer.replaceChildren(...segments);
                legend.replaceChildren(...legendItems);
                const total = fields.reduce((sum, field) => sum + Number(field.hours || 0), 0);
                if (totalElement) totalElement.textContent = String(total);
                if (description) {
                    description.textContent = fields.map((field) => `${field.label}: ${field.hours} giờ`).join(', ');
                }
            };

            const renderStatistics = (periodId) => {
                const period = getStatisticsPeriod(statisticsPeriods, periodId);
                if (statisticsContent) statisticsContent.hidden = !period;
                if (statisticsEmpty) statisticsEmpty.hidden = Boolean(period);
                if (statisticsStatus) {
                    statisticsStatus.textContent = period
                        ? `Đang hiển thị thống kê ${period.label}.`
                        : 'Chưa có dữ liệu trong khoảng thời gian đã chọn.';
                }
                if (!period) return;

                period.kpis.forEach((kpi) => {
                    const card = document.querySelector(`[data-statistics-kpi][data-kpi-id="${kpi.id}"]`);
                    if (!card) return;
                    const value = card.querySelector('[data-kpi-value]');
                    const suffix = card.querySelector('[data-kpi-suffix]');
                    const change = card.querySelector('[data-kpi-change]');
                    if (value) value.textContent = String(kpi.value);
                    if (suffix) suffix.textContent = kpi.suffix;
                    if (change) change.textContent = kpi.change;
                });

                renderExperienceChart(period.experience);
                renderFieldChart(period.fields);

                const skillRows = Array.from(document.querySelectorAll('[data-statistics-skill]'));
                period.skills.forEach((skill, index) => {
                    const row = skillRows[index];
                    if (!row) return;
                    const name = row.querySelector('[data-skill-name]');
                    const score = row.querySelector('[data-skill-score]');
                    const level = row.querySelector('[data-skill-level]');
                    const progress = row.querySelector('[role="progressbar"]');
                    const bar = progress?.querySelector('span');
                    if (name) name.textContent = skill.name;
                    if (score) score.textContent = `${skill.score}%`;
                    if (level) level.textContent = skill.level;
                    progress?.setAttribute('aria-label', skill.name);
                    progress?.setAttribute('aria-valuenow', String(skill.score));
                    bar?.style.setProperty('--learner-progress', `${skill.score}%`);
                });

                period.activities.forEach((activity) => {
                    const item = document.querySelector(`[data-activity-summary][data-activity-id="${activity.id}"]`);
                    if (!item) return;
                    const label = item.querySelector('[data-activity-label]');
                    const value = item.querySelector('[data-activity-value]');
                    const change = item.querySelector('[data-activity-change]');
                    if (label) label.textContent = activity.label;
                    if (value) value.textContent = String(activity.value);
                    if (change) change.textContent = activity.change;
                });
            };

            statisticsSelect.addEventListener('change', () => {
                renderStatistics(statisticsSelect.value);
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
        profileForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = profileForm.querySelector('button[type="submit"]');
            const formData = new FormData(profileForm);
            const payload = {
                fullName: String(formData.get('fullName') || '').trim(),
                dateOfBirth: String(formData.get('dateOfBirth') || '').trim() || undefined,
                phone: String(formData.get('phone') || '').trim() || undefined,
                location: String(formData.get('location') || '').trim() || undefined,
                headline: String(formData.get('headline') || '').trim() || undefined,
                bio: String(formData.get('bio') || '').trim() || undefined,
            };

            profileForm.querySelectorAll('.learner-field__error').forEach((error) => {
                error.textContent = '';
            });
            profileForm.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
                input.removeAttribute('aria-invalid');
            });

            if (!payload.fullName) {
                const field = profileForm.elements.namedItem('fullName');
                const error = profileForm.querySelector('[data-error-for="fullName"]');
                if (error) error.textContent = 'Vui lòng nhập họ và tên.';
                field?.setAttribute('aria-invalid', 'true');
                field?.focus();
                return;
            }

            const mutationBackend = resolveMutationBackend(
                document.body?.dataset?.learnerSource || '',
                Boolean(global.TalentHubLearnerApi),
            );
            if (mutationBackend === 'server') {
                if (submitBtn) submitBtn.disabled = true;
                try {
                    const client = global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: '/api/v1' });
                    const res = await client.send('PATCH', '/students/me', payload);
                    if (res) {
                        const nameTarget = document.querySelector('[data-profile-name]');
                        if (nameTarget) nameTarget.textContent = payload.fullName;
                        const locTarget = document.querySelector('[data-profile-location]');
                        if (locTarget && payload.location) locTarget.textContent = payload.location;
                        closeModal(profileForm.closest('.learner-modal'));
                        showToast('Hồ sơ đã được cập nhật thành công.');
                    }
                } catch (err) {
                    showToast(err?.message || 'Không thể cập nhật hồ sơ.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
                return;
            }

            if (mutationBackend === 'unavailable') {
                showToast('Không thể cập nhật hồ sơ vì API chưa sẵn sàng.', 'error');
                return;
            }

            // Explicit mock mode only.
            const nameTarget = document.querySelector('[data-profile-name]');
            if (nameTarget) nameTarget.textContent = payload.fullName;
            const locTarget = document.querySelector('[data-profile-location]');
            if (locTarget && payload.location) locTarget.textContent = payload.location;
            closeModal(profileForm.closest('.learner-modal'));
            showToast('Hồ sơ đã được cập nhật trên giao diện.');
        });

        const shareForm = document.getElementById('learner-share-form');
        shareForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = shareForm.querySelector('button[type="submit"]');
            const formData = new FormData(shareForm);
            const sharedFields = formData.getAll('sharedFields[]');
            if (!sharedFields.includes('fullName')) {
                sharedFields.unshift('fullName');
            }
            const expiresInDays = Number(formData.get('expiresInDays')) || 30;

            if (submitBtn) submitBtn.disabled = true;
            try {
                const mutationBackend = resolveMutationBackend(
                    document.body?.dataset?.learnerSource || '',
                    Boolean(global.TalentHubLearnerApi),
                );
                if (mutationBackend === 'server') {
                    const client = global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: '/app/learner/api/v1' });
                    const res = await client.send('POST', '/profile-shares.php', { sharedFields, expiresInDays });
                    if (res && res.share) {
                        const resultBox = document.getElementById('learner-share-result');
                        const linkInput = document.getElementById('learner-share-link');
                        if (linkInput) {
                            linkInput.value = `${window.location.origin}${res.share.shareUrl}`;
                        }
                        if (resultBox) resultBox.style.display = 'block';
                        showToast('Đã tạo liên kết chia sẻ hồ sơ.');
                    }
                } else if (mutationBackend === 'mock') {
                    const resultBox = document.getElementById('learner-share-result');
                    const linkInput = document.getElementById('learner-share-link');
                    if (linkInput) {
                        const mockToken = global.crypto?.randomUUID?.() || String(Date.now());
                        linkInput.value = `${window.location.origin}/app/learner/shared-profile.php?token=mock-${mockToken}`;
                    }
                    if (resultBox) resultBox.style.display = 'block';
                    showToast('Đã tạo liên kết chia sẻ hồ sơ demo.');
                } else {
                    throw new Error('Không thể tạo liên kết vì API chưa sẵn sàng.');
                }
            } catch (err) {
                showToast(err?.message || 'Không thể tạo liên kết chia sẻ.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        const certForm = document.getElementById('learner-certificate-form');
        certForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = certForm.querySelector('button[type="submit"]');
            const formData = new FormData(certForm);
            const payload = {
                title: String(formData.get('title') || '').trim(),
                issuingOrganization: String(formData.get('issuingOrganization') || '').trim(),
                issueDate: String(formData.get('issueDate') || '').trim(),
                expiryDate: String(formData.get('expiryDate') || '').trim() || undefined,
                credentialId: String(formData.get('credentialId') || '').trim() || undefined,
                credentialUrl: String(formData.get('credentialUrl') || '').trim() || undefined,
            };

            if (!payload.title || !payload.issuingOrganization || !payload.issueDate) {
                showToast('Vui lòng điền đầy đủ các thông tin bắt buộc.', 'error');
                return;
            }

            if (submitBtn) submitBtn.disabled = true;
            try {
                const mutationBackend = resolveMutationBackend(
                    document.body?.dataset?.learnerSource || '',
                    Boolean(global.TalentHubLearnerApi),
                );
                if (mutationBackend === 'server') {
                    const client = global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: '/app/learner/api/v1' });
                    await client.send('POST', '/certificates.php', payload);
                    closeModal(certForm.closest('.learner-modal'));
                    showToast('Chứng chỉ đã được thêm thành công.');
                } else if (mutationBackend === 'mock') {
                    closeModal(certForm.closest('.learner-modal'));
                    showToast('Chứng chỉ demo đã được thêm.');
                } else {
                    throw new Error('Không thể lưu chứng chỉ vì API chưa sẵn sàng.');
                }
            } catch (err) {
                showToast(err?.message || 'Không thể lưu chứng chỉ.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        document.querySelector('[data-copy-profile]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const input = document.getElementById('learner-share-link');
            if (!input || !input.value) return;

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

    });
})(typeof window !== 'undefined' ? window : globalThis);
