/**
 * Tests for Learner Notifications UI and DOM Safety
 * Run with: node tests/learner_notifications_ui_test.js
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('Running tests/learner_notifications_ui_test.js...');

// 1. Static security audit of assets/js/learner-notifications.js
const jsFilePath = path.join(__dirname, '..', 'assets', 'js', 'learner-notifications.js');
const jsCode = fs.readFileSync(jsFilePath, 'utf8');
const learnerShellCode = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'learner.js'), 'utf8');
const headerCode = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'includes', 'header.php'), 'utf8');
const pageCode = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'notifications.php'), 'utf8');

// Assert no dangerous innerHTML assignments on dynamic variables
const dangerousPatterns = [
    /\.innerHTML\s*=\s*[^"'\s]/,
    /\.insertAdjacentHTML\s*\(/,
    /document\.write\s*\(/,
    /eval\s*\(/,
];

for (const pattern of dangerousPatterns) {
    assert.strictEqual(
        pattern.test(jsCode),
        false,
        `Security audit failed: found dangerous pattern ${pattern} in learner-notifications.js`
    );
}
console.log('  [PASS] Static security audit: Zero unsafe innerHTML or dynamic execution patterns found.');
assert.strictEqual(jsCode.includes('.innerHTML'), false, 'Notification UI uses DOM replacement APIs, not innerHTML');
assert.strictEqual(jsCode.includes('TalentHubLearnerApi.createLearnerApiClient'), true, 'Notification UI uses the shared learner API client');
assert.strictEqual(/\bfetch\s*\(/.test(jsCode), false, 'Notification UI does not bypass the shared learner API client');
assert.strictEqual(jsCode.includes('new AbortController()'), true, 'Notification list requests are cancellable');
assert.strictEqual(jsCode.includes('listRequestSequence'), true, 'Stale notification responses cannot overwrite the active filter');
assert.strictEqual(jsCode.includes("error?.code === 'REQUEST_ABORTED'"), true, 'Aborted notification requests do not render an error state');
assert.strictEqual(learnerShellCode.includes('Bạn có 3 thông báo mới'), false, 'Global learner shell contains no fake unread notification');
assert.strictEqual(headerCode.includes('learner-notifications.js'), true, 'Notification badge controller is loaded on every learner page through the shared header');
assert.strictEqual(pageCode.includes('chưa gửi email trong v1'), true, 'Preference UI discloses that Phase 8 does not send email');
assert.strictEqual(pageCode.includes('learner-notification-load-more'), true, 'Notification Center exposes server-backed pagination control');

// 2. Require module exports from learner-notifications.js
// Mock global DOM before requiring
class MockNode {
    constructor() {
        this.childNodes = [];
    }
    appendChild(child) {
        this.childNodes.push(child);
        return child;
    }
}

class MockElement extends MockNode {
    constructor(tagName) {
        super();
        this.tagName = tagName;
        this.attributes = {};
        this.eventListeners = {};
        this.className = '';
        this.textContent = '';
        this.style = {};
    }
    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }
    getAttribute(name) {
        return this.attributes[name];
    }
    removeAttribute(name) {
        delete this.attributes[name];
    }
    addEventListener(event, handler) {
        this.eventListeners[event] = handler;
    }
}

class MockTextNode extends MockNode {
    constructor(text) {
        super();
        this.textContent = text;
    }
}

global.document = {
    createElement(tag) {
        return new MockElement(tag);
    },
    createTextNode(text) {
        return new MockTextNode(text);
    },
    getElementById(id) {
        return null;
    },
    querySelector(sel) {
        return null;
    },
    querySelectorAll(sel) {
        return [];
    }
};
global.Node = MockNode;

const notificationsModule = require('../assets/js/learner-notifications.js');
const { isSafeDeepLink, el, ALLOWED_DEEP_LINKS, preferenceLabel } = notificationsModule;

// 3. Test Deep Link Validator
console.log('Testing Deep Link Validator...');
const safeLinks = [
    '/app/learner/my-activities.php',
    '/app/learner/checkin.php',
    '/app/learner/ecosystem.php',
    '/app/learner/assessment-result.php',
    '/app/learner/badges.php',
    '/app/learner/activity-history.php',
    '/app/learner/talent-passport.php',
    '/app/teacher/projects/index.php',
];

for (const link of safeLinks) {
    assert.strictEqual(isSafeDeepLink(link), true, `Expected safe link: ${link}`);
}

const unsafeLinks = [
    'https://attacker.com',
    'http://localhost/app/learner/my-activities.php',
    '//attacker.com/evil',
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    '/app/learner/../../etc/passwd',
    '/app/teacher/activities.php',
    '/app/admin/dashboard.php',
    '/app/learner/my-activities.php?tab=approved',
    '/app/learner/checkin.php#fragment',
    '/app/learner/activities.php',
    '/app/learner/notifications.php',
    'blob:https://talenthub.test/12345',
];

for (const link of unsafeLinks) {
    assert.strictEqual(isSafeDeepLink(link), false, `Expected unsafe link to be rejected: ${link}`);
}
console.log('  [PASS] Deep Link allow-list validation passed.');
assert.strictEqual(preferenceLabel('activity_attendance_no_show'), 'Thông báo hoạt động không tham gia');
assert.strictEqual(preferenceLabel('activity_checkin_committed'), 'Check-in và giờ trải nghiệm');

// 4. Test DOM Construction Helper Safety
console.log('Testing el() DOM Helper Safety...');
const xssString = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
const testElement = el('div', { className: 'test-card' }, [
    el('h3', { textContent: xssString }),
    el('p', { textContent: 'Normal message' }),
    'Direct text child: ' + xssString
]);

assert.strictEqual(testElement.className, 'test-card');
assert.strictEqual(testElement.childNodes.length, 3);
assert.strictEqual(testElement.childNodes[0].textContent, xssString);
assert.strictEqual(testElement.childNodes[2] instanceof MockTextNode, true);
assert.strictEqual(testElement.childNodes[2].textContent, 'Direct text child: ' + xssString);
console.log('  [PASS] DOM creation helper properly treats XSS strings as plain text nodes.');

// 5. API/UI contract helpers must match the production endpoint exactly.
const { normalizePreferences, buildNotificationQuery, apiRequest } = notificationsModule;
const normalized = normalizePreferences({
    activity_registration_created: { inAppEnabled: true, emailEnabled: false, updatedAt: null },
});
assert.deepStrictEqual(normalized, [{
    notificationType: 'activity_registration_created',
    inAppEnabled: true,
    emailEnabled: false,
    updatedAt: null,
}]);
assert.strictEqual(
    buildNotificationQuery('unread', 25, 50),
    '/app/learner/api/v1/notifications.php?filter=unread&limit=25&offset=50'
);

(async () => {
    let captured = null;
    const client = {
        async send(method, endpoint, body) {
            captured = { method, endpoint, body };
            return { unreadCount: 0 };
        },
        async get(endpoint) {
            captured = { method: 'GET', endpoint };
            return { unreadCount: 0 };
        },
    };
    await apiRequest('/app/learner/api/v1/notifications.php', 'PATCH', {
        action: 'mark-read',
        notificationId: '11111111-1111-4111-8111-111111111111',
    }, client);
    assert.strictEqual(captured.endpoint, '/notifications.php');
    assert.strictEqual(captured.body.action, 'mark-read', 'PATCH action is sent in JSON body');

    const failingClient = { async send() { throw new Error('VALIDATION_FAILED'); } };
    await assert.rejects(
        () => apiRequest('/app/learner/api/v1/notifications.php', 'PATCH', { action: 'mark-all-read' }, failingClient),
        /VALIDATION_FAILED/,
        'API error envelopes reject instead of silently succeeding'
    );

    console.log('  [PASS] Browser/API contract helpers match the Phase 8 endpoint.');
    console.log('All tests in learner_notifications_ui_test.js PASSED.\n');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
