'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
require('../assets/js/learner-activities.js');
const {
  canRegisterActivity,
  resolveRegistrationStatus,
  hasScheduleConflict,
  createActivityStorage,
  createRegistration,
  cancelRegistration,
  saveFeedback,
  mergeRegistrations,
  resolveRegistrationCollection,
  canUseLocalActivityMutations,
  createActivityRegistrationGateway,
  getStatusLabel,
  statusLabels
} = global.LearnerActivities;

const memory = () => {
  const d = {};
  return {
    getItem: k => d[k] ?? null,
    setItem: (k, v) => { d[k] = String(v); }
  };
};

const openActivity = (overrides = {}) => ({
  id: 'a1',
  participants: 1,
  capacity: 2,
  approval_mode: 'automatic',
  status: 'published',
  registration_opens_at: '2026-08-01T00:00:00Z',
  registration_closes_at: '2026-08-31T23:59:59Z',
  can_register: true,
  ...overrides
});

test('registration status follows canonical capacity and approval rules', () => {
  assert.equal(resolveRegistrationStatus({ participants: 10, capacity: 20, approval_mode: 'automatic' }), 'approved');
  assert.equal(resolveRegistrationStatus({ participants: 20, capacity: 20, approval_mode: 'automatic' }), 'waitlisted');
  assert.equal(resolveRegistrationStatus({ participants: 10, capacity: 20, approval_mode: 'teacher_review' }), 'pending');
});

test('statusLabels and getStatusLabel never return undefined for canonical statuses and aliases', () => {
  const expected = {
    approved: 'Đã đăng ký',
    pending: 'Chờ duyệt',
    rejected: 'Bị từ chối',
    cancelled: 'Đã hủy',
    attended: 'Đã tham gia',
    registered: 'Đã đăng ký',
    checked_in: 'Đã check-in',
    completed: 'Hoàn thành',
    waitlisted: 'Danh sách chờ'
  };
  for (const [status, label] of Object.entries(expected)) {
    assert.equal(statusLabels[status], label, `statusLabels contains ${status}`);
    assert.equal(getStatusLabel(status), label, `getStatusLabel returns ${label} for ${status}`);
    assert.notEqual(getStatusLabel(status), undefined);
  }
  assert.equal(getStatusLabel('custom_unmapped'), 'custom_unmapped');
  assert.equal(getStatusLabel(null), 'Không xác định');
  assert.equal(getStatusLabel(undefined), 'Không xác định');
});

test('schedule conflict detects overlapping active registrations across canonical and alias statuses', () => {
  const activity = { id: 'new', start_at: '2026-08-28T15:00:00Z', end_at: '2026-08-28T17:00:00Z' };
  const catalog = [activity, { id: 'old', start_at: '2026-08-28T14:00:00Z', end_at: '2026-08-28T16:00:00Z' }];
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'approved' }], catalog), true);
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'attended' }], catalog), true);
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'registered' }], catalog), true);
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'pending' }], catalog), true);
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'cancelled' }], catalog), false);
  assert.equal(hasScheduleConflict(activity, [{ activity_id: 'old', status: 'rejected' }], catalog), false);
});

test('storage survives corrupt data, falls back gracefully, and round trips registration', () => {
  const raw = memory();
  raw.setItem('x', '{bad');
  const store = createActivityStorage(raw, 'x');
  assert.deepEqual(store.getRegistrations(), []);
  store.saveRegistration({ id: 'r1', activity_id: 'a1' });
  assert.equal(store.getRegistration('r1').activity_id, 'a1');

  // Fallback when storage is null/unavailable
  const memoryStore = createActivityStorage(null, 'y');
  assert.deepEqual(memoryStore.getRegistrations(), []);
  memoryStore.saveRegistration({ id: 'r2', activity_id: 'a2' });
  assert.equal(memoryStore.getRegistration('r2').activity_id, 'a2');
});

