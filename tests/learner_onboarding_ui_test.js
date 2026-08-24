const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const modulePath = path.join(root, 'assets', 'js', 'learner-onboarding.js');

test('pending dialog is mandatory and posts only fixed decisions', () => {
  const source = fs.readFileSync(path.join(root, 'app', 'learner', 'index.php'), 'utf8');
  assert.match(source, /data-onboarding-dialog/);
  assert.match(source, /name="action" value="accept"/);
  assert.match(source, /name="action" value="decline"/);
  assert.doesNotMatch(source, /data-onboarding-dialog[^]*data-close-modal/);
  assert.match(source, /Hoàn thành đánh giá ban đầu/);
  assert.match(source, /inert aria-hidden=/, 'background content is removed from focus and accessibility navigation');
});

test('progress hub renders four server-owned states', () => {
  const source = fs.readFileSync(path.join(root, 'app', 'learner', 'discover.php'), 'utf8');
  assert.match(source, /data-onboarding-progress/);
  assert.match(source, /completed_count/);
  assert.match(source, /required_count/);
  assert.match(source, /Đăng xuất và tiếp tục sau/);
  assert.match(source, /multiple_intelligence/);
});

test('safe onboarding destination accepts only local learner paths', () => {
  const { safeOnboardingDestination } = require(modulePath);
  assert.equal(safeOnboardingDestination('/app/learner/assessment.php?code=mbti'), '/app/learner/assessment.php?code=mbti');
  assert.equal(safeOnboardingDestination('/app/learner/discover.php?onboarding=completed'), '/app/learner/discover.php?onboarding=completed');
  assert.equal(safeOnboardingDestination('https://evil.example/app/learner/x'), null);
  assert.equal(safeOnboardingDestination('//evil.example/app/learner/x'), null);
  assert.equal(safeOnboardingDestination('/login.php'), null);
});

test('dialog focus is contained and Escape is suppressed', () => {
  const { containDialogFocus, suppressEscape } = require(modulePath);
  const first = { focusCalled: false, focus() { this.focusCalled = true; } };
  const last = { focusCalled: false, focus() { this.focusCalled = true; } };
  const dialog = {
    ownerDocument: { activeElement: last },
    querySelectorAll() { return [first, last]; },
    focus() {},
  };
  let prevented = false;
  containDialogFocus(dialog, { key: 'Tab', shiftKey: false, preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.equal(first.focusCalled, true);

  let escapePrevented = false;
  let stopped = false;
  suppressEscape({ key: 'Escape', preventDefault() { escapePrevented = true; }, stopPropagation() { stopped = true; } });
  assert.equal(escapePrevented, true);
  assert.equal(stopped, true);
});

test('assessment intro never advertises a stale hard-coded question count', () => {
  const pageSource = fs.readFileSync(path.join(root, 'app', 'learner', 'assessment.php'), 'utf8');
  const controllerSource = fs.readFileSync(path.join(root, 'assets', 'js', 'learner-assessment.js'), 'utf8');

  assert.doesNotMatch(pageSource, /data-assessment-intro-count>\s*24 câu/);
  assert.match(controllerSource, /assessment\.question_count \|\| detail\?\.questions\?\.length/);
});

test('restricted onboarding pages do not expose or poll notifications', () => {
  const headerSource = fs.readFileSync(path.join(root, 'app', 'learner', 'includes', 'header.php'), 'utf8');
  const notificationSource = fs.readFileSync(path.join(root, 'assets', 'js', 'learner-notifications.js'), 'utf8');

  assert.match(headerSource, /\$learnerOnboardingRestricted/);
  assert.match(headerSource, /'onboardingRestricted'\s*=>\s*\$learnerOnboardingRestricted/);
  assert.match(headerSource, /if\s*\(!\$learnerOnboardingRestricted\)/);
  assert.match(notificationSource, /getBootContext\(\)\.onboardingRestricted === true/);
});

test('student registration phone pattern compiles with the browser v flag', () => {
  const source = fs.readFileSync(path.join(root, 'register.php'), 'utf8');
  const match = source.match(/id="phone"[^\r\n]*?pattern="([^"]+)"/);
  assert.ok(match, 'phone input pattern must exist');

  let pattern;
  assert.doesNotThrow(() => {
    pattern = new RegExp(`^(?:${match[1]})$`, 'v');
  });
  assert.equal(pattern.test('+84 (28) 1234-5678'), true);
  assert.equal(pattern.test('invalid_phone'), false);
});
