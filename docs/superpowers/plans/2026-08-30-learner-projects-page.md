# Learner Projects Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the learner-facing opportunity listing with real same-school projects, add a complete internal project detail page, and make every AI `Xem dự án` action open that page instead of GitHub.

**Architecture:** Add a learner-owned project read boundary beside the existing ecosystem repository, with student-to-school authorization enforced in SQL for both list and detail reads. Keep the existing ecosystem tab identifiers and AI orchestration stable, replace only their learner-facing project data/view and canonical action URL, and resolve persisted AI results against the current authorized candidate URL before returning them.

**Tech Stack:** PHP 8.x, PDO/MySQL-compatible SQL with SQLite-focused repository tests, server-rendered PHP, vanilla JavaScript, Node.js built-in test runner, existing TalentHub learner CSS.

## Global Constraints

- Keep the current `projects.schoolId` schema; do not add a migration or fabricate enterprise-owned projects.
- The learner list shows only `projects.status = 'in_progress'` projects from the signed-in learner's school.
- Project cards use only the `Dự án` type label and do not add mandatory `Trường`/`Doanh nghiệp` ownership badges.
- Remove internship/application-position cards and the application drawer from this learner page only; preserve internship data and existing application routes elsewhere.
- Do not change AI scoring, Gemini prompting, consent, loading, retry, no-fit, low-fit, stale, or explanation behavior.
- The only AI behavior change is canonical navigation: `Xem dự án` must resolve to `/app/learner/project.php?id=<authorized-project-id>`.
- Never render `projects.projectUrl` or a repository/GitHub URL on learner project cards, project detail, or AI project actions.
- Show paid enterprise sponsorship details only on project detail; if sponsorship lookup fails, keep the project visible and omit sponsorship metadata.
- Preserve all unrelated and pre-existing uncommitted workspace changes; stage only files named in the current task.

---

## File Structure

- Create `app/learner/data/Contracts/ProjectRepository.php`: learner project list/detail contract.
- Create `app/learner/data/Database/DatabaseProjectRepository.php`: same-school authorization, active project reads, member counts, and optional paid sponsorship reads.
- Create `app/learner/data/ReadModel/ProjectReadModel.php`: stable learner-facing project keys, labels, dates, money, and sponsor normalization.
- Create `app/learner/includes/project-data.php`: page-level helpers that bind the authenticated student to the repository without mock project fabrication.
- Create `app/learner/project.php`: read-only internal project detail page.
- Modify `app/learner/data/bootstrap.php` and `app/learner/data/RepositoryFactory.php`: register the new read boundary.
- Modify `app/learner/includes/student-data.php`: rename the sidebar entry to `Hệ sinh thái & Dự án`.
- Modify `app/learner/ecosystem.php`: render projects instead of internships and remove application UI from this page.
- Modify `assets/js/learner.js`: keep project search/category filtering and result-count/empty-state behavior without location or application coupling.
- Modify `assets/css/learner.css`: add focused project card/detail styles using the existing design tokens.
- Modify `app/learner/ai/Sources/Database/DatabaseCatalogSource.php`: emit the internal project detail URL for real school projects.
- Modify `app/learner/ai/Service/OpportunityMatchService.php`: rehydrate stored results with current authorized candidate URLs.
- Modify `assets/js/learner-opportunity-matches.js`: accept only the internal project-detail route for project actions.
- Create `tests/learner_project_repository_test.php`, `tests/learner_projects_ui_test.js`, and `tests/learner_project_detail_ui_test.php`.
- Modify `tests/learner_ai_opportunity_candidate_test.php`, `tests/learner_ai_opportunity_service_test.php`, `tests/learner_ai_opportunity_ui_test.js`, `tests/learner_ecosystem_ui_test.js`, `tests/learner_security_contract_test.php`, and `tests/phase7_application_ui_test.js` for the new boundaries and preserved application route.

---

### Task 1: Add the authorized learner project read boundary

