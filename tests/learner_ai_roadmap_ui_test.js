const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const modulePath = path.join(root, 'assets', 'js', 'learner-ai-roadmap.js');
const pagePath = path.join(root, 'app', 'learner', 'ai-recommendations.php');
const cssPath = path.join(root, 'assets', 'css', 'learner.css');

function payload(origin = 'model') {
  const phases = [
    ['discover', 0, 30, 'Khám phá'],
    ['practice', 31, 60, 'Thực hành'],
    ['breakthrough', 61, 90, 'Bứt phá'],
  ].map(([code, start, end, title], phaseIndex) => ({
    phase_id: `phase-${phaseIndex + 1}`,
    position: phaseIndex + 1,
    code,
    start_day: start,
    end_day: end,
    title,
    goal: `Mục tiêu ${phaseIndex + 1}`,
    skill_focus: `Kỹ năng ${phaseIndex + 1}`,
    deliverable: `Sản phẩm ${phaseIndex + 1}`,
    effort_label: '3 giờ/tuần',
    metric_label: `Chỉ số ${phaseIndex + 1}`,
    progress: { completed_tasks: phaseIndex === 0 ? 1 : 0, total_tasks: 3 },
    tasks: [0, 1, 2].map((taskIndex) => ({
      task_id: `task-${phaseIndex + 1}-${taskIndex + 1}`,
      title: `Nhiệm vụ ${phaseIndex + 1}.${taskIndex + 1}`,
      description: 'Đầu việc có thể kiểm chứng.',
      estimated_minutes: 45,
      status: phaseIndex === 0 && taskIndex === 0 ? 'completed' : 'not_started',
      action: phaseIndex === 1 && taskIndex === 0
        ? { type: 'register_activity', activity_source_id: '11111111-1111-4111-8111-111111111111' }
        : { type: 'self_task' },
    })),
  }));
  return {
    state: origin === 'model' ? 'ready_model' : 'fallback_rule',
    analysis_origin: origin,
    executive_summary: 'Bạn có tiềm năng phát triển sản phẩm công nghệ giải quyết vấn đề thực tế.',
    confidence_band: 'high',
    generated_at: '2026-08-24T12:00:00Z',
    primary_direction: { code: 'technology_product', label: 'Công nghệ sản phẩm', rationale: 'Kiểm chứng bằng sản phẩm thật.' },
    alternative_directions: [
      { code: 'automation', label: 'Tự động hóa', rationale: 'Thử qua dự án kỹ thuật.' },
      { code: 'data', label: 'Phân tích dữ liệu', rationale: 'Rèn tư duy phân tích.' },
    ],
    insights: [
      { category: 'strength', title: 'Lợi thế', summary: 'Biến ý tưởng thành thử nghiệm.' },
      { category: 'improvement', title: 'Cần cải thiện', summary: 'Trình bày quyết định rõ hơn.' },
      { category: 'potential', title: 'Tiềm năng', summary: 'Dẫn dắt dự án nhóm nhỏ.' },
    ],
    talent_map: [
      { field: 'Kỹ thuật', score: 72, evidence_ref_ids: ['assessment:holland'] },
      { field: 'Kinh doanh', score: 28, evidence_ref_ids: ['assessment:disc'] },
    ],
    strengths: [{ text: 'Tư duy logic và học công nghệ nhanh.', evidence_ref_ids: ['skill:logic'] }],
    improvements: [{ text: 'Thuyết trình trước nhóm đông.', evidence_ref_ids: ['evaluation:presentation'] }],
    potential_paths: [{ label: 'Kỹ sư tự động hóa', catalog_id: 'project-robotics', evidence_ref_ids: ['assessment:holland'] }],
    trend_signals: [{ direction: 'up', label: 'Kỹ năng trình bày đang tiến bộ.', evidence_ref_ids: ['evaluation:presentation'] }],
    growth_hypotheses: [{ text: 'Có thể dẫn dắt dự án kỹ thuật nhỏ.', confidence: 0.78, evidence_ref_ids: ['project:robotics'] }],
    evidence_summary: { assessment_count: 4, skill_count: 2, activity_count: 1, evaluation_count: 1 },
    engine: origin === 'model'
      ? { provider: '9router_gemini', model_version: 'ag/gemini', prompt_version: 'roadmap-1' }
      : { rule_version: 'rule-1', fallback_reason: 'provider_unavailable' },
    phases,
    progress: { completed_tasks: 1, total_tasks: 9 },
  };
}

