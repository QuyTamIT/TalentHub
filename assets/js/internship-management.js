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
                return;
            }

            // Close open dropdowns when clicking outside
            if (!e.target.closest('.ent-dropdown')) {
                document.querySelectorAll('.ent-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
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
            const matchesField = !fieldFilter || field === fieldFilter;

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

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
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
        const apiBase = boot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/api/v1' : '/api/v1');
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
    // Master Mapping Specifications & Mock Skills DB
    const FIELD_TECHNICAL_SKILLS = {
        'Công nghệ thông tin': [
            'Python', 'Java', 'C++', 'SQL', 'Git', 'Networking', 'Linux'
        ],
        'AI / Machine Learning': [
            'Python', 'Machine Learning', 'Deep Learning', 'TensorFlow', 'PyTorch', 'Computer Vision', 'NLP', 'Data Analysis'
        ],
        'Thiết kế UI/UX': [
            'Figma', 'UI/UX', 'Wireframing', 'Prototyping', 'User Research', 'Design System'
        ],
        'Marketing Digital': [
            'Digital Marketing', 'SEO', 'Content Writing', 'Social Media', 'Google Ads', 'Analytics'
        ],
        'Khoa học Dữ liệu': [
            'Python', 'SQL', 'Pandas', 'NumPy', 'Data Analysis', 'Data Visualization', 'Power BI', 'Machine Learning', 'PyTorch'
        ],
        'Kỹ thuật Phần mềm': [
            'Java', 'JavaScript', 'TypeScript', 'PHP', 'Laravel', 'React', 'Node.js', 'REST API', 'Git', 'Docker', 'SQL'
        ]
    };

    const SOFT_SKILLS = [
        'Communication', 'Teamwork', 'Problem Solving', 'Leadership', 'Time Management', 'Critical Thinking'
    ];

    // Master Mock Skills Database (Maps to `skills` DB table: id, name, category)
    const MOCK_SKILLS_DB = [
        { id: 'sk-101', name: 'Python', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-102', name: 'Java', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-103', name: 'C++', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-104', name: 'SQL', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-105', name: 'Git', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-106', name: 'Networking', category: 'Công nghệ thông tin', type: 'tech' },
        { id: 'sk-107', name: 'Linux', category: 'Công nghệ thông tin', type: 'tech' },

        { id: 'sk-201', name: 'Machine Learning', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-202', name: 'Deep Learning', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-203', name: 'TensorFlow', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-204', name: 'PyTorch', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-205', name: 'Computer Vision', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-206', name: 'NLP', category: 'AI / Machine Learning', type: 'tech' },
        { id: 'sk-207', name: 'Data Analysis', category: 'AI / Machine Learning', type: 'tech' },

        { id: 'sk-301', name: 'Figma', category: 'Thiết kế UI/UX', type: 'tech' },
        { id: 'sk-302', name: 'UI/UX', category: 'Thiết kế UI/UX', type: 'tech' },
        { id: 'sk-303', name: 'Wireframing', category: 'Thiết kế UI/UX', type: 'tech' },
        { id: 'sk-304', name: 'Prototyping', category: 'Thiết kế UI/UX', type: 'tech' },
        { id: 'sk-305', name: 'User Research', category: 'Thiết kế UI/UX', type: 'tech' },
        { id: 'sk-306', name: 'Design System', category: 'Thiết kế UI/UX', type: 'tech' },

        { id: 'sk-401', name: 'Digital Marketing', category: 'Marketing Digital', type: 'tech' },
        { id: 'sk-402', name: 'SEO', category: 'Marketing Digital', type: 'tech' },
        { id: 'sk-403', name: 'Content Writing', category: 'Marketing Digital', type: 'tech' },
        { id: 'sk-404', name: 'Social Media', category: 'Marketing Digital', type: 'tech' },
        { id: 'sk-405', name: 'Google Ads', category: 'Marketing Digital', type: 'tech' },
        { id: 'sk-406', name: 'Analytics', category: 'Marketing Digital', type: 'tech' },

        { id: 'sk-501', name: 'Pandas', category: 'Khoa học Dữ liệu', type: 'tech' },
        { id: 'sk-502', name: 'NumPy', category: 'Khoa học Dữ liệu', type: 'tech' },
        { id: 'sk-503', name: 'Data Visualization', category: 'Khoa học Dữ liệu', type: 'tech' },
        { id: 'sk-504', name: 'Power BI', category: 'Khoa học Dữ liệu', type: 'tech' },

        { id: 'sk-601', name: 'JavaScript', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-602', name: 'TypeScript', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-603', name: 'PHP', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-604', name: 'Laravel', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-605', name: 'React', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-606', name: 'Node.js', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-607', name: 'REST API', category: 'Kỹ thuật Phần mềm', type: 'tech' },
        { id: 'sk-608', name: 'Docker', category: 'Kỹ thuật Phần mềm', type: 'tech' },

        { id: 'sk-901', name: 'Communication', category: 'Kỹ năng mềm', type: 'soft' },
        { id: 'sk-902', name: 'Teamwork', category: 'Kỹ năng mềm', type: 'soft' },
        { id: 'sk-903', name: 'Problem Solving', category: 'Kỹ năng mềm', type: 'soft' },
        { id: 'sk-904', name: 'Leadership', category: 'Kỹ năng mềm', type: 'soft' },
        { id: 'sk-905', name: 'Time Management', category: 'Kỹ năng mềm', type: 'soft' },
        { id: 'sk-906', name: 'Critical Thinking', category: 'Kỹ năng mềm', type: 'soft' }
    ];

    if (form) {
        const fieldSelect = id('form-field');
        const selectedSkillsWrapper = id('form-selected-skills');
        const skillPickerContainer = id('skill-picker-container');
        const techSuggestionsContainer = id('tech-skills-suggestions');
        const softSuggestionsContainer = id('soft-skills-suggestions');
        const techFieldLabel = id('tech-skill-field-label');
        const selectedCountEl = id('selected-skills-count');
        const btnClearSkills = id('btn-clear-skills');
        const customSkillInput = id('input-custom-skill');
        const btnAddCustomSkill = id('btn-add-custom-skill');
        const searchResultsContainer = id('custom-skill-search-results');

        // Internal State array of selected skill objects: { name, type, category, skillId }
        let selectedSkills = [];

        // Parse initial skills from data attribute (if editing or pre-filled)
        let rawInitialSkills = [];
        if (skillPickerContainer && skillPickerContainer.getAttribute('data-initial-skills')) {
            try {
                rawInitialSkills = JSON.parse(skillPickerContainer.getAttribute('data-initial-skills')) || [];
            } catch (e) {
                rawInitialSkills = [];
            }
        }

        // Initialize Skill Picker Component
        initSkillPicker();

        function initSkillPicker() {
            renderSoftSkillsSuggestions();

            const initialField = fieldSelect ? fieldSelect.value : '';
            if (initialField) {
                updateTechSuggestions(initialField);
            } else {
                renderEmptyTechHint();
            }

            // Populate initial selected skills
            if (rawInitialSkills && rawInitialSkills.length > 0) {
                rawInitialSkills.forEach(skName => {
                    addSkillObject(createSkillObject(skName, initialField));
                });
            }

            renderSelectedSkillTags();
            updateChipStates();

            // Field Change Listener
            if (fieldSelect) {
                fieldSelect.addEventListener('change', handleFieldChange);
            }

            // Suggestion Chip Clicks (Technical & Soft)
            if (techSuggestionsContainer) {
                techSuggestionsContainer.addEventListener('click', (e) => {
                    const pill = e.target.closest('.ent-chip-pill');
                    if (pill) {
                        const skillName = pill.getAttribute('data-skill');
                        toggleSkill(skillName, 'tech');
                    }
                });
            }

            if (softSuggestionsContainer) {
                softSuggestionsContainer.addEventListener('click', (e) => {
                    const pill = e.target.closest('.ent-chip-pill');
                    if (pill) {
                        const skillName = pill.getAttribute('data-skill');
                        toggleSkill(skillName, 'soft');
                    }
                });
            }

            // Tag Removals & Clear All
            if (selectedSkillsWrapper) {
                selectedSkillsWrapper.addEventListener('click', (e) => {
                    const removeBtn = e.target.closest('.remove-skill-btn');
                    if (removeBtn) {
                        const tag = removeBtn.closest('.skill-tag');
                        if (tag) {
                            const skillName = tag.getAttribute('data-skill');
                            removeSkillByName(skillName);
                        }
                    }
                });
            }

            if (btnClearSkills) {
                btnClearSkills.addEventListener('click', () => {
                    selectedSkills = [];
                    renderSelectedSkillTags();
                    updateChipStates();
                });
            }

            // Search / Custom Skill Input
            if (customSkillInput) {
                customSkillInput.addEventListener('input', handleSearchInput);
                customSkillInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitCustomSkillInput();
                    }
                });
            }

            if (btnAddCustomSkill) {
                btnAddCustomSkill.addEventListener('click', (e) => {
                    e.preventDefault();
                    submitCustomSkillInput();
                });
            }

            // Close search results dropdown on outside click
            document.addEventListener('click', (e) => {
                if (searchResultsContainer && !e.target.closest('.ent-skill-search-wrapper')) {
                    searchResultsContainer.style.display = 'none';
                }
            });
        }

        // Field change handler: updates tech suggestions, validates existing tech skills, keeps soft skills
        function handleFieldChange() {
            const newField = fieldSelect ? fieldSelect.value : '';

            if (!newField) {
                renderEmptyTechHint();
                renderSelectedSkillTags();
                updateChipStates();
                return;
            }

            updateTechSuggestions(newField);

            const allowedTechList = FIELD_TECHNICAL_SKILLS[newField] || [];
            let removedCount = 0;

            // Filter out technical skills that are no longer valid for the new field
            selectedSkills = selectedSkills.filter(s => {
                if (s.type === 'soft') return true; // Soft skills remain intact
                
                // For technical or custom skills, verify if allowed in new field
                const isAllowed = allowedTechList.includes(s.name);
                if (!isAllowed) {
                    removedCount++;
                }
                return isAllowed;
            });

            if (removedCount > 0) {
                showToast('Một số kỹ năng không phù hợp với lĩnh vực mới đã được bỏ chọn.');
            }

            renderSelectedSkillTags();
            updateChipStates();
        }

        function renderEmptyTechHint() {
            if (techFieldLabel) techFieldLabel.textContent = 'Gợi ý theo lĩnh vực: Vui lòng chọn lĩnh vực ở trên';
            if (techSuggestionsContainer) {
                techSuggestionsContainer.innerHTML = `<span class="text-muted" style="font-size: 0.8125rem; font-style: italic;">Vui lòng chọn một Lĩnh vực chuyên môn để xem danh sách gợi ý kỹ năng phù hợp.</span>`;
            }
        }

        function renderSoftSkillsSuggestions() {
            if (!softSuggestionsContainer) return;
            softSuggestionsContainer.innerHTML = SOFT_SKILLS.map(sk => `
                <button type="button" class="ent-chip-pill ent-chip-pill--soft" data-skill="${escapeHtml(sk)}">
                    <span class="chip-label">+ ${escapeHtml(sk)}</span>
                </button>
            `).join('');
        }

        function updateTechSuggestions(field) {
            if (techFieldLabel) {
                techFieldLabel.textContent = `Gợi ý theo lĩnh vực: ${field}`;
            }

            const techList = FIELD_TECHNICAL_SKILLS[field] || [];
            if (techSuggestionsContainer) {
                if (techList.length === 0) {
                    techSuggestionsContainer.innerHTML = `<span class="text-muted" style="font-size: 0.8125rem; font-style: italic;">Chưa có gợi ý kỹ năng cho lĩnh vực này.</span>`;
                } else {
                    techSuggestionsContainer.innerHTML = techList.map(sk => `
                        <button type="button" class="ent-chip-pill ent-chip-pill--tech" data-skill="${escapeHtml(sk)}">
                            <span class="chip-label">+ ${escapeHtml(sk)}</span>
                        </button>
                    `).join('');
                }
            }
        }

        function createSkillObject(skillName, currentField) {
            const trimmed = skillName.trim();
            const softMatch = SOFT_SKILLS.find(s => s.toLowerCase() === trimmed.toLowerCase());
            if (softMatch) {
                const dbMatch = MOCK_SKILLS_DB.find(s => s.name === softMatch);
                return {
                    name: softMatch,
                    type: 'soft',
                    category: 'Kỹ năng mềm',
                    skillId: dbMatch ? dbMatch.id : 'sk-soft-custom'
                };
            }

            const dbMatch = MOCK_SKILLS_DB.find(s => s.name.toLowerCase() === trimmed.toLowerCase());
            if (dbMatch) {
                return {
                    name: dbMatch.name,
                    type: 'tech',
                    category: dbMatch.category,
                    skillId: dbMatch.id
                };
            }

            return {
                name: trimmed,
                type: 'tech',
                category: currentField || 'Khác',
                skillId: 'sk-custom-' + Date.now()
            };
        }

        function toggleSkill(skillName, defaultType) {
            const existingIdx = selectedSkills.findIndex(s => s.name.toLowerCase() === skillName.toLowerCase());
            if (existingIdx !== -1) {
                selectedSkills.splice(existingIdx, 1);
            } else {
                const currentField = fieldSelect ? fieldSelect.value : '';
                const skObj = createSkillObject(skillName, currentField);
                if (defaultType) skObj.type = defaultType;
                selectedSkills.push(skObj);
            }
            renderSelectedSkillTags();
            updateChipStates();
        }

        function addSkillObject(skObj) {
            if (!skObj || !skObj.name) return;
            const exists = selectedSkills.some(s => s.name.toLowerCase() === skObj.name.toLowerCase());
            if (!exists) {
                selectedSkills.push(skObj);
            }
        }

        function removeSkillByName(skillName) {
            selectedSkills = selectedSkills.filter(s => s.name.toLowerCase() !== skillName.toLowerCase());
            renderSelectedSkillTags();
            updateChipStates();
        }

        function renderSelectedSkillTags() {
            if (!selectedSkillsWrapper) return;

            if (selectedSkills.length === 0) {
                selectedSkillsWrapper.innerHTML = `<span class="ent-skill-empty-tip text-muted">Chưa có kỹ năng nào được chọn. Hãy chọn từ danh sách gợi ý bên dưới hoặc gõ để tìm kiếm.</span>`;
                if (selectedCountEl) selectedCountEl.textContent = '0';
                if (btnClearSkills) btnClearSkills.style.display = 'none';
                return;
            }

            if (selectedCountEl) selectedCountEl.textContent = selectedSkills.length;
            if (btnClearSkills) btnClearSkills.style.display = 'inline-block';

            selectedSkillsWrapper.innerHTML = selectedSkills.map(sk => `
                <span class="skill-tag skill-tag--removable" data-skill="${escapeHtml(sk.name)}">
                    <span>${escapeHtml(sk.name)}</span>
                    <button type="button" class="remove-skill-btn" title="Bỏ chọn">&times;</button>
                </span>
            `).join('');
        }

        function updateChipStates() {
            // Update Tech Pills
            if (techSuggestionsContainer) {
                techSuggestionsContainer.querySelectorAll('.ent-chip-pill').forEach(pill => {
                    const name = pill.getAttribute('data-skill');
                    const isSelected = selectedSkills.some(s => s.name.toLowerCase() === name.toLowerCase());
                    const labelSpan = pill.querySelector('.chip-label');
                    if (isSelected) {
                        pill.classList.add('is-selected');
                        if (labelSpan) labelSpan.innerHTML = `✓ ${escapeHtml(name)}`;
                    } else {
                        pill.classList.remove('is-selected');
                        if (labelSpan) labelSpan.innerHTML = `+ ${escapeHtml(name)}`;
                    }
                });
            }

            // Update Soft Pills
            if (softSuggestionsContainer) {
                softSuggestionsContainer.querySelectorAll('.ent-chip-pill').forEach(pill => {
                    const name = pill.getAttribute('data-skill');
                    const isSelected = selectedSkills.some(s => s.name.toLowerCase() === name.toLowerCase());
                    const labelSpan = pill.querySelector('.chip-label');
                    if (isSelected) {
                        pill.classList.add('is-selected');
                        if (labelSpan) labelSpan.innerHTML = `✓ ${escapeHtml(name)}`;
                    } else {
                        pill.classList.remove('is-selected');
                        if (labelSpan) labelSpan.innerHTML = `+ ${escapeHtml(name)}`;
                    }
                });
            }
        }

        // Search Input Handling
        function handleSearchInput() {
            if (!searchResultsContainer || !customSkillInput) return;
            const query = customSkillInput.value.trim().toLowerCase();

            if (!query) {
                searchResultsContainer.style.display = 'none';
                return;
            }

            const currentField = fieldSelect ? fieldSelect.value : '';
            const fieldTechs = FIELD_TECHNICAL_SKILLS[currentField] || [];

            // Prioritize skills matching query: tech skills for current field first, soft skills, then other tech skills
            const matches = MOCK_SKILLS_DB.filter(s => {
                const nameMatches = s.name.toLowerCase().includes(query);
                if (!nameMatches) return false;
                // If it's a tech skill, only show if it belongs to current field or soft skills (prioritize field)
                if (s.type === 'tech') {
                    return fieldTechs.includes(s.name) || s.category === currentField;
                }
                return true;
            });

            if (matches.length === 0) {
                searchResultsContainer.innerHTML = `
                    <div class="ent-skill-search-item text-muted" id="search-item-custom-add">
                        <span>Bấm Enter hoặc "+ Thêm" để thêm "<strong>${escapeHtml(query)}</strong>"</span>
                    </div>
                `;
            } else {
                searchResultsContainer.innerHTML = matches.map(s => {
                    const isAlready = selectedSkills.some(sel => sel.name.toLowerCase() === s.name.toLowerCase());
                    return `
                        <div class="ent-skill-search-item" data-skill="${escapeHtml(s.name)}" data-type="${s.type}">
                            <span>${escapeHtml(s.name)} <small class="text-muted">(${escapeHtml(s.category)})</small></span>
                            <span class="badge ${isAlready ? 'bg-secondary' : 'bg-primary'}">${isAlready ? 'Đã chọn' : '+ Chọn'}</span>
                        </div>
                    `;
                }).join('');
            }

            searchResultsContainer.style.display = 'block';

            // Item clicks in dropdown
            searchResultsContainer.querySelectorAll('.ent-skill-search-item[data-skill]').forEach(item => {
                item.addEventListener('click', () => {
                    const skName = item.getAttribute('data-skill');
                    const skType = item.getAttribute('data-type');
                    toggleSkill(skName, skType);
                    customSkillInput.value = '';
                    searchResultsContainer.style.display = 'none';
                });
            });
        }

        function submitCustomSkillInput() {
            if (!customSkillInput) return;
            const val = customSkillInput.value.trim();
            if (!val) return;

            const currentField = fieldSelect ? fieldSelect.value : '';
            const skObj = createSkillObject(val, currentField);
            addSkillObject(skObj);

            renderSelectedSkillTags();
            updateChipStates();

            customSkillInput.value = '';
            if (searchResultsContainer) searchResultsContainer.style.display = 'none';
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
                const apiBase = boot.apiBase || (window.location.pathname.includes('/TalentHub') ? '/TalentHub/api/v1' : '/api/v1');
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
            alert(msg);
        }
    }

    function intval(val) {
        return parseInt(val || '0', 10);
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}
