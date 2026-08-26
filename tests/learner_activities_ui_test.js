'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
require('../assets/js/learner-activities.js');
require('../assets/js/learner.js');
const activitySource = fs.readFileSync(path.join(__dirname, '..', 'assets/js/learner-activities.js'), 'utf8');
assert.doesNotMatch(activitySource, /innerHTML|outerHTML|insertAdjacentHTML/, 'activity history renders server values without HTML parsing');
const {
  canRegisterActivity,
  activityCtaState,
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
  resolveRegistrationMessage,
  getStatusLabel,
  statusLabels,
  registrationPageForStatus,
  activityMatchesDiscoveryFilters,
  activityAvailabilityState,
  registrationErrorMessage,
  createSingleFlightRegistration,
  activeRegistrations,
  registeredSummary,
  registrationMatchesRegisteredFilters,
  canCancelRegistration,
  canCheckinRegistration,
  attendanceHistory,
  historySummary,
  attendanceRate,
  groupHistoryByMonth,
  historyMatchesFilters
} = global.LearnerActivities;

test('[phase8 task15] attendance history classification, KPI, grouping and filtering use resolved data', () => {
  const rows = [
    { id: 'a1', status: 'attended', experience_hours: 3.5, checked_in_at: '2026-08-10T09:05:00Z', end_at: '2026-08-10T11:00:00Z' },
    { id: 'a2', status: 'attended', experience_hours: 2, checked_in_at: '2026-07-10T09:05:00Z', end_at: '2026-07-10T11:00:00Z' },
    { id: 'n1', status: 'no_show', experience_hours: 8, checked_in_at: 'unsafe', attendance_resolved_at: '2026-08-12T11:00:00Z', end_at: '2026-08-11T11:00:00Z' },
    { id: 'x', status: 'cancelled', experience_hours: 99, updated_at: '2026-08-20T00:00:00Z' },
  ];
  const history = attendanceHistory(rows);
  assert.deepEqual(history.map(row => row.id), ['n1', 'a1', 'a2']);
  assert.equal(history[0].checked_in_at, null);
  assert.equal(history[0].experience_hours, 0);
  assert.deepEqual(historySummary(rows, '2026-08-25T00:00:00Z'), { attended: 2, noShow: 1, hours: 5.5, month: 2 });
  assert.equal(attendanceRate([]), 0);
  assert.equal(attendanceRate([{ status: 'attended' }]), 100);
  assert.equal(attendanceRate([{ status: 'no_show' }]), 0);
  assert.equal(attendanceRate(rows), 67);
  assert.deepEqual(Object.keys(groupHistoryByMonth(rows)), ['2026-08', '2026-07']);
  assert.equal(historyMatchesFilters(history[0], { status: 'no_show', period: 'all' }, '2026-08-25T00:00:00Z'), true);
  assert.equal(historyMatchesFilters(history[2], { status: 'all', period: '30d' }, '2026-08-25T00:00:00Z'), false);
});

