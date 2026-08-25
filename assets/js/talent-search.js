/**
 * TalentHub - Enterprise Talent Search Controller
 * Handles live API search, multi-criteria filtering, quick filter pills,
 * popular & categorized skills selection, pagination, and candidate detail navigation.
 */

document.addEventListener('DOMContentLoaded', () => {
    initTalentSearchModule();
});

function initTalentSearchModule() {
    // 1. Read session & initial data
    let sessionBoot = {
        csrfToken: '',
        enterpriseId: '',
        isVerified: true,
        apiBase: '/api/v1/businesses/me',
        initialTalents: [],
        totalTalents: 0,
    };

    const bootElement = document.getElementById('enterprise-session-boot');
    if (bootElement) {
        try {
            sessionBoot = Object.assign(sessionBoot, JSON.parse(bootElement.textContent));
        } catch (e) {
            console.error('Failed to parse enterprise session boot data:', e);
        }
    }

    // Normalized talent array
    let allTalents = (sessionBoot.initialTalents || []).map(normalizeTalent);

    // 2. Structured Skill Categories
    const SKILL_CATEGORIES = [
        {
            id: 'tech',
            name: 'Công nghệ & Lập trình',
            skills: ['Python', 'JavaScript', 'PHP', 'Node.js', 'C++', 'Java', 'C#', 'React', 'Vue.js', 'TypeScript', 'Laravel', 'HTML/CSS', 'REST API', 'Mobile App', 'Git', 'Docker', 'SQL']
        },
        {
            id: 'data_ai',
            name: 'Dữ liệu & AI',
            skills: ['AI / Machine Learning', 'Data Analysis', 'PyTorch', 'TensorFlow', 'SQL', 'Pandas', 'Data Analytics', 'Deep Learning']
        },
        {
            id: 'design',
            name: 'Thiết kế & Sáng tạo',
            skills: ['UI/UX', 'Figma', 'Photoshop', 'Prototyping', 'User Research', 'Thiết kế đồ họa', 'Illustrator']
        },
        {
            id: 'business_marketing',
            name: 'Kinh doanh & Marketing',
            skills: ['Digital Marketing', 'SEO', 'Google Analytics', 'Content Marketing', 'Social Ads', 'Quản lý dự án', 'Copywriting']
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
    const selectedSkillsSet = new Set();

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
    let isFetchingApi = false;
    let searchDebounceTimer = null;

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
    const totalBadgeNum = document.getElementById('total-talents-badge');

    // Skill Modal Elements
    const openSkillsModalBtn = document.getElementById('open-skills-modal-btn');
    const skillsModal = document.getElementById('skills-selector-modal');
    const closeSkillsModalBtn = document.getElementById('close-skills-modal-btn');
    const skillsModalBackdrop = document.getElementById('skills-modal-backdrop');
    const confirmSkillsBtn = document.getElementById('confirm-skills-btn');
    const skillSearchInput = document.getElementById('skill-search-input');
    const skillsCategoriesContainer = document.getElementById('skills-categories-container');
    const modalSelectedCountEl = document.getElementById('modal-selected-count');
    const selectedSkillsChipsContainer = document.getElementById('selected-skills-chips');
    const popularSkillPills = document.querySelectorAll('.ent-skill-pill');

    // 3. Normalization Helper
    function normalizeTalent(raw) {
        const id = raw.studentId || raw.id || '';
        const name = raw.displayName || raw.name || 'Ứng viên';
        const rawSkills = Array.isArray(raw.verifiedSkills) ? raw.verifiedSkills : (Array.isArray(raw.skills) ? raw.skills : []);
        const skills = rawSkills.map(s => typeof s === 'string' ? s : (s.name || s.skillName || ''));
        const school = raw.schoolName || raw.school || '';
        const classYear = raw.className || raw.class_year || '';
        const eduLevel = raw.studyStatus || raw.education_level || '';
        const majorField = raw.headline || raw.major_field || 'Công nghệ & Kỹ thuật';
        const expHours = typeof raw.experienceHours === 'number' ? raw.experienceHours : (raw.experience_hours || (skills.length * 12));
        const score = raw.talent_score || (80 + Math.min(skills.length * 4, 19));

        return {
            id: id,
            studentId: id,
            name: name,
            avatar_initials: getInitials(name),
            school: school,
            class_year: classYear,
            education_level: eduLevel,
            major_field: majorField,
            skills: skills,
            experience_hours: expHours,
            talent_score: score,
            match_score: score,
            internship_status: 'ready_now',
            internship_status_label: 'Sẵn sàng thực tập',
            saved: false,
            contactAllowed: Boolean(raw.contactAllowed),
            hasPendingContactRequest: Boolean(raw.hasPendingContactRequest),
            updated_at: raw.grantedAt || new Date().toISOString(),
        };
    }

    function getInitials(name) {
        const words = (name || '').trim().split(/\s+/);
        if (!words.length || !words[0]) return 'UV';
        if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
        return (words[0][0] + words[words.length - 1][0]).toUpperCase();
    }

    // 4. API Fetching
    async function fetchFromApi() {
        if (isFetchingApi) return;
        isFetchingApi = true;

        const params = new URLSearchParams();
        if (currentSearchQuery) params.append('search', currentSearchQuery);
        if (activeFilters.school) params.append('school', activeFilters.school);
        if (selectedSkillsSet.size > 0) {
            params.append('skills', Array.from(selectedSkillsSet).join(','));
        }
        if (currentSortOption === 'latest') params.append('sort', 'newest');
        else if (currentSortOption === 'score_desc') params.append('sort', 'skills');

        try {
            const url = `${sessionBoot.apiBase}/talents?${params.toString()}`;
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                const items = data.data?.items || data.items || [];
                allTalents = items.map(normalizeTalent);
            }
        } catch (e) {
            console.warn('Live talent API fetch fallback to local filter:', e);
        } finally {
            isFetchingApi = false;
            updateAndRender();
        }
    }

    // 5. Filter & Sort Core Logic
    function candidateHasSkill(talent, reqSkill) {
        const reqLow = reqSkill.toLowerCase().trim();
        return talent.skills.some(s => {
            const candLow = s.toLowerCase().trim();
            return candLow === reqLow || candLow.includes(reqLow);
        });
    }

    function getFilteredTalents() {
        return allTalents.filter(talent => {
            if (currentSearchQuery) {
                const nameMatch = talent.name.toLowerCase().includes(currentSearchQuery);
                const schoolMatch = talent.school.toLowerCase().includes(currentSearchQuery);
                const majorMatch = talent.major_field.toLowerCase().includes(currentSearchQuery);
                const skillMatch = talent.skills.some(s => s.toLowerCase().includes(currentSearchQuery));

                if (!nameMatch && !schoolMatch && !majorMatch && !skillMatch) {
                    return false;
                }
            }

            if (activeQuickFilters.has('ai_ml')) {
                const aiSkillsSet = new Set(['ai/ml', 'machine learning', 'deep learning', 'pytorch', 'tensorflow', 'trí tuệ nhân tạo', 'ai / machine learning']);
                const hasAIML = talent.skills.some(s => aiSkillsSet.has(s.toLowerCase().trim()));
                if (!hasAIML) return false;
            }

            if (activeQuickFilters.has('coding')) {
                const codingSkillsSet = new Set([
                    'python', 'javascript', 'java', 'php', 'c', 'c++', 'c#', 
                    'node.js', 'laravel', 'react', 'react native', 'typescript', 'vue.js', 
                    'spring boot', '.net core', 'scratch', 'html/css', 'rest api', 
                    'microservices', 'sql', 'mysql', 'postgresql', 'git', 'docker'
                ]);
                const hasCoding = talent.skills.some(s => codingSkillsSet.has(s.toLowerCase().trim()));
                if (!hasCoding) return false;
            }

            if (activeQuickFilters.has('design')) {
                const designSkillsSet = new Set(['figma', 'ui/ux', 'ui/ux design', 'photoshop', 'design', 'prototyping', 'user research', 'illustrator', 'thiết kế đồ họa']);
                const hasDesign = talent.skills.some(s => designSkillsSet.has(s.toLowerCase().trim()));
                if (!hasDesign) return false;
            }

            if (activeQuickFilters.has('marketing')) {
                const marketingSkillsSet = new Set(['seo', 'google analytics', 'content marketing', 'social ads', 'copywriting', 'digital marketing']);
                const hasMarketing = talent.skills.some(s => marketingSkillsSet.has(s.toLowerCase().trim()));
                if (!hasMarketing) return false;
            }

            if (selectedSkillsSet.size > 0) {
                const hasAllSelected = Array.from(selectedSkillsSet).every(reqSkill => {
                    return candidateHasSkill(talent, reqSkill);
                });
                if (!hasAllSelected) return false;
            }

            if (activeFilters.eduLevel && talent.education_level !== activeFilters.eduLevel) {
                return false;
            }

            if (activeFilters.school && talent.school !== activeFilters.school) {
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

    // 6. Render Pipeline
    function updateAndRender() {
        const filtered = getFilteredTalents();
        const sorted = sortTalentsList(filtered);

        if (totalBadgeNum) {
            totalBadgeNum.textContent = sorted.length;
        }

        if (sorted.length === 0) {
            renderEmptyState(true);
            return;
        }

        renderEmptyState(false);

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

    function resolveCandidateDetailUrl(id) {
        const basePrefix = window.location.pathname.includes('/TalentHub') ? '/TalentHub' : '';
        return `${basePrefix}/app/enterprise/talents/detail.php?id=${encodeURIComponent(id)}`;
    }

    function renderCards(talents) {
        if (!cardsContainer) return;

        cardsContainer.innerHTML = talents.map(talent => {
            const topSkills = talent.skills.slice(0, 4);
            const isSaved = talent.saved;
            const score = talent.talent_score || talent.match_score;
            const detailUrl = resolveCandidateDetailUrl(talent.id);

            return `
                <article class="ent-talent-card-item" data-talent-id="${escapeHtml(talent.id)}">
                    <div class="ent-talent-card-item__header">
                        <div class="ent-talent-card-item__user">
                            <div class="ent-talent-card-item__avatar">
                                ${escapeHtml(talent.avatar_initials)}
                            </div>
                            <div class="ent-talent-card-item__title-box">
                                <div class="ent-talent-card-item__name-row">
                                    <a href="${detailUrl}" class="ent-talent-card-item__name">
                                        ${escapeHtml(talent.name)}
                                    </a>
                                    <span class="ent-talent-card-item__score" title="Điểm đánh giá năng lực">
                                        ${score}% phù hợp
                                    </span>
                                </div>
                                <div class="ent-talent-card-item__school">
                                    <span>${escapeHtml(talent.school || 'Nhà trường')}</span>
                                    ${talent.major_field ? `<span class="ent-talent-card-item__dot">&bull;</span><span>${escapeHtml(talent.major_field)}</span>` : ''}
                                    ${talent.class_year ? `<span class="ent-talent-card-item__dot">&bull;</span><span>${escapeHtml(talent.class_year)}</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <button type="button" 
                                class="ent-bookmark-btn ${isSaved ? 'is-saved' : ''}" 
                                data-action="save" 
                                data-talent-id="${escapeHtml(talent.id)}" 
                                title="${isSaved ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ này'}"
                                aria-label="${isSaved ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ'}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="ent-talent-card-item__meta-strip">
                        <div class="ent-meta-item">
                            <span class="ent-meta-item__label">Kỹ năng xác thực:</span>
                            <span class="ent-meta-item__value font-semibold text-dark">${talent.skills.length} kỹ năng</span>
                        </div>
                        <div class="ent-meta-item__divider"></div>
                        <div class="ent-meta-item">
                            <span class="ent-meta-item__label">Trạng thái:</span>
                            <span class="val-status badge-ready-now">${escapeHtml(talent.internship_status_label)}</span>
                        </div>
                        <div class="ent-meta-item__divider"></div>
                        <div class="ent-meta-item">
                            <span class="ent-meta-item__label">Bậc học:</span>
                            <span class="ent-meta-item__value">${escapeHtml(talent.education_level || 'Sinh viên')}</span>
                        </div>
                    </div>

                    <div class="ent-talent-card-item__skills">
                        <span class="skills-label">Kỹ năng:</span>
                        <div class="skills-chips">
                            ${topSkills.map(skill => `<span class="skill-tag">${escapeHtml(skill)}</span>`).join('')}
                            ${talent.skills.length > 4 ? `<span class="skill-tag skill-tag--more">+${talent.skills.length - 4}</span>` : ''}
                        </div>
                    </div>

                    <div class="ent-talent-card-item__footer">
                        <div class="ent-privacy-note" title="Thông tin liên hệ (Email, SĐT) chỉ được hiển thị khi ứng viên đồng ý kết nối">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span>${talent.contactAllowed ? 'Đã có quyền liên hệ' : 'Hồ sơ có consent'}</span>
                        </div>
                        <div class="ent-talent-card-item__actions">
                            <a href="${detailUrl}" class="btn btn-secondary btn-sm">
                                Xem hồ sơ
                            </a>
                            <a href="${detailUrl}" class="btn btn-primary btn-sm">
                                ${talent.hasPendingContactRequest ? 'Đã yêu cầu' : 'Liên hệ'}
                            </a>
                        </div>
                    </div>
                </article>
            `;
        }).join('');

        // Attach bookmark events
        cardsContainer.querySelectorAll('.ent-bookmark-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const tid = btn.getAttribute('data-talent-id');
                const cand = allTalents.find(t => t.id === tid);
                if (cand) {
                    cand.saved = !cand.saved;
                    btn.classList.toggle('is-saved', cand.saved);
                    const svg = btn.querySelector('svg');
                    if (svg) svg.setAttribute('fill', cand.saved ? 'currentColor' : 'none');
                    showToast(cand.saved ? 'Đã lưu hồ sơ vào danh sách quan tâm.' : 'Đã bỏ lưu hồ sơ.');
                }
            });
        });
    }

    function renderPagination(totalItems, totalPages) {
        if (!paginationWrapper || !paginationInfo || !paginationBtns) return;
        paginationInfo.textContent = `Trang ${currentPage} / ${totalPages} (${totalItems} ứng viên)`;

        let html = `
            <button type="button" class="ent-page-btn" id="prev-page-btn" ${currentPage === 1 ? 'disabled' : ''} aria-label="Trang trước">&larr;</button>
        `;
        for (let i = 1; i <= totalPages; i++) {
            html += `<button type="button" class="ent-page-btn ${i === currentPage ? 'is-active' : ''}" data-page="${i}">${i}</button>`;
        }
        html += `
            <button type="button" class="ent-page-btn" id="next-page-btn" ${currentPage === totalPages ? 'disabled' : ''} aria-label="Trang kế">&rarr;</button>
        `;

        paginationBtns.innerHTML = html;

        paginationBtns.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.getAttribute('data-page'), 10);
                updateAndRender();
                scrollToTopCards();
            });
        });

        const prevBtn = document.getElementById('prev-page-btn');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    updateAndRender();
                    scrollToTopCards();
                }
            });
        }

        const nextBtn = document.getElementById('next-page-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    updateAndRender();
                    scrollToTopCards();
                }
            });
        }
    }

    function scrollToTopCards() {
        if (cardsContainer) {
            cardsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // 7. Event Handlers
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim().toLowerCase();
            if (searchClearBtn) searchClearBtn.style.display = currentSearchQuery ? 'block' : 'none';
            currentPage = 1;

            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                fetchFromApi();
            }, 300);
        });
    }

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            currentSearchQuery = '';
            searchClearBtn.style.display = 'none';
            currentPage = 1;
            fetchFromApi();
        });
    }

    quickFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filterKey = btn.getAttribute('data-quick-filter');
            if (activeQuickFilters.has(filterKey)) {
                activeQuickFilters.delete(filterKey);
                btn.classList.remove('is-active');
            } else {
                activeQuickFilters.add(filterKey);
                btn.classList.add('is-active');
            }
            currentPage = 1;
            updateAndRender();
        });
    });

    if (filterEduLevel) filterEduLevel.addEventListener('change', (e) => { activeFilters.eduLevel = e.target.value; currentPage = 1; updateAndRender(); });
    if (filterSchool) filterSchool.addEventListener('change', (e) => { activeFilters.school = e.target.value; currentPage = 1; fetchFromApi(); });
    if (filterMajorField) filterMajorField.addEventListener('change', (e) => { activeFilters.majorField = e.target.value; currentPage = 1; updateAndRender(); });
    if (filterMatchScore) filterMatchScore.addEventListener('change', (e) => { activeFilters.matchScore = parseInt(e.target.value, 10) || 0; currentPage = 1; updateAndRender(); });
    if (filterExpHours) filterExpHours.addEventListener('change', (e) => { activeFilters.expHours = parseInt(e.target.value, 10) || 0; currentPage = 1; updateAndRender(); });

    if (sortSelect) {
        sortSelect.addEventListener('change', (e) => {
            currentSortOption = e.target.value;
            currentPage = 1;
            fetchFromApi();
        });
    }

    function resetAllFilters() {
        if (searchInput) searchInput.value = '';
        currentSearchQuery = '';
        if (searchClearBtn) searchClearBtn.style.display = 'none';

        activeQuickFilters.clear();
        quickFilterBtns.forEach(b => b.classList.remove('is-active'));

        selectedSkillsSet.clear();
        popularSkillPills.forEach(p => p.classList.remove('is-active'));
        renderSelectedSkillsChips();

        activeFilters.eduLevel = '';
        activeFilters.school = '';
        activeFilters.classYear = '';
        activeFilters.majorField = '';
        activeFilters.matchScore = 0;
        activeFilters.expHours = 0;
        activeFilters.readiness = '';

        if (filterEduLevel) filterEduLevel.value = '';
        if (filterSchool) filterSchool.value = '';
        if (filterMajorField) filterMajorField.value = '';
        if (filterMatchScore) filterMatchScore.value = '0';
        if (filterExpHours) filterExpHours.value = '0';

        currentPage = 1;
        fetchFromApi();
    }

    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', resetAllFilters);
    if (resetHeaderBtn) resetHeaderBtn.addEventListener('click', resetAllFilters);
    if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);

    // 8. Skills Modal & Chips Handlers
    popularSkillPills.forEach(pill => {
        pill.addEventListener('click', () => {
            const skillName = pill.getAttribute('data-skill');
            if (selectedSkillsSet.has(skillName)) {
                selectedSkillsSet.delete(skillName);
                pill.classList.remove('is-active');
            } else {
                selectedSkillsSet.add(skillName);
                pill.classList.add('is-active');
            }
            renderSelectedSkillsChips();
            currentPage = 1;
            fetchFromApi();
        });
    });

    function renderSelectedSkillsChips() {
        if (!selectedSkillsChipsContainer) return;
        if (selectedSkillsSet.size === 0) {
            selectedSkillsChipsContainer.innerHTML = '';
            return;
        }

        selectedSkillsChipsContainer.innerHTML = Array.from(selectedSkillsSet).map(sk => `
            <span class="ent-selected-skill-chip" data-skill="${escapeHtml(sk)}">
                <span>${escapeHtml(sk)}</span>
                <button type="button" class="ent-remove-skill-chip" aria-label="Xóa kỹ năng ${escapeHtml(sk)}">&times;</button>
            </span>
        `).join('');

        selectedSkillsChipsContainer.querySelectorAll('.ent-remove-skill-chip').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const chip = e.target.closest('.ent-selected-skill-chip');
                const sk = chip.getAttribute('data-skill');
                selectedSkillsSet.delete(sk);
                popularSkillPills.forEach(p => {
                    if (p.getAttribute('data-skill') === sk) p.classList.remove('is-active');
                });
                renderSelectedSkillsChips();
                currentPage = 1;
                fetchFromApi();
            });
        });
    }

    function renderModalSkills(searchFilter = '') {
        if (!skillsCategoriesContainer) return;
        const low = searchFilter.toLowerCase().trim();

        skillsCategoriesContainer.innerHTML = SKILL_CATEGORIES.map(cat => {
            const filteredSkills = cat.skills.filter(s => !low || s.toLowerCase().includes(low));
            if (!filteredSkills.length) return '';

            return `
                <div class="ent-skill-category-block">
                    <div class="ent-skill-category-title">${escapeHtml(cat.name)}</div>
                    <div class="ent-skill-category-chips">
                        ${filteredSkills.map(sk => {
                            const isSel = selectedSkillsSet.has(sk);
                            return `
                                <button type="button" 
                                        class="ent-modal-skill-item ${isSel ? 'is-selected' : ''}" 
                                        data-skill="${escapeHtml(sk)}">
                                    ${escapeHtml(sk)}
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }).join('');

        skillsCategoriesContainer.querySelectorAll('.ent-modal-skill-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const sk = btn.getAttribute('data-skill');
                if (selectedSkillsSet.has(sk)) {
                    selectedSkillsSet.delete(sk);
                    btn.classList.remove('is-selected');
                } else {
                    selectedSkillsSet.add(sk);
                    btn.classList.add('is-selected');
                }
                if (modalSelectedCountEl) modalSelectedCountEl.textContent = selectedSkillsSet.size;
            });
        });

        if (modalSelectedCountEl) modalSelectedCountEl.textContent = selectedSkillsSet.size;
    }

    if (openSkillsModalBtn) {
        openSkillsModalBtn.addEventListener('click', () => {
            if (!skillsModal) return;
            renderModalSkills();
            skillsModal.style.display = 'block';
            skillsModal.setAttribute('aria-hidden', 'false');
            if (skillSearchInput) {
                skillSearchInput.value = '';
                skillSearchInput.focus();
            }
        });
    }

    function closeSkillsModal() {
        if (!skillsModal) return;
        skillsModal.style.display = 'none';
        skillsModal.setAttribute('aria-hidden', 'true');
    }

    if (closeSkillsModalBtn) closeSkillsModalBtn.addEventListener('click', closeSkillsModal);
    if (skillsModalBackdrop) skillsModalBackdrop.addEventListener('click', closeSkillsModal);
    if (confirmSkillsBtn) {
        confirmSkillsBtn.addEventListener('click', () => {
            closeSkillsModal();
            popularSkillPills.forEach(p => {
                const sk = p.getAttribute('data-skill');
                p.classList.toggle('is-active', selectedSkillsSet.has(sk));
            });
            renderSelectedSkillsChips();
            currentPage = 1;
            fetchFromApi();
        });
    }

    if (skillSearchInput) {
        skillSearchInput.addEventListener('input', (e) => {
            renderModalSkills(e.target.value);
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

    function showToast(msg) {
        if (typeof window.showEntToast === 'function') {
            window.showEntToast(msg);
        } else if (typeof showEntToast === 'function') {
            showEntToast(msg);
        } else {
            alert(msg);
        }
    }

    // Initial render
    updateAndRender();
}
