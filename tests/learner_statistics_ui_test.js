'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'app/learner/statistics.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'assets/js/learner-statistics.js'), 'utf8');

assert.match(page, /statisticsService\(\)->forStudentPeriod\(\$studentId, \$selectedPeriod\)/, 'database page uses owner-scoped statistics');
assert.match(page, /value="week"/, 'week period is available');
assert.match(page, /value="month"/, 'month period is available');
assert.doesNotMatch(page, /Xu hướng tham chiếu|Xếp hạng lớp|Điểm năng lực/, 'database statistics removes unsupported comparison/ranking concepts');
assert.match(source, /new AbortController\(\)/, 'period requests are cancellable');
assert.match(source, /sequence !== requestSequence/, 'stale period responses cannot overwrite newer data');
assert.match(source, /activeController\.abort\(\)/, 'the previous period request is aborted');
assert.match(source, /Không thể tải thống kê/, 'request failures have a visible retry instruction');
assert.match(source, /replaceChildren\(\)/, 'charts replace server-derived nodes safely');
assert.doesNotMatch(source, /innerHTML|outerHTML|insertAdjacentHTML/, 'statistics never writes untrusted HTML');

console.log('learner_statistics_ui_test: OK');
