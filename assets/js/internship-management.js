/**
 * TalentHub Enterprise - Internship Management Controller
 * Handles list page search/filtering/sorting, status state toggles,
 * summary metrics recalculation, and create/edit form behavior.
 */

document.addEventListener('DOMContentLoaded', () => {
    initInternshipManagementModule();
});

function initInternshipManagementModule() {
    // DOM Elements for List Page
    const searchInput = document.getElementById('internship-search-input');
    const searchClearBtn = document.getElementById('internship-search-clear');
    const statusSelect = document.getElementById('filter-status-select');
    const fieldSelect = document.getElementById('filter-field-select');
    const sortSelect = document.getElementById('sort-select');
    const tbody = id('internship-cards-container') || id('internship-tbody');
    const emptyState = id('internships-empty-state');
    const resetSearchBtn = id('reset-search-btn');

    // Metrics DOM Elements
    const metricTotal = id('metric-total');
    const metricActive = id('metric-active');
    const metricDraft = id('metric-draft');
    const metricClosed = id('metric-closed');

    // Form DOM Elements
    const form = id('internship-form');
    const btnSaveDraft = id('btn-save-draft');
    const btnPublishPost = id('btn-publish-post');
    const selectedSkillsWrapper = id('form-selected-skills');
    const skillPickerContainer = id('skill-picker-container');

    function id(elementId) {
        return document.getElementById(elementId);
    }

    /* --------------------------------------------------------------------------
     * 1. Search, Filter & Sort Logic (Management List Page)
     * -------------------------------------------------------------------------- */
    if (tbody) {
        setupTableEventListeners();
        applyFiltersAndSort();
    }

    function setupTableEventListeners() {
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (searchClearBtn) {
                    searchClearBtn.style.display = searchInput.value.trim() ? 'block' : 'none';
                }
                applyFiltersAndSort();
            });
        }

        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                searchClearBtn.style.display = 'none';
                applyFiltersAndSort();
            });
        }

        if (statusSelect) statusSelect.addEventListener('change', applyFiltersAndSort);
        if (fieldSelect) fieldSelect.addEventListener('change', applyFiltersAndSort);
        if (sortSelect) sortSelect.addEventListener('change', applyFiltersAndSort);

        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                if (statusSelect) statusSelect.value = '';
                if (fieldSelect) fieldSelect.value = '';
                if (sortSelect) sortSelect.value = 'newest';
                if (searchClearBtn) searchClearBtn.style.display = 'none';
                applyFiltersAndSort();
            });
        }

        // Event Delegation for Action Dropdowns & Status Change Buttons
        document.addEventListener('click', (e) => {
            // Dropdown Toggle
            const toggleBtn = e.target.closest('.ent-dropdown-toggle');
            if (toggleBtn) {
                e.stopPropagation();
                const dropdown = toggleBtn.closest('.ent-dropdown');
                document.querySelectorAll('.ent-dropdown.is-open').forEach(d => {
                    if (d !== dropdown) d.classList.remove('is-open');
                });
                if (dropdown) dropdown.classList.toggle('is-open');
                if (dropdown) {
                    toggleBtn.setAttribute('aria-expanded', dropdown.classList.contains('is-open') ? 'true' : 'false');
                }
                return;
            }

            // Close open dropdowns when clicking outside
            if (!e.target.closest('.ent-dropdown')) {
                document.querySelectorAll('.ent-dropdown.is-open').forEach(d => {
                    d.classList.remove('is-open');
                    d.querySelector('.ent-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
                });
            }

            // Change Status Action Button Click
            const statusBtn = e.target.closest('.action-change-status');
            if (statusBtn) {
                const postId = statusBtn.getAttribute('data-post-id');
                const targetStatus = statusBtn.getAttribute('data-target-status');
                handleStatusChange(postId, targetStatus);
                const dropdown = statusBtn.closest('.ent-dropdown');
                if (dropdown) dropdown.classList.remove('is-open');
            }
        });
    }

    function applyFiltersAndSort() {
        if (!tbody) return;

        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const statusFilter = statusSelect ? statusSelect.value : '';
        const fieldFilter = fieldSelect ? fieldSelect.value : '';
        const sortVal = sortSelect ? sortSelect.value : 'newest';

        const rows = Array.from(tbody.querySelectorAll('[data-post-id]'));
        let visibleCount = 0;

        rows.forEach(row => {
            const title = row.getAttribute('data-title') || '';
            const status = row.getAttribute('data-status') || '';
            const field = row.getAttribute('data-field') || '';

            const matchesQuery = !query || title.includes(query);
            const matchesStatus = !statusFilter || status === statusFilter;
            
            let matchesField = false;
            if (!fieldFilter) {
                matchesField = true;
            } else {
                const categoryMappings = {
                    'Công nghệ thông tin': [
                        'công nghệ thông tin', 'ai', 'machine learning', 
                        'frontend', 'backend', 'fullstack', 'qa', 'tester',
                        'software', 'developer', 'kỹ thuật phần mềm', 
                        'khoa học dữ liệu', 'data', 'it', 'genai', 'trí tuệ nhân tạo', 'llm'
                    ],
                    'AI / Machine Learning': [
                        'ai', 'machine learning', 'trí tuệ nhân tạo', 'genai', 'llm', 'data', 'khoa học dữ liệu'
                    ],
                    'Khoa học Dữ liệu': [
                        'khoa học dữ liệu', 'data science', 'data analyst', 'data engineer', 'phân tích dữ liệu'
                    ],
                    'Kỹ thuật Phần mềm': [
                        'kỹ thuật phần mềm', 'software', 'developer', 'frontend', 'backend', 'fullstack', 'lập trình'
                    ],
                    'Marketing Digital': [
                        'marketing', 'digital', 'seo', 'content', 'truyền thông'
                    ],
                    'Thiết kế UI/UX': [
                        'thiết kế', 'ui', 'ux', 'design', 'graphic'
                    ]
                };

                const fieldLower = field.toLowerCase();
                const titleLower = title.toLowerCase();
                
                if (field === fieldFilter) {
                    matchesField = true;
                } else if (categoryMappings[fieldFilter]) {
                    matchesField = categoryMappings[fieldFilter].some(k => 
                        fieldLower.includes(k) || titleLower.includes(k)
                    );
                }
            }

            if (matchesQuery && matchesStatus && matchesField) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Handle Table & Cards Sorting
        const sortedRows = rows.filter(r => r.style.display !== 'none');
        sortedRows.sort((a, b) => {
            if (sortVal === 'applicants') {
                const numA = parseInt(a.querySelector('.ent-applicant-num')?.textContent || a.querySelector('.ent-applicant-count-badge')?.textContent || a.querySelector('.ent-applicant-count-text')?.textContent || '0', 10);
                const numB = parseInt(b.querySelector('.ent-applicant-num')?.textContent || b.querySelector('.ent-applicant-count-badge')?.textContent || b.querySelector('.ent-applicant-count-text')?.textContent || '0', 10);
                return numB - numA;
            } else if (sortVal === 'deadline') {
                const deadA = a.getAttribute('data-deadline') || a.querySelector('[data-meta="deadline"]')?.textContent.trim() || a.querySelectorAll('td')[3]?.textContent.trim() || '';
                const deadB = b.getAttribute('data-deadline') || b.querySelector('[data-meta="deadline"]')?.textContent.trim() || b.querySelectorAll('td')[3]?.textContent.trim() || '';
                return deadA.localeCompare(deadB);
            } else {
                // Newest by ID default
                const idA = parseInt(a.getAttribute('data-post-id'), 10) || 0;
                const idB = parseInt(b.getAttribute('data-post-id'), 10) || 0;
                return idB - idA;
            }
        });

        sortedRows.forEach(r => tbody.appendChild(r));

        if (tbody) {
            tbody.style.display = visibleCount === 0 ? 'none' : 'grid';
        }

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'flex' : 'none';
        }
    }

    /* --------------------------------------------------------------------------
     * 2. Status Transition Machine & Summary Metrics Recalculation
     * -------------------------------------------------------------------------- */
    async function handleStatusChange(postId, targetStatus) {
        const row = tbody ? tbody.querySelector(`[data-post-id="${postId}"]`) : null;
        if (!row) return;

        const expectedCurrentStatus = row.getAttribute('data-status') || '';
        const action = targetStatus === 'active' && expectedCurrentStatus === 'draft'
            ? 'publish'
            : (targetStatus === 'closed' && expectedCurrentStatus === 'active' ? 'close' : '');
        if (!action) {
            (window.showEntToast || showToast)('Chuyển trạng thái tin không hợp lệ.');
            return;
        }
        const bootNode = document.getElementById('enterprise-session-boot');
        let boot = {};
        try { boot = JSON.parse(bootNode?.textContent || '{}'); } catch { boot = {}; }
        const apiBase = boot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/app/api/v1/index.php' : '/app/api/v1/index.php');
        const csrf = boot.csrfToken || document.querySelector('input[name="csrfToken"]')?.value || '';
        try {
            const response = await fetch(`${apiBase}/businesses/me/internships/${encodeURIComponent(postId)}/${action}`, {
                method: 'POST', credentials: 'include',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ expectedCurrentStatus }),
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || payload?.data?.post?.status !== targetStatus) {
                const errorMsg = payload?.error?.message 
                    || (payload?.error?.details && Array.isArray(payload.error.details) ? payload.error.details.map(d => d.message).join(' ') : null)
                    || 'Không thể đổi trạng thái tin.';
                throw new Error(errorMsg);
            }
        } catch (error) {
            (window.showEntToast || showToast)(error?.message || 'Không thể đổi trạng thái tin.');
            return;
        }

        row.setAttribute('data-status', targetStatus);
        const statusCell = row.querySelector('.ent-status-pill-wrapper') || row.querySelector('.ent-status-pill');
        const actionMenu = row.querySelector('.ent-dropdown-menu');
        const actionHub = row.querySelector('.ent-job-card__actions');

        let statusLabel = 'Đang nhận hồ sơ';
        let pillClass = 'ent-status-pill--active';

        if (targetStatus === 'active') {
            statusLabel = 'Đang nhận hồ sơ';
            pillClass = 'ent-status-pill--active';
            if (actionHub) {
                const btnToggle = actionHub.querySelector('.ent-btn-toggle-status');
                if (btnToggle) {
                    btnToggle.outerHTML = `
                        <button type="button" class="ent-btn-toggle-status ent-btn-close-job action-change-status" data-post-id="${postId}" data-target-status="closed">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                            <span>Đóng tin</span>
                        </button>`;
                }
            }
            (window.showEntToast || showToast)(`Đã xuất bản tin tuyển dụng #${postId}. Tin hiện đang mở nhận hồ sơ.`);
        } else if (targetStatus === 'closed') {
            statusLabel = 'Đã đóng';
            pillClass = 'ent-status-pill--closed';
            if (actionHub) {
                const btnToggle = actionHub.querySelector('.ent-btn-toggle-status');
                if (btnToggle) {
                    btnToggle.outerHTML = `<span class="ent-status-closed-text">Đã đóng</span>`;
                }
            }
            (window.showEntToast || showToast)(`Đã đóng tin tuyển dụng #${postId}. Ngừng tiếp nhận hồ sơ.`);
        } else {
            statusLabel = 'Bản nháp';
            pillClass = 'ent-status-pill--draft';
            if (actionHub) {
                const btnToggle = actionHub.querySelector('.ent-btn-toggle-status');
                if (btnToggle) {
                    btnToggle.outerHTML = `
                        <button type="button" class="ent-btn-toggle-status ent-btn-publish-job action-change-status" data-post-id="${postId}" data-target-status="active">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Đăng tuyển</span>
                        </button>`;
                }
            }
            (window.showEntToast || showToast)(`Đã chuyển tin tuyển dụng #${postId} thành bản nháp.`);
        }

        if (statusCell) {
            statusCell.innerHTML = `
                <span class="ent-status-pill ${pillClass}">
                    <span class="dot"></span>
                    <span>${statusLabel}</span>
                </span>`;
        }

        recalculateMetrics();
        applyFiltersAndSort();
    }

    function recalculateMetrics() {
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('[data-post-id]'));
        let total = rows.length;
        let active = 0;
        let draft = 0;
        let closed = 0;

        rows.forEach(r => {
            const st = r.getAttribute('data-status');
            if (st === 'active') active++;
            else if (st === 'draft') draft++;
            else if (st === 'closed') closed++;
        });

        if (metricTotal) metricTotal.textContent = total;
        if (metricActive) metricActive.textContent = active;
        if (metricDraft) metricDraft.textContent = draft;
        if (metricClosed) metricClosed.textContent = closed;
    }

    /* --------------------------------------------------------------------------
     * 3. Create & Edit Form Interactivity (create.php)
     * -------------------------------------------------------------------------- */
    if (form) {
        const skillTree = form.querySelector('[data-skill-tree]');
        const selectedSkillsWrapper = id('form-selected-skills');
        const selectedCountEl = id('selected-skills-count');
        const btnClearSkills = id('btn-clear-skills');
        const skillsJsonInput = id('form-skills-json');
        let selectedSkills = [];

        function syncSkillsHidden() {
            if (skillsJsonInput) skillsJsonInput.value = JSON.stringify(selectedSkills.map((s) => s.name));
            if (selectedCountEl) selectedCountEl.textContent = String(selectedSkills.length);
            if (btnClearSkills) btnClearSkills.hidden = selectedSkills.length === 0;
            if (selectedSkillsWrapper) {
                if (selectedSkills.length === 0) {
                    selectedSkillsWrapper.innerHTML = '<span class="ent-skill-empty-tip text-muted">Chưa có kỹ năng nào được chọn. Hãy chọn từ danh sách bên dưới.</span>';
                } else {
                    selectedSkillsWrapper.innerHTML = selectedSkills.map((sk) =>
                        '<span class="ent-skill-tag" data-remove-skill="' + escapeHtml(sk.id) + '">' +
                        escapeHtml(sk.name) +
                        '<button type="button" aria-label="Bỏ ' + escapeHtml(sk.name) + '">&times;</button></span>'
                    ).join('');
                }
            }
        }

        function collectSelectedFromTree() {
            selectedSkills = [];
            if (!skillTree) return;
            skillTree.querySelectorAll('[data-skill-tree-leaf] input[type="checkbox"]:checked').forEach((input) => {
                const leaf = input.closest('[data-skill-tree-leaf]');
                if (!(leaf instanceof HTMLElement)) return;
                selectedSkills.push({
                    id: leaf.getAttribute('data-skill-id') || input.value,
                    name: leaf.getAttribute('data-skill-label') || input.value,
                });
            });
            syncSkillsHidden();
        }

        function syncParent(node) {
            const parent = node.querySelector('[data-skill-tree-parent]');
            const leaves = [...node.querySelectorAll('[data-skill-tree-leaf] input[type="checkbox"]')]
                .filter((input) => input instanceof HTMLInputElement && !input.closest('.is-filtered-out'));
            if (!(parent instanceof HTMLInputElement)) return;
            if (leaves.length === 0) {
                parent.checked = false;
                parent.indeterminate = false;
                return;
            }
            const checked = leaves.filter((input) => input.checked).length;
            parent.checked = checked === leaves.length;
            parent.indeterminate = checked > 0 && checked < leaves.length;
        }

        function setOpen(node, open) {
            const children = node.querySelector('.ent-skill-tree__children');
            node.classList.toggle('is-open', open);
            node.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (children instanceof HTMLElement) children.hidden = !open;
        }

        if (skillTree instanceof HTMLElement) {
            skillTree.querySelectorAll('[data-skill-tree-node]').forEach((node) => {
                if (!(node instanceof HTMLElement)) return;
                const parent = node.querySelector('[data-skill-tree-parent]');
                if (parent instanceof HTMLInputElement && parent.getAttribute('data-indeterminate') === '1') {
                    parent.indeterminate = true;
                    parent.removeAttribute('data-indeterminate');
                }
                syncParent(node);
                const toggle = node.querySelector('[data-skill-tree-toggle]');
                if (toggle instanceof HTMLButtonElement) {
                    toggle.addEventListener('click', () => setOpen(node, !node.classList.contains('is-open')));
                }
                if (parent instanceof HTMLInputElement) {
                    parent.addEventListener('change', () => {
                        node.querySelectorAll('[data-skill-tree-leaf] input[type="checkbox"]').forEach((input) => {
                            if (!(input instanceof HTMLInputElement) || input.closest('.is-filtered-out')) return;
                            input.checked = parent.checked;
                        });
                        parent.indeterminate = false;
                        if (parent.checked) setOpen(node, true);
                        collectSelectedFromTree();
                    });
                }
                node.querySelectorAll('[data-skill-tree-leaf] input[type="checkbox"]').forEach((input) => {
                    if (!(input instanceof HTMLInputElement)) return;
                    input.addEventListener('change', () => {
                        syncParent(node);
                        collectSelectedFromTree();
                    });
                });
            });
            if (selectedSkillsWrapper) {
                selectedSkillsWrapper.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-remove-skill]');
                    if (!btn) return;
                    const skillId = btn.getAttribute('data-remove-skill');
                    const leafInput = [...skillTree.querySelectorAll('[data-skill-tree-leaf]')].find((el) => el.getAttribute('data-skill-id') === skillId)?.querySelector('input[type="checkbox"]');
                    if (leafInput instanceof HTMLInputElement) {
                        leafInput.checked = false;
                        const node = leafInput.closest('[data-skill-tree-node]');
                        if (node instanceof HTMLElement) syncParent(node);
                    }
                    collectSelectedFromTree();
                });
            }
            if (btnClearSkills) {
                btnClearSkills.addEventListener('click', () => {
                    skillTree.querySelectorAll('[data-skill-tree-leaf] input[type="checkbox"]').forEach((input) => {
                        if (input instanceof HTMLInputElement) input.checked = false;
                    });
                    skillTree.querySelectorAll('[data-skill-tree-node]').forEach((node) => {
                        if (node instanceof HTMLElement) syncParent(node);
                    });
                    collectSelectedFromTree();
                });
            }
            collectSelectedFromTree();
        }

        // Audience & Target Schools Management
        const audienceRadios = document.querySelectorAll('input[name="audience"]');
        const targetSchoolsContainer = document.getElementById('target-schools-container');
        const targetSchoolCheckboxes = document.querySelectorAll('.target-school-checkbox');
        const targetSchoolsCountEl = document.getElementById('target-schools-count');

        function updateTargetSchoolsCount() {
            if (!targetSchoolsCountEl) return;
            const count = document.querySelectorAll('.target-school-checkbox:checked').length;
            targetSchoolsCountEl.textContent = count;
        }

        audienceRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.ent-radio-card').forEach(card => {
                    const cardRadio = card.querySelector('input[type="radio"]');
                    if (cardRadio && cardRadio.checked) {
                        card.classList.add('border-primary', 'bg-light');
                    } else {
                        card.classList.remove('border-primary', 'bg-light');
                    }
                });

                if (targetSchoolsContainer) {
                    targetSchoolsContainer.style.display = radio.value === 'partner_schools' && radio.checked ? 'block' : 'none';
                }
            });
        });

        targetSchoolCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateTargetSchoolsCount);
        });

        // Form Submit Buttons
        if (btnSaveDraft) {
            btnSaveDraft.addEventListener('click', () => submitForm('draft'));
        }

        if (btnPublishPost) {
            btnPublishPost.addEventListener('click', () => submitForm('active'));
        }

        async function submitForm(targetStatus) {
            const titleInput = id('form-title');
            const fieldInput = id('form-field');
            const descInput = id('form-description');
            const locationInput = id('form-location');

            if (titleInput && !titleInput.value.trim()) {
                titleInput.focus();
                showToast('Vui lòng nhập tiêu đề tuyển dụng.');
                return;
            }
            if (fieldInput && !fieldInput.value) {
                fieldInput.focus();
                showToast('Vui lòng chọn lĩnh vực chuyên môn.');
                return;
            }
            if (descInput && !descInput.value.trim()) {
                descInput.focus();
                showToast('Vui lòng nhập mô tả công việc.');
                return;
            }
            if (locationInput && !locationInput.value.trim()) {
                locationInput.focus();
                showToast('Vui lòng nhập địa điểm làm việc.');
                return;
            }
            if (selectedSkills.length === 0) {
                showToast('Vui lòng chọn ít nhất 1 kỹ năng yêu cầu cho vị trí tuyển dụng.');
                return;
            }

            const audience = document.querySelector('input[name="audience"]:checked')?.value || 'public';
            const targetSchoolIds = [];
            if (audience === 'partner_schools') {
                document.querySelectorAll('.target-school-checkbox:checked').forEach(cb => {
                    targetSchoolIds.push(cb.value);
                });
                if (targetSchoolIds.length === 0) {
                    showToast('Vui lòng chọn ít nhất 1 trường đối tác cho vị trí tuyển dụng.');
                    return;
                }
            }

            const postId = id('form-post-id') ? id('form-post-id').value : '';
            const bootNode = document.getElementById('enterprise-session-boot');
            let boot = {};
            try { boot = JSON.parse(bootNode?.textContent || '{}'); } catch { boot = {}; }
            const deadlineValue = id('form-deadline')?.value || '';
            const payload = {
                title: titleInput.value.trim(),
                field: fieldInput.value,
                slots: intval(id('form-slots')?.value),
                workType: id('form-work-type')?.value || '',
                duration: id('form-duration')?.value || '',
                educationLevel: id('form-edu-level')?.value || '',
                deadline: deadlineValue ? `${deadlineValue} 23:59:59.000000` : '',
                location: locationInput?.value.trim() || '',
                description: descInput.value.trim(),
                benefits: id('form-benefits')?.value.trim() || '',
                skills: selectedSkills.map((skill) => skill.name),
                requirements: [],
                audience: audience,
                targetSchoolIds: targetSchoolIds,
            };
            const request = async (method, path, body) => {
                const apiBase = boot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/app/api/v1/index.php' : '/app/api/v1/index.php');
                const csrf = boot.csrfToken || document.querySelector('input[name="csrfToken"]')?.value || '';
                const response = await fetch(`${apiBase}${path}`, {
                    method,
                    credentials: 'include',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
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
            btnSaveDraft && (btnSaveDraft.disabled = true);
            btnPublishPost && (btnPublishPost.disabled = true);
            try {
                let post;
                if (postId) {
                    post = (await request('PATCH', `/businesses/me/internships/${encodeURIComponent(postId)}`, payload)).post;
                } else {
                    post = (await request('POST', '/businesses/me/internships', payload)).post;
                }
                if (targetStatus === 'active' && post.status === 'draft') {
                    post = (await request('POST', `/businesses/me/internships/${encodeURIComponent(post.id)}/publish`, { expectedCurrentStatus: 'draft' })).post;
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Đăng tin thành công!',
                    text: 'Tin tuyển dụng của bạn đã được cập nhật lên hệ thống.',
                    confirmButtonText: 'Hoàn tất',
                    confirmButtonColor: '#f97316'
                }).then((result) => {
                    window.location.href = 'index.php';
                });
            } catch (error) {
                console.error('Submit internship post error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Thất bại!',
                    text: error?.message || 'Không thể lưu tin tuyển dụng. Vui lòng thử lại.',
                    confirmButtonText: 'Đóng',
                    confirmButtonColor: '#f97316'
                });
            } finally {
                btnSaveDraft && (btnSaveDraft.disabled = false);
                btnPublishPost && (btnPublishPost.disabled = false);
            }
        }
    }

    function showToast(msg) {
        if (window.showEntToast) {
            window.showEntToast(msg);
        } else {
            let toast = document.getElementById('ent-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ent-toast';
                toast.className = 'ent-toast';
                toast.innerHTML = '<div class="ent-toast__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg><span class="ent-toast__message"></span></div>';
                document.body.appendChild(toast);
            }
            const msgEl = toast.querySelector('.ent-toast__message');
            if (msgEl) msgEl.textContent = msg;
            toast.classList.add('is-visible');
            setTimeout(() => toast.classList.remove('is-visible'), 4000);
        }
    }

    function intval(val) {
        return parseInt(val || '0', 10);
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}