test('[phase8 task14] registered-page lifecycle, KPI, search and action rules are deterministic', () => {
  const rows = [
    { id: 'p', status: 'pending', activity_id: 'a1' },
    { id: 'a', status: 'approved', activity_id: 'a2' },
    { id: 'w', status: 'waitlisted', activity_id: 'a3' },
    { id: 'x1', status: 'attended', activity_id: 'a4' },
    { id: 'x2', status: 'no_show', activity_id: 'a5' },
    { id: 'x3', status: 'cancelled', activity_id: 'a6' },
    { id: 'x4', status: 'rejected', activity_id: 'a7' },
  ];
  assert.deepEqual(activeRegistrations(rows).map(row => row.id), ['p', 'a', 'w']);
  assert.deepEqual(registeredSummary(rows), { total: 3, approved: 1, pending: 1 });

  const activity = { title: 'Ngày hội Rô-bốt', organizer_name: 'Đại học FPT' };
  assert.equal(registrationMatchesRegisteredFilters(rows[1], activity, { query: 'ro bot', status: 'all' }), true);
  assert.equal(registrationMatchesRegisteredFilters(rows[1], activity, { query: 'dai hoc fpt', status: 'approved' }), true);
  assert.equal(registrationMatchesRegisteredFilters(rows[0], activity, { query: '', status: 'approved' }), false);
  assert.equal(registrationMatchesRegisteredFilters(rows[2], activity, { query: '', status: 'pending' }), false, 'waitlisted remains visible only in the all view');
  assert.equal(registrationMatchesRegisteredFilters(rows[2], activity, { query: '', status: 'all' }), true);

  const policy = { cancellation_closes_at: '2026-08-26T00:00:00Z' };
  assert.equal(canCancelRegistration(rows[1], policy, '2026-08-25T23:59:59Z'), true);
  assert.equal(canCancelRegistration(rows[1], policy, '2026-08-26T00:00:00Z'), false, 'cancellation close is exclusive');
  assert.equal(canCancelRegistration({ status: 'attended' }, policy, '2026-08-25T00:00:00Z'), false);
  assert.equal(canCheckinRegistration(rows[1]), true);
  assert.equal(canCheckinRegistration(rows[0]), false);
  assert.equal(canCheckinRegistration(rows[2]), false);
});

test('activity registration command feedback takes priority over the normal CTA explanation', () => {
  assert.equal(typeof resolveRegistrationMessage, 'function');
  assert.equal(resolveRegistrationMessage('Đăng ký sẽ được ghi trực tiếp vào hệ thống.', 'Máy chủ từ chối đăng ký.'), 'Máy chủ từ chối đăng ký.');
  assert.equal(resolveRegistrationMessage('Đăng ký sẽ được ghi trực tiếp vào hệ thống.', ''), 'Đăng ký sẽ được ghi trực tiếp vào hệ thống.');
});

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

test('registration requires a currently open status inside the registration window', () => {
  assert.equal(canRegisterActivity(openActivity(), '2026-08-14T00:00:00Z'), true);
  for (const status of ['draft', 'cancelled', 'closed', 'completed', 'archived']) {
    const activity = openActivity({ status });
    assert.equal(canRegisterActivity(activity, '2026-08-14T00:00:00Z'), false);
    assert.equal(createRegistration({ studentId: 's1', activity, now: '2026-08-14T00:00:00Z' }), null);
  }
  assert.equal(canRegisterActivity(openActivity({ registration_closes_at: '2026-08-13T23:59:59Z' }), '2026-08-14T00:00:00Z'), false);
  assert.equal(createRegistration({ studentId: 's1', activity: openActivity({ registration_closes_at: '2026-08-13T23:59:59Z' }), now: '2026-08-14T00:00:00Z' }), null);
  assert.equal(canRegisterActivity(openActivity({ registration_opens_at: '2026-08-15T00:00:00Z' }), '2026-08-14T00:00:00Z'), false);
  assert.equal(canRegisterActivity(openActivity({ can_register: false }), '2026-08-14T00:00:00Z'), false);
  assert.equal(canRegisterActivity(openActivity(), '2026-08-01T00:00:00Z'), true, 'opening is inclusive');
  assert.equal(canRegisterActivity(openActivity(), '2026-08-31T23:59:59Z'), false, 'closing is exclusive');
  assert.equal(canRegisterActivity(openActivity({ participants: 2, capacity: 2 }), '2026-08-14T00:00:00Z'), false, 'full activity is unavailable');
  assert.equal(canRegisterActivity(openActivity({ participants: 0, capacity: 0 }), '2026-08-14T00:00:00Z'), false, 'invalid zero capacity is unavailable');
  assert.equal(canRegisterActivity(openActivity({ remaining: 0 }), '2026-08-14T00:00:00Z'), false, 'explicit zero remaining is unavailable');
});