**Files:**
- Create: `app/learner/data/Contracts/ProjectRepository.php`
- Create: `app/learner/data/Database/DatabaseProjectRepository.php`
- Create: `app/learner/data/ReadModel/ProjectReadModel.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Create: `tests/learner_project_repository_test.php`

**Interfaces:**
- Consumes: authenticated `student_profiles.id`; existing `projects`, `classes`, `schools`, `teacher_profiles`, `users`, `project_members`, `project_sponsorships`, and `enterprises` tables.
- Produces: `ProjectRepository::listVisibleForStudent(string $studentId): array` and `ProjectRepository::findVisibleForStudent(string $studentId, string $projectId): ?array`.
- Produces normalized records with `id`, `school_id`, `school_name`, `mentor_name`, `title`, `category`, `category_label`, `description`, `funding_goal`, `start_at`, `end_at`, `status`, `status_label`, `members_count`, and `sponsorships`.

- [ ] **Step 1: Write the failing repository test**

Create an in-memory SQLite fixture with two schools, one active learner, active/draft/completed projects, duplicate active project members, paid/pledged sponsorships, and an active enterprise. Assert that only the same-school `in_progress` project is listed, member counts are not multiplied by sponsorship rows, detail returns the paid sponsor/note, another school's project is `null`, and deleting `project_sponsorships` still returns project detail with an empty sponsor list.

```php
$projects = $repository->listVisibleForStudent($studentId);
project_assert(array_column($projects, 'id') === [$sameSchoolActiveId], 'only same-school in-progress project is listed');
project_assert($projects[0]['members_count'] === 2, 'active members are counted once');

$detail = $repository->findVisibleForStudent($studentId, $sameSchoolActiveId);
project_assert(($detail['school_name'] ?? '') === 'FPT Polytechnic', 'school name is exposed');
project_assert(($detail['mentor_name'] ?? '') === 'Nguyễn Minh Anh', 'mentor user name is exposed');
project_assert(count($detail['sponsorships'] ?? []) === 1, 'only paid sponsorship is public');
project_assert(($detail['sponsorships'][0]['enterprise_name'] ?? '') === 'FPT Software', 'active sponsor is resolved');
project_assert($repository->findVisibleForStudent($studentId, $crossSchoolProjectId) === null, 'cross-school detail is hidden');

$pdo->exec('DROP TABLE project_sponsorships');
$withoutSponsors = $repository->findVisibleForStudent($studentId, $sameSchoolActiveId);
project_assert(($withoutSponsors['sponsorships'] ?? null) === [], 'sponsor failure does not hide the project');
```

- [ ] **Step 2: Run the repository test and verify the boundary is missing**

Run: `php tests/learner_project_repository_test.php`

Expected: FAIL because `ProjectRepository`, `DatabaseProjectRepository`, and `ProjectReadModel` do not exist.

- [ ] **Step 3: Add the contract and register it in the learner bootstrap/factory**

```php
interface ProjectRepository
{
    /** @return list<array<string,mixed>> */
    public function listVisibleForStudent(string $studentId): array;

    /** @return array<string,mixed>|null */
    public function findVisibleForStudent(string $studentId, string $projectId): ?array;
}
```

Add `require_once` entries for the contract, database repository, and read model in `app/learner/data/bootstrap.php`. Add this database-only factory method in `RepositoryFactory`:

```php
public function project(): ProjectRepository
{
    if ($this->source !== 'database') {
        throw new LearnerDataConfigurationException('Learner projects require the canonical database source.');
    }

    return new Database\DatabaseProjectRepository($this->pdo);
}
```

- [ ] **Step 4: Implement tenant-safe list and detail SQL**

Use the learner's class as the source of truth for school scope. Keep sponsorships in a separate best-effort query so sponsorship failure cannot erase a valid project.

```sql
SELECT p.id, p.schoolId, p.mentorTeacherId, p.title, p.category,
       p.description, p.fundingGoal, p.startAt, p.endAt, p.status,
       p.createdAt, p.updatedAt, s.name AS schoolName,
       mentor.fullName AS mentorName,
       COALESCE((SELECT COUNT(*) FROM project_members pm
                 WHERE pm.projectId = p.id AND pm.status = 'active'), 0) AS membersCount