function viewRecorder() {
  return { events: [], render(state, data) { this.events.push([state, data]); } };
}

test('roadmap module maps all stable API states', () => {
  const { presentationState } = require(modulePath);
  assert.deepEqual([
    'not_generated', 'pending', 'consent_required', 'insufficient_data',
    'source_unavailable', 'engine_failure', 'ready_model', 'stale_model', 'fallback_rule',
  ].map((state) => presentationState({ state })), [
    'not-generated', 'pending', 'consent-required', 'insufficient-data',
    'source-error', 'source-error', 'ready-model', 'stale-model', 'fallback-rule',
  ]);
});

test('roadmap API client receives session CSRF and the dedicated model timeout', () => {
  const { createRoadmapApiClient } = require(modulePath);
  assert.equal(typeof createRoadmapApiClient, 'function');
  let options = null;
  const client = { get() {}, send() {} };
  const suppliedDocument = {
    getElementById(id) {
      assert.equal(id, 'learner-session-boot');
      return { textContent: JSON.stringify({ csrfToken: 'csrf-roadmap-page-123' }) };
    },
  };
  assert.equal(createRoadmapApiClient((received) => { options = received; return client; }, suppliedDocument), client);
  assert.deepEqual(options, {
    baseUrl: '/app/learner/api/v1',
    csrfToken: 'csrf-roadmap-page-123',
    timeoutMs: 45000,
  });
});

test('view model keeps exactly three roadmap phases and derives real next actions', () => {
  const { buildRoadmapViewModel } = require(modulePath);
  const model = buildRoadmapViewModel(payload());
  assert.equal(model.phases.length, 3);
  assert.deepEqual(model.phases.map((phase) => phase.rangeLabel), ['1–30 ngày', '31–60 ngày', '61–90 ngày']);
  assert.equal(model.nextActions.length, 3);
  assert.equal(model.nextActions.every((task) => task.status !== 'completed'), true);
  assert.equal(model.activities.length, 1);
  assert.equal(model.evidenceTotal, 8);
  assert.equal(model.confidenceLabel, 'Độ tin cậy cao');
  assert.equal(model.talentMap.length, 2);
  assert.equal(model.strengths.length, 1);
  assert.equal(model.improvements.length, 1);
  assert.equal(model.potentialPaths.length, 1);
  assert.equal(model.trendSignals.length, 1);
  assert.equal(model.growthHypotheses.length, 1);
  assert.equal(Object.hasOwn(model, 'fitPercentage'), false, 'UI never invents a fit percentage');
});

test('view model normalizes fractional talent scores without changing percentage scores', () => {
  const { buildRoadmapViewModel } = require(modulePath);
  const fractional = payload();
  fractional.talent_map = [
    { field: 'Logic', score: 0.82 },
    { field: 'Thực hành', score: 72 },
    { field: 'Điều phối', score: -4 },
    { field: 'Sáng tạo', score: 120 },
  ];
  assert.deepEqual(buildRoadmapViewModel(fractional).talentMap.map((item) => item.score), [82, 72, 0, 100]);
});

test('view model exposes one current phase and compact direction rows', () => {
  const { buildRoadmapViewModel } = require(modulePath);
  const model = buildRoadmapViewModel(payload());
  assert.equal(model.currentPhaseIndex, 0);
  assert.deepEqual(model.phases.map((phase) => phase.status), ['current', 'upcoming', 'upcoming']);
  assert.equal(model.phases.every((phase) => phase.displayTasks.length === 2), true);
  assert.equal(model.overallPercent, 11);
});

test('canonical ready_rule state renders as an explicit fallback rule', () => {
    assert.equal(require(modulePath).presentationState({ state: 'ready_rule' }), 'fallback-rule');
});

