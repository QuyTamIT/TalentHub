/**
 * TalentHub - Enterprise Talent Search & AI Matching Controller
 * Handles live API search, multi-criteria filtering, dynamic quick filter pills,
 * skills selection modal, pagination, candidate navigation, and AI candidate matching.
 *
 * NOTE: Strict Privacy & Security rules:
 * - Safe DOM methods only (createElement, textContent, replaceChildren).
 * - NO fake score fallbacks.
 * - Handles ready_model, stale_model, provider_unavailable, no_candidates.
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
            sessionBoot = Object.assign(sessionBoot, JSON.parse(bootElement.textContent || '{}'));
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

    // Enterprise AI Matching DOM Elements
    const aiMatcherContainer = document.querySelector('[data-enterprise-ai-matcher]');
    const aiJobSelect = document.querySelector('[data-enterprise-ai-job]');
    const aiRunBtn = document.querySelector('[data-enterprise-ai-run]');
    const aiStateEl = document.querySelector('[data-enterprise-ai-state]');
    const aiResultsEl = document.querySelector('[data-enterprise-ai-results]');
    const aiFreshnessEl = document.querySelector('[data-enterprise-ai-freshness]');
    const aiProvenanceEl = document.querySelector('[data-enterprise-ai-provenance]');

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
        const score = typeof raw.talentScore === 'number'
            ? raw.talentScore
            : (typeof raw.talent_score === 'number' ? raw.talent_score : null);

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
            match_score: null,
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

            // Check selected skills
            if (selectedSkillsSet.size > 0) {
                const hasAllSelected = Array.from(selectedSkillsSet).every(reqSkill => candidateHasSkill(talent, reqSkill));
                if (!hasAllSelected) return false;
            }

            if (activeFilters.eduLevel && talent.education_level !== activeFilters.eduLevel) return false;
            if (activeFilters.school && talent.school !== activeFilters.school) return false;

            if (activeFilters.majorField) {
                const reqMajor = activeFilters.majorField.toLowerCase();
                const candMajor = talent.major_field.toLowerCase();
                const candHead = (talent.headline || '').toLowerCase();
                if (!candMajor.includes(reqMajor) && !candHead.includes(reqMajor) && !reqMajor.includes(candMajor)) {
                    return false;
                }
            }

            if (activeFilters.matchScore > 0 && (!Number.isFinite(talent.talent_score) || talent.talent_score < activeFilters.matchScore)) return false;
            if (activeFilters.expHours > 0 && talent.experience_hours < activeFilters.expHours) return false;

            return true;
        });
    }

    function calculateRelevanceScore(talent) {
        return Number.isFinite(talent.talent_score) ? talent.talent_score : 0;
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

    // 6. Render Pipeline (Safe DOM construction)
    function updateAndRender() {
        const filtered = getFilteredTalents();
        const sorted = sortTalentsList(filtered);

        if (totalBadgeNum) {
            totalBadgeNum.textContent = String(sorted.length);
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
            if (cardsContainer) cardsContainer.replaceChildren();
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
        cardsContainer.replaceChildren();

        talents.forEach(talent => {
            const article = document.createElement('article');
            article.className = 'ent-talent-card-item';
            article.setAttribute('data-talent-id', talent.id);

            // Header
            const header = document.createElement('div');
            header.className = 'ent-talent-card-item__header';

            const userDiv = document.createElement('div');
            userDiv.className = 'ent-talent-card-item__user';

            const avatar = document.createElement('div');
            avatar.className = 'ent-talent-card-item__avatar';
            avatar.textContent = talent.avatar_initials;

            const titleBox = document.createElement('div');
            titleBox.className = 'ent-talent-card-item__title-box';

            const nameRow = document.createElement('div');
            nameRow.className = 'ent-talent-card-item__name-row';

            const nameLink = document.createElement('a');
            nameLink.href = resolveCandidateDetailUrl(talent.id);
            nameLink.className = 'ent-talent-card-item__name';
            nameLink.textContent = talent.name;

            nameRow.appendChild(nameLink);
            if (Number.isFinite(talent.talent_score)) {
                const scoreBadge = document.createElement('span');
                scoreBadge.className = 'ent-talent-card-item__score';
                scoreBadge.title = 'Điểm đánh giá năng lực';
                scoreBadge.textContent = `${Math.round(talent.talent_score)}% năng lực`;
                nameRow.appendChild(scoreBadge);
            }

            const schoolDiv = document.createElement('div');
            schoolDiv.className = 'ent-talent-card-item__school';

            const schoolSpan = document.createElement('span');
            schoolSpan.textContent = talent.school || 'Nhà trường';
            schoolDiv.appendChild(schoolSpan);

            if (talent.major_field) {
                const dot = document.createElement('span');
                dot.className = 'ent-talent-card-item__dot';
                dot.textContent = '•';
                const majorSpan = document.createElement('span');
                majorSpan.textContent = talent.major_field;
                schoolDiv.appendChild(dot);
                schoolDiv.appendChild(majorSpan);
            }

            titleBox.appendChild(nameRow);
            titleBox.appendChild(schoolDiv);

            userDiv.appendChild(avatar);
            userDiv.appendChild(titleBox);

            const bookmarkBtn = document.createElement('button');
            bookmarkBtn.type = 'button';
            bookmarkBtn.className = `ent-bookmark-btn ${talent.saved ? 'is-saved' : ''}`;
            bookmarkBtn.setAttribute('data-action', 'save');
            bookmarkBtn.setAttribute('data-talent-id', talent.id);
            bookmarkBtn.title = talent.saved ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ này';
            bookmarkBtn.setAttribute('aria-label', talent.saved ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ');
            bookmarkBtn.textContent = talent.saved ? '★' : '☆';

            bookmarkBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                talent.saved = !talent.saved;
                bookmarkBtn.classList.toggle('is-saved', talent.saved);
                bookmarkBtn.textContent = talent.saved ? '★' : '☆';
                showToast(talent.saved ? `Đã lưu hồ sơ của ${talent.name}` : `Đã bỏ lưu hồ sơ của ${talent.name}`);
            });

            header.appendChild(userDiv);
            header.appendChild(bookmarkBtn);

            // Meta strip
            const metaStrip = document.createElement('div');
            metaStrip.className = 'ent-talent-card-item__meta-strip';

            const metaItem1 = document.createElement('div');
            metaItem1.className = 'ent-meta-item';
            const mLabel1 = document.createElement('span');
            mLabel1.className = 'ent-meta-item__label';
            mLabel1.textContent = 'Kỹ năng xác thực:';
            const mVal1 = document.createElement('span');
            mVal1.className = 'ent-meta-item__value font-semibold text-dark';
            mVal1.textContent = ` ${talent.skills.length} kỹ năng`;
            metaItem1.appendChild(mLabel1);
            metaItem1.appendChild(mVal1);

            const div1 = document.createElement('div');
            div1.className = 'ent-meta-item__divider';

            const metaItem2 = document.createElement('div');
            metaItem2.className = 'ent-meta-item';
            const mLabel2 = document.createElement('span');
            mLabel2.className = 'ent-meta-item__label';
            mLabel2.textContent = 'Trạng thái:';
            const mVal2 = document.createElement('span');
            mVal2.className = 'val-status badge-ready-now';
            mVal2.textContent = ` ${talent.internship_status_label}`;
            metaItem2.appendChild(mLabel2);
            metaItem2.appendChild(mVal2);

            metaStrip.appendChild(metaItem1);
            metaStrip.appendChild(div1);
            metaStrip.appendChild(metaItem2);

            // Skills
            const skillsDiv = document.createElement('div');
            skillsDiv.className = 'ent-talent-card-item__skills';
            const sLabel = document.createElement('span');
            sLabel.className = 'skills-label';
            sLabel.textContent = 'Kỹ năng:';
            const chipsDiv = document.createElement('div');
            chipsDiv.className = 'skills-chips';

            talent.skills.slice(0, 4).forEach(sk => {
                const chip = document.createElement('span');
                chip.className = 'skill-tag';
                chip.textContent = sk;
                chipsDiv.appendChild(chip);
            });
            if (talent.skills.length > 4) {
                const moreChip = document.createElement('span');
                moreChip.className = 'skill-tag skill-tag--more';
                moreChip.textContent = `+${talent.skills.length - 4}`;
                chipsDiv.appendChild(moreChip);
            }

            skillsDiv.appendChild(sLabel);
            skillsDiv.appendChild(chipsDiv);

            // Footer
            const footer = document.createElement('div');
            footer.className = 'ent-talent-card-item__footer';

            const privNote = document.createElement('div');
            privNote.className = 'ent-privacy-note';
            privNote.title = 'Thông tin liên hệ chỉ hiển thị khi ứng viên đồng ý kết nối';
            const privSpan = document.createElement('span');
            privSpan.textContent = talent.contactAllowed ? 'Đã có quyền liên hệ' : 'Hồ sơ có consent';
            privNote.appendChild(privSpan);

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'ent-talent-card-item__actions';

            const detailLink = document.createElement('a');
            detailLink.href = resolveCandidateDetailUrl(talent.id);
            detailLink.className = 'btn btn-secondary btn-sm';
            detailLink.textContent = 'Xem hồ sơ';

            const contactLink = document.createElement('a');
            contactLink.href = resolveCandidateDetailUrl(talent.id);
            contactLink.className = 'btn btn-primary btn-sm';
            contactLink.textContent = talent.hasPendingContactRequest ? 'Đã yêu cầu' : 'Mời ứng tuyển';

            actionsDiv.appendChild(detailLink);
            actionsDiv.appendChild(contactLink);

            footer.appendChild(privNote);
            footer.appendChild(actionsDiv);

            article.appendChild(header);
            article.appendChild(metaStrip);
            article.appendChild(skillsDiv);
            article.appendChild(footer);

            cardsContainer.appendChild(article);
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
        paginationBtns.replaceChildren();

        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'btn btn-secondary btn-sm';
        prevBtn.disabled = currentPage === 1;
        prevBtn.textContent = '← Trang trước';
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                updateAndRender();
                scrollToTopCards();
            }
        });
        paginationBtns.appendChild(prevBtn);

        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1) {
                const pBtn = document.createElement('button');
                pBtn.type = 'button';
                pBtn.className = `btn ${p === currentPage ? 'btn-primary' : 'btn-secondary'} btn-sm page-num-btn`;
                pBtn.textContent = String(p);
                pBtn.addEventListener('click', () => {
                    if (p !== currentPage) {
                        currentPage = p;
                        updateAndRender();
                        scrollToTopCards();
                    }
                });
                paginationBtns.appendChild(pBtn);
            } else if (p === currentPage - 2 || p === currentPage + 2) {
                const ell = document.createElement('span');
                ell.className = 'ent-page-ellipsis';
                ell.textContent = '...';
                paginationBtns.appendChild(ell);
            }
        }

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'btn btn-secondary btn-sm';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.textContent = 'Trang sau →';
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                updateAndRender();
                scrollToTopCards();
            }
        });
        paginationBtns.appendChild(nextBtn);
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

    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', () => { currentPage = 1; fetchFromApi(); });
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', resetAllFilters);
    if (resetHeaderBtn) resetHeaderBtn.addEventListener('click', resetAllFilters);
    if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);

    // Selected skills rendering
    function renderSelectedSkillsTags() {
        const count = selectedSkillsSet.size;
        if (selectedSkillsWrapper) selectedSkillsWrapper.style.display = count > 0 ? 'block' : 'none';
        if (selectedSkillsCountEl) selectedSkillsCountEl.textContent = String(count);

        if (selectedSkillsTagsContainer) {
            selectedSkillsTagsContainer.replaceChildren();
            selectedSkillsSet.forEach(sk => {
                const tag = document.createElement('span');
                tag.className = 'ent-selected-skill-tag';
                tag.textContent = sk + ' ';
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'ent-remove-skill-tag';
                rm.textContent = '×';
                rm.addEventListener('click', () => {
                    selectedSkillsSet.delete(sk);
                    renderSelectedSkillsTags();
                    currentPage = 1;
                    updateAndRender();
                });
                tag.appendChild(rm);
                selectedSkillsTagsContainer.appendChild(tag);
            });
        }
    }

    function renderModalSkills(searchFilter = '') {
        if (!skillsCategoriesContainer) return;
        const low = searchFilter.toLowerCase().trim();
        skillsCategoriesContainer.replaceChildren();

        SKILL_CATEGORIES.forEach(cat => {
            const filteredSkills = cat.skills.filter(s => !low || s.toLowerCase().includes(low));
            if (!filteredSkills.length) return;

            const block = document.createElement('div');
            block.className = 'ent-skill-category-block';

            const title = document.createElement('div');
            title.className = 'ent-skill-category-title';
            title.textContent = cat.name;
            block.appendChild(title);

            const chipsDiv = document.createElement('div');
            chipsDiv.className = 'ent-skill-category-chips';

            filteredSkills.forEach(sk => {
                const isSel = selectedSkillsSet.has(sk);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `ent-modal-skill-item ${isSel ? 'is-selected' : ''}`;
                btn.setAttribute('data-skill', sk);
                btn.textContent = sk;

                btn.addEventListener('click', () => {
                    if (selectedSkillsSet.has(sk)) {
                        selectedSkillsSet.delete(sk);
                        btn.classList.remove('is-selected');
                    } else {
                        selectedSkillsSet.add(sk);
                        btn.classList.add('is-selected');
                    }
                    if (modalSelectedCountEl) modalSelectedCountEl.textContent = String(selectedSkillsSet.size);
                });

                chipsDiv.appendChild(btn);
            });

            block.appendChild(chipsDiv);
            skillsCategoriesContainer.appendChild(block);
        });

        if (modalSelectedCountEl) modalSelectedCountEl.textContent = String(selectedSkillsSet.size);
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

    // 8. Enterprise AI Matcher Execution
    if (aiRunBtn && aiJobSelect) {
        aiRunBtn.addEventListener('click', async () => {
            const jobId = aiJobSelect.value.trim();
            if (!jobId) {
                showToast('Vui lòng chọn một vị trí thực tập đang tuyển dụng để tìm nhân tài phù hợp.');
                return;
            }

            if (aiStateEl) {
                aiStateEl.textContent = 'loading...';
                aiStateEl.className = 'badge badge-warning';
            }
            aiRunBtn.disabled = true;

            if (!window.crypto || (typeof window.crypto.randomUUID !== 'function' && typeof window.crypto.getRandomValues !== 'function')) {
                showToast('Trình duyệt không hỗ trợ yêu cầu bảo mật để chạy AI.');
                aiRunBtn.disabled = false;
                return;
            }
            const idempotencyKey = typeof window.crypto.randomUUID === 'function'
                ? window.crypto.randomUUID()
                : `ent-match-${Date.now()}-${Array.from(window.crypto.getRandomValues(new Uint32Array(2))).join('')}`;
            const payload = {
                jobId: jobId,
            };
            if (selectedSkillsSet.size > 0) {
                payload.requiredSkills = Array.from(selectedSkillsSet);
            }

            try {
                const response = await fetch(`${sessionBoot.apiBase}/ai-matches`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': sessionBoot.csrfToken,
                        'X-Idempotency-Key': idempotencyKey,
                    },
                    body: JSON.stringify(payload),
                });

                const result = await response.json();
                if (!response.ok) {
                    const msg = result.error?.message || 'Không thể thực hiện khớp nối AI.';
                    showToast(msg);
                    if (aiStateEl) {
                        aiStateEl.textContent = 'error';
                        aiStateEl.className = 'badge badge-danger';
                    }
                    return;
                }

                const matchData = result.data || result;
                const status = matchData.state || 'provider_unavailable';

                if (aiProvenanceEl && matchData.model_version) {
                    aiProvenanceEl.textContent = matchData.model_version + (status === 'stale_model' ? ' (cached LKG)' : '');
                }
                if (aiFreshnessEl && (matchData.generated_at || matchData.updated_at)) {
                    aiFreshnessEl.textContent = matchData.generated_at || matchData.updated_at;
                }

                if (aiStateEl) {
                    aiStateEl.textContent = status;
                    aiStateEl.className = status === 'ready_model' ? 'badge badge-success' : (status === 'stale_model' ? 'badge badge-warning' : 'badge badge-secondary');
                }

                renderAiMatchResults(status, matchData.items || [], matchData);
            } catch (err) {
                showToast('Lỗi mạng khi kết nối dịch vụ AI.');
                if (aiStateEl) {
                    aiStateEl.textContent = 'provider_unavailable';
                    aiStateEl.className = 'badge badge-danger';
                }
                renderAiMatchResults('provider_unavailable', [], {});
            } finally {
                aiRunBtn.disabled = false;
            }
        });
    }

    function renderAiMatchResults(status, items, matchData) {
        if (!aiResultsEl) return;
        aiResultsEl.replaceChildren();
        aiResultsEl.style.display = 'block';

        if (status === 'provider_unavailable') {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = 'Dịch vụ AI hiện tại tạm thời không khả dụng. Không có dữ liệu phân tích đã lưu trước đó.';
            aiResultsEl.appendChild(alert);
            return;
        }

        if (status !== 'ready_model' && status !== 'stale_model') {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info';
            alert.textContent = 'Kết quả AI chưa sẵn sàng để hiển thị.';
            aiResultsEl.appendChild(alert);
            return;
        }

        if (status === 'no_candidates' || items.length === 0) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info';
            alert.textContent = 'Không có ứng viên phù hợp nào với các tiêu chí kỹ năng của vị trí này.';
            aiResultsEl.appendChild(alert);
            return;
        }

        if (status === 'stale_model') {
            const staleBanner = document.createElement('div');
            staleBanner.className = 'alert alert-warning mb-3';
            staleBanner.textContent = 'Dịch vụ AI đang gián đoạn tạm thời. Dưới đây là kết quả phân tích AI đã lưu trước đó (LKG cache).';
            aiResultsEl.appendChild(staleBanner);
        }

        const heading = document.createElement('h4');
        heading.className = 'h6 font-weight-bold text-dark mb-3';
        heading.textContent = `Kết quả xếp hạng phù hợp (${items.length} ứng viên):`;
        aiResultsEl.appendChild(heading);

        const listContainer = document.createElement('div');
        listContainer.className = 'd-flex flex-column gap-3';

        items.forEach((item, idx) => {
            const card = document.createElement('div');
            card.className = 'card p-3 shadow-sm border-0';
            card.style.borderRadius = '10px';
            card.style.background = '#ffffff';

            const headerRow = document.createElement('div');
            headerRow.className = 'd-flex justify-content-between align-items-center mb-2';

            const nameBox = document.createElement('div');
            nameBox.className = 'd-flex align-items-center gap-2';

            const rankBadge = document.createElement('span');
            rankBadge.className = 'badge bg-light text-dark border';
            rankBadge.textContent = `#${idx + 1}`;

            const nameEl = document.createElement('strong');
            nameEl.className = 'text-primary';
            nameEl.textContent = item.candidate_name || item.candidate_ref || `Ứng viên ${idx + 1}`;

            nameBox.appendChild(rankBadge);
            nameBox.appendChild(nameEl);

            const scoreEl = document.createElement('div');
            scoreEl.className = 'badge bg-success text-white px-2 py-1';
            scoreEl.style.fontSize = '0.9rem';
            scoreEl.textContent = typeof item.match_score === 'number'
                ? `${Math.round(item.match_score)}% Phù hợp`
                : 'Điểm AI không hợp lệ';

            headerRow.appendChild(nameBox);
            headerRow.appendChild(scoreEl);
            card.appendChild(headerRow);

            // Matched skills & Skill gaps
            const skillsRow = document.createElement('div');
            skillsRow.className = 'd-flex flex-wrap gap-1 mb-2';

            (item.matched_skills || []).forEach(sk => {
                const tag = document.createElement('span');
                tag.className = 'badge bg-primary-subtle text-primary border border-primary px-2 py-1';
                tag.textContent = `✓ ${sk}`;
                skillsRow.appendChild(tag);
            });

            (item.skill_gaps || []).forEach(gap => {
                const tag = document.createElement('span');
                tag.className = 'badge bg-warning-subtle text-warning border border-warning px-2 py-1';
                tag.textContent = `! Thiếu: ${gap}`;
                skillsRow.appendChild(tag);
            });

            card.appendChild(skillsRow);

            // Reason codes & Evidence
            if (Array.isArray(item.reason_codes) && item.reason_codes.length > 0) {
                const reasonsBox = document.createElement('div');
                reasonsBox.className = 'small text-muted mb-2';
                const reasonLabels = {
                    'verified_skill_match': 'Khớp kỹ năng xác thực',
                    'partial_skill_match': 'Khớp một phần kỹ năng',
                    'skill_gap': 'Còn thiếu một số kỹ năng',
                    'strong_verified_level': 'Trình độ kỹ năng vượt trội',
                };
                const translatedReasons = item.reason_codes.map(r => reasonLabels[r] || r);
                reasonsBox.textContent = 'Lý do: ' + translatedReasons.join(', ');
                card.appendChild(reasonsBox);
            }

            // Evidence
            if (Array.isArray(item.evidence) && item.evidence.length > 0) {
                const evidenceList = document.createElement('ul');
                evidenceList.className = 'small text-secondary mb-0 ps-3';
                item.evidence.forEach(ev => {
                    const li = document.createElement('li');
                    const safeValue = ev.safe_value || ev;
                    li.textContent = `${safeValue.skill || 'Kỹ năng'}: Trình độ ${safeValue.level_score || 0}/100 (Đã xác thực)`;
                    evidenceList.appendChild(li);
                });
                card.appendChild(evidenceList);
            }

            listContainer.appendChild(card);
        });

        aiResultsEl.appendChild(listContainer);
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