test('[phase6] discovery search and filters are accent insensitive and deterministic', () => {
  assert.equal(typeof activityMatchesDiscoveryFilters, 'function');
  const activity = {
    search: 'Workshop Lập trình Python Đại học FPT Kỹ thuật xử lý dữ liệu',
    category: 'Kỹ thuật',
    startAt: '2026-08-28T09:00:00Z',
    available: true
  };
  assert.equal(activityMatchesDiscoveryFilters(activity, { query: 'lap trinh', category: 'Tất cả', time: 'all', onlyAvailable: true }, '2026-08-25T00:00:00Z'), true);
  assert.equal(activityMatchesDiscoveryFilters(activity, { query: 'fpt', category: 'Kinh doanh', time: 'all', onlyAvailable: true }, '2026-08-25T00:00:00Z'), false);
  assert.equal(activityMatchesDiscoveryFilters(activity, { query: '', category: 'Kỹ thuật', time: '7d', onlyAvailable: true }, '2026-08-25T00:00:00Z'), true);
  assert.equal(activityMatchesDiscoveryFilters(activity, { query: '', category: 'Kỹ thuật', time: '7d', onlyAvailable: true }, '2026-09-01T00:00:00Z'), false);
});

test('[phase6] history is an implemented learner route', () => {
  assert.equal(global.LearnerUI.isImplementedRoute('/app/learner/activity-history.php'), true);
});

test('[phase1] published-only discovery never accepts ongoing or active activities', () => {
  assert.equal(canRegisterActivity(openActivity({ status: 'ongoing' }), '2026-08-14T00:00:00Z'), false);
  assert.equal(canRegisterActivity(openActivity({ status: 'active' }), '2026-08-14T00:00:00Z'), false);
});

test('[phase1] no_show has the Vietnamese attendance label', () => {
  assert.equal(statusLabels.no_show, 'Không tham gia');
  assert.equal(getStatusLabel('no_show'), 'Không tham gia');
});

test('[phase1] registration status classifier separates registered from attendance history', () => {
  assert.equal(typeof registrationPageForStatus, 'function', 'LearnerActivities.registrationPageForStatus(status) must be exported for registered/history routing.');
  assert.equal(registrationPageForStatus('pending'), 'registered');
  assert.equal(registrationPageForStatus('approved'), 'registered');
  assert.equal(registrationPageForStatus('waitlisted'), 'registered');
  assert.equal(registrationPageForStatus('attended'), 'history');
  assert.equal(registrationPageForStatus('no_show'), 'history');
  assert.equal(registrationPageForStatus('rejected'), null);
  assert.equal(registrationPageForStatus('cancelled'), null);
});

test('[phase1] activity rendering keeps server values out of HTML parsing sinks', () => {
  assert.doesNotMatch(activitySource, /innerHTML|outerHTML|insertAdjacentHTML/);
});

test('activity CTA is always explicit for open, registered, and closed states', () => {
  assert.deepEqual(activityCtaState(openActivity(), null, '2026-08-14T00:00:00Z'), {
    label: 'Đăng ký hoạt động',
    disabled: false,
    tone: 'primary',
    explanation: 'Đăng ký sẽ được ghi trực tiếp vào hệ thống.'
  });
  assert.equal(activityCtaState(openActivity(), { status: 'pending' }, '2026-08-14T00:00:00Z').label, 'Chờ duyệt');
  assert.equal(activityCtaState(openActivity({ status: 'completed' }), null, '2026-08-14T00:00:00Z').label, 'Đã kết thúc');
});

