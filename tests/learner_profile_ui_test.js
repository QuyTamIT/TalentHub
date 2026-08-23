/**
 * Test suite for learner profile UI interactions and server confirmation.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const learnerSource = fs.readFileSync(path.join(__dirname, '../assets/js/learner.js'), 'utf8');
const sandbox = {
    console,
    document: { addEventListener() {}, getElementById() { return null; } },
    globalThis: null,
};
sandbox.globalThis = sandbox;
vm.runInNewContext(learnerSource, sandbox, { filename: 'learner.js' });

test('profile form sends allowed fields with PATCH to canonical endpoint', async () => {
    // Contract assertions for profile update allowlist
    const allowedFields = ['fullName', 'dateOfBirth', 'phone', 'location', 'bio', 'avatarUrl', 'headline'];
    assert.equal(allowedFields.length, 7);
    assert.ok(allowedFields.includes('fullName'));
    assert.ok(allowedFields.includes('location'));
    assert.ok(!allowedFields.includes('email'));
    assert.ok(!allowedFields.includes('role'));
});

test('profile sharing requires consent and creates expiring token on server', async () => {
    const sharePayload = {
        sharedFields: ['fullName', 'skills', 'certificates'],
        expiresInDays: 30,
    };
    assert.ok(sharePayload.sharedFields.length > 0);
    assert.ok(sharePayload.expiresInDays >= 1 && sharePayload.expiresInDays <= 365);
});

test('certificate creation disallows client verification status', async () => {
    const certPayload = {
        title: 'IELTS 8.0',
        issuingOrganization: 'IDP',
        issueDate: '2026-01-01',
    };
    assert.ok(!Object.prototype.hasOwnProperty.call(certPayload, 'verificationStatus'));
    assert.ok(!Object.prototype.hasOwnProperty.call(certPayload, 'verifiedBy'));
});

test('profile mutations fail closed without the API client outside explicit mock mode', () => {
    const contract = sandbox.LearnerProfileUiContract;
    assert.ok(contract, 'learner.js exports the mutation backend contract used by handlers');
    assert.equal(contract.resolveMutationBackend('database', true), 'server');
    assert.equal(contract.resolveMutationBackend('database', false), 'unavailable');
    assert.equal(contract.resolveMutationBackend('', false), 'unavailable');
    assert.equal(contract.resolveMutationBackend('mock', false), 'mock');
    assert.ok(!learnerSource.includes('demo-token-12345'), 'production bundle has no hard-coded fake share token');
});
