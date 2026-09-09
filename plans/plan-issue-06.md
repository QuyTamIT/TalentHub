# Issue 06 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Give learners a reliable view of every internship application and its lifecycle.

**Architecture:** Reuse `applications.php` GET/PATCH contracts; add a learner page with server-rendered shell and JS API controller, while preserving enterprise status ownership.

**Tech Stack:** PHP, existing application repository/API, learner JS/CSS.

## Implementation

- [x] Add API fixtures for submitted, reviewing, accepted, rejected and withdrawn records and a withdrawal authorization test.
- [x] Create `app/learner/my-applications.php` redirecting to `ecosystem.php?tab=enterprises&view=applications`; load only the authenticated learner's records from `DatabaseApplicationRepository`.
- [x] Add `assets/js/learner-applications-tracker.js` to render collapsible drawer, status timeline, enterprise/role/date/message and empty states.
- [x] Implement withdrawal confirmation and PATCH action only for withdrawable statuses; refresh the row and notification badge after success.
- [x] Add URL filter for status and verify access control, status labels, dates, withdraw behavior and mobile layout.

## Verification
- `node tests/learner_my_applications_tracker_test.js` (PASS - 4/4 tests)
- `node tests/phase7_application_ui_test.js` (PASS - 4/4 tests)
- `git diff --check` (PASS)
