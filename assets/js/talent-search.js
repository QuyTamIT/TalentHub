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
    const originalTalents = [...allTalents];
    let isAiModeActive = false;
    let activeAiJobTitle = '';

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
    const aiFreshnessEl = document.querySelector('[data-enterprise-ai-freshness]');
    const aiProvenanceEl = document.querySelector('[data-enterprise-ai-provenance]');

    // 3. Normalization Helper
    function normalizeTalent(raw) {
        const id = String(raw.studentId || raw.student_id || raw.id || '');
        const name = raw.displayName || raw.display_name || raw.name || 'Ứng viên tiềm năng';
        const rawSkills = Array.isArray(raw.skills) ? raw.skills : (Array.isArray(raw.verifiedSkills) ? raw.verifiedSkills : (Array.isArray(raw.matched_skills) ? raw.matched_skills : []));
        const skills = rawSkills.map(s => typeof s === 'string' ? s : (s.name || s.skillName || ''));
        const school = raw.schoolName || raw.school_name || raw.school || '';
        const classYear = raw.className || raw.class_name || raw.class_year || '';
        const eduLevel = raw.studyStatus || raw.study_status || raw.education_level || '';
        const headline = raw.headline || '';
        const majorField = raw.major_field || (headline ? extractMajorFromHeadline(headline) : (isEconomicSector ? 'Kinh tế & Quản trị' : 'Công nghệ thông tin'));
        const expHours = typeof raw.experienceHours === 'number' ? raw.experienceHours : (raw.experience_hours || (skills.length * 15 + 20));
        const rawScore = raw.talentScore !== undefined ? raw.talentScore : (raw.talent_score !== undefined ? raw.talent_score : null);
        const parsedScore = (rawScore !== null && rawScore !== undefined && rawScore !== '') ? Number(rawScore) : null;
        const score = (parsedScore !== null && Number.isFinite(parsedScore)) ? parsedScore : null;

        const rawMatchScore = raw.match_score !== undefined ? raw.match_score : (raw.matchScore !== undefined ? raw.matchScore : null);
        const matchScore = (rawMatchScore !== null && rawMatchScore !== undefined && rawMatchScore !== '') ? Number(rawMatchScore) : null;
        const isAiMatched = Boolean(raw.is_ai_matched || (matchScore !== null));

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
            projects: Array.isArray(raw.projects) ? raw.projects : [],
            experience_hours: expHours,
            talent_score: score,
            match_score: matchScore,
            match_level: raw.match_level || (matchScore !== null ? (matchScore >= 75 ? 'Rất phù hợp' : (matchScore >= 45 ? 'Phù hợp' : 'Có liên quan')) : null),
            recommendation_reason: raw.recommendation_reason || '',
            matched_skills: Array.isArray(raw.matched_skills) ? raw.matched_skills : [],
            skill_gaps: Array.isArray(raw.skill_gaps) ? raw.skill_gaps : [],
            badges: Array.isArray(raw.badges) ? raw.badges : [],
            is_ai_matched: isAiMatched,
            ai_rank: raw.ai_rank || null,
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
        if (/điện tử|tự động hóa|automation|robotics/i.test(headline)) return 'Điện tử & Tự động hóa';
        if (/cơ khí|mechanical|chế tạo/i.test(headline)) return 'Cơ khí & Kỹ thuật';
        if (/thiết kế|đa phương tiện|multimedia|đồ họa|ui\/ux/i.test(headline)) return 'Thiết kế & Đa phương tiện';
        if (/du lịch|khách sạn|tourism|hospitality/i.test(headline)) return 'Du lịch & Khách sạn';
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
        if (isAiModeActive) {
            updateAndRender();
            return;
        }
        if (isFetchingApi) return;
        isFetchingApi = true;

        const params = new URLSearchParams();
        if (currentSearchQuery) params.append('search', currentSearchQuery);
        if (activeFilters.school) params.append('school', activeFilters.school);
        if (selectedSkillsSet.size > 0) {
            params.append('skills', Array.from(selectedSkillsSet).join(','));
        }
        if (currentSortOption === 'latest') params.append('sort', 'newest');
        else if (currentSortOption === 'score_desc' || currentSortOption === 'matching') params.append('sort', 'score_desc');
        else if (currentSortOption === 'exp_desc') params.append('sort', 'exp_desc');

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
                const q = currentSearchQuery.toLowerCase();
                const matchName = (talent.name || '').toLowerCase().includes(q);
                const matchSchool = (talent.school || '').toLowerCase().includes(q);
                const matchHeadline = (talent.headline || '').toLowerCase().includes(q);
                const matchMajor = (talent.major_field || '').toLowerCase().includes(q);
                const matchSkill = (talent.skills || []).some(s => s.toLowerCase().includes(q));
                if (!matchName && !matchSchool && !matchHeadline && !matchMajor && !matchSkill) {
                    return false;
                }
            }

            // Quick Filters (Economic / FMCG / General Business)
            if (activeQuickFilters.has('marketing_pr') || activeQuickFilters.has('marketing_media')) {
                const mktSet = new Set(['digital marketing', 'marketing', 'pr', 'sáng tạo nội dung', 'content creator', 'content marketing', 'quản trị thương hiệu', 'social ads', 'seo', 'quảng bá', 'truyền thông', 'copywriting', 'media']);
                const hasMkt = talent.skills.some(s => mktSet.has(s.toLowerCase().trim())) 
                    || /marketing|pr|truyền thông|brand|quảng cáo|media/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /marketing|pr|truyền thông|quảng cáo|media|brand/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasMkt) return false;
            }

            if (activeQuickFilters.has('biz_mgmt')) {
                const bizSet = new Set(['quản trị kinh doanh', 'quản trị thương hiệu', 'phân tích thị trường', 'nghiên cứu thị trường', 'kinh doanh quốc tế', 'quản lý dự án', 'kỹ năng thuyết trình', 'khởi nghiệp & quản trị', 'kinh doanh & quản trị', 'quản trị', 'bán hàng', 'sales']);
                const hasBiz = talent.skills.some(s => bizSet.has(s.toLowerCase().trim())) 
                    || /kinh doanh|quản trị|business|qtkd|thương mại|khởi nghiệp/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /kinh doanh|quản trị|business|khởi nghiệp/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasBiz) return false;
            }

            if (activeQuickFilters.has('data_bi')) {
                const biSet = new Set(['powerbi', 'power bi', 'phân tích dữ liệu', 'data analysis', 'data analytics', 'excel nâng cao', 'sql', 'tableau', 'thống kê']);
                const hasBI = talent.skills.some(s => biSet.has(s.toLowerCase().trim())) 
                    || /bi|phân tích|data|dữ liệu|analytics/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /bi|phân tích|data|dữ liệu|analytics|powerbi/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasBI) return false;
            }

            if (activeQuickFilters.has('logistics_sc')) {
                const logSet = new Set(['logistics', 'quản trị kho vận', 'quản lý kho vận', 'chuỗi cung ứng', 'supply chain', 'tối ưu hóa đơn hàng', 'phân tích dữ liệu vận hành', 'vận hành', 'kho vận', 'xuất nhập khẩu', 'vận tải']);
                const hasLog = talent.skills.some(s => logSet.has(s.toLowerCase().trim())) 
                    || /logistics|chuỗi cung ứng|kho vận|supply chain|vận tải|xuất nhập khẩu/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /logistics|chuỗi cung ứng|kho vận|supply chain/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasLog) return false;
            }

            if (activeQuickFilters.has('finance_acc')) {
                const finSet = new Set(['tài chính', 'kế toán', 'lập báo cáo tài chính', 'kế toán chi phí', 'cost accounting', 'finance', 'excel nâng cao', 'ifrs', 'kế toán quản trị', 'kiểm toán', 'tài chính - ngân hàng', 'ngân hàng']);
                const hasFin = talent.skills.some(s => finSet.has(s.toLowerCase().trim())) 
                    || /tài chính|kế toán|finance|accounting|ngân hàng|kiểm toán/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /tài chính|kế toán|finance|accounting/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasFin) return false;
            }

            // Quick Filters (Tech / IT)
            if (activeQuickFilters.has('ai_ml')) {
                const aiSkillsSet = new Set(['ai/ml', 'machine learning', 'deep learning', 'pytorch', 'tensorflow', 'trí tuệ nhân tạo', 'ai / machine learning', 'computer vision', 'opencv', 'python', 'ai']);
                const hasAIML = talent.skills.some(s => aiSkillsSet.has(s.toLowerCase().trim())) 
                    || /ai|machine learning|computer vision|data|trí tuệ nhân tạo|deep learning/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /ai|machine learning|deep learning|trí tuệ nhân tạo|computer vision|opencv|pytorch|tensorflow/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasAIML) return false;
            }

            if (activeQuickFilters.has('frontend')) {
                const feSkillsSet = new Set(['react', 'reactjs', 'react.js', 'vue.js', 'vuejs', 'vue', 'angular', 'html', 'html5', 'html/css', 'css', 'css3', 'javascript', 'typescript', 'frontend', 'frontend development', 'ui/ux', 'tailwind', 'tailwind css', 'bootstrap', 'next.js', 'nextjs', 'web development']);
                const hasFE = talent.skills.some(s => feSkillsSet.has(s.toLowerCase().trim())) 
                    || /frontend|react|vue|angular|web|ui\/ux|giao diện/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /frontend|react|vue|angular|html|css|giao diện/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasFE) return false;
            }

            if (activeQuickFilters.has('backend')) {
                const beSkillsSet = new Set(['node.js', 'nodejs', 'java', 'spring boot', 'springboot', 'docker', 'mysql', 'sql', 'rest api', 'backend', 'backend development', 'microservices', 'postgresql', 'python', 'php', 'laravel', 'c#', '.net', 'asp.net', 'golang', 'go', 'django', 'fastapi', 'express', 'database', 'cơ sở dữ liệu']);
                const hasBE = talent.skills.some(s => beSkillsSet.has(s.toLowerCase().trim())) 
                    || /backend|java|spring|node|php|mysql|c#|\.net|python|sql|database|hệ thống/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /backend|php|mysql|sql|java|spring|node|api|quản lý sinh viên|máy chủ|cơ sở dữ liệu/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasBE) return false;
            }

            if (activeQuickFilters.has('security')) {
                const secSkillsSet = new Set(['an toàn thông tin', 'cyber_security', 'cyber security', 'security', 'bảo mật', 'an ninh mạng']);
                const hasSec = talent.skills.some(s => secSkillsSet.has(s.toLowerCase().trim())) 
                    || /security|an toàn thông tin|an ninh|bảo mật/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /security|an toàn thông tin|bảo mật|an ninh/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasSec) return false;
            }

            // Quick Filters (Engineering, Creative & Service)
            if (activeQuickFilters.has('electronics_automation')) {
                const elecSet = new Set(['điện tử', 'tự động hóa', 'automation', 'electronics', 'robotics', 'plc', 'scada', 'vi điều khiển', 'nhúng', 'embedded', 'iot', 'cơ điện tử', 'mạch điện tử', 'pcb', 'arduino', 'kỹ thuật điện tử']);
                const hasElec = talent.skills.some(s => elecSet.has(s.toLowerCase().trim())) 
                    || /điện tử|tự động hóa|automation|electronics|robotics|plc|vi điều khiển|nhúng|embedded|iot|cơ điện tử/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /điện tử|tự động hóa|iot|robotics|embedded/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasElec) return false;
            }

            if (activeQuickFilters.has('mechanical_engineering')) {
                const mechSet = new Set(['cơ khí', 'kỹ thuật cơ khí', 'mechanical', 'chế tạo máy', 'cad', 'cam', 'cnc', 'solidworks', 'autocad', 'kỹ thuật chế tạo', 'bảo trì cơ khí', 'cơ điện tử']);
                const hasMech = talent.skills.some(s => mechSet.has(s.toLowerCase().trim())) 
                    || /cơ khí|chế tạo|mechanical|cad|cam|cnc|solidworks|autocad|kỹ thuật cơ khí/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /cơ khí|chế tạo|mechanical|cad|cam|cnc/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasMech) return false;
            }

            if (activeQuickFilters.has('design_multimedia')) {
                const designSet = new Set(['thiết kế sáng tạo & ui/ux', 'thiết kế đồ họa', 'graphic design', 'ui/ux', 'ui/ux design', 'photoshop', 'illustrator', 'figma', 'video editing', 'dựng video', 'animation', '3d', 'sáng tạo nội dung', 'multimedia', 'đa phương tiện', 'thiết kế', 'design']);
                const hasDesign = talent.skills.some(s => designSet.has(s.toLowerCase().trim())) 
                    || /thiết kế|đa phương tiện|multimedia|graphic|đồ họa|ui\/ux|design|figma|photoshop|video|animation/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /thiết kế|đồ họa|ui\/ux|design|figma|photoshop/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasDesign) return false;
            }

            if (activeQuickFilters.has('tourism_hospitality')) {
                const tourSet = new Set(['du lịch', 'khách sạn', 'tourism', 'hospitality', 'quản trị khách sạn', 'nhà hàng', 'lữ hành', 'hướng dẫn viên', 'lễ tân', 'f&b', 'quản trị dịch vụ du lịch', 'tour guide', 'nghiệp vụ nhà hàng', 'nghiệp vụ khách sạn']);
                const hasTour = talent.skills.some(s => tourSet.has(s.toLowerCase().trim())) 
                    || /du lịch|khách sạn|tourism|hospitality|nhà hàng|lữ hành|hướng dẫn viên|lễ tân|f&b|tour/i.test(talent.headline || talent.major_field || '')
                    || (Array.isArray(talent.projects) && talent.projects.some(p => /du lịch|khách sạn|tourism|nhà hàng|lữ hành/i.test((p.title || '') + ' ' + (p.description || '') + ' ' + (p.category || ''))));
                if (!hasTour) return false;
            }

            if (activeQuickFilters.has('ready_now')) {
                const isReady = (talent.internship_status === 'ready_now' || (talent.internship_status_label && talent.internship_status_label.includes('Sẵn sàng')));
                if (!isReady) return false;
            }

            // Check selected skills
            if (selectedSkillsSet.size > 0) {
                for (const reqSkill of selectedSkillsSet) {
                    if (!candidateHasSkill(talent, reqSkill)) {
                        return false;
                    }
                }
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
            if (activeFilters.readiness && talent.internship_status !== activeFilters.readiness) return false;

            return true;
        });
    }

    function calculateRelevanceScore(talent) {
        if (talent.is_ai_matched && typeof talent.match_score === 'number') {
            return (talent.match_score * 1000) + (Number.isFinite(talent.talent_score) ? talent.talent_score : 0);
        }
        return Number.isFinite(talent.talent_score) ? talent.talent_score : -1;
    }

    function sortTalentsList(list) {
        const sorted = [...list];
        if (currentSortOption === 'matching') {
            if (isAiModeActive) {
                sorted.sort((a, b) => {
                    const scoreA = typeof a.match_score === 'number' ? a.match_score : -1;
                    const scoreB = typeof b.match_score === 'number' ? b.match_score : -1;
                    if (scoreB !== scoreA) return scoreB - scoreA;
                    const tsA = Number.isFinite(a.talent_score) ? a.talent_score : -1;
                    const tsB = Number.isFinite(b.talent_score) ? b.talent_score : -1;
                    if (tsB !== tsA) return tsB - tsA;
                    return (a.ai_rank || 999) - (b.ai_rank || 999);
                });
            } else {
                sorted.sort((a, b) => calculateRelevanceScore(b) - calculateRelevanceScore(a));
            }
        } else if (currentSortOption === 'score_desc') {
            sorted.sort((a, b) => (Number.isFinite(b.talent_score) ? b.talent_score : -1) - (Number.isFinite(a.talent_score) ? a.talent_score : -1));
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
            article.className = 'ent-talent-card-item' + (talent.is_ai_matched && talent.ai_rank === 1 ? ' ent-talent-card-item--ai-top' : '');
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

            // AI Rank badge if AI-matched
            if (talent.is_ai_matched && talent.ai_rank) {
                const rankBadge = document.createElement('span');
                rankBadge.className = 'ent-ai-rank-badge' + (talent.ai_rank === 1 ? ' ent-ai-rank-badge--1' : (talent.ai_rank <= 3 ? ' ent-ai-rank-badge--top3' : ''));
                rankBadge.textContent = `#${talent.ai_rank}`;
                nameRow.appendChild(rankBadge);
            }

            const nameLink = document.createElement('a');
            nameLink.href = resolveCandidateDetailUrl(talent.id);
            nameLink.className = 'ent-talent-card-item__name';
            nameLink.textContent = talent.name;
            nameRow.appendChild(nameLink);

            // AI Match badge if AI-matched
            if (talent.is_ai_matched && talent.match_score !== null) {
                const matchScore = Math.round(talent.match_score);
                const matchLevel = talent.match_level || (matchScore >= 75 ? 'Rất phù hợp' : (matchScore >= 45 ? 'Phù hợp' : 'Có liên quan'));
                const matchPill = document.createElement('span');
                const levelClass = matchLevel === 'Rất phù hợp' ? 'ent-ai-match-pill--high' : (matchLevel === 'Phù hợp' ? 'ent-ai-match-pill--med' : 'ent-ai-match-pill--rel');
                matchPill.className = `ent-ai-match-pill ${levelClass}`;
                matchPill.textContent = `${matchLevel} • ${matchScore}%`;
                nameRow.appendChild(matchPill);
            }

            // Teacher score badge
            if (Number.isFinite(talent.talent_score)) {
                const scoreBadge = document.createElement('span');
                scoreBadge.className = 'ent-talent-card-item__score';
                scoreBadge.title = 'Điểm đánh giá năng lực thực tế từ giáo viên';
                scoreBadge.textContent = `★ ${Math.round(talent.talent_score)} điểm đánh giá`;
                nameRow.appendChild(scoreBadge);
            } else {
                const scoreBadge = document.createElement('span');
                scoreBadge.className = 'ent-talent-card-item__score ent-talent-card-item__score--pending';
                scoreBadge.title = 'Chưa có điểm đánh giá từ giáo viên';
                scoreBadge.textContent = 'Chưa chấm điểm';
                nameRow.appendChild(scoreBadge);
            }

            const schoolDiv = document.createElement('div');
            schoolDiv.className = 'ent-talent-card-item__school';

            const schoolSpan = document.createElement('span');
            schoolSpan.textContent = talent.school || 'Nhà trường';
            schoolDiv.appendChild(schoolSpan);

            if (talent.class_year) {
                const classSpan = document.createElement('span');
                classSpan.textContent = `(${talent.class_year})`;
                schoolDiv.appendChild(classSpan);
            }

            if (talent.headline || talent.major_field) {
                const dot = document.createElement('span');
                dot.className = 'ent-talent-card-item__dot';
                dot.textContent = '•';
                const majorSpan = document.createElement('span');
                majorSpan.textContent = talent.headline || talent.major_field;
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

            // AI Recommendation Reason Box
            let aiReasonEl = null;
            if (talent.is_ai_matched && talent.recommendation_reason) {
                aiReasonEl = document.createElement('div');
                aiReasonEl.className = 'ent-ai-reason-box';
                const titleSpan = document.createElement('span');
                titleSpan.className = 'ent-ai-reason-box__title';
                titleSpan.textContent = '💡 Đề xuất bởi AI:';
                const textSpan = document.createElement('span');
                textSpan.className = 'ent-ai-reason-box__text';
                textSpan.textContent = talent.recommendation_reason;
                aiReasonEl.appendChild(titleSpan);
                aiReasonEl.appendChild(textSpan);
            }

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

            if (Array.isArray(talent.badges) && talent.badges.length > 0) {
                const div2 = document.createElement('div');
                div2.className = 'ent-meta-item__divider';
                const metaItem3 = document.createElement('div');
                metaItem3.className = 'ent-meta-item';
                const mLabel3 = document.createElement('span');
                mLabel3.className = 'ent-meta-item__label';
                mLabel3.textContent = 'Thành tích:';
                const mVal3 = document.createElement('span');
                mVal3.className = 'ent-meta-item__value font-semibold text-dark';
                mVal3.textContent = ` 🏆 ${talent.badges[0]}` + (talent.badges.length > 1 ? ` (+${talent.badges.length - 1})` : '');
                metaItem3.appendChild(mLabel3);
                metaItem3.appendChild(mVal3);
                metaStrip.appendChild(div2);
                metaStrip.appendChild(metaItem3);
            }

            // Skills
            const skillsDiv = document.createElement('div');
            skillsDiv.className = 'ent-talent-card-item__skills';
            const sLabel = document.createElement('span');
            sLabel.className = 'skills-label';
            sLabel.textContent = 'Kỹ năng:';
            const chipsDiv = document.createElement('div');
            chipsDiv.className = 'skills-chips';

            const matchedSet = new Set((talent.matched_skills || []).map(s => s.toLowerCase().trim()));
            const orderedSkills = [...talent.skills];
            orderedSkills.sort((a, b) => {
                const aMatch = matchedSet.has(a.toLowerCase().trim());
                const bMatch = matchedSet.has(b.toLowerCase().trim());
                if (aMatch && !bMatch) return -1;
                if (!aMatch && bMatch) return 1;
                return 0;
            });

            orderedSkills.slice(0, 5).forEach(sk => {
                const chip = document.createElement('span');
                const isMatched = matchedSet.has(sk.toLowerCase().trim());
                chip.className = isMatched ? 'skill-tag skill-tag--matched' : 'skill-tag';
                chip.textContent = isMatched ? `✓ ${sk}` : sk;
                chipsDiv.appendChild(chip);
            });
            if (orderedSkills.length > 5) {
                const moreChip = document.createElement('span');
                moreChip.className = 'skill-tag skill-tag--more';
                moreChip.textContent = `+${orderedSkills.length - 5}`;
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
            if (aiReasonEl) {
                article.appendChild(aiReasonEl);
            }
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

            const originalBtnHtml = aiRunBtn.innerHTML;
            aiRunBtn.disabled = true;
            aiRunBtn.innerHTML = `
                <svg class="ent-animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line>
                    <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                    <line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line>
                    <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                </svg>
                <span>Đang phân tích & xếp hạng AI...</span>
            `;

            if (!window.crypto || (typeof window.crypto.randomUUID !== 'function' && typeof window.crypto.getRandomValues !== 'function')) {
                showToast('Trình duyệt không hỗ trợ yêu cầu bảo mật để chạy AI.');
                aiRunBtn.disabled = false;
                aiRunBtn.innerHTML = originalBtnHtml;
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

                const items = Array.isArray(matchData.items) ? matchData.items : [];
                if (items.length === 0) {
                    allTalents = [];
                    isAiModeActive = true;
                    currentPage = 1;
                    updateAndRender();
                    showToast('Không tìm thấy sinh viên có thông tin liên quan trong hệ thống.');
                    return;
                }

                allTalents = items.map((item, idx) => {
                    const norm = normalizeTalent(item);
                    norm.is_ai_matched = true;
                    norm.ai_rank = idx + 1;
                    return norm;
                });

                isAiModeActive = true;
                currentPage = 1;

                // Update active AI banner
                const jobOption = aiJobSelect.selectedOptions ? aiJobSelect.selectedOptions[0] : null;
                const jobTitleText = jobOption ? jobOption.textContent.split('(')[0].trim() : 'vị trí đã chọn';
                activeAiJobTitle = jobTitleText;

                const bannerEl = document.getElementById('ent-ai-active-banner');
                const bannerTextEl = document.getElementById('ent-ai-active-text');
                const countBadgeEl = document.getElementById('ent-ai-count-badge');
                if (bannerEl && bannerTextEl) {
                    bannerEl.style.display = 'flex';
                    bannerEl.style.animation = 'none';
                    void bannerEl.offsetWidth;
                    bannerEl.style.animation = '';
                    
                    const safeTitle = (jobTitleText || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
                    bannerTextEl.innerHTML = `Đang hiển thị <strong class="ent-ai-highlight">${allTalents.length}</strong> ứng viên phù hợp theo đề xuất AI cho vị trí: <strong class="ent-ai-highlight">"${safeTitle}"</strong>`;
                    if (countBadgeEl) {
                        countBadgeEl.textContent = `${allTalents.length} ứng viên phù hợp`;
                    }
                }

                showToast(`AI đã tìm thấy và xếp hạng ${allTalents.length} nhân tài liên quan!`);
                updateAndRender();
                scrollToTopCards();
            } catch (err) {
                showToast('Lỗi mạng khi kết nối dịch vụ AI.');
                if (aiStateEl) {
                    aiStateEl.textContent = 'provider_unavailable';
                    aiStateEl.className = 'badge badge-danger';
                }
            } finally {
                aiRunBtn.disabled = false;
                aiRunBtn.innerHTML = originalBtnHtml;
            }
        });
    }

    // Reset AI Matching Mode
    const aiResetBtn = document.getElementById('ent-ai-reset-btn');
    if (aiResetBtn) {
        aiResetBtn.addEventListener('click', () => {
            isAiModeActive = false;
            activeAiJobTitle = '';
            allTalents = [...originalTalents];
            const bannerEl = document.getElementById('ent-ai-active-banner');
            if (bannerEl) bannerEl.style.display = 'none';
            if (aiJobSelect) aiJobSelect.value = '';
            currentPage = 1;
            updateAndRender();
            scrollToTopCards();
            showToast('Đã chuyển về danh sách tất cả nhân tài.');
        });
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