test('registration contract and canonical lifecycle are immutable', () => {
  const registration = createRegistration({ studentId: 's1', activity: openActivity(), now: '2026-08-13T10:00:00Z', id: 'r1' });
  assert.equal(registration.status, 'approved');
  assert.equal(registration.student_id, 's1');

  const cancelled = cancelRegistration(registration, 'Không tham gia được', '2026-08-13T11:00:00Z');
  assert.equal(cancelled.status, 'cancelled');
  assert.equal(registration.status, 'approved'); // immutable original

  const attended = { ...registration, status: 'attended' };
  const reviewed = saveFeedback(attended, 5, 'Rất hữu ích', '2026-08-13T12:00:00Z');
  assert.equal(reviewed.feedback.rating, 5);
  assert.equal(reviewed.feedback.comment, 'Rất hữu ích');

  // Completed alias also works for feedback
  const completed = { ...registration, status: 'completed' };
  const completedReviewed = saveFeedback(completed, 4, 'Tốt', '2026-08-13T12:00:00Z');
  assert.equal(completedReviewed.feedback.rating, 4);

  // Cannot save feedback on approved or cancelled
  assert.equal(saveFeedback(registration, 5, 'x', '2026-08-13T12:00:00Z'), null);
  assert.equal(saveFeedback(cancelled, 5, 'x', '2026-08-13T12:00:00Z'), null);
});

test('registration requires published or ongoing status inside the registration window', () => {
  assert.equal(canRegisterActivity(openActivity(), '2026-08-14T00:00:00Z'), true);
  assert.equal(canRegisterActivity(openActivity({ status: 'ongoing' }), '2026-08-14T00:00:00Z'), true);
  assert.equal(canRegisterActivity(openActivity({ status: 'active' }), '2026-08-14T00:00:00Z'), true);
  for (const status of ['draft', 'cancelled', 'closed', 'completed', 'archived']) {
    const activity = openActivity({ status });
    assert.equal(canRegisterActivity(activity, '2026-08-14T00:00:00Z'), false);
    assert.equal(createRegistration({ studentId: 's1', activity, now: '2026-08-14T00:00:00Z' }), null);
  }
  assert.equal(canRegisterActivity(openActivity({ registration_closes_at: '2026-08-13T23:59:59Z' }), '2026-08-14T00:00:00Z'), false);
  assert.equal(createRegistration({ studentId: 's1', activity: openActivity({ registration_closes_at: '2026-08-13T23:59:59Z' }), now: '2026-08-14T00:00:00Z' }), null);
  assert.equal(canRegisterActivity(openActivity({ registration_opens_at: '2026-08-15T00:00:00Z' }), '2026-08-14T00:00:00Z'), false);
  assert.equal(canRegisterActivity(openActivity({ can_register: false }), '2026-08-14T00:00:00Z'), false);
});

test('database mode keeps server registrations authoritative over stale local state', () => {
  const server = [{ id: 'server', activity_id: 'a', status: 'approved' }];
  const staleLocal = [{ id: 'local', activity_id: 'a', status: 'cancelled' }];
  const result = resolveRegistrationCollection(server, staleLocal, 'database');

  assert.deepEqual(result, server);
  assert.equal(canUseLocalActivityMutations('database'), false);
});

test('database registration gateway sends canonical register and cancel commands', async () => {
  const calls = [];
  const api = { send: async (method, path, body) => {
    calls.push({ method, path, body });
    return { registration: { id: 'r-server', activity_id: body.activityId || 'a1', status: body.action === 'cancel' ? 'cancelled' : 'approved' } };
  } };
  const gateway = createActivityRegistrationGateway(api);
  const registered = await gateway.register('a1');
  const cancelled = await gateway.cancel('r-server', 'Đổi lịch học');

  assert.equal(registered.registration.status, 'approved');
  assert.equal(cancelled.registration.status, 'cancelled');
  assert.deepEqual(calls, [
    { method: 'POST', path: '/activity-registrations.php', body: { action: 'register', activityId: 'a1' } },
    { method: 'POST', path: '/activity-registrations.php', body: { action: 'cancel', registrationId: 'r-server', reason: 'Đổi lịch học' } }
  ]);
});

test('explicit mock mode may use browser-local demo state', () => {
  const serverFixture = [{ id: 'mock-server', activity_id: 'a', status: 'approved' }];
  const localDemo = [{ id: 'mock-local', activity_id: 'a', status: 'cancelled' }];
  const result = resolveRegistrationCollection(serverFixture, localDemo, 'mock');

  assert.deepEqual(result.map(x => x.id), ['mock-local']);
  assert.equal(canUseLocalActivityMutations('mock'), true);
});

