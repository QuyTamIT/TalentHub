'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'app/learner/ecosystem.php'), 'utf8');
const sidebarData = fs.readFileSync(path.join(root, 'app/learner/includes/student-data.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'assets/css/learner.css'), 'utf8');
require('../assets/js/learner.js');

test('learner ecosystem exposes a project-only second tab', () => {
    assert.match(page, />\s*Dự án\s*<span class="learner-count-badge">/);
    assert.match(page, /\$projects\s*=\s*learner_projects\(\)/);
    assert.match(page, /data-ecosystem-item-type="project"/);
    assert.match(page, /project\.php\?id=<\?= learner_escape\(\$project\['id'\]\); \?>/);
    assert.match(page, />Dự án<\/span>/);
    assert.doesNotMatch(page, /data-ecosystem-item-type="internship"/);
    assert.doesNotMatch(page, /learner-application-drawer|Hồ sơ đã ứng tuyển/);
    assert.doesNotMatch(page, /projectUrl|project_url|github\.com/i);
    assert.doesNotMatch(page, /data-ecosystem-filter="location"/);
});

test('project list has database, filter-empty, and load-error feedback', () => {
    assert.match(page, /Chưa có dự án đang triển khai/);
    assert.match(page, /Chưa tìm thấy dự án phù hợp/);
    assert.match(page, /Không thể tải danh sách dự án/);
    assert.match(page, /location\.reload\(\)/);
    assert.match(page, /data-ecosystem-result-count/);
});

test('project cards show project metadata without application semantics', () => {
    assert.match(page, /\$project\['school_name'\]/);
    assert.match(page, /\$project\['category_label'\]/);
    assert.match(page, /\$project\['members_count'\]/);
    assert.match(page, /\$project\['end_at_label'\]/);
    assert.doesNotMatch(page, />\s*Ứng tuyển ngay\s*</);
    assert.doesNotMatch(page, /\$project\['slots'\]|vị trí/);
    assert.match(styles, /\.learner-project-card/);
});

test('navigation and copy consistently name the experience Dự án', () => {
    assert.match(sidebarData, /'label' => 'Hệ sinh thái & Dự án'/);
    assert.match(page, /Hệ sinh thái &amp; Dự án/);
    assert.match(page, /Tất cả dự án đang triển khai/);
});

test('project search is accent insensitive and combines with category', () => {
    const { ecosystemItemMatches } = global.LearnerUI;
    assert.equal(ecosystemItemMatches(
        { search: 'EcoSmart AI FPT Polytechnic Kỹ thuật', field: 'Kỹ thuật', location: '' },
        { query: 'ecosmart', field: 'Kỹ thuật' },
    ), true);
    assert.equal(ecosystemItemMatches(
        { search: 'Dự án tái chế thông minh', field: 'Kỹ thuật', location: '' },
        { query: 'tai che', field: 'Kỹ thuật' },
    ), true);
    assert.equal(ecosystemItemMatches(
        { search: 'EcoSmart AI', field: 'Kỹ thuật', location: '' },
        { query: 'ecosmart', field: 'Kinh doanh' },
    ), false);
});
