'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'app/learner/statistics.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'assets/js/learner-statistics.js'), 'utf8');
const stylesheet = fs.readFileSync(path.join(root, 'assets/css/learner.css'), 'utf8');
const statistics = require(path.join(root, 'assets/js/learner-statistics.js'));

class FakeElement {
    constructor(name = 'div') {
        this.name = name;
        this.attributes = new Map();
        this.children = [];
        this.textContent = '';
        this.hidden = false;
        this.className = '';
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    appendChild(child) {
        this.children.push(child);
        return child;
    }

    append(...children) {
        this.children.push(...children);
    }

    replaceChildren(...children) {
        this.children = children;
    }
}

assert.match(page, /statisticsService\(\)->forStudentPeriod\(\$studentId, \$selectedPeriod\)/, 'database page uses owner-scoped statistics');
assert.match(page, /value="week"/, 'week period is available');
assert.match(page, /value="month"/, 'month period is available');
assert.doesNotMatch(page, /Xu hướng tham chiếu|Xếp hạng lớp|Điểm năng lực/, 'database statistics removes unsupported comparison/ranking concepts');
assert.match(source, /new AbortController\(\)/, 'period requests are cancellable');
assert.match(source, /sequence !== requestSequence/, 'stale period responses cannot overwrite newer data');
assert.match(source, /activeController\.abort\(\)/, 'the previous period request is aborted');
assert.match(source, /TalentHubLearnerApi\.createLearnerApiClient/, 'statistics uses the shared learner API client');
assert.doesNotMatch(source, /global\.fetch\(|\bfetch\(/, 'statistics does not bypass the shared learner API client');
assert.match(source, /Không thể tải thống kê/, 'request failures have a visible retry instruction');
assert.match(source, /replaceChildren\(\)/, 'charts replace server-derived nodes safely');
assert.doesNotMatch(source, /innerHTML|outerHTML|insertAdjacentHTML/, 'statistics never writes untrusted HTML');

const selected = statistics.selectAxisLabelIndexes(31, 7);
assert.equal(selected.has(0), true, 'the first date label remains visible');
assert.equal(selected.has(30), true, 'the last date label remains visible');
assert.ok(selected.size <= 7, 'a month chart renders at most seven axis labels');
assert.deepEqual([...selected], [0, 5, 10, 15, 20, 25, 30], 'month labels are evenly spaced and deterministic');

assert.match(page, /data-lifetime-hours/, 'page has a dedicated lifetime-hours value');
assert.match(page, /Tổng tích lũy/, 'page labels lifetime facts explicitly');
assert.match(page, /data-period-kpi-title/, 'period KPI section has an explicit period heading');
assert.match(page, /data-experience-period-title/, 'experience chart has an updatable visible period heading');
assert.match(page, /Giờ trong kỳ đã chọn/, 'chart legend distinguishes selected-period hours from lifetime hours');
assert.match(page, /data-field-content/, 'field chart content remains available for empty-to-populated period changes');
assert.match(page, /data-field-empty/, 'field chart has a dedicated refreshable empty state');
assert.match(page, /<\/svg>\s*<ol class="learner-visually-hidden" data-experience-accessible-list/, 'daily datapoints are exposed outside the presentational descendants of the SVG image');
assert.doesNotMatch(page, /<g data-experience-bars role="list"/, 'SVG image descendants do not claim inaccessible list semantics');
assert.equal((page.match(/\$fieldColorMap\[\$category\]\s*\?\?\s*'neutral'/g) || []).length, 2, 'SSR donut segments and legend share the defined neutral fallback for unknown categories');
assert.match(stylesheet, /\.learner-statistics-donut__segment--neutral\s*\{\s*stroke:/, 'neutral fallback has an explicit visible donut stroke');
assert.doesNotMatch(page, /11\.5/, 'hero lifetime hours are never hardcoded in the page');
assert.doesNotMatch(page, /id="learner-statistics-data"/, 'legacy global statistics renderer cannot race the dedicated API renderer');
assert.doesNotMatch(page, /style="(?:display|text-align|margin|padding|min-width|font-size)/, 'statistics layout uses Learner CSS classes instead of inline layout styles');

const originalDocument = global.document;
const nodes = new Map([
    ['[data-experience-bars]', new FakeElement('g')],
    ['[data-experience-labels]', new FakeElement('g')],
    ['[data-experience-chart-title]', new FakeElement('title')],
    ['[data-experience-accessible-list]', new FakeElement('ol')],
    ['[data-statistics-status]', new FakeElement('p')],
    ['[data-period-kpi-title]', new FakeElement('h2')],
    ['[data-experience-period-title]', new FakeElement('h2')],
    ['[data-lifetime-hours]', new FakeElement('strong')],
    ['[data-lifetime-activities]', new FakeElement('strong')],
    ['[data-lifetime-assessments]', new FakeElement('strong')],
    ['[data-lifetime-evaluations]', new FakeElement('strong')],
    ['[data-field-content]', new FakeElement('div')],
    ['[data-field-empty]', new FakeElement('div')],
    ['[data-field-segments]', new FakeElement('g')],
    ['[data-field-total]', new FakeElement('text')],
    ['[data-field-legend]', new FakeElement('div')],
]);
global.document = {
    querySelector: selector => nodes.get(selector) || null,
    querySelectorAll: () => [],
    createElement: name => new FakeElement(name),
    createElementNS: (_namespace, name) => new FakeElement(name),
};

try {
    const hours = Array.from({ length: 31 }, (_, index) => index / 2);
    const labels = Array.from({ length: 31 }, (_, index) => `${index + 1}/8`);
    const dates = Array.from({ length: 31 }, (_, index) => `2026-08-${String(index + 1).padStart(2, '0')}`);

    statistics.renderStatistics({
        period: { label: 'Tháng này' },
        kpis: [{ id: 'hours', value: 7.5, suffix: 'giờ' }],
        experience: { hours, labels, dates },
        facts: {
            confirmed_experience_hours: 11.5,
            attended_activity_count: 2,
            submitted_assessment_type_count: 4,
            published_teacher_evaluation_count: 1,
        },
        fields: [
            { category: 'technology', hours: 7.5, percentage: 75 },
            { category: 'career', hours: 2.5, percentage: 25 },
        ],
    });

    const bars = nodes.get('[data-experience-bars]').children;
    const axisLabels = nodes.get('[data-experience-labels]').children;
    const accessibleItems = nodes.get('[data-experience-accessible-list]').children;
    assert.equal(bars.length, 31, 'all 31 daily bars remain in the chart');
    assert.equal(axisLabels.length, 7, 'only seven month labels are rendered');
    assert.equal(axisLabels[0].textContent, '1/8', 'first label is retained');
    assert.equal(axisLabels.at(-1).textContent, '31/8', 'last label is retained');
    bars.forEach((bar, index) => {
        const expected = `Ngày ${dates[index]}: ${hours[index]} giờ`;
        assert.equal(bar.children[0]?.name, 'title', `bar ${index + 1} has an SVG title`);
        assert.equal(bar.children[0]?.textContent, expected, `bar ${index + 1} title includes full date and hours`);
    });
    assert.equal(accessibleItems.length, 31, 'all 31 daily datapoints exist in an independent accessible list');
    accessibleItems.forEach((item, index) => {
        assert.equal(item.name, 'li');
        assert.equal(item.textContent, `Ngày ${dates[index]}: ${hours[index]} giờ`);
    });
    assert.equal(nodes.get('[data-lifetime-hours]').textContent, '11.5');
    assert.equal(nodes.get('[data-lifetime-activities]').textContent, '2');
    assert.equal(nodes.get('[data-lifetime-assessments]').textContent, '4');
    assert.equal(nodes.get('[data-lifetime-evaluations]').textContent, '1');
    assert.equal(nodes.get('[data-period-kpi-title]').textContent, 'Chỉ số trong Tháng này');
    assert.equal(nodes.get('[data-experience-period-title]').textContent, 'Giờ trải nghiệm (Tháng này)');
    assert.equal(nodes.get('[data-field-content]').hidden, false);
    assert.equal(nodes.get('[data-field-empty]').hidden, true);
    assert.equal(nodes.get('[data-field-total]').textContent, '10');
    assert.equal(nodes.get('[data-field-segments]').children.length, 2);
    assert.match(nodes.get('[data-field-segments]').children[0].getAttribute('class'), /--primary$/);
    assert.match(nodes.get('[data-field-segments]').children[1].getAttribute('class'), /--secondary$/);
    assert.equal(nodes.get('[data-field-legend]').children.length, 2);
    assert.equal(nodes.get('[data-field-legend]').children[0].children[1].children[0].textContent, 'Công nghệ');
    assert.equal(nodes.get('[data-field-legend]').children[1].children[1].children[0].textContent, 'Hướng nghiệp');

    statistics.renderStatistics({ period: { label: 'Tuần này' }, kpis: [], experience: { hours: [], labels: [], dates: [] }, facts: {}, fields: [] });
    assert.equal(nodes.get('[data-field-content]').hidden, true, 'empty period hides the donut content');
    assert.equal(nodes.get('[data-field-empty]').hidden, false, 'empty period exposes its empty state');
    assert.equal(nodes.get('[data-field-total]').textContent, '0');
    assert.equal(nodes.get('[data-field-segments]').children.length, 0);
    assert.equal(nodes.get('[data-field-legend]').children.length, 0);

    statistics.renderStatistics({
        period: { label: 'Tháng này' },
        kpis: [], experience: { hours: [], labels: [], dates: [] }, facts: {},
        fields: [{ category: 'personal', hours: 4, percentage: 100 }],
    });
    assert.equal(nodes.get('[data-field-content]').hidden, false, 'a later populated period restores donut content');
    assert.equal(nodes.get('[data-field-empty]').hidden, true);
    assert.equal(nodes.get('[data-field-total]').textContent, '4');
    assert.equal(nodes.get('[data-field-legend]').children[0].children[1].children[0].textContent, 'Phát triển cá nhân');

    statistics.renderStatistics({
        period: { label: 'Tháng này' },
        kpis: [], experience: { hours: [], labels: [], dates: [] }, facts: {},
        fields: [{ category: 'arts', hours: 3, percentage: 100 }],
    });
    assert.match(nodes.get('[data-field-segments]').children[0].getAttribute('class'), /--neutral$/, 'unknown field categories use a donut class with a defined stroke');
    assert.equal(nodes.get('[data-field-legend]').children[0].children[1].children[0].textContent, 'arts');
} finally {
    global.document = originalDocument;
}

console.log('learner_statistics_ui_test: OK');
