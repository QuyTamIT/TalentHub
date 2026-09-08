# Issue 08 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Allow early retakes with an explicit 90-day warning while preserving complete attempt history.

**Architecture:** The first request returns a confirmation-required response; a second request with explicit confirmation creates a new attempt. Latest submitted attempt remains current and prior attempts remain immutable.

**Tech Stack:** PHP/PDO, assessment repository/API, learner result view and JavaScript.

## Implementation

- [ ] Add failing tests for under-90-day response, confirmed early retake, 90-day retake and historical attempt ordering.
- [ ] Add `assessment_attempts` migration or map to the existing attempt table with learner, assessment type, submitted time, result payload and superseded/current markers.
- [ ] Change `DatabaseAssessmentWriteRepository` to return `retake_confirmation_required` with elapsed/remaining days; accept `confirm_early_retake=true` only after rechecking ownership and latest attempt.
- [ ] Update `assessment-result.php` and `assets/js/learner-assessment.js` to show the Vietnamese warning modal, preserve old result on cancel and submit confirmation on continue.
- [ ] Add history endpoint/view if the schema supports it; ensure AI/profile reads only the newest submitted result.
- [ ] Verify concurrency, authorization, exact 90-day boundary, cancel flow, latest-result selection and regression of all four DISC/MBTI/Holland/MI tests.
