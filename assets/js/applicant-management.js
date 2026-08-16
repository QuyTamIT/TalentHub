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
        console.error('Failed to parse applicant mock data:', e);
        applicants = [];
    }

    const talentsDataEl = document.getElementById('talents-raw-data');
    let talentsList = [];
    if (talentsDataEl) {
        try {
            talentsList = JSON.parse(talentsDataEl.textContent || '[]');
        } catch (e) {
            console.warn('Could not parse talents data:', e);
            talentsList = [];
        }
    }

    const currentPostId = applicantsDataEl.getAttribute('data-post-id') || '1';
    const storageKey = `talenthub_applicant_reviews_${currentPostId}`;

    // Load persisted mock reviews from localStorage if available
    const savedReviews = localStorage.getItem(storageKey);
    if (savedReviews) {
        try {
            const parsedReviews = JSON.parse(savedReviews);
            applicants = applicants.map(app => {
                if (parsedReviews[app.id]) {
                    return {
                        ...app,
                        status: parsedReviews[app.id].status || app.status,
                        status_label: parsedReviews[app.id].status_label || app.status_label,
                        reviewer_note: parsedReviews[app.id].reviewer_note !== undefined ? parsedReviews[app.id].reviewer_note : app.reviewer_note
                    };
                }
                return app;
            });
        } catch (e) {
            console.warn('Could not parse local mock reviews:', e);
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
            case 'new': return 'Mới';
            case 'reviewing': return 'Đang xem xét';
            case 'interviewing': return 'Phỏng vấn';
            case 'accepted': return 'Đã nhận';
            case 'rejected': return 'Từ chối';
            default: return 'Tất cả';
        }
    }

    // Helper: Render status pill HTML
    function renderStatusPillHtml(status, label) {
        return `<span class="ent-app-status-pill ent-app-status-pill--${status}">
            <span class="dot"></span>
            ${escapeHtml(label || getStatusLabel(status))}
        </span>`;
    }

    // Helper: Render Single-Line Job Match Score tag
    function renderMatchScoreBadge(score) {
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
            new: 0,
            reviewing: 0,
            interviewing: 0,
            accepted: 0,
            rejected: 0
        };

        applicants.forEach(app => {
            if (counts[app.status] !== undefined) {
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

            // Score Range Check
            if (selectedScore === '90_plus' && app.match_score < 90) return false;
            if (selectedScore === '80_89' && (app.match_score < 80 || app.match_score >= 90)) return false;
            if (selectedScore === 'under_80' && app.match_score >= 80) return false;

            return true;
        }).sort((a, b) => {
            if (selectedSort === 'score_desc') {
                return b.match_score - a.match_score;
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

        if (filtered.length === 0) {
            if (tableBody) tableBody.innerHTML = '';
            if (mobileCardsContainer) mobileCardsContainer.innerHTML = '';
            if (emptyStateContainer) emptyStateContainer.style.display = 'block';
            return;
        }

        if (emptyStateContainer) emptyStateContainer.style.display = 'none';

        // Render Desktop Table Rows
        if (tableBody) {
            tableBody.innerHTML = filtered.map(app => {
                const isDecisionMade = (app.status === 'accepted' || app.status === 'rejected');
                const primaryBtnText = isDecisionMade ? 'Chi tiết' : 'Duyệt';
                const primaryBtnClass = isDecisionMade ? 'btn-secondary' : 'btn-primary';

                return `
                    <tr data-applicant-id="${app.id}">
                        <td>
                            <div class="ent-applicant-identity">
                                <div class="ent-applicant-avatar">${escapeHtml(app.avatar_initials)}</div>
                                <div class="ent-applicant-info">
                                    <a href="../talents/detail.php?id=${app.student_id}" 
                                       class="ent-applicant-info__name"
                                       title="Xem Talent Passport của ${escapeHtml(app.name)}">
                                        ${escapeHtml(app.name)}
                                    </a>
                                    <div class="ent-applicant-info__sub" title="${escapeHtml(app.school)} · ${escapeHtml(app.class_code || app.education_level)}">
                                        ${escapeHtml(app.school)} &middot; ${escapeHtml(app.class_code || app.education_level)}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap align-items-center">${renderSkillsHtml(app.main_skills)}</div>
                        </td>
                        <td>
                            <span class="text-secondary" style="font-size:0.8125rem;">${escapeHtml(app.applied_at.split(' ')[0])}</span>
                        </td>
                        <td class="text-center">
                            ${renderMatchScoreBadge(app.match_score)}
                        </td>
                        <td>
                            ${renderStatusPillHtml(app.status, app.status_label)}
                        </td>
                        <td class="text-right">
                            <div class="ent-action-group">
                                <a href="../talents/detail.php?id=${app.student_id}" 
                                   class="btn btn-secondary btn-sm"
                                   title="Xem Talent Passport">
                                    Xem hồ sơ
                                </a>
                                <button type="button" 
                                        class="btn ${primaryBtnClass} btn-sm btn-review-app" 
                                        data-app-id="${app.id}">
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
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            Thêm ghi chú
                                        </button>
                                        <button type="button" class="ent-dropdown-item btn-review-app" data-app-id="${app.id}">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Đổi trạng thái
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
                const isDecisionMade = (app.status === 'accepted' || app.status === 'rejected');
                const primaryBtnText = isDecisionMade ? 'Chi tiết' : 'Duyệt hồ sơ';
                const primaryBtnClass = isDecisionMade ? 'btn-secondary' : 'btn-primary';

                return `
                    <article class="ent-applicant-mobile-card" data-applicant-id="${app.id}">
                        <div class="ent-applicant-mobile-card__header">
                            <div class="ent-applicant-identity">
                                <div class="ent-applicant-avatar" style="width:34px; height:34px; font-size:0.8rem;">
                                    ${escapeHtml(app.avatar_initials)}
                                </div>
                                <div class="ent-applicant-info">
                                    <a href="../talents/detail.php?id=${app.student_id}" class="ent-applicant-info__name">
                                        ${escapeHtml(app.name)}
                                    </a>
                                    <div class="ent-applicant-info__sub">${escapeHtml(app.school)} &middot; ${escapeHtml(app.class_code || app.education_level)}</div>
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
                                <a href="../talents/detail.php?id=${app.student_id}" class="btn btn-secondary btn-sm">Xem hồ sơ</a>
                                <button type="button" class="btn ${primaryBtnClass} btn-sm btn-review-app" data-app-id="${app.id}">${primaryBtnText}</button>
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
        // Review Drawer Triggers
        document.querySelectorAll('.btn-review-app').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                closeAllDropdowns();
                const appId = parseInt(btn.getAttribute('data-app-id'));
                openReviewDrawer(appId);
            });
        });

        // CV Modal Triggers
        document.querySelectorAll('.btn-view-cv').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                closeAllDropdowns();
                const appId = parseInt(btn.getAttribute('data-app-id'));
                openCvModal(appId);
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

    let currentActiveDrawerStatus = 'new';

    // Helper: Update ATS Recruiter Pipeline visual states
    function updateDrawerPipelineUI(status) {
        currentActiveDrawerStatus = status;
        const steps = ['new', 'reviewing', 'interviewing', 'accepted'];
        const targetIdx = steps.indexOf(status);

        document.querySelectorAll('.ats-pipeline-step').forEach(stepBtn => {
            const stepStatus = stepBtn.getAttribute('data-status');
            const stepIdx = steps.indexOf(stepStatus);

            stepBtn.classList.remove('is-active', 'is-completed');
            stepBtn.setAttribute('aria-checked', 'false');

            if (status === 'rejected') {
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
            if (status === 'rejected') {
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
            updateDrawerPipelineUI('rejected');
        });
    }

    function openReviewDrawer(appId) {
        const app = applicants.find(a => a.id === appId);
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
            locTextEl.textContent = app.location || 'Hà Nội';
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
            expEl.textContent = `${app.experience_hours || 120}h thực án`;
        }

        const scoreValEl = document.getElementById('drawer-snapshot-score');
        if (scoreValEl) {
            scoreValEl.textContent = `${app.match_score}%`;
        }

        const snapshotStatus = document.getElementById('drawer-snapshot-status');
        if (snapshotStatus) {
            snapshotStatus.innerHTML = renderStatusPillHtml(app.status, app.status_label);
        }

        // 3. Role Fit Analysis Section
        const fitPercentageEl = document.getElementById('drawer-fit-percentage');
        if (fitPercentageEl) {
            fitPercentageEl.textContent = `${app.match_score}% phù hợp`;
        }

        const progressFillEl = document.getElementById('drawer-fit-progress-fill');
        if (progressFillEl) {
            progressFillEl.style.width = `${app.match_score}%`;
        }

        const progressAriaEl = document.getElementById('drawer-fit-progress-aria');
        if (progressAriaEl) {
            progressAriaEl.setAttribute('aria-valuenow', app.match_score);
        }

        const fitSummaryEl = document.getElementById('drawer-fit-summary');
        if (fitSummaryEl) {
            if (app.match_score >= 92) {
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
                missingContainer.innerHTML = '<div class="ats-missing-item" style="color:#16A34A; font-weight:500;">✓ Đáp ứng đầy đủ tiêu chí năng lực yêu cầu</div>';
            }
        }

        // 4. Quick Action Links
        const passportBtn = document.getElementById('btn-drawer-passport');
        if (passportBtn) {
            passportBtn.href = `/app/enterprise/talents/detail.php?id=${app.student_id}`;
        }

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
        saveReviewBtn.addEventListener('click', () => {
            if (!currentActiveAppId) return;

            const newStatus = currentActiveDrawerStatus || 'new';
            const newStatusLabel = getStatusLabel(newStatus);
            const noteInput = document.getElementById('drawer-reviewer-note');
            const newNote = noteInput ? noteInput.value.trim() : '';

            // Update in-memory applicant record
            const appIndex = applicants.findIndex(a => a.id === currentActiveAppId);
            if (appIndex !== -1) {
                applicants[appIndex].status = newStatus;
                applicants[appIndex].status_label = newStatusLabel;
                applicants[appIndex].reviewer_note = newNote;

                // Sync with localStorage
                try {
                    let reviewsObj = {};
                    const existing = localStorage.getItem(storageKey);
                    if (existing) reviewsObj = JSON.parse(existing);

                    reviewsObj[currentActiveAppId] = {
                        status: newStatus,
                        status_label: newStatusLabel,
                        reviewer_note: newNote,
                        updated_at: new Date().toISOString()
                    };

                    localStorage.setItem(storageKey, JSON.stringify(reviewsObj));
                } catch (e) {
                    console.warn('Failed to save review in localStorage:', e);
                }

                showToast(`Đã cập nhật trạng thái ứng viên thành "${newStatusLabel}"!`);
                closeReviewDrawer();
                renderList();
            }
        });
    }

    // CV Lightbox Modal Functions
    function openCvModal(appId) {
        const app = applicants.find(a => a.id === appId);
        if (!app) return;

        currentActiveAppId = appId;
        const student = talentsList.find(t => t.id === app.student_id) || {};

        // 1. Pinned Modal Header
        if (cvModalName) cvModalName.textContent = app.name;
        
        const appliedTimeEl = document.getElementById('cv-modal-applied-time');
        if (appliedTimeEl) {
            appliedTimeEl.textContent = `Nộp ngày ${app.applied_at ? app.applied_at.split(' ')[0] : '-'}`;
        }

        const passportModalBtn = document.getElementById('btn-cv-modal-passport');
        if (passportModalBtn) {
            passportModalBtn.href = `/app/enterprise/talents/detail.php?id=${app.student_id}`;
        }

        // 2. Recruiter Context Bar
        const matchScoreEl = document.getElementById('cv-modal-match-score');
        if (matchScoreEl) {
            matchScoreEl.textContent = `${app.match_score}% phù hợp`;
        }

        const statusPillEl = document.getElementById('cv-modal-status-pill');
        if (statusPillEl) {
            statusPillEl.innerHTML = renderStatusPillHtml(app.status, app.status_label);
        }

        const filenameEl = document.getElementById('cv-modal-filename');
        if (filenameEl) {
            filenameEl.textContent = app.resume_file || `CV_${escapeHtml(app.name.replace(/\s+/g, ''))}.pdf`;
        }

        // 3. Render Authentic Resume Paper
        if (cvModalBody) {
            const headline = student.readiness_summary?.preferred_field || student.major_field || 'Ứng viên thực tập tiềm năng';
            const bioText = student.bio || 'Mong muốn ứng tuyển vị trí Thực tập sinh tại doanh nghiệp nhằm trau dồi kinh nghiệm thực tế, áp dụng các kiến thức đã tích lũy trong môi trường làm việc chuyên nghiệp và đóng góp giá trị cho dự án của công ty.';
            
            // Skills tags
            const skills = app.main_skills || student.skills || [];
            const skillsHtml = skills.map(s => `<span class="ats-resume-skill-tag">${escapeHtml(s)}</span>`).join('');

            // Certificates
            let certsHtml = '';
            if (student.certificates && student.certificates.length > 0) {
                certsHtml = student.certificates.map(c => `
                    <div class="ats-resume-item mt-2">
                        <div class="ats-resume-item__header">
                            <h4 class="ats-resume-item__title" style="font-size:0.8125rem;">📜 ${escapeHtml(c.name)}</h4>
                            <span class="ats-resume-item__date">${escapeHtml(c.issue_date || '')}</span>
                        </div>
                        <div class="ats-resume-item__desc">Tổ chức cấp: ${escapeHtml(c.issuer)} ${c.verified ? '<span class="text-success font-medium">(Đã xác thực)</span>' : ''}</div>
                    </div>
                `).join('');
            }

            // Projects / Experience
            let projectsHtml = '';
            if (student.projects && student.projects.length > 0) {
                projectsHtml = student.projects.map(p => `
                    <div class="ats-resume-item">
                        <div class="ats-resume-item__header">
                            <h3 class="ats-resume-item__title">${escapeHtml(p.name)}</h3>
                            <span class="ats-resume-item__date">${escapeHtml(p.role || 'Thành viên')}</span>
                        </div>
                        <div class="ats-resume-item__desc">${escapeHtml(p.description || '')}</div>
                        ${p.technologies ? `<div class="text-muted mt-1" style="font-size:0.75rem;">Công nghệ: <strong>${p.technologies.join(', ')}</strong> &bull; Kết quả: <span class="text-dark font-medium">${escapeHtml(p.result || '')}</span></div>` : ''}
                    </div>
                `).join('');
            } else if (student.experience_logs && student.experience_logs.length > 0) {
                projectsHtml = student.experience_logs.map(exp => `
                    <div class="ats-resume-item">
                        <div class="ats-resume-item__header">
                            <h3 class="ats-resume-item__title">${escapeHtml(exp.title)}</h3>
                            <span class="ats-resume-item__date">${escapeHtml(exp.duration || '')}</span>
                        </div>
                        <div class="ats-resume-item__subtitle">${escapeHtml(exp.role)} &bull; ${exp.hours}h thực tế</div>
                        <div class="ats-resume-item__desc">${escapeHtml(exp.description || '')}</div>
                    </div>
                `).join('');
            } else {
                projectsHtml = `
                    <div class="ats-resume-item">
                        <div class="ats-resume-item__header">
                            <h3 class="ats-resume-item__title">Dự án Đồ án Chuyên ngành</h3>
                            <span class="ats-resume-item__date">01/2026 - 06/2026</span>
                        </div>
                        <div class="ats-resume-item__desc">Ứng dụng kiến thức chuyên ngành vào giải quyết bài toán thực tế của doanh nghiệp, làm việc nhóm theo mô hình Agile/Scrum.</div>
                    </div>
                `;
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
                            <span>📍 ${escapeHtml(app.location || 'Hà Nội')}</span>
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
                                    <span class="ats-resume-item__date">2022 - Hiện tại</span>
                                </div>
                                <div class="ats-resume-item__desc">Chuyên ngành: <strong>${escapeHtml(student.major_field || 'Công nghệ Thông tin')}</strong> &bull; Trình độ: ${escapeHtml(app.education_level || 'Đại học')}</div>
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

                    <!-- Footer Verification Stamp -->
                    <footer class="ats-resume-footer">
                        <div class="ats-resume-cert-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Tài liệu đính kèm: <strong>${escapeHtml(app.resume_file || 'CV_Applicant.pdf')}</strong></span>
                            <span class="ats-meta-divider">&bull;</span>
                            <span style="color:#16A34A; font-weight:600;">Đã xác thực bởi TalentHub</span>
                        </div>
                    </footer>
                </article>
            `;
        }

        if (cvModal) cvModal.classList.add('is-open');
    }

    function closeCvModal() {
        if (cvModal) cvModal.classList.remove('is-open');
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

    const downloadCvBtn = document.getElementById('btn-download-cv-file');
    if (downloadCvBtn) {
        downloadCvBtn.addEventListener('click', () => {
            showToast('Đang tạo bản PDF chính thức của ứng viên để tải xuống...');
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

    // Initial render call
    renderList();
});
