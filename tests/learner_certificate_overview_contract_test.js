const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const html = fs.readFileSync(path.join(__dirname, '../app/learner/index.php'), 'utf8');

assert.match(html, /data-dashboard-external-certificates/);
assert.match(html, /foreach \(\$certificates as \$certificate\)/);
assert.match(html, /data-dashboard-certificate-title/);
assert.match(html, /data-dashboard-certificate-issuer/);
assert.match(html, /data-dashboard-certificate-date/);
assert.match(html, /Chứng chỉ bên ngoài/);
console.log('learner_certificate_overview_contract_test: OK');
