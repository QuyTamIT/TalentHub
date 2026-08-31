/** TalentHub learner statistics: owner-scoped period refresh with stale-response protection. */
(function (global) {
    'use strict';

    const ALLOWED_PERIODS = ['week', 'month', 'semester', 'year', 'all'];

    const FIELD_TONE_BY_CATEGORY = {
        technology: 'primary',
        career: 'secondary',
        personal: 'warning',
        academic: 'accent',
        sports: 'teal',
        arts: 'purple',
        general: 'neutral',
    };

    const FIELD_LABEL_BY_CATEGORY = {
        technology: 'Công nghệ & Kỹ thuật',
        career: 'Hướng nghiệp & Thực tập',
        personal: 'Kỹ năng mềm',
        academic: 'Học thuật & Nghiên cứu',
        sports: 'Thể thao & Sức khỏe',
        arts: 'Nghệ thuật',
        general: 'Khác',
    };

    const SKILL_CATEGORY_LABELS = {
        technical: 'Kỹ thuật',
        soft: 'Kỹ năng mềm',
        creative: 'Sáng tạo',
        academic: 'Học thuật',
        business: 'Kinh doanh',
        sports: 'Thể thao',
    };

    let activeController = null;
    let requestSequence = 0;

    function selectAxisLabelIndexes(count, maximumLabels = 7) {
        const normalizedCount = Math.max(0, Math.floor(Number(count) || 0));
        const normalizedMaximum = Math.max(0, Math.floor(Number(maximumLabels) || 0));
        const selected = new Set();
        if (normalizedCount === 0 || normalizedMaximum === 0) return selected;
        if (normalizedCount <= normalizedMaximum) {
            for (let index = 0; index < normalizedCount; index += 1) selected.add(index);
            return selected;
        }
        if (normalizedMaximum === 1) {
            selected.add(0);
            return selected;
        }
        for (let slot = 0; slot < normalizedMaximum; slot += 1) {
            selected.add(Math.round(slot * (normalizedCount - 1) / (normalizedMaximum - 1)));
        }
        return selected;
    }

    function createStatisticsClient() {
        if (!global.TalentHubLearnerApi) return null;
        const bootNode = document.getElementById('learner-session-boot');
        let csrfToken = '';
        try {
            csrfToken = JSON.parse(bootNode?.textContent || '{}').csrfToken || '';
        } catch {
            csrfToken = '';
        }
        return global.TalentHubLearnerApi.createLearnerApiClient({
            baseUrl: '/app/learner/api/v1',
            csrfToken,
        });
    }

    function setStatus(message) {
        const status = document.querySelector('[data-statistics-status]');
        if (status) status.textContent = message;
    }

    function setText(selector, value) {
        const node = document.querySelector(selector);
        if (node) node.textContent = String(value ?? '');
    }

    function setHidden(selector, hidden) {
        const node = document.querySelector(selector);
        if (node) node.hidden = Boolean(hidden);
    }

    function replaceKpis(kpis) {
        const byId = new Map((Array.isArray(kpis) ? kpis : []).map(item => [String(item.id), item]));
        document.querySelectorAll('[data-statistics-kpi]').forEach(card => {
            const item = byId.get(card.getAttribute('data-kpi-id') || '');
            if (!item) return;
            const value = card.querySelector('[data-kpi-value]');
            const suffix = card.querySelector('[data-kpi-suffix]');
            if (value) value.textContent = String(item.value ?? 0);
            if (suffix) suffix.textContent = String(item.suffix ?? '');
        });
    }

    function replaceExperience(experience, periodLabel = 'khoảng đã chọn') {
        const hours = Array.isArray(experience?.hours) ? experience.hours.map(Number) : [];
        const labels = Array.isArray(experience?.labels) ? experience.labels.map(String) : [];
        const dates = Array.isArray(experience?.dates) ? experience.dates.map(String) : [];
        const bars = document.querySelector('[data-experience-bars]');
        const labelGroup = document.querySelector('[data-experience-labels]');
        const accessibleList = document.querySelector('[data-experience-accessible-list]');
        if (!bars || !labelGroup) return;

        bars.replaceChildren();
        labelGroup.replaceChildren();
        accessibleList?.replaceChildren();
        const maximum = Math.max(10, ...hours);
        const width = 550;
        const height = 170;
        const step = width / Math.max(1, hours.length);
        const barWidth = Math.min(36, step * 0.55);
        const svgNamespace = 'http://www.w3.org/2000/svg';
        const visibleLabelIndexes = selectAxisLabelIndexes(hours.length);

        hours.forEach((rawHours, index) => {
            const safeHours = Number.isFinite(rawHours) ? Math.max(0, rawHours) : 0;
            const date = dates[index] || labels[index] || `Mốc ${index + 1}`;
            const accessibleTitle = `Ngày ${date}: ${safeHours} giờ`;
            const barHeight = (safeHours / maximum) * height;
            const x = 46 + (index * step) + ((step - barWidth) / 2);
            const y = 24 + height - barHeight;
            const rect = document.createElementNS(svgNamespace, 'rect');
            rect.setAttribute('x', String(x));
            rect.setAttribute('y', String(y));
            rect.setAttribute('width', String(barWidth));
            rect.setAttribute('height', String(barHeight));
            rect.setAttribute('rx', '5');
            rect.setAttribute('class', 'learner-experience-chart__bar');
            const title = document.createElementNS(svgNamespace, 'title');
            title.textContent = accessibleTitle;
            rect.appendChild(title);
            bars.appendChild(rect);

            if (accessibleList) {
                const item = document.createElement('li');
                item.textContent = accessibleTitle;
                accessibleList.appendChild(item);
            }

            if (!visibleLabelIndexes.has(index)) return;
            const text = document.createElementNS(svgNamespace, 'text');
            text.setAttribute('x', String(46 + (index * step) + (step / 2)));
            text.setAttribute('y', '222');
            text.setAttribute('text-anchor', 'middle');
            text.textContent = labels[index] || '';
            labelGroup.appendChild(text);
        });

        const chartTitle = document.querySelector('[data-experience-chart-title]');
        if (chartTitle) chartTitle.textContent = `Biểu đồ giờ trải nghiệm cá nhân ${periodLabel}`;
    }

    function replaceLifetimeFacts(facts) {
        const values = [
            ['[data-lifetime-hours]', facts?.confirmed_experience_hours],
            ['[data-lifetime-activities]', facts?.attended_activity_count],
            ['[data-lifetime-assessments]', facts?.submitted_assessment_type_count],
            ['[data-lifetime-evaluations]', facts?.published_teacher_evaluation_count],
        ];
        values.forEach(([selector, value]) => {
            setText(selector, value ?? 0);
        });
    }

    function replaceFields(fields) {
        const normalizedFields = Array.isArray(fields) ? fields : [];
        const content = document.querySelector('[data-field-content]');
        const empty = document.querySelector('[data-field-empty]');
        const segments = document.querySelector('[data-field-segments]');
        const totalNode = document.querySelector('[data-field-total]');
        const legend = document.querySelector('[data-field-legend]');
        const hasFields = normalizedFields.length > 0;
        if (content) content.hidden = !hasFields;
        if (empty) empty.hidden = hasFields;
        if (!segments || !legend) return;

        const svgNamespace = 'http://www.w3.org/2000/svg';
        const radius = 70;
        const circumference = 2 * Math.PI * radius;
        let offset = 0;
        let totalHours = 0;
        const segmentNodes = [];
        const legendNodes = [];

        normalizedFields.forEach(field => {
            const category = String(field?.category || 'general');
            const hours = Number(field?.hours);
            const safeHours = Number.isFinite(hours) ? Math.max(0, hours) : 0;
            const percentage = Number(field?.percentage);
            const safePercentage = Number.isFinite(percentage) ? Math.max(0, Math.min(100, percentage)) : 0;
            const length = circumference * safePercentage / 100;
            const tone = String(field?.tone || FIELD_TONE_BY_CATEGORY[category] || 'neutral');
            const label = String(field?.label || FIELD_LABEL_BY_CATEGORY[category] || category);
            totalHours += safeHours;

            const segment = document.createElementNS(svgNamespace, 'circle');
            segment.setAttribute('class', `learner-statistics-donut__segment learner-statistics-donut__segment--${tone}`);
            segment.setAttribute('cx', '100');
            segment.setAttribute('cy', '100');
            segment.setAttribute('r', String(radius));
            segment.setAttribute('stroke-dasharray', `${length} ${circumference - length}`);
            segment.setAttribute('stroke-dashoffset', String(-offset));
            segmentNodes.push(segment);
            offset += length;

            const item = document.createElement('div');
            item.className = 'learner-field-legend__item';
            const dot = document.createElement('span');
            dot.className = `learner-field-legend__dot learner-field-legend__dot--${tone}`;
            dot.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = label;
            const detail = document.createElement('small');
            detail.textContent = `${safeHours} giờ (${safePercentage}%)`;
            copy.append(title, detail);
            item.append(dot, copy);
            legendNodes.push(item);
        });

        segments.replaceChildren(...segmentNodes);
        legend.replaceChildren(...legendNodes);
        if (totalNode) totalNode.textContent = String(totalHours);
    }

    function buildSkillItem(skill) {
        const score = Math.max(0, Math.min(100, Math.round(Number(skill?.score) || 0)));
        const tone = String(skill?.tone || 'secondary');
        const category = String(skill?.category || 'soft');

        const item = document.createElement('li');
        item.className = 'learner-skill-bar';
        item.setAttribute('data-skill-item', '');

        const heading = document.createElement('div');
        heading.className = 'learner-skill-bar__heading';
        const name = document.createElement('span');
        name.className = 'learner-skill-bar__name';
        name.setAttribute('data-skill-name', '');
        name.textContent = String(skill?.name || '');
        const scoreWrap = document.createElement('span');
        scoreWrap.className = 'learner-skill-bar__score';
        const scoreValue = document.createElement('b');
        scoreValue.setAttribute('data-skill-score', '');
        scoreValue.textContent = String(score);
        const scoreMax = document.createElement('small');
        scoreMax.textContent = '/100';
        scoreWrap.append(scoreValue, scoreMax);
        heading.append(name, scoreWrap);

        const track = document.createElement('div');
        track.className = 'learner-skill-bar__track';
        track.setAttribute('role', 'progressbar');
        track.setAttribute('aria-label', String(skill?.name || 'Kỹ năng'));
        track.setAttribute('aria-valuemin', '0');
        track.setAttribute('aria-valuemax', '100');
        track.setAttribute('aria-valuenow', String(score));
        const fill = document.createElement('span');
        fill.className = `learner-skill-bar__fill learner-skill-bar__fill--${tone}`;
        fill.setAttribute('data-skill-fill', '');
        fill.setAttribute('style', `--learner-progress: ${score}%;`);
        track.appendChild(fill);

        const meta = document.createElement('div');
        meta.className = 'learner-skill-bar__meta';
        const level = document.createElement('span');
        level.className = 'learner-skill-bar__level';
        level.setAttribute('data-skill-level', '');
        level.textContent = String(skill?.level || '');
        const categoryLabel = document.createElement('span');
        categoryLabel.className = 'learner-skill-bar__category';
        categoryLabel.setAttribute('data-skill-category', '');
        categoryLabel.textContent = SKILL_CATEGORY_LABELS[category] || category;
        meta.append(level, categoryLabel);

        item.append(heading, track, meta);
        return item;
    }

    function replaceSkills(skills) {
        const normalizedSkills = Array.isArray(skills) ? skills : [];
        const list = document.querySelector('[data-skills-list]');
        const empty = document.querySelector('[data-skills-empty]');
        const hasSkills = normalizedSkills.length > 0;
        if (list) list.hidden = !hasSkills;
        if (empty) empty.hidden = hasSkills;
        if (!list) return;

        list.replaceChildren(...normalizedSkills.map(buildSkillItem));
    }

    function buildCriterionItem(criterion) {
        const percentage = Math.max(0, Math.min(100, Math.round(Number(criterion?.percentage) || 0)));
        const item = document.createElement('div');
        item.className = 'learner-evaluation-criterion';
        item.setAttribute('data-criterion-item', '');

        const heading = document.createElement('div');
        heading.className = 'learner-evaluation-criterion__heading';
        const name = document.createElement('span');
        name.setAttribute('data-criterion-name', '');
        name.textContent = String(criterion?.name || '');
        const points = document.createElement('span');
        points.className = 'learner-evaluation-criterion__points';
        const score = document.createElement('b');
        score.setAttribute('data-criterion-score', '');
        score.textContent = String(criterion?.score ?? 0);
        const slash = document.createElement('span');
        slash.textContent = '/';
        const max = document.createElement('span');
        max.setAttribute('data-criterion-max', '');
        max.textContent = String(criterion?.max ?? 0);
        points.append(score, slash, max);
        heading.append(name, points);

        const progress = document.createElement('div');
        progress.className = 'learner-progress';
        progress.setAttribute('role', 'progressbar');
        progress.setAttribute('aria-label', String(criterion?.name || 'Tiêu chí'));
        progress.setAttribute('aria-valuemin', '0');
        progress.setAttribute('aria-valuemax', '100');
        progress.setAttribute('aria-valuenow', String(percentage));
        const fill = document.createElement('span');
        fill.setAttribute('style', `--learner-progress: ${percentage}%;`);
        progress.appendChild(fill);

        item.append(heading, progress);
        return item;
    }

    function replaceEvaluations(evaluations) {
        const data = evaluations && typeof evaluations === 'object' ? evaluations : {};
        const hasEvaluation = data.total_score !== null && data.total_score !== undefined && Number(data.total_score) > 0;

        setText('[data-evaluation-term]', data.term || '');
        setText('[data-evaluation-total]', hasEvaluation ? data.total_score : 0);
        setText('[data-evaluation-ranking]', data.ranking || '');
        setText('[data-evaluation-classification]', data.classification || 'Chưa có đánh giá');

        const classification = document.querySelector('[data-evaluation-classification]');
        if (classification) {
            classification.className = `learner-evaluation-card__classification learner-evaluation-card__classification--${hasEvaluation ? 'active' : 'empty'}`;
        }

        setHidden('[data-evaluation-score-row]', !hasEvaluation);
        setHidden('[data-evaluation-empty]', hasEvaluation);

        const criteria = document.querySelector('[data-evaluation-criteria]');
        if (criteria) {
            const normalized = Array.isArray(data.criteria) ? data.criteria : [];
            criteria.replaceChildren(...normalized.map(buildCriterionItem));
        }

        const comment = document.querySelector('[data-evaluation-comment]');
        const commentText = String(data.teacher_comment || '');
        if (comment) {
            const paragraph = comment.querySelector('p');
            if (paragraph) paragraph.textContent = commentText;
            comment.hidden = commentText === '';
        }
    }

    function buildProjectItem(project) {
        const item = document.createElement('li');
        item.className = 'learner-projects-card__item';
        item.setAttribute('data-project-item', '');

        const icon = document.createElement('span');
        icon.className = 'learner-projects-card__icon';
        icon.setAttribute('aria-hidden', 'true');

        const name = document.createElement('span');
        name.className = 'learner-projects-card__name';
        name.setAttribute('data-project-name', '');
        name.textContent = String(project?.name || '');

        const role = document.createElement('span');
        role.className = 'learner-badge learner-badge--muted';
        role.setAttribute('data-project-role', '');
        role.textContent = String(project?.role || 'Thành viên');

        const status = document.createElement('span');
        status.className = `learner-badge learner-badge--${String(project?.tone || 'warning')}`;
        status.setAttribute('data-project-status', '');
        status.textContent = String(project?.status || 'Đang triển khai');

        item.append(icon, name, role, status);
        return item;
    }

    function replaceProjects(projects) {
        const data = projects && typeof projects === 'object' ? projects : {};
        setText('[data-projects-total]', data.total ?? 0);
        setText('[data-projects-completed]', data.completed ?? 0);
        setText('[data-projects-in-progress]', data.in_progress ?? 0);
        setText('[data-projects-leader]', data.leader_roles ?? 0);

        const featured = Array.isArray(data.featured) ? data.featured : [];
        const list = document.querySelector('[data-projects-list]');
        const empty = document.querySelector('[data-projects-empty]');
        if (list) {
            list.replaceChildren(...featured.map(buildProjectItem));
            list.hidden = featured.length === 0;
        }
        if (empty) empty.hidden = featured.length > 0;
    }

    function fillInsightList(selector, items) {
        const list = document.querySelector(selector);
        if (!list) return;
        list.replaceChildren();
        (Array.isArray(items) ? items : []).forEach(text => {
            const item = document.createElement('li');
            item.textContent = String(text);
            list.appendChild(item);
        });
    }

    function replaceAiInsights(insights) {
        const data = insights && typeof insights === 'object' ? insights : {};
        setText('[data-ai-summary]', data.executive_summary || '');
        fillInsightList('[data-ai-strengths]', data.strengths);
        fillInsightList('[data-ai-recommendations]', data.recommendations);
    }

    function replaceLevel(level) {
        const data = level && typeof level === 'object' ? level : {};
        const percent = Math.max(0, Math.min(100, Math.round(Number(data.progressPercent) || 0)));
        setText('[data-level-percent]', `${percent}%`);
        setText('[data-level-current]', data.currentHours ?? 0);
        setText('[data-level-target]', data.targetHours ?? 0);

        const remaining = (data.nextLevel ?? null) !== null
            ? `Còn ${data.remainingHours ?? 0} giờ trải nghiệm để lên cấp ${data.nextLevel}.`
            : 'Bạn đã đạt cấp độ cao nhất của hành trình rèn luyện.';
        setText('[data-level-remaining]', remaining);

        const progress = document.querySelector('.learner-level-summary-card [role="progressbar"]');
        if (progress) {
            progress.setAttribute('aria-valuenow', String(percent));
            const fill = progress.querySelector('span');
            if (fill) fill.setAttribute('style', `--learner-progress: ${percent}%;`);
        }
    }

    function updateEmptyState(data) {
        const facts = data?.facts && typeof data.facts === 'object' ? data.facts : {};
        const skills = Array.isArray(data?.skills) ? data.skills : [];
        const isEmpty = Number(facts.confirmed_experience_hours ?? 0) === 0
            && Number(facts.submitted_assessment_type_count ?? 0) === 0
            && Number(facts.published_teacher_evaluation_count ?? 0) === 0
            && skills.length === 0;
        setHidden('[data-statistics-empty]', !isEmpty);
    }

    function renderStatistics(data) {
        const label = String(data?.period?.label || 'khoảng đã chọn');
        replaceKpis(data?.kpis);
        replaceExperience(data?.experience, label);
        replaceLifetimeFacts(data?.facts);
        replaceFields(data?.fields);
        replaceSkills(data?.skills);
        replaceEvaluations(data?.evaluations);
        replaceProjects(data?.projects);
        replaceAiInsights(data?.ai_insights);
        replaceLevel(data?.level);
        updateEmptyState(data);

        const periodTitle = document.querySelector('[data-period-kpi-title]');
        if (periodTitle) periodTitle.textContent = `Chỉ số trong ${label}`;
        const experienceTitle = document.querySelector('[data-experience-period-title]');
        if (experienceTitle) experienceTitle.textContent = `Giờ trải nghiệm (${label})`;
        setStatus(`Đang hiển thị thống kê ${label}.`);
    }

    async function loadPeriod(period, client = null) {
        if (!ALLOWED_PERIODS.includes(period)) return;
        if (activeController) activeController.abort();
        const controller = new AbortController();
        activeController = controller;
        const sequence = ++requestSequence;
        setStatus('Đang tải thống kê đã xác nhận…');

        try {
            const api = client || createStatisticsClient();
            if (!api) throw new Error('STATISTICS_API_UNAVAILABLE');
            const data = await api.get(`/statistics.php?period=${encodeURIComponent(period)}`, {
                signal: controller.signal,
            });
            if (!data || typeof data !== 'object') throw new Error('INVALID_STATISTICS_RESPONSE');
            if (sequence !== requestSequence) return;
            renderStatistics(data);
            const url = new URL(global.location.href);
            url.searchParams.set('period', period);
            global.history.replaceState({}, '', url.toString());
        } catch (error) {
            if (error?.code === 'REQUEST_ABORTED' || sequence !== requestSequence) return;
            setStatus('Không thể tải thống kê. Vui lòng chọn lại khoảng thời gian để thử lại.');
        } finally {
            if (activeController === controller) activeController = null;
        }
    }

    function initStatistics() {
        const periodSelect = document.getElementById('learner-statistics-period');
        if (!periodSelect) return;

        periodSelect.addEventListener('change', () => {
            loadPeriod(periodSelect.value);
        });
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStatistics);
        } else {
            initStatistics();
        }
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            createStatisticsClient,
            loadPeriod,
            renderStatistics,
            selectAxisLabelIndexes,
            ALLOWED_PERIODS,
        };
    }
})(typeof window !== 'undefined' ? window : globalThis);
