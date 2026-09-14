'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const assessmentPhp = fs.readFileSync(path.join(root, 'app/learner/assessment.php'), 'utf8');
const assessmentResultPhp = fs.readFileSync(path.join(root, 'app/learner/assessment-result.php'), 'utf8');
const learnerAssessmentJs = fs.readFileSync(path.join(root, 'assets/js/learner-assessment.js'), 'utf8');
const learnerApiJs = fs.readFileSync(path.join(root, 'assets/js/learner-api.js'), 'utf8');
const assessmentWriteRepo = fs.readFileSync(path.join(root, 'app/learner/data/Database/DatabaseAssessmentWriteRepository.php'), 'utf8');
const assessmentAttemptsEndpoint = fs.readFileSync(path.join(root, 'app/learner/api/v1/assessment-attempts.php'), 'utf8');
const talentPassportRepo = fs.readFileSync(path.join(root, 'app/learner/data/Database/DatabaseTalentPassportRepository.php'), 'utf8');
const talentPassportPhp = fs.readFileSync(path.join(root, 'app/learner/talent-passport.php'), 'utf8');
const aiRoadmapService = fs.readFileSync(path.join(root, 'src/Modules/Student/AiRoadmapService.php'), 'utf8');
const assessmentService = fs.readFileSync(path.join(root, 'app/learner/data/Service/LearnerAssessmentService.php'), 'utf8');

const {
    presentationState,
    buildRetakeWarningMessage,
    createAssessmentController,
    bootCatalog,
    bootRunner,
    bootResult,
} = require(path.join(root, 'assets/js/learner-assessment.js'));

test('backend repository and endpoint contracts for 90-day soft warning and retake', () => {
    // 1. Signature supports confirmEarlyRetake parameter
    assert.match(
        assessmentWriteRepo,
        /public function startOrResumeAttempt\(\s*string \$studentId,\s*string \$assessmentCode,\s*string \$(educationBand|band),\s*bool \$confirmEarlyRetake = false\s*\)/
    );
    assert.match(
        assessmentService,
        /public function startOrResume\(\s*string \$studentId,\s*string \$assessmentCode,\s*string \$(educationBand|band),\s*bool \$confirmEarlyRetake = false\s*\)/
    );

    // 2. Soft warning when under 90 days without confirmation
    assert.match(assessmentWriteRepo, /'status' => 'retake_confirmation_required'/);
    assert.match(assessmentWriteRepo, /'code' => 'RETAKE_CONFIRMATION_REQUIRED'/);
    assert.match(assessmentWriteRepo, /'requires_confirmation' => true/);
    assert.match(assessmentWriteRepo, /'elapsed_days' => \$elapsedDays/);
    assert.match(assessmentWriteRepo, /'remaining_days' => \$remainingDays/);
    assert.match(assessmentWriteRepo, /'last_submitted_at' => \$submittedAt->format/);

    // 3. Immutability: insert new attempt when confirmed or >= 90 days, previous attempt untouched
    assert.match(assessmentWriteRepo, /INSERT INTO test_attempts \([^)]*id[^)]*testId[^)]*studentId[^)]*status[^)]*\) VALUES/);
    assert.doesNotMatch(assessmentWriteRepo, /UPDATE test_attempts SET status = 'in_progress'/);

    // 4. Endpoint allows confirm_early_retake input and maps RETAKE_CONFIRMATION_REQUIRED to 409
    assert.match(assessmentAttemptsEndpoint, /'confirm_early_retake'/);
    assert.match(assessmentAttemptsEndpoint, /'RETAKE_CONFIRMATION_REQUIRED'/);
    assert.match(assessmentAttemptsEndpoint, /new ApiException\(409, 'RETAKE_CONFIRMATION_REQUIRED'/);
});

