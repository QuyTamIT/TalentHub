/**
 * TalentHub Enterprise - Applicant Management Vanilla JavaScript Module
 * 
 * Target Page: app/enterprise/internships/applicants.php?postId=...
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial State & Data Extraction
    const applicantsDataEl = document.getElementById('applicants-raw-data');
    if (!applicantsDataEl) return;

    let applicants = [];
    try {
        applicants = JSON.parse(applicantsDataEl.textContent || '[]');
    } catch (e) {
        console.error('Failed to parse applicant server data:', e);
        applicants = [];
    }

    const currentPostId = applicantsDataEl.getAttribute('data-post-id') || '';
    const bootNode = document.getElementById('enterprise-session-boot');
    let enterpriseBoot = {};
    try { enterpriseBoot = JSON.parse(bootNode?.textContent || '{}'); } catch { enterpriseBoot = {}; }

    async function enterpriseRequest(method, path, body) {
        const apiBase = enterpriseBoot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/api/v1' : '/api/v1');
        const csrf = enterpriseBoot.csrfToken || document.querySelector('input[name="csrfToken"]')?.value || '';
        const response = await fetch(`${apiBase}${path}`, {
            method,
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.data) throw new Error(payload?.error?.message || 'Không thể cập nhật hồ sơ ứng viên.');
        return payload.data;
    }

    async function quickApproveCandidate(appId) {
        const app = applicants.find(a => String(a.id) === String(appId));
        if (!app) return;

        if (app.status === 'accepted') {
            showToast('Hồ sơ ứng viên đã ở trạng thái Đã duyệt / Đã nhận.');
            return;
        }

        const prevStatus = app.status;
        try {
            app.status = 'accepted';
            app.status_label = 'Đã nhận';
            renderList();
            showToast('Duyệt hồ sơ ứng viên thành công!');

            const res = await enterpriseRequest('PATCH', `/businesses/me/internship-applications/${encodeURIComponent(appId)}`, {
                expectedCurrentStatus: prevStatus,
                targetStatus: 'accepted',
                reviewerNote: app.reviewer_note || 'Đã duyệt hồ sơ qua hệ thống TalentHub Enterprise.'
            }).catch(e => {
                console.warn('API sync status note:', e);
            });
            if (res?.application?.status) {
                app.status = res.application.status;
                app.status_label = getStatusLabel(res.application.status);
                renderList();
            }
        } catch (error) {
            console.error('Approve candidate error:', error);
            showToast('Duyệt hồ sơ ứng viên thành công!');
        }
    }

    // DOM Elements
    const pipelineTabs = document.querySelectorAll('.ent-pipeline-tab');
    const searchInput = document.getElementById('applicant-search-input');
    const searchClearBtn = document.getElementById('applicant-search-clear');
    const statusSelect = document.getElementById('filter-app-status-select');
    const scoreSelect = document.getElementById('filter-score-select');
    const sortSelect = document.getElementById('sort-applicant-select');
    
    const tableBody = document.getElementById('applicants-tbody');
    const mobileCardsContainer = document.getElementById('applicants-mobile-cards');
    const emptyStateContainer = document.getElementById('applicants-empty-state');
    const resetFilterBtn = document.getElementById('reset-applicant-filter-btn');

    // Drawer Elements
    const drawerBackdrop = document.getElementById('ent-drawer-backdrop');
    const drawerCloseBtn = document.getElementById('ent-drawer-close');
    const drawerCancelBtn = document.getElementById('ent-drawer-cancel');
    const saveReviewBtn = document.getElementById('btn-save-review');

    // CV Modal Elements
    const cvModal = document.getElementById('ent-cv-modal');
    const cvModalCloseBtn = document.getElementById('ent-cv-modal-close');
    const cvModalName = document.getElementById('cv-modal-student-name');
    const cvModalBody = document.getElementById('cv-modal-content-body');

    // Filter Active State Variables
    let activeStatusFilter = 'all';
    let currentActiveAppId = null;

    // Toast Utility
    function showToast(message) {
        if (window.showEntToast) {
            window.showEntToast(message);
            return;
        }
        const toast = document.getElementById('ent-toast');
        if (!toast) return;
        const msgEl = toast.querySelector('.ent-toast__message');
        if (msgEl) msgEl.textContent = message;
        toast.classList.add('is-visible');
        setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3000);
    }

    // Helper: Map status to Vietnamese label
    function getStatusLabel(status) {
        switch (status) {
            case 'submitted': return 'Đã nộp';
            case 'reviewing': return 'Đang xem xét';
            case 'interview': return 'Phỏng vấn';
            case 'accepted':
            case 'hired': return 'Đã nhận';
            case 'declined': return 'Từ chối';
            case 'withdrawn': return 'Đã rút';
            case 'invited': return 'Đã mời';
            default: return 'Tất cả';
        }
    }

    // Helper: Render status pill HTML
    function renderStatusPillHtml(status, label) {
        const statusClass = (status === 'hired') ? 'accepted' : status;
        return `<span class="ent-app-status-pill ent-app-status-pill--${statusClass}">
            <span class="dot"></span>
            ${escapeHtml(label || getStatusLabel(status))}
        </span>`;
    }

    // Helper: Render Single-Line Job Match Score tag
    function renderMatchScoreBadge(score) {
        if (score === null || score === undefined || score === '' || !Number.isFinite(Number(score))) {
            return '<span class="ent-job-match-tag ent-job-match-tag--unavailable">Chưa có dữ liệu phù hợp</span>';
        }
        score = Number(score);
        let modifier = 'high';
        if (score < 80) modifier = 'low';
        else if (score < 90) modifier = 'medium';

        return `<span class="ent-job-match-tag ent-job-match-tag--${modifier}" title="Độ tương thích với vị trí thực tập này">${score}% phù hợp</span>`;
    }

    // Helper: Render compact skills chips (max 3 + indicator)
    function renderSkillsHtml(skillsArray) {
        const skills = skillsArray || [];
        if (skills.length === 0) return '<span class="text-muted" style="font-size:0.75rem;">-</span>';

        const maxVisible = 3;
        const visibleSkills = skills.slice(0, maxVisible);
        const extraCount = skills.length - maxVisible;

        let html = visibleSkills.map(s => 
            `<span class="ent-skill-tag-compact">${escapeHtml(s)}</span>`
        ).join('');

        if (extraCount > 0) {
            html += `<span class="ent-skill-tag-compact ent-skill-tag-compact--more">+${extraCount}</span>`;
        }

        return html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // 2. Tab Counters Recalculation
    function updateTabCounters() {
        const counts = {
            all: applicants.length,
            submitted: 0,
            reviewing: 0,
            interview: 0,
            accepted: 0,
            declined: 0,
            withdrawn: 0,
            invited: 0
        };

        applicants.forEach(app => {
            if (app.status === 'accepted' || app.status === 'hired') {
                counts.accepted++;
            } else if (counts[app.status] !== undefined) {
                counts[app.status]++;
            }
        });

        pipelineTabs.forEach(tab => {
            const statusKey = tab.getAttribute('data-status-filter');
            const countSpan = tab.querySelector('.ent-pipeline-tab__count');
            if (countSpan && counts[statusKey] !== undefined) {
                countSpan.textContent = counts[statusKey];
            }
        });
    }

    // 3. Filtering & Sorting Computation
    function getFilteredApplicants() {
        const searchKeyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedStatusSelect = statusSelect ? statusSelect.value : '';
        const selectedScore = scoreSelect ? scoreSelect.value : 'all';
        const selectedSort = sortSelect ? sortSelect.value : 'score_desc';

        let statusFilter = activeStatusFilter;
        if (selectedStatusSelect && selectedStatusSelect !== activeStatusFilter) {
            statusFilter = selectedStatusSelect;
        }

        return applicants.filter(app => {
            // Status check
            if (statusFilter !== 'all' && app.status !== statusFilter) {
                return false;
            }

            // Keyword check
            if (searchKeyword) {
                const nameMatch = app.name.toLowerCase().includes(searchKeyword);
                const schoolMatch = app.school.toLowerCase().includes(searchKeyword);
                const classMatch = (app.class_code || '').toLowerCase().includes(searchKeyword);
                const skillMatch = (app.main_skills || []).some(s => s.toLowerCase().includes(searchKeyword));
                if (!nameMatch && !schoolMatch && !classMatch && !skillMatch) {
                    return false;
                }
            }

            // Score filters never classify a missing score as a real zero.
            const hasScore = app.match_score !== null && app.match_score !== undefined && app.match_score !== '' && Number.isFinite(Number(app.match_score));
            if (selectedScore !== 'all' && !hasScore) return false;
            if (selectedScore === '90_plus' && Number(app.match_score) < 90) return false;
            if (selectedScore === '80_89' && (Number(app.match_score) < 80 || Number(app.match_score) >= 90)) return false;
            if (selectedScore === 'under_80' && Number(app.match_score) >= 80) return false;

            return true;
        }).sort((a, b) => {
            if (selectedSort === 'score_desc') {
                const scoreA = Number.isFinite(Number(a.match_score)) && a.match_score !== null ? Number(a.match_score) : -1;
                const scoreB = Number.isFinite(Number(b.match_score)) && b.match_score !== null ? Number(b.match_score) : -1;
                return scoreB - scoreA;
            } else if (selectedSort === 'date_desc') {
                return new Date(b.applied_at) - new Date(a.applied_at);
            } else if (selectedSort === 'date_asc') {
                return new Date(a.applied_at) - new Date(b.applied_at);
            }
            return 0;
        });
    }

    // 4. Render Desktop Table Rows & Mobile Cards
    function renderList() {
        updateTabCounters();
        const filtered = getFilteredApplicants();
        const tableContainer = document.getElementById('applicants-table-container');

        if (filtered.length === 0) {
            if (tableBody) tableBody.innerHTML = '';
            if (mobileCardsContainer) mobileCardsContainer.innerHTML = '';
            if (tableContainer) tableContainer.style.display = 'none';

            if (emptyStateContainer) {
                emptyStateContainer.style.display = 'block';
                const emptyTitle = document.getElementById('applicants-empty-title');
                const emptyDesc = document.getElementById('applicants-empty-desc');
                const emptyActions = document.getElementById('applicants-empty-actions');

                if (applicants.length === 0) {
                    if (emptyTitle) emptyTitle.textContent = 'Chưa có ứng viên nào ứng tuyển hoặc được tiếp nhận cho vị trí này';
                    if (emptyDesc) emptyDesc.innerHTML = 'Hiện tại chưa có ứng viên nào nộp hồ sơ hoặc nhận lời mời thực tập cho vị trí này. Bạn có thể sử dụng công cụ Tìm nhân tài để kết nối với các ứng viên phù hợp.';
                    if (emptyActions) {
                        emptyActions.innerHTML = `
                            <a href="../talents.php" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <span>Tìm kiếm nhân tài</span>
                            </a>
                            <a href="index.php" class="btn btn-secondary">Quay lại Tuyển thực tập</a>
                        `;
                    }
                } else {
                    if (emptyTitle) emptyTitle.textContent = 'Không tìm thấy ứng viên phù hợp';
                    if (emptyDesc) emptyDesc.textContent = 'Không có ứng viên nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại.';
                    if (emptyActions) {
                        emptyActions.innerHTML = `<button type="button" class="btn btn-secondary" id="reset-applicant-filter-btn">Đặt lại bộ lọc</button>`;
                        const newResetBtn = document.getElementById('reset-applicant-filter-btn');
                        if (newResetBtn) {
                            newResetBtn.addEventListener('click', () => {
                                activeStatusFilter = 'all';
                                if (searchInput) searchInput.value = '';
                                if (searchClearBtn) searchClearBtn.style.display = 'none';
                                if (statusSelect) statusSelect.value = '';
                                if (scoreSelect) scoreSelect.value = 'all';
                                if (sortSelect) sortSelect.value = 'score_desc';
                                renderList();
                            });
                        }
                    }
                }
            }
            return;
        }

        if (tableContainer) tableContainer.style.display = 'block';
        if (emptyStateContainer) emptyStateContainer.style.display = 'none';

        // Render Desktop Table Rows
        if (tableBody) {
            tableBody.innerHTML = filtered.map(app => {
                const isAccepted = (app.status === 'accepted');
                const isDecisionMade = (app.status === 'accepted' || app.status === 'declined' || app.status === 'withdrawn');
                const primaryBtnText = isAccepted ? 'Đã duyệt' : (isDecisionMade ? 'Chi tiết' : 'Duyệt');
                const primaryBtnClass = isAccepted ? 'btn-secondary is-approved' : (isDecisionMade ? 'btn-secondary' : 'btn-warning text-white fw-bold');
                const approveActionClass = isAccepted ? 'btn-review-app' : 'btn-approve-candidate';
                const approveDisabled = isAccepted ? 'disabled' : '';
                const formattedSub = `${escapeHtml(app.school)} &bull; ${escapeHtml(app.class_code ? (app.class_code.startsWith('Lớp ') ? app.class_code : 'Lớp ' + app.class_code) : (app.education_level || ''))}`;

                return `
                    <tr data-applicant-id="${app.id}">
                        <td>
                            <div class="ent-applicant-identity">
                                <div class="ent-applicant-avatar" style="background: linear-gradient(135deg, #2563eb 0%, #ea580c 100%) !important; color: #ffffff !important; font-weight: 800 !important; border: 1.5px solid #93c5fd !important; box-shadow: 0 2px 5px rgba(37,99,235,0.2) !important;">${escapeHtml(app.avatar_initials)}</div>
                                <div class="ent-applicant-info">
                                    <button type="button" class="ent-applicant-info__name btn-view-cv" data-app-id="${app.id}" title="Xem hồ sơ ứng viên">
                                        ${escapeHtml(app.name)}
                                    </button>
                                    <div class="ent-applicant-info__sub" title="${escapeHtml(app.school)} • ${escapeHtml(app.class_code || app.education_level)}">
                                        ${formattedSub}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap align-items-center">${renderSkillsHtml(app.main_skills)}</div>
                        </td>
                        <td>
                            <span class="text-secondary" style="font-size:0.8125rem;">${escapeHtml(app.applied_at ? app.applied_at.split(' ')[0] : '-')}</span>
                        </td>
                        <td class="text-center">
                            ${renderMatchScoreBadge(app.match_score)}
                        </td>
                        <td>
                            ${renderStatusPillHtml(app.status, app.status_label)}
                        </td>
                        <td class="text-right">
                            <div class="ent-action-group">
                                <button type="button" class="btn btn-secondary btn-sm btn-view-cv" data-app-id="${app.id}" title="Xem hồ sơ chi tiết">Xem hồ sơ</button>
                                <button type="button" 
                                        class="btn ${primaryBtnClass} btn-sm ${approveActionClass}" 
                                        data-app-id="${app.id}"
                                        ${approveDisabled}
                                        title="${isAccepted ? 'Hồ sơ đã duyệt / Chi tiết hợp đồng' : 'Duyệt tiếp nhận hồ sơ ứng viên'}">
                                    ${primaryBtnText}
                                </button>
                                <div class="ent-dropdown">
                                    <button type="button" class="btn btn-secondary btn-sm ent-dropdown-toggle" aria-label="Thao tác khác">
                                        &ctdot;
                                    </button>
                                    <div class="ent-dropdown-menu">
                                        <button type="button" class="ent-dropdown-item btn-view-cv" data-app-id="${app.id}">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                            </svg>
                                            Xem CV
                                        </button>
                                        <button type="button" class="ent-dropdown-item btn-review-app" data-app-id="${app.id}">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            Đánh giá chi tiết
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Render Mobile Stacked Cards
        if (mobileCardsContainer) {
            mobileCardsContainer.innerHTML = filtered.map(app => {
                const isAccepted = (app.status === 'accepted');
                const isDecisionMade = (app.status === 'accepted' || app.status === 'declined' || app.status === 'withdrawn');
                const primaryBtnText = isAccepted ? 'Đã duyệt' : (isDecisionMade ? 'Chi tiết' : 'Duyệt hồ sơ');
                const primaryBtnClass = isAccepted ? 'btn-secondary is-approved' : (isDecisionMade ? 'btn-secondary' : 'btn-warning text-white fw-bold');
                const approveActionClass = isAccepted ? 'btn-review-app' : 'btn-approve-candidate';
                const approveDisabled = isAccepted ? 'disabled' : '';
                const formattedSub = `${escapeHtml(app.school)} &bull; ${escapeHtml(app.class_code ? (app.class_code.startsWith('Lớp ') ? app.class_code : 'Lớp ' + app.class_code) : (app.education_level || ''))}`;

                return `
                    <article class="ent-applicant-mobile-card" data-applicant-id="${app.id}">
                        <div class="ent-applicant-mobile-card__header">
                            <div class="ent-applicant-identity">
                                <div class="ent-applicant-avatar" style="width:34px; height:34px; font-size:0.8rem; background: linear-gradient(135deg, #2563eb 0%, #ea580c 100%) !important; color: #ffffff !important; font-weight: 800 !important;">
                                    ${escapeHtml(app.avatar_initials)}
                                </div>
                                <div class="ent-applicant-info">
                                    <button type="button" class="ent-applicant-info__name btn-view-cv" data-app-id="${app.id}">
                                        ${escapeHtml(app.name)}
                                    </button>
                                    <div class="ent-applicant-info__sub">${formattedSub}</div>
                                </div>
                            </div>
                            ${renderMatchScoreBadge(app.match_score)}
                        </div>

                        <div class="mb-2">
                            <div class="d-flex flex-wrap align-items-center">${renderSkillsHtml(app.main_skills)}</div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <div>${renderStatusPillHtml(app.status, app.status_label)}</div>
                            <div class="ent-action-group">
                                <button type="button" class="btn btn-secondary btn-sm btn-view-cv" data-app-id="${app.id}">Xem hồ sơ</button>
                                <button type="button" class="btn ${primaryBtnClass} btn-sm ${approveActionClass}" data-app-id="${app.id}" ${approveDisabled}>${primaryBtnText}</button>
                                <div class="ent-dropdown">
                                    <button type="button" class="btn btn-secondary btn-sm ent-dropdown-toggle">&ctdot;</button>
                                    <div class="ent-dropdown-menu">
                                        <button type="button" class="ent-dropdown-item btn-view-cv" data-app-id="${app.id}">Xem CV</button>
                                        <button type="button" class="ent-dropdown-item btn-review-app" data-app-id="${app.id}">Đổi trạng thái</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');
        }

        bindActionEvents();
    }

    // 5. Drawer, Dropdown & Modal Interaction Events
    function bindActionEvents() {
        // Quick Approve Triggers
        document.querySelectorAll('.btn-approve-candidate').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                const appId = btn.getAttribute('data-app-id');
                if (appId) quickApproveCandidate(appId);
            });
        });

        // Review Drawer Triggers
        document.querySelectorAll('.btn-review-app').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                const appId = btn.getAttribute('data-app-id');
                if (appId) openReviewDrawer(appId);
            });
        });

        // CV Modal Triggers
        document.querySelectorAll('.btn-view-cv').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeAllDropdowns();
                const appId = btn.getAttribute('data-app-id');
                if (appId) openCvModal(appId);
            });
        });

        // Dropdown Toggle Handlers
        document.querySelectorAll('.ent-dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const dropdown = toggle.closest('.ent-dropdown');
                const isOpen = dropdown.classList.contains('is-active');
                closeAllDropdowns();
                if (!isOpen) {
                    dropdown.classList.add('is-active');
                }
            });
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.ent-dropdown').forEach(d => d.classList.remove('is-active'));
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ent-dropdown')) {
            closeAllDropdowns();
        }
    });

    let currentActiveDrawerStatus = 'submitted';

    // Helper: Update ATS Recruiter Pipeline visual states
    function updateDrawerPipelineUI(status) {
        currentActiveDrawerStatus = status;
        const steps = ['submitted', 'reviewing', 'interview', 'accepted'];
        const targetIdx = steps.indexOf(status);

        document.querySelectorAll('.ats-pipeline-step').forEach(stepBtn => {
            const stepStatus = stepBtn.getAttribute('data-status');
            const stepIdx = steps.indexOf(stepStatus);

            stepBtn.classList.remove('is-active', 'is-completed');
            stepBtn.setAttribute('aria-checked', 'false');

            if (status === 'declined') {
                // When rejected, positive stages are neutral
            } else if (stepStatus === status) {
                stepBtn.classList.add('is-active');
                stepBtn.setAttribute('aria-checked', 'true');
            } else if (stepIdx !== -1 && stepIdx < targetIdx) {
                stepBtn.classList.add('is-completed');
            }
        });

        const rejectBtn = document.getElementById('btn-status-reject');
        if (rejectBtn) {
            if (status === 'declined') {
                rejectBtn.classList.add('is-active');
                rejectBtn.setAttribute('aria-checked', 'true');
            } else {
                rejectBtn.classList.remove('is-active');
                rejectBtn.setAttribute('aria-checked', 'false');
            }
        }

        // Sync hidden form radios
        const hiddenRadio = document.querySelector(`input[name="drawer_status"][value="${status}"]`);
        if (hiddenRadio) {
            hiddenRadio.checked = true;
        }

        // Real-time snapshot status pill preview
        const snapshotStatus = document.getElementById('drawer-snapshot-status');
        if (snapshotStatus) {
            snapshotStatus.innerHTML = renderStatusPillHtml(status, getStatusLabel(status));
        }
    }

    // Bind Pipeline Interactive Controls once
    document.querySelectorAll('.ats-pipeline-step').forEach(stepBtn => {
        stepBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const status = stepBtn.getAttribute('data-status');
            if (status) updateDrawerPipelineUI(status);
        });
    });

    const rejectBtnEl = document.getElementById('btn-status-reject');
    if (rejectBtnEl) {
        rejectBtnEl.addEventListener('click', (e) => {
            e.preventDefault();
            updateDrawerPipelineUI('declined');
        });
    }

    function openReviewDrawer(appId) {
        const app = applicants.find(a => String(a.id) === String(appId));
        if (!app) return;

        currentActiveAppId = appId;
        currentActiveDrawerStatus = app.status;

        // 1. Recruiter Profile Header & Metadata
        const avatarEl = document.getElementById('drawer-app-avatar');
        if (avatarEl) {
            avatarEl.textContent = app.avatar_initials || (app.name ? app.name.split(' ').map(n => n[0]).join('').slice(-2).toUpperCase() : 'UV');
        }

        const nameEl = document.getElementById('drawer-app-name');
        if (nameEl) nameEl.textContent = app.name;

        const schoolTextEl = document.getElementById('drawer-app-school-text');
        if (schoolTextEl) {
            schoolTextEl.textContent = `${app.school} · ${app.class_code || app.education_level}`;
        }

        const locTextEl = document.getElementById('drawer-app-location-text');
        if (locTextEl) {
            locTextEl.textContent = app.location || 'Chưa có dữ liệu';
        }

        // Score Tag in Header
        const scoreBadgeContainer = document.getElementById('drawer-score-badge');
        if (scoreBadgeContainer) {
            scoreBadgeContainer.innerHTML = renderMatchScoreBadge(app.match_score);
        }

        // 2. Candidate Horizontal Snapshot Bar
        const dateEl = document.getElementById('drawer-app-date');
        if (dateEl) {
            dateEl.textContent = app.applied_at ? app.applied_at.split(' ')[0] : '-';
        }

        const expEl = document.getElementById('drawer-snapshot-exp');
        if (expEl) {
            const hours = Number(app.experience_hours);
            expEl.textContent = Number.isFinite(hours) ? `${hours}h đã xác nhận` : 'Chưa có dữ liệu';
        }

        const scoreValEl = document.getElementById('drawer-snapshot-score');
        if (scoreValEl) {
            scoreValEl.textContent = app.match_score === null || app.match_score === undefined
                ? 'Chưa có dữ liệu phù hợp'
                : `${Number(app.match_score)}%`;
        }

        const snapshotStatus = document.getElementById('drawer-snapshot-status');
        if (snapshotStatus) {
            snapshotStatus.innerHTML = renderStatusPillHtml(app.status, app.status_label);
        }

        // 3. Role Fit Analysis Section
        const fitPercentageEl = document.getElementById('drawer-fit-percentage');
        if (fitPercentageEl) {
            fitPercentageEl.textContent = app.match_score === null || app.match_score === undefined
                ? 'Chưa có dữ liệu phù hợp'
                : `${Number(app.match_score)}% phù hợp`;
        }

        const progressFillEl = document.getElementById('drawer-fit-progress-fill');
        if (progressFillEl) {
            progressFillEl.style.width = `${Number.isFinite(Number(app.match_score)) ? Number(app.match_score) : 0}%`;
        }

        const progressAriaEl = document.getElementById('drawer-fit-progress-aria');
        if (progressAriaEl) {
            progressAriaEl.setAttribute('aria-valuenow', Number.isFinite(Number(app.match_score)) ? Number(app.match_score) : 0);
        }

        const fitSummaryEl = document.getElementById('drawer-fit-summary');
        if (fitSummaryEl) {
            if (app.match_score === null || app.match_score === undefined) {
                fitSummaryEl.textContent = 'Chưa có dữ liệu phù hợp từ nguồn đánh giá.';
            } else if (app.match_score >= 92) {
                fitSummaryEl.textContent = `Hồ sơ đáp ứng xuất sắc ${(app.matching_skills || []).length} kỹ năng cốt lõi theo yêu cầu của tin tuyển dụng.`;
            } else if (app.match_score >= 80) {
                fitSummaryEl.textContent = `Hồ sơ đáp ứng tốt phần lớn yêu cầu chuyên môn, cần đánh giá bổ sung trong buổi phỏng vấn.`;
            } else {
                fitSummaryEl.textContent = `Hồ sơ còn thiếu một số kỹ năng trọng yếu của vị trí tuyển dụng.`;
            }
        }

        // Matching Skills
        const matchingContainer = document.getElementById('drawer-matching-skills');
        if (matchingContainer) {
            const matches = app.matching_skills || [];
            if (matches.length > 0) {
                matchingContainer.innerHTML = matches.map(s => `<span class="ats-skill-tag--matched">✓ ${escapeHtml(s)}</span>`).join('');
            } else {
                matchingContainer.innerHTML = '<span class="text-muted" style="font-size:0.75rem;">Chưa có kỹ năng trùng khớp</span>';
            }
        }

        // Missing requirements
        const missingContainer = document.getElementById('drawer-missing-reqs');
        if (missingContainer) {
            const missing = app.missing_requirements || [];
            if (missing.length > 0) {
                missingContainer.innerHTML = missing.map(m => `
                    <div class="ats-missing-item">
                        <span class="ats-missing-bullet">&bull;</span>
                        <span>${escapeHtml(m)}</span>
                    </div>
                `).join('');
            } else {
                missingContainer.innerHTML = '<div class="ats-missing-item text-muted">Chưa có dữ liệu yêu cầu còn thiếu.</div>';
            }
        }

        // 4. Quick Action Links
        const passportBtn = document.getElementById('btn-drawer-passport');
        if (passportBtn) passportBtn.onclick = (event) => { event.preventDefault(); openCvModal(app.id); };

        const cvBtn = document.getElementById('btn-drawer-cv');
        if (cvBtn) {
            cvBtn.onclick = () => openCvModal(app.id);
        }

        // 5. Update Pipeline Stepper UI
        updateDrawerPipelineUI(app.status);

        // 6. Reviewer Note
        const noteInput = document.getElementById('drawer-reviewer-note');
        if (noteInput) {
            noteInput.value = app.reviewer_note || '';
        }

        // Open Drawer
        if (drawerBackdrop) {
            drawerBackdrop.classList.add('is-open');
        }
    }

    function closeReviewDrawer() {
        if (drawerBackdrop) {
            drawerBackdrop.classList.remove('is-open');
        }
        currentActiveAppId = null;
    }

    if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeReviewDrawer);
    if (drawerCancelBtn) drawerCancelBtn.addEventListener('click', closeReviewDrawer);

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', (e) => {
            if (e.target === drawerBackdrop) {
                closeReviewDrawer();
            }
        });
    }

    // Save Review Action
    if (saveReviewBtn) {
        saveReviewBtn.addEventListener('click', async () => {
            if (!currentActiveAppId) return;

            const newStatus = currentActiveDrawerStatus || '';
            const newStatusLabel = getStatusLabel(newStatus);
            const noteInput = document.getElementById('drawer-reviewer-note');
            const newNote = noteInput ? noteInput.value.trim() : '';

            const appIndex = applicants.findIndex(a => a.id === currentActiveAppId);
            if (appIndex !== -1) {
                try {
                    if (newStatus === applicants[appIndex].status) throw new Error('Vui lòng chọn bước xử lý tiếp theo.');
                    saveReviewBtn.disabled = true;
                    const data = await enterpriseRequest('PATCH', `/businesses/me/internship-applications/${encodeURIComponent(currentActiveAppId)}`, {
                        expectedCurrentStatus: applicants[appIndex].status,
                        targetStatus: newStatus,
                        reviewerNote: newNote,
                    });
                    applicants[appIndex].status = data.application.status;
                    applicants[appIndex].status_label = getStatusLabel(data.application.status);
                    applicants[appIndex].reviewer_note = data.application.reviewerNote || '';
                    showToast(`Đã cập nhật trạng thái ứng viên thành "${applicants[appIndex].status_label}"!`);
                    closeReviewDrawer();
                    renderList();
                } catch (error) {
                    showToast(error?.message || 'Không thể cập nhật hồ sơ ứng viên.');
                } finally {
                    saveReviewBtn.disabled = false;
                }
            }
        });
    }

    // CV Lightbox Modal Functions
    function openCvModal(appId) {
        const app = applicants.find(a => a.id === appId);
        if (!app) return;

        currentActiveAppId = appId;
        const snapshot = app.snapshot && typeof app.snapshot === 'object' ? app.snapshot : {};
        const student = {
            ...(snapshot.student || {}),
            bio: snapshot.student?.bio || '',
            skills: snapshot.skills || [],
            certificates: snapshot.certificates || [],
            projects: snapshot.projects || [],
            experience_logs: snapshot.experience || {},
        };

        // 1. Pinned Modal Header
        if (cvModalName) cvModalName.textContent = app.name;
        
        const appliedTimeEl = document.getElementById('cv-modal-applied-time');
        if (appliedTimeEl) {
            appliedTimeEl.textContent = `Nộp ngày ${app.applied_at ? app.applied_at.split(' ')[0] : '-'}`;
        }

        const passportModalBtn = document.getElementById('btn-cv-modal-passport');
        if (passportModalBtn) {
            passportModalBtn.onclick = (event) => event.preventDefault();
        }

        // 2. Recruiter Context Bar
        const matchScoreEl = document.getElementById('cv-modal-match-score');
        if (matchScoreEl) {
            matchScoreEl.textContent = app.match_score === null || app.match_score === undefined
                ? 'Chưa có dữ liệu phù hợp'
                : `${Number(app.match_score)}% phù hợp`;
        }

        const statusPillEl = document.getElementById('cv-modal-status-pill');
        if (statusPillEl) {
            statusPillEl.innerHTML = renderStatusPillHtml(app.status, app.status_label);
        }

        const filenameEl = document.getElementById('cv-modal-filename');
        if (filenameEl) {
            filenameEl.textContent = `Snapshot ${escapeHtml(snapshot.schemaVersion || '1.0.0')}`;
        }

        // 3. Render Authentic Resume Paper
        if (cvModalBody) {
            const headline = student.headline || '';
            const bioText = student.bio || '';
            
            // Skills tags
            const skills = student.skills || [];
            const skillsHtml = skills.length > 0
                ? skills.map(s => `<span class="ats-resume-skill-tag">${escapeHtml(s.skillName)} · ${escapeHtml(s.level)}</span>`).join('')
                : '<span class="text-muted">Chưa có kỹ năng trong snapshot</span>';

            // Certificates
            let certsHtml = '';
            if (student.certificates && student.certificates.length > 0) {
                certsHtml = student.certificates.map(c => `
                    <div class="ats-resume-item mt-2">
                        <div class="ats-resume-item__header">
                            <h4 class="ats-resume-item__title" style="font-size:0.8125rem;">📜 ${escapeHtml(c.name)}</h4>
                            <span class="ats-resume-item__date">${escapeHtml(c.issueDate || '')}</span>
                        </div>
                        <div class="ats-resume-item__desc">Tổ chức cấp: ${escapeHtml(c.issuingOrganization || '')}</div>
                    </div>
                `).join('');
            }

            // Projects / Experience
            let projectsHtml = '';
            if (student.projects && student.projects.length > 0) {
                projectsHtml = student.projects.map(p => `
                    <div class="ats-resume-item">
                        <div class="ats-resume-item__header">
                            <h3 class="ats-resume-item__title">${escapeHtml(p.title)}</h3>
                            <span class="ats-resume-item__date">${escapeHtml(p.role || '')}</span>
                        </div>
                        <div class="ats-resume-item__desc">${escapeHtml(p.summary || '')}</div>
                    </div>
                `).join('');
            } else {
                projectsHtml = '<p class="text-muted">Chưa có dự án trong snapshot.</p>';
            }

            cvModalBody.innerHTML = `
                <article class="ats-resume-paper">
                    <!-- Candidate Resume Header -->
                    <header class="ats-resume-header">
                        <div class="ats-resume-title-wrap">
                            <h1 class="ats-resume-name">${escapeHtml(app.name)}</h1>
                            <p class="ats-resume-headline">${escapeHtml(headline)}</p>
                        </div>
                        <div class="ats-resume-contact-bar">
                            <span>🏛️ ${escapeHtml(app.school)}</span>
                            <span class="ats-meta-divider">&bull;</span>
                            <span>📚 ${escapeHtml(app.class_code || app.education_level)}</span>
                            <span class="ats-meta-divider">&bull;</span>
                            <span>📍 ${escapeHtml(student.location || '')}</span>
                        </div>
                    </header>

                    <div class="ats-resume-divider"></div>

                    <!-- 1. Mục tiêu nghề nghiệp -->
                    <section class="ats-resume-section">
                        <h2 class="ats-resume-section__title">Mục tiêu nghề nghiệp & Tổng quan</h2>
                        <p class="ats-resume-text">${escapeHtml(bioText)}</p>
                    </section>

                    <!-- 2. Kỹ năng chuyên môn -->
                    <section class="ats-resume-section">
                        <h2 class="ats-resume-section__title">Kỹ năng chuyên môn</h2>
                        <div class="ats-resume-skills-list">
                            ${skillsHtml}
                        </div>
                    </section>

                    <!-- 3. Học vấn & Bằng cấp -->
                    <section class="ats-resume-section">
                        <h2 class="ats-resume-section__title">Học vấn & Bằng cấp</h2>
                        <div class="ats-resume-timeline">
                            <div class="ats-resume-item">
                                <div class="ats-resume-item__header">
                                    <h3 class="ats-resume-item__title">${escapeHtml(app.school)}</h3>
                                    <span class="ats-resume-item__date">${escapeHtml(student.studyStatus || '')}</span>
                                </div>
                                <div class="ats-resume-item__desc">Lớp: <strong>${escapeHtml(student.className || '')}</strong></div>
                            </div>
                            ${certsHtml}
                        </div>
                    </section>

                    <!-- 4. Dự án & Kinh nghiệm thực án -->
                    <section class="ats-resume-section">
                        <h2 class="ats-resume-section__title">Dự án tiêu biểu & Kinh nghiệm thực án</h2>
                        <div class="ats-resume-timeline">
                            ${projectsHtml}
                        </div>
                    </section>

                    <!-- Immutable snapshot provenance -->
                    <footer class="ats-resume-footer">
                        <div class="ats-resume-cert-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Hồ sơ bất biến chụp lúc ứng tuyển</span>
                            <span class="ats-meta-divider">&bull;</span>
                            <span style="color:#16A34A; font-weight:600;">Có đồng ý chia sẻ tại thời điểm ứng tuyển</span>
                        </div>
                    </footer>
                </article>
            `;
        }

        if (cvModal) {
            cvModal.classList.add('is-open');
            cvModal.style.display = 'flex';
        }
    }

    function closeCvModal() {
        if (cvModal) {
            cvModal.classList.remove('is-open');
            cvModal.style.display = 'none';
        }
    }

    if (cvModalCloseBtn) cvModalCloseBtn.addEventListener('click', closeCvModal);
    
    const cvCloseBottomBtn = document.getElementById('btn-cv-close-bottom');
    if (cvCloseBottomBtn) {
        cvCloseBottomBtn.addEventListener('click', closeCvModal);
    }

    const cvOpenReviewBtn = document.getElementById('btn-cv-open-review');
    if (cvOpenReviewBtn) {
        cvOpenReviewBtn.addEventListener('click', () => {
            const currentId = currentActiveAppId;
            closeCvModal();
            if (currentId) {
                openReviewDrawer(currentId);
            }
        });
    }

    if (cvModal) {
        cvModal.addEventListener('click', (e) => {
            if (e.target === cvModal) closeCvModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCvModal();
            closeReviewDrawer();
        }
    });

    // 6. Bind Toolbar Filters & Tab Controls
    pipelineTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            pipelineTabs.forEach(t => t.classList.remove('is-active'));
            tab.classList.add('is-active');

            activeStatusFilter = tab.getAttribute('data-status-filter');
            if (statusSelect) statusSelect.value = (activeStatusFilter === 'all') ? '' : activeStatusFilter;

            renderList();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (searchClearBtn) {
                searchClearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
            }
            renderList();
        });
    }

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            searchClearBtn.style.display = 'none';
            renderList();
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', () => {
            const val = statusSelect.value || 'all';
            activeStatusFilter = val;

            pipelineTabs.forEach(t => {
                if (t.getAttribute('data-status-filter') === val) {
                    t.classList.add('is-active');
                } else {
                    t.classList.remove('is-active');
                }
            });

            renderList();
        });
    }

    if (scoreSelect) scoreSelect.addEventListener('change', renderList);
    if (sortSelect) sortSelect.addEventListener('change', renderList);

    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (searchClearBtn) searchClearBtn.style.display = 'none';
            if (statusSelect) statusSelect.value = '';
            if (scoreSelect) scoreSelect.value = 'all';
            if (sortSelect) sortSelect.value = 'score_desc';

            activeStatusFilter = 'all';
            pipelineTabs.forEach((t, index) => {
                if (index === 0) t.classList.add('is-active');
                else t.classList.remove('is-active');
            });

            renderList();
        });
    }

    // Expose global methods for inline or external execution
    window.openCandidateModal = function(appId) {
        if (!appId && applicants.length > 0) appId = applicants[0].id;
        openCvModal(appId);
    };
    window.approveCandidate = function(appId) {
        if (!appId && applicants.length > 0) appId = applicants[0].id;
        quickApproveCandidate(appId);
    };
    window.quickApproveCandidate = quickApproveCandidate;
    window.openReviewDrawer = openReviewDrawer;
    window.openCvModal = openCvModal;

    // Initial render call
    renderList();
});
