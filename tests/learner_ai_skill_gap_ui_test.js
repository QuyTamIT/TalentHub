'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const modulePath = path.join(root, 'assets', 'js', 'learner-skill-gap.js');
const page = fs.readFileSync(path.join(root, 'app', 'learner', 'ai-recommendations.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'css', 'learner.css'), 'utf8');
const { normalizeSkillGapPayload, isSafeLearnerActivityUrl, createSkillGapController } = require(modulePath);

const jobId = '11111111-1111-4111-8111-111111111111';
const payload = {
    state: 'ready_model',
    enterprise_groups: [{ enterprise_id: 'ent-1', enterprise_name: 'Công ty A', positions: [{ catalog_id: jobId, title: 'AI Engineer Intern', url: `/app/learner/opportunity.php?type=internship&id=${jobId}`, match_score: 78 }] }],
    skill_gap: {
        state: 'ok', role: { code: 'ai_engineer', title: 'AI Engineer' }, match_score: 78, skill_readiness_score: 64,
        skills_met: [{ code: 'python', label: 'Python', current_score: 85, target_score: 70, gap_score: 0, evidence_refs: ['skill:python'] }],
        skills_missing: [{ code: 'mlops', label: 'MLOps', current_score: 20, target_score: 60, gap_score: 40, impact: 'Kỹ năng bắt buộc còn thiếu lớn.', evidence_refs: [] }],
        recommended_activities: [{ catalog_id: 'activity-1', title: 'Workshop MLOps', item_type: 'workshop', url: '/app/learner/activity-detail.php?id=activity-1', reason: 'Hoạt động này giúp bù kỹ năng MLOps.', skill_codes: ['mlops'] }],
    },
};

test('skill gap view model exposes target, scores, met/missing skills and activities', () => {
    const model = normalizeSkillGapPayload(payload);
    assert.equal(model.target_role, 'AI Engineer');
    assert.equal(model.match_score, 78);
    assert.equal(model.skill_readiness_score, 64);
    assert.deepEqual(model.skills_met[0], { code: 'python', label: 'Python', current_score: 85, target_score: 70, gap_score: 0, target_basis: '', target_is_approximate: false, evidence_count: 1 });
    assert.equal(model.skills_missing[0].impact, 'Kỹ năng bắt buộc còn thiếu lớn.');
    assert.equal(model.activities[0].title, 'Workshop MLOps');
});

test('skill gap activity CTA accepts only canonical learner paths', () => {
    assert.equal(isSafeLearnerActivityUrl('/app/learner/activity-detail.php?id=activity-1'), true);
    assert.equal(isSafeLearnerActivityUrl('/app/learner/project.php?id=project-1'), true);
    assert.equal(isSafeLearnerActivityUrl('javascript:alert(1)'), false);
    assert.equal(isSafeLearnerActivityUrl('//evil.example/path'), false);
});

test('unknown learner observations and posting targets remain unknown', () => {
    const model = normalizeSkillGapPayload({
        ...payload,
        skill_gap: {
            ...payload.skill_gap,
            skills_met: [],
            skills_missing: [{
                code: 'sql', label: 'SQL', current_score: null, target_score: null,
                gap_score: null, target_basis: 'candidate_unspecified', target_is_approximate: true,
                impact: 'Cần đối chiếu thêm yêu cầu vị trí.', evidence_refs: [],
            }],
        },
    });
    assert.equal(model.skills_missing[0].current_score, null);
    assert.equal(model.skills_missing[0].target_score, null);
    assert.equal(model.skills_missing[0].gap_score, null);
    assert.equal(model.skills_missing[0].target_is_approximate, true);
});

test('skill gap controller only reads latest job matching result', async () => {
    const calls=[];const states=[];
    const controller=createSkillGapController({api:{async get(endpoint){calls.push(endpoint);return payload;}},view:{render(state,data){states.push([state,data]);}}});
    await controller.load();
    assert.deepEqual(calls,['/ai-job-matches.php']);
    assert.equal(states.at(-1)[0],'ready-model');
    assert.equal(states.at(-1)[1].target_role,'AI Engineer');
});

test('no matching jobs with a persisted near match renders Skill Gap instead of redirecting back', async () => {
    const noMatchPayload = { ...payload, state: 'no_matching_jobs', enterprise_groups: [], near_match: { title: 'AI Engineer Intern', match_score: 34 } };
    const states = [];
    const controller = createSkillGapController({ api: { async get() { return noMatchPayload; } }, view: { render(state, data) { states.push([state, data]); } } });
    await controller.load();
    assert.equal(states.at(-1)[0], 'ready-model');
    assert.equal(states.at(-1)[1].is_near_match, true);
    assert.equal(states.at(-1)[1].target_role, 'AI Engineer');
});

test('AI page replaces visible highlights with detailed Skill Gap without changing roadmap regions', () => {
    assert.match(page, /Phân tích khoảng cách kỹ năng/);
    for (const marker of ['data-skill-gap','data-skill-gap-target','data-skill-gap-scores','data-skill-gap-met','data-skill-gap-missing','data-skill-gap-activities']) assert.match(page,new RegExp(marker));
    assert.doesNotMatch(page, /<h2>Nhận định nổi bật<\/h2>/);
    assert.match(page, /data-roadmap-phases/);
    assert.match(page, /learner-skill-gap\.js/);
    assert.match(css, /\.learner-skill-gap/);
});

test('skill gap renderer uses text nodes only', () => {
    const source=fs.readFileSync(modulePath,'utf8');
    assert.match(source,/textContent/);
    assert.doesNotMatch(source,/innerHTML/);
});