test('pending roadmap polls with bounded exponential backoff and stops when ready', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = viewRecorder();
  const scheduled = [];
  let reads = 0;
  const api = {
    async get() { reads += 1; return reads === 1 ? { state: 'pending' } : payload(); },
    async send() { throw new Error('not used'); },
  };
  const controller = createRoadmapController({
    api, view,
    schedule: (callback, delay) => { scheduled.push({ callback, delay }); return scheduled.length; },
    cancelSchedule: () => {},
    pendingDelays: [1000, 2000, 4000],
  });
  await controller.load();
  assert.equal(view.events.at(-1)[0], 'pending');
  assert.equal(scheduled[0].delay, 1000);
  await scheduled[0].callback();
  assert.equal(reads, 2);
  assert.equal(view.events.at(-1)[0], 'ready-model');
  assert.equal(scheduled.length, 1);
  controller.dispose();
});

test('controller loads roadmap and reuses one in-flight refresh', async () => {
  const { createRoadmapController } = require(modulePath);
  const view = viewRecorder();
  const calls = [];
  let release;
  const gate = new Promise((resolve) => { release = resolve; });
  const api = {
    async get(endpoint) { calls.push(['GET', endpoint]); return payload(); },
    async send(method, endpoint, body, options) { calls.push([method, endpoint, body, options]); await gate; return payload(); },
  };
  const controller = createRoadmapController({ api, view, createIdempotencyKey: () => 'roadmap-page-key-0001' });
  await controller.load();
  assert.equal(view.events.at(-1)[0], 'ready-model');
  const first = controller.generate('refresh');
  const second = controller.generate('refresh');
  release();
  await Promise.all([first, second]);
  assert.equal(calls.filter((call) => call[0] === 'POST').length, 1);
  assert.deepEqual(calls.at(-1), ['POST', '/ai-roadmap.php', { action: 'refresh' }, { idempotencyKey: 'roadmap-page-key-0001' }]);
});

test('DOM view renders the canonical model payload into semantic roadmap regions', () => {
  const { createDomView, buildRoadmapViewModel } = require(modulePath);
  class FakeNode {
    constructor(tag = 'div') { this.tagName = tag.toUpperCase(); this.className = ''; this.children = []; this.dataset = {}; this.attributes = {}; this.style = { setProperty: (name, value) => { this[name] = value; } }; this.hidden = false; this.textContent = ''; }
    get firstChild() { return this.children[0] || null; }
    append(...nodes) { this.children.push(...nodes); }
    appendChild(node) { this.children.push(node); return node; }
    removeChild(node) { this.children.splice(this.children.indexOf(node), 1); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
  }
  const selectors = [
    'status', 'loading', 'not-generated', 'consent', 'insufficient', 'pending', 'error', 'ready', 'fallback',
    'freshness', 'summary-label', 'summary-text', 'evidence-total', 'confidence', 'direction-label',
    'direction-rationale', 'direction-alternatives', 'insights', 'phases', 'overall-progress', 'next-actions',
    'activities', 'evidence-content', 'engine-content',
    'talent-map', 'strengths', 'improvements', 'trends', 'potential-paths', 'growth-hypotheses',
  ];
  const nodes = Object.fromEntries(selectors.map((name) => [`[data-roadmap-${name}]`, new FakeNode()]));
  const doc = { createElement: (tag) => new FakeNode(tag), createElementNS: (_namespace, tag) => new FakeNode(tag) };
  const rootNode = { ownerDocument: doc, querySelector: (selector) => nodes[selector] || null };
  const radarPayload = payload();
  radarPayload.talent_map.push({ field: 'Điều phối', score: 68, evidence_ref_ids: ['evaluation:coordination'] });
  createDomView(rootNode).render('ready-model', buildRoadmapViewModel(radarPayload));
  assert.match(nodes['[data-roadmap-summary-text]'].textContent, /sản phẩm công nghệ/);
  assert.equal(nodes['[data-roadmap-phases]'].children.length, 3);
  assert.equal(nodes['[data-roadmap-next-actions]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-activities]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-talent-map]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-talent-map]'].children[0].tagName, 'SVG');
  assert.equal(nodes['[data-roadmap-talent-map]'].children[0].attributes.role, 'img');
  assert.match(nodes['[data-roadmap-talent-map]'].children[0].attributes['aria-label'], /Bản đồ năng khiếu/);
  assert.match(nodes['[data-roadmap-phases]'].children[0].className, /is-current/);
  assert.equal(nodes['[data-roadmap-phases]'].children[0].children.some((item) => item.textContent === 'Bạn đang ở đây'), true);
  assert.equal(nodes['[data-roadmap-overall-progress]'].attributes['aria-valuenow'], '11');
  assert.equal(nodes['[data-roadmap-strengths]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-improvements]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-trends]'].children.length, 1);
  assert.equal(nodes['[data-roadmap-engine-content]'].children.length, 6);
});

test('page exposes every Roadmap-first region and the live recommendation client', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  assert.match(page, /ROADMAP PHÁT TRIỂN 90 NGÀY/);
  assert.match(page, /learner-roadmap-hero/);
  assert.match(page, /learner-roadmap-analysis/);
  assert.match(page, /<details[^>]+learner-roadmap-secondary/);
  assert.doesNotMatch(page, /learner-roadmap-capability__grid/);
  for (const marker of [
    'data-ai-roadmap-page', 'data-roadmap-summary', 'data-roadmap-direction',
    'data-roadmap-phases', 'data-roadmap-next-actions', 'data-roadmap-activities',
    'data-roadmap-evidence', 'data-roadmap-feedback', 'data-roadmap-engine',
    'data-roadmap-talent-map', 'data-roadmap-strengths', 'data-roadmap-improvements',
    'data-roadmap-trends', 'data-roadmap-potential-paths', 'data-roadmap-growth-hypotheses',
    'data-roadmap-generate', 'data-roadmap-retry', 'learner-ai-roadmap.js',
  ]) assert.match(page, new RegExp(marker));
  assert.match(page, /learner-recommendations\.js/);
});

