const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { test } = require('node:test');

const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'assets/js/learner-activities.js'), 'utf8');

function control(value = '', dataset = {}) {
  return {
    value, dataset, hidden: false, textContent: '', listeners: {},
    addEventListener(type, listener) { this.listeners[type] = listener; },
    setAttribute() {},
    fire(type) { this.listeners[type](); }
  };
}

function mountDiscovery() {
  const startAt = days => new Date(Date.now() + days * 86400000).toISOString();
  const cards = [
    control('', { activitySearch: 'Lập trình', filterCategory: 'Kỹ thuật', available: 'true', startAt: startAt(2) }),
    control('', { activitySearch: 'Khởi nghiệp', filterCategory: 'Kinh doanh', available: 'true', startAt: startAt(15) }),
    control('', { activitySearch: 'Lập trình hết hạn', filterCategory: 'Kỹ thuật', available: 'false', startAt: startAt(2) }),
    control('', { activitySearch: 'Khởi nghiệp đầy chỗ', filterCategory: 'Kinh doanh', available: 'false', startAt: startAt(15) })
  ];
  const search = control();
  const time = control('all');
  const status = control();
  const empty = control();
  const categories = ['Tất cả', 'Kỹ thuật', 'Kinh doanh'].map(activityFilter => control('', { activityFilter }));
  const nodes = {
    '[data-activity-search-input]': search,
    '[data-activity-time-filter]': time,
    '[data-activity-result-status]': status,
    '[data-activity-filter-empty]': empty
  };
  const discovery = {
    querySelector: selector => nodes[selector] || null,
    querySelectorAll: selector => selector === '[data-activity-card]' ? cards : selector === '[data-activity-filter]' ? categories : []
  };
  let ready;
  const document = {
    addEventListener(type, listener) { if (type === 'DOMContentLoaded') ready = listener; },
    querySelector: selector => selector === '[data-activity-discovery-page]' ? discovery : null,
    getElementById: () => null
  };
  vm.runInNewContext(script, { document, console });
  ready();
  return { cards, search, time, status, empty, categories };
}

function visibleCards(ui) {
  return ui.cards.map((card, index) => card.hidden ? null : index).filter(index => index !== null);
}

test('discovery markup no longer offers an availability toggle or its label', () => {
  const php = fs.readFileSync(path.join(root, 'app/learner/activities.php'), 'utf8');
  assert(!php.includes('data-activity-availability-filter'));
  assert(!php.includes('Chỉ hiển thị hoạt động còn hạn và còn chỗ'));
});

test('initial render without a toggle hides unavailable cards', () => {
  const ui = mountDiscovery();
  assert.deepEqual(visibleCards(ui), [0, 1]);
  assert.equal(ui.status.textContent, '2 hoạt động phù hợp');
  assert.equal(ui.empty.hidden, true);
});

test('search and category changes retain the permanent availability filter', () => {
  const ui = mountDiscovery();
  ui.search.value = 'lap trinh';
  ui.search.fire('input');
  assert.deepEqual(visibleCards(ui), [0]);
  ui.search.value = '';
  ui.search.fire('input');
  ui.categories[2].fire('click');
  assert.deepEqual(visibleCards(ui), [1]);
  ui.categories[0].fire('click');
  assert.deepEqual(visibleCards(ui), [0, 1]);
  ui.search.value = 'đầy chỗ';
  ui.search.fire('input');
  assert.deepEqual(visibleCards(ui), []);
  assert.equal(ui.status.textContent, '0 hoạt động phù hợp');
  assert.equal(ui.empty.hidden, false);
});

test('time filter changes retain the permanent availability filter', () => {
  const ui = mountDiscovery();
  ui.time.value = '7d';
  ui.time.fire('change');
  assert.deepEqual(visibleCards(ui), [0]);
  ui.time.value = '30d';
  ui.time.fire('change');
  assert.deepEqual(visibleCards(ui), [0, 1]);
  ui.time.value = 'all';
  ui.time.fire('change');
  assert.deepEqual(visibleCards(ui), [0, 1]);
});
