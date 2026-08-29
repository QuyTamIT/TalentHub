const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const modulePath = path.join(__dirname, '..', 'assets', 'js', 'learner-ai-roadmap.js');

test('strict roadmap rejects every legacy rule state as unavailable', () => {
  const { presentationState } = require(modulePath);

  for (const state of ['ready_rule', 'fallback_rule', 'rule_fallback']) {
    assert.equal(presentationState({ state }), 'source-error', `${state} must not render a ready roadmap`);
  }
});
