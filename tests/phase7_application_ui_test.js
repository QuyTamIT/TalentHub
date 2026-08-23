'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const learner = fs.readFileSync(path.join(root, 'assets/js/learner.js'), 'utf8');
const applicants = fs.readFileSync(path.join(root, 'assets/js/applicant-management.js'), 'utf8');
const internships = fs.readFileSync(path.join(root, 'assets/js/internship-management.js'), 'utf8');
const opportunity = fs.readFileSync(path.join(root, 'app/learner/opportunity.php'), 'utf8');
const ecosystem = fs.readFileSync(path.join(root, 'app/learner/ecosystem.php'), 'utf8');
const applicantsPage = fs.readFileSync(path.join(root, 'app/enterprise/internships/applicants.php'), 'utf8');
const internshipsPage = fs.readFileSync(path.join(root, 'app/enterprise/internships/index.php'), 'utf8');
const applicationRepository = fs.readFileSync(path.join(root, 'app/learner/data/Database/DatabaseApplicationRepository.php'), 'utf8');
const opportunityRepository = fs.readFileSync(path.join(root, 'app/learner/data/Database/DatabaseEcosystemRepository.php'), 'utf8');

test('learner submit and withdraw wait for server-confirmed application state', () => {
    assert.match(learner, /send\('POST', '\/applications\.php', \{ action: 'grant-consent', confirmed: true \}\)/);
    assert.match(learner, /action: 'submit', postId: opportunityId/);
    assert.match(learner, /send\('PATCH', '\/applications\.php'/);
    assert.doesNotMatch(learner, /giao diện demo/);
    assert.match(opportunity, /data-opportunity-id=/);
    assert.match(ecosystem, /data-application-id=/);
});

test('enterprise reviews use shared API and immutable snapshot data', () => {
    assert.match(applicants, /expectedCurrentStatus: applicants\[appIndex\]\.status/);
    assert.match(applicants, /businesses\/me\/internship-applications/);
    assert.match(applicants, /const snapshot = app\.snapshot/);
    assert.match(applicants, /Chưa có dữ liệu phù hợp/);
    assert.doesNotMatch(applicants, /localStorage/);
    assert.doesNotMatch(applicants, /talents\/detail\.php|resolveCandidateDetailUrl|resume_file|\|\| 120/);
    assert.doesNotMatch(applicants, /Dự án Đồ án Chuyên ngành|Ứng viên thực tập tiềm năng|Mong muốn ứng tuyển vị trí/);
    assert.match(applicants, /c\.issueDate/);
    assert.match(applicants, /c\.issuingOrganization/);
    assert.match(applicants, /p\.title/);
    assert.match(applicants, /p\.summary/);
    assert.doesNotMatch(applicants, /Đã xác thực bởi TalentHub|PDF chính thức|btn-download-cv-file/);
    assert.doesNotMatch(applicantsPage, /talents-raw-data|resume_file|120h thực án|95% phù hợp|xác thực danh tính bởi TalentHub|btn-download-cv-file/);
    assert.match(applicantsPage, /đồng ý chia sẻ tại thời điểm ứng tuyển/);
});

test('learner reads canonical opportunity, immutable snapshot, and ordered history', () => {
    assert.match(opportunityRepository, /ip\.field/);
    assert.match(opportunityRepository, /ip\.workType/);
    assert.match(opportunityRepository, /ip\.skillsJson/);
    assert.match(opportunityRepository, /ip\.requirementsJson/);
    assert.match(applicationRepository, /application_profile_snapshots/);
    assert.match(applicationRepository, /application_status_history/);
    assert.match(applicationRepository, /ORDER BY h\.createdAt, h\.id/);
    assert.doesNotMatch(applicationRepository, /reviewerNote|cvUrl/);
    assert.match(ecosystem, /Hồ sơ tại thời điểm ứng tuyển/);
});

test('enterprise post mutations use server responses', () => {
    assert.match(internships, /businesses\/me\/internships/);
    assert.match(internships, /payload\?\.data\?\.post\?\.status !== targetStatus/);
    assert.doesNotMatch(internships, /mockRequirementsPayload/);
    assert.match(internshipsPage, /in_array\(\$post\['status'\], \['draft', 'active'\], true\)/);
    assert.doesNotMatch(internshipsPage, /Mở lại/);
});