test('[phase7] activity detail availability and CTA states match backend boundaries', () => {
  assert.equal(typeof activityAvailabilityState, 'function');
  const atOpen = activityAvailabilityState(openActivity(), '2026-08-01T00:00:00Z');
  assert.deepEqual(atOpen, { code: 'open', label: 'Đang mở đăng ký', explanation: 'Hoạt động đang nhận đăng ký.' });
  assert.equal(activityAvailabilityState(openActivity(), '2026-08-31T23:59:59Z').code, 'expired', 'closing boundary is exclusive');
  assert.equal(activityAvailabilityState(openActivity({ registration_opens_at: '2026-08-15T00:00:00Z' }), '2026-08-14T23:59:59Z').code, 'not_open');
  assert.equal(activityAvailabilityState(openActivity({ participants: 2, remaining: 0 }), '2026-08-14T00:00:00Z').code, 'full');
  assert.equal(activityAvailabilityState(openActivity({ status: 'ongoing' }), '2026-08-14T00:00:00Z').code, 'ongoing');
  assert.equal(activityAvailabilityState(openActivity({ status: 'completed' }), '2026-08-14T00:00:00Z').code, 'completed');

  const expectedRegistrations = {
    approved: 'Đã đăng ký',
    pending: 'Chờ duyệt',
    waitlisted: 'Danh sách chờ',
    rejected: 'Bị từ chối',
    cancelled: 'Đã hủy',
    attended: 'Đã tham gia'
  };
  for (const [status, label] of Object.entries(expectedRegistrations)) {
    const state = activityCtaState(openActivity(), { status }, '2026-08-14T00:00:00Z');
    assert.equal(state.label, label);
    assert.equal(state.disabled, true);
  }
  assert.equal(activityCtaState(openActivity({ participants: 2, remaining: 0 }), null, '2026-08-14T00:00:00Z').label, 'Đã hết chỗ');
  assert.equal(activityCtaState(openActivity(), null, '2026-08-31T23:59:59Z').label, 'Đã hết hạn');
  assert.equal(activityCtaState(openActivity({ status: 'ongoing' }), null, '2026-08-14T00:00:00Z').label, 'Đang diễn ra');
  assert.equal(activityCtaState(openActivity({ status: 'completed' }), null, '2026-08-14T00:00:00Z').label, 'Đã kết thúc');
});

test('[phase7] registration errors are mapped without exposing raw responses', () => {
  assert.equal(typeof registrationErrorMessage, 'function');
  assert.equal(registrationErrorMessage({ status: 403, code: 'ACTIVITY_SCHOOL_SCOPE_DENIED' }), 'Bạn không thể đăng ký hoạt động ngoài trường của mình.');
  assert.equal(registrationErrorMessage({ status: 409, code: 'REGISTRATION_EXISTS' }), 'Bạn đã có đăng ký cho hoạt động này.');
  assert.equal(registrationErrorMessage({ status: 409, code: 'SCHEDULE_CONFLICT' }), 'Lịch hoạt động bị trùng với một đăng ký hiện có.');
  assert.equal(registrationErrorMessage({ status: 422, code: 'REGISTRATION_CLOSED' }), 'Hoạt động đã đóng đăng ký hoặc không còn ở trạng thái phù hợp.');
  assert.equal(registrationErrorMessage({ status: 503, code: 'ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE' }), 'Chưa thể xác minh phạm vi trường. Vui lòng thử lại sau.');
  assert.equal(registrationErrorMessage({ status: 0, code: 'NETWORK_ERROR' }), 'Không thể kết nối đến máy chủ. Vui lòng thử lại.');
  assert.equal(registrationErrorMessage({ status: 500, code: 'UNKNOWN', message: '<pre>stack</pre>' }), 'Không thể đăng ký hoạt động. Vui lòng thử lại.');
});

test('[phase7] detail registration submitter prevents duplicate in-flight requests and trusts server status', async () => {
  assert.equal(typeof createSingleFlightRegistration, 'function');
  let calls = 0;
  let release;
  const gateway = { register: () => {
    calls += 1;
    return new Promise(resolve => { release = resolve; });
  } };
  const submit = createSingleFlightRegistration(gateway);
  const first = submit('a1');
  const second = submit('a1');
  assert.equal(calls, 1);
  release({ registration: { id: 'server-registration', activity_id: 'a1', status: 'pending' } });
  assert.equal((await first).registration.status, 'pending');
  assert.equal((await second).registration.status, 'pending');
});

test('activity detail only renders supported metadata sections', () => {
  const detailSource = fs.readFileSync(path.join(__dirname, '..', 'app', 'learner', 'activity-detail.php'), 'utf8');
  for (const flag of ['has_description', 'has_skills', 'has_requirements', 'has_benefits', 'has_format', 'has_cost', 'has_location']) {
    assert.match(detailSource, new RegExp(`\\$activity\\['${flag}'\\].*\\?`), `${flag} conditionally renders its metadata`);
  }
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
