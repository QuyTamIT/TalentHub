# Issue 03 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Let learners filter projects and internship opportunities by lifecycle and personal participation state.

**Architecture:** Repository returns canonical status fields; `ecosystem.php` applies URL-preserved filters server-side and the existing JS applies search/category/status together.

**Tech Stack:** PHP/PDO, learner views, existing ecosystem JavaScript/CSS.

## Implementation

- [ ] Write repository tests for `all`, `recruiting`, `active`, `completed`, including a completed project and a non-member.
- [ ] Remove the hard `p.status='in_progress'` restriction in `DatabaseProjectRepository`; left join the learner's membership and return `is_member` and normalized `membership_status`.
- [ ] Add opportunity/application join using the existing application schema and map `open`, `applied`, `interning`, `completed` without exposing enterprise-only records.
- [ ] Validate filter query parameters in `ecosystem.php`, render count badges and `data-status` attributes, and preserve `tab` plus `filter` on reload.
- [ ] Update client filtering and empty states; ensure cards link to the same detail pages.
- [ ] Verify SQL visibility, URL reload, combined search/category/status filtering, counts and no regression in project registration.