FROM student_profiles sp
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN projects p ON p.schoolId = c.schoolId
INNER JOIN schools s ON s.id = p.schoolId AND s.status = 'active'
LEFT JOIN teacher_profiles tp ON tp.id = p.mentorTeacherId AND tp.schoolId = p.schoolId
LEFT JOIN users mentor ON mentor.id = tp.userId
WHERE sp.id = :student_id
  AND sp.studyStatus = 'active'
  AND p.status = 'in_progress'
ORDER BY p.updatedAt DESC, p.id
```

For detail, append `AND p.id = :project_id` and `LIMIT 1`. Load sponsorships only after the authorized project exists:

```sql
SELECT e.id AS enterpriseId, e.name AS enterpriseName,
       ps.amount, ps.currency, ps.note
FROM project_sponsorships ps
INNER JOIN enterprises e ON e.id = ps.enterpriseId AND e.status = 'active'
WHERE ps.projectId = :project_id AND ps.status = 'paid'
ORDER BY ps.createdAt, ps.id
```

Catch only sponsorship-query failures and normalize them to `[]`; allow core project-query failures to propagate so the page can show a project-specific error state.

- [ ] **Step 5: Implement the read model**

```php
public static function project(array $record): array
{
    $record['category_label'] = learner_activity_category_label((string) ($record['category'] ?? ''));
    $record['status_label'] = match ((string) ($record['status'] ?? '')) {
        'in_progress' => 'Đang triển khai',
        'completed' => 'Đã hoàn thành',
        default => 'Chưa xác định',
    };
    $record['members_count'] = max(0, (int) ($record['members_count'] ?? 0));
    foreach (['start_at', 'end_at'] as $dateField) {
        $labelField = $dateField . '_label';
        $rawDate = trim((string) ($record[$dateField] ?? ''));
        try { $record[$labelField] = $rawDate === '' ? '' : (new \DateTimeImmutable($rawDate))->format('d/m/Y'); }
        catch (\Throwable) { $record[$labelField] = ''; }
    }
    $record['sponsorships'] = array_values(is_array($record['sponsorships'] ?? null) ? $record['sponsorships'] : []);
    $record['raised_amount'] = array_reduce(
        $record['sponsorships'],
        static fn (float $total, array $sponsor): float => $total + (float) ($sponsor['amount'] ?? 0),
        0.0,
    );
    unset($record['project_url']);
    return $record;
}

/** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
public static function projects(array $records): array
{
    return array_map([self::class, 'project'], $records);
}
```

- [ ] **Step 6: Run the repository test**

Run: `php tests/learner_project_repository_test.php`

Expected: `learner_project_repository_test: OK`

- [ ] **Step 7: Commit the read boundary**

```powershell
git add -- app/learner/data/Contracts/ProjectRepository.php app/learner/data/Database/DatabaseProjectRepository.php app/learner/data/ReadModel/ProjectReadModel.php app/learner/data/bootstrap.php app/learner/data/RepositoryFactory.php tests/learner_project_repository_test.php
git commit -m "feat: add learner project read model"
```

---

### Task 2: Replace the learner opportunity listing with projects

**Files:**
- Create: `app/learner/includes/project-data.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/ecosystem.php`
- Modify: `assets/js/learner.js`
- Modify: `assets/css/learner.css`
- Create: `tests/learner_projects_ui_test.js`
- Modify: `tests/learner_ecosystem_ui_test.js`
- Modify: `tests/phase7_application_ui_test.js`

**Interfaces:**
- Consumes: Task 1 `RepositoryFactory::project()` and `ProjectReadModel::projects()`.
- Produces: `learner_projects(): array`, project-only server-rendered cards, dynamic category options, and internal `project.php?id=...` links.
- Preserves: DOM tab key `opportunities` and `data-opportunity-matches` so the current AI controller mounts without an AI-flow rewrite.

- [ ] **Step 1: Write failing source/UI tests**

```js
assert.match(page, />\s*Dự án\s*<span class="learner-count-badge">/);
assert.match(page, /\$projects\s*=\s*learner_projects\(\)/);
assert.match(page, /data-ecosystem-item-type="project"/);
assert.match(page, /project\.php\?id=<\?= learner_escape\(\$project\['id'\]\); \?>/);
assert.match(page, />Dự án<\/span>/);
assert.doesNotMatch(page, /data-ecosystem-item-type="internship"/);
assert.doesNotMatch(page, />\s*Ứng tuyển ngay\s*</);
assert.doesNotMatch(page, /learner-application-drawer|Hồ sơ đã ứng tuyển/);
assert.doesNotMatch(page, /projectUrl|project_url|github\.com/i);
assert.doesNotMatch(page, /data-ecosystem-filter="location"/);
assert.match(page, /Chưa có dự án đang triển khai/);
assert.match(page, /Không thể tải danh sách dự án/);
assert.match(page, /location\.reload\(\)/);
```

Add a functional filter assertion using the existing accent-insensitive helper:

```js
assert.equal(ecosystemItemMatches(
    { search: 'EcoSmart AI FPT Polytechnic Kỹ thuật', field: 'Kỹ thuật', location: '' },
    { query: 'ecosmart', field: 'Kỹ thuật' },
), true);
```

- [ ] **Step 2: Run the UI tests and verify the old opportunity UI fails**

Run: `node --test tests/learner_projects_ui_test.js tests/learner_ecosystem_ui_test.js tests/phase7_application_ui_test.js`

Expected: FAIL on the old `Cơ hội`, internship cards, location filter, and application drawer assertions.

- [ ] **Step 3: Bind the authenticated student to project reads**

Create `app/learner/includes/project-data.php`:

```php
function learner_projects(): array
{
    if (learner_repository_factory()->source() !== 'database') return [];
    return \TalentHub\Learner\Data\ReadModel\ProjectReadModel::projects(
        learner_repository_factory()->project()->listVisibleForStudent(learner_current_student_id())
    );
}

function learner_project(string $projectId): ?array
{
    if (learner_repository_factory()->source() !== 'database') return null;
    $record = learner_repository_factory()->project()->findVisibleForStudent(
        learner_current_student_id(),
        $projectId,
    );
    return $record === null ? null : \TalentHub\Learner\Data\ReadModel\ProjectReadModel::project($record);
}
```

- [ ] **Step 4: Replace only the second ecosystem tab's content**

Keep `$allowedTabs = ['enterprises', 'opportunities']`, but change visible copy and data:

```php
$projectLoadFailed = false;
try {
    $projects = learner_projects();
} catch (Throwable) {
    $projects = [];
    $projectLoadFailed = true;
}
$projectCategories = [];
foreach ($projects as $project) {
    $projectCategories[(string) $project['category_label']] = true;
}
ksort($projectCategories, SORT_NATURAL | SORT_FLAG_CASE);
```

Render each card with stable project-only metadata:

```php
<article class="learner-project-card learner-card"
    data-ecosystem-item
    data-ecosystem-item-type="project"
    data-search="<?= learner_escape(implode(' ', [$project['title'], $project['description'], $project['category_label'], $project['school_name']])); ?>"
    data-field="<?= learner_escape($project['category_label']); ?>">
    <div class="learner-project-card__top">
        <span class="learner-badge learner-badge--primary">Dự án</span>
        <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($project['status_label']); ?></span>
    </div>
    <h3><?= learner_escape($project['title']); ?></h3>
    <p><?= learner_icon('building', 16); ?> <?= learner_escape($project['school_name']); ?></p>
    <div class="learner-meta-list">
        <span><?= learner_icon('briefcase', 16); ?> <?= learner_escape($project['category_label']); ?></span>
        <span><?= learner_icon('users', 16); ?> <?= learner_escape($project['members_count']); ?> thành viên</span>
        <?php if (($project['end_at_label'] ?? '') !== ''): ?>
            <span><?= learner_icon('calendar', 16); ?> Đến <?= learner_escape($project['end_at_label']); ?></span>
        <?php endif; ?>
    </div>
    <a class="learner-btn learner-btn--primary learner-btn--block" href="project.php?id=<?= learner_escape($project['id']); ?>">Xem chi tiết dự án</a>
</article>
```

Remove `$applications`, the hero application button, the entire application drawer, internship cards, location filter, and internship-specific empty copy from `ecosystem.php`. Do not delete `opportunity.php` or application services. When `$projectLoadFailed` is true, render `Không thể tải danh sách dự án` and a `Thử lại` button that calls `location.reload()`; otherwise distinguish the source-empty message `Chưa có dự án đang triển khai` from the existing filter-empty message.

- [ ] **Step 5: Update search/filter behavior and project styles**

Keep `ecosystemItemMatches()` backward-compatible for enterprise cards; missing `location` must act as no location constraint. Add result count synchronization for the active panel:

```js
const resultCount = activePanel.querySelector('[data-ecosystem-result-count]');
if (resultCount) resultCount.textContent = String(visibleCount);
```

Add focused `.learner-project-card`, `.learner-project-card__top`, and responsive grid rules using existing design tokens:

```css
.learner-project-card { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.learner-project-card__top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.learner-project-card h3 { margin: 0; line-height: 1.35; }
.learner-project-card .learner-btn { margin-top: auto; }
.learner-project-card:focus-within { outline: 3px solid var(--primary); outline-offset: 3px; }
@media (max-width: 720px) {
    .learner-project-card__top { align-items: flex-start; flex-direction: column; }
}
```

- [ ] **Step 6: Run the project list tests**

Run: `node --test tests/learner_projects_ui_test.js tests/learner_ecosystem_ui_test.js tests/phase7_application_ui_test.js`

Expected: all tests PASS, with the phase 7 test still proving `opportunity.php` and the application API exist outside the ecosystem page.

- [ ] **Step 7: Commit the project-only page**

```powershell
git add -- app/learner/includes/project-data.php app/learner/includes/student-data.php app/learner/ecosystem.php assets/js/learner.js assets/css/learner.css tests/learner_projects_ui_test.js tests/learner_ecosystem_ui_test.js tests/phase7_application_ui_test.js
git commit -m "feat: replace learner opportunities with projects"
```

---

### Task 3: Add the complete internal project detail page

**Files:**
- Create: `app/learner/project.php`
- Modify: `assets/css/learner.css`
- Create: `tests/learner_project_detail_ui_test.php`
- Modify: `tests/learner_security_contract_test.php`

**Interfaces:**
- Consumes: `learner_project(string $projectId): ?array` from Task 2.
- Produces: GET `/app/learner/project.php?id=<uuid>` as the canonical read-only learner project destination.

- [ ] **Step 1: Write the failing detail-page contract tests**

```php
$assert(str_contains($detailPage, "learner_project((string) (\$_GET['id'] ?? ''))"), 'detail uses student-scoped lookup');
$assert(str_contains($detailPage, 'Chi tiết dự án'), 'detail has project-specific title');
$assert(str_contains($detailPage, 'Mô tả dự án'), 'detail renders description');
$assert(str_contains($detailPage, 'Doanh nghiệp đồng hành'), 'detail supports paid sponsor section');
$assert(str_contains($detailPage, "foreach (\$project['sponsorships'] as \$sponsorship)"), 'detail renders normalized sponsors');
$assert(!preg_match('/projectUrl|project_url|github\.com/i', $detailPage), 'detail never renders repository links');
$assert(!str_contains($detailPage, 'Ứng tuyển'), 'detail has no application action');
$assert(!str_contains($detailPage, 'Tham gia dự án'), 'detail does not imply an enrollment workflow');
```

- [ ] **Step 2: Run the detail tests and verify the route is missing**

Run: `php tests/learner_project_detail_ui_test.php`

Expected: FAIL because `app/learner/project.php` does not exist.

- [ ] **Step 3: Implement the read-only page and not-found state**

Use `student-data.php`, `icons.php`, and `project-data.php`, then load only by the student-scoped helper:

```php
$projectId = (string) ($_GET['id'] ?? '');
$project = learner_project($projectId);
$pageTitle = $project ? 'Chi tiết dự án' : 'Không tìm thấy dự án';
$currentRoute = '/app/learner/ecosystem.php';
```

The page must render:

- Breadcrumbs to `Hệ sinh thái & Dự án` and `Dự án`.
- `Dự án`, status, title, school, category, mentor, start/end dates, and member count.
- `Mô tả dự án` with the stored description.
- Funding goal and raised total when either is present.
- A `Doanh nghiệp đồng hành/Tài trợ` section only when `sponsorships !== []`, including enterprise name, formatted amount/currency, and non-empty note.
- A project-specific not-found card for invalid, missing, draft, archived, completed, or cross-school identifiers.
- A back-to-projects button; no join/apply/save/repository button.

Use safe formatting helpers and render optional sponsorships without repository links:

```php
function learner_project_date(?string $value): string
{
    if ($value === null || trim($value) === '') return '';
    try { return (new DateTimeImmutable($value))->format('d/m/Y'); }
    catch (Throwable) { return ''; }
}

function learner_project_money(mixed $amount, string $currency = 'VND'): string
{
    return number_format((float) $amount, 0, ',', '.') . ' ' . strtoupper($currency ?: 'VND');
}
```

```php
<?php if (($project['sponsorships'] ?? []) !== []): ?>
<section class="learner-card learner-project-detail__sponsors" aria-labelledby="project-sponsors-title">
    <h2 id="project-sponsors-title">Doanh nghiệp đồng hành/Tài trợ</h2>
    <?php foreach ($project['sponsorships'] as $sponsorship): ?>
        <article>
            <strong><?= learner_escape($sponsorship['enterprise_name']); ?></strong>
            <span><?= learner_escape(learner_project_money($sponsorship['amount'], $sponsorship['currency'])); ?></span>
            <?php if (trim((string) ($sponsorship['note'] ?? '')) !== ''): ?>
                <p><?= learner_escape($sponsorship['note']); ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
```

- [ ] **Step 4: Add responsive detail styles**

Reuse the existing opportunity hero/layout visual grammar but use distinct selectors:

```css
.learner-project-detail__facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
.learner-project-detail__body { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(17rem, .8fr); gap: 1.25rem; }
.learner-project-detail__sponsors article { padding: 1rem 0; border-top: 1px solid var(--border); }
.learner-project-detail__sponsors article:first-of-type { border-top: 0; }
@media (max-width: 900px) {
    .learner-project-detail__facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .learner-project-detail__body { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .learner-project-detail__facts { grid-template-columns: 1fr; }
}
```

- [ ] **Step 5: Run detail and security tests**

Run: `php tests/learner_project_detail_ui_test.php`

Expected: `learner_project_detail_ui_test: OK`

Run: `php tests/learner_security_contract_test.php`

Expected: `learner_security_contract_test: OK`

- [ ] **Step 6: Commit the detail route**

```powershell
git add -- app/learner/project.php assets/css/learner.css tests/learner_project_detail_ui_test.php tests/learner_security_contract_test.php
git commit -m "feat: add learner project detail page"
```

---

### Task 4: Point current and persisted AI project actions to internal detail

**Files:**
- Modify: `app/learner/ai/Sources/Database/DatabaseCatalogSource.php`
- Modify: `app/learner/ai/Service/OpportunityMatchService.php`
- Modify: `assets/js/learner-opportunity-matches.js`
- Modify: `tests/learner_ai_opportunity_candidate_test.php`
- Modify: `tests/learner_ai_opportunity_service_test.php`
- Modify: `tests/learner_ai_opportunity_ui_test.js`

**Interfaces:**
- Consumes: current authorized `OpportunityCandidate` objects already produced by the unchanged AI eligibility flow.
- Produces: `canonical_url = /app/learner/project.php?id=<project-id>` for every clickable project result; unknown/stale unauthorized IDs produce no clickable URL.
- Preserves: all scores, ranks, narratives, fit/gap reasons, skills-to-develop, states, and provider calls.

- [ ] **Step 1: Add failing canonical-navigation tests**

Update the database catalog test to seed a fake GitHub `projectUrl` and assert it is ignored:

```php
$project = array_values(array_filter($catalogRows, static fn (array $row): bool => ($row['catalog_id'] ?? '') === $projectId))[0];
candidate_assert(
    ($project['url'] ?? '') === '/app/learner/project.php?id=' . rawurlencode($projectId),
    'school project catalog always emits internal detail URL',
);
candidate_assert(!str_contains((string) $project['url'], 'github.com'), 'GitHub demo URL is never canonical');
```

Add a service test with a stored `actionJson` containing GitHub but a current authorized candidate containing the internal route. Assert the mapped response uses the current internal route. Add another stored catalog ID absent from current candidates and assert it is not clickable.

Add JS tests:

```js
assert.equal(isSafeInternalProjectUrl('/app/learner/project.php?id=50000000-0000-4000-8000-000000000001'), true);
assert.equal(isSafeInternalProjectUrl('https://github.com/talenthub-demo/example'), false);
assert.equal(normalizeReadyItems([validItem({ canonical_url: 'https://github.com/demo/x' })])[0].canonical_url, '');
```

- [ ] **Step 2: Run focused AI tests and verify old GitHub behavior fails**

Run: `php tests/learner_ai_opportunity_candidate_test.php`

Run: `php tests/learner_ai_opportunity_service_test.php`

Run: `node --test tests/learner_ai_opportunity_ui_test.js`

Expected: FAIL until backend and frontend canonical URL handling is updated.

- [ ] **Step 3: Make the database catalog URL unconditionally internal**

Replace the `projectUrl` fallback logic in `DatabaseCatalogSource::schoolProjects()`:

```php
$url = '/app/learner/project.php?id=' . rawurlencode($id);
```

Do not select, parse, validate, or expose `projectUrl` for learner AI evidence.

- [ ] **Step 4: Rehydrate persisted result URLs from current authorized candidates**

Change `mapReady`, `mapStale`, and `mapItems` to accept the current candidates and build an allow-listed URL map:

```php
/** @param list<OpportunityCandidate> $candidates @return array<string,string> */
private function canonicalUrls(array $candidates): array
{
    $urls = [];
    foreach ($candidates as $candidate) {
        if ($candidate->catalogType() === 'project') {
            $urls[$candidate->catalogId()] = $candidate->canonicalUrl();
        }
    }
    return $urls;
}
```

In `mapItems`, replace the persisted action URL with the current map:

```php
$catalogId = (string) ($item['catalogId'] ?? '');
'canonical_url' => (string) ($canonicalUrls[$catalogId] ?? ''),
```

Pass the same current candidate list already computed by `latest()`/`generate()` into every `mapReady()` and `mapStale()` call. Do not alter candidate scoring or model inputs.

- [ ] **Step 5: Restrict the browser renderer to the internal project route**

```js
function isSafeInternalProjectUrl(value) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) return false;
    try {
        const url = new URL(value, 'https://talenthub.invalid');
        return url.origin === 'https://talenthub.invalid'
            && url.pathname === '/app/learner/project.php'
            && /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(url.searchParams.get('id') || '');
    } catch {
        return false;
    }
}
```

`normalizeReadyItems()` must retain the URL only when this predicate is true and always set `canonical_url_external: false`. Keep disabled `Xem dự án` behavior for missing/unauthorized canonical URLs.

- [ ] **Step 6: Run focused AI tests**

Run: `php tests/learner_ai_opportunity_candidate_test.php`

Expected: `learner_ai_opportunity_candidate_test: OK`

Run: `php tests/learner_ai_opportunity_service_test.php`

Expected: `learner_ai_opportunity_service_test: OK`

Run: `node --test tests/learner_ai_opportunity_ui_test.js`

Expected: all subtests PASS.

- [ ] **Step 7: Commit only the AI navigation delta**

```powershell
git add -- app/learner/ai/Sources/Database/DatabaseCatalogSource.php app/learner/ai/Service/OpportunityMatchService.php assets/js/learner-opportunity-matches.js tests/learner_ai_opportunity_candidate_test.php tests/learner_ai_opportunity_service_test.php tests/learner_ai_opportunity_ui_test.js
git commit -m "fix: route AI projects to internal detail"
```

---

### Task 5: Run regression, live-data, and visual verification

**Files:**
- Modify only if verification exposes a scoped defect: files already listed in Tasks 1-4 and their focused tests.
- Evidence output: `artifacts/learner-projects-page.png` and `artifacts/learner-project-detail.png`.

**Interfaces:**
- Consumes: completed project list/detail and AI navigation behavior.
- Produces: syntax, focused-suite, live authorization, and browser evidence for completion.

- [ ] **Step 1: Run PHP syntax checks on every changed PHP file**

```powershell
$phpFiles = @(
  'app/learner/data/Contracts/ProjectRepository.php',
  'app/learner/data/Database/DatabaseProjectRepository.php',
  'app/learner/data/ReadModel/ProjectReadModel.php',
  'app/learner/includes/project-data.php',
  'app/learner/project.php',
  'app/learner/ecosystem.php',
  'app/learner/ai/Sources/Database/DatabaseCatalogSource.php',
  'app/learner/ai/Service/OpportunityMatchService.php'
)
$phpFiles | ForEach-Object { php -l $_ }
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 2: Run the focused project and AI regression suite**

