const test=require('node:test');
const assert=require('node:assert/strict');
const {actions,safeUrl,reviewSkillPayload}=require('../assets/js/learner-portfolio.js');
test('student cannot approve and cannot edit submitted/verified reports directly',()=>{
 assert.deepEqual(actions('student','draft'),['save','submit']);
 assert.deepEqual(actions('student','submitted'),[]);
 assert.deepEqual(actions('student','verified'),['newRevision']);
 assert.deepEqual(actions('student','changes_requested'),['save','submit']);
});
test('teacher only reviews submitted or revokes verified',()=>{
 assert.deepEqual(actions('teacher','submitted'),['verified','changes_requested']);
 assert.deepEqual(actions('teacher','verified'),['revoked']);
 assert.deepEqual(actions('teacher','draft'),[]);
});
test('unsafe links never become clickable',()=>{
 assert.equal(safeUrl('javascript:alert(1)'),null);
 assert.equal(safeUrl('https://user:pass@example.test'),null);
 assert.equal(safeUrl('https://example.test/report'),'https://example.test/report');
});
test('skills are only submitted when the teacher verifies',()=>{
 const scoreOf=(id)=>(id==='sk1'?'80':'');
 assert.deepEqual(reviewSkillPayload('verified',['sk1','sk2'],scoreOf),{skillIds:['sk1','sk2'],skillScores:{sk1:'80',sk2:''}});
 assert.deepEqual(reviewSkillPayload('changes_requested',['sk1'],scoreOf),{skillIds:[],skillScores:{}});
 assert.deepEqual(reviewSkillPayload('revoked',['sk1'],scoreOf),{skillIds:[],skillScores:{}});
 assert.deepEqual(reviewSkillPayload('verified',undefined,undefined),{skillIds:[],skillScores:{}});
});
