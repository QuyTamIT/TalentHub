const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');

const profile = fs.readFileSync(path.join(__dirname, '../app/learner/profile.php'), 'utf8');
const modal = profile.slice(profile.indexOf('<!-- Certificate Modal -->'), profile.indexOf('<!-- Project Detail Modal -->'));
const section = profile.match(/<section class="learner-card learner-certificates"[\s\S]*?<\/section>/)?.[0] || '';
const fields = [...modal.matchAll(/<input\b[^>]*\bname="([^"]+)"/g)].map(match => match[1]);

assert.deepEqual(fields, ['title', 'issuingOrganization', 'issueDate'], 'Demo form contains exactly three inputs');
assert.ok(!/Tự khai báo|Chờ xác minh|Đang chờ|Đã xác minh/.test(section), 'Certificate cards have no verification labels');
assert.ok(section.includes('data-certificate-list'), 'List exists for immediate insert');
assert.ok(section.includes('data-certificate-empty'), 'Empty state can be hidden after save');

const learner = fs.readFileSync(path.join(__dirname, '../assets/js/learner.js'), 'utf8');
const handler = learner.slice(learner.indexOf("const certForm = document.getElementById('learner-certificate-form');"), learner.indexOf("document.querySelector('[data-copy-profile]')", learner.indexOf("const certForm = document.getElementById('learner-certificate-form');")));
assert.ok(handler.includes("res.certificate"), 'Handler renders the canonical API certificate response');
assert.ok(handler.includes('data-certificate-list'), 'Handler updates the certificate list');
assert.ok(handler.includes('certForm.reset()'), 'Form resets after successful save');
assert.ok(!handler.includes("showToast('Chứng chỉ demo đã được thêm.')"), 'No false demo success fallback');

console.log('learner_certificate_demo_ui_test: OK');