test('Vietnamese modal markup and messaging in assessment-result.php', () => {
    // Retake action button
    assert.match(assessmentResultPhp, /data-retake-assessment/);
    assert.match(assessmentResultPhp, /data-assessment-code=/);
    assert.match(assessmentResultPhp, /Làm lại bài đánh giá/);

    // Retake modal structure
    assert.match(assessmentResultPhp, /data-assessment-retake-modal/);
    assert.match(assessmentResultPhp, /id="retake-modal-title"/);
    assert.match(assessmentResultPhp, /Xác nhận làm lại bài đánh giá/);
    assert.match(assessmentResultPhp, /data-retake-elapsed-days/);
    assert.match(assessmentResultPhp, /data-retake-remaining-days/);
    assert.match(assessmentResultPhp, /data-retake-modal-message/);

    // Modal action buttons
    assert.match(assessmentResultPhp, /data-cancel-retake/);
    assert.match(assessmentResultPhp, /Giữ kết quả hiện tại/);
    assert.match(assessmentResultPhp, /data-confirm-retake/);
    assert.match(assessmentResultPhp, /Xác nhận làm lại/);

    // Exact Vietnamese text template
    assert.match(assessmentResultPhp, /Bạn đã hoàn thành bài đánh giá này cách đây/);
    assert.match(assessmentResultPhp, /Kết quả xu hướng năng lực và tính cách thường ổn định và đạt độ tin cậy cao nhất sau chu kỳ/);
    assert.match(assessmentResultPhp, /90 ngày/);
    assert.match(assessmentResultPhp, /Bạn có chắc chắn muốn làm lại ngay bây giờ không\?/);
});

test('buildRetakeWarningMessage helper format and edge cases', () => {
    assert.equal(typeof buildRetakeWarningMessage, 'function');
    const msg = buildRetakeWarningMessage(15, 75);
    assert.equal(
        msg,
        'Bạn đã hoàn thành bài đánh giá này cách đây 15 ngày. Kết quả xu hướng năng lực và tính cách thường ổn định và đạt độ tin cậy cao nhất sau chu kỳ 90 ngày (còn 75 ngày nữa). Bạn có chắc chắn muốn làm lại ngay bây giờ không?'
    );

    // Edge cases: 0 days, string numbers, negative numbers clamped
    const msgZero = buildRetakeWarningMessage(0, 90);
    assert.match(msgZero, /cách đây 0 ngày/);
    assert.match(msgZero, /còn 90 ngày nữa/);

    const msgString = buildRetakeWarningMessage('42', '48');
    assert.match(msgString, /cách đây 42 ngày/);
    assert.match(msgString, /còn 48 ngày nữa/);
});

test('presentationState recognizes retake-confirmation-required', () => {
    assert.equal(presentationState({ status: 'retake-confirmation-required' }), 'retake-confirmation-required');
    assert.equal(presentationState({ status: 'retake_confirmation_required' }), 'retake-confirmation-required');
    assert.equal(presentationState({ status: 'ready' }), 'ready');
});