test('page loads live catalog recommendations through the server API client', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  assert.match(page, /data-ai-page/);
  assert.match(page, /data-ai-result-list/);
  assert.match(page, /learner-recommendations\.js/);
  const client = fs.readFileSync(path.join(root, 'assets', 'js', 'learner-recommendations.js'), 'utf8');
  assert.match(client, /api\.get\('\/recommendations\.php'\)/);
  assert.doesNotMatch(client, /generativelanguage\.googleapis|x-goog-api-key/i);
});

test('renderer uses safe text nodes and never duplicates assessment result content', () => {
  const page = fs.readFileSync(pagePath, 'utf8');
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.match(source, /textContent/);
  assert.doesNotMatch(source, /innerHTML/);
  for (const duplicated of ['Holland', 'MBTI', 'DISC', 'Multiple Intelligence', 'dimension_scores']) {
    assert.equal(page.includes(duplicated), false, `page omits ${duplicated}`);
    assert.equal(source.includes(duplicated), false, `renderer omits ${duplicated}`);
  }
  assert.match(source, /Tóm tắt từ AI/);
  assert.match(source, /Gợi ý dự phòng theo quy tắc/);
  assert.match(source, /AI chưa được bật cho tài khoản này/);
  assert.match(source, /(?:document|doc)\.createElement\('details'\)/);
});

test('Roadmap-first CSS is scoped, responsive and accessible', () => {
  const css = fs.readFileSync(cssPath, 'utf8');
  assert.match(css, /\.learner-page-ai \.learner-roadmap/);
  assert.match(css, /min-height:\s*44px/);
  assert.match(css, /@media \(max-width: 1100px\)/);
  assert.match(css, /@media \(max-width: 720px\)/);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
  assert.match(css, /font-family:\s*['"]Be Vietnam Pro['"],\s*sans-serif/);
  assert.match(css, /\.learner-page-ai \.learner-roadmap-timeline/);
  assert.match(css, /\.learner-page-ai \.learner-roadmap-radar/);
  assert.match(css, /\.learner-page-ai \.learner-roadmap-secondary/);
  assert.match(css, /@media \(max-width: 720px\)[\s\S]*grid-template-columns:\s*1fr/);
  for (const token of ['#F97316', '#EA580C', '#FFF7ED', '#2563EB', '#EFF6FF', '#16A34A']) {
    assert.match(css.toUpperCase(), new RegExp(token));
  }
});
