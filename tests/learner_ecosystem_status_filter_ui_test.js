import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

test('ecosystem status filter is rendered, URL-persisted, and applied to cards', () => {
  const php = fs.readFileSync('app/learner/ecosystem.php', 'utf8');
  const js = fs.readFileSync('assets/js/learner.js', 'utf8');
  for (const value of ['recruiting', 'active', 'completed']) assert.match(php, new RegExp(value));
  assert.match(php, /data-status=/);
  assert.match(js, /filters\.status/);
  assert.match(js, /searchParams\.set\('filter'/);
});
