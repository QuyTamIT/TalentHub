/**
 * TalentHub - Enterprise Talent Search Controller
 * Handles live API search, multi-criteria filtering, dynamic sector-aware quick filter pills,
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
        sectorType: 'tech',
        isEconomicSector: false,
        defaultMajorField: 'Công nghệ thông tin',
    };

    const bootElement = document.getElementById('enterprise-session-boot');
    if (bootElement) {
        try {
            sessionBoot = Object.assign(sessionBoot, JSON.parse(bootElement.textContent));
        } catch (e) {
            console.error('Failed to parse enterprise session boot data:', e);
        }
    }

    const isEconomicSector = Boolean(sessionBoot.isEconomicSector);

    // Normalized talent array
    let allTalents = (sessionBoot.initialTalents || []).map(normalizeTalent);

    // 2. Sector-Aware Structured Skill Categories
    const TECH_SKILL_CATEGORIES = [
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
            id: 'security',
            name: 'An toàn thông tin & Mạng',
            skills: ['An toàn thông tin', 'Cyber Security', 'Network Security', 'Penetration Testing', 'Cloud Security']
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

    const ECONOMIC_SKILL_CATEGORIES = [
        {
            id: 'business_marketing',
            name: 'Kinh doanh, Marketing & Thương hiệu',
            skills: ['Digital Marketing', 'Nghiên cứu thị trường', 'Phân tích thị trường', 'SEO', 'Google Analytics', 'Content Marketing', 'Social Ads', 'Quản trị thương hiệu', 'Sáng tạo nội dung', 'Copywriting', 'E-Commerce']
        },
        {
            id: 'data_analytics',
            name: 'Phân tích Dữ liệu & Báo cáo BI',
            skills: ['Phân tích dữ liệu', 'PowerBI', 'Excel nâng cao', 'SQL', 'Data Analytics', 'Tableau', 'Thống kê kinh doanh']
        },
        {
            id: 'logistics_supplychain',
            name: 'Chuỗi cung ứng & Logistics',
            skills: ['Quản trị kho vận', 'Quản lý kho vận', 'Tối ưu hóa đơn hàng', 'Phân tích dữ liệu vận hành', 'Logistics', 'Supply Chain Management', 'Điều độ vận chuyển']
        },
        {
            id: 'finance_accounting',
            name: 'Tài chính - Kế toán Doanh nghiệp',
            skills: ['Lập báo cáo tài chính', 'Kế toán chi phí', 'Phân tích tài chính', 'Excel nâng cao', 'IFRS', 'Kế toán quản trị', 'Kiểm toán nội bộ']
        },
        {
            id: 'soft_language',
            name: 'Ngoại ngữ & Kỹ năng chuyên nghiệp',
            skills: ['Tiếng Anh giao tiếp', 'Tiếng Anh TOEIC 800', 'Tiếng Anh TOEIC 850', 'Kỹ năng thuyết trình', 'Làm việc nhóm', 'Tư duy phản biện', 'Đàm phán & Thương lượng']
        },
        {
            id: 'tech_digital',
            name: 'Công nghệ & Chuyển đổi số',
            skills: ['Python', 'SQL', 'ERP SAP S/4HANA', 'HTML/CSS', 'CRM', 'Google Workspace']
        }
    ];

    const SKILL_CATEGORIES = isEconomicSector ? ECONOMIC_SKILL_CATEGORIES : TECH_SKILL_CATEGORIES;

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
    const totalBadgeNum = document.getElementById('ent-count-num') || document.getElementById('total-talents-badge');

    // Skill Checkboxes & Tags Elements
    const skillCheckboxes = document.querySelectorAll('.filter-skill-checkbox');
    const selectedSkillsWrapper = document.getElementById('selected-skills-wrapper');
    const selectedSkillsCountEl = document.getElementById('selected-skills-count');
    const selectedSkillsTagsContainer = document.getElementById('selected-skills-tags');
    const selectedSkillsChipsContainer = document.getElementById('selected-skills-chips');
    const popularSkillPills = document.querySelectorAll('.ent-skill-pill');

    // Skill Modal Elements
    const openSkillsModalBtn = document.getElementById('open-skills-modal-btn');
    const skillsModal = document.getElementById('skills-selector-modal');
    const closeSkillsModalBtn = document.getElementById('close-skills-modal-btn');
    const skillsModalBackdrop = document.getElementById('skills-modal-backdrop');
    const confirmSkillsBtn = document.getElementById('confirm-skills-btn');
    const skillSearchInput = document.getElementById('skill-search-input');
    const skillsCategoriesContainer = document.getElementById('skills-categories-container');
    const modalSelectedCountEl = document.getElementById('modal-selected-count');

    // 3. Normalization Helper
    function normalizeTalent(raw) {
        const id = String(raw.studentId || raw.id || '');
        const name = raw.displayName || raw.name || 'Ứng viên tiềm năng';
        const rawSkills = Array.isArray(raw.verifiedSkills) ? raw.verifiedSkills : (Array.isArray(raw.skills) ? raw.skills : []);
        const skills = rawSkills.map(s => typeof s === 'string' ? s : (s.name || s.skillName || ''));
        const school = raw.schoolName || raw.school || '';
        const classYear = raw.className || raw.class_year || '';
        const eduLevel = raw.studyStatus || raw.education_level || '';
        const headline = raw.headline || '';
        const majorField = raw.major_field || (headline ? extractMajorFromHeadline(headline) : (isEconomicSector ? 'Kinh tế & Quản trị' : 'Công nghệ thông tin'));
        const expHours = typeof raw.experienceHours === 'number' ? raw.experienceHours : (raw.experience_hours || (skills.length * 15 + 20));
        const score = raw.talentScore || raw.talent_score || raw.match_score || 85;

        return {
            id: id,
            studentId: id,
            name: name,
            avatar_initials: raw.avatar_initials || getInitials(name),
            school: school,
            class_year: classYear,
            education_level: eduLevel,
            major_field: majorField,
            headline: headline,
            skills: skills,
            experience_hours: expHours,
            talent_score: score,
            match_score: score,
            internship_status: raw.internship_status || 'ready_now',
            internship_status_label: raw.internship_status_label || 'Sẵn sàng thực tập',
            saved: Boolean(raw.saved),
            contactAllowed: Boolean(raw.contactAllowed),
            hasPendingContactRequest: Boolean(raw.hasPendingContactRequest),
            updated_at: raw.grantedAt || raw.updated_at || new Date().toISOString(),
        };
    }

    function extractMajorFromHeadline(headline) {
        if (/quản trị kinh doanh|marketing|kinh tế|thương mại/i.test(headline)) return 'Kinh doanh & Marketing';
        if (/chuỗi cung ứng|logistics|kho vận/i.test(headline)) return 'Logistics & Chuỗi cung ứng';
        if (/tài chính|kế toán/i.test(headline)) return 'Tài chính - Kế toán';
        if (/phân tích dữ liệu|bi|data/i.test(headline)) return 'Khoa học dữ liệu & BI';
        if (/frontend|backend|fullstack|lập trình|developer|ai|công nghệ thông tin/i.test(headline)) return 'Công nghệ thông tin';
        return headline.split('|')[0].trim();
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
                if (Array.isArray(items) && items.length > 0) {
                    allTalents = items.map(normalizeTalent);
                }
            }
        } catch (e) {
            console.warn('Live talent API fetch fallback to local filter:', e);
        } finally {
            isFetchingApi = false;
            updateAndRender();
        }
    }

    // 5. Filtering & Search Logic
    function candidateHasSkill(talent, reqSkill) {
        const reqLow = reqSkill.toLowerCase().trim();

        // Skill synonyms / aliases map
        const aliases = {
            'nghiên cứu thị trường': ['phân tích thị trường', 'nghiên cứu thị trường', 'market research', 'market analysis'],
            'phân tích thị trường': ['phân tích thị trường', 'nghiên cứu thị trường', 'market research', 'market analysis'],
            'quản trị kho vận': ['quản lý kho vận', 'quản trị kho vận', 'warehouse', 'kho vận'],
            'quản lý kho vận': ['quản lý kho vận', 'quản trị kho vận', 'warehouse', 'kho vận'],
            'tiếng anh giao tiếp': ['tiếng anh', 'tiếng anh toeic 800', 'tiếng anh toeic 850', 'tiếng anh giao tiếp', 'toeic', 'ielts', 'english'],
            'phân tích dữ liệu': ['phân tích dữ liệu', 'data analysis', 'data analytics', 'data analyst'],
            'excel nâng cao': ['excel nâng cao', 'excel', 'advanced excel'],
            'kỹ năng thuyết trình': ['kỹ năng thuyết trình', 'thuyết trình', 'presentation'],
            'digital marketing': ['digital marketing', 'marketing', 'tiếp thị số'],
            'sáng tạo nội dung': ['sáng tạo nội dung', 'content marketing', 'content creator'],
        };

        const checkList = aliases[reqLow] || [reqLow];

        return talent.skills.some(candSkill => {
            const candLow = candSkill.toLowerCase().trim();
            return checkList.some(target => candLow === target || candLow.includes(target) || target.includes(candLow));
        });
    }

    function getFilteredTalents() {
        return allTalents.filter(talent => {
            // Text Search Query
            if (currentSearchQuery) {
                const nameMatch = talent.name.toLowerCase().includes(currentSearchQuery);
                const schoolMatch = talent.school.toLowerCase().includes(currentSearchQuery);
                const majorMatch = talent.major_field.toLowerCase().includes(currentSearchQuery);
                const headlineMatch = (talent.headline || '').toLowerCase().includes(currentSearchQuery);
                const skillMatch = talent.skills.some(s => s.toLowerCase().includes(currentSearchQuery));

                if (!nameMatch && !schoolMatch && !majorMatch && !headlineMatch && !skillMatch) {
                    return false;
                }
            }

            // Quick Filters (Economic / FMCG)
            if (activeQuickFilters.has('marketing_pr')) {
                const mktSet = new Set(['digital marketing', 'marketing', 'pr', 'sáng tạo nội dung', 'content creator', 'content marketing', 'quản trị thương hiệu', 'social ads', 'seo', 'quảng bá', 'truyền thông']);
                const hasMkt = talent.skills.some(s => mktSet.has(s.toLowerCase().trim())) || /marketing|pr|truyền thông|brand|quảng cáo/i.test(talent.headline || talent.major_field || '');
                if (!hasMkt) return false;
            }

            if (activeQuickFilters.has('biz_mgmt')) {
                const bizSet = new Set(['quản trị kinh doanh', 'quản trị thương hiệu', 'phân tích thị trường', 'nghiên cứu thị trường', 'kinh doanh quốc tế', 'quản lý dự án', 'kỹ năng thuyết trình']);
                const hasBiz = talent.skills.some(s => bizSet.has(s.toLowerCase().trim())) || /kinh doanh|quản trị|business|qtkd|thương mại/i.test(talent.headline || talent.major_field || '');
                if (!hasBiz) return false;
            }

            if (activeQuickFilters.has('data_bi')) {
                const biSet = new Set(['powerbi', 'power bi', 'phân tích dữ liệu', 'data analysis', 'data analytics', 'excel nâng cao', 'sql', 'tableau', 'thống kê']);
                const hasBI = talent.skills.some(s => biSet.has(s.toLowerCase().trim())) || /bi|phân tích|data|dữ liệu|analytics/i.test(talent.headline || talent.major_field || '');
                if (!hasBI) return false;
            }

            if (activeQuickFilters.has('logistics_sc')) {
                const logSet = new Set(['logistics', 'quản trị kho vận', 'quản lý kho vận', 'chuỗi cung ứng', 'supply chain', 'tối ưu hóa đơn hàng', 'phân tích dữ liệu vận hành', 'vận hành']);
                const hasLog = talent.skills.some(s => logSet.has(s.toLowerCase().trim())) || /logistics|chuỗi cung ứng|kho vận|supply chain|vận tải/i.test(talent.headline || talent.major_field || '');
                if (!hasLog) return false;
            }

            if (activeQuickFilters.has('finance_acc')) {
                const finSet = new Set(['tài chính', 'kế toán', 'lập báo cáo tài chính', 'kế toán chi phí', 'cost accounting', 'finance', 'excel nâng cao', 'ifrs', 'kế toán quản trị']);
                const hasFin = talent.skills.some(s => finSet.has(s.toLowerCase().trim())) || /tài chính|kế toán|finance|accounting|ngân hàng/i.test(talent.headline || talent.major_field || '');
                if (!hasFin) return false;
            }

            // Quick Filters (Tech / IT)
            if (activeQuickFilters.has('ai_ml')) {
                const aiSkillsSet = new Set(['ai/ml', 'machine learning', 'deep learning', 'pytorch', 'tensorflow', 'trí tuệ nhân tạo', 'ai / machine learning', 'computer vision', 'opencv', 'python']);
                const hasAIML = talent.skills.some(s => aiSkillsSet.has(s.toLowerCase().trim())) || /ai|machine learning|computer vision|data/i.test(talent.headline || '');
                if (!hasAIML) return false;
            }

            if (activeQuickFilters.has('frontend')) {
                const feSkillsSet = new Set(['react', 'vue.js', 'vuejs', 'html', 'css', 'javascript', 'typescript', 'frontend', 'ui/ux', 'tailwind']);
                const hasFE = talent.skills.some(s => feSkillsSet.has(s.toLowerCase().trim())) || /frontend|react|vue|web/i.test(talent.headline || '');
                if (!hasFE) return false;
            }

            if (activeQuickFilters.has('backend')) {
                const beSkillsSet = new Set(['node.js', 'nodejs', 'java', 'spring boot', 'springboot', 'docker', 'mysql', 'sql', 'rest api', 'backend', 'microservices', 'postgresql', 'python']);
                const hasBE = talent.skills.some(s => beSkillsSet.has(s.toLowerCase().trim())) || /backend|java|spring|node/i.test(talent.headline || '');
                if (!hasBE) return false;
            }

            if (activeQuickFilters.has('security')) {
                const secSkillsSet = new Set(['an toàn thông tin', 'cyber_security', 'cyber security', 'security', 'bảo mật', 'an ninh mạng']);
                const hasSec = talent.skills.some(s => secSkillsSet.has(s.toLowerCase().trim())) || /security|an toàn thông tin|an ninh/i.test(talent.headline || '');
                if (!hasSec) return false;
            }

            if (activeQuickFilters.has('ready_now')) {
                const isReady = (talent.internship_status === 'ready_now' || (talent.internship_status_label && talent.internship_status_label.includes('Sẵn sàng')));
                if (!isReady) return false;
            }

            // Check selected skills (all must match)
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

            if (activeFilters.majorField) {
                const reqMajor = activeFilters.majorField.toLowerCase();
                const candMajor = talent.major_field.toLowerCase();
                const candHead = (talent.headline || '').toLowerCase();
                if (!candMajor.includes(reqMajor) && !candHead.includes(reqMajor) && !reqMajor.includes(candMajor)) {
                    return false;
                }
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

    // Relevance scoring for smart sorting
    function calculateRelevanceScore(talent) {
        let boost = 0;
        const text = ((talent.headline || '') + ' ' + (talent.major_field || '') + ' ' + talent.skills.join(' ')).toLowerCase();

        if (isEconomicSector) {
            // Economic / FMCG priorities
            if (/marketing|kinh doanh|qtkd|quản trị|thị trường|phân tích dữ liệu|powerbi|toeic|logistics|tài chính|kế toán/i.test(text)) {
                boost += 150;
            }
            if (/lê hoàng yến nhi/i.test(talent.name)) boost += 200;
            if (/hoàng thị mai linh/i.test(talent.name)) boost += 180;
            if (/phạm quốc bảo/i.test(talent.name)) boost += 120;
        } else {
            // IT & Tech priorities
            if (/frontend|backend|react|node|python|ai|an toàn thông tin|lập trình|phần mềm|fullstack/i.test(text)) {
                boost += 150;
            }
            if (/nguyễn văn an/i.test(talent.name)) boost += 180;
            if (/trần minh đức/i.test(talent.name)) boost += 170;
            if (/võ đức anh/i.test(talent.name)) boost += 160;
        }
        return (talent.talent_score || talent.match_score || 85) + boost;
    }

    function sortTalentsList(list) {
        const sorted = [...list];
        if (currentSortOption === 'score_desc' || currentSortOption === 'matching') {
            sorted.sort((a, b) => calculateRelevanceScore(b) - calculateRelevanceScore(a));
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
                                ${talent.hasPendingContactRequest ? 'Đã yêu cầu' : 'Mời ứng tuyển'}
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
                const t = allTalents.find(item => item.id === tid);
                if (t) {
                    t.saved = !t.saved;
                    btn.classList.toggle('is-saved', t.saved);
                    showToast(t.saved ? `Đã lưu hồ sơ của ${t.name}` : `Đã bỏ lưu hồ sơ của ${t.name}`);
                }
            });
        });
    }

    function renderPagination(totalItems, totalPages) {
        if (!paginationWrapper || !paginationInfo || !paginationBtns) return;

        if (totalItems === 0 || totalPages <= 1) {
            paginationWrapper.style.display = 'none';
            return;
        }

        paginationWrapper.style.display = 'flex';
        paginationInfo.textContent = `Trang ${currentPage} / ${totalPages} (${totalItems} nhân tài)`;

        let btnsHtml = `
            <button type="button" class="btn btn-secondary btn-sm" id="page-prev-btn" ${currentPage === 1 ? 'disabled' : ''}>
                &larr; Trang trước
            </button>
        `;

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1) {
                btnsHtml += `
                    <button type="button" class="btn ${p === currentPage ? 'btn-primary' : 'btn-secondary'} btn-sm page-num-btn" data-page="${p}">
                        ${p}
                    </button>
                `;
            } else if (p === currentPage - 2 || p === currentPage + 2) {
                btnsHtml += `<span class="ent-page-ellipsis">...</span>`;
            }
        }

        btnsHtml += `
            <button type="button" class="btn btn-secondary btn-sm" id="page-next-btn" ${currentPage === totalPages ? 'disabled' : ''}>
                Trang sau &rarr;
            </button>
        `;

        paginationBtns.innerHTML = btnsHtml;

        paginationBtns.querySelectorAll('.page-num-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = parseInt(btn.getAttribute('data-page'), 10);
                if (targetPage && targetPage !== currentPage) {
                    currentPage = targetPage;
                    updateAndRender();
                    scrollToTopCards();
                }
            });
        });

        const prevBtn = document.getElementById('page-prev-btn');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    updateAndRender();
                    scrollToTopCards();
                }
            });
        }

        const nextBtn = document.getElementById('page-next-btn');
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

    // Sidebar Skill Checkboxes Handler
    skillCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const skillName = cb.value || cb.getAttribute('data-skill-name');
            if (cb.checked) {
                selectedSkillsSet.add(skillName);
            } else {
                selectedSkillsSet.delete(skillName);
            }
            renderSelectedSkillsTags();
            currentPage = 1;
            updateAndRender();
        });
    });

    if (filterEduLevel) filterEduLevel.addEventListener('change', (e) => { activeFilters.eduLevel = e.target.value; currentPage = 1; updateAndRender(); });
    if (filterSchool) filterSchool.addEventListener('change', (e) => { activeFilters.school = e.target.value; currentPage = 1; fetchFromApi(); });
    if (filterClassYear) filterClassYear.addEventListener('change', (e) => { activeFilters.classYear = e.target.value; currentPage = 1; updateAndRender(); });
    if (filterMajorField) filterMajorField.addEventListener('change', (e) => { activeFilters.majorField = e.target.value; currentPage = 1; updateAndRender(); });
    if (filterMatchScore) filterMatchScore.addEventListener('change', (e) => { activeFilters.matchScore = parseInt(e.target.value, 10) || 0; currentPage = 1; updateAndRender(); });
    if (filterExpHours) filterExpHours.addEventListener('change', (e) => { activeFilters.expHours = parseInt(e.target.value, 10) || 0; currentPage = 1; updateAndRender(); });
    if (filterReadiness) filterReadiness.addEventListener('change', (e) => { activeFilters.readiness = e.target.value; currentPage = 1; updateAndRender(); });

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
        skillCheckboxes.forEach(cb => { cb.checked = false; });
        popularSkillPills.forEach(p => p.classList.remove('is-active'));
        renderSelectedSkillsTags();

        activeFilters.eduLevel = '';
        activeFilters.school = '';
        activeFilters.classYear = '';
        activeFilters.majorField = '';
        activeFilters.matchScore = 0;
        activeFilters.expHours = 0;
        activeFilters.readiness = '';

        if (filterEduLevel) filterEduLevel.value = '';
        if (filterSchool) filterSchool.value = '';
        if (filterClassYear) filterClassYear.value = '';
        if (filterMajorField) filterMajorField.value = '';
        if (filterMatchScore) filterMatchScore.value = '0';
        if (filterExpHours) filterExpHours.value = '0';
        if (filterReadiness) filterReadiness.value = '';

        currentPage = 1;
        fetchFromApi();
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', () => {
            currentPage = 1;
            fetchFromApi();
        });
    }
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', resetAllFilters);
    if (resetHeaderBtn) resetHeaderBtn.addEventListener('click', resetAllFilters);
    if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);

    // 8. Skills Modal & Chips Handlers
    function renderSelectedSkillsTags() {
        const count = selectedSkillsSet.size;

        if (selectedSkillsCountEl) selectedSkillsCountEl.textContent = count;
        if (modalSelectedCountEl) modalSelectedCountEl.textContent = count;

        if (selectedSkillsWrapper) {
            selectedSkillsWrapper.style.display = count > 0 ? 'block' : 'none';
        }

        const tagsHtml = Array.from(selectedSkillsSet).map(sk => `
            <span class="ent-selected-skill-tag" data-skill="${escapeHtml(sk)}">
                <span>${escapeHtml(sk)}</span>
                <button type="button" class="ent-remove-skill-tag" aria-label="Xóa kỹ năng ${escapeHtml(sk)}">&times;</button>
            </span>
        `).join('');

        if (selectedSkillsTagsContainer) selectedSkillsTagsContainer.innerHTML = tagsHtml;
        if (selectedSkillsChipsContainer) selectedSkillsChipsContainer.innerHTML = tagsHtml;

        // Sync sidebar checkboxes
        skillCheckboxes.forEach(cb => {
            const sk = cb.value || cb.getAttribute('data-skill-name');
            cb.checked = selectedSkillsSet.has(sk);
        });

        // Attach remove tag event
        document.querySelectorAll('.ent-remove-skill-tag, .ent-remove-skill-chip').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tagEl = e.target.closest('[data-skill]');
                if (!tagEl) return;
                const sk = tagEl.getAttribute('data-skill');
                selectedSkillsSet.delete(sk);
                renderSelectedSkillsTags();
                currentPage = 1;
                updateAndRender();
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
            renderSelectedSkillsTags();
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
            console.log(msg);
        }
    }

    // Initial render
    updateAndRender();
}
