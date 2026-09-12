/**
 * TalentHub Enterprise - Project Sponsorships Module JavaScript
 * Handles interactive tabs, live filters, project detail view modals,
 * sponsorship calculation form & submission toast confirmations.
 */

document.addEventListener('DOMContentLoaded', function () {
    // --------------------------------------------------------------------------
    // 1. Tab Switching Logic
    // --------------------------------------------------------------------------
    const tabBtns = document.querySelectorAll('.spon-tab-btn');
    const tabPanes = document.querySelectorAll('.spon-tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('is-active'));
            tabPanes.forEach(p => p.style.display = 'none');

            this.classList.add('is-active');
            const activePane = document.getElementById('tab-' + targetTab);
            if (activePane) {
                activePane.style.display = 'block';
            }
        });
    });

    // --------------------------------------------------------------------------
    // --------------------------------------------------------------------------
    // 2. Discover Projects Filter & Search Logic
    // --------------------------------------------------------------------------
    const searchInput = document.getElementById('spon-search-input');
    const categorySelect = document.getElementById('spon-category-select');
    const schoolSelect = document.getElementById('spon-school-select');
    const rangeSelect = document.getElementById('spon-range-select');
    const statusSelect = document.getElementById('spon-status-select');
    const resetBtn = document.getElementById('spon-reset-filters');
    const pillBtns = document.querySelectorAll('.spon-pill-btn');
    const projectCards = document.querySelectorAll('.spon-project-card');
    const projectsEmptyState = document.getElementById('spon-projects-empty');

    let activePillCategory = 'all';

    function applyFilters() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const cat = categorySelect ? categorySelect.value : activePillCategory;
        const sch = schoolSelect ? schoolSelect.value : 'all';
        const rng = rangeSelect ? rangeSelect.value : 'all';
        const st = statusSelect ? statusSelect.value : 'all';

        let visibleCount = 0;

        projectCards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            const category = card.getAttribute('data-category') || '';
            const school = card.getAttribute('data-school') || '';
            const status = card.getAttribute('data-status') || '';
            const target = parseInt(card.getAttribute('data-target') || '0', 10);

            // Keyword Search Match
            const matchesQuery = !query || 
                title.toLowerCase().includes(query) || 
                category.toLowerCase().includes(query) || 
                school.toLowerCase().includes(query);

            // Category Filter Match (handles both pills and select dropdown)
            const matchesCategory = cat === 'all' || 
                category.toLowerCase() === cat.toLowerCase() || 
                category.toLowerCase().includes(cat.toLowerCase());

            // School Filter Match
            const matchesSchool = sch === 'all' || school === sch;

            // Status Filter Match
            const matchesStatus = st === 'all' || status === st;

            // Target Range Match
            let matchesRange = true;
            if (rng === 'under_50m') {
                matchesRange = target < 50000000;
            } else if (rng === '50m_100m') {
                matchesRange = target >= 50000000 && target <= 100000000;
            } else if (rng === 'above_100m') {
                matchesRange = target > 100000000;
            }

            if (matchesQuery && matchesCategory && matchesSchool && matchesStatus && matchesRange) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (projectsEmptyState) {
            projectsEmptyState.style.display = visibleCount === 0 ? 'flex' : 'none';
        }
    }

    if (pillBtns.length > 0) {
        pillBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                pillBtns.forEach(b => {
                    b.classList.remove('is-active');
                    b.style.backgroundColor = '#F8FAFC';
                    b.style.color = '#475569';
                    b.style.borderColor = '#E2E8F0';
                });
                this.classList.add('is-active');
                this.style.backgroundColor = '#C2410C';
                this.style.color = '#FFFFFF';
                this.style.borderColor = '#C2410C';

                activePillCategory = this.getAttribute('data-cat') || 'all';
                applyFilters();
            });
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (categorySelect) categorySelect.addEventListener('change', applyFilters);
    if (schoolSelect) schoolSelect.addEventListener('change', applyFilters);
    if (rangeSelect) rangeSelect.addEventListener('change', applyFilters);
    if (statusSelect) statusSelect.addEventListener('change', applyFilters);

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = 'all';
            if (schoolSelect) schoolSelect.value = 'all';
            if (rangeSelect) rangeSelect.value = 'all';
            if (statusSelect) statusSelect.value = 'all';
            activePillCategory = 'all';
            pillBtns.forEach((b, idx) => {
                if (idx === 0) {
                    b.classList.add('is-active');
                    b.style.backgroundColor = '#C2410C';
                    b.style.color = '#FFFFFF';
                    b.style.borderColor = '#C2410C';
                } else {
                    b.classList.remove('is-active');
                    b.style.backgroundColor = '#F8FAFC';
                    b.style.color = '#475569';
                    b.style.borderColor = '#E2E8F0';
                }
            });
            applyFilters();
        });
    }

    // --------------------------------------------------------------------------
    // 3. Project Detail Modal Controller
    // --------------------------------------------------------------------------
    const detailModal = document.getElementById('project-detail-modal');
    const closeDetailBtn = document.getElementById('close-detail-modal');

    function appendDetailText(parent, tag, className, value) {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = value == null || String(value).trim() === '' ? 'Chưa có dữ liệu' : String(value);
        parent.appendChild(element);
        return element;
    }

    function renderDetailFacts(id, entries) {
        const container = document.getElementById(id);
        if (!container) return;
        container.replaceChildren();
        entries.forEach(([label, value]) => {
            const row = document.createElement('div');
            appendDetailText(row, 'dt', '', label);
            appendDetailText(row, 'dd', '', value);
            container.appendChild(row);
        });
    }

    function formatDetailDate(value) {
        // Preserve the stored calendar date; parsing as a browser Date can shift its timezone.
        const date = typeof value === 'string' ? value.match(/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/) : null;
        return date ? `${date[3]}/${date[2]}/${date[1]}` : null;
    }

    function formatDetailMoney(value) {
        if (value == null || String(value).trim() === '' || !Number.isFinite(Number(value))) return null;
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value)) + ' VNĐ';
    }

    document.querySelectorAll('.btn-view-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const projectId = this.getAttribute('data-project-id');
            openProjectDetailModal(projectId);
        });
    });

    if (closeDetailBtn && detailModal) {
        closeDetailBtn.addEventListener('click', closeDetailModal);
        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal || e.target.classList.contains('spon-modal-close') || e.target.hasAttribute('data-close-modal')) {
                closeDetailModal();
            }
        });
    }

    function openProjectDetailModal(projectId) {
        if (!detailModal || !window.ENTERPRISE_PROJECTS) return;

        const project = window.ENTERPRISE_PROJECTS.find(p => p.id === projectId);
        if (!project) return;

        // Populate Modal Fields
        document.getElementById('modal-project-title').textContent = project.title;
        document.getElementById('modal-school-badge').textContent = project.school_badge ? (project.school_badge + ' • ' + project.school_name) : project.school_name;
        document.getElementById('modal-category-badge').textContent = project.category;
        document.getElementById('modal-status-badge').textContent = project.status_label || 'Đang gọi vốn';
        document.getElementById('modal-problem-desc').textContent = project.problem_statement || project.description;
        document.getElementById('modal-solution-desc').textContent = project.solution;

        // Populate Leader Info (mentor teacher)
        document.getElementById('modal-leader-avatar').textContent = project.team_leader.avatar_initial;
        document.getElementById('modal-leader-name').textContent = project.team_leader.name;
        document.getElementById('modal-leader-role').textContent = project.team_leader.role + (project.team_leader.school ? ' (' + project.team_leader.school + ')' : '');

        // Populate Members List (student members)
        const membersContainer = document.getElementById('modal-team-members');
        if (membersContainer) {
            membersContainer.innerHTML = '';
            (project.team_members || []).forEach(m => {
                const initials = m.name ? m.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : 'TV';
                const skills = m.skills || [];
                const skillsHtml = skills.length > 0
                    ? `<div class="spon-skills-row">${skills.map(s => `<span class="spon-skill-tag">${s}</span>`).join('')}</div>`
                    : '';
                const memberHtml = `
                    <div class="spon-team-card">
                        <div class="spon-avatar">${initials}</div>
                        <div class="spon-team-info">
                            <h6>${m.name}</h6>
                            <p>${m.role}</p>
                            ${skillsHtml}
                        </div>
                    </div>
                `;
                membersContainer.insertAdjacentHTML('beforeend', memberHtml);
            });
        }

        // Overall project dates/status are separate from research phase records.
        const schedule = project.project_schedule || {};
        renderDetailFacts('modal-project-schedule', [
            ['Ngày bắt đầu dự án', formatDetailDate(schedule.start_at)],
            ['Ngày kết thúc dự án', formatDetailDate(schedule.end_at)],
            ['Trạng thái dự án', schedule.status_label]
        ]);

        // Render only supplied phase records; never infer phases from project dates/status.
        const milestoneContainer = document.getElementById('modal-milestones-timeline');
        if (milestoneContainer) {
            milestoneContainer.replaceChildren();
            const milestones = Array.isArray(project.milestones) ? project.milestones : [];
            milestoneContainer.classList.toggle('spon-timeline-empty', milestones.length === 0);
            if (milestones.length === 0) {
                appendDetailText(milestoneContainer, 'p', 'spon-detail-empty', 'Chưa có dữ liệu về các giai đoạn nghiên cứu và nghiệm thu.');
            }
            milestones.forEach(ms => {
                const item = document.createElement('div');
                item.className = 'spon-timeline-item';
                if (['completed', 'in_progress', 'planned'].includes(ms.status)) item.classList.add(ms.status);
                const node = document.createElement('div');
                node.className = 'spon-timeline-node';
                item.appendChild(node);
                const content = document.createElement('div');
                content.className = 'spon-timeline-content';
                const header = document.createElement('div');
                header.className = 'spon-timeline-header';
                appendDetailText(header, 'span', 'spon-timeline-title', [ms.phase, ms.title].filter(Boolean).join(': '));
                appendDetailText(header, 'span', 'spon-timeline-date', [ms.date, ms.status_label].filter(Boolean).join(' • '));
                content.appendChild(header);
                item.appendChild(content);
                milestoneContainer.appendChild(item);
            });
        }

        // Show the stored funding state; reaching a computed percentage does not set a status.
        const funding = project.funding_plan || {};
        renderDetailFacts('modal-funding-summary', [
            ['Mục tiêu tài trợ', formatDetailMoney(funding.goal)],
            ['Đã nhận tài trợ', formatDetailMoney(funding.received_amount)],
            ['Trạng thái tài trợ', funding.status_label]
        ]);

        const fundContainer = document.getElementById('modal-fund-allocation');
        if (fundContainer) {
            fundContainer.replaceChildren();
            const allocations = Array.isArray(project.expected_use_of_funds) ? project.expected_use_of_funds : [];
            if (allocations.length === 0) {
                appendDetailText(fundContainer, 'p', 'spon-detail-empty', 'Chưa có dữ liệu về phân bổ chi phí chi tiết.');
            }
            allocations.forEach(f => {
                const item = document.createElement('div');
                item.className = 'spon-detail-fund-item';
                appendDetailText(item, 'span', '', f.category);
                appendDetailText(item, 'span', '', f.amount);
                fundContainer.appendChild(item);
            });
        }

        // Link CTA Button to Sponsor Modal
        const sponsorCta = document.getElementById('modal-sponsor-cta');
        if (sponsorCta) {
            const remaining = Math.max(0, project.target_amount - project.raised_amount);
            if (remaining <= 0) {
                sponsorCta.textContent = 'Đã đủ ngân sách';
                sponsorCta.style.backgroundColor = '#E2E8F0';
                sponsorCta.style.color = '#94A3B8';
                sponsorCta.style.cursor = 'not-allowed';
                sponsorCta.onclick = null;
            } else {
                sponsorCta.textContent = '🌱 Đồng ý Tài trợ dự án này';
                sponsorCta.style.backgroundColor = '';
                sponsorCta.style.color = '';
                sponsorCta.style.cursor = '';
                sponsorCta.onclick = function () {
                    closeDetailModal();
                    openSponsorshipFormModal(projectId);
                };
            }
        }

        detailModal.style.display = 'flex';
        detailModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailModal() {
        if (detailModal) {
            detailModal.style.display = 'none';
            detailModal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }

    // --------------------------------------------------------------------------
    // 4. Sponsorship Form Modal Controller
    // --------------------------------------------------------------------------
    const formModal = document.getElementById('sponsorship-form-modal');
    const closeFormBtn = document.getElementById('close-sponsorship-modal');
    const formProjectTitle = document.getElementById('form-project-title');
    const formTargetInfo = document.getElementById('form-target-info');
    const formNeededAmount = document.getElementById('form-needed-amount');
    const amountInput = document.getElementById('spon-amount-input');
    const presetBtns = document.querySelectorAll('.spon-preset-btn');
    const sponsorSubmitBtn = document.getElementById('btn-submit-sponsorship');
    const sponsorshipForm = document.getElementById('sponsorship-active-form');

    let activeSponsorProjectId = null;

    document.querySelectorAll('.btn-sponsor-now').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const projectId = this.getAttribute('data-project-id');
            openSponsorshipFormModal(projectId);
        });
    });

    if (closeFormBtn && formModal) {
        closeFormBtn.addEventListener('click', closeFormModal);
        formModal.addEventListener('click', function (e) {
            if (e.target === formModal || e.target.classList.contains('spon-modal-close') || e.target.hasAttribute('data-close-modal')) {
                closeFormModal();
            }
        });
    }

    function showModernToast(title, message) {
        let container = document.getElementById('spon-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'spon-toast-container';
            Object.assign(container.style, {
                position: 'fixed',
                top: '24px',
                right: '24px',
                zIndex: '9999',
                display: 'flex',
                flexDirection: 'column',
                gap: '12px'
            });
            document.body.appendChild(container);
            
            if (!document.getElementById('spon-toast-styles')) {
                const style = document.createElement('style');
                style.id = 'spon-toast-styles';
                style.textContent = `
                    @keyframes sponToastSlideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes sponToastSlideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                    @keyframes sponToastProgress {
                        from { width: 100%; }
                        to { width: 0%; }
                    }
                    .spon-modern-toast {
                        background: #fff;
                        border-left: 4px solid #10B981;
                        border-radius: 8px;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                        padding: 16px;
                        width: 320px;
                        max-width: 90vw;
                        position: relative;
                        overflow: hidden;
                        animation: sponToastSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                        display: flex;
                        gap: 12px;
                        align-items: flex-start;
                        box-sizing: border-box;
                    }
                    .spon-modern-toast.hiding {
                        animation: sponToastSlideOut 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
                    }
                    .spon-toast-icon {
                        color: #10B981;
                        flex-shrink: 0;
                    }
                    .spon-toast-content {
                        flex: 1;
                    }
                    .spon-toast-title {
                        font-weight: 600;
                        color: #111827;
                        margin-bottom: 4px;
                        font-size: 15px;
                    }
                    .spon-toast-message {
                        color: #4B5563;
                        font-size: 14px;
                        line-height: 1.4;
                    }
                    .spon-toast-progress {
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        height: 3px;
                        background: #10B981;
                        animation: sponToastProgress 3s linear forwards;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        const toast = document.createElement('div');
        toast.className = 'spon-modern-toast';
        toast.innerHTML = `
            <div class="spon-toast-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="spon-toast-content">
                <div class="spon-toast-title">${title}</div>
                <div class="spon-toast-message">${message}</div>
            </div>
            <div class="spon-toast-progress"></div>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => {
                toast.remove();
                if (container.childNodes.length === 0) {
                    container.remove();
                }
            }, 300);
        }, 3000);
    }

    function openSponsorshipFormModal(projectId) {
        if (!formModal || !window.ENTERPRISE_PROJECTS) return;

        activeSponsorProjectId = projectId;
        const project = window.ENTERPRISE_PROJECTS.find(p => p.id === projectId);
        if (!project) return;

        const remaining = Math.max(0, project.target_amount - project.raised_amount);
        
        if (remaining <= 0) {
            // Cannot sponsor fully funded projects
            showModernToast('Tài trợ thành công', 'Dự án này đã nhận đủ ngân sách tài trợ.');
            return;
        }

        if (formProjectTitle) formProjectTitle.textContent = project.title;
        if (formNeededAmount) {
            formNeededAmount.textContent = remaining.toLocaleString('vi-VN') + ' VNĐ';
        }
        if (formTargetInfo && !formNeededAmount) {
            formTargetInfo.textContent = `${project.school_name} • Mục tiêu: ${(project.target_amount / 1000000).toFixed(0)} triệu VNĐ (Còn thiếu: ${(remaining / 1000000).toFixed(0)} triệu VNĐ)`;
        }

        // Helper to update the range slider track fill
        function updateSliderFill(slider) {
            const val = parseInt(slider.value, 10);
            const max = parseInt(slider.max, 10);
            const percentage = max > 0 ? (val / max) * 100 : 0;
            slider.style.background = `linear-gradient(to right, #F97316 ${percentage}%, #E2E8F0 ${percentage}%)`;
        }

        // Configure the Range Slider
        if (amountInput) {
            amountInput.max = remaining;
            amountInput.value = Math.min(10000000, remaining);
            updateSliderFill(amountInput);
            
            const displayEl = document.getElementById('spon-amount-display');
            if (displayEl) {
                displayEl.textContent = parseInt(amountInput.value, 10).toLocaleString('vi-VN') + ' VNĐ';
            }
            
            const maxLabel = document.getElementById('spon-amount-max-label');
            if (maxLabel) {
                maxLabel.textContent = remaining.toLocaleString('vi-VN') + ' VNĐ';
            }
            
            validateSponsorshipAmount(amountInput.value);
        }

        formModal.style.display = 'flex';
        formModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeFormModal() {
        if (formModal) {
            formModal.style.display = 'none';
            formModal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
    }
    
    function validateSponsorshipAmount(val) {
        const amountNum = parseInt(val, 10) || 0;
        const btnSubmit = document.getElementById('btn-submit-sponsorship');
        const errorMsg = document.getElementById('spon-amount-error');
        
        if (amountNum <= 0) {
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.style.opacity = '0.5';
                btnSubmit.style.cursor = 'not-allowed';
            }
            if (errorMsg) errorMsg.style.display = 'block';
        } else {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
            }
            if (errorMsg) errorMsg.style.display = 'none';
        }
    }

    // Global ESC Key Listener to Close Open Modals
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDetailModal();
            closeFormModal();
        }
    });

    // Format Amount Input
    if (amountInput) {
        amountInput.addEventListener('input', function () {
            const displayEl = document.getElementById('spon-amount-display');
            if (displayEl) {
                displayEl.textContent = parseInt(this.value, 10).toLocaleString('vi-VN') + ' VNĐ';
            }
            const val = parseInt(this.value, 10);
            const max = parseInt(this.max, 10);
            const percentage = max > 0 ? (val / max) * 100 : 0;
            this.style.background = `linear-gradient(to right, #F97316 ${percentage}%, #E2E8F0 ${percentage}%)`;
            
            validateSponsorshipAmount(this.value);
        });
    }

    // Submit Sponsorship Form
    if (sponsorshipForm) {
        sponsorshipForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!activeSponsorProjectId) return;

            const project = window.ENTERPRISE_PROJECTS ? window.ENTERPRISE_PROJECTS.find(p => p.id === activeSponsorProjectId) : null;
            const valString = String(amountInput.value).replace(/[^0-9]/g, '');
            const amountNum = parseInt(valString, 10);

            if (!amountNum || amountNum <= 0) {
                return; // Disabled via UI, but double check just in case. Removed ugly alert.
            }

            const noteInput = document.getElementById('spon-note-input');
            const noteText = noteInput ? noteInput.value.trim() : '';

            let boot = window.ENTERPRISE_BOOT || {};
            const bootElement = document.getElementById('enterprise-session-boot');
            if (bootElement && (!boot.csrfToken || !boot.apiBase)) {
                try {
                    boot = Object.assign(boot, JSON.parse(bootElement.textContent));
                    window.ENTERPRISE_BOOT = boot;
                } catch(err) {}
            }

            const apiBase = boot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/api/v1' : '/api/v1');
            const csrfToken = boot.csrfToken || '';

            const request = async (method, path, body) => {
                const response = await fetch(`${apiBase}${path}`, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                const json = await response.json().catch(() => null);
                if (!response.ok || !json?.data) {
                    const errorMsg = json?.error?.message 
                        || (json?.error?.details && Array.isArray(json.error.details) ? json.error.details.map(d => d.message).join(' ') : null)
                        || `Thao tác thất bại (HTTP ${response.status}). Vui lòng thử lại.`;
                    throw new Error(errorMsg);
                }
                return json.data;
            };

            if (sponsorSubmitBtn) {
                sponsorSubmitBtn.disabled = true;
                sponsorSubmitBtn.textContent = 'Đang ghi cam kết...';
            }

            try {
                // Bước 1: Ghi cam kết tài trợ (status = pledged)
                const sponRes = await request('POST', '/businesses/me/sponsorships', {
                    projectId: activeSponsorProjectId,
                    amount: String(amountNum),
                    currency: 'VND',
                    note: noteText || 'Tài trợ phát triển dự án nghiên cứu sinh viên.',
                });

                const sponsorshipId = sponRes.id;

                // Bước 2: Tạo lệnh thanh toán (payment_orders.paymentStatus = pending)
                const paymentRes = await request('POST', '/businesses/me/payments', {
                    sponsorshipId: sponsorshipId,
                    provider: 'vnpay',
                });

                const orderId = paymentRes.id;

                // Bước 3: Xác nhận thanh toán từ cổng (status → paid, paymentStatus → paid)
                await request('POST', `/businesses/me/payments/${encodeURIComponent(orderId)}/confirm`, {
                    providerReference: 'VNPAY_' + Date.now(),
                });

                // Bước 4: Cập nhật thẻ project trong DOM (chỉ phản ánh số đã thanh toán)
                if (project) {
                    project.raised_amount = Number(project.raised_amount || 0) + amountNum;
                    const target = Number(project.target_amount || 1);
                    const newPct = Math.min(100, Math.round((project.raised_amount / target) * 100));
                    project.percentage = newPct;

                    const card = document.querySelector(`.spon-project-card[data-project-id="${activeSponsorProjectId}"]`);
                    if (card) {
                        const raisedM = (project.raised_amount / 1000000).toFixed(1).replace('.0', '');
                        const targetM = (target / 1000000).toFixed(1).replace('.0', '');
                        const progSpan = card.querySelector('div span:first-child');
                        if (progSpan) progSpan.textContent = `${raisedM} triệu / ${targetM} triệu VNĐ`;
                        const pctSpan = card.querySelector('div span:last-child');
                        if (pctSpan) pctSpan.textContent = `${newPct}%`;
                        const bar = card.querySelector('.spon-progress-fill, div[style*="linear-gradient"]');
                        if (bar) bar.style.width = `${newPct}%`;
                    }
                }

                closeFormModal();

                // Toast phân biệt: cam kết + thanh toán đã xác nhận
                showSuccessToast(
                    `Cam kết tài trợ ${amountNum.toLocaleString('vi-VN')} VNĐ cho dự án` +
                    ` "${project ? project.title : ''}" đã được ghi nhận và thanh toán xác nhận thành công.`
                );

                // Reload để đồng bộ trạng thái server
                setTimeout(() => {
                    window.location.reload();
                }, 1500);

            } catch (error) {
                showSuccessToast(error?.message || 'Có lỗi xảy ra trong quá trình tài trợ dự án.');
            } finally {
                if (sponsorSubmitBtn) {
                    sponsorSubmitBtn.disabled = false;
                    sponsorSubmitBtn.textContent = 'Xác nhận Cam kết Tài trợ';
                }
            }
        });
    }

    // --------------------------------------------------------------------------
    // 5. Progress Update Modal Controller ("Theo dõi tiến độ")
    // --------------------------------------------------------------------------
    const progressModal = document.getElementById('progress-detail-modal');
    const closeProgressBtn = document.getElementById('close-progress-modal');

    document.querySelectorAll('.btn-track-progress').forEach(btn => {
        btn.addEventListener('click', function () {
            const sponId = this.getAttribute('data-sponsorship-id');
            openProgressModal(sponId);
        });
    });

    if (closeProgressBtn && progressModal) {
        closeProgressBtn.addEventListener('click', function () {
            progressModal.classList.remove('is-open');
            document.body.style.overflow = '';
        });
        progressModal.addEventListener('click', function (e) {
            if (e.target === progressModal) {
                progressModal.classList.remove('is-open');
                document.body.style.overflow = '';
            }
        });
    }

    function openProgressModal(sponsorshipId) {
        if (!progressModal || !window.ENTERPRISE_SPONSORSHIPS) return;

        const item = window.ENTERPRISE_SPONSORSHIPS.find(s => s.id === sponsorshipId);
        if (!item) return;

        document.getElementById('prog-modal-title').textContent = item.project_title;
        document.getElementById('prog-modal-school').textContent = item.school_name + ' • ' + item.category;
        document.getElementById('prog-modal-amount').textContent = item.sponsored_amount_formatted;
        document.getElementById('prog-modal-status').textContent = item.status_label;
        document.getElementById('prog-modal-update-date').textContent = item.latest_update.date;
        document.getElementById('prog-modal-update-title').textContent = item.latest_update.title;
        document.getElementById('prog-modal-update-author').textContent = 'Bởi: ' + item.latest_update.author;
        document.getElementById('prog-modal-update-summary').textContent = item.latest_update.summary;

        progressModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    // Helper Toast Notification
    function showSuccessToast(message) {
        let toast = document.getElementById('spon-toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'spon-toast-notification';
            toast.style.cssText = `
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                background-color: #322014;
                color: #FFFFFF;
                border-left: 4px solid #F83F70;
                padding: 1.125rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(50, 32, 20, 0.25);
                z-index: 10000;
                font-size: 0.9375rem;
                max-width: 420px;
                display: flex;
                align-items: center;
                gap: 0.875rem;
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            `;
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <div style="width: 2rem; height: 2rem; border-radius: 50%; background: rgba(248,63,112,0.2); color: #FF6B45; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <div>${message}</div>
        `;

        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 50);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
        }, 4500);
    }

    // Global Escape Key Modal Close Handler
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.spon-modal-overlay.is-open').forEach(m => {
                m.classList.remove('is-open');
            });
            document.body.style.overflow = '';
        }
    });
});
