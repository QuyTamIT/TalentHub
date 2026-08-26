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
                this.style.backgroundColor = '#F97316';
                this.style.color = '#FFFFFF';
                this.style.borderColor = '#F97316';

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
                    b.style.backgroundColor = '#F97316';
                    b.style.color = '#FFFFFF';
                    b.style.borderColor = '#F97316';
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
        document.getElementById('modal-school-badge').textContent = (project.school_badge || 'Đại học') + ' • ' + project.school_name;
        document.getElementById('modal-category-badge').textContent = project.category;
        document.getElementById('modal-status-badge').textContent = project.status_label || 'Đang gọi vốn';
        document.getElementById('modal-problem-desc').textContent = project.problem_statement || project.description;
        document.getElementById('modal-solution-desc').textContent = project.solution;

        // Populate Leader Info
        document.getElementById('modal-leader-avatar').textContent = project.team_leader.avatar_initial;
        document.getElementById('modal-leader-name').textContent = project.team_leader.name;
        document.getElementById('modal-leader-role').textContent = project.team_leader.role + ' (' + project.team_leader.school + ')';

        // Populate Members List
        const membersContainer = document.getElementById('modal-team-members');
        if (membersContainer) {
            membersContainer.innerHTML = '';
            (project.team_members || []).forEach(m => {
                const initials = m.name ? m.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : 'TV';
                const skills = m.skills || ['Nghiên cứu', 'Thực hành'];
                const memberHtml = `
                    <div class="spon-team-card">
                        <div class="spon-avatar">${initials}</div>
                        <div class="spon-team-info">
                            <h5>${m.name}</h5>
                            <p>${m.role}</p>
                            <div class="spon-skills-row">
                                ${skills.map(s => `<span class="spon-skill-tag">${s}</span>`).join('')}
                            </div>
                        </div>
                    </div>
                `;
                membersContainer.insertAdjacentHTML('beforeend', memberHtml);
            });
        }

        // Populate Milestones Timeline
        const milestoneContainer = document.getElementById('modal-milestones-timeline');
        if (milestoneContainer) {
            milestoneContainer.innerHTML = '';
            (project.milestones || []).forEach(ms => {
                const msHtml = `
                    <div class="spon-timeline-item ${ms.status}">
                        <div class="spon-timeline-node"></div>
                        <div class="spon-timeline-content">
                            <div class="spon-timeline-header">
                                <span class="spon-timeline-title">${ms.phase}: ${ms.title}</span>
                                <span class="spon-timeline-date">${ms.date} • ${ms.status_label}</span>
                            </div>
                        </div>
                    </div>
                `;
                milestoneContainer.insertAdjacentHTML('beforeend', msHtml);
            });
        }

        // Populate Fund Allocation Bars
        const fundContainer = document.getElementById('modal-fund-allocation');
        if (fundContainer) {
            fundContainer.innerHTML = '';
            (project.expected_use_of_funds || []).forEach(f => {
                const fHtml = `
                    <div style="margin-bottom: 0.875rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.25rem;">
                            <span>${f.category}</span>
                            <span>${f.amount} (${f.percentage}%)</span>
                        </div>
                        <div class="spon-progress-track" style="height: 6px;">
                            <div class="spon-progress-fill" style="width: ${f.percentage}%;"></div>
                        </div>
                    </div>
                `;
                fundContainer.insertAdjacentHTML('beforeend', fHtml);
            });
        }

        // Link CTA Button to Sponsor Modal
        const sponsorCta = document.getElementById('modal-sponsor-cta');
        if (sponsorCta) {
            sponsorCta.onclick = function () {
                closeDetailModal();
                openSponsorshipFormModal(projectId);
            };
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

    function openSponsorshipFormModal(projectId) {
        if (!formModal || !window.ENTERPRISE_PROJECTS) return;

        activeSponsorProjectId = projectId;
        const project = window.ENTERPRISE_PROJECTS.find(p => p.id === projectId);
        if (!project) return;

        if (formProjectTitle) formProjectTitle.textContent = project.title;
        const remaining = Math.max(0, project.target_amount - project.raised_amount);
        if (formNeededAmount) {
            formNeededAmount.textContent = remaining.toLocaleString('vi-VN') + ' VNĐ';
        }
        if (formTargetInfo && !formNeededAmount) {
            formTargetInfo.textContent = `${project.school_name} • Mục tiêu: ${(project.target_amount / 1000000).toFixed(0)} triệu VNĐ (Còn thiếu: ${(remaining / 1000000).toFixed(0)} triệu VNĐ)`;
        }

        // Set Default Preset Value (10.000.000 VNĐ)
        if (amountInput) amountInput.value = '10000000';
        presetBtns.forEach(b => {
            const btnVal = b.getAttribute('data-val') || b.getAttribute('data-amount');
            if (btnVal === '10000000') {
                b.classList.add('is-selected');
                b.style.backgroundColor = '#F97316';
                b.style.color = '#FFFFFF';
                b.style.borderColor = '#F97316';
            } else {
                b.classList.remove('is-selected');
                b.style.backgroundColor = '#FFFFFF';
                b.style.color = '#0F172A';
                b.style.borderColor = '#E2E8F0';
            }
        });

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

    // Global ESC Key Listener to Close Open Modals
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDetailModal();
            closeFormModal();
        }
    });

    // Preset Amount Click Handler
    presetBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const rawVal = parseInt(this.getAttribute('data-amount'), 10);
            if (amountInput) {
                amountInput.value = rawVal.toLocaleString('en-US');
            }
            presetBtns.forEach(b => b.classList.remove('is-selected'));
            this.classList.add('is-selected');
        });
    });

    // Format Amount Input
    if (amountInput) {
        amountInput.addEventListener('input', function () {
            let val = this.value.replace(/[^0-9]/g, '');
            if (val) {
                this.value = parseInt(val, 10).toLocaleString('en-US');
            } else {
                this.value = '';
            }
            presetBtns.forEach(b => b.classList.remove('is-selected'));
        });
    }

    // Submit Sponsorship Form
    if (sponsorshipForm) {
        sponsorshipForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!activeSponsorProjectId) return;

            const project = window.ENTERPRISE_PROJECTS.find(p => p.id === activeSponsorProjectId);
            const valString = amountInput.value.replace(/,/g, '');
            const amountNum = parseInt(valString, 10);

            if (!amountNum || amountNum <= 0) {
                alert('Vui lòng nhập số tiền tài trợ hợp lệ.');
                return;
            }

            const noteInput = document.getElementById('spon-note-input');
            const noteText = noteInput ? noteInput.value.trim() : '';

            const boot = window.ENTERPRISE_BOOT || {};
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
                sponsorSubmitBtn.textContent = 'Đang xử lý thanh toán...';
            }

            try {
                // 1. Pledge sponsorship
                const sponRes = await request('POST', '/businesses/me/sponsorships', {
                    projectId: activeSponsorProjectId,
                    amount: String(amountNum),
                    currency: 'VND',
                    note: noteText || 'Tài trợ phát triển dự án nghiên cứu sinh viên.',
                });

                const sponsorshipId = sponRes.id;

                // 2. Create Payment Order
                const paymentRes = await request('POST', '/businesses/me/payments', {
                    sponsorshipId: sponsorshipId,
                    provider: 'vnpay',
                });

                const orderId = paymentRes.id;

                // 3. Confirm Payment (Simulated Provider Callback)
                await request('POST', `/businesses/me/payments/${encodeURIComponent(orderId)}/confirm`, {
                    providerReference: 'VNPAY_' + Date.now(),
                });

                closeFormModal();

                showSuccessToast(`Tài trợ thành công ${(amountNum).toLocaleString('vi-VN')} VNĐ cho dự án "${project ? project.title : ''}". Giao dịch đã được xác nhận thanh toán!`);

                // Reload page after a brief moment to show updated funding progress
                setTimeout(() => {
                    window.location.reload();
                }, 1500);

            } catch (error) {
                alert(error?.message || 'Có lỗi xảy ra trong quá trình tài trợ dự án.');
            } finally {
                if (sponsorSubmitBtn) {
                    sponsorSubmitBtn.disabled = false;
                    sponsorSubmitBtn.textContent = 'Xác nhận tài trợ ngay';
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
                background-color: #0F172A;
                color: #FFFFFF;
                border-left: 4px solid #F97316;
                padding: 1.125rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
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
            <div style="width: 2rem; height: 2rem; border-radius: 50%; background: rgba(249,115,22,0.2); color: #F97316; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
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
