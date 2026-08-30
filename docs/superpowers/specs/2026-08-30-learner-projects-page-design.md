# Learner Projects Page Design

**Date:** 2026-08-30  
**Status:** Approved  
**Scope:** Learner-facing `Hệ sinh thái & Cơ hội` page, project cards, project detail, and AI project links.

## Goal

Turn the learner's current `Cơ hội` tab into a project-only experience. The page must help a learner discover active projects from their own school and open a complete TalentHub project detail page instead of being sent directly to GitHub.

The change removes internship/application positions from this interface only. It does not delete internship records or remove existing application routes elsewhere in TalentHub.

## Confirmed Product Decisions

- Rename the learner tab from `Cơ hội` to `Dự án`.
- Display only projects on this tab; do not render internship/application-position cards.
- Scope visible projects to the signed-in learner's school.
- Show projects only when their current status is `in_progress`.
- Use only `Dự án` as the type label. Do not add a required `Trường` or `Doanh nghiệp` source badge to project cards.
- Keep the current schema: projects are school-owned and may have enterprise sponsorships. Do not invent enterprise-owned projects from sponsorship data.
- If the data model later exposes a genuine enterprise-owned project, the learner page may render it as a normal `Dự án`; the current change does not add that ownership model.
- When a paid sponsorship exists for a school project, show the enterprise and sponsorship/funding information in the internal project detail page.
- Preserve all internship data and existing application functionality outside this page.
- Make the internal TalentHub project detail page the canonical destination for both normal cards and AI recommendations.
- Do not render the repository/GitHub demo URL in the learner interface.

For the Nguyễn Hoài An learner account, the current expected page contains the two `in_progress` projects belonging to FPT Polytechnic. The tab count must be derived from the database rather than hard-coded.

## Page Structure

The existing learner ecosystem header and top-level `Doanh nghiệp` tab remain unchanged. The second tab becomes `Dự án` and displays the number of projects available to the current learner.

The project toolbar contains:

1. A text search with project-focused placeholder copy.
2. A field/category filter.
3. A project status filter. The default selection is the active state shown to learners.
4. The existing AI action, renamed to `AI gợi ý dự án phù hợp`.

The location filter is removed because the current project schema has no reliable project-location field. No artificial location value will be inferred from the school or sponsor.

Below the toolbar, the page displays a responsive project grid. The empty state explains that the learner's school currently has no active projects. It must not fall back to showing internships.

## Project Card

Each project card displays:

- `Dự án` type badge.
- Project status, rendered as learner-facing Vietnamese text.
- Project title.
- School name.
- Project category or field when present.
- Current member count.
- Timeline or end date when available.
- A primary `Xem dự án` action that opens the internal learner project detail page.

Missing optional fields are omitted without placeholders that could be mistaken for real data.

## Data and Authorization

A learner-specific read model loads projects using the authenticated learner context. The query must:

- Require a valid learner/student identity.
- Resolve the learner's `schoolId` from the authenticated profile.
- Filter `projects.schoolId` to that school.
- Include only `projects.status = 'in_progress'` for the listing.
- Aggregate project member counts without multiplying rows when multiple sponsorships exist.
- Include only paid sponsorships in the project-detail `Doanh nghiệp đồng hành/Tài trợ` section.
- Resolve active enterprise display names through the sponsorship relationship.

The project detail page repeats the school-scope authorization check. A learner cannot view another school's project by changing the project identifier in the URL. Missing, archived, draft, or unauthorized projects return the standard learner not-found/forbidden experience without exposing project data.

## Internal Project Detail

Add a read-only learner project detail page that follows the visual structure of the existing learner opportunity detail page while using project-specific content. It includes:

- Breadcrumbs back to `Hệ sinh thái & Cơ hội` and `Dự án`.
- Project type, status, title, and school.
- Description, objectives, category, mentor, dates, member count, and funding summary where available.
- A distinct `Doanh nghiệp đồng hành/Tài trợ` section when paid sponsorship information is present.

No project-join or application button is introduced because the current data model has no approved learner self-enrollment workflow. The page must not display a disabled action that implies such a workflow exists.

The repository/GitHub demo URL is not rendered in the learner interface. It remains non-canonical internal data and cannot replace the internal project-detail action.

## AI Recommendation Integration

The existing AI project matching, scoring, explanation, generation, loading, consent, and error flows remain unchanged. The only required behavior change is the action destination: `Xem dự án` must open the matching internal learner project detail route.

Persisted or newly generated matches must not make a GitHub repository the primary project action. Where a persisted match references a known project identifier, the application resolves the current internal canonical URL before rendering. An unknown or unauthorized project is not rendered as a clickable recommendation.

The AI panel remains project-only and uses the label `Top dự án AI đề xuất cho bạn`.

## Filtering Behavior

Search matches project title, description, category, and school name using the existing accent-insensitive learner search behavior where available.

The category filter uses categories actually present in the visible project set. The status filter uses learner-facing status labels supported by the listing policy. Filters operate together and update both the visible cards and result count. Resetting filters restores all visible school projects.

## Failure and Empty States

- If no active same-school projects exist, render a project-specific empty state and keep the toolbar usable.
- If project loading fails, render a project-specific error state with a retry action. Do not silently replace projects with internships.
- If sponsorship data is unavailable, keep the project visible and omit sponsorship metadata.
- If AI analysis fails, preserve the ordinary project list and show the existing actionable AI retry message.
- Repository/GitHub URLs are omitted; they never disable or replace the internal `Xem dự án` action.

## Testing Strategy

Implementation follows test-driven development.

Backend tests cover:

- Same-school project visibility.
- Exclusion of projects from other schools.
- Exclusion of non-`in_progress` projects from the listing.
- Paid sponsorship aggregation and sponsor/funding display on project detail.
- Detail-page authorization and missing-project behavior.
- Internal canonical URLs emitted by the AI catalog source.

Frontend tests cover:

- Renamed `Dự án` tab and database-derived count.
- Absence of internship/application-position cards and labels on this page.
- Project search, category filter, status filter, reset, and result count.
- Internal `Xem dự án` navigation.
- AI recommendation navigation to the matching internal project detail page without changing the current AI analysis flow.
- Absence of repository/GitHub links from learner project cards and details.
- Project-specific loading, empty, and error states.

Manual browser verification uses the Nguyễn Hoài An account. It must confirm the expected project count, exercise each filter, open both project details, run the AI suggestion flow, verify internal navigation, and capture a screenshot of the final page.

## Out of Scope

- Deleting or migrating internship/application data.
- Removing existing internship detail or application routes elsewhere in TalentHub.
- Creating enterprise-owned projects; the current schema models school ownership plus enterprise sponsorship.
- Adding a learner project enrollment workflow.
- Inventing project locations or other unavailable metadata.
