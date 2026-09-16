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
        if (!response.ok || !payload?.data) throw new Error(payload?.error?.message || (method === 'GET' ? 'Không thể tải hồ sơ ứng viên.' : 'Không thể cập nhật hồ sơ ứng viên.'));
        return payload.data;
    }

    async function quickApproveCandidate(appId) {
        const app = applicants.find(a => String(a.id) === String(appId));
        if (!app) return;

        if (app.status === 'accepted' || app.status === 'hired') {
            showToast('Hồ sơ ứng viên đã ở trạng thái Đã duyệt / Đã nhận.');
            return;
        }

        const prevStatus = app.status;
        const prevStatusLabel = app.status_label;
        const btn = document.querySelector(`button.btn-approve-candidate[data-app-id="${appId}"]`);
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Đang duyệt...';
        }

        try {
            const res = await enterpriseRequest('PATCH', `/businesses/me/internship-applications/${encodeURIComponent(appId)}`, {
                expectedCurrentStatus: prevStatus,
                targetStatus: 'accepted',
                reviewerNote: app.reviewer_note || 'Đã duyệt hồ sơ qua hệ thống FTalentHub Enterprise.'
            });

            const newStatus = res?.application?.status || 'accepted';
            app.status = newStatus;
            app.status_label = getStatusLabel(newStatus);
            if (res?.application?.reviewerNote) {
                app.reviewer_note = res.application.reviewerNote;
            }
            renderList();
            showToast('Duyệt tiếp nhận ứng viên thành công!');
        } catch (error) {
            console.error('Approve candidate error:', error);
            app.status = prevStatus;
            app.status_label = prevStatusLabel;
            renderList();
            showToast(error?.message || 'Không thể duyệt hồ sơ ứng viên.');
        }
    }

    // DOM Elements
    const pipelineTabs = document.querySelectorAll('.ent-pipeline-tab');
    const searchInput = document.getElementById('applicant-search-input');
    const searchClearBtn = document.getElementById('applicant-search-clear');
    const statusSelect = document.getElementById('filter-app-status-select');
    const scoreSelect = document.getElementById('filter-score-select');
    const sortSelect = document.getElementById('sort-applicant-select');
    
    const applicantList = document.getElementById('applicants-list');
    const resultSummary = document.getElementById('applicants-result-summary');
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

    // 4. Render the responsive applicant list
    function renderList() {
        updateTabCounters();
        const filtered = getFilteredApplicants();
        const listContainer = document.getElementById('applicants-list-container');
        if (resultSummary) resultSummary.textContent = `Hiển thị ${filtered.length} / ${applicants.length} ứng viên`;

        if (filtered.length === 0) {
            if (applicantList) applicantList.innerHTML = '';
            if (listContainer) listContainer.style.display = 'none';

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
                            <a href="../talents.php" class="applicants-action applicants-action--primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <span>Tìm kiếm nhân tài</span>
                            </a>
                            <a href="index.php" class="applicants-action applicants-action--quiet">Quay lại Tuyển thực tập</a>
                        `;
                    }
                } else {
                    if (emptyTitle) emptyTitle.textContent = 'Không tìm thấy ứng viên phù hợp';
                    if (emptyDesc) emptyDesc.textContent = 'Không có ứng viên nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại.';
                    if (emptyActions) {
                        emptyActions.innerHTML = `<button type="button" class="applicants-action applicants-action--quiet" id="reset-applicant-filter-btn">Đặt lại bộ lọc</button>`;
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

        if (listContainer) listContainer.style.display = 'block';
        if (emptyStateContainer) emptyStateContainer.style.display = 'none';

        // Keep every candidate in one DOM representation at every viewport size.
        if (applicantList) {
            applicantList.innerHTML = filtered.map(app => {
                const isAccepted = (app.status === 'accepted');
                const isDecisionMade = (app.status === 'accepted' || app.status === 'declined' || app.status === 'withdrawn');
                const primaryBtnText = isAccepted ? 'Đã duyệt' : (isDecisionMade ? 'Chi tiết' : 'Duyệt');
                const approveActionClass = isAccepted ? 'btn-review-app' : 'btn-approve-candidate';
                const approveDisabled = isAccepted ? 'disabled' : '';
                const classLabel = app.class_code ? (app.class_code.startsWith('Lớp ') ? app.class_code : 'Lớp ' + app.class_code) : (app.education_level || '');

                return `
                    <article class="applicant-entry" role="listitem" data-applicant-id="${app.id}" aria-label="${escapeHtml(app.name)}">
                        <div class="applicant-entry__identity">
                            <div class="applicant-entry__avatar" aria-hidden="true">
                                ${app.avatar_url
                                    ? `<img src="${escapeHtml(app.avatar_url)}" alt="">`
                                    : escapeHtml(app.avatar_initials)
                                }
                            </div>
                            <div class="applicant-entry__person">
                                <h3 class="applicant-entry__name">
                                    <button type="button" class="btn-view-cv" data-app-id="${app.id}" title="Xem hồ sơ ứng viên">${escapeHtml(app.name)}</button>
                                </h3>
                                <p class="applicant-entry__school">${escapeHtml(app.school)}</p>
                                <p class="applicant-entry__class">${escapeHtml(classLabel)}</p>
                                <p class="applicant-entry__date"><span>Ngày ứng tuyển</span> ${escapeHtml(app.applied_at ? app.applied_at.split(' ')[0] : '-')}</p>
                            </div>
                        </div>
                        <div class="applicant-entry__details">
                            <div class="applicant-entry__skills">
                                <span class="applicants-label">Kỹ năng chính</span>
                                <div class="applicants-skills">${renderSkillsHtml(app.main_skills)}</div>
                            </div>
                            <dl class="applicant-entry__assessment">
                                <div>
                                    <dt>Độ phù hợp</dt>
                                    <dd>${renderMatchScoreBadge(app.match_score)}</dd>
                                </div>
                                <div>
                                    <dt>Trạng thái</dt>
                                    <dd>${renderStatusPillHtml(app.status, app.status_label)}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="applicant-entry__actions">
                            <button type="button" class="applicants-action applicants-action--primary btn-view-cv" data-app-id="${app.id}">Xem hồ sơ</button>
                            <div class="applicant-entry__secondary-actions">
                                <button type="button" class="applicants-action applicants-action--quiet ${approveActionClass}" data-app-id="${app.id}" ${approveDisabled}
                                    title="${isAccepted ? 'Hồ sơ đã duyệt / Chi tiết hợp đồng' : 'Duyệt tiếp nhận hồ sơ ứng viên'}">${primaryBtnText}</button>
                                <div class="ent-dropdown">
                                    <button type="button" class="applicants-action applicants-action--icon ent-dropdown-toggle" aria-label="Thao tác khác" aria-haspopup="menu" aria-expanded="false">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
                                    </button>
                                    <div class="ent-dropdown-menu" role="menu">
                                        <button type="button" class="ent-dropdown-item btn-view-cv" role="menuitem" data-app-id="${app.id}">Xem CV</button>
                                        <button type="button" class="ent-dropdown-item btn-review-app" role="menuitem" data-app-id="${app.id}">Đánh giá chi tiết</button>
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
                    const menu = dropdown.querySelector('.ent-dropdown-menu');
                    if (menu && dropdown.closest('.ent-applicants-page')) {
                        const anchor = toggle.getBoundingClientRect();
                        const menuHeight = menu.offsetHeight;
                        dropdown.classList.toggle('opens-above',
                            anchor.bottom + menuHeight + 8 > document.documentElement.clientHeight
                            && anchor.top > menuHeight + 8);
                    }
                }
                toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.ent-dropdown').forEach(d => {
            d.classList.remove('is-active');
            d.querySelector('.ent-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
        });
    }

    // Dismiss list menus when the viewport or scroll position changes.
    window.addEventListener('resize', closeAllDropdowns);
    document.addEventListener('scroll', (event) => {
        if (!event.target.closest?.('.ent-dropdown-menu')) closeAllDropdowns();
    }, true);
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const toggle = document.querySelector('.ent-applicants-page .ent-dropdown.is-active .ent-dropdown-toggle');
        closeAllDropdowns();
        toggle?.focus();
    });

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
            if (app.avatar_url) {
                avatarEl.innerHTML = `<img src="${escapeHtml(app.avatar_url)}" alt="${escapeHtml(app.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">`;
            } else {
                avatarEl.textContent = app.avatar_initials || (app.name ? app.name.split(' ').map(n => n[0]).join('').slice(-2).toUpperCase() : 'UV');
            }
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

        // Student Cover Message (if present)
        const studentMsgSec = document.getElementById('drawer-student-message-section');
        const studentMsgEl = document.getElementById('drawer-student-message');
        if (studentMsgSec && studentMsgEl) {
            if (app.message && app.message.trim() !== '') {
                studentMsgEl.textContent = app.message;
                studentMsgSec.style.display = 'block';
            } else {
                studentMsgSec.style.display = 'none';
            }
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
            const hours = app.experience_hours === null || app.experience_hours === undefined ? NaN : Number(app.experience_hours);
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
            drawerBackdrop.hidden = false;
            drawerBackdrop.classList.add('is-open');
        }
    }

    function closeReviewDrawer() {
        if (drawerBackdrop) {
            drawerBackdrop.classList.remove('is-open');
            window.setTimeout(() => {
                if (!drawerBackdrop.classList.contains('is-open')) {
                    drawerBackdrop.hidden = true;
                }
            }, 280);
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
                    const currentStatus = applicants[appIndex].status;
                    const currentNote = applicants[appIndex].reviewer_note || '';
                    if (newStatus === currentStatus && newNote === currentNote) {
                        showToast('Không có thay đổi nào để lưu.');
                        closeReviewDrawer();
                        return;
                    }

                    saveReviewBtn.disabled = true;
                    saveReviewBtn.textContent = 'Đang lưu...';
                    const data = await enterpriseRequest('PATCH', `/businesses/me/internship-applications/${encodeURIComponent(currentActiveAppId)}`, {
                        expectedCurrentStatus: currentStatus,
                        targetStatus: newStatus || currentStatus,
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
                    saveReviewBtn.textContent = 'Lưu đánh giá';
                }
            }
        });
    }

    // PHP renders the same Passport template used by Student, from the stored snapshot.
    // Isolate its existing CSS from Enterprise; never navigate to the live Student route.
    function renderApplicationCv(html) {
        const frame = document.createElement('iframe');
        frame.title = 'CV / Talent Passport đã lưu khi ứng tuyển';
        frame.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
        frame.referrerPolicy = 'no-referrer';
        frame.className = 'applicant-cv-frame';
        frame.addEventListener('load', () => {
            frame.contentDocument?.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeCvModal();
            });
        });
        frame.srcdoc = html;
        cvModalBody.replaceChildren(frame);
    }

    let cvRequestVersion = 0;
    let cvReturnFocus = null;

    async function openCvModal(appId) {
        const app = applicants.find(a => a.id === appId);
        if (!app || !cvModal || !cvModalBody) return;
        const requestVersion = ++cvRequestVersion;
        if (!cvModal.classList.contains('is-open')) cvReturnFocus = document.activeElement;
        currentActiveAppId = appId;
        cvModal.classList.add('is-open');
        cvModal.style.display = 'flex';
        cvModalBody.setAttribute('aria-busy', 'true');
        cvModalBody.innerHTML = '<p class="ats-resume-text" role="status">Đang tải hồ sơ đã lưu khi ứng tuyển…</p>';
        cvModalBody.scrollTop = 0;
        if (cvModalName) cvModalName.textContent = 'Hồ sơ ứng viên';
        const appliedTimeEl = document.getElementById('cv-modal-applied-time');
        if (appliedTimeEl) appliedTimeEl.textContent = app.applied_at ? `Nộp ngày ${app.applied_at}` : '';
        const positionEl = document.getElementById('cv-modal-position-title');
        if (positionEl) positionEl.textContent = '';
        const filenameEl = document.getElementById('cv-modal-filename');
        if (filenameEl) filenameEl.textContent = 'Đang tải';
        const matchScoreEl = document.getElementById('cv-modal-match-score');
        if (matchScoreEl) matchScoreEl.textContent = app.match_score === null || app.match_score === undefined
            ? 'Chưa có dữ liệu phù hợp' : `${Number(app.match_score)}% phù hợp`;
        const statusPillEl = document.getElementById('cv-modal-status-pill');
        if (statusPillEl) statusPillEl.innerHTML = renderStatusPillHtml(app.status, app.status_label);
        cvModalCloseBtn?.focus();

        try {
            // Existing endpoint enforces Enterprise role, CV permission and application ownership.
            const data = await enterpriseRequest('GET', `/businesses/me/internship-applications/${encodeURIComponent(appId)}`);
            if (requestVersion !== cvRequestVersion) return;
            const application = data.application;
            if (!application || application.id !== appId) throw new Error('Không thể xác định hồ sơ ứng tuyển.');
            const snapshot = application.snapshot;
            if (positionEl) positionEl.textContent = application.title || '';
            if (!snapshot || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
                if (filenameEl) filenameEl.textContent = 'Chưa có hồ sơ đã lưu';
                cvModalBody.innerHTML = '<p class="ats-resume-text" role="status">Ứng tuyển này chưa có bản hồ sơ được lưu. Không có dữ liệu để hiển thị.</p>';
                return;
            }
            if (cvModalName) cvModalName.textContent = snapshot.passportCv?.name || snapshot.passport_cv?.name || snapshot.student?.fullName || snapshot.student?.full_name || 'Hồ sơ ứng viên';
            if (filenameEl) filenameEl.textContent = 'Bản lưu khi ứng tuyển';
            if (typeof application.cvHtml !== 'string' || application.cvHtml.trim() === '') {
                throw new Error('Chưa thể dựng CV từ hồ sơ đã lưu. Vui lòng thử lại.');
            }
            renderApplicationCv(application.cvHtml);
        } catch (error) {
            if (requestVersion !== cvRequestVersion) return;
            if (filenameEl) filenameEl.textContent = 'Chưa tải được hồ sơ';
            cvModalBody.innerHTML = `<p class="ats-resume-text" role="alert">${escapeHtml(error?.message || 'Không thể tải hồ sơ ứng viên.')}</p>
                <button type="button" class="ats-footer-btn ats-footer-btn--secondary" data-retry-snapshot>Thử lại</button>`;
            cvModalBody.querySelector('[data-retry-snapshot]')?.addEventListener('click', () => openCvModal(appId));
        } finally {
            if (requestVersion === cvRequestVersion) cvModalBody.removeAttribute('aria-busy');
        }
    }

    function closeCvModal() {
        ++cvRequestVersion;
        const wasOpen = cvModal?.classList.contains('is-open');
        if (cvModal) {
            cvModal.classList.remove('is-open');
            cvModal.style.display = 'none';
        }
        cvModalBody?.removeAttribute('aria-busy');
        if (wasOpen && cvReturnFocus?.isConnected) cvReturnFocus.focus();
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
        cvModal.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab') return;
            const controls = Array.from(cvModal.querySelectorAll('button:not([disabled]), a[href], iframe, [tabindex="0"]'))
                .filter(element => element.getClientRects().length > 0);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last?.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first?.focus();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCvModal();
            closeReviewDrawer();
        }
    });

    // 6. Bind Toolbar Filters & Tab Controls
    const setActivePipelineTab = (activeTab) => {
        pipelineTabs.forEach(t => {
            const isActive = t === activeTab;
            t.classList.toggle('is-active', isActive);
            t.setAttribute('aria-selected', String(isActive));
        });
    };

    pipelineTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            setActivePipelineTab(tab);

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

            const activeTab = Array.from(pipelineTabs).find(
                t => t.getAttribute('data-status-filter') === val
            );
            setActivePipelineTab(activeTab || null);

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
            setActivePipelineTab(pipelineTabs[0] || null);

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
