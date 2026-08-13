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
                            <div class="d-flex align-items-center gap-2">
                                <div class="ent-applicant-avatar">${escapeHtml(app.avatar_initials)}</div>
                                <div style="min-width: 0;">
                                    <a href="/app/enterprise/talents/detail.php?id=${app.student_id}" 
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
                                <a href="/app/enterprise/talents/detail.php?id=${app.student_id}" 
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
                            <div class="d-flex align-items-center gap-2">
                                <div class="ent-applicant-avatar" style="width:34px; height:34px; font-size:0.8rem;">
                                    ${escapeHtml(app.avatar_initials)}
                                </div>
                                <div>
                                    <a href="/app/enterprise/talents/detail.php?id=${app.student_id}" class="ent-applicant-info__name">
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
                                <a href="/app/enterprise/talents/detail.php?id=${app.student_id}" class="btn btn-secondary btn-sm">Xem hồ sơ</a>
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

    function openReviewDrawer(appId) {
        const app = applicants.find(a => a.id === appId);
        if (!app) return;

        currentActiveAppId = appId;

        // Drawer header & metadata
        document.getElementById('drawer-app-name').textContent = app.name;
        document.getElementById('drawer-app-school').textContent = `${app.school} · ${app.class_code || app.education_level}`;
        document.getElementById('drawer-app-date').textContent = app.applied_at;

        // Score Tag
        const scoreBadgeContainer = document.getElementById('drawer-score-badge');
        if (scoreBadgeContainer) {
            scoreBadgeContainer.innerHTML = renderMatchScoreBadge(app.match_score);
        }

        // Matching skills
        const matchingContainer = document.getElementById('drawer-matching-skills');
        if (matchingContainer) {
            const matches = app.matching_skills || [];
            if (matches.length > 0) {
                matchingContainer.innerHTML = matches.map(s => `<span class="ent-skill-tag-compact" style="background-color:#DCFCE7; color:#15803D;">✓ ${escapeHtml(s)}</span>`).join('');
            } else {
                matchingContainer.innerHTML = '<span class="text-muted" style="font-size:0.8125rem;">Chưa có kỹ năng khớp</span>';
            }
        }

        // Missing requirements
        const missingContainer = document.getElementById('drawer-missing-reqs');
        if (missingContainer) {
            const missing = app.missing_requirements || [];
            if (missing.length > 0) {
                missingContainer.innerHTML = missing.map(m => `<div class="text-secondary" style="font-size:0.8125rem;">• ${escapeHtml(m)}</div>`).join('');
            } else {
                missingContainer.innerHTML = '<span class="text-muted" style="font-size:0.8125rem;">Đáp ứng đầy đủ yêu cầu vị trí</span>';
            }
        }

        // Talent Passport Link
        const passportBtn = document.getElementById('btn-drawer-passport');
        if (passportBtn) {
            passportBtn.href = `/app/enterprise/talents/detail.php?id=${app.student_id}`;
        }

        // CV Modal Trigger inside drawer
        const cvBtn = document.getElementById('btn-drawer-cv');
        if (cvBtn) {
            cvBtn.onclick = () => openCvModal(app.id);
        }

        // Status Radios
        const radioCards = document.querySelectorAll('.ent-status-radio-card');
        radioCards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio.value === app.status) {
                radio.checked = true;
                card.classList.add('is-selected');
            } else {
                radio.checked = false;
                card.classList.remove('is-selected');
            }

            card.onclick = () => {
                radioCards.forEach(c => c.classList.remove('is-selected'));
                card.classList.add('is-selected');
                radio.checked = true;
            };
        });

        // Reviewer Note
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

            const selectedRadio = document.querySelector('.ent-status-radio-card input[type="radio"]:checked');
            const noteInput = document.getElementById('drawer-reviewer-note');
            
            const newStatus = selectedRadio ? selectedRadio.value : 'new';
            const newStatusLabel = getStatusLabel(newStatus);
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

        if (cvModalName) cvModalName.textContent = app.name;

        if (cvModalBody) {
            cvModalBody.innerHTML = `
                <div class="ent-cv-paper-preview">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                        <div>
                            <h3 class="font-bold text-primary mb-1" style="font-size: 1.25rem;">${escapeHtml(app.name)}</h3>
                            <p class="text-secondary mb-0" style="font-size: 0.875rem;">
                                ${escapeHtml(app.school)} &bull; ${escapeHtml(app.class_code || app.education_level)}
                            </p>
                        </div>
                        <div class="text-right">
                            ${renderStatusPillHtml(app.status, app.status_label)}
                            <div class="text-muted mt-1" style="font-size:0.75rem;">Nộp ngày: ${escapeHtml(app.applied_at)}</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-semibold text-dark mb-2" style="font-size: 0.9375rem; border-left: 3px solid var(--primary); padding-left: 0.5rem;">
                            MỤC TIÊU NGHỀ NGHIỆP & TỔNG QUAN
                        </h4>
                        <p class="text-secondary" style="font-size: 0.875rem; line-height: 1.6;">
                            Mong muốn ứng tuyển vị trí Thực tập sinh tại doanh nghiệp nhằm trau dồi kinh nghiệm thực tế, áp dụng các kiến thức đã tích lũy trong môi trường làm việc chuyên nghiệp.
                        </p>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-semibold text-dark mb-2" style="font-size: 0.9375rem; border-left: 3px solid var(--primary); padding-left: 0.5rem;">
                            KỸ NĂNG CHUYÊN MÔN
                        </h4>
                        <div class="d-flex flex-wrap gap-1">
                            ${(app.main_skills || []).map(s => `<span class="ent-skill-tag-compact">${escapeHtml(s)}</span>`).join('')}
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="font-semibold text-dark mb-2" style="font-size: 0.9375rem; border-left: 3px solid var(--primary); padding-left: 0.5rem;">
                            HỌC VẤN & BẰNG CẤP
                        </h4>
                        <ul class="text-secondary pl-3 mb-0" style="font-size:0.875rem; line-height:1.6;">
                            <li><strong>${escapeHtml(app.school)}</strong> - ${escapeHtml(app.class_code || 'CNTT')} (${escapeHtml(app.education_level)})</li>
                            <li>Độ tương thích vị trí: <strong>${app.match_score}% phù hợp</strong></li>
                        </ul>
                    </div>

                    <div class="p-3 bg-light rounded text-center text-muted" style="font-size:0.78125rem; border:1px dashed #CBD5E1;">
                        🔒 Hồ sơ CV đính kèm chính thức <strong>(${escapeHtml(app.resume_file || 'CV_Applicant.pdf')})</strong> đã được xác thực bởi TalentHub.
                    </div>
                </div>
            `;
        }

        if (cvModal) cvModal.classList.add('is-open');
    }

    function closeCvModal() {
        if (cvModal) cvModal.classList.remove('is-open');
    }

    if (cvModalCloseBtn) cvModalCloseBtn.addEventListener('click', closeCvModal);
    if (cvModal) {
        cvModal.addEventListener('click', (e) => {
            if (e.target === cvModal) closeCvModal();
        });
    }

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
