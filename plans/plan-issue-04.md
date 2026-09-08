# Issue 04 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Produce a single, current A4 portrait talent passport PDF with compact, useful evidence.

**Architecture:** Build a print-specific view model with bounded sections and use print CSS plus a measured browser/PDF check. Screen layout remains unchanged.

**Tech Stack:** PHP, CSS print media, existing `window.print()` JavaScript, browser/PDF rendering.

## Implementation

- [ ] Write a fixture with long projects, skills and internships and a failing one-page PDF assertion.
- [ ] Extend `DatabaseTalentPassportRepository` aggregate with accepted/active internship fields and verified assessment/project data.
- [ ] Add a print view model selecting top 2–3 projects/internships and top 4–6 skills, with deterministic ordering and truncation.
- [ ] Update `talent-passport.php` print markup; set A4 dimensions, box sizing, overflow/page-break rules and hide workshop-only sections in print.
- [ ] Keep `prepareSinglePagePrint()` reversible and measure the actual print container before `window.print()`.
- [ ] Render PDF at 210×297 mm and verify exactly one page, no clipping, internship presence and current completed-project status.
