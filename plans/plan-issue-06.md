# Issue 06 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Give learners a reliable view of every internship application and its lifecycle.

**Architecture:** Reuse `applications.php` GET/PATCH contracts; add a learner page with server-rendered shell and JS API controller, while preserving enterprise status ownership.

**Tech Stack:** PHP, existing application repository/API, learner JS/CSS.

## Implementation

- [ ] Add API fixtures for submitted, reviewing, accepted, rejected and withdrawn records and a withdrawal authorization test.
- [ ] Create `app/learner/my-applications.php` and navigation entry; load only the authenticated learner's records from `DatabaseApplicationRepository`.
- [ ] Add `assets/js/learner-applications.js` to render status timeline, enterprise/role/date/message and retry/empty states.
- [ ] Implement withdrawal confirmation and PATCH action only for withdrawable statuses; refresh the row and notification badge after success.
- [ ] Add URL filter for status and verify access control, status labels, dates, withdraw behavior and mobile layout.