test('createAssessmentController handles retake confirmation required and confirmed retake', async () => {
    const renders = [];
    const fakeView = {
        render: (state, payload) => renders.push({ state, payload }),
    };

    // Scenario 1: First attempt within 90 days returns 409 RETAKE_CONFIRMATION_REQUIRED
    const error409 = {
        status: 409,
        code: 'RETAKE_CONFIRMATION_REQUIRED',
        message: 'Cần xác nhận làm lại bài đánh giá trong vòng 90 ngày.',
        elapsed_days: 12,
        remaining_days: 78,
        last_submitted_at: '2026-08-28T10:00:00.000Z',
    };

    const sentRequests = [];
    const fakeApi = {
        get: async () => ({}),
        send: async (method, endpoint, payload) => {
            sentRequests.push({ method, endpoint, payload });
            if (payload.confirm_early_retake === false || !payload.confirm_early_retake) {
                const err = new Error(error409.message);
                Object.assign(err, error409);
                throw err;
            }
            return {
                id: 'new-attempt-id-12345',
                assessment_code: payload.assessmentCode,
                status: 'in_progress',
                questions: [{ id: 'q1', text: 'Sample question' }],
            };
        },
    };

    const controller = createAssessmentController({ api: fakeApi, view: fakeView });

    // Calling startOrResume without confirmation
    const firstResult = await controller.startOrResume('holland', 'high', false);
    assert.equal(firstResult.status, 'retake-confirmation-required');
    assert.equal(firstResult.code, 'RETAKE_CONFIRMATION_REQUIRED');
    assert.equal(firstResult.elapsed_days, 12);
    assert.equal(firstResult.remaining_days, 78);
    assert.equal(sentRequests.length, 1);
    assert.equal(Boolean(sentRequests[0].payload.confirm_early_retake), false);
    assert.equal(renders[renders.length - 1].state, 'retake-confirmation-required');

    // Calling startOrResume with confirmation
    const secondResult = await controller.startOrResume('holland', 'high', true);
    assert.equal(secondResult.id, 'new-attempt-id-12345');
    assert.equal(secondResult.status, 'in_progress');
    assert.equal(renders[renders.length - 1].state, 'ready');
    assert.equal(sentRequests.length, 2);
    assert.equal(sentRequests[1].payload.confirm_early_retake, true);
});

