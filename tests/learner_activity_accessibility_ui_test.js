'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const pages = {
  dashboard: read('app/learner/index.php'),
  discover: read('app/learner/activities.php'),
  detail: read('app/learner/activity-detail.php'),
  registered: read('app/learner/my-activities.php'),
  history: read('app/learner/activity-history.php')
};
const navigation = read('app/learner/includes/activity-navigation.php');
const css = read('app/learner/assets/activities/activities.css');
const javascript = read('assets/js/learner-activities.js');

test('dashboard and four activity routes retain semantic headings, labelled landmarks and meaningful images', () => {
  for (const [route, source] of Object.entries(pages)) {
    assert.equal((source.match(/<h1\b/g) || []).length >= 1, true, `${route} has an h1`);
    assert.match(source, /<main\b[^>]*id="main-content"/s, `${route} has the main landmark target`);
    for (const fragment of source.split(/<img\b/g).slice(1)) {
      assert.match(fragment.slice(0, 600), /\balt="[^"]*"/, `${route} image has an explicit alt contract`);
    }
    const ids = [...source.matchAll(/\bid="([^"]+)"/g)].map(match => match[1]);
    if (route !== 'dashboard') {
      assert.equal(new Set(ids).size, ids.length, `${route} has no duplicate literal id`);
    }
  }
});

test('activity navigation and interactive filters expose current state', () => {
  assert.match(navigation, /aria-label="Điều hướng Hoạt động"/);
  assert.match(navigation, /aria-current="page"/);
  assert.match(pages.discover, /data-activity-filter[\s\S]*aria-pressed=/);
  assert.match(pages.registered, /data-registration-filter=[\s\S]*aria-pressed=/);
  assert.match(pages.history, /data-history-filter=[\s\S]*aria-pressed=/);
  assert.match(javascript, /setAttribute\('aria-pressed'/);
  assert.match(pages.registered, /aria-current="step"/, 'registration stepper announces its current step');
  assert.match(pages.history, /<span aria-current="page">Lịch sử<\/span>/, 'history breadcrumb announces the current page');
});

test('search, status, progress, timeline and donut have non-color accessible text', () => {
  assert.match(pages.discover, /type="search"[\s\S]*data-activity-search-input/);
  assert.match(pages.registered, /type="search"[^>]*aria-label=/);
  assert.match(pages.discover, /role="status" aria-live="polite"/);
  assert.match(pages.detail, /role="status" aria-live="polite"/);
  assert.match(pages.registered, /role="status" aria-live="polite"/);
  for (const [route, source] of [['discover', pages.discover], ['detail', pages.detail]]) {
    const progress = source.split(/<progress\b/)[1]?.slice(0, 800) || '';
    assert.match(progress, /\bvalue=/, `${route} progress has a value`);
    assert.match(progress, /\bmax=/, `${route} progress has a maximum`);
    assert.match(progress, /\baria-label=/, `${route} progress has an accessible label`);
  }
  assert.match(pages.registered, /<ol[^>]*aria-label="Tiến trình đăng ký"/);
  assert.match(pages.history, /Không tham gia/);
  assert.match(pages.history, /role="img" aria-label="Tỷ lệ tham gia/);
  assert.match(pages.history, /<dt><span class="is-attended"><\/span>Đã tham gia<\/dt>[\s\S]*<dt><span class="is-no-show"><\/span>Không tham gia<\/dt>/);
});

test('focus, touch, responsive overflow and reduced motion contracts are explicit', () => {
  assert.match(css, /:focus-visible/);
  assert.match(css, /min-height:\s*44px/);
  assert.match(css, /overflow-x:\s*auto/);
  for (const width of ['1024px', '768px', '390px']) {
    assert.match(css, new RegExp(`@media \\(max-width: ${width.replace('.', '\\.')}\\)`));
  }
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)/);
});
