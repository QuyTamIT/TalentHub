/**
 * TalentHub - Enterprise Talent Search Controller
 * Handles client-side search, multi-criteria filtering, quick filter pills,
 * popular & categorized skills selection with AND logic, pagination, and profile actions.
 * 
 * Note for Developers:
 * - Skill categories and mock data structure simulate database tables `skills` (id, name, category)
 *   and `student_skills` (studentId, skillId, level, verifiedStatus).
 * - When backend API is connected, replace local Sets and arrays with endpoint calls.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTalentSearchModule();
});

function initTalentSearchModule() {
    // 1. Load mock data from inline JSON
    const mockDataElement = document.getElementById('talents-mock-data');
    if (!mockDataElement) return;

    let allTalents = [];
    try {
        allTalents = JSON.parse(mockDataElement.textContent);
    } catch (e) {
        console.error('Failed to parse talents mock data:', e);
        return;
    }

    // 2. Structured Skill Categories (maps to DB table `skills`)
    const SKILL_CATEGORIES = [
        {
            id: 'tech',
            name: 'Công nghệ',
            skills: ['Python', 'JavaScript', 'PHP', 'Node.js', 'C++', 'Java', 'C#', 'React', 'Vue.js', 'TypeScript', 'Laravel', 'HTML/CSS', 'REST API', 'Mobile App', 'Git', 'Docker']
        },
        {
            id: 'data_ai',
            name: 'Dữ liệu & AI',
            skills: ['AI / Machine Learning', 'Data Analysis', 'PyTorch', 'TensorFlow', 'SQL', 'Pandas', 'Data Analytics']
        },
        {
            id: 'design',
            name: 'Thiết kế',
            skills: ['UI/UX', 'Figma', 'Photoshop', 'Prototyping', 'User Research', 'Thiết kế đồ họa']
        },
        {
            id: 'business_marketing',
            name: 'Kinh doanh / Marketing',
            skills: ['Digital Marketing', 'SEO', 'Google Analytics', 'Content Marketing', 'Social Ads', 'Quản lý dự án']
        },
        {
            id: 'soft_skills',
            name: 'Kỹ năng mềm',
            skills: ['Communication', 'Leadership', 'Giải quyết vấn đề', 'Làm việc nhóm', 'Tư duy phản biện', 'Quản lý thời gian']
        }
    ];

    // State Variables
    let currentSearchQuery = '';
    const activeQuickFilters = new Set();
    const selectedSkillsSet = new Set(); // Stores all selected skill names

    const activeFilters = {
        eduLevel: '',
        school: '',
        classYear: '',
        majorField: '',
        matchScore: 0,
        expHours: 0,
        readiness: ''
    };
    let currentSortOption = 'matching';
    let currentPage = 1;
    const PAGE_SIZE = 6;

    // DOM Elements
    const searchInput = document.getElementById('talent-search-input');
    const searchClearBtn = document.getElementById('talent-search-clear');
    const quickFilterBtns = document.querySelectorAll('.ent-quick-pill');
    
    const filterEduLevel = document.getElementById('filter-edu-level');
    const filterSchool = document.getElementById('filter-school');
    const filterClassYear = document.getElementById('filter-class-year');
    const filterMajorField = document.getElementById('filter-major-field');
    const filterMatchScore = document.getElementById('filter-match-score');
    const filterExpHours = document.getElementById('filter-exp-hours');
    const filterReadiness = document.getElementById('filter-readiness');
    
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const resetHeaderBtn = document.getElementById('filter-reset-btn');
    const emptyResetBtn = document.getElementById('empty-reset-btn');
    
    const sortSelect = document.getElementById('talent-sort-select');
    const cardsContainer = document.getElementById('talent-cards-container');
    const emptyStateEl = document.getElementById('talent-empty-state');
    const paginationWrapper = document.getElementById('talent-pagination');
    const paginationInfo = document.getElementById('pagination-info');
    const paginationBtns = document.getElementById('pagination-btns');
    
    const totalBadgeNum = document.getElementById('ent-count-num');
    const mobileFilterBtn = document.getElementById('mobile-filter-toggle');
    const mobileFilterCount = document.getElementById('mobile-filter-count');
    const filterCard = document.getElementById('ent-filter-card');

    // Skill Selector DOM Elements
    const popularSkillsContainer = document.getElementById('popular-skills-container');
    const selectedSkillsWrapper = document.getElementById('selected-skills-wrapper');
    const selectedSkillsTags = document.getElementById('selected-skills-tags');
    const selectedSkillsCount = document.getElementById('selected-skills-count');

    // Skill Modal Elements
    const skillsModal = document.getElementById('skills-selector-modal');
    const openSkillsModalBtn = document.getElementById('open-skills-modal-btn');
    const closeSkillsModalBtn = document.getElementById('close-skills-modal-btn');
    const skillsModalBackdrop = document.getElementById('skills-modal-backdrop');
    const skillSearchInput = document.getElementById('skill-search-input');
    const skillsCategoriesContainer = document.getElementById('skills-categories-container');
    const modalSelectedCount = document.getElementById('modal-selected-count');
    const confirmSkillsBtn = document.getElementById('confirm-skills-btn');

    // 3. Initial Setup & Event Listeners
    setupEventListeners();
    buildModalCategoriesUI();
    updateAndRender();

    function setupEventListeners() {
        // Search Input Handling
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                currentSearchQuery = e.target.value.trim().toLowerCase();
                if (searchClearBtn) {
                    searchClearBtn.style.display = currentSearchQuery.length > 0 ? 'block' : 'none';
                }
                currentPage = 1;
                updateAndRender();
            });
        }

        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                currentSearchQuery = '';
                searchClearBtn.style.display = 'none';
                currentPage = 1;
                updateAndRender();
            });
        }

        // Quick Filter Pills Handling
        quickFilterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute('data-quick-filter');
                if (activeQuickFilters.has(key)) {
                    activeQuickFilters.delete(key);
                    btn.classList.remove('is-active');
                } else {
                    activeQuickFilters.add(key);
                    btn.classList.add('is-active');
                }
                currentPage = 1;
                updateActiveFilterBadgeCount();
                updateAndRender();
            });
        });

        // Popular Skills Checkboxes
        if (popularSkillsContainer) {
            const popCheckboxes = popularSkillsContainer.querySelectorAll('.filter-skill-checkbox');
            popCheckboxes.forEach(cb => {
                cb.addEventListener('change', () => {
                    const skillName = cb.getAttribute('data-skill-name') || cb.value;
                    if (cb.checked) {
                        selectedSkillsSet.add(skillName);
                    } else {
                        selectedSkillsSet.delete(skillName);
                    }
                    syncAllSkillCheckboxes();
                    renderSelectedSkillTags();
                    currentPage = 1;
                    updateActiveFilterBadgeCount();
                    updateAndRender();
                });
            });
        }

        // Modal Open / Close Handlers
        if (openSkillsModalBtn) {
            openSkillsModalBtn.addEventListener('click', openSkillsModal);
        }
        if (closeSkillsModalBtn) {
            closeSkillsModalBtn.addEventListener('click', closeSkillsModal);
        }
        if (skillsModalBackdrop) {
            skillsModalBackdrop.addEventListener('click', closeSkillsModal);
        }
        if (confirmSkillsBtn) {
            confirmSkillsBtn.addEventListener('click', () => {
                closeSkillsModal();
                currentPage = 1;
                updateActiveFilterBadgeCount();
                updateAndRender();
            });
        }

        // Modal Skill Search Input
        if (skillSearchInput) {
            skillSearchInput.addEventListener('input', (e) => {
                filterModalSkillsByQuery(e.target.value.trim().toLowerCase());
            });
        }

        // Apply Filters Button
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                readFormFilters();
                currentPage = 1;
                updateAndRender();
                if (window.innerWidth < 1200 && filterCard) {
                    filterCard.classList.remove('is-mobile-open');
                }
            });
        }

        // Clear Filters Buttons
        const clearButtons = [clearFiltersBtn, resetHeaderBtn, emptyResetBtn];
        clearButtons.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', resetAllFilters);
            }
        });

        // Sort Dropdown
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                currentSortOption = e.target.value;
                currentPage = 1;
                updateAndRender();
            });
        }

        // Mobile Filter Toggle Button
        if (mobileFilterBtn && filterCard) {
            mobileFilterBtn.addEventListener('click', () => {
                filterCard.classList.toggle('is-mobile-open');
            });
        }
    }

    // 4. Skills Modal & Categories Logic
    function buildModalCategoriesUI() {
        if (!skillsCategoriesContainer) return;

        let html = '';
        SKILL_CATEGORIES.forEach(cat => {
            html += `
                <div class="ent-skill-category-block" data-category-id="${cat.id}">
                    <h4 class="ent-skill-category-title">${escapeHtml(cat.name)}</h4>
                    <div class="ent-skill-category-grid">
                        ${cat.skills.map(skill => `
                            <label class="ent-checkbox-label ent-skill-item" data-skill-keyword="${escapeHtml(skill.toLowerCase())}">
                                <input type="checkbox" 
                                       value="${escapeHtml(skill)}" 
                                       class="modal-skill-checkbox" 
                                       data-skill-name="${escapeHtml(skill)}">
                                <span>${escapeHtml(skill)}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        skillsCategoriesContainer.innerHTML = html;

        // Bind change events to modal checkboxes
        const modalCheckboxes = skillsCategoriesContainer.querySelectorAll('.modal-skill-checkbox');
        modalCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const skillName = cb.getAttribute('data-skill-name') || cb.value;
                if (cb.checked) {
                    selectedSkillsSet.add(skillName);
                } else {
                    selectedSkillsSet.delete(skillName);
                }
                syncAllSkillCheckboxes();
                renderSelectedSkillTags();
                updateModalCounter();
            });
        });
    }

    function openSkillsModal() {
        if (!skillsModal) return;
        syncAllSkillCheckboxes();
        updateModalCounter();
        if (skillSearchInput) {
            skillSearchInput.value = '';
            filterModalSkillsByQuery('');
        }
        skillsModal.style.display = 'block';
        skillsModal.setAttribute('aria-hidden', 'false');
    }

    function closeSkillsModal() {
        if (!skillsModal) return;
        skillsModal.style.display = 'none';
        skillsModal.setAttribute('aria-hidden', 'true');
    }

    function filterModalSkillsByQuery(query) {
        if (!skillsCategoriesContainer) return;

        const categoryBlocks = skillsCategoriesContainer.querySelectorAll('.ent-skill-category-block');
        
        categoryBlocks.forEach(block => {
            const items = block.querySelectorAll('.ent-skill-item');
            let visibleCount = 0;

            items.forEach(item => {
                const keyword = item.getAttribute('data-skill-keyword') || '';
                if (!query || keyword.includes(query)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide whole category block if no items match query
            block.style.display = visibleCount > 0 ? 'block' : 'none';
        });
    }

    function syncAllSkillCheckboxes() {
        // Sync Popular Skills checkboxes
        if (popularSkillsContainer) {
            const popCheckboxes = popularSkillsContainer.querySelectorAll('.filter-skill-checkbox');
            popCheckboxes.forEach(cb => {
                const skillName = cb.getAttribute('data-skill-name') || cb.value;
                cb.checked = selectedSkillsSet.has(skillName);
            });
        }

        // Sync Modal checkboxes
        if (skillsCategoriesContainer) {
            const modalCheckboxes = skillsCategoriesContainer.querySelectorAll('.modal-skill-checkbox');
            modalCheckboxes.forEach(cb => {
                const skillName = cb.getAttribute('data-skill-name') || cb.value;
                cb.checked = selectedSkillsSet.has(skillName);
            });
        }

        updateModalCounter();
    }

    function updateModalCounter() {
        if (modalSelectedCount) {
            modalSelectedCount.textContent = selectedSkillsSet.size;
        }
    }

    function renderSelectedSkillTags() {
        if (!selectedSkillsWrapper || !selectedSkillsTags) return;

        if (selectedSkillsSet.size === 0) {
            selectedSkillsWrapper.style.display = 'none';
            selectedSkillsTags.innerHTML = '';
            if (selectedSkillsCount) selectedSkillsCount.textContent = '0';
            return;
        }

        selectedSkillsWrapper.style.display = 'block';
        if (selectedSkillsCount) selectedSkillsCount.textContent = selectedSkillsSet.size;

        let tagsHtml = '';
        selectedSkillsSet.forEach(skillName => {
            tagsHtml += `
                <span class="ent-selected-tag">
                    ${escapeHtml(skillName)}
                    <button type="button" 
                            class="ent-remove-tag-btn" 
                            data-remove-skill="${escapeHtml(skillName)}" 
                            aria-label="Xóa kỹ năng ${escapeHtml(skillName)}">
                        &times;
                    </button>
                </span>
            `;
        });

        selectedSkillsTags.innerHTML = tagsHtml;

        // Bind remove tag click events
        const removeBtns = selectedSkillsTags.querySelectorAll('.ent-remove-tag-btn');
        removeBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const skillToRemove = btn.getAttribute('data-remove-skill');
                if (skillToRemove) {
                    selectedSkillsSet.delete(skillToRemove);
                    syncAllSkillCheckboxes();
                    renderSelectedSkillTags();
                    currentPage = 1;
                    updateActiveFilterBadgeCount();
                    updateAndRender();
                }
            });
        });
    }

    function readFormFilters() {
        activeFilters.eduLevel = filterEduLevel ? filterEduLevel.value : '';
        activeFilters.school = filterSchool ? filterSchool.value : '';
        activeFilters.classYear = filterClassYear ? filterClassYear.value : '';
        activeFilters.majorField = filterMajorField ? filterMajorField.value : '';
        activeFilters.matchScore = filterMatchScore ? parseInt(filterMatchScore.value, 10) || 0 : 0;
        activeFilters.expHours = filterExpHours ? parseInt(filterExpHours.value, 10) || 0 : 0;
        activeFilters.readiness = filterReadiness ? filterReadiness.value : '';

        updateActiveFilterBadgeCount();
    }

    function resetAllFilters() {
        if (searchInput) searchInput.value = '';
        currentSearchQuery = '';
        if (searchClearBtn) searchClearBtn.style.display = 'none';

        activeQuickFilters.clear();
        quickFilterBtns.forEach(btn => btn.classList.remove('is-active'));

        selectedSkillsSet.clear();
        syncAllSkillCheckboxes();
        renderSelectedSkillTags();

        if (filterEduLevel) filterEduLevel.value = '';
        if (filterSchool) filterSchool.value = '';
        if (filterClassYear) filterClassYear.value = '';
        if (filterMajorField) filterMajorField.value = '';
        if (filterMatchScore) filterMatchScore.value = '0';
        if (filterExpHours) filterExpHours.value = '0';
        if (filterReadiness) filterReadiness.value = '';

        activeFilters.eduLevel = '';
        activeFilters.school = '';
        activeFilters.classYear = '';
        activeFilters.majorField = '';
        activeFilters.matchScore = 0;
        activeFilters.expHours = 0;
        activeFilters.readiness = '';

        currentSortOption = 'matching';
        if (sortSelect) sortSelect.value = 'matching';

        currentPage = 1;
        updateActiveFilterBadgeCount();
        updateAndRender();
    }

    function updateActiveFilterBadgeCount() {
        let count = activeQuickFilters.size + selectedSkillsSet.size;
        if (activeFilters.eduLevel) count++;
        if (activeFilters.school) count++;
        if (activeFilters.classYear) count++;
        if (activeFilters.majorField) count++;
        if (activeFilters.matchScore > 0) count++;
        if (activeFilters.expHours > 0) count++;
        if (activeFilters.readiness) count++;

        if (mobileFilterCount) {
            mobileFilterCount.textContent = count;
        }
    }

    // 5. Skill Matching Helper (AND Logic support)
    function candidateHasSkill(talent, reqSkill) {
        const reqLow = reqSkill.toLowerCase().trim();

        // Skill Aliases & Group Mappings
        if (reqLow === 'ai / machine learning' || reqLow === 'ai/ml') {
            const aiKeywords = ['ai/ml', 'machine learning', 'deep learning', 'pytorch', 'tensorflow', 'trí tuệ nhân tạo'];
            return talent.skills.some(s => aiKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        if (reqLow === 'data analysis' || reqLow === 'dữ liệu & ai') {
            const dataKeywords = ['data analytics', 'data analysis', 'pandas', 'sql', 'dữ liệu'];
            return talent.skills.some(s => dataKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        if (reqLow === 'ui/ux') {
            const designKeywords = ['ui/ux', 'ui/ux design', 'figma', 'photoshop', 'prototyping'];
            return talent.skills.some(s => designKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        if (reqLow === 'digital marketing') {
            const mktKeywords = ['digital marketing', 'seo', 'google analytics', 'content marketing', 'social ads'];
            return talent.skills.some(s => mktKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        if (reqLow === 'communication') {
            const commKeywords = ['communication', 'giao tiếp', 'làm việc nhóm'];
            return talent.skills.some(s => commKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        if (reqLow === 'leadership') {
            const leadKeywords = ['leadership', 'lãnh đạo', 'quản lý'];
            return talent.skills.some(s => leadKeywords.some(kw => s.toLowerCase().includes(kw)));
        }

        // Direct Exact / Includes Matching for specific skill names
        return talent.skills.some(s => {
            const candLow = s.toLowerCase().trim();
            return candLow === reqLow || candLow.includes(reqLow);
        });
    }

    // 6. Filter & Sort Core Logic
    function getFilteredTalents() {
        return allTalents.filter(talent => {
            // Text Search matching (Name, School, Major, Skills)
            if (currentSearchQuery) {
                const nameMatch = talent.name.toLowerCase().includes(currentSearchQuery);
                const schoolMatch = talent.school.toLowerCase().includes(currentSearchQuery);
                const majorMatch = talent.major_field.toLowerCase().includes(currentSearchQuery);
                const skillMatch = talent.skills.some(s => s.toLowerCase().includes(currentSearchQuery));

                if (!nameMatch && !schoolMatch && !majorMatch && !skillMatch) {
                    return false;
                }
            }

            // Quick Filters (Combined AND conditions - talent must satisfy EVERY active quick filter)
            if (activeQuickFilters.has('ai_ml')) {
                const aiSkillsSet = new Set(['ai/ml', 'machine learning', 'deep learning', 'pytorch', 'tensorflow', 'trí tuệ nhân tạo']);
                const hasAIML = talent.skills.some(s => aiSkillsSet.has(s.toLowerCase().trim()));
                if (!hasAIML) return false;
            }

            if (activeQuickFilters.has('coding')) {
                const codingSkillsSet = new Set([
                    'python', 'python cơ bản', 'javascript', 'java', 'php', 'c', 'c++', 'c#', 
                    'node.js', 'laravel', 'react', 'react native', 'typescript', 'vue.js', 
                    'spring boot', '.net core', 'scratch', 'html/css', 'rest api', 
                    'microservices', 'sql', 'mysql', 'postgresql', 'sql server', 'firebase', 'git'
                ]);
                const hasCoding = talent.skills.some(s => codingSkillsSet.has(s.toLowerCase().trim()));
                if (!hasCoding) return false;
            }

            if (activeQuickFilters.has('design')) {
                const designSkillsSet = new Set(['figma', 'ui/ux', 'ui/ux design', 'photoshop', 'design', 'prototyping', 'user research', 'illustration']);
                const hasDesign = talent.skills.some(s => designSkillsSet.has(s.toLowerCase().trim()));
                if (!hasDesign) return false;
            }

            if (activeQuickFilters.has('marketing')) {
                const marketingSkillsSet = new Set(['seo', 'google analytics', 'content marketing', 'social ads', 'copywriting', 'content']);
                const hasMarketing = talent.skills.some(s => marketingSkillsSet.has(s.toLowerCase().trim()));
                if (!hasMarketing) return false;
            }

            if (activeQuickFilters.has('ready_now')) {
                if (talent.internship_status !== 'ready_now') return false;
            }

            // Multi-selected Skills Filter (Strict AND condition: talent MUST satisfy EVERY selected skill)
            if (selectedSkillsSet.size > 0) {
                const hasAllSelected = Array.from(selectedSkillsSet).every(reqSkill => {
                    return candidateHasSkill(talent, reqSkill);
                });
                if (!hasAllSelected) return false;
            }

            // Advanced Form Filters
            if (activeFilters.eduLevel && talent.education_level !== activeFilters.eduLevel) {
                return false;
            }

            if (activeFilters.school && talent.school !== activeFilters.school) {
                return false;
            }

            if (activeFilters.classYear && talent.class_year !== activeFilters.classYear) {
                return false;
            }

            if (activeFilters.majorField && talent.major_field !== activeFilters.majorField) {
                return false;
            }

            if (activeFilters.matchScore > 0 && talent.match_score < activeFilters.matchScore) {
                return false;
            }

            if (activeFilters.expHours > 0 && talent.experience_hours < activeFilters.expHours) {
                return false;
            }

            if (activeFilters.readiness && talent.internship_status !== activeFilters.readiness) {
                return false;
            }

            return true;
        });
    }

    function sortTalentsList(list) {
        const sorted = [...list];
        if (currentSortOption === 'score_desc' || currentSortOption === 'matching') {
            sorted.sort((a, b) => b.match_score - a.match_score);
        } else if (currentSortOption === 'exp_desc') {
            sorted.sort((a, b) => b.experience_hours - a.experience_hours);
        } else if (currentSortOption === 'latest') {
            sorted.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));
        }
        return sorted;
    }

    // 7. Render Pipeline
    function updateAndRender() {
        const filtered = getFilteredTalents();
        const sorted = sortTalentsList(filtered);

        // Update count badge
        if (totalBadgeNum) {
            totalBadgeNum.textContent = sorted.length;
        }

        if (sorted.length === 0) {
            renderEmptyState(true);
            return;
        }

        renderEmptyState(false);

        // Pagination calculations
        const totalPages = Math.ceil(sorted.length / PAGE_SIZE);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * PAGE_SIZE;
        const pageItems = sorted.slice(startIndex, startIndex + PAGE_SIZE);

        renderCards(pageItems);
        renderPagination(sorted.length, totalPages);
    }

    function renderEmptyState(isEmpty) {
        if (isEmpty) {
            if (cardsContainer) cardsContainer.innerHTML = '';
            if (emptyStateEl) emptyStateEl.style.display = 'block';
            if (paginationWrapper) paginationWrapper.style.display = 'none';
        } else {
            if (emptyStateEl) emptyStateEl.style.display = 'none';
            if (paginationWrapper) paginationWrapper.style.display = 'flex';
        }
    }

    function renderCards(talents) {
        if (!cardsContainer) return;

        cardsContainer.innerHTML = talents.map(talent => {
            const topSkills = talent.skills.slice(0, 4);
            const isSaved = talent.saved;

            let statusBadgeClass = 'badge-ready-now';
            if (talent.internship_status === 'ready_1_3m') statusBadgeClass = 'badge-ready-later';
            if (talent.internship_status === 'not_ready') statusBadgeClass = 'badge-not-ready';

            return `
                <article class="ent-talent-card-item" data-talent-id="${talent.id}">
                    <div class="ent-talent-card-item__header">
                        <div class="ent-talent-card-item__user">
                            <div class="ent-talent-card-item__avatar">
                                ${escapeHtml(talent.avatar_initials)}
                            </div>
                            <div class="ent-talent-card-item__title-box">
                                <div class="ent-talent-card-item__name-row">
                                    <h3 class="ent-talent-card-item__name">${escapeHtml(talent.name)}</h3>
                                    <span class="ent-talent-card-item__score">
                                        ${talent.talent_score || talent.match_score} điểm
                                    </span>
                                </div>
                                <div class="ent-talent-card-item__school">
                                    ${escapeHtml(talent.school)} &bull; ${escapeHtml(talent.class_year)} &bull; ${escapeHtml(talent.education_level)}
                                </div>
                            </div>
                        </div>

                        <button type="button" 
                                class="ent-bookmark-btn ${isSaved ? 'is-saved' : ''}" 
                                data-action="save" 
                                data-talent-id="${talent.id}" 
                                title="${isSaved ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ này'}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="ent-talent-card-item__meta-grid">
                        <div class="ent-meta-chip">
                            <span class="label">Lĩnh vực:</span>
                            <span class="value font-medium">${escapeHtml(talent.major_field)}</span>
                        </div>
                        <div class="ent-meta-chip">
                            <span class="label">Kinh nghiệm thực án:</span>
                            <span class="value font-semibold text-primary">${talent.experience_hours}h trải nghiệm</span>
                        </div>
                        <div class="ent-meta-chip">
                            <span class="label">Trạng thái:</span>
                            <span class="val-status ${statusBadgeClass}">${escapeHtml(talent.internship_status_label)}</span>
                        </div>
                    </div>

                    <div class="ent-talent-card-item__skills">
                        <span class="skills-label">Kỹ năng chính:</span>
                        <div class="skills-chips">
                            ${topSkills.map(skill => `<span class="skill-tag">${escapeHtml(skill)}</span>`).join('')}
                            ${talent.skills.length > 4 ? `<span class="skill-tag skill-tag--more">+${talent.skills.length - 4}</span>` : ''}
                        </div>
                    </div>

                    <div class="ent-talent-card-item__footer">
                        <div class="ent-privacy-note">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Thông tin cá nhân được bảo vệ
                        </div>
                        <div class="ent-talent-card-item__actions">
                            <button type="button" 
                                    class="btn btn-secondary btn-sm ent-talent-action-btn" 
                                    data-action="view" 
                                    data-talent-id="${talent.id}"
                                    data-talent-name="${escapeHtml(talent.name)}">
                                Xem hồ sơ
                            </button>
                            <button type="button" 
                                    class="btn btn-primary btn-sm ent-talent-action-btn" 
                                    data-action="contact" 
                                    data-talent-id="${talent.id}"
                                    data-talent-name="${escapeHtml(talent.name)}">
                                Liên hệ
                            </button>
                        </div>
                    </div>
                </article>
            `;
        }).join('');

        // Bind events for dynamically rendered cards
        bindCardActions();
    }

    function bindCardActions() {
        const actionBtns = cardsContainer.querySelectorAll('.ent-talent-action-btn, .ent-bookmark-btn');
        
        actionBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const action = btn.getAttribute('data-action');
                const id = parseInt(btn.getAttribute('data-talent-id'), 10);
                const name = btn.getAttribute('data-talent-name') || `Ứng viên #${id}`;

                if (action === 'view') {
                    (window.showEntToast || showEntToast)(`Đang mở Hồ sơ năng lực (Talent Passport) của ${name}...`);
                } else if (action === 'contact') {
                    (window.showEntToast || showEntToast)(`Đã gửi yêu cầu kết nối tới ${name}. Chúng tôi sẽ thông báo khi ứng viên phản hồi.`);
                } else if (action === 'save') {
                    const talentItem = allTalents.find(t => t.id === id);
                    if (talentItem) {
                        talentItem.saved = !talentItem.saved;
                        if (talentItem.saved) {
                            (window.showEntToast || showEntToast)(`Đã lưu hồ sơ của ${talentItem.name} vào danh sách quan tâm.`);
                        } else {
                            (window.showEntToast || showEntToast)(`Đã bỏ lưu hồ sơ của ${talentItem.name}.`);
                        }
                        updateAndRender();
                    }
                }
            });
        });
    }

    function renderPagination(totalCount, totalPages) {
        if (!paginationInfo || !paginationBtns) return;

        const startCount = (currentPage - 1) * PAGE_SIZE + 1;
        const endCount = Math.min(currentPage * PAGE_SIZE, totalCount);

        paginationInfo.textContent = `Hiển thị ${startCount}-${endCount} / ${totalCount} nhân tài (Trang ${currentPage}/${totalPages})`;

        let btnsHtml = '';

        // Previous button
        btnsHtml += `
            <button type="button" class="ent-page-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">
                &larr; Trước
            </button>
        `;

        // Page Numbers
        for (let i = 1; i <= totalPages; i++) {
            btnsHtml += `
                <button type="button" class="ent-page-btn ${i === currentPage ? 'is-active' : ''}" data-page="${i}">
                    ${i}
                </button>
            `;
        }

        // Next button
        btnsHtml += `
            <button type="button" class="ent-page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">
                Sau &rarr;
            </button>
        `;

        paginationBtns.innerHTML = btnsHtml;

        // Bind pagination click listeners
        const pageBtns = paginationBtns.querySelectorAll('.ent-page-btn:not([disabled])');
        pageBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = parseInt(btn.getAttribute('data-page'), 10);
                if (targetPage && targetPage !== currentPage) {
                    currentPage = targetPage;
                    updateAndRender();
                    if (cardsContainer) {
                        cardsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