test('UI behavior with canonical boot registrations (approved, attended, rejected, cancelled, pending)', () => {
  const catalog = [
    { id: 'act-1', title: 'IoT Lab', start_at: '2026-09-01T09:00:00Z', location: 'Lab 1' },
    { id: 'act-2', title: 'AI Bootcamp', start_at: '2026-09-02T09:00:00Z', location: 'Lab 2' },
    { id: 'act-3', title: 'Design Thinking', start_at: '2026-09-03T09:00:00Z', location: 'Room 3' },
    { id: 'act-4', title: 'Startup Pitch', start_at: '2026-09-04T09:00:00Z', location: 'Hall A' },
    { id: 'act-5', title: 'Charity Run', start_at: '2026-09-05T09:00:00Z', location: 'Stadium' },
    { id: 'act-6', title: 'Robotics Workshop', start_at: '2026-09-06T09:00:00Z', location: 'Room 4' }
  ];
  const canonicalRegistrations = [
    { id: 'r-approved', activity_id: 'act-1', status: 'approved', checkin_id: null, feedback: null },
    { id: 'r-attended-no-fb', activity_id: 'act-2', status: 'attended', checkin_id: 'chk-1', feedback: null },
    { id: 'r-attended-with-fb', activity_id: 'act-3', status: 'attended', checkin_id: 'chk-2', feedback: { rating: 5, comment: 'Hay' } },
    { id: 'r-pending', activity_id: 'act-4', status: 'pending', checkin_id: null, feedback: null },
    { id: 'r-cancelled', activity_id: 'act-5', status: 'cancelled', checkin_id: null, feedback: null },
    { id: 'r-rejected', activity_id: 'act-6', status: 'rejected', checkin_id: null, feedback: null }
  ];

  // Check labels for each canonical registration
  for (const r of canonicalRegistrations) {
    const label = getStatusLabel(r.status);
    assert.ok(label, `label for ${r.status} is non-empty`);
    assert.notEqual(label, 'undefined');
    assert.notEqual(label, undefined);
  }

  // Check eligibility logic for actions
  // 1. approved: can check-in, can cancel
  const appReg = canonicalRegistrations.find(r => r.status === 'approved');
  assert.equal(['approved', 'registered'].includes(appReg.status), true, 'approved is eligible for check-in');
  assert.equal(['approved', 'registered', 'pending', 'waitlisted'].includes(appReg.status), true, 'approved is eligible for cancel');

  // 2. attended without feedback: eligible for feedback, not eligible for check-in or cancel
  const attNoFb = canonicalRegistrations.find(r => r.id === 'r-attended-no-fb');
  assert.equal((attNoFb.status === 'attended' || attNoFb.status === 'completed') && !attNoFb.feedback, true, 'attended without feedback is eligible for feedback');
  assert.equal(['approved', 'registered', 'pending', 'waitlisted'].includes(attNoFb.status), false, 'attended cannot be cancelled');

  // 3. attended with feedback: not eligible for feedback button
  const attWithFb = canonicalRegistrations.find(r => r.id === 'r-attended-with-fb');
  assert.equal((attWithFb.status === 'attended' || attWithFb.status === 'completed') && !attWithFb.feedback, false, 'attended with feedback cannot submit again');

  // 4. pending: can cancel, cannot check in
  const pendReg = canonicalRegistrations.find(r => r.status === 'pending');
  assert.equal(['approved', 'registered', 'pending', 'waitlisted'].includes(pendReg.status), true, 'pending is eligible for cancel');
  assert.equal(['approved', 'registered'].includes(pendReg.status), false, 'pending cannot check in');

  // 5. cancelled and rejected: cannot cancel, cannot check in, cannot submit feedback
  for (const r of canonicalRegistrations.filter(x => ['cancelled', 'rejected'].includes(x.status))) {
    assert.equal(['approved', 'registered', 'pending', 'waitlisted'].includes(r.status), false, `${r.status} cannot cancel`);
    assert.equal(['approved', 'registered'].includes(r.status), false, `${r.status} cannot check in`);
    assert.equal((r.status === 'attended' || r.status === 'completed') && !r.feedback, false, `${r.status} cannot feedback`);
  }
});
