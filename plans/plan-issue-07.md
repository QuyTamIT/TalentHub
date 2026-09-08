# Issue 07 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Show verified internship experience on the learner profile and provide a controlled project submission/acceptance workflow.

**Architecture:** Internship profile data comes from accepted/active/completed applications. Project submissions are separate versioned records, authorized by active members and reviewed by the mentor.

**Tech Stack:** PHP/PDO, learner APIs/views, notification service, migration, JS/CSS.

## Implementation

- [ ] Write authorization tests for active member submit/update and non-member rejection; add internship aggregate fixture.
- [ ] Create `project_submissions` migration with project/member/team identity, repository URL, demo URL, notes, submitted/reviewed timestamps, status and reviewer feedback; index project/status.
- [ ] Add learner repository/service/API methods for create/update/submit-for-review and mentor accept/request-changes; validate URL schemes and ownership server-side.
- [ ] Add submission form/status card to `app/learner/project.php` and mentor notification; prevent edits after accepted unless a new revision is created.
- [ ] Add internships to profile/talent-passport read model and render company, role, dates, hours and employer feedback in `app/learner/profile.php`.
- [ ] Verify member permissions, transitions, persistence, profile output, notification, XSS-safe URLs and existing project registration.