```powershell
php tests/learner_project_repository_test.php
php tests/learner_project_detail_ui_test.php
php tests/learner_security_contract_test.php
php tests/learner_ai_opportunity_candidate_test.php
php tests/learner_ai_opportunity_service_test.php
node --test tests/learner_projects_ui_test.js tests/learner_ecosystem_ui_test.js tests/phase7_application_ui_test.js tests/learner_ai_opportunity_ui_test.js
```

Expected: all PHP scripts print `OK`; Node reports zero failed tests.

- [ ] **Step 3: Confirm the implementation contains no learner-facing GitHub path**

Run:

```powershell
rg -n -i "github\.com|projectUrl|project_url" app/learner/ecosystem.php app/learner/project.php assets/js/learner-opportunity-matches.js
```

Expected: no matches.

- [ ] **Step 4: Start the local app and verify with the Nguyễn Hoài An account**

Run: `php -S 127.0.0.1:8080 -t D:\TalentHub`

Open `/app/learner/ecosystem.php?tab=opportunities` after signing in as Nguyễn Hoài An. Confirm:

- The visible tab says `Dự án` and the database-derived count is 2 for the approved fixture/account.
- No internship position, slots, application button, or application drawer appears in the project tab.
- Search and category filter update the grid, count, and empty state.
- Both ordinary project cards open `/app/learner/project.php?id=...`.
- Each detail page contains the correct school, mentor, dates, category, member count, description, funding goal, and paid enterprise sponsorship/note.
- No GitHub/repository link appears.
- Running the existing AI suggestion preserves its current analysis and states; every enabled `Xem dự án` opens the matching internal detail page in the same tab.
- Desktop and narrow mobile widths have no clipping, overlap, horizontal scrolling, or unreachable keyboard focus.

Capture the final list and one sponsored detail page to the two evidence paths above.

- [ ] **Step 5: Inspect scope and whitespace before completion**

Run: `git diff --check`

Expected: no whitespace errors.

Run: `git status --short`

Expected: only scoped implementation/test files plus pre-existing unrelated user changes; no accidental staging of scratch files or unrelated artifacts.

- [ ] **Step 6: Commit any verification-only fixes**

If Task 5 required scoped fixes, stage only the named files and commit:

```powershell
git commit -m "test: verify learner projects experience"
```

If no fix was required, do not create an empty commit.
