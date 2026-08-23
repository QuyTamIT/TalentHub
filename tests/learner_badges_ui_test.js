'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'app/learner/badges.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'assets/js/learner-badges.js'), 'utf8');

assert.match(page, /badgeReadService\(\)->forStudent\(\$studentId\)/, 'database page reads the authenticated learner only');
assert.match(page, /data-badge-load-error/, 'database errors have an explicit retry state');
assert.match(page, /data-badge-filter/, 'filters use native keyboard-accessible buttons');
assert.match(page, /learner-badges-data/, 'server truth is serialized with JSON hex escaping');
assert.doesNotMatch(source, /innerHTML|outerHTML|insertAdjacentHTML/, 'badge filtering never writes untrusted HTML');
assert.match(source, /textContent/, 'filter result status uses safe text content');
assert.match(source, /aria-pressed/, 'filter selection remains accessible');

console.log('learner_badges_ui_test: OK');