// Hoisted DOM mock helpers
function matches(el, sel) {
    if (sel.includes(',')) {
        return sel.split(',').some((sub) => matches(el, sub.trim()));
    }
    if (sel.startsWith('[') && sel.endsWith(']')) {
        const attr = sel.slice(1, -1);
        if (attr.includes('=')) {
            const [name, val] = attr.split('=');
            return el.getAttribute(name) === val.replace(/^["']|["']$/g, '');
        }
        return el.getAttribute(attr) !== null;
    }
    if (sel.startsWith('#')) return el.getAttribute('id') === sel.slice(1);
    if (sel.startsWith('.')) return (el.getAttribute('class') || '').includes(sel.slice(1));
    return false;
}

function findChild(parent, sel) {
    if (!parent || !parent.children) return null;
    for (const child of parent.children) {
        if (matches(child, sel)) return child;
        const nested = findChild(child, sel);
        if (nested) return nested;
    }
    return null;
}

function findAllChildren(parent, sel) {
    const found = [];
    if (!parent || !parent.children) return found;
    for (const child of parent.children) {
        if (matches(child, sel)) found.push(child);
        found.push(...findAllChildren(child, sel));
    }
    return found;
}

function createElement(tag, attrs = {}, textContent = '') {
    const listeners = {};
    const children = [];
    const el = {
        tagName: tag.toUpperCase(),
        dataset: {},
        hidden: false,
        disabled: false,
        textContent,
        listeners,
        children,
        get href() { return attrs.href || this._href || ''; },
        set href(val) { attrs.href = val; this._href = val; },
        getAttribute(name) { return attrs[name] ?? (this[name] !== undefined ? this[name] : null); },
        setAttribute(name, val) { attrs[name] = val; },
        removeAttribute(name) { delete attrs[name]; },
        addEventListener(event, fn) {
            listeners[event] = listeners[event] || [];
            listeners[event].push(fn);
        },
        dispatchEvent(event) {
            const fns = listeners[event.type || event] || [];
            for (const fn of fns) fn(event);
        },
        querySelector(sel) {
            return findChild(this, sel);
        },
        querySelectorAll(sel) {
            return findAllChildren(this, sel);
        },
        appendChild(child) {
            children.push(child);
            child.parentNode = this;
            return child;
        },
        removeChild(child) {
            const idx = children.indexOf(child);
            if (idx >= 0) children.splice(idx, 1);
            return child;
        },
        get firstChild() { return children[0] || null; },
    };
    for (const [k, v] of Object.entries(attrs)) {
        if (k.startsWith('data-')) {
            const camel = k.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
            el.dataset[camel] = v;
        }
    }
    return el;
}

function createDocument() {
    const doc = createElement('document');
    doc.ownerDocument = doc;
    doc.createElement = (tag) => {
        const el = createElement(tag);
        el.ownerDocument = doc;
        return el;
    };
    return doc;
}

test('bootResult wires up retake modal, cancel keeps current result, confirm redirects to new attempt', async () => {
    // Set up document tree
    const doc = createDocument();

    const rootEl = doc.createElement('main');
    rootEl.setAttribute('data-assessment-result-page', '');
    rootEl.setAttribute('data-assessment-code', 'mbti');
    rootEl.dataset.assessmentCode = 'mbti';
    doc.appendChild(rootEl);

    const retakeBtn = doc.createElement('button');
    retakeBtn.setAttribute('data-retake-assessment', '');
    retakeBtn.setAttribute('data-assessment-code', 'mbti');
    rootEl.appendChild(retakeBtn);

    const retakeModal = doc.createElement('div');
    retakeModal.setAttribute('data-assessment-retake-modal', '');
    retakeModal.hidden = true;
    doc.appendChild(retakeModal);

    const elapsedEl = doc.createElement('strong');
    elapsedEl.setAttribute('data-retake-elapsed-days', '');
    elapsedEl.textContent = '0';
    retakeModal.appendChild(elapsedEl);

    const remainingEl = doc.createElement('strong');
    remainingEl.setAttribute('data-retake-remaining-days', '');
    remainingEl.textContent = '0';
    retakeModal.appendChild(remainingEl);

    const cancelBtn = doc.createElement('button');
    cancelBtn.setAttribute('data-cancel-retake', '');
    cancelBtn.setAttribute('data-close-retake-modal', '');
    retakeModal.appendChild(cancelBtn);

    const confirmBtn = doc.createElement('button');
    confirmBtn.setAttribute('data-confirm-retake', '');
    retakeModal.appendChild(confirmBtn);

    const requests = [];
    const fakeApi = {
        get: async () => ({ history: [] }),
        send: async (method, endpoint, payload) => {
            requests.push({ method, endpoint, payload });
            if (!payload.confirm_early_retake) {
                const err = new Error('Retake confirmation required');
                err.status = 409;
                err.code = 'RETAKE_CONFIRMATION_REQUIRED';
                err.elapsed_days = 20;
                err.remaining_days = 70;
                throw err;
            }
            return {
                id: 'attempt-retake-999',
                assessment_code: 'mbti',
                status: 'in_progress',
            };
        },
    };

    let redirectedUrl = '';
    const originalLocation = global.location;
    global.location = {
        search: '?code=mbti',
        set href(val) { redirectedUrl = val; },
        get href() { return redirectedUrl; },
    };

    try {
        bootResult(rootEl, fakeApi);

        // Click retake button -> triggers API call without confirmation
        retakeBtn.dispatchEvent({ type: 'click', preventDefault: () => {} });
        await new Promise((resolve) => setTimeout(resolve, 10));

        assert.equal(requests.length, 1);
        assert.equal(Boolean(requests[0].payload.confirm_early_retake), false);
        // Modal must be open
        assert.equal(retakeModal.hidden, false);
        assert.equal(elapsedEl.textContent, '20');
        assert.equal(remainingEl.textContent, '70');

        // Click cancel button -> closes modal, no redirect, old result preserved
        cancelBtn.dispatchEvent({ type: 'click', preventDefault: () => {} });
        assert.equal(retakeModal.hidden, true);
        assert.equal(redirectedUrl, '');

        // Click confirm button -> calls API with confirm_early_retake: true and redirects
        confirmBtn.dispatchEvent({ type: 'click', preventDefault: () => {} });
        await new Promise((resolve) => setTimeout(resolve, 10));

        assert.equal(requests.length, 2);
        assert.equal(requests[1].payload.confirm_early_retake, true);
        assert.match(redirectedUrl, /assessment\.php\?code=mbti&attempt=attempt-retake-999/);
    } finally {
        global.location = originalLocation;
    }
});

test('read repositories and services prioritize latest submitted attempt', () => {
    // 1. Talent Passport Repository query orders by submittedAt DESC, ta.id DESC
    assert.match(
        talentPassportRepo,
        /ORDER BY ta\.submittedAt DESC, ta\.id DESC/,
        'DatabaseTalentPassportRepository orders attempts newest first'
    );

    // 2. Talent Passport view deduplicates by test type/code so only newest attempt is displayed
    assert.match(
        talentPassportPhp,
        /\$seenAssessmentTypes\[\$dedupKey\]/,
        'talent-passport.php deduplicates assessment results by type'
    );

    // 3. AiRoadmapService only retains the first (newest) attempt for each assessment type
    assert.match(
        aiRoadmapService,
        /if \(\$type !== '' && !isset\(\$result\[\$type\]\)\)/,
        'AiRoadmapService retains only the first (newest) attempt for each assessment type'
    );
});

test('Vietnamese modal markup in assessment.php for retake confirmation', () => {
    assert.match(assessmentPhp, /data-assessment-retake-modal/);
    assert.match(assessmentPhp, /id="retake-modal-title"/);
    assert.match(assessmentPhp, /Xác nhận làm lại bài đánh giá/);
    assert.match(assessmentPhp, /data-retake-elapsed-days/);
    assert.match(assessmentPhp, /data-retake-remaining-days/);
    assert.match(assessmentPhp, /data-retake-modal-message/);
    assert.match(assessmentPhp, /data-cancel-retake/);
    assert.match(assessmentPhp, /Giữ kết quả hiện tại/);
    assert.match(assessmentPhp, /data-confirm-retake/);
    assert.match(assessmentPhp, /Xác nhận làm lại/);
});

test('bootCatalog creates active retake link for completed assessments rather than disabled button', async () => {
    const doc = createDocument();

    const rootEl = doc.createElement('div');
    rootEl.setAttribute('data-assessment-catalog', '');
    const cardsEl = doc.createElement('div');
    cardsEl.setAttribute('data-catalog-cards', '');
    rootEl.appendChild(cardsEl);

    const fakeCatalog = {
        assessments: [
            {
                code: 'disc',
                status: 'published',
                attempt_status: 'retake_locked',
                latest_result: { id: 'attempt-123', result_code: 'CDIS' },
                can_view_result: true,
            },
            {
                code: 'holland',
                status: 'published',
                attempt_status: 'submitted',
                latest_result: { id: 'attempt-456', result_code: 'ACR' },
                can_view_result: true,
            },
        ],
    };

    const fakeApi = {
        get: async (endpoint) => {
            if (endpoint.includes('view=history')) {
                return { assessment_history: { items: [] } };
            }
            return fakeCatalog;
        },
    };

    await bootCatalog(rootEl, fakeApi);

    // Cards must have rendered
    assert.equal(cardsEl.children.length, 2);

    // Check DISC card
    const discCard = cardsEl.children[0];
    const discAction = discCard.children.find((c) => c.tagName === 'A' && c.getAttribute('href')?.includes('code=disc'));
    assert.ok(discAction, 'DISC action must be an active <a> link');
    assert.equal(discAction.disabled, false);
    assert.equal(discAction.textContent, 'Làm lại bài đánh giá');
    assert.notEqual(discAction.textContent, 'Chưa thể làm lại');

    // Check Holland card
    const hollandCard = cardsEl.children[1];
    const hollandAction = hollandCard.children.find((c) => c.tagName === 'A' && c.getAttribute('href')?.includes('code=holland'));
    assert.ok(hollandAction, 'Holland action must be an active <a> link');
    assert.equal(hollandAction.disabled, false);
    assert.equal(hollandAction.textContent, 'Làm lại bài đánh giá');
});
